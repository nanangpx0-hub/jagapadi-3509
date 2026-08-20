import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/offline_login_policy.dart';

DateTime fixedNow() => DateTime(2026, 8, 16, 12, 0, 0);

void main() {
  group('OfflineLockPolicy', () {
    test('tidak terkunci tanpa lockUntil', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      expect(policy.isLocked(lockUntil: null), isFalse);
      expect(policy.remainingLockSeconds(lockUntil: null), 0);
    });

    test('terkunci selama lockUntil masih di masa depan', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final future = DateTime(2026, 8, 16, 12, 4, 0);
      expect(policy.isLocked(lockUntil: future), isTrue);
      expect(policy.remainingLockSeconds(lockUntil: future), 240);
    });

    test('kunci berakhir saat lockUntil lewat', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final past = DateTime(2026, 8, 16, 11, 0, 0);
      expect(policy.isLocked(lockUntil: past), isFalse);
    });

    test('registerFailure menghitung percobaan gagal', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final r1 = policy.registerFailure(failCount: 0, lockUntil: null);
      expect(r1.failCount, 1);
      expect(r1.lockUntil, isNull);
    });

    test('registerFailure mengunci saat mencapai maxAttempts', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final r = policy.registerFailure(failCount: 4, lockUntil: null);
      expect(r.failCount, 0);
      expect(r.lockUntil, DateTime(2026, 8, 16, 12, 5, 0));
    });

    test('percobaan saat terkunci tidak menambah hitungan', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final lock = DateTime(2026, 8, 16, 12, 5, 0);
      final r = policy.registerFailure(failCount: 3, lockUntil: lock);
      expect(r.failCount, 3);
      expect(r.lockUntil, lock);
    });

    test('reset mengosongkan hitungan dan kunci', () {
      const policy = OfflineLockPolicy(now: fixedNow);
      final r = policy.reset();
      expect(r.failCount, 0);
      expect(r.lockUntil, isNull);
    });

    test('maxAttempts khusus dihormati', () {
      final policy = OfflineLockPolicy(
        maxAttempts: 2,
        lockDuration: const Duration(minutes: 10),
        now: fixedNow,
      );
      final r = policy.registerFailure(failCount: 1, lockUntil: null);
      expect(r.failCount, 0);
      expect(r.lockUntil, DateTime(2026, 8, 16, 12, 10, 0));
    });
  });

  group('OfflineVerifierPolicy', () {
    test('menerima versi saat ini', () {
      expect(
        OfflineVerifierPolicy.accepts(
            OfflineVerifierPolicy.currentVersion.toString()),
        isTrue,
      );
    });

    test('menerima verifier legacy tanpa versi (migration bertahap)', () {
      expect(OfflineVerifierPolicy.accepts(null), isTrue);
    });

    test('menolak versi yang tidak dikenal (fail-closed)', () {
      expect(OfflineVerifierPolicy.accepts('0'), isFalse);
      expect(OfflineVerifierPolicy.accepts('99'), isFalse);
      expect(OfflineVerifierPolicy.accepts('abc'), isFalse);
    });
  });
}
