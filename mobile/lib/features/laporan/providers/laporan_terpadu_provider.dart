import 'package:flutter/foundation.dart';
import '../../../core/api_client.dart';
import '../models/laporan_item.dart';

/// Filter state yang dibawa provider, immutable per perubahan.
class LaporanFilter {
  final String jenisKey; // 'semua' | 'hama' | 'irigasi'
  final String statusKey; // 'all' | 'Draf' | 'Submitted' | 'Diverifikasi' | 'Ditolak'
  final String? tanggalDari; // YYYY-MM-DD atau null
  final String? tanggalSampai; // YYYY-MM-DD atau null
  final String? searchQuery;

  const LaporanFilter({
    this.jenisKey = 'semua',
    this.statusKey = 'all',
    this.tanggalDari,
    this.tanggalSampai,
    this.searchQuery,
  });

  LaporanFilter copyWith({
    String? jenisKey,
    String? statusKey,
    String? tanggalDari,
    String? tanggalSampai,
    String? searchQuery,
    bool clearSearch = false,
    bool clearTanggal = false,
  }) {
    return LaporanFilter(
      jenisKey: jenisKey ?? this.jenisKey,
      statusKey: statusKey ?? this.statusKey,
      tanggalDari: clearTanggal ? null : tanggalDari ?? this.tanggalDari,
      tanggalSampai: clearTanggal ? null : tanggalSampai ?? this.tanggalSampai,
      searchQuery: clearSearch ? null : searchQuery ?? this.searchQuery,
    );
  }

  bool get hasActiveDateFilter =>
      tanggalDari != null || tanggalSampai != null;

  bool get hasActiveFilter =>
      jenisKey != 'semua' ||
      statusKey != 'all' ||
      hasActiveDateFilter ||
      (searchQuery != null && searchQuery!.isNotEmpty);
}

/// Provider terpadu untuk halaman laporan gabungan (hama + irigasi).
///
/// Melakukan dua fetch paralel (hama & irigasi) lalu merge dan urutkan
/// berdasarkan tanggal descending. Pagination menggunakan cursor sederhana
/// (page per tipe) dan berhenti saat kedua sumber habis.
class LaporanTerpaduProvider extends ChangeNotifier {
  final ApiClient api;

  LaporanTerpaduProvider(this.api);

  // ── State ──────────────────────────────────────────────────────────────────
  List<LaporanItem> _list = [];
  bool _loading = false;
  bool _loadingMore = false;
  String? _error;
  LaporanFilter _filter = const LaporanFilter();

  int _hamaPage = 1;
  int _irigasiPage = 1;
  int _hamaTotal = 0;
  int _irigasiTotal = 0;
  static const int _pageSize = 15;

  // ── Getters ────────────────────────────────────────────────────────────────
  List<LaporanItem> get list => _list;
  bool get loading => _loading;
  bool get loadingMore => _loadingMore;
  String? get error => _error;
  LaporanFilter get filter => _filter;

  /// True bila masih ada data yang belum dimuat dari setidaknya satu sumber.
  ///
  /// Bug fix: sebelumnya selalu `true` sebelum fetch pertama karena `_hamaPage == 1`
  /// dianggap "belum diketahui". Sekarang menggunakan flag `_initialLoadDone`
  /// sehingga `loadMore()` tidak terpanggil berulang sebelum data pertama dimuat.
  bool _initialLoadDone = false;

  bool get hasMore {
    if (!_initialLoadDone) return false; // belum fetch pertama — jangan trigger loadMore
    final hamaHasMore    = (_hamaPage - 1)    * _pageSize < _hamaTotal;
    final irigasiHasMore = (_irigasiPage - 1) * _pageSize < _irigasiTotal;
    if (_filter.jenisKey == 'hama')    return hamaHasMore;
    if (_filter.jenisKey == 'irigasi') return irigasiHasMore;
    return hamaHasMore || irigasiHasMore;
  }

  int get totalCount => _hamaTotal + _irigasiTotal;

  // ── Public API ─────────────────────────────────────────────────────────────

  /// Muat ulang dari awal dengan filter saat ini.
  Future<void> refresh() => _load(reset: true);

  /// Terapkan filter baru dan muat ulang.
  Future<void> applyFilter(LaporanFilter filter) {
    _filter = filter;
    return _load(reset: true);
  }

  /// Muat halaman berikutnya (infinite scroll).
  Future<void> loadMore() {
    if (_loadingMore || !hasMore) return Future.value();
    return _load(reset: false);
  }

  // ── Private Logic ──────────────────────────────────────────────────────────

