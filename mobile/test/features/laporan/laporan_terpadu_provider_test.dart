import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/features/laporan/models/laporan_item.dart';
import 'package:jagapadi_mobile/features/laporan/providers/laporan_terpadu_provider.dart';

// ── Stub ApiClient ───────────────────────────────────────────────────────────
// Kita menggunakan implementasi stub manual agar tidak memerlukan build_runner.

class _StubApiClient extends ApiClient {
  ApiResponse<Map<String, dynamic>> Function(
      String path, Map<String, dynamic>? q)? onGet;

  _StubApiClient() : super();

  @override
  Future<ApiResponse<Map<String, dynamic>>> get(
    String path, {
    Map<String, dynamic>? queryParams,
  }) async {
    return onGet?.call(path, queryParams) ??
        ApiResponse(
          success: false,
          message: 'Stub tidak dikonfigurasi',
          statusCode: 500,
        );
  }
}

// ── Helper builders ──────────────────────────────────────────────────────────

ApiResponse<Map<String, dynamic>> _hamaResponse({
  List<Map<String, dynamic>>? items,
  int total = 0,
}) {
  return ApiResponse(
    success: true,
    statusCode: 200,
    data: {
      'data': items ?? [],
      'meta': {'total': total, 'page': 1, 'per_page': 15},
    },
  );
}

ApiResponse<Map<String, dynamic>> _realListResponse({
  required List<Map<String, dynamic>> items,
  required int total,
}) {
  return ApiResponse<Map<String, dynamic>>.fromJson(
    {
      'success': true,
      'data': items,
      'meta': {'total': total, 'page': 1, 'limit': 15},
    },
    200,
  );
}

ApiResponse<Map<String, dynamic>> _errorResponse(String msg, int code) {
  return ApiResponse(
    success: false,
    message: msg,
    statusCode: code,
  );
}

Map<String, dynamic> _hamaItem({
  int id = 1,
  String status = 'Submitted',
  String tanggal = '2026-08-11',
  String? nomor = 'LH-20260811-0001',
}) =>
    {
      'id': id,
      'nomor_laporan': nomor,
      'status': status,
      'tanggal': tanggal,
      'nama_opt': 'Wereng Batang Coklat',
      'nama_kecamatan': 'Kaliwates',
    };

Map<String, dynamic> _irigasiItem({
  int id = 10,
  String status = 'Submitted',
  String tanggal = '2026-08-10',
  String? nomor = 'LI-20260810-0001',
}) =>
    {
      'id': id,
      'nomor_laporan': nomor,
      'status': status,
      'tanggal': tanggal,
      'nama_saluran': 'Saluran Primer Bedadung',
      'nama_kecamatan': 'Sumbersari',
    };

// ── Tests ────────────────────────────────────────────────────────────────────

