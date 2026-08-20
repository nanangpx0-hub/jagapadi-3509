import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:google_fonts/google_fonts.dart';

/// Token desain JAGAPADI. Semua ukuran mengikuti grid dasar 8dp.
abstract final class AppSpacing {
  static const double xxs = 4;
  static const double xs = 8;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 24;
  static const double xl = 32;
  static const double xxl = 48;
}

abstract final class AppRadius {
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 20;
}

abstract final class AppBreakpoints {
  static const double tablet = 600;
  static const double desktop = 960;

  static int columnsForWidth(double width) {
    if (width >= desktop) return 3;
    if (width >= tablet) return 2;
    return 1;
  }
}

class AppTheme {
  // Hijau agrikultur dengan rasio kontras > 4.5:1 terhadap putih.
  static const primaryColor = Color(0xFF176B3A);
  static const secondaryColor = Color(0xFF49664F);
  static const tertiaryColor = Color(0xFF8A5A12);
  static const errorColor = Color(0xFFBA1A1A);
  static const successColor = Color(0xFF217A3C);
  static const warningColor = Color(0xFF8A5A12);

  // ── Token warna semantik tambahan untuk state umum ─────────────────────
  /// Warna latar banner offline/koneksi buruk (kontras > 4.5:1 terhadap onWarning).
  static const warningContainer = Color(0xFFFFF1D8);
  static const onWarningContainer = Color(0xFF4C2E00);

  /// Warna latar banner info / filter aktif.
  static const infoContainer = Color(0xFFE0ECFF);
  static const onInfoContainer = Color(0xFF001B3D);

  /// Surface container gradasi (sesuai Material 3).
  static const surfaceContainerLowestLight = Color(0xFFFFFFFF);
  static const surfaceContainerLowLight = Color(0xFFF3F5F1);
  static const surfaceContainerLight = Color(0xFFEDEEE9);
  static const surfaceContainerHighLight = Color(0xFFE8E9E4);

