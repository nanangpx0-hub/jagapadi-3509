import 'dart:io';
import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:jagapadi_mobile/core/photo_compressor.dart';
import 'package:jagapadi_mobile/core/photo_validator.dart';
import 'package:path/path.dart' as p;

void main() {
  late Directory tempDir;

  setUp(() {
    tempDir = Directory.systemTemp.createTempSync('jagapadi_photo_test_');
  });

  tearDown(() {
    if (tempDir.existsSync()) tempDir.deleteSync(recursive: true);
  });

  File writeImage({int width = 2000, int height = 2000, int quality = 100}) {
    final image = img.Image(width: width, height: height);
    final rng = Random(42);
    for (final pixel in image) {
      pixel
        ..r = rng.nextInt(256)
        ..g = rng.nextInt(256)
        ..b = rng.nextInt(256);
    }
    final bytes = img.encodeJpg(image, quality: quality);
    final file = File(p.join(tempDir.path, 'source_$width.jpg'));
    file.writeAsBytesSync(bytes, flush: true);
    return file;
  }

  group('PhotoCompressor', () {
    test('file besar (>2MB) dikompresi menjadi <2MB dan tetap JPEG valid',
        () async {
      final source = writeImage(); // noise 2000x2000 q100 -> jauh > 2MB
      expect(source.lengthSync(), greaterThan(PhotoCompressor.maxBytes));

      final result = await PhotoCompressor.compressIfNeeded(source);

      expect(result.lengthSync(), lessThanOrEqualTo(PhotoCompressor.maxBytes));
      final bytes = result.readAsBytesSync();
      expect(PhotoValidator.detectTypeFromBytes(bytes), 'jpeg');
    });

    test('file sudah kecil dikembalikan apa adanya (tanpa kompresi)', () async {
      final source = writeImage(width: 800, height: 800, quality: 70);
      expect(source.lengthSync(), lessThan(PhotoCompressor.maxBytes));

      final result = await PhotoCompressor.compressIfNeeded(source);

      expect(result.path, source.path);
    });

    test('dimensi hasil kompresi tidak melebihi batas maksimal', () async {
      final source = writeImage(width: 3000, height: 2000);
      final result = await PhotoCompressor.compressIfNeeded(source);

      final decoded = img.decodeImage(result.readAsBytesSync());
      expect(decoded, isNotNull);
      final longest = max(decoded!.width, decoded.height);
      expect(longest, lessThanOrEqualTo(PhotoCompressor.maxDimension));
    });

    test('validasi lokal menolak file > 2MB (PhotoValidator sinkron)',
        () async {
      final source = writeImage(); // > 2MB
      final error = PhotoValidator.validateFile(source);
      expect(error, contains('melebihi batas 2 MB'));
    });
  });
}
