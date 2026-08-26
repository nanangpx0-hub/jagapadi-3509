import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'photo_validator.dart';
import 'secure_storage.dart';
import 'config.dart';
import 'video_validator.dart';

/// Amplop respons standar JAGAPADI API.
class ApiResponse<T> {
  final bool success;
  final T? data;
  final String? message;
  final String? error;
  final Map<String, dynamic>? errors;
  final int statusCode;

  const ApiResponse({
    required this.success,
    this.data,
    this.message,
    this.error,
    this.errors,
    required this.statusCode,
  });

  factory ApiResponse.fromJson(Map<String, dynamic> json, int statusCode) {
    final rawData = json['data'];
    final dynamic normalizedData;

    // Endpoint list mengembalikan `data` sebagai array JSON dan `meta`
    // pagination di level envelope. Normalkan agar provider tetap aman.
    if (rawData is List) {
      normalizedData = <String, dynamic>{
        'data': rawData,
        if (json['meta'] is Map)
          'meta': Map<String, dynamic>.from(json['meta'] as Map),
      };
    } else {
      normalizedData = rawData;
    }

    return ApiResponse(
      success: json['success'] == true,
      data: normalizedData as T?,
      message: json['message'] as String?,
      error: json['error'] as String?,
      errors: json['errors'] as Map<String, dynamic>?,
      statusCode: statusCode,
    );
  }

  /// True jika error disebabkan oleh masalah jaringan / koneksi.
  bool get isNetworkError => error == 'NetworkError';

  /// True jika error disebabkan timeout.
  bool get isTimeoutError => error == 'TimeoutError';

  /// True jika error disebabkan SSL.
  bool get isSslError => error == 'SslError';
}

/// HTTP client terpusat dengan:
/// - JWT Bearer token injection otomatis
/// - Auto-refresh token saat 401
/// - Retry otomatis untuk error jaringan sementara
/// - Logging terstruktur (debug only)
/// - Error message yang actionable per jenis DioExceptionType
class ApiClient {
  late final Dio _dio;
  final VoidCallback? onUnauthorized;
  bool _isRefreshing = false;

