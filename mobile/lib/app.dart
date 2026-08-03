import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/api_client.dart';
import 'core/router.dart';
import 'core/theme.dart';
import 'features/auth/providers/auth_provider.dart';
import 'features/notifications/providers/notification_provider.dart';

class JagapadiApp extends StatelessWidget {
  final AppRouter appRouter;

  const JagapadiApp({super.key, required this.appRouter});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider(appRouter)),
        ChangeNotifierProvider(create: (_) => NotificationProvider(ApiClient())),
      ],
      child: MaterialApp.router(
        title: 'JAGAPADI',
        theme: AppTheme.light,
        routerConfig: appRouter.router,
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
