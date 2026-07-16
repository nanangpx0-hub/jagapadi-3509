import 'package:dio/dio.dart';
import 'secure_storage.dart';
import 'config.dart';

class ApiResponse<T> {
  final bool success;
  final T? data;
  final String? message;
  final String? error;
  final Map<String, dynamic>? errors;
  final int statusCode;

  ApiResponse({
    required this.success,
    this.data,
    this.message,
    this.error,
    this.errors,
    required this.statusCode,
  });

  factory ApiResponse.fromJson(Map<String, dynamic> json, int statusCode) {
    return ApiResponse(
      success: json['success'] == true,
      data: json['data'] as T?,
      message: json['message'] as String?,
      error: json['error'] as String?,
      errors: json['errors'] as Map<String, dynamic>?,
      statusCode: statusCode,
    );
  }
}

class ApiClient {
  late final Dio _dio;
  final VoidCallback? onUnauthorized;
  bool _isRefreshing = false;

  ApiClient({this.onUnauthorized}) {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.baseUrl,
      connectTimeout: const Duration(milliseconds: AppConfig.connectTimeout),
      receiveTimeout: const Duration(milliseconds: AppConfig.receiveTimeout),
      headers: {'Accept': 'application/json'},
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await AppSecureStorage.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401 && !_isRefreshing) {
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
          AppSecureStorage.clearAll();
          onUnauthorized?.call();
        }
        handler.next(error);
      },
    ));
  }

  Future<bool> _tryRefresh() async {
    final token = await AppSecureStorage.getToken();
    if (token == null) return false;
    try {
      final response = await Dio(BaseOptions(
        baseUrl: AppConfig.baseUrl,
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
        connectTimeout: const Duration(milliseconds: AppConfig.connectTimeout),
        receiveTimeout: const Duration(milliseconds: AppConfig.receiveTimeout),
      )).post('/auth/refresh');
      final data = response.data as Map<String, dynamic>?;
      if (data != null && data['success'] == true) {
        final newToken = data['data']?['token'] as String?;
        if (newToken != null) {
          await AppSecureStorage.saveToken(newToken);
          return true;
        }
      }
    } catch (_) {}
    return false;
  }

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
      return _handleDioError(e);
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> post(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.post(path, data: data);
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e);
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> put(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.put(path, data: data);
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e);
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> delete(String path) async {
    try {
      final response = await _dio.delete(path);
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e);
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> uploadFoto(
    String path,
    String filePath,
  ) async {
    try {
      final formData = FormData.fromMap({
        'foto': await MultipartFile.fromFile(filePath,
            filename: 'foto_${DateTime.now().millisecondsSinceEpoch}.jpg'),
      });
      final response = await _dio.post(
        path,
        data: formData,
        options: Options(
          sendTimeout:
              const Duration(milliseconds: AppConfig.uploadTimeout),
        ),
      );
      return ApiResponse.fromJson(
        response.data as Map<String, dynamic>,
        response.statusCode ?? 200,
      );
    } on DioException catch (e) {
      return _handleDioError(e);
    }
  }

  ApiResponse<Map<String, dynamic>> _handleDioError(DioException e) {
    if (e.response != null) {
      final body = e.response!.data as Map<String, dynamic>?;
      if (body != null) {
        return ApiResponse.fromJson(body, e.response!.statusCode ?? 500);
      }
    }
    final message = e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout
        ? 'Koneksi timeout. Periksa jaringan Anda.'
        : e.type == DioExceptionType.connectionError
            ? 'Tidak dapat terhubung ke server.'
            : 'Terjadi kesalahan. Silakan coba lagi.';
    return ApiResponse(
      success: false,
      error: 'NetworkError',
      message: message,
      statusCode: e.response?.statusCode ?? 500,
    );
  }
}

typedef VoidCallback = void Function();