  ApiClient({this.onUnauthorized}) {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.baseUrl,
      connectTimeout: const Duration(milliseconds: AppConfig.connectTimeout),
      receiveTimeout: const Duration(milliseconds: AppConfig.receiveTimeout),
      headers: {
        'Accept': 'application/json',
        'X-App-Platform': 'android-flutter',
      },
    ));

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: _onRequest,
        onResponse: _onResponse,
        onError: _onError,
      ),
    );
  }

  // ── Interceptors ─────────────────────────────────────────────────────────

  Future<void> _onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await AppSecureStorage.getToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    _log(
      '→ ${options.method} ${options.path}',
      level: _LogLevel.request,
    );
    handler.next(options);
  }

  void _onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    _log(
      '← ${response.statusCode} ${response.requestOptions.path}',
      level: _LogLevel.response,
    );
    handler.next(response);
  }

  Future<void> _onError(
    DioException error,
    ErrorInterceptorHandler handler,
  ) async {
    _logError(error);

    // Auto-refresh token saat 401
    final isLoginRequest = error.requestOptions.path.endsWith('/auth/login');
    if (error.response?.statusCode == 401 &&
        !_isRefreshing &&
        !isLoginRequest) {
      _isRefreshing = true;
      final refreshed = await _tryRefresh();
      _isRefreshing = false;
      if (refreshed) {
        final token = await AppSecureStorage.getToken();
        if (token != null) {
          error.requestOptions.headers['Authorization'] = 'Bearer $token';
          try {
            final response = await _dio.fetch(error.requestOptions);
            return handler.resolve(response);
          } catch (_) {}
        }
      }
      await AppSecureStorage.clearSession();
      onUnauthorized?.call();
    }
    handler.next(error);
  }

  // ── Public HTTP methods ──────────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> get(
    String path, {
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParams);
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e, path: path);
    }
  }

  /// POST dengan retry otomatis untuk error koneksi sementara.
  ///
  /// Retry tambahan untuk 5xx HANYA jika request membawa header
  /// `Idempotency-Key` (diisi oleh sinkronisasi draf) — aman karena server
  /// dapat mencegah duplikasi. Request tanpa key tidak pernah di-retry
  /// terhadap 5xx agar tidak membuat laporan duplikat.
  Future<ApiResponse<Map<String, dynamic>>> post(
    String path, {
    Map<String, dynamic>? data,
    Map<String, String>? headers,
    int maxRetries = AppConfig.maxRetries,
  }) async {
    final hasIdempotencyKey = headers?['Idempotency-Key'] != null;
    int attempt = 0;
    while (true) {
      try {
        final response = await _dio.post(
          path,
          data: data,
          options: headers == null ? null : Options(headers: headers),
        );
        return ApiResponse.fromJson(
          response.data as Map<String, dynamic>,
          response.statusCode ?? 200,
        );
      } on DioException catch (e) {
        attempt++;
        final retryable = _isRetryable(e) ||
            (hasIdempotencyKey && _isTransientServerError(e));
        if (retryable && attempt <= maxRetries) {
          final delay = _backoffDelay(attempt);
          _log(
            'Retry $attempt/$maxRetries untuk $path dalam ${delay.inMilliseconds}ms',
            level: _LogLevel.warning,
          );
          await Future<void>.delayed(delay);
          continue;
        }
        return _handleDioError(e, path: path);
      }
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> put(
    String path, {
    Map<String, dynamic>? data,
    Map<String, String>? headers,
  }) async {
    try {
      final response = await _dio.put(
        path,
        data: data,
        options: headers == null ? null : Options(headers: headers),
      );
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e, path: path);
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> delete(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.delete(path, data: data);
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e, path: path);
    }
  }

  /// Unggah foto lampiran laporan.
  ///
  /// Sebelum dikirim, berkas divalidasi secara lokal (ada, ukuran ≤ batas
  /// AppConfig, magic bytes sesuai ekstensi) — file yang tidak valid ditolak
  /// dini tanpa membebani jaringan. Retry otomatis dengan backoff eksponensial
  /// untuk kegagalan sementara (timeout / koneksi terputus). Aman di-retry
  /// karena endpoint foto bersifat idempoten (foto lama diganti foto baru).
  Future<ApiResponse<Map<String, dynamic>>> uploadFoto(
    String path,
    String filePath, {
    void Function(double progress)? onSendProgress,
    int maxRetries = AppConfig.maxRetries,
  }) async {
    final validation = PhotoValidator.validateFile(File(filePath));
    if (validation != null) {
      return ApiResponse(
        success: false,
        message: validation,
        statusCode: 0,
      );
    }

    int attempt = 0;
    while (true) {
      try {
        final formData = FormData.fromMap({
          'foto': await MultipartFile.fromFile(
            filePath,
            filename: 'foto_${DateTime.now().millisecondsSinceEpoch}.jpg',
          ),
        });
        final response = await _dio.post(
          path,
          data: formData,
          onSendProgress: onSendProgress == null
              ? null
              : (sent, total) {
                  if (total <= 0) return;
                  onSendProgress(sent / total);
                },
          options: Options(
            sendTimeout: const Duration(milliseconds: AppConfig.uploadTimeout),
          ),
        );
        return ApiResponse.fromJson(
          response.data as Map<String, dynamic>,
          response.statusCode ?? 200,
        );
      } on DioException catch (e) {
        attempt++;
        if (_isRetryable(e) && attempt <= maxRetries) {
          final delay = _backoffDelay(attempt);
          _log(
            'Retry upload foto $attempt/$maxRetries untuk $path dalam '
            '${delay.inMilliseconds}ms',
            level: _LogLevel.warning,
          );
          await Future<void>.delayed(delay);
          continue;
        }
        return _handleDioError(e, path: path);
      }
    }
  }

  /// Unggah video pendukung laporan hama.
  ///
  /// Backend `VideoUploader` menerima MP4 maksimal 50 MB di endpoint
  /// `/laporan-hama/{id}/video` (multipart field: `video`).
  Future<ApiResponse<Map<String, dynamic>>> uploadVideo(
    String path,
    String filePath, {
    void Function(double progress)? onSendProgress,
    int maxRetries = AppConfig.maxRetries,
  }) async {
    final validation = VideoValidator.validateFile(File(filePath));
    if (validation != null) {
      return ApiResponse(
        success: false,
        message: validation,
        statusCode: 0,
      );
    }

    int attempt = 0;
    while (true) {
      try {
        final formData = FormData.fromMap({
          'video': await MultipartFile.fromFile(
            filePath,
            filename:
                'video_${DateTime.now().millisecondsSinceEpoch}.${_videoExtensionOf(filePath)}',
          ),
        });
        final response = await _dio.post(
          path,
          data: formData,
          onSendProgress: onSendProgress == null
              ? null
              : (sent, total) {
                  if (total <= 0) return;
                  onSendProgress(sent / total);
                },
          options: Options(
            sendTimeout: const Duration(milliseconds: AppConfig.uploadTimeout * 3),
          ),
        );
        return ApiResponse.fromJson(
          response.data as Map<String, dynamic>,
          response.statusCode ?? 200,
        );
      } on DioException catch (e) {
        attempt++;
        if (_isRetryable(e) && attempt <= maxRetries) {
          final delay = _backoffDelay(attempt);
          _log(
            'Retry upload video $attempt/$maxRetries untuk $path',
            level: _LogLevel.warning,
          );
          await Future<void>.delayed(delay);
          continue;
        }
        return _handleDioError(e, path: path);
      }
    }
  }

  /// Backoff eksponensial: base × 2^(attempt-1) → 1s, 2s, 4s, …
  Duration _backoffDelay(int attempt) {
    return Duration(
      milliseconds: AppConfig.retryBaseDelayMs * (1 << (attempt - 1)),
    );
  }

  /// Ekstensi aman untuk nama file video multipart (default mp4).
  String _videoExtensionOf(String path) {
    final dot = path.lastIndexOf('.');
    if (dot == -1 || dot == path.length - 1) return 'mp4';
    final ext = path.substring(dot + 1).toLowerCase();
    return ext == 'mov' ? 'mov' : 'mp4';
  }

  // ── Token Refresh ────────────────────────────────────────────────────────

  Future<bool> _tryRefresh() async {
    final token = await AppSecureStorage.getToken();
    if (token == null) return false;
    try {
      final response = await Dio(
        BaseOptions(
          baseUrl: AppConfig.baseUrl,
          connectTimeout:
              const Duration(milliseconds: AppConfig.connectTimeout),
          receiveTimeout:
              const Duration(milliseconds: AppConfig.receiveTimeout),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
          },
        ),
      ).post('/auth/refresh');

      final data = response.data as Map<String, dynamic>?;
      if (data != null && data['success'] == true) {
        final newToken = data['data']?['token'] as String?;
        if (newToken != null) {
          await AppSecureStorage.saveToken(newToken);
          _log('Token berhasil di-refresh', level: _LogLevel.info);
          return true;
        }
      }
    } on DioException catch (e) {
      _log('Refresh token gagal: ${e.type}', level: _LogLevel.warning);
    }
    return false;
  }

  // ── Error handling ───────────────────────────────────────────────────────

  /// True jika error bersifat sementara dan layak untuk di-retry.
  bool _isRetryable(DioException e) =>
      e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      e.type == DioExceptionType.connectionError;

  /// Error 5xx sementara yang aman di-retry HANYA untuk request idempotent
  /// (membawa Idempotency-Key). 500, 502, 503, 504.
  bool _isTransientServerError(DioException e) {
    final code = e.response?.statusCode;
    return code == 500 || code == 502 || code == 503 || code == 504;
  }

  ApiResponse<Map<String, dynamic>> _handleDioError(
    DioException e, {
    String? path,
  }) {
    // Ada respons HTTP dari server — parse seperti biasa
    if (e.response != null) {
      final body = e.response!.data;
      if (body is Map<String, dynamic>) {
        return ApiResponse.fromJson(body, e.response!.statusCode ?? 500);
      }
    }

    // Klasifikasi berdasarkan tipe error Dio
    return switch (e.type) {
      DioExceptionType.connectionTimeout => ApiResponse(
          success: false,
          error: 'TimeoutError',
          message:
              'Koneksi ke server timeout (>${AppConfig.connectTimeout ~/ 1000}s). '
              'Periksa kecepatan jaringan Anda.',
          statusCode: 0,
        ),
      DioExceptionType.receiveTimeout => ApiResponse(
          success: false,
          error: 'TimeoutError',
          message: 'Server terlalu lama merespons. Coba lagi nanti.',
          statusCode: 0,
        ),
      DioExceptionType.sendTimeout => ApiResponse(
          success: false,
          error: 'TimeoutError',
          message: 'Pengiriman data ke server timeout. '
              'Periksa koneksi internet Anda.',
          statusCode: 0,
        ),
      DioExceptionType.connectionError => ApiResponse(
          success: false,
          error: 'NetworkError',
          message: _buildConnectionErrorMessage(e),
          statusCode: 0,
        ),
      DioExceptionType.badCertificate => ApiResponse(
          success: false,
          error: 'SslError',
          message: 'Sertifikat SSL server tidak valid. '
              'Hubungi administrator untuk memeriksa konfigurasi HTTPS.',
          statusCode: 0,
        ),
      DioExceptionType.cancel => ApiResponse(
          success: false,
          error: 'Cancelled',
          message: 'Permintaan dibatalkan.',
          statusCode: 0,
        ),
      _ => ApiResponse(
          success: false,
          error: 'NetworkError',
          message: 'Terjadi kesalahan jaringan tidak terduga '
              '(${e.type.name}). Silakan coba lagi.',
          statusCode: e.response?.statusCode ?? 0,
        ),
    };
  }

  String _buildConnectionErrorMessage(DioException e) {
    final url = AppConfig.baseUrl;
    final inner = e.error?.toString() ?? '';

    // Deteksi SSL dari pesan inner error
    if (inner.toLowerCase().contains('ssl') ||
        inner.toLowerCase().contains('certificate') ||
        inner.toLowerCase().contains('handshake')) {
      return 'Koneksi HTTPS gagal karena masalah sertifikat SSL. '
          'Hubungi administrator server.';
    }

    // Deteksi host tidak ditemukan
    if (inner.toLowerCase().contains('failed host lookup') ||
        inner.toLowerCase().contains('no address associated')) {
      return 'Nama server tidak ditemukan ($url). '
          'Periksa URL dan koneksi internet.';
    }

    // Deteksi connection refused
    if (inner.toLowerCase().contains('connection refused') ||
        inner.toLowerCase().contains('econnrefused')) {
      return 'Server menolak koneksi di $url. '
          'Pastikan backend berjalan di port yang benar.';
    }

    return 'Tidak dapat terhubung ke server ($url). '
        'Pastikan server aktif dan perangkat terhubung ke jaringan yang benar.';
  }

  // ── Logging ──────────────────────────────────────────────────────────────

  void _log(String message, {required _LogLevel level}) {
    if (!kDebugMode) return;
    final prefix = switch (level) {
      _LogLevel.request => '[ApiClient →]',
      _LogLevel.response => '[ApiClient ←]',
      _LogLevel.info => '[ApiClient ℹ]',
      _LogLevel.warning => '[ApiClient ⚠]',
      _LogLevel.error => '[ApiClient ✗]',
    };
    debugPrint('$prefix $message');
  }

  void _logError(DioException e) {
    if (!kDebugMode) return;
    debugPrint(
      '[ApiClient ✗] ${e.type.name.toUpperCase()} | '
      '${e.requestOptions.method} ${e.requestOptions.path} | '
      'Status: ${e.response?.statusCode ?? "–"} | '
      'BaseUrl: ${AppConfig.baseUrl} | '
      'Msg: ${e.message}',
    );
    if (e.error != null) {
      debugPrint('[ApiClient ✗] Inner error: ${e.error}');
    }
  }
}

enum _LogLevel { request, response, info, warning, error }

typedef VoidCallback = void Function();
