import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jagapadi_mobile/core/theme.dart';

void main() {
  // google_fonts memerlukan ServicesBinding; runtime fetching dimatikan
  // agar test tidak bergantung jaringan (font hanya sebagai fallback).
  TestWidgetsFlutterBinding.ensureInitialized();
  GoogleFonts.config.allowRuntimeFetching = false;

  group('kontras warna WCAG', () {
    test('warna utama dan teks putih memenuhi AA', () {
      final colors = AppTheme.light.colorScheme;
      expect(_contrast(colors.primary, colors.onPrimary),
          greaterThanOrEqualTo(4.5));
    });

    test('teks utama pada surface memenuhi AAA', () {
      final colors = AppTheme.light.colorScheme;
      expect(
          _contrast(colors.surface, colors.onSurface), greaterThanOrEqualTo(7));
    });

    test('dark mode mempertahankan kontras teks utama', () {
      final colors = AppTheme.dark.colorScheme;
      expect(
          _contrast(colors.surface, colors.onSurface), greaterThanOrEqualTo(7));
    });
  });

  test('breakpoint responsif menghasilkan 1, 2, dan 3 kolom', () {
    expect(AppBreakpoints.columnsForWidth(360), 1);
    expect(AppBreakpoints.columnsForWidth(600), 2);
    expect(AppBreakpoints.columnsForWidth(800), 2);
    expect(AppBreakpoints.columnsForWidth(1200), 3);
  });

  test('semua tombol tema memiliki tinggi sentuh minimal 48dp', () {
    final elevated =
        AppTheme.light.elevatedButtonTheme.style?.minimumSize?.resolve({});
    final outlined =
        AppTheme.light.outlinedButtonTheme.style?.minimumSize?.resolve({});
    final text = AppTheme.light.textButtonTheme.style?.minimumSize?.resolve({});
    expect(elevated?.height, greaterThanOrEqualTo(48));
    expect(outlined?.height, greaterThanOrEqualTo(48));
    expect(text?.height, greaterThanOrEqualTo(48));
  });
}

double _contrast(Color first, Color second) {
  final lighter =
      first.computeLuminance() > second.computeLuminance() ? first : second;
  final darker = identical(lighter, first) ? second : first;
  return (lighter.computeLuminance() + 0.05) /
      (darker.computeLuminance() + 0.05);
}
