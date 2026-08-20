import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/features/hama/models/laporan_hama.dart';
import 'package:jagapadi_mobile/features/hama/widgets/opt_search_field.dart';

void main() {
  testWidgets('pencarian OPT memfilter dan memilih hasil', (tester) async {
    int? selected;
    final options = [
      OptOption(id: 1, nama: 'Wereng Batang Cokelat', jenis: 'hama'),
      OptOption(id: 2, nama: 'Penggerek Batang Padi', jenis: 'hama'),
      OptOption(id: 3, nama: 'Blast Daun', jenis: 'penyakit'),
    ];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: OptSearchField(
            options: options,
            value: null,
            onChanged: (value) => selected = value,
          ),
        ),
      ),
    );

    await tester.tap(find.byKey(const Key('opt_search_field')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('opt_search_input')),
      'wereng',
    );
    await tester.pump();

    expect(find.text('Wereng Batang Cokelat'), findsOneWidget);
    expect(find.text('Blast Daun'), findsNothing);

    await tester.tap(find.text('Wereng Batang Cokelat'));
    await tester.pumpAndSettle();
    expect(selected, 1);
  });
}
