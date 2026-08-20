import '../features/auth/models/user.dart';

/// Kapabilitas (permission) aplikasi yang diperiksa di UI dan router.
///
/// Matriks ini adalah lapisan UX (menyembunyikan aksi yang tidak diizinkan).
/// Otorisasi final tetap dilakukan backend (API), sesuai aturan proyek:
/// "UI hanya membantu UX dan bukan security boundary".
enum ReportCapability {
  canViewDashboard,
  canViewAllReports,
  canCreateReport,
  canEditOwnReport,
  canSubmitReport,
  canVerifyReport,
  canRejectReport,
  canArchiveReport,
  canExportReport,
  canManageMasterData,
}

/// Matriks permission terpusat berdasarkan role backend
/// (admin, petugas, operator, statistisi, viewer).
///
/// Role yang tidak dikenal diperlakukan paling ketat (read-only) agar
/// aplikasi tidak pernah menampilkan aksi yang backend-nya menolak.
class RolePermissions {
  RolePermissions._();

  static const Map<String, Set<ReportCapability>> _matrix = {
    'admin': {
      ReportCapability.canViewDashboard,
      ReportCapability.canViewAllReports,
      ReportCapability.canCreateReport,
      ReportCapability.canEditOwnReport,
      ReportCapability.canSubmitReport,
      ReportCapability.canVerifyReport,
      ReportCapability.canRejectReport,
      ReportCapability.canArchiveReport,
      ReportCapability.canExportReport,
      ReportCapability.canManageMasterData,
    },
    'petugas': {
      ReportCapability.canViewDashboard,
      ReportCapability.canCreateReport,
      ReportCapability.canEditOwnReport,
      ReportCapability.canSubmitReport,
      ReportCapability.canExportReport,
    },
    // Operator mengikuti kebijakan penulisan backend; di mobile diperlakukan
    // setara petugas untuk pembuatan laporan (perlu konfirmasi backend).
    'operator': {
      ReportCapability.canViewDashboard,
      ReportCapability.canCreateReport,
      ReportCapability.canEditOwnReport,
      ReportCapability.canSubmitReport,
      ReportCapability.canExportReport,
    },
    'statistisi': {
      ReportCapability.canViewDashboard,
      ReportCapability.canViewAllReports,
      ReportCapability.canExportReport,
    },
    'viewer': {
      ReportCapability.canViewDashboard,
      ReportCapability.canViewAllReports,
    },
  };

  static Set<ReportCapability> capabilitiesFor(String role) =>
      _matrix[role] ?? const <ReportCapability>{};

  static bool can(String role, ReportCapability capability) =>
      capabilitiesFor(role).contains(capability);
}

/// Ekstensi [User] untuk memeriksa permission dari role user saat ini.
extension UserPermissions on User {
  bool can(ReportCapability capability) =>
      RolePermissions.can(role, capability);
}
