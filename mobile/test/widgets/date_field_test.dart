import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/widgets/date_field.dart';

void main() {
  testWidgets('DateField menampilkan perubahan tanggal dari controller', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('id', 'ID'),
        supportedLocales: const [Locale('id', 'ID')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Scaffold(
          body: Form(child: DateField(controller: controller)),
        ),
      ),
    );

    controller.text = '2026-08-14';
    await tester.pump();

    expect(find.text('14 Agustus 2026'), findsOneWidget);
  });
}
