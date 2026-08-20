import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/permissions.dart';
import 'package:jagapadi_mobile/features/auth/models/user.dart';

void main() {
  group('RolePermissions.capabilitiesFor', () {
    test('admin memiliki semua kapabilitas', () {
      for (final cap in ReportCapability.values) {
        expect(
          RolePermissions.can('admin', cap),
          isTrue,
          reason: 'admin harus bisa $cap',
        );
      }
    });

    test('petugas bisa membuat dan mengirim laporan, tidak verifikasi', () {
      expect(RolePermissions.can('petugas', ReportCapability.canCreateReport),
          isTrue);
      expect(RolePermissions.can('petugas', ReportCapability.canSubmitReport),
          isTrue);
      expect(RolePermissions.can('petugas', ReportCapability.canEditOwnReport),
          isTrue);
      expect(RolePermissions.can('petugas', ReportCapability.canVerifyReport),
          isFalse);
      expect(RolePermissions.can('petugas', ReportCapability.canRejectReport),
          isFalse);
      expect(RolePermissions.can('petugas', ReportCapability.canArchiveReport),
          isFalse);
      expect(RolePermissions.can('petugas', ReportCapability.canViewAllReports),
          isFalse);
      expect(
          RolePermissions.can('petugas', ReportCapability.canManageMasterData),
          isFalse);
    });

    test('operator diperlakukan setara petugas untuk penulisan laporan', () {
      expect(RolePermissions.can('operator', ReportCapability.canCreateReport),
          isTrue);
      expect(RolePermissions.can('operator', ReportCapability.canSubmitReport),
          isTrue);
      expect(RolePermissions.can('operator', ReportCapability.canVerifyReport),
          isFalse);
    });

    test('statistisi hanya melihat dan mengekspor', () {
      expect(
          RolePermissions.can('statistisi', ReportCapability.canViewAllReports),
          isTrue);
      expect(
          RolePermissions.can('statistisi', ReportCapability.canExportReport),
          isTrue);
      expect(
          RolePermissions.can('statistisi', ReportCapability.canCreateReport),
          isFalse);
      expect(
          RolePermissions.can('statistisi', ReportCapability.canVerifyReport),
          isFalse);
    });

    test('viewer hanya melihat dashboard dan laporan', () {
      expect(RolePermissions.can('viewer', ReportCapability.canViewDashboard),
          isTrue);
      expect(RolePermissions.can('viewer', ReportCapability.canViewAllReports),
          isTrue);
      expect(RolePermissions.can('viewer', ReportCapability.canCreateReport),
          isFalse);
      expect(RolePermissions.can('viewer', ReportCapability.canExportReport),
          isFalse);
    });

    test('role tidak dikenal tidak mendapat kapabilitas apa pun (fail-closed)',
        () {
      for (final cap in ReportCapability.values) {
        expect(RolePermissions.can('role_aneh', cap), isFalse);
      }
    });
  });

  group('UserPermissions.can', () {
    test('meneruskan pengecekan ke matriks role', () {
      final user = User(
        id: 1,
        username: 'p1',
        namaLengkap: 'Petugas Satu',
        role: 'petugas',
        isActive: true,
        mustChangePassword: false,
      );
      expect(user.can(ReportCapability.canSubmitReport), isTrue);
      expect(user.can(ReportCapability.canVerifyReport), isFalse);
    });
  });
}
