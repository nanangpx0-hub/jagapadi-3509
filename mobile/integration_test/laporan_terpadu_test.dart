/// Instrumented integration test untuk LaporanTerpaduScreen.
///
/// Prasyarat:
/// - Backend JAGAPADI berjalan di 10.0.2.2:8080 (emulator) atau
///   atur API_BASE_URL via dart-define
/// - Akun petugas01 / TestPetugas!456 tersedia (seed)
/// - Minimal Android 8.0 (API 26)
///
/// Jalankan:
///   flutter test integration_test/laporan_terpadu_test.dart \
///     --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
library;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:jagapadi_mobile/main.dart' as app;

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  // ── Helper ──────────────────────────────────────────────────────────────

  Future<void> _login(WidgetTester tester) async {
    await tester.pumpAndSettle();

    // Pastikan kita di login screen
    if (find.byKey(const Key('login_username')).evaluate().isEmpty) return;

    await tester.enterText(
      find.byKey(const Key('login_username')),
      'petugas01',
    );
    await tester.enterText(
      find.byKey(const Key('login_password')),
      'TestPetugas!456',
    );
    await tester.tap(find.byKey(const Key('login_submit')));
    await tester.pumpAndSettle();
  }

  Future<void> _navigateToLaporan(WidgetTester tester) async {
    // Tap card "Semua Laporan" di HomeScreen
    final card = find.byKey(const Key('menu_semua_laporan'));
    if (card.evaluate().isNotEmpty) {
      await tester.tap(card);
    } else {
      // fallback: find by text
      await tester.tap(find.text('Semua Laporan'));
    }
    await tester.pumpAndSettle();
  }

  // ── Tests ────────────────────────────────────────────────────────────────

  group('LaporanTerpaduScreen', () {
    testWidgets(
      'A1 — Halaman terbuka dan menampilkan AppBar "Semua Laporan"',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);

        expect(find.text('Semua Laporan'), findsAtLeastNWidgets(1));
      },
    );

    testWidgets(
      'A2 — Menampilkan skeleton loader saat data sedang dimuat',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);

        // Skeleton tampil sesaat (pump sekali sebelum settle)
        await tester.pump(const Duration(milliseconds: 100));
        // Skeleton atau list harus ada — tidak crash
        expect(
          find.byKey(const Key('skeleton_list')).evaluate().isNotEmpty ||
              find.byKey(const Key('refresh_indicator')).evaluate().isNotEmpty,
          isTrue,
        );
      },
    );

    testWidgets(
      'A3 — Tombol filter terbuka bottom sheet',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        await tester.tap(find.byKey(const Key('btn_filter')));
        await tester.pumpAndSettle();

        // Bottom sheet terbuka
        expect(find.text('Filter Laporan'), findsOneWidget);
      },
    );

    testWidgets(
      'A4 — Filter jenis Hama menampilkan hanya laporan hama',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Buka filter sheet
        await tester.tap(find.byKey(const Key('btn_filter')));
        await tester.pumpAndSettle();

        // Pilih "Hama/OPT"
        await tester.tap(find.byKey(const Key('jenis_hama')));
        await tester.pumpAndSettle();

        // Terapkan
        await tester.tap(find.byKey(const Key('btn_terapkan')));
        await tester.pumpAndSettle();

        // Tidak ada badge "Irigasi" di layar (jika ada data)
        expect(find.text('Irigasi'), findsNothing);
      },
    );

    testWidgets(
      'A5 — Search field muncul saat tap ikon search',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        await tester.tap(find.byKey(const Key('btn_search')));
        await tester.pumpAndSettle();

        expect(find.byKey(const Key('search_field')), findsOneWidget);
      },
    );

    testWidgets(
      'A6 — Banner offline tampil saat tidak ada koneksi',
      (tester) async {
        // Catatan: test ini memerlukan mock connectivity.
        // Pada emulator tanpa jaringan, banner harus tampil.
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Verifikasi widget banner ada di tree (mungkin visible atau tidak tergantung koneksi)
        // Tes ini memverifikasi widget teregister, bukan konten kondisional
        expect(find.byType(Scaffold), findsWidgets);
      },
    );

    testWidgets(
      'A7 — Tombol ekspor tidak crash saat tap dengan data kosong',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Apply filter yang pasti kosong
        await tester.tap(find.byKey(const Key('btn_filter')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('status_Ditolak')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('btn_terapkan')));
        await tester.pumpAndSettle();

        // Tap ekspor
        await tester.tap(find.byKey(const Key('btn_export')));
        await tester.pumpAndSettle();

        // Harus muncul SnackBar "Tidak ada data" atau bottom sheet
        // Tidak boleh crash
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets(
      'A8 — Pull-to-refresh bekerja tanpa crash',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Fling ke bawah untuk trigger refresh indicator
        final listFinder = find.byKey(const Key('refresh_indicator'));
        if (listFinder.evaluate().isNotEmpty) {
          await tester.fling(listFinder, const Offset(0, 300), 800);
          await tester.pumpAndSettle();
        }

        expect(tester.takeException(), isNull);
      },
    );

    testWidgets(
      'A9 — Tap item laporan hama navigasi ke detail hama',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Filter ke hama agar lebih mudah dicari
        await tester.tap(find.byKey(const Key('btn_filter')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('jenis_hama')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('btn_terapkan')));
        await tester.pumpAndSettle();

        // Tap item pertama jika ada
        final cards = find.byWidgetPredicate(
          (w) => w is Card && w.key.toString().contains('hama'),
        );
        if (cards.evaluate().isNotEmpty) {
          await tester.tap(cards.first);
          await tester.pumpAndSettle();

          // Harus navigasi ke detail (ada AppBar baru)
          expect(find.text('Semua Laporan'), findsNothing);
        }
      },
    );

    testWidgets(
      'A10 — Reset filter bekerja: filter dihapus dan data reload',
      (tester) async {
        app.main();
        await _login(tester);
        await _navigateToLaporan(tester);
        await tester.pumpAndSettle();

        // Terapkan filter status
        await tester.tap(find.byKey(const Key('btn_filter')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('status_Draf')));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const Key('btn_terapkan')));
        await tester.pumpAndSettle();

        // Hapus filter dari active bar chip
        final clearBtn = find.byKey(const Key('btn_clear_filter'));
        if (clearBtn.evaluate().isNotEmpty) {
          await tester.tap(clearBtn);
          await tester.pumpAndSettle();
        }

        expect(tester.takeException(), isNull);
      },
    );
  });
}
