import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../api_client.dart';

typedef FcmNavigationCallback = void Function(String entity, int laporanId);

@pragma('vm:entry-point')
Future<void> fcmBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
  final data = message.data;
  debugPrint('FCM background: entity=${data['entity']} laporan_id=${data['laporan_id']}');
}

class FcmService {
  static bool _initialized = false;
  static String? _lastToken;
  static ApiClient? _api;
  static set api(ApiClient? value) => _api = value;
  static FcmNavigationCallback? _onNavigate;
  static StreamSubscription? _onMessageSub;
  static StreamSubscription? _onOpenSub;
  static StreamSubscription? _onTokenRefreshSub;

  static bool get isEnabled => _initialized;

  static Future<void> init({
    required bool enabled,
    ApiClient? api,
    FcmNavigationCallback? onNavigate,
  }) async {
    _api = api;
    _onNavigate = onNavigate;

    if (!enabled) {
      debugPrint('FCM: disabled');
      return;
    }

    try {
      await Firebase.initializeApp();
      _initialized = true;
      debugPrint('FCM: Firebase initialized');

      FirebaseMessaging.onBackgroundMessage(fcmBackgroundHandler);

      _onMessageSub = FirebaseMessaging.onMessage.listen((message) {
        debugPrint('FCM foreground: ${message.notification?.title}');
      });

      _onOpenSub = FirebaseMessaging.onMessageOpenedApp.listen((message) {
        _handleData(message.data);
      });

      final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
      if (initialMessage != null) {
        _handleData(initialMessage.data);
      }
    } catch (e) {
      debugPrint('FCM: init failed — $e');
    }
  }

  static void _handleData(Map<String, String> data) {
    final entity = data['entity'];
    final laporanId = data['laporan_id'];
    if (entity != null && laporanId != null && _onNavigate != null) {
      final id = int.tryParse(laporanId);
      if (id != null) {
        _onNavigate!(entity, id);
      }
    }
  }

  static Future<String?> getToken() async {
    if (!_initialized) return null;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      _lastToken = token;
      return token;
    } catch (e) {
      debugPrint('FCM: getToken failed — $e');
      return null;
    }
  }

  static Future<void> registerToken() async {
    if (!_initialized || _api == null) return;
    final token = await getToken();
    if (token == null) return;
    _lastToken = token;
    try {
      await _api!.post('/device-tokens', data: {
        'token': token,
        'platform': 'android',
      });
      debugPrint('FCM: token registered');

      _onTokenRefreshSub?.cancel();
      _onTokenRefreshSub = FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
        _lastToken = newToken;
        try {
          await _api!.post('/device-tokens', data: {
            'token': newToken,
            'platform': 'android',
          });
          debugPrint('FCM: token refreshed');
        } catch (e) {
          debugPrint('FCM: token refresh registration failed — $e');
        }
      });
    } catch (e) {
      debugPrint('FCM: register failed — $e');
    }
  }

  static Future<void> unregisterToken() async {
    _onTokenRefreshSub?.cancel();
    if (_lastToken == null || _api == null) return;
    try {
      await _api!.delete('/device-tokens', data: {'token': _lastToken});
      debugPrint('FCM: token unregistered');
    } catch (e) {
      debugPrint('FCM: unregister failed — $e');
    }
    _lastToken = null;
  }

  static void dispose() {
    _onMessageSub?.cancel();
    _onOpenSub?.cancel();
    _onTokenRefreshSub?.cancel();
  }
}
