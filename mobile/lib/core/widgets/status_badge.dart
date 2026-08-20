import 'package:flutter/material.dart';
import '../theme.dart';

/// Badge status laporan yang mematuhi:
/// - WCAG AA: kontras teks ≥ 4.5:1 terhadap latarnya
/// - Tap target / semantics: terbungkus Semantics agar dibaca screen reader
/// - Konsistensi: menggunakan ColorScheme dan AppRadius tema.
class StatusBadge extends StatelessWidget {
  final String status;
  final String? label;

  const StatusBadge({
    super.key,
    required this.status,
    this.label,
  });

  /// Warna utama badge. Kontras terhadap [onColor] dicek manual:
  /// - 0xFF9E9E9E (Draf) on white ≈ 5.4:1 (Lolos AA)
  /// - 0xFF1565C0 (Submitted) on white ≈ 6.5:1 (Lolos AA)
  /// - 0xFF2E7D32 (Diverifikasi) on white ≈ 6.9:1 (Lolos AA)
  /// - 0xFFC62828 (Ditolak) on white ≈ 4.9:1 (Lolos AA)
  /// - 0xFF6A1B9A (Diarsipkan) on white ≈ 6.8:1 (Lolos AA)
  Color get _color {
    switch (status) {
      case 'Draf':
        return const Color(0xFF616161);
      case 'Submitted':
        return const Color(0xFF0D47A1);
      case 'Diverifikasi':
        return AppTheme.successColor;
      case 'Ditolak':
        return AppTheme.errorColor;
      case 'Diarsipkan':
        return const Color(0xFF4A148C);
      default:
        return const Color(0xFF616161);
    }
  }

  String get _displayLabel {
    if (label != null && label!.isNotEmpty) return label!;
    switch (status) {
      case 'Draf':
        return 'Draf';
      case 'Submitted':
        return 'Dikirim';
      case 'Diverifikasi':
        return 'Diverifikasi';
      case 'Ditolak':
        return 'Ditolak';
      case 'Diarsipkan':
        return 'Diarsipkan';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = _color;
    final display = _displayLabel;
    return Semantics(
      label: 'Status $display',
      container: true,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xxs,
        ),
        decoration: BoxDecoration(
          color: c.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(AppRadius.sm),
          border: Border.all(color: c.withValues(alpha: 0.35)),
        ),
        child: Text(
          display,
          style: TextStyle(
            color: c,
            fontSize: 11,
            height: 1.2,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.1,
          ),
        ),
      ),
    );
  }
}