void main() {
  late _StubApiClient stub;
  late LaporanTerpaduProvider provider;

  setUp(() {
    stub = _StubApiClient();
    provider = LaporanTerpaduProvider(stub);
  });

  tearDown(() {
    provider.dispose();
  });

  // ── Initial state ─────────────────────────────────────────────────────────
  group('initial state', () {
    test('list is empty', () => expect(provider.list, isEmpty));
    test('loading is false', () => expect(provider.loading, isFalse));
    test('error is null', () => expect(provider.error, isNull));
    test('filter is default', () {
      expect(provider.filter.jenisKey, 'semua');
      expect(provider.filter.statusKey, 'all');
    });
  });

  // ── refresh: success ──────────────────────────────────────────────────────
  group('refresh() success', () {
    setUp(() {
      stub.onGet = (path, q) {
        if (path.contains('laporan-hama')) {
          return _hamaResponse(
            items: [_hamaItem(id: 1, tanggal: '2026-08-11')],
            total: 1,
          );
        }
        return _hamaResponse(
          items: [_irigasiItem(id: 10, tanggal: '2026-08-10')],
          total: 1,
        );
      };
    });

    test('loads combined list', () async {
      await provider.refresh();
      expect(provider.list, hasLength(2));
    });

    test('list contains both jenis', () async {
      await provider.refresh();
      final jenisList = provider.list.map((e) => e.jenis).toList();
      expect(jenisList, containsAll([JenisLaporan.hama, JenisLaporan.irigasi]));
    });

    test('list is sorted by tanggal descending', () async {
      await provider.refresh();
      expect(provider.list.first.tanggal, '2026-08-11');
      expect(provider.list.last.tanggal, '2026-08-10');
    });

    test('loading becomes false after refresh', () async {
      await provider.refresh();
      expect(provider.loading, isFalse);
    });

    test('error is null after success', () async {
      await provider.refresh();
      expect(provider.error, isNull);
    });

    test('totalCount equals sum of both totals', () async {
      await provider.refresh();
      expect(provider.totalCount, 2);
    });

    test('loads irrigation from the real backend envelope shape', () async {
      stub.onGet = (path, q) {
        if (path.contains('laporan-hama')) {
          return _realListResponse(items: [], total: 0);
        }
        return _realListResponse(
          items: [_irigasiItem(id: 42)],
          total: 1,
        );
      };

      await provider.refresh();

      expect(provider.error, isNull);
      expect(provider.list, hasLength(1));
      expect(provider.list.single.jenis, JenisLaporan.irigasi);
      expect(provider.list.single.id, 42);
      expect(provider.totalCount, 1);
    });
  });

  // ── refresh: network error ────────────────────────────────────────────────
  group('refresh() network error', () {
    setUp(() {
      stub.onGet = (_, __) => _errorResponse(
            'Koneksi bermasalah.',
            0,
          );
    });

    test('sets error when both endpoints fail', () async {
      await provider.refresh();
      expect(provider.error, isNotNull);
    });

    test('list remains empty on full failure', () async {
      await provider.refresh();
      expect(provider.list, isEmpty);
    });

    test('loading is false after error', () async {
      await provider.refresh();
      expect(provider.loading, isFalse);
    });
  });

  // ── refresh: partial failure (irigasi OK, hama fails) ────────────────────
  group('refresh() partial failure', () {
    test('shows hama data even when irigasi fails', () async {
      stub.onGet = (path, q) {
        if (path.contains('laporan-hama')) {
          return _hamaResponse(
            items: [_hamaItem()],
            total: 1,
          );
        }
        // irigasi gagal
        return _errorResponse('Server error', 500);
      };

      await provider.refresh();

      // Hama berhasil dimuat meski irigasi error
      expect(provider.list.any((e) => e.jenis == JenisLaporan.hama), isTrue);
    });
  });

  // ── applyFilter ───────────────────────────────────────────────────────────
  group('applyFilter()', () {
    test('filter jenis=hama only fetches hama endpoint', () async {
      final called = <String>[];
      stub.onGet = (path, q) {
        called.add(path);
        return _hamaResponse(items: [_hamaItem()], total: 1);
      };

      await provider.applyFilter(
        const LaporanFilter(jenisKey: 'hama'),
      );

      expect(called.every((p) => p.contains('laporan-hama')), isTrue);
      expect(called.any((p) => p.contains('laporan-irigasi')), isFalse);
    });

    test('filter jenis=irigasi only fetches irigasi endpoint', () async {
      final called = <String>[];
      stub.onGet = (path, q) {
        called.add(path);
        return _hamaResponse(items: [_irigasiItem()], total: 1);
      };

      await provider.applyFilter(
        const LaporanFilter(jenisKey: 'irigasi'),
      );

      expect(called.every((p) => p.contains('laporan-irigasi')), isTrue);
      expect(called.any((p) => p.contains('laporan-hama')), isFalse);
    });

    test('filter status is sent as query param', () async {
      String? capturedStatus;
      stub.onGet = (path, q) {
        capturedStatus = q?['status'] as String?;
        return _hamaResponse(items: [], total: 0);
      };

      await provider.applyFilter(
        const LaporanFilter(jenisKey: 'hama', statusKey: 'Ditolak'),
      );

      expect(capturedStatus, 'Ditolak');
    });

    test('filter statusKey=all does NOT send status param', () async {
      bool sentStatus = false;
      stub.onGet = (path, q) {
        if (q?.containsKey('status') == true) sentStatus = true;
        return _hamaResponse(items: [], total: 0);
      };

      await provider.applyFilter(const LaporanFilter(statusKey: 'all'));

      expect(sentStatus, isFalse);
    });

    test('applyFilter resets list and page', () async {
      // Pre-populate
      stub.onGet =
          (_, __) => _hamaResponse(items: [_hamaItem(id: 1)], total: 1);
      await provider.refresh();
      expect(provider.list, hasLength(2)); // hama+irigasi

      // Terapkan filter baru
      stub.onGet = (path, _) {
        if (path.contains('hama')) {
          return _hamaResponse(items: [_hamaItem(id: 99)], total: 1);
        }
        return _hamaResponse(items: [], total: 0);
      };

      await provider.applyFilter(
        const LaporanFilter(statusKey: 'Submitted'),
      );

      // List harus di-reset, bukan di-append
      expect(provider.list.where((e) => e.id == 99), hasLength(1));
    });
  });

  // ── LaporanFilter ─────────────────────────────────────────────────────────
  group('LaporanFilter', () {
    test('hasActiveFilter false for default filter', () {
      expect(const LaporanFilter().hasActiveFilter, isFalse);
    });

    test('hasActiveFilter true when jenis is not semua', () {
      expect(
        const LaporanFilter(jenisKey: 'hama').hasActiveFilter,
        isTrue,
      );
    });

    test('hasActiveFilter true when status is not all', () {
      expect(
        const LaporanFilter(statusKey: 'Ditolak').hasActiveFilter,
        isTrue,
      );
    });

    test('hasActiveDateFilter when tanggalDari set', () {
      expect(
        const LaporanFilter(tanggalDari: '2026-01-01').hasActiveDateFilter,
        isTrue,
      );
    });

    test('copyWith clearSearch removes searchQuery', () {
      final f = const LaporanFilter(searchQuery: 'wereng');
      final cleared = f.copyWith(clearSearch: true);
      expect(cleared.searchQuery, isNull);
    });

    test('copyWith clearTanggal removes tanggal range', () {
      final f = const LaporanFilter(
        tanggalDari: '2026-01-01',
        tanggalSampai: '2026-12-31',
      );
      final cleared = f.copyWith(clearTanggal: true);
      expect(cleared.tanggalDari, isNull);
      expect(cleared.tanggalSampai, isNull);
    });

    test('copyWith preserves unchanged fields', () {
      const f = LaporanFilter(
        jenisKey: 'hama',
        statusKey: 'Ditolak',
        searchQuery: 'wereng',
      );
      final updated = f.copyWith(jenisKey: 'irigasi');
      expect(updated.statusKey, 'Ditolak');
      expect(updated.searchQuery, 'wereng');
      expect(updated.jenisKey, 'irigasi');
    });
  });
}
