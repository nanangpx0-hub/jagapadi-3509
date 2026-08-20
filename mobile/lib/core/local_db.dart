import 'dart:convert';

import 'package:flutter/foundation.dart' show visibleForTesting;
import 'package:path/path.dart' show join;
import 'package:sqflite/sqflite.dart';

import 'operation_id.dart';
import 'secure_storage.dart';

class LocalDraftItem {
  final int? id;
  final int userId;
  final String type;
  final Map<String, dynamic> payload;
  final int? serverId;
  final String? fotoPath;
  final String createdAt;
  final String? syncedAt;
  final String syncState;
  final String? lastError;
  final int retryCount;
  final String? clientOperationId;
  final bool photoSynced;

  const LocalDraftItem({
    this.id,
    required this.userId,
    required this.type,
    required this.payload,
    this.serverId,
    this.fotoPath,
    required this.createdAt,
    this.syncedAt,
    this.syncState = 'pending',
    this.lastError,
    this.retryCount = 0,
    this.clientOperationId,
    this.photoSynced = true,
  });

  Map<String, dynamic> toMap() => {
        if (id != null) 'id': id,
        'user_id': userId,
        'type': type,
        'payload': json.encode(payload),
        'server_id': serverId,
        'foto_path': fotoPath,
        'created_at': createdAt,
        'synced_at': syncedAt,
        'sync_state': syncState,
        'last_error': lastError,
        'retry_count': retryCount,
        'client_operation_id': clientOperationId,
        'photo_synced': photoSynced ? 1 : 0,
      };

  factory LocalDraftItem.fromMap(Map<String, dynamic> map) => LocalDraftItem(
        id: map['id'] as int?,
        userId: map['user_id'] as int? ?? 0,
        type: map['type'] as String? ?? 'hama',
        payload: json.decode(map['payload'] as String? ?? '{}')
            as Map<String, dynamic>,
        serverId: map['server_id'] as int?,
        fotoPath: map['foto_path'] as String?,
        createdAt:
            map['created_at'] as String? ?? DateTime.now().toIso8601String(),
        syncedAt: map['synced_at'] as String?,
        syncState: map['sync_state'] as String? ?? 'pending',
        lastError: map['last_error'] as String?,
        retryCount: map['retry_count'] as int? ?? 0,
        clientOperationId: map['client_operation_id'] as String?,
        photoSynced: (map['photo_synced'] as int? ?? 1) == 1,
      );

  bool get isSynced => syncState == 'synced';

  /// Status terminal yang tidak boleh di-retry otomatis.
  bool get isTerminal =>
      syncState == 'failed_validation' || syncState == 'conflict';
}

class LocalDb {
  static final LocalDb instance = LocalDb._init();
  static Database? _database;

  /// Path DB alternatif untuk test (biasanya berkas sementara unik per test).
  @visibleForTesting
  static String? testDbPath;

  LocalDb._init();

  Future<Database> get database async {
    if (_database != null) return _database!;
    final path =
        testDbPath ?? join(await getDatabasesPath(), 'jagapadi_drafts.db');
    _database = await _initDB(path);
    return _database!;
  }

  /// Tutup koneksi & reset cache agar test berikutnya memakai DB bersih.
  @visibleForTesting
  static Future<void> resetForTesting() async {
    await _database?.close();
    _database = null;
    testDbPath = null;
  }

  Future<Database> _initDB(String filePath) async {
    final path = join(await getDatabasesPath(), filePath);
    return openDatabase(
      path,
      version: 3,
      onCreate: _createDB,
      onUpgrade: _upgradeDB,
    );
  }

