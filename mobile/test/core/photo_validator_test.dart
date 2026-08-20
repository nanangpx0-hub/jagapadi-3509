import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/photo_validator.dart';

void main() {
  group('detectTypeFromBytes', () {
    test('mendeteksi JPEG (FF D8 FF)', () {
      expect(
          PhotoValidator.detectTypeFromBytes([0xFF, 0xD8, 0xFF, 0xE0]), 'jpeg');
    });

    test('mendeteksi PNG (89 50 4E 47 ...)', () {
      expect(
        PhotoValidator.detectTypeFromBytes(
            [0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A]),
        'png',
      );
    });

    test('mendeteksi WebP (RIFF .... WEBP)', () {
      final bytes = <int>[
        0x52,
        0x49,
        0x46,
        0x46,
        0,
        0,
        0,
        0,
        0x57,
        0x45,
        0x42,
        0x50,
      ];
      expect(PhotoValidator.detectTypeFromBytes(bytes), 'webp');
    });

    test('menolak byte acak', () {
      expect(PhotoValidator.detectTypeFromBytes([1, 2, 3, 4]), isNull);
    });

    test('menolak byte terlalu pendek', () {
      expect(PhotoValidator.detectTypeFromBytes([0xFF]), isNull);
    });
  });

  group('validateBytes', () {
    test('menerima byte JPEG valid', () {
      expect(
          PhotoValidator.validateBytes([0xFF, 0xD8, 0xFF, 0xE0, 0x00]), isNull);
    });

    test('menolak byte kosong', () {
      expect(PhotoValidator.validateBytes([]), isNotNull);
    });

    test('menolak byte bukan gambar', () {
      expect(PhotoValidator.validateBytes([1, 2, 3, 4]), isNotNull);
    });
  });

  group('validateFile', () {
    test('menolak berkas yang tidak ada', () {
      final f = File('C:/path/tidak/ada/apa_apa.jpg');
      expect(PhotoValidator.validateFile(f), isNotNull);
    });

    test('menolak ekstensi tidak diizinkan', () {
      final dir = Directory.systemTemp.createTempSync('jagapadi_photo_test');
      addTearDown(() => dir.deleteSync(recursive: true));
      final f = File('${dir.path}/foto.txt');
      f.writeAsBytesSync([0xFF, 0xD8, 0xFF, 0xE0]);
      expect(PhotoValidator.validateFile(f), isNotNull);
    });

    test('menolak isi yang tidak sesuai ekstensi (renamed file)', () {
      final dir = Directory.systemTemp.createTempSync('jagapadi_photo_test');
      addTearDown(() => dir.deleteSync(recursive: true));
      final f = File('${dir.path}/foto.png');
      f.writeAsBytesSync([0xFF, 0xD8, 0xFF, 0xE0]);
      expect(PhotoValidator.validateFile(f), isNotNull);
    });

    test('menolak ukuran melebihi 10 MB', () {
      final dir = Directory.systemTemp.createTempSync('jagapadi_photo_test');
      addTearDown(() => dir.deleteSync(recursive: true));
      final f = File('${dir.path}/besar.jpg');
      final big = <int>[0xFF, 0xD8, 0xFF, 0xE0];
      final chunk = List<int>.filled(1024, 0);
      final raf = f.openSync(mode: FileMode.write);
      try {
        for (var i = 0; i < 1024 * 10 + 1; i++) {
          raf.writeFromSync(chunk);
        }
        raf.writeFromSync(big);
      } finally {
        raf.closeSync();
      }
      expect(PhotoValidator.validateFile(f), isNotNull);
    });

    test('menerima berkas JPEG valid', () {
      final dir = Directory.systemTemp.createTempSync('jagapadi_photo_test');
      addTearDown(() => dir.deleteSync(recursive: true));
      final f = File('${dir.path}/foto.jpg');
      f.writeAsBytesSync([0xFF, 0xD8, 0xFF, 0xE0, 0x00, 0x10]);
      expect(PhotoValidator.validateFile(f), isNull);
    });

    test('menolak berkas kosong', () {
      final dir = Directory.systemTemp.createTempSync('jagapadi_photo_test');
      addTearDown(() => dir.deleteSync(recursive: true));
      final f = File('${dir.path}/kosong.jpg');
      f.writeAsBytesSync([]);
      expect(PhotoValidator.validateFile(f), isNotNull);
    });
  });
}
