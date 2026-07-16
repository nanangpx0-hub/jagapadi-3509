import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:jagapadi_mobile/core/theme.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/core/config.dart';
import 'package:jagapadi_mobile/core/router.dart';
import 'package:jagapadi_mobile/core/secure_storage.dart';
import 'package:jagapadi_mobile/features/auth/providers/auth_provider.dart';
import 'package:jagapadi_mobile/features/auth/screens/login_screen.dart';

Widget createTestApp() {
  final appRouter = AppRouter();

  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AuthProvider(appRouter)),
    ],
    child: MaterialApp.router(
      title: 'JAGAPADI',
      theme: AppTheme.light,
      routerConfig: appRouter.router,
      debugShowCheckedModeBanner: false,
    ),
  );
}

void main() {
  testWidgets('Login screen renders form fields', (tester) async {
    await tester.pumpWidget(createTestApp());
    await tester.pumpAndSettle();

    expect(find.text('Masuk'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
    expect(find.byType(ElevatedButton), findsOneWidget);
  });
}
