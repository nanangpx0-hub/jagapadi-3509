import 'dart:io';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'app.dart';
import 'core/fcm/fcm_service.dart';
import 'core/router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final appRouter = AppRouter();

  // ── Android 13+ (API 33): POST_NOTIFICATIONS wajib diminta runtime ──────
  // Tanpa ini FCM push notification tidak tampil sama sekali di Android 13+.
  // Permintaan dilakukan sebelum FCM init agar token terdaftar setelah izin
  // diberikan.
  if (Platform.isAndroid) {
    final status = await Permission.notification.status;
    if (status.isDenied) {
      await Permission.notification.request();
    }
  }

  const fcmEnabled = bool.fromEnvironment('FCM_ENABLED', defaultValue: false);
  await FcmService.init(
    enabled: fcmEnabled,
    onNavigate: (entity, laporanId) {
      // FCM entity sudah divalidasi whitelist di FcmService._handleData()
      final routeMap = {
        'hama': '/hama/$laporanId',
        'irigasi': '/irigasi/$laporanId',
        'pupuk': '/pupuk/$laporanId',
        'panen': '/panen/$laporanId',
        'cuaca': '/cuaca/$laporanId',
        'alat_sarana': '/alat-sarana/$laporanId',
      };
      final route = routeMap[entity];
      if (route != null) appRouter.router.go(route);
    },
  );

  runApp(JagapadiApp(appRouter: appRouter));
}
