import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'offline_credentials.dart';
import 'offline_login_policy.dart';

class AppSecureStorage {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'jwt_token';
  static const _userKey = 'user_data';
  static const _offlineUsernameKey = 'offline_username';
  static const _offlineSaltKey = 'offline_password_salt';
  static const _offlineVerifierKey = 'offline_password_verifier';
  static const _offlineIterationsKey = 'offline_password_iterations';
  static const _offlineVerifierVersionKey = 'offline_verifier_version';
  static const _offlineLastOnlineKey = 'offline_last_online_at';
  static const _offlineFailCountKey = 'offline_fail_count';
  static const _offlineLockUntilKey = 'offline_lock_until';

  /// Policy lockout — dapat ditimpa di test.
  static OfflineLockPolicy lockPolicy = OfflineLockPolicy(
    maxAttempts: 5,
    lockDuration: const Duration(minutes: 5),
    now: DateTime.now,
  );

  static Future<void> saveToken(String token) async =>
      await _storage.write(key: _tokenKey, value: token);

  static Future<String?> getToken() async =>
      await _storage.read(key: _tokenKey);

  static Future<void> deleteToken() async =>
      await _storage.delete(key: _tokenKey);

  static Future<void> saveUser(String userJson) async =>
      await _storage.write(key: _userKey, value: userJson);

  static Future<String?> getUser() async => await _storage.read(key: _userKey);

  static Future<void> saveOfflineCredentials(
    String username,
    String password,
  ) async {
    final salt = OfflineCredentialHasher.createSalt();
    final verifier = OfflineCredentialHasher.derive(password, salt);
    await _storage.write(key: _offlineUsernameKey, value: username.trim());
    await _storage.write(key: _offlineSaltKey, value: salt);
    await _storage.write(key: _offlineVerifierKey, value: verifier);
    await _storage.write(
      key: _offlineIterationsKey,
      value: OfflineCredentialHasher.defaultIterations.toString(),
    );
    // Versi verifier + waktu login online terakhir + reset percobaan gagal.
    await _storage.write(
      key: _offlineVerifierVersionKey,
      value: OfflineVerifierPolicy.currentVersion.toString(),
    );
    await _storage.write(
      key: _offlineLastOnlineKey,
      value: DateTime.now().toIso8601String(),
    );
    await _storage.write(key: _offlineFailCountKey, value: '0');
    await _storage.delete(key: _offlineLockUntilKey);
  }

  static Future<bool> verifyOfflineCredentials(
    String username,
    String password,
  ) async {
    final savedUsername = await _storage.read(key: _offlineUsernameKey);
    final salt = await _storage.read(key: _offlineSaltKey);
    final verifier = await _storage.read(key: _offlineVerifierKey);
    final iterations = int.tryParse(
      await _storage.read(key: _offlineIterationsKey) ?? '',
    );
    if (savedUsername == null || salt == null || verifier == null) return false;
    final storedVersion = await _storage.read(key: _offlineVerifierVersionKey);
    if (!OfflineVerifierPolicy.accepts(storedVersion)) return false;
    if (savedUsername.toLowerCase() != username.trim().toLowerCase()) {
      return false;
    }
    return OfflineCredentialHasher.verify(
      password,
      salt,
      verifier,
      iterations: iterations ?? OfflineCredentialHasher.defaultIterations,
    );
  }

  // ── Kebijakan lockout login offline ───────────────────────────────────────

  /// Sisa waktu kunci login offline (detik); 0 jika tidak terkunci.
  static Future<int> offlineLockRemainingSeconds() async {
    final raw = await _storage.read(key: _offlineLockUntilKey);
    final until = DateTime.tryParse(raw ?? '');
    return lockPolicy.remainingLockSeconds(lockUntil: until);
  }

  /// Catat percobaan login offline gagal (dengan lockout setelah batas).
  static Future<void> recordOfflineLoginFailure() async {
    final rawCount = await _storage.read(key: _offlineFailCountKey);
    final rawUntil = await _storage.read(key: _offlineLockUntilKey);
    final failCount = int.tryParse(rawCount ?? '0') ?? 0;
    final lockUntil = DateTime.tryParse(rawUntil ?? '');
    final result = lockPolicy.registerFailure(
      failCount: failCount,
      lockUntil: lockUntil,
    );
    await _storage.write(
      key: _offlineFailCountKey,
      value: result.failCount.toString(),
    );
    if (result.lockUntil != null) {
      await _storage.write(
        key: _offlineLockUntilKey,
        value: result.lockUntil!.toIso8601String(),
      );
    } else {
      await _storage.delete(key: _offlineLockUntilKey);
    }
  }

  /// Reset counter setelah login offline berhasil.
  static Future<void> resetOfflineLoginFailures() async {
    final result = lockPolicy.reset();
    await _storage.write(
      key: _offlineFailCountKey,
      value: result.failCount.toString(),
    );
    await _storage.delete(key: _offlineLockUntilKey);
  }

  /// Waktu login online terakhir (null jika tidak pernah).
  static Future<DateTime?> offlineLastOnlineAt() async {
    final raw = await _storage.read(key: _offlineLastOnlineKey);
    return DateTime.tryParse(raw ?? '');
  }

  /// Menghapus sesi aktif tanpa menghapus verifier login offline perangkat.
  static Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }

  static Future<void> clearAll() async => await _storage.deleteAll();
}
