import 'dart:io';
import 'config.dart';

/// Validasi berkas foto sesuai kontrak backend JAGAPADI.
///
/// Catatan jujur: validasi ini memeriksa signature (magic bytes), ekstensi,
/// ukuran, dan keterbacaan berkas. Ini BUKAN dekompresi gambar penuh —
/// pencegahan gambar "zip bomb"/polyglot tingkat lanjut tetap bergantung
/// pada backend (layer keamanan berlapis).
class PhotoValidator {
  PhotoValidator._();

  /// Batas ukuran foto mengikuti AppConfig (default 2 MB) — hasil kompresi
  /// [PhotoCompressor] dijamin berada di bawah batas ini.
  static int get maxBytes => AppConfig.maxFotoSizeMB * 1024 * 1024;
  static const int maxMagicRead = 16;

  static const _allowedExtensions = {'jpg', 'jpeg', 'png', 'webp'};

  /// Deteksi tipe gambar dari magic bytes (tanpa eksternal dependency).
  static String? detectTypeFromBytes(List<int> bytes) {
    if (bytes.length < 4) return null;
    // JPEG: FF D8 FF
    if (bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF) {
      return 'jpeg';
    }
    // PNG: 89 50 4E 47 0D 0A 1A 0A
    if (bytes.length >= 8 &&
        bytes[0] == 0x89 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x4E &&
        bytes[3] == 0x47 &&
        bytes[4] == 0x0D &&
        bytes[5] == 0x0A &&
        bytes[6] == 0x1A &&
        bytes[7] == 0x0A) {
      return 'png';
    }
    // WebP: RIFF .... WEBP
    if (bytes.length >= 12 &&
        bytes[0] == 0x52 &&
        bytes[1] == 0x49 &&
        bytes[2] == 0x46 &&
        bytes[3] == 0x46 &&
        bytes[8] == 0x57 &&
        bytes[9] == 0x45 &&
        bytes[10] == 0x42 &&
        bytes[11] == 0x50) {
      return 'webp';
    }
    return null;
  }

  static String? _extensionOf(String path) {
    final dot = path.lastIndexOf('.');
    if (dot == -1 || dot == path.length - 1) return null;
    return path.substring(dot + 1).toLowerCase();
  }

  /// Validasi lengkap berkas foto:
  /// - berkas ada dan dapat dibaca (tidak kosong),
  /// - ekstensi diizinkan,
  /// - magic bytes sesuai ekstensi (file bukan teks/sampah),
  /// - ukuran tidak melebihi batas.
  ///
  /// Mengembalikan pesan error, atau null jika valid.
  static String? validateFile(File file) {
    if (!file.existsSync()) {
      return 'Berkas foto tidak ditemukan. Ambil ulang foto.';
    }
    if (file.lengthSync() == 0) {
      return 'Berkas foto kosong. Ambil ulang foto.';
    }
    if (file.lengthSync() > maxBytes) {
      final mb = (file.lengthSync() / (1024 * 1024)).toStringAsFixed(1);
      final maxMb = (maxBytes / (1024 * 1024)).toStringAsFixed(0);
      return 'Ukuran foto ($mb MB) melebihi batas $maxMb MB.';
    }

    final ext = _extensionOf(file.path);
    if (ext == null || !_allowedExtensions.contains(ext)) {
      return 'Format foto tidak diizinkan. Gunakan JPG, PNG, atau WebP.';
    }

    try {
      final raf = file.openSync();
      try {
        final bytes = raf.readSync(maxMagicRead);
        final detected = detectTypeFromBytes(bytes);
        if (detected == null) {
          return 'Berkas foto korup atau bukan gambar yang valid.';
        }
        final jpegAliases = {'jpg', 'jpeg'};
        final sameFamily = detected == ext ||
            (jpegAliases.contains(detected) && jpegAliases.contains(ext));
        if (!sameFamily) {
          return 'Isi berkas tidak sesuai ekstensi ($ext). '
              'Ulangi pengambilan foto.';
        }
      } finally {
        raf.closeSync();
      }
    } on FileSystemException {
      return 'Berkas foto tidak dapat dibaca. Ambil ulang foto.';
    }

    return null;
  }

  /// Validasi tipe dari byte mentah (untuk unit test tanpa berkas fisik).
  static String? validateBytes(List<int> bytes) {
    if (bytes.isEmpty) return 'Berkas foto kosong.';
    if (detectTypeFromBytes(bytes) == null) {
      return 'Berkas foto korup atau bukan gambar yang valid.';
    }
    return null;
  }
}
