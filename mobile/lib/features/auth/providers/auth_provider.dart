import 'dart:convert';
import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../../../core/fcm/fcm_service.dart';
import '../../../core/router.dart';
import '../../../core/secure_storage.dart';
import '../models/user.dart';

class AuthProvider extends ChangeNotifier {
  final AppRouter _router;
  ApiClient? _api;
  User? _user;
  bool _loading = false;
  String? _error;

  AuthProvider(this._router) {
    _api = ApiClient(onUnauthorized: _onUnauthorized);
    _loadSavedSession();
  }

  ApiClient get api => _api!;
  User? get user => _user;
  bool get loading => _loading;
  String? get error => _error;
  bool get isLoggedIn => _user != null;
  bool get isAdmin => _user?.isAdmin ?? false;
  bool get mustChangePassword => _user?.mustChangePassword ?? false;

  void _onUnauthorized() {
    _user = null;
    _router.redirectToLogin();
    notifyListeners();
  }

  Future<void> _loadSavedSession() async {
    final token = await AppSecureStorage.getToken();
    final userJson = await AppSecureStorage.getUser();
    if (token != null && userJson != null) {
      _user = User.fromJson(json.decode(userJson) as Map<String, dynamic>);
      notifyListeners();
    }
  }

  Future<bool> login(String username, String password) async {
    _loading = true;
    _error = null;
    notifyListeners();

    final res = await _api!.post('/auth/login', data: {
      'username': username,
      'password': password,
    });

    _loading = false;

    if (res.success && res.data != null) {
      final token = res.data!['token'] as String?;
      final userData = res.data!['user'] as Map<String, dynamic>?;
      if (token != null && userData != null) {
        _user = User.fromJson(userData);
        await AppSecureStorage.saveToken(token);
        await AppSecureStorage.saveUser(json.encode(userData));
        notifyListeners();
        FcmService.api = api;
        FcmService.registerToken();
        return true;
      }
    }

    if (res.errors != null && res.errors!.isNotEmpty) {
      _error = res.errors!.values.join('\n');
    } else {
      _error = res.message ?? 'Login gagal. Periksa username dan password.';
    }
    notifyListeners();
    return false;
  }

  Future<void> logout() async {
    await FcmService.unregisterToken();
    await _api!.post('/auth/logout');
    await AppSecureStorage.clearAll();
    _user = null;
    _router.redirectToLogin();
    notifyListeners();
  }

  Future<String?> changePassword(String current, String newPass, String confirm) async {
    final res = await _api!.post('/auth/change-password', data: {
      'current_password': current,
      'new_password': newPass,
      'new_password_confirmation': confirm,
    });
    if (res.success) {
      if (_user != null) {
        _user = User(
          id: _user!.id,
          username: _user!.username,
          namaLengkap: _user!.namaLengkap,
          role: _user!.role,
          isActive: _user!.isActive,
          mustChangePassword: false,
        );
        await AppSecureStorage.saveUser(json.encode(_user!.toJson()));
        notifyListeners();
      }
      return null;
    }
    if (res.errors != null && res.errors!.isNotEmpty) {
      return res.errors!.values.join('\n');
    }
    return res.message ?? 'Gagal mengubah password.';
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
