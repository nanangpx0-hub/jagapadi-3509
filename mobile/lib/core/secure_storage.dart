import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AppSecureStorage {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'jwt_token';
  static const _userKey = 'user_data';

  static Future<void> saveToken(String token) async =>
      await _storage.write(key: _tokenKey, value: token);

  static Future<String?> getToken() async =>
      await _storage.read(key: _tokenKey);

  static Future<void> deleteToken() async =>
      await _storage.delete(key: _tokenKey);

  static Future<void> saveUser(String userJson) async =>
      await _storage.write(key: _userKey, value: userJson);

  static Future<String?> getUser() async =>
      await _storage.read(key: _userKey);

  static Future<void> clearAll() async => await _storage.deleteAll();
}