  Future<void> _load({required bool reset}) async {
    if (reset) {
      _hamaPage     = 1;
      _irigasiPage  = 1;
      _hamaTotal    = 0;
      _irigasiTotal = 0;
      _list         = [];
      _error        = null;
      _initialLoadDone = false; // reset flag sebelum fetch ulang
      _loading      = true;
    } else {
      _loadingMore = true;
    }
    notifyListeners();

    try {
      final futures = <Future<_FetchResult>>[];
      final jenis = _filter.jenisKey;

      if (jenis == 'semua' || jenis == 'hama') {
        futures.add(_fetchHama());
      }
      if (jenis == 'semua' || jenis == 'irigasi') {
        futures.add(_fetchIrigasi());
      }

      final results = await Future.wait(futures);

      // Proses hasil
      for (final r in results) {
        if (r.error != null) {
          // Tetap tampilkan data parsial; error hanya fatal jika list kosong
          _error = r.error;
        } else {
          _list.addAll(r.items);
          if (r.jenis == JenisLaporan.hama) {
            _hamaTotal = r.total;
            _hamaPage++;
          } else {
            _irigasiTotal = r.total;
            _irigasiPage++;
          }
        }
      }

      // Urutkan gabungan: tanggal descending, lalu ID descending
      _list.sort((a, b) {
        final dateCmp = (b.tanggal ?? '').compareTo(a.tanggal ?? '');
        if (dateCmp != 0) return dateCmp;
        return b.id.compareTo(a.id);
      });

      // Jika list kosong dan ada error → tampilkan error state
      if (_list.isEmpty && _error == null) {
        _error = null; // biarkan empty state ditangani UI
      } else if (_list.isNotEmpty) {
        _error = null; // partial load dianggap sukses
      }
    } catch (e) {
      _error = 'Terjadi kesalahan: $e';
    } finally {
      _loading         = false;
      _loadingMore     = false;
      _initialLoadDone = true; // tandai fetch pertama selesai
      notifyListeners();
    }
  }

  Future<_FetchResult> _fetchHama() async {
    final q = _buildQuery();
    q['page'] = _hamaPage;
    try {
      final res = await api.get('/laporan-hama', queryParams: q);
      if (res.success && res.data != null) {
        final raw = res.data!['data'] as List<dynamic>? ?? [];
        final items = raw
            .whereType<Map<String, dynamic>>()
            .map(LaporanItem.fromHamaJson)
            .toList();
        final meta = res.data!['meta'] as Map<String, dynamic>?;
        return _FetchResult(
          jenis: JenisLaporan.hama,
          items: items,
          total: meta?['total'] as int? ?? items.length,
        );
      }
      return _FetchResult(
        jenis: JenisLaporan.hama,
        items: const [],
        total: 0,
        error: _parseError(res),
      );
    } catch (e) {
      return _FetchResult(
        jenis: JenisLaporan.hama,
        items: const [],
        total: 0,
        error: _networkError(e),
      );
    }
  }

  Future<_FetchResult> _fetchIrigasi() async {
    final q = _buildQuery();
    q['page'] = _irigasiPage;
    try {
      final res = await api.get('/laporan-irigasi', queryParams: q);
      if (res.success && res.data != null) {
        final raw = res.data!['data'] as List<dynamic>? ?? [];
        final items = raw
            .whereType<Map<String, dynamic>>()
            .map(LaporanItem.fromIrigasiJson)
            .toList();
        final meta = res.data!['meta'] as Map<String, dynamic>?;
        return _FetchResult(
          jenis: JenisLaporan.irigasi,
          items: items,
          total: meta?['total'] as int? ?? items.length,
        );
      }
      return _FetchResult(
        jenis: JenisLaporan.irigasi,
        items: const [],
        total: 0,
        error: _parseError(res),
      );
    } catch (e) {
      return _FetchResult(
        jenis: JenisLaporan.irigasi,
        items: const [],
        total: 0,
        error: _networkError(e),
      );
    }
  }

  Map<String, dynamic> _buildQuery() {
    final q = <String, dynamic>{
      'limit': _pageSize,
      'include_draft': 'true',
    };
    if (_filter.statusKey != 'all') q['status'] = _filter.statusKey;
    if (_filter.tanggalDari != null) q['tanggal_dari'] = _filter.tanggalDari;
    if (_filter.tanggalSampai != null) {
      q['tanggal_sampai'] = _filter.tanggalSampai;
    }
    if (_filter.searchQuery != null && _filter.searchQuery!.isNotEmpty) {
      q['q'] = _filter.searchQuery;
    }
    return q;
  }

  String _parseError(ApiResponse<Map<String, dynamic>> res) {
    if (res.error == 'NetworkError') {
      return 'Koneksi bermasalah. Periksa jaringan internet Anda.';
    }
    if (res.statusCode == 404) return 'Data tidak ditemukan.';
    if (res.statusCode == 401) return 'Sesi berakhir. Silakan login kembali.';
    if (res.statusCode >= 500) {
      return 'Server sedang bermasalah. Coba lagi nanti.';
    }
    return res.message ?? 'Gagal memuat data.';
  }

  String _networkError(Object e) {
    final msg = e.toString().toLowerCase();
    if (msg.contains('timeout')) {
      return 'Koneksi timeout. Jaringan terlalu lambat atau tidak stabil.';
    }
    if (msg.contains('socket') || msg.contains('connection')) {
      return 'Tidak dapat terhubung ke server. Periksa koneksi internet.';
    }
    return 'Terjadi kesalahan jaringan. Coba lagi.';
  }
}

// ── Data class internal ────────────────────────────────────────────────────

class _FetchResult {
  final JenisLaporan jenis;
  final List<LaporanItem> items;
  final int total;
  final String? error;

  const _FetchResult({
    required this.jenis,
    required this.items,
    required this.total,
    this.error,
  });
}
