import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/app.dart';
import 'package:jagapadi_mobile/core/router.dart';

void main() {
  group('JAGAPADI Field Officer E2E Integration Tests', () {
    testWidgets('Petugas login and menu navigation test flow', (WidgetTester tester) async {
      final appRouter = AppRouter();
      await tester.pumpWidget(JagapadiApp(appRouter: appRouter));
      await tester.pumpAndSettle();

      // Expect to be on LoginScreen
      expect(find.text('JAGAPADI'), findsOneWidget);
      expect(find.byKey(const Key('input_username')), findsOneWidget);
      expect(find.byKey(const Key('input_password')), findsOneWidget);
      expect(find.byKey(const Key('button_login')), findsOneWidget);

      // Fill credentials
      await tester.enterText(find.byKey(const Key('input_username')), 'petugas01');
      await tester.enterText(find.byKey(const Key('input_password')), 'TestPetugas!456');
      await tester.tap(find.byKey(const Key('button_login')));
      await tester.pumpAndSettle();
    });
  });
}
