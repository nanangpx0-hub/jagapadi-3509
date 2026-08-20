/// Kebijakan login offline: batasi percobaan, cooldown, dan versi verifier.
///
/// Logika murni (tanpa platform channel) agar dapat diuji unit.
/// Penyimpanan nilai konkret dilakukan oleh [AppSecureStorage].
class OfflineLockPolicy {
  const OfflineLockPolicy({
    this.maxAttempts = 5,
    this.lockDuration = const Duration(minutes: 5),
    required this.now,
  });

  final int maxAttempts;
  final Duration lockDuration;
  final DateTime Function() now;

  /// Apakah login offline sedang dikunci hingga [lockUntil].
  bool isLocked({DateTime? lockUntil}) {
    if (lockUntil == null) return false;
    return now().isBefore(lockUntil);
  }

  /// Sisa waktu kunci dalam detik; 0 jika tidak terkunci.
  int remainingLockSeconds({DateTime? lockUntil}) {
    if (!isLocked(lockUntil: lockUntil)) return 0;
    final remaining = lockUntil!.difference(now());
    return remaining.inSeconds < 0 ? 0 : remaining.inSeconds;
  }

  /// Catat satu percobaan gagal. Mengembalikan nilai baru
  /// (failCount, lockUntil). Saat failCount mencapai maxAttempts, akun
  /// dikunci selama [lockDuration].
  ({int failCount, DateTime? lockUntil}) registerFailure({
    required int failCount,
    DateTime? lockUntil,
  }) {
    if (isLocked(lockUntil: lockUntil)) {
      return (failCount: failCount, lockUntil: lockUntil);
    }
    final next = failCount + 1;
    if (next >= maxAttempts) {
      return (
        failCount: 0,
        lockUntil: now().add(lockDuration),
      );
    }
    return (failCount: next, lockUntil: null);
  }

  /// Reset setelah login offline berhasil.
  ({int failCount, DateTime? lockUntil}) reset() =>
      (failCount: 0, lockUntil: null);
}

/// Versi format verifier offline.
///
/// Saat format verifier berubah, versi baru ditulis dan versi lama ditolak
/// (fail-closed) untuk mencegah verifier yang tidak dapat diverifikasi.
/// Migration bertahap: user cukup login online sekali untuk menulis verifier
/// versi baru — tanpa menghapus data lain.
class OfflineVerifierPolicy {
  OfflineVerifierPolicy._();

  static const int currentVersion = 1;

  /// Verifier lama tetap diterima bila versi tersimpan tidak ada (data
  /// sebelum penambahan kolom versi) ATAU sama dengan [currentVersion].
  /// Versi lain (tidak dikenal) ditolak.
  static bool accepts(String? storedVersion) {
    if (storedVersion == null) return true; // legacy tanpa versi
    return storedVersion == currentVersion.toString();
  }
}
