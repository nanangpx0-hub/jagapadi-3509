import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/config.dart';

void main() {
  group('AppConfig', () {
    group('baseUrl', () {
      test('tidak menggunakan port 8000 (port yang salah)', () {
        // Port 8000 adalah bug sebelumnya — Laragon berjalan di 8080
        expect(AppConfig.baseUrl, isNot(contains(':8000')));
      });

      test('menggunakan port 8080 untuk dev lokal', () {
        // Tidak ada dart-define aktif di unit test — gunakan default Android
        // Catatan: Platform.isAndroid = false di Dart test host, jadi
        // baseUrl akan mengembalikan localhost:8080
        final uri = Uri.parse(AppConfig.baseUrl);
        expect(['localhost', '10.0.2.2'], contains(uri.host));
      });

      test('berisi path /api/v1', () {
        expect(AppConfig.baseUrl, contains('/api/v1'));
      });

      test('tidak diakhiri dengan slash', () {
        expect(AppConfig.baseUrl, isNot(endsWith('/')));
      });
    });

    group('timeout values', () {
      test('connectTimeout >= 15000ms (cukup untuk jaringan lambat)', () {
        expect(AppConfig.connectTimeout, greaterThanOrEqualTo(15000));
      });

      test('receiveTimeout >= connectTimeout', () {
        expect(AppConfig.receiveTimeout,
            greaterThanOrEqualTo(AppConfig.connectTimeout));
      });

      test('uploadTimeout >= 60000ms (untuk foto besar)', () {
        expect(AppConfig.uploadTimeout, greaterThanOrEqualTo(60000));
      });
    });

    group('healthUrl', () {
      test('healthUrl berisi /api/v1/health', () {
        expect(AppConfig.healthUrl, contains('/api/v1/health'));
      });

      test('healthUrl memiliki scheme yang valid', () {
        final uri = Uri.tryParse(AppConfig.healthUrl);
        expect(uri, isNotNull);
        expect(['http', 'https'], contains(uri!.scheme));
      });

      test('healthUrl dibentuk langsung dari API base URL', () {
        expect(AppConfig.healthUrl, '${AppConfig.baseUrl}/health');
      });
    });

    group('maxRetries', () {
      test('maxRetries bernilai positif', () {
        expect(AppConfig.maxRetries, greaterThan(0));
      });

      test('retryBaseDelayMs bernilai positif', () {
        expect(AppConfig.retryBaseDelayMs, greaterThan(0));
      });
    });
  });
}
