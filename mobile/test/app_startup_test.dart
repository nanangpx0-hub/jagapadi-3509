import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jagapadi_mobile/app.dart';
import 'package:jagapadi_mobile/core/router.dart';
import 'package:jagapadi_mobile/features/auth/providers/auth_provider.dart';
import 'package:provider/provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('startup registers session cleanup below MultiProvider',
      (tester) async {
    GoogleFonts.config.allowRuntimeFetching = false;
    final messenger =
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    const storage =
        MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
    const connectivity =
        MethodChannel('dev.fluttercommunity.plus/connectivity');
    const events =
        MethodChannel('dev.fluttercommunity.plus/connectivity_status');
    messenger.setMockMethodCallHandler(storage, (_) async => null);
    messenger.setMockMethodCallHandler(connectivity, (_) async => ['none']);
    messenger.setMockMethodCallHandler(events, (_) async => null);
    final router = AppRouter();
    addTearDown(() {
      router.router.dispose();
      messenger.setMockMethodCallHandler(storage, null);
      messenger.setMockMethodCallHandler(connectivity, null);
      messenger.setMockMethodCallHandler(events, null);
    });

    await tester.pumpWidget(JagapadiApp(appRouter: router));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    final context = tester.element(find.byType(MaterialApp));
    final auth = context.read<AuthProvider>();
    expect(auth.onLogoutCallback, isNotNull);
    auth.onLogoutCallback!();
    await tester.pump();
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  });
}
