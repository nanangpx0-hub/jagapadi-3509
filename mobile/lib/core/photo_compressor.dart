import 'dart:io';
import 'dart:isolate';
import 'dart:typed_data';

import 'package:image/image.dart' as img;

import 'config.dart';

/// Kompresi foto lampiran laporan agar ukurannya di bawah batas [maxBytes]
/// (default 2 MB) sebelum dikirim ke server.
///
/// Pekerjaan berat (decode, resize, re-encode) dijalankan di isolate terpisah
/// melalui [Isolate.run] sehingga main isolate (UI) tidak terblokir.
///
/// Catatan:
/// - Output selalu JPEG (foto kamera sudah JPEG; PNG/WebP besar dari galeri
///   dikonversi untuk menghemat ukuran).
/// - Jika berkas sudah di bawah batas, dikembalikan apa adanya (tanpa kerja).
class PhotoCompressor {
  PhotoCompressor._();

  /// Batas ukuran target — sinkron dengan AppConfig.maxFotoSizeMB.
  static int get maxBytes => AppConfig.maxFotoSizeMB * 1024 * 1024;

  /// Dimensi maksimal sisi panjang setelah kompresi.
  static const int maxDimension = 1600;

  /// Tingkat kualitas yang dicoba berurutan (turun saat masih terlalu besar).
  static const List<int> _qualities = [85, 70, 55, 40];

  /// Kompresi [source] jika ukurannya melebihi [maxBytes].
  ///
  /// Mengembalikan [source] bila tidak perlu dikompresi, atau [File] baru
  /// (suffix `.opt.jpg`) berisi hasil kompresi. Jika decode gagal, berkas
  /// asli dikembalikan — validasi magic bytes tetap dilakukan di
  /// [PhotoValidator] sebelum pengiriman.
  static Future<File> compressIfNeeded(
    File source, {
    int? maxBytes,
  }) async {
    final limit = maxBytes ?? PhotoCompressor.maxBytes;
    if (await source.length() <= limit) return source;

    final Uint8List input;
    try {
      input = await source.readAsBytes();
    } on FileSystemException {
      return source;
    }

    final Uint8List? output;
    try {
      output = await Isolate.run(() => _compressSync(input, limit));
    } catch (_) {
      return source;
    }
    if (output == null) return source;

    final outFile = File('${source.path}.opt.jpg');
    try {
      await outFile.writeAsBytes(output, flush: true);
    } on FileSystemException {
      return source;
    }
    return outFile;
  }

  /// Kompresi sinkron — HANYA dipanggil dari dalam [Isolate.run].
  static Uint8List? _compressSync(Uint8List input, int limit) {
    img.Image? image = img.decodeImage(input);
    if (image == null) return null;

    image = img.bakeOrientation(image);

    var current = image;
    // Dua tahap dimensi: 1600 lalu 1280 bila masih terlalu besar.
    for (final dimension in <int>[maxDimension, 1280]) {
      if (current.width > dimension || current.height > dimension) {
        current = _resizeTo(current, dimension);
      }
      for (final quality in _qualities) {
        final encoded = img.encodeJpg(current, quality: quality);
        if (encoded.length <= limit) return encoded;
      }
    }
    // Kasus ekstrem: masih di atas batas — kirim hasil kualitas terendah
    // (jarang terjadi; backend masih menerima hingga 10 MB).
    return img.encodeJpg(current, quality: _qualities.last);
  }

  static img.Image _resizeTo(img.Image image, int dimension) {
    final double scale =
        dimension / (image.width > image.height ? image.width : image.height);
    return img.copyResize(
      image,
      width: (image.width * scale).round(),
      height: (image.height * scale).round(),
      interpolation: img.Interpolation.linear,
    );
  }
}
