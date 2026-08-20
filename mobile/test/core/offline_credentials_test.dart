import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/offline_credentials.dart';

void main() {
  const salt = 'AAECAwQFBgcICQoLDA0ODw==';

  test('verifier menerima password yang benar', () {
    final verifier = OfflineCredentialHasher.derive(
      'rahasia-ku',
      salt,
      iterations: 10,
    );

    expect(
      OfflineCredentialHasher.verify(
        'rahasia-ku',
        salt,
        verifier,
        iterations: 10,
      ),
      isTrue,
    );
  });

  test('verifier menolak password yang salah', () {
    final verifier = OfflineCredentialHasher.derive(
      'password-benar',
      salt,
      iterations: 10,
    );

    expect(
      OfflineCredentialHasher.verify(
        'password-salah',
        salt,
        verifier,
        iterations: 10,
      ),
      isFalse,
    );
  });
}
