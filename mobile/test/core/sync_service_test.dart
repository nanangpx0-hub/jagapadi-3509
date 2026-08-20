import 'dart:async';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/core/local_db.dart';
import 'package:jagapadi_mobile/core/sync_service.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

const _userJson =
    '{"id": 7, "username": "petugas7", "nama_lengkap": "Petugas Tujuh", '
    '"role": "petugas", "is_active": true, "must_change_password": false}';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      (call) async {
        if (call.method == 'read') {
          final key = (call.arguments as Map?)?['key'];
          if (key == 'user_data') return _userJson;
        }
        return null;
      },
    );
  });

  late Directory _tempDir;
  late String _dbPath;

  setUp(() {
    _tempDir = Directory.systemTemp.createTempSync('jagapadi_sync_test');
    _dbPath = p.join(_tempDir.path, 'test.db');
    LocalDb.testDbPath = _dbPath;
  });

  tearDown(() async {
    await LocalDb.resetForTesting();
    _tempDir.deleteSync(recursive: true);
  });

  group('SyncService', () {
    test('POST draf membawa Idempotency-Key dan menandai synced', () async {
      final id = await LocalDb.instance
          .insertDraft(type: 'hama', payload: {'tanggal': '2026-08-16'});
      final item = await LocalDb.instance.getDraft(id);

      Map<String, String>? capturedHeaders;
      String? capturedPath;
      final api = _StubSyncApi(
        onPost: (path, data, headers) async {
          capturedPath = path;
          capturedHeaders = headers;
          return ApiResponse(
            success: true,
            statusCode: 201,
            data: {'id': 42},
          );
        },
      );

      final result = await SyncService.syncPendingDrafts(api);

      expect(capturedPath, '/laporan-hama');
      expect(capturedHeaders, {'Idempotency-Key': item!.clientOperationId});
      expect(result.synced, 1);
      expect(result.failed, 0);

      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'synced');
      expect(after.serverId, 42);
    });

    test('draf dengan foto: upload foto setelah payload, lalu synced',
        () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'irigasi',
        payload: {'tanggal': '2026-08-16'},
        fotoPath: 'C:/tmp/foto.jpg',
      );
      final api = _StubSyncApi(
        onPost: (path, data, headers) async =>
            ApiResponse(success: true, statusCode: 201, data: {'id': 7}),
        onUploadFoto: (path, filePath) async =>
            ApiResponse(success: true, statusCode: 200, data: {}),
      );

      final result = await SyncService.syncPendingDrafts(api);

      expect(result.synced, 1);
      expect(api.uploadCalls, 1);
      expect(api.uploadPaths.single, '/laporan-irigasi/7/foto');
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'synced');
      expect(after.photoSynced, isTrue);
    });

    test('422 → failed_validation terminal; tidak di-sync ulang', () async {
      final id = await LocalDb.instance
          .insertDraft(type: 'hama', payload: {'tanggal': '2026-08-16'});
      final api = _StubSyncApi(
        onPost: (path, data, headers) async => ApiResponse(
          success: false,
          statusCode: 422,
          message: 'Validasi gagal',
        ),
      );

      final first = await SyncService.syncPendingDrafts(api);
      expect(first.failed, 1);
      expect(api.postCalls, 1);

      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'failed_validation');

      final second = await SyncService.syncPendingDrafts(api);
      expect(second.total, 0);
      expect(second.message, contains('Tidak ada draf'));
      expect(api.postCalls, 1, reason: 'status terminal tidak boleh di-retry');
    });

    test('409 → conflict terminal; tidak di-sync ulang', () async {
      final id = await LocalDb.instance
          .insertDraft(type: 'pupuk', payload: {'tanggal': '2026-08-16'});
      final api = _StubSyncApi(
        onPost: (path, data, headers) async => ApiResponse(
          success: false,
          statusCode: 409,
          message: 'Duplikat',
        ),
      );

      await SyncService.syncPendingDrafts(api);
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'conflict');

      await SyncService.syncPendingDrafts(api);
      expect(api.postCalls, 1);
    });

    test('pending_photo dengan photoSynced=true: tanpa upload, langsung synced',
        () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'hama',
        payload: {'tanggal': '2026-08-16'},
        fotoPath: 'C:/tmp/foto.jpg',
      );
      await LocalDb.instance.markSynced(id, 5);
      // Simulasikan foto sudah dikonfirmasi server (mis. upload sebelumnya).
      final db = await LocalDb.instance.database;
      await db.rawUpdate(
          'UPDATE local_drafts SET photo_synced = 1 WHERE id = ?', [id]);

      final api = _StubSyncApi();
      final result = await SyncService.syncPendingDrafts(api);

      expect(api.postCalls, 0);
      expect(api.uploadCalls, 0);
      expect(result.synced, 1);
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'synced');
    });

    test('pending_update memakai PUT dengan Idempotency-Key', () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'panen',
        payload: {'tanggal': '2026-08-16'},
        serverId: 9,
      );
      final item = await LocalDb.instance.getDraft(id);

      Map<String, String>? capturedHeaders;
      final api = _StubSyncApi(
        onPut: (path, data, headers) async {
          capturedHeaders = headers;
          return ApiResponse(success: true, statusCode: 200, data: {});
        },
      );

      final result = await SyncService.syncPendingDrafts(api);

      expect(api.putCalls, 1);
      expect(api.putPaths.single, '/laporan-panen/9');
      expect(capturedHeaders, {'Idempotency-Key': item!.clientOperationId});
      expect(result.synced, 1);
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'synced');
    });

    test('tipe tidak dikenal dilewati tanpa panggilan API', () async {
      await LocalDb.instance.insertDraft(
        type: 'jenis_aneh',
        payload: {'tanggal': '2026-08-16'},
      );
      final api = _StubSyncApi();
      final result = await SyncService.syncPendingDrafts(api);
      expect(result.failed, 1);
      expect(api.postCalls, 0);
    });

    test('error jaringan → draf tetap pending dan gagal dihitung', () async {
      final id = await LocalDb.instance
          .insertDraft(type: 'cuaca', payload: {'tanggal': '2026-08-16'});
      final api = _StubSyncApi(
        onPost: (path, data, headers) async => ApiResponse(
          success: false,
          statusCode: 0,
          message: 'Tidak ada koneksi',
          error: 'NetworkError',
        ),
      );

      final result = await SyncService.syncPendingDrafts(api);
      expect(result.failed, 1);
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.syncState, 'pending');
      expect(after.lastError, contains('Tidak ada koneksi'));
    });

    test('panggilan saat sinkronisasi berjalan langsung ditolak', () async {
      await LocalDb.instance
          .insertDraft(type: 'hama', payload: {'tanggal': '2026-08-16'});

      final gate = Completer<void>();
      final api = _StubSyncApi(
        onPost: (path, data, headers) async {
          await gate.future;
          return ApiResponse(success: true, statusCode: 201, data: {'id': 1});
        },
      );

      final first = SyncService.syncPendingDrafts(api);
      await Future<void>.delayed(const Duration(milliseconds: 50));

      final second = await SyncService.syncPendingDrafts(api);
      expect(second.message, 'Sinkronisasi sedang berjalan');
      expect(second.total, 0);

      gate.complete();
      final firstResult = await first;
      expect(firstResult.synced, 1);
    });
  });
}

