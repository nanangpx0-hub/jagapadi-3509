import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/core/error_handler.dart';

void main() {
  // ── getErrorMessage ───────────────────────────────────────────────────────
  group('ErrorHandler.getErrorMessage', () {
    ApiResponse<Map<String, dynamic>> _make({
      bool success = false,
      String? error,
      String? message,
      Map<String, dynamic>? errors,
      required int statusCode,
    }) =>
        ApiResponse(
          success: success,
          error: error,
          message: message,
          errors: errors,
          statusCode: statusCode,
        );

    group('network errors', () {
      test('NetworkError mengembalikan pesan koneksi', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(error: 'NetworkError', statusCode: 0),
        );
        expect(msg.toLowerCase(), contains('server'));
      });

      test('TimeoutError mengembalikan pesan timeout', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(error: 'TimeoutError', statusCode: 0),
        );
        expect(msg.toLowerCase(), anyOf(contains('timeout'), contains('lambat')));
      });

      test('SslError mengembalikan pesan SSL', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(error: 'SslError', statusCode: 0),
        );
        expect(msg.toLowerCase(), contains('ssl'));
      });

      test('statusCode=0 tanpa error field mengembalikan pesan koneksi', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 0));
        expect(msg, isNotEmpty);
      });
    });

    group('HTTP status codes', () {
      test('401 → pesan sesi berakhir', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 401));
        expect(msg.toLowerCase(), contains('sesi'));
      });

      test('403 → pesan tidak diizinkan', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 403));
        expect(msg.toLowerCase(), contains('izin'));
      });

      test('404 → pesan tidak ditemukan', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 404));
        expect(msg.toLowerCase(), contains('tidak ditemukan'));
      });

      test('422 dengan errors map → gabung semua pesan field', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(
            statusCode: 422,
            errors: {
              'tanggal': 'Tanggal wajib diisi',
              'master_opt_id': ['OPT wajib dipilih'],
            },
          ),
        );
        expect(msg, contains('Tanggal wajib diisi'));
        expect(msg, contains('OPT wajib dipilih'));
      });

      test('422 tanpa errors → gunakan message', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(statusCode: 422, message: 'Data laporan tidak valid'),
        );
        expect(msg, 'Data laporan tidak valid');
      });

      test('429 → pesan rate limit', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 429));
        expect(msg.toLowerCase(), anyOf(contains('permintaan'), contains('menit')));
      });

      test('500 → pesan server error', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 500));
        expect(msg.toLowerCase(), contains('server'));
      });

      test('503 → pesan maintenance', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 503));
        expect(msg.toLowerCase(), anyOf(contains('server'), contains('tersedia')));
      });
    });

    group('message fallback', () {
      test('status tidak dikenal dengan message → gunakan message', () {
        final msg = ErrorHandler.getErrorMessage(
          _make(statusCode: 418, message: 'I am a teapot'),
        );
        expect(msg, contains('I am a teapot'));
      });

      test('status tidak dikenal tanpa message → ada fallback', () {
        final msg = ErrorHandler.getErrorMessage(_make(statusCode: 599));
        expect(msg, isNotEmpty);
      });
    });
  });

  // ── isConnectionProblem ───────────────────────────────────────────────────
  group('ErrorHandler.isConnectionProblem', () {
    test('NetworkError adalah connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, error: 'NetworkError', statusCode: 0),
        ),
        isTrue,
      );
    });

    test('TimeoutError adalah connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, error: 'TimeoutError', statusCode: 0),
        ),
        isTrue,
      );
    });

    test('SslError adalah connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, error: 'SslError', statusCode: 0),
        ),
        isTrue,
      );
    });

    test('statusCode=0 adalah connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, statusCode: 0),
        ),
        isTrue,
      );
    });

    test('401 bukan connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, statusCode: 401),
        ),
        isFalse,
      );
    });

    test('422 bukan connection problem', () {
      expect(
        ErrorHandler.isConnectionProblem(
          const ApiResponse(success: false, statusCode: 422),
        ),
        isFalse,
      );
    });
  });

  // ── validatePhoto ─────────────────────────────────────────────────────────
  group('ErrorHandler.validatePhoto', () {
    test('jpg diterima', () {
      expect(ErrorHandler.validatePhoto('/path/foto.jpg'), isNull);
    });

    test('jpeg diterima', () {
      expect(ErrorHandler.validatePhoto('/path/foto.jpeg'), isNull);
    });

    test('png diterima', () {
      expect(ErrorHandler.validatePhoto('/path/foto.png'), isNull);
    });

    test('webp diterima', () {
      expect(ErrorHandler.validatePhoto('/path/foto.webp'), isNull);
    });

    test('gif ditolak', () {
      expect(ErrorHandler.validatePhoto('/path/animasi.gif'), isNotNull);
    });

    test('pdf ditolak', () {
      expect(ErrorHandler.validatePhoto('/path/doc.pdf'), isNotNull);
    });

    test('pesan error mencantumkan ekstensi yang tidak valid', () {
      final msg = ErrorHandler.validatePhoto('/path/file.bmp');
      expect(msg, isNotNull);
      expect(msg!.toLowerCase(), contains('bmp'));
    });
  });
}
