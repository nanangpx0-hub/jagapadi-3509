import 'package:flutter/material.dart';
import '../../../core/api_client.dart';

/// State dashboard yang jelas untuk UI:
/// - [initial] : belum pernah dimuat
/// - [loading] : request sedang berjalan
/// - [success] : data terbaru dari server
/// - [empty]   : berhasil dimuat, tetapi semua angka nol (data valid, bukan error)
/// - [error]   : gagal dimuat dan tidak ada data cache — TIDAK ditampilkan
///               sebagai angka nol
/// - [offline] : gagal karena jaringan, tapi data cache tersedia (data lama)
/// - [stale]   : gagal karena error server, tapi data cache tersedia
enum DashboardViewState { initial, loading, success, empty, error, offline, stale }

class DashboardStats {
  final int totalAktif;
  final int totalDraf;
  final int totalDitolak;

  DashboardStats({
    required this.totalAktif,
    required this.totalDraf,
    required this.totalDitolak,
  });

  factory DashboardStats.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return DashboardStats(totalAktif: 0, totalDraf: 0, totalDitolak: 0);
    }
    return DashboardStats(
      totalAktif: json['total_aktif'] as int? ?? json['aktif'] as int? ?? 0,
      totalDraf: json['total_draf'] as int? ?? json['draf'] as int? ?? 0,
      totalDitolak: json['total_ditolak'] as int? ?? json['ditolak'] as int? ?? 0,
    );
  }

  bool get allZero =>
      totalAktif == 0 && totalDraf == 0 && totalDitolak == 0;
}

class DashboardProvider extends ChangeNotifier {
  final ApiClient api;
  bool _loading = false;
  String? _error;
  DashboardStats? _hamaStats;
  DashboardStats? _irigasiStats;
  int _tahun = DateTime.now().year;
  DashboardViewState _state = DashboardViewState.initial;
  DateTime? _lastUpdatedAt;

  DashboardProvider(this.api);

  bool get loading => _loading;
  String? get error => _error;
  DashboardStats? get hamaStats => _hamaStats;
  DashboardStats? get irigasiStats => _irigasiStats;
  int get tahun => _tahun;
  DashboardViewState get state => _state;
  DateTime? get lastUpdatedAt => _lastUpdatedAt;

  bool get hasCachedData => _hamaStats != null || _irigasiStats != null;

  /// Bersihkan seluruh data — dipanggil saat logout agar data user lama
  /// tidak tampil kepada user baru.
  void reset() {
    _hamaStats = null;
    _irigasiStats = null;
    _error = null;
    _state = DashboardViewState.initial;
    _lastUpdatedAt = null;
    _loading = false;
    notifyListeners();
  }

  Future<void> loadStats({int? tahun}) async {
    if (tahun != null) _tahun = tahun;
    _loading = true;
    _error = null;
    _state = DashboardViewState.loading;
    notifyListeners();

    final res = await api.get('/dashboard/stats', queryParams: {'tahun': _tahun.toString()});
    _loading = false;

    if (res.success && res.data != null) {
      final data = res.data!;
      final hamaRaw = data['hama'] as Map<String, dynamic>? ?? data['laporan_hama'] as Map<String, dynamic>?;
      final irigasiRaw = data['irigasi'] as Map<String, dynamic>? ?? data['laporan_irigasi'] as Map<String, dynamic>?;

      _hamaStats = DashboardStats.fromJson(hamaRaw);
      _irigasiStats = DashboardStats.fromJson(irigasiRaw);
      _lastUpdatedAt = DateTime.now();

      final totalAktif = (_hamaStats?.totalAktif ?? 0) + (_irigasiStats?.totalAktif ?? 0);
      final totalDraf = (_hamaStats?.totalDraf ?? 0) + (_irigasiStats?.totalDraf ?? 0);
      final totalDitolak = (_hamaStats?.totalDitolak ?? 0) + (_irigasiStats?.totalDitolak ?? 0);
      _state = (totalAktif == 0 && totalDraf == 0 && totalDitolak == 0)
          ? DashboardViewState.empty
          : DashboardViewState.success;
    } else {
      // Error: jangan tampilkan angka nol. Bila ada cache, tampilkan data
      // lama dengan penanda offline/stale + tombol retry.
      _error = res.message ?? 'Gagal memuat data statistik';
      final isNetwork = res.isNetworkError || res.isTimeoutError || res.isSslError;
      if (hasCachedData) {
        _state = isNetwork ? DashboardViewState.offline : DashboardViewState.stale;
      } else {
        _state = DashboardViewState.error;
      }
    }
    notifyListeners();
  }
}