class _StubSyncApi extends ApiClient {
  Future<ApiResponse<Map<String, dynamic>>> Function(
    String path,
    Map<String, dynamic>? data,
    Map<String, String>? headers,
  )? onPost;
  Future<ApiResponse<Map<String, dynamic>>> Function(
    String path,
    Map<String, dynamic>? data,
    Map<String, String>? headers,
  )? onPut;
  Future<ApiResponse<Map<String, dynamic>>> Function(
    String path,
    String filePath,
  )? onUploadFoto;

  int postCalls = 0;
  int putCalls = 0;
  int uploadCalls = 0;
  final List<String> postPaths = [];
  final List<String> putPaths = [];
  final List<String> uploadPaths = [];

  _StubSyncApi({this.onPost, this.onPut, this.onUploadFoto}) : super();

  @override
  Future<ApiResponse<Map<String, dynamic>>> post(
    String path, {
    Map<String, dynamic>? data,
    Map<String, String>? headers,
    int maxRetries = 3,
  }) async {
    postCalls++;
    postPaths.add(path);
    final callback = onPost;
    if (callback != null) {
      return callback(path, data, headers);
    }
    return ApiResponse(
      success: false,
      message: 'Stub tidak dikonfigurasi',
      statusCode: 500,
    );
  }

  @override
  Future<ApiResponse<Map<String, dynamic>>> put(
    String path, {
    Map<String, dynamic>? data,
    Map<String, String>? headers,
  }) async {
    putCalls++;
    putPaths.add(path);
    final callback = onPut;
    if (callback != null) {
      return callback(path, data, headers);
    }
    return ApiResponse(
      success: false,
      message: 'Stub tidak dikonfigurasi',
      statusCode: 500,
    );
  }

  @override
  Future<ApiResponse<Map<String, dynamic>>> uploadFoto(
    String path,
    String filePath, {
    void Function(double progress)? onSendProgress,
    int maxRetries = 0,
  }) async {
    uploadCalls++;
    uploadPaths.add(path);
    final callback = onUploadFoto;
    if (callback != null) {
      return callback(path, filePath);
    }
    return ApiResponse(
      success: false,
      message: 'Stub tidak dikonfigurasi',
      statusCode: 500,
    );
  }
}
