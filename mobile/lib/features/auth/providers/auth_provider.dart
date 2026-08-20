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
  bool _offlineMode = false;
  String? _error;

  /// Callback opsional yang dipanggil saat logout berhasil.
  /// Digunakan oleh root widget (app.dart) untuk membersihkan cache provider
  /// lain (WilayahProvider, LaporanHamaProvider, dll.) tanpa circular dependency.
  VoidCallback? onLogoutCallback;

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
  bool get offlineMode => _offlineMode;

  void _onUnauthorized() {
    _user = null;
    _offlineMode = false;
    _router.redirectToLogin();
    notifyListeners();
  }

  Future<void> _loadSavedSession() async {
    final token = await AppSecureStorage.getToken();
    final userJson = await AppSecureStorage.getUser();
    if (token != null && userJson != null) {
      _user = User.fromJson(json.decode(userJson) as Map<String, dynamic>);
      _router.setRole(_user!.role);
      notifyListeners();
    }
  }

  Future<bool> login(String username, String password,
      {bool isOnline = true}) async {
    if (!isOnline) {
      return _loginOffline(username, password);
    }

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
        await AppSecureStorage.saveOfflineCredentials(username, password);
        _router.setToken(token);
        _router.setRole(_user!.role);
        _offlineMode = false;
        notifyListeners();
        FcmService.api = api;
        FcmService.registerToken();
        return true;
      }
    }

    if (res.isNetworkError || res.isTimeoutError || res.isSslError) {
      return _loginOffline(username, password, serverUnavailable: true);
    } else if (res.errors != null && res.errors!.isNotEmpty) {
      _error = res.errors!.values.join('\n');
    } else {
      _error = res.message ?? 'Login gagal. Periksa username dan password.';
    }
    notifyListeners();
    return false;
  }

  Future<bool> _loginOffline(
    String username,
    String password, {
    bool serverUnavailable = false,
  }) async {
    _loading = true;
    _error = null;
    notifyListeners();

    // Batasi percobaan login offline (anti brute-force lokal).
    final lockSeconds = await AppSecureStorage.offlineLockRemainingSeconds();
    if (lockSeconds > 0) {
      _loading = false;
      final minutes = (lockSeconds / 60).ceil();
      _error =
          'Terlalu banyak percobaan login offline. '
          'Coba lagi dalam $minutes menit.';
      notifyListeners();
      return false;
    }

    final valid = await AppSecureStorage.verifyOfflineCredentials(
      username,
      password,
    );
    final token = await AppSecureStorage.getToken();
    final userJson = await AppSecureStorage.getUser();

    _loading = false;
    if (valid && token != null && userJson != null) {
      final cachedUser = User.fromJson(
        json.decode(userJson) as Map<String, dynamic>,
      );
      if (cachedUser.mustChangePassword) {
        _error =
            'Password wajib diubah saat online sebelum mode offline dapat digunakan.';
        notifyListeners();
        return false;
      }
      if (!cachedUser.isActive) {
        _error =
            'Akun ini dinonaktifkan. Hubungi administrator untuk informasi lebih lanjut.';
        notifyListeners();
        return false;
      }
      await AppSecureStorage.resetOfflineLoginFailures();
      _user = cachedUser;
      _offlineMode = true;
      _router.setToken(token);
      _router.setRole(cachedUser.role);
      notifyListeners();
      return true;
    }

    await AppSecureStorage.recordOfflineLoginFailure();
    _error = serverUnavailable
        ? 'Server tidak dapat dijangkau dan login offline belum tersedia untuk akun ini. Hubungkan ke server sekali untuk mengaktifkannya.'
        : 'Login offline gagal. Akun harus pernah login online di perangkat ini dan password harus sesuai.';
    notifyListeners();
    return false;
  }

  Future<void> logout() async {
    await FcmService.unregisterToken();
    await _api!.post('/auth/logout');
    await AppSecureStorage.clearAll();
    _user        = null;
    _offlineMode = false;
    _router.setToken(null);
    _router.setRole(null);
    // Bersihkan cache provider lain (wilayah, OPT, dll.) via callback
    // agar pengguna berbeda tidak melihat data sesi sebelumnya.
    onLogoutCallback?.call();
    _router.redirectToLogin();
    notifyListeners();
  }

  Future<String?> changePassword(
      String current, String newPass, String confirm) async {
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
