import 'package:flutter_test/flutter_test.dart';

import 'package:jagapadi_mobile/core/report_edit_access.dart';

void main() {
  group('ReportEditAccess.isOwner', () {
    test('pemilik sah', () {
      expect(ReportEditAccess.isOwner(7, 7), isTrue);
    });

    test('bukan pemilik', () {
      expect(ReportEditAccess.isOwner(7, 8), isFalse);
    });

    test('report tanpa user_id (data lama) ditolak', () {
      expect(ReportEditAccess.isOwner(null, 7), isFalse);
    });

    test('pengguna belum login ditolak', () {
      expect(ReportEditAccess.isOwner(7, null), isFalse);
    });

    test('reportUserId <= 0 dianggap tidak valid', () {
      expect(ReportEditAccess.isOwner(0, 0), isFalse);
    });
  });

  group('ReportEditAccess.canShowEditActions', () {
    test('pemilik dengan kapabilitas dapat melihat aksi', () {
      expect(
        ReportEditAccess.canShowEditActions(
          reportUserId: 7,
          currentUserId: 7,
          hasCapability: true,
        ),
        isTrue,
      );
    });

    test('non-pemilik dengan kapabilitas tetap disembunyikan', () {
      expect(
        ReportEditAccess.canShowEditActions(
          reportUserId: 7,
          currentUserId: 8,
          hasCapability: true,
        ),
        isFalse,
      );
    });

    test('pemilik tanpa kapabilitas disembunyikan', () {
      expect(
        ReportEditAccess.canShowEditActions(
          reportUserId: 7,
          currentUserId: 7,
          hasCapability: false,
        ),
        isFalse,
      );
    });
  });
}