  Future<void> _createDB(Database db, int version) async {
    await db.execute('''
      CREATE TABLE local_drafts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        payload TEXT NOT NULL,
        server_id INTEGER,
        foto_path TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        synced_at TEXT,
        sync_state TEXT NOT NULL DEFAULT 'pending',
        photo_synced INTEGER NOT NULL DEFAULT 1,
        last_error TEXT,
        retry_count INTEGER NOT NULL DEFAULT 0,
        client_operation_id TEXT
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_local_drafts_sync '
      'ON local_drafts(user_id, sync_state, created_at)',
    );
  }

  Future<void> _upgradeDB(Database db, int oldVersion, int newVersion) async {
    if (oldVersion < 2) {
      await db.execute(
        'ALTER TABLE local_drafts ADD COLUMN user_id INTEGER NOT NULL DEFAULT 0',
      );
      await db.execute(
        "ALTER TABLE local_drafts ADD COLUMN sync_state TEXT NOT NULL DEFAULT 'pending'",
      );
      await db.execute(
        'ALTER TABLE local_drafts ADD COLUMN photo_synced INTEGER NOT NULL DEFAULT 1',
      );
      await db.execute('ALTER TABLE local_drafts ADD COLUMN last_error TEXT');
      await db.execute(
        'ALTER TABLE local_drafts ADD COLUMN retry_count INTEGER NOT NULL DEFAULT 0',
      );
      await db.execute(
        "ALTER TABLE local_drafts ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''",
      );
      await db.execute(
        'CREATE INDEX IF NOT EXISTS idx_local_drafts_sync '
        'ON local_drafts(user_id, sync_state, created_at)',
      );
    }
    // v2 → v3: kolom idempotency key. Draf lama diberi key saat sync berikutnya
    // (diisi on-the-fly jika kosong), sehingga tidak ada data yang hilang.
    if (oldVersion < 3) {
      await db.execute(
        'ALTER TABLE local_drafts ADD COLUMN client_operation_id TEXT',
      );
    }
  }

  Future<int?> _currentUserId() async {
    final raw = await AppSecureStorage.getUser();
    if (raw == null) return null;
    final user = json.decode(raw) as Map<String, dynamic>;
    final id = user['id'];
    return id is int ? id : int.tryParse(id?.toString() ?? '');
  }

  Future<int> insertDraft({
    required String type,
    required Map<String, dynamic> payload,
    String? fotoPath,
    int? serverId,
    String? clientOperationId,
  }) async {
    final userId = await _currentUserId();
    if (userId == null) {
      throw StateError('Draf lokal tidak dapat disimpan tanpa pengguna aktif');
    }
    final db = await database;
    final now = DateTime.now().toIso8601String();
    final item = LocalDraftItem(
      userId: userId,
      type: type,
      payload: payload,
      serverId: serverId,
      fotoPath: fotoPath,
      createdAt: now,
      syncedAt: null,
      syncState: serverId == null ? 'pending' : 'pending_update',
      clientOperationId: clientOperationId ?? OperationId.generate(),
    );
    final values = item.toMap()
      ..['photo_synced'] = fotoPath == null ? 1 : 0
      ..['updated_at'] = now;
    return db.insert('local_drafts', values);
  }

  /// Ambil satu draf lokal — untuk mengambil client_operation_id saat
  /// menyimpan ke server online (idempotency).
  Future<LocalDraftItem?> getDraft(int id) async {
    final userId = await _currentUserId();
    if (userId == null) return null;
    final result = await (await database).query(
      'local_drafts',
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
      limit: 1,
    );
    if (result.isEmpty) return null;
    return LocalDraftItem.fromMap(result.first);
  }

  Future<int> updateDraft(
    int id, {
    Map<String, dynamic>? payload,
    int? serverId,
    String? fotoPath,
  }) async {
    final userId = await _currentUserId();
    if (userId == null) return 0;
    final values = <String, dynamic>{
      'updated_at': DateTime.now().toIso8601String(),
    };
    if (payload != null) {
      values['payload'] = json.encode(payload);
      values['sync_state'] = 'pending';
      values['synced_at'] = null;
    }
    if (serverId != null) values['server_id'] = serverId;
    if (fotoPath != null) {
      values['foto_path'] = fotoPath;
      values['photo_synced'] = 0;
    }
    return (await database).update(
      'local_drafts',
      values,
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
    );
  }

  Future<int> markSynced(int id, int serverId) async {
    final userId = await _currentUserId();
    if (userId == null) return 0;
    final db = await database;
    final rows = await db.query(
      'local_drafts',
      columns: ['foto_path'],
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
      limit: 1,
    );
    final hasPhoto = rows.isNotEmpty &&
        (rows.first['foto_path'] as String?)?.isNotEmpty == true;
    return db.update(
      'local_drafts',
      {
        'server_id': serverId,
        'synced_at': hasPhoto ? null : DateTime.now().toIso8601String(),
        'sync_state': hasPhoto ? 'pending_photo' : 'synced',
        'last_error': null,
        'updated_at': DateTime.now().toIso8601String(),
      },
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
    );
  }

  Future<int> markPhotoSynced(int id) async {
    final userId = await _currentUserId();
    if (userId == null) return 0;
    return (await database).update(
      'local_drafts',
      {
        'photo_synced': 1,
        'sync_state': 'synced',
        'synced_at': DateTime.now().toIso8601String(),
        'last_error': null,
        'updated_at': DateTime.now().toIso8601String(),
      },
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
    );
  }

  Future<int> markFailed(int id, String state, String message) async {
    final userId = await _currentUserId();
    if (userId == null) return 0;
    return (await database).rawUpdate('''
      UPDATE local_drafts
      SET sync_state = ?, last_error = ?, retry_count = retry_count + 1,
          updated_at = ?
      WHERE id = ? AND user_id = ?
    ''', [state, message, DateTime.now().toIso8601String(), id, userId]);
  }

  Future<int> deleteDraft(int id) async {
    final userId = await _currentUserId();
    if (userId == null) return 0;
    return (await database).delete(
      'local_drafts',
      where: 'id = ? AND user_id = ?',
      whereArgs: [id, userId],
    );
  }

  /// Semua draf yang belum sinkron, TERMASUK status terminal
  /// (failed_validation, conflict) — untuk ditampilkan ke user agar bisa
  /// diperbaiki/dihapus manual.
  Future<List<LocalDraftItem>> getUnsyncedDrafts([String? type]) async {
    final userId = await _currentUserId();
    if (userId == null) return [];
    final result = await (await database).query(
      'local_drafts',
      where: type == null
          ? "user_id = ? AND sync_state != 'synced'"
          : "user_id = ? AND sync_state != 'synced' AND type = ?",
      whereArgs: type == null ? [userId] : [userId, type],
      orderBy: 'created_at ASC',
    );
    return result.map(LocalDraftItem.fromMap).toList();
  }

  /// Draf yang layak disinkronkan otomatis — status terminal
  /// (failed_validation, conflict) DILEWATKAN agar tidak retry tak berujung.
  Future<List<LocalDraftItem>> getSyncableDrafts([String? type]) async {
    final userId = await _currentUserId();
    if (userId == null) return [];
    final result = await (await database).query(
      'local_drafts',
      where: type == null
          ? "user_id = ? AND sync_state != 'synced' "
              "AND sync_state != 'failed_validation' AND sync_state != 'conflict'"
          : "user_id = ? AND sync_state != 'synced' "
              "AND sync_state != 'failed_validation' AND sync_state != 'conflict' "
              'AND type = ?',
      whereArgs: type == null ? [userId] : [userId, type],
      orderBy: 'created_at ASC',
    );
    return result.map(LocalDraftItem.fromMap).toList();
  }

  Future<List<LocalDraftItem>> getAllDrafts([String? type]) async {
    final userId = await _currentUserId();
    if (userId == null) return [];
    final result = await (await database).query(
      'local_drafts',
      where: type == null ? 'user_id = ?' : 'user_id = ? AND type = ?',
      whereArgs: type == null ? [userId] : [userId, type],
      orderBy: 'created_at DESC',
    );
    return result.map(LocalDraftItem.fromMap).toList();
  }
}