  static const _lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: primaryColor,
    onPrimary: Colors.white,
    primaryContainer: Color(0xFFB7F2C9),
    onPrimaryContainer: Color(0xFF00210E),
    secondary: secondaryColor,
    onSecondary: Colors.white,
    secondaryContainer: Color(0xFFCCE8D0),
    onSecondaryContainer: Color(0xFF071F10),
    tertiary: tertiaryColor,
    onTertiary: Colors.white,
    tertiaryContainer: Color(0xFFFFDDB0),
    onTertiaryContainer: Color(0xFF2C1700),
    error: errorColor,
    onError: Colors.white,
    errorContainer: Color(0xFFFFDAD6),
    onErrorContainer: Color(0xFF410002),
    surface: Color(0xFFF8FAF6),
    onSurface: Color(0xFF191C19),
    surfaceContainerHighest: Color(0xFFE1E4DF),
    onSurfaceVariant: Color(0xFF414942),
    outline: Color(0xFF717971),
    outlineVariant: Color(0xFFC1C9C1),
    shadow: Colors.black,
    scrim: Colors.black,
    inverseSurface: Color(0xFF2E312E),
    onInverseSurface: Color(0xFFF0F1ED),
    inversePrimary: Color(0xFF9CD5AD),
  );

  static const _darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: Color(0xFF9CD5AD),
    onPrimary: Color(0xFF00391B),
    primaryContainer: Color(0xFF00522A),
    onPrimaryContainer: Color(0xFFB7F2C9),
    secondary: Color(0xFFB0CCB4),
    onSecondary: Color(0xFF1C3523),
    secondaryContainer: Color(0xFF334B38),
    onSecondaryContainer: Color(0xFFCCE8D0),
    tertiary: Color(0xFFF3BD72),
    onTertiary: Color(0xFF4A2A00),
    tertiaryContainer: Color(0xFF693C00),
    onTertiaryContainer: Color(0xFFFFDDB0),
    error: Color(0xFFFFB4AB),
    onError: Color(0xFF690005),
    errorContainer: Color(0xFF93000A),
    onErrorContainer: Color(0xFFFFDAD6),
    surface: Color(0xFF111411),
    onSurface: Color(0xFFE1E3DE),
    surfaceContainerHighest: Color(0xFF414942),
    onSurfaceVariant: Color(0xFFC1C9C1),
    outline: Color(0xFF8B938A),
    outlineVariant: Color(0xFF414942),
    shadow: Colors.black,
    scrim: Colors.black,
    inverseSurface: Color(0xFFE1E3DE),
    onInverseSurface: Color(0xFF2E312E),
    inversePrimary: primaryColor,
  );

  static ThemeData get light => _build(_lightScheme);
  static ThemeData get dark => _build(_darkScheme);

  static ThemeData _build(ColorScheme colors) {
    // Plus Jakarta Sans: modern, readable, mendukung karakter Bahasa
    // Indonesia. Dicari lewat GoogleFonts saat online; fallback ke font
    // sistem saat offline.
    final baseText = GoogleFonts.plusJakartaSansTextTheme(
      ThemeData(brightness: colors.brightness).textTheme,
    );
    final textTheme = baseText
        .copyWith(
          displaySmall: baseText.displaySmall?.copyWith(
            fontWeight: FontWeight.w700,
            height: 1.15,
          ),
          headlineMedium: baseText.headlineMedium?.copyWith(
            fontWeight: FontWeight.w700,
            height: 1.18,
          ),
          headlineSmall: baseText.headlineSmall?.copyWith(
            fontWeight: FontWeight.w700,
            height: 1.2,
          ),
          titleLarge: baseText.titleLarge?.copyWith(
            fontWeight: FontWeight.w700,
            height: 1.25,
          ),
          titleMedium: baseText.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
            height: 1.3,
          ),
          titleSmall: baseText.titleSmall?.copyWith(
            fontWeight: FontWeight.w600,
            height: 1.3,
          ),
          bodyLarge: baseText.bodyLarge?.copyWith(height: 1.5),
          bodyMedium: baseText.bodyMedium?.copyWith(height: 1.45),
          bodySmall: baseText.bodySmall?.copyWith(height: 1.4),
          labelLarge:
              baseText.labelLarge?.copyWith(fontWeight: FontWeight.w600),
        )
        .apply(bodyColor: colors.onSurface, displayColor: colors.onSurface);

    final rounded = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.md),
    );
    final smRounded = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.sm),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colors,
      scaffoldBackgroundColor: colors.surface,
      textTheme: textTheme,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      visualDensity: VisualDensity.standard,
      pageTransitionsTheme: PageTransitionsTheme(
        builders: {
          TargetPlatform.android: PredictiveBackPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 2,
        backgroundColor: colors.primary,
        foregroundColor: colors.onPrimary,
        surfaceTintColor: colors.primary,
        titleTextStyle: textTheme.titleLarge?.copyWith(color: colors.onPrimary),
        iconTheme: IconThemeData(color: colors.onPrimary, size: 24),
        actionsIconTheme: IconThemeData(color: colors.onPrimary, size: 24),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: colors.surface,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: rounded.copyWith(
          side: BorderSide(color: colors.outlineVariant),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: colors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: colors.outline),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: colors.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: colors.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: colors.error, width: 1.5),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: BorderSide(color: colors.error, width: 2),
        ),
        errorMaxLines: 3,
        isDense: false,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          shape: rounded,
          textStyle: textTheme.labelLarge,
          elevation: 0,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          shape: rounded,
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          shape: rounded,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, 48),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          shape: rounded,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize: const Size.square(48),
          padding: EdgeInsets.zero,
          shape: const CircleBorder(),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: smRounded,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xs,
        ),
        labelStyle: textTheme.bodySmall
            ?.copyWith(fontWeight: FontWeight.w500, height: 1.2),
      ),
      listTileTheme: ListTileThemeData(
        shape: smRounded,
        minVerticalPadding: AppSpacing.sm,
        minLeadingWidth: 40,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs,
        ),
        horizontalTitleGap: AppSpacing.sm,
        iconColor: colors.onSurfaceVariant,
        textColor: colors.onSurface,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        elevation: 3,
        backgroundColor: colors.surface,
        surfaceTintColor: colors.surface,
        indicatorColor: colors.primaryContainer,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return textTheme.labelSmall?.copyWith(
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            color: selected ? colors.primary : colors.onSurfaceVariant,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? colors.primary : colors.onSurfaceVariant,
            size: 26,
          );
        }),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: colors.surface,
        indicatorColor: colors.primaryContainer,
        selectedIconTheme: IconThemeData(color: colors.primary, size: 26),
        unselectedIconTheme:
            IconThemeData(color: colors.onSurfaceVariant, size: 26),
        selectedLabelTextStyle: textTheme.labelSmall
            ?.copyWith(fontWeight: FontWeight.w700, color: colors.primary),
        unselectedLabelTextStyle:
            textTheme.labelSmall?.copyWith(color: colors.onSurfaceVariant),
        useIndicator: true,
        minWidth: 72,
      ),
      badgeTheme: BadgeThemeData(
        backgroundColor: colors.error,
        textColor: colors.onError,
        smallSize: 8,
        largeSize: 20,
        padding: const EdgeInsets.symmetric(horizontal: 6),
        textStyle: textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w700),
      ),
      dividerTheme: DividerThemeData(
        color: colors.outlineVariant,
        space: 1,
        thickness: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: rounded,
        elevation: 4,
        width: 400,
        insetPadding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.md,
          AppSpacing.md,
          AppSpacing.xxl,
        ),
        contentTextStyle:
            textTheme.bodyMedium?.copyWith(color: colors.onInverseSurface),
      ),
      dialogTheme: DialogThemeData(
        shape: rounded,
        elevation: 6,
        backgroundColor: colors.surface,
        titleTextStyle: textTheme.titleLarge,
        contentTextStyle: textTheme.bodyMedium,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: colors.surface,
        shape: const RoundedRectangleBorder(
          borderRadius:
              BorderRadius.vertical(top: Radius.circular(AppRadius.lg)),
        ),
        clipBehavior: Clip.antiAlias,
      ),
      expansionTileTheme: ExpansionTileThemeData(
        shape: rounded,
        collapsedShape: rounded,
        childrenPadding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          0,
          AppSpacing.md,
          AppSpacing.md,
        ),
        tilePadding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        iconColor: colors.primary,
        collapsedIconColor: colors.onSurfaceVariant,
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: colors.inverseSurface,
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        textStyle:
            textTheme.bodySmall?.copyWith(color: colors.onInverseSurface),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xs,
        ),
        waitDuration: const Duration(milliseconds: 500),
        showDuration: const Duration(milliseconds: 2000),
      ),
      searchBarTheme: SearchBarThemeData(
        backgroundColor: WidgetStateProperty.all(colors.surface),
        surfaceTintColor: WidgetStateProperty.all(Colors.transparent),
        shape: WidgetStateProperty.all(smRounded),
        padding: WidgetStateProperty.all(
          const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        ),
        hintStyle: WidgetStateProperty.all(
          textTheme.bodyLarge?.copyWith(color: colors.onSurfaceVariant),
        ),
        textStyle: WidgetStateProperty.all(
          textTheme.bodyLarge?.copyWith(color: colors.onSurface),
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: colors.primary,
        unselectedLabelColor: colors.onSurfaceVariant,
        labelStyle: textTheme.titleSmall,
        unselectedLabelStyle:
            textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
        indicatorSize: TabBarIndicatorSize.label,
        indicator: BoxDecoration(
          border: Border(
            bottom: BorderSide(color: colors.primary, width: 3),
          ),
        ),
        dividerColor: Colors.transparent,
      ),
      splashFactory: InkSparkle.splashFactory,
      splashColor: colors.primary.withValues(alpha: .08),
      highlightColor: colors.primary.withValues(alpha: .05),
    );
  }
}

class AppColors {
  static const background = Color(0xFFF8FAF6);
  static const card = Colors.white;
  static const textPrimary = Color(0xFF191C19);
  static const textSecondary = Color(0xFF414942);
}
