import 'package:flutter/material.dart';
import 'app.dart';
import 'core/fcm/fcm_service.dart';
import 'core/router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final appRouter = AppRouter();

  const fcmEnabled = bool.fromEnvironment('FCM_ENABLED', defaultValue: false);
  await FcmService.init(
    enabled: fcmEnabled,
    onNavigate: (entity, laporanId) {
      final route = entity == 'hama' ? '/hama/$laporanId' : '/irigasi/$laporanId';
      appRouter.router.go(route);
    },
  );

  runApp(JagapadiApp(appRouter: appRouter));
}
