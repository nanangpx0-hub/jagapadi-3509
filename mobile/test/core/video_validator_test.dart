import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:jagapadi_mobile/core/video_validator.dart';

void main() {
  late Directory tmp;

  setUp(() {
    tmp = Directory.systemTemp.createTempSync('video_validator_test');
  });

  tearDown(() {
    tmp.deleteSync(recursive: true);
  });

  File writeVideo(String name, List<int> bytes) {
    final f = File('${tmp.path}/$name')..writeAsBytesSync(bytes);
    return f;
  }

  List<int> mp4Bytes() {
    final b = <int>[0, 0, 0, 24] + 'ftypisom'.codeUnits + List.filled(16, 0);
    return b;
  }

  List<int> movBytes() {
    final b = <int>[0, 0, 0, 24] + 'ftypqt  '.codeUnits + List.filled(16, 0);
    return b;
  }

  test('MP4 valid lolos', () {
    final f = writeVideo('v.mp4', mp4Bytes());
    expect(VideoValidator.validateFile(f), isNull);
  });

  test('MOV QuickTime (brand qt) lolos', () {
    final f = writeVideo('v.mov', movBytes());
    expect(VideoValidator.validateFile(f), isNull);
  });

  test('MOV tanpa brand qt ditolak', () {
    final f = writeVideo('fake.mov', mp4Bytes());
    expect(VideoValidator.validateFile(f), contains('MOV'));
  });

  test('Ekstensi .exe ditolak', () {
    final f = writeVideo('evil.exe', mp4Bytes());
    expect(VideoValidator.validateFile(f), contains('MP4 atau MOV'));
  });

  test('Berkas tanpa ftyp ditolak', () {
    final f = writeVideo('x.mp4', List<int>.filled(64, 65));
    expect(VideoValidator.validateFile(f), isNotNull);
  });

  test('Berkas kosong ditolak', () {
    final f = writeVideo('empty.mp4', <int>[]);
    expect(VideoValidator.validateFile(f), contains('kosong'));
  });
}
