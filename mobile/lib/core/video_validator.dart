import 'dart:io';

/// Validator video pendukung laporan hama.
/// Sinkron dengan backend `LaporanVideoController` (MP4/MOV, maksimal 50 MB).
class VideoValidator {
  VideoValidator._();

  static const int maxBytes = 50 * 1024 * 1024; // 50 MB
  static const List<String> allowedExtensions = ['mp4', 'mov'];

  /// Kembalikan null jika valid, atau pesan error jika tidak.
  static String? validateFile(File file) {
    if (!file.existsSync()) return 'File video tidak ditemukan.';

    final size = file.lengthSync();
    if (size == 0) return 'File video kosong.';
    if (size > maxBytes) {
      final mb = (size / (1024 * 1024)).toStringAsFixed(1);
      return 'Ukuran video $mb MB melebihi batas maksimal 50 MB.';
    }

    final ext = file.path.split('.').last.toLowerCase();
    if (!allowedExtensions.contains(ext)) {
      return 'Format video harus MP4 atau MOV.';
    }

    // Magic bytes check: MP4/MOV diawali box "ftyp" pada offset 4-8.
    final raf = file.openSync();
    try {
      if (raf.lengthSync() < 12) return 'File video tidak valid.';
      raf.setPositionSync(4);
      final bytes = raf.readSync(4);
      final signature = String.fromCharCodes(bytes);
      if (signature != 'ftyp') {
        return 'Berkas bukan video MP4/MOV yang valid.';
      }
      // MOV QuickTime memakai brand "qt" pada offset 8-12.
      if (ext == 'mov') {
        raf.setPositionSync(8);
        final brand = String.fromCharCodes(raf.readSync(2));
        if (brand != 'qt') {
          return 'Berkas MOV tidak valid.';
        }
      }
    } finally {
      raf.closeSync();
    }

    return null;
  }
}
