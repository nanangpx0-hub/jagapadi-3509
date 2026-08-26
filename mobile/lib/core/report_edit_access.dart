/// Aturan akses edit laporan berbasis kepemilikan.
///
/// Menu/kontainer aksi edit hanya boleh tampil bagi petugas pemilik
/// laporan (Persyaratan keamanan: sembunyikan di UI untuk non-pemilik).
/// Pemeriksaan server tetap sumber kebenaran; helper ini defense-in-depth
/// di sisi antarmuka dan dapat diuji unit tanpa widget.
class ReportEditAccess {
  ReportEditAccess._();

  /// true bila [currentUserId] adalah pemilik [reportUserId].
  static bool isOwner(int? reportUserId, int? currentUserId) =>
      reportUserId != null &&
      currentUserId != null &&
      reportUserId > 0 &&
      reportUserId == currentUserId;

  /// Kontainer aksi edit tampil hanya bila pengguna memiliki kapabilitas
  /// submit/edit DAN merupakan pemilik laporan.
  static bool canShowEditActions({
    required int? reportUserId,
    required int? currentUserId,
    required bool hasCapability,
  }) =>
      hasCapability && isOwner(reportUserId, currentUserId);
}
