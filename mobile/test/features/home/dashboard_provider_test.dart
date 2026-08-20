import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/features/home/providers/dashboard_provider.dart';

void main() {
  test('berhasil memuat data → state success + lastUpdatedAt terisi', () async {
    final api = _StubDashboardApi((path, q) {
      return ApiResponse(
        success: true,
        statusCode: 200,
        data: {
          'hama': {
            'total_aktif': 3,
            'total_draf': 1,
            'total_ditolak': 0,
          },
          'irigasi': {
            'total_aktif': 5,
            'total_draf': 2,
            'total_ditolak': 1,
          },
        },
      );
    });
    final provider = DashboardProvider(api);
    await provider.loadStats();

    expect(provider.state, DashboardViewState.success);
    expect(provider.hamaStats?.totalAktif, 3);
    expect(provider.irigasiStats?.totalAktif, 5);
    expect(provider.lastUpdatedAt, isNotNull);
    expect(provider.hasCachedData, isTrue);
  });

  test('semua angka nol → state empty (bukan error)', () async {
    final api = _StubDashboardApi((path, q) {
      return ApiResponse(
        success: true,
        statusCode: 200,
        data: {
          'hama': {'total_aktif': 0, 'total_draf': 0, 'total_ditolak': 0},
          'irigasi': {'total_aktif': 0, 'total_draf': 0, 'total_ditolak': 0},
        },
      );
    });
    final provider = DashboardProvider(api);
    await provider.loadStats();

    expect(provider.state, DashboardViewState.empty);
    expect(provider.error, isNull);
  });

  test('gagal tanpa cache → state error dan data tetap kosong (bukan nol)',
      () async {
    final api = _StubDashboardApi((path, q) {
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Server sibuk',
      );
    });
    final provider = DashboardProvider(api);
    await provider.loadStats();

    expect(provider.state, DashboardViewState.error);
    expect(provider.error, isNotNull);
    expect(provider.hasCachedData, isFalse);
    expect(provider.hamaStats, isNull);
  });

  test('gagal jaringan dengan cache → state offline, data lama dipertahankan',
      () async {
    var calls = 0;
    final api = _StubDashboardApi((path, q) {
      calls++;
      if (calls == 1) {
        return ApiResponse(
          success: true,
          statusCode: 200,
          data: {
            'hama': {'total_aktif': 3, 'total_draf': 0, 'total_ditolak': 0},
          },
        );
      }
      return ApiResponse(
        success: false,
        statusCode: 0,
        message: 'Tidak ada koneksi',
        error: 'NetworkError',
      );
    });
    final provider = DashboardProvider(api);

    await provider.loadStats();
    expect(provider.state, DashboardViewState.success);

    await provider.loadStats();
    expect(provider.state, DashboardViewState.offline);
    expect(provider.hamaStats?.totalAktif, 3);
  });

  test('error server dengan cache → state stale', () async {
    var calls = 0;
    final api = _StubDashboardApi((path, q) {
      calls++;
      if (calls == 1) {
        return ApiResponse(
          success: true,
          statusCode: 200,
          data: {
            'hama': {'total_aktif': 3, 'total_draf': 0, 'total_ditolak': 0},
          },
        );
      }
      return ApiResponse(
        success: false,
        statusCode: 500,
        message: 'Server sibuk',
      );
    });
    final provider = DashboardProvider(api);

    await provider.loadStats();
    await provider.loadStats();
    expect(provider.state, DashboardViewState.stale);
  });

  test('reset mengosongkan data dan kembali ke initial', () async {
    final api = _StubDashboardApi((path, q) {
      return ApiResponse(
        success: true,
        statusCode: 200,
        data: {
          'hama': {'total_aktif': 3, 'total_draf': 0, 'total_ditolak': 0},
        },
      );
    });
    final provider = DashboardProvider(api);
    await provider.loadStats();
    expect(provider.hasCachedData, isTrue);

    provider.reset();
    expect(provider.state, DashboardViewState.initial);
    expect(provider.hasCachedData, isFalse);
    expect(provider.lastUpdatedAt, isNull);
  });

  test('memuat ulang dengan tahun berbeda mengirim query tahun baru', () async {
    String? sentYear;
    final api = _StubDashboardApi((path, q) {
      sentYear = q?['tahun'] as String?;
      return ApiResponse(
        success: true,
        statusCode: 200,
        data: {
          'hama': {'total_aktif': 1, 'total_draf': 0, 'total_ditolak': 0},
        },
      );
    });
    final provider = DashboardProvider(api);
    await provider.loadStats(tahun: 2025);
    expect(sentYear, '2025');
  });
}

class _StubDashboardApi extends ApiClient {
  ApiResponse<Map<String, dynamic>> Function(
      String path, Map<String, dynamic>? queryParams)? onGet;

  _StubDashboardApi(this.onGet) : super();

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
