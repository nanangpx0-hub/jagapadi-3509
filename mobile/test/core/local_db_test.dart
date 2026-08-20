import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/local_db.dart';
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
    _tempDir = Directory.systemTemp.createTempSync('jagapadi_db_test');
    _dbPath = p.join(_tempDir.path, 'test.db');
    LocalDb.testDbPath = _dbPath;
  });

  tearDown(() async {
    await LocalDb.resetForTesting();
    _tempDir.deleteSync(recursive: true);
  });

  group('LocalDb — insert & get', () {
    test('insertDraft membuat client_operation_id otomatis (op- + 32 hex)',
        () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'hama',
        payload: {'tanggal': '2026-08-16'},
      );
      final item = await LocalDb.instance.getDraft(id);
      expect(item, isNotNull);
      expect(item!.clientOperationId, startsWith('op-'));
      expect(item.clientOperationId!.substring(3).length, 32);
      expect(
        RegExp(r'^[0-9a-f]{32}$')
            .hasMatch(item.clientOperationId!.substring(3)),
        isTrue,
      );
      expect(item.userId, 7);
      expect(item.type, 'hama');
      expect(item.payload['tanggal'], '2026-08-16');
      expect(item.syncState, 'pending');
      expect(item.photoSynced, isTrue);
    });

    test('insertDraft dengan fotoPath menandai photo_synced=0', () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'irigasi',
        payload: {'tanggal': '2026-08-16'},
        fotoPath: 'C:/tmp/foto.jpg',
      );
      final item = await LocalDb.instance.getDraft(id);
      expect(item!.photoSynced, isFalse);
      expect(item.fotoPath, 'C:/tmp/foto.jpg');
    });

    test('client_operation_id eksplisit dipertahankan', () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'pupuk',
        payload: {'tanggal': '2026-08-16'},
        clientOperationId: 'op-manual1234567890abcdef1234567890',
      );
      final item = await LocalDb.instance.getDraft(id);
      expect(item!.clientOperationId, 'op-manual1234567890abcdef1234567890');
    });

    test('insertDraft tanpa user aktif melempar StateError', () async {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(
        const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
        (call) async => null,
      );
      addTearDown(() {
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
      await expectLater(
        LocalDb.instance.insertDraft(type: 'hama', payload: {}),
        throwsStateError,
      );
    });

    test('getDraft mengembalikan null untuk id tidak ada', () async {
      expect(await LocalDb.instance.getDraft(9999), isNull);
    });
  });

  group('LocalDb — update & status', () {
    test('updateDraft memperbarui payload & reset ke pending, key tetap',
        () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'hama',
        payload: {'catatan': 'v1'},
      );
      final before = await LocalDb.instance.getDraft(id);
      await LocalDb.instance.markSynced(id, 100);
      await LocalDb.instance.updateDraft(
        id,
        payload: {'catatan': 'v2'},
      );
      final after = await LocalDb.instance.getDraft(id);
      expect(after!.payload['catatan'], 'v2');
      expect(after.syncState, 'pending');
      expect(after.syncedAt, isNull);
      expect(after.clientOperationId, before!.clientOperationId);
    });

    test('markSynced tanpa foto → synced; dengan foto → pending_photo',
        () async {
      final noFoto =
          await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      await LocalDb.instance.markSynced(noFoto, 11);
      final syncedItem = await LocalDb.instance.getDraft(noFoto);
      expect(syncedItem!.syncState, 'synced');
      expect(syncedItem.serverId, 11);
      expect(syncedItem.syncedAt, isNotNull);

      final withFoto = await LocalDb.instance.insertDraft(
        type: 'irigasi',
        payload: {},
        fotoPath: 'C:/tmp/f.jpg',
      );
      await LocalDb.instance.markSynced(withFoto, 12);
      final photoPending = await LocalDb.instance.getDraft(withFoto);
      expect(photoPending!.syncState, 'pending_photo');
      expect(photoPending.syncedAt, isNull);
    });

    test('markPhotoSynced menyelesaikan status menjadi synced', () async {
      final id = await LocalDb.instance.insertDraft(
        type: 'hama',
        payload: {},
        fotoPath: 'C:/tmp/f.jpg',
      );
      await LocalDb.instance.markSynced(id, 5);
      await LocalDb.instance.markPhotoSynced(id);
      final item = await LocalDb.instance.getDraft(id);
      expect(item!.syncState, 'synced');
      expect(item.photoSynced, isTrue);
      expect(item.syncedAt, isNotNull);
    });

    test('markFailed menandai status terminal & menambah retry_count',
        () async {
      final id = await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      await LocalDb.instance.markFailed(id, 'failed_validation', '422 x');
      final item = await LocalDb.instance.getDraft(id);
      expect(item!.syncState, 'failed_validation');
      expect(item.lastError, '422 x');
      expect(item.retryCount, 1);
    });
  });

  group('LocalDb — query draf', () {
    test('getSyncableDrafts mengecualikan status terminal & synced', () async {
      final pendingId =
          await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      final updateId = await LocalDb.instance
          .insertDraft(type: 'irigasi', payload: {}, serverId: 9);
      final terminalId =
          await LocalDb.instance.insertDraft(type: 'pupuk', payload: {});
      final conflictId =
          await LocalDb.instance.insertDraft(type: 'panen', payload: {});
      final syncedId =
          await LocalDb.instance.insertDraft(type: 'cuaca', payload: {});
      await LocalDb.instance.markFailed(terminalId, 'failed_validation', 'x');
      await LocalDb.instance.markFailed(conflictId, 'conflict', 'y');
      await LocalDb.instance.markSynced(syncedId, 1);

      final syncable = await LocalDb.instance.getSyncableDrafts();
      final ids = syncable.map((e) => e.id).toSet();
      expect(ids, contains(pendingId));
      expect(ids, contains(updateId));
      expect(ids, isNot(contains(terminalId)));
      expect(ids, isNot(contains(conflictId)));
      expect(ids, isNot(contains(syncedId)));
    });

    test('getUnsyncedDrafts tetap menampilkan status terminal', () async {
      final terminalId =
          await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      await LocalDb.instance.markFailed(terminalId, 'failed_validation', 'x');
      final unsynced = await LocalDb.instance.getUnsyncedDrafts();
      expect(unsynced.map((e) => e.id), contains(terminalId));
    });

    test('filter tipe pada query draf', () async {
      await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      final irigasiId =
          await LocalDb.instance.insertDraft(type: 'irigasi', payload: {});
      final irigasiOnly = await LocalDb.instance.getSyncableDrafts('irigasi');
      expect(irigasiOnly.length, 1);
      expect(irigasiOnly.first.id, irigasiId);
    });

    test('deleteDraft menghapus draf', () async {
      final id = await LocalDb.instance.insertDraft(type: 'hama', payload: {});
      await LocalDb.instance.deleteDraft(id);
      expect(await LocalDb.instance.getDraft(id), isNull);
    });
  });

  group('LocalDb — migration v1/v2 → v3', () {
    test('skema lama di-upgrade: kolom v2 & client_operation_id ditambahkan',
        () async {
      // Buat DB skema v1 (hanya kolom awal) dengan versi 1, lalu tutup.
      final v1 = await databaseFactory.openDatabase(
        _dbPath,
        options: OpenDatabaseOptions(
          version: 1,
          onCreate: (db, version) async {
            await db.execute('''
              CREATE TABLE local_drafts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                payload TEXT NOT NULL,
                server_id INTEGER,
                foto_path TEXT,
                created_at TEXT NOT NULL,
                synced_at TEXT
              )
            ''');
          },
        ),
      );
      await v1.insert('local_drafts', {
        'type': 'hama',
        'payload': '{"catatan":"lama"}',
        'created_at': '2026-01-01T00:00:00',
      });
      await v1.close();

      // Buka via LocalDb (versi 3) → onUpgrade harus menjalankan v2 & v3.
      final db = await LocalDb.instance.database;
      final cols = await db.rawQuery('PRAGMA table_info(local_drafts)');
      final names = cols.map((c) => c['name']).toSet();
      expect(
          names,
          containsAll([
            'user_id',
            'sync_state',
            'photo_synced',
            'last_error',
            'retry_count',
            'updated_at',
            'client_operation_id',
          ]));

      // Draf lama tetap terbaca, insert baru memakai kolom baru.
      final id = await LocalDb.instance.insertDraft(
        type: 'hama',
        payload: {'catatan': 'baru'},
      );
      final item = await LocalDb.instance.getDraft(id);
      expect(item!.clientOperationId, isNotNull);
      expect(item.userId, 7);
    });
  });
}
