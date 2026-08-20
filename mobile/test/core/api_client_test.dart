import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';

void main() {
  group('ApiResponse.fromJson', () {
    test('normalizes list data and top-level meta for report providers', () {
      final response = ApiResponse<Map<String, dynamic>>.fromJson(
        {
          'success': true,
          'data': [
            {'id': 7, 'nama_saluran': 'Saluran Bedadung'},
          ],
          'meta': {'total': 1, 'page': 1, 'limit': 20},
        },
        200,
      );

      expect(response.success, isTrue);
      expect(response.data?['data'], isA<List<dynamic>>());
      expect(response.data?['data'], hasLength(1));
      expect(response.data?['meta']['total'], 1);
    });

    test('keeps detail object data unchanged', () {
      final response = ApiResponse<Map<String, dynamic>>.fromJson(
        {
          'success': true,
          'data': {'id': 7, 'status': 'Draf'},
        },
        200,
      );

      expect(response.data?['id'], 7);
      expect(response.data?['status'], 'Draf');
    });
  });

  group('ApiResponse Tests', () {
    test('ApiResponse.fromJson handles successful response', () {
      final json = {
        'success': true,
        'message': 'Data berhasil dimuat',
        'data': {'id': 100, 'name': 'Test'},
      };

      final response = ApiResponse<Map<String, dynamic>>.fromJson(json, 200);

      expect(response.success, isTrue);
      expect(response.statusCode, equals(200));
      expect(response.message, equals('Data berhasil dimuat'));
      expect(response.data?['id'], equals(100));
      expect(response.error, isNull);
    });

    test('ApiResponse.fromJson handles validation error (422)', () {
      final json = {
        'success': false,
        'message': 'Validasi gagal',
        'errors': {
          'tanggal': ['Tanggal wajib diisi'],
          'master_opt_id': ['OPT wajib dipilih'],
        },
      };

      final response = ApiResponse<dynamic>.fromJson(json, 422);

      expect(response.success, isFalse);
      expect(response.statusCode, equals(422));
      expect(response.errors, isNotNull);
      expect(response.errors?['tanggal'], isNotNull);
    });
  });
}
