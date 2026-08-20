import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';

/// Pembuat verifier password lokal. Password asli tidak pernah disimpan.
class OfflineCredentialHasher {
  static const int defaultIterations = 20000;

  static String createSalt() {
    final random = Random.secure();
    return base64Encode(List<int>.generate(32, (_) => random.nextInt(256)));
  }

  static String derive(
    String password,
    String encodedSalt, {
    int iterations = defaultIterations,
  }) {
    if (iterations < 1) {
      throw ArgumentError.value(iterations, 'iterations', 'harus positif');
    }

    final salt = base64Decode(encodedSalt);
    final hmac = Hmac(sha256, utf8.encode(password));
    var block = hmac.convert(<int>[...salt, 0, 0, 0, 1]).bytes;
    final result = Uint8List.fromList(block);

    for (var iteration = 1; iteration < iterations; iteration++) {
      block = hmac.convert(block).bytes;
      for (var index = 0; index < result.length; index++) {
        result[index] ^= block[index];
      }
    }

    return base64Encode(result);
  }

  static bool verify(
    String password,
    String encodedSalt,
    String expected, {
    int iterations = defaultIterations,
  }) {
    final actual = derive(password, encodedSalt, iterations: iterations);
    if (actual.length != expected.length) return false;

    var difference = 0;
    for (var index = 0; index < actual.length; index++) {
      difference |= actual.codeUnitAt(index) ^ expected.codeUnitAt(index);
    }
    return difference == 0;
  }
}
