import 'package:flutter/material.dart';
import 'api_client.dart';
import 'config.dart';

/// Mengonversi [ApiResponse] menjadi pesan error yang dapat ditampilkan
/// kepada pengguna dan/atau digunakan untuk logika UI.
class ErrorHandler {
  ErrorHandler._();

  // ── Pesan error ───────────────────────────────────────────────────────────

  /// Kembalikan pesan string yang ramah pengguna dari sebuah [ApiResponse].
  static String getErrorMessage(ApiResponse<dynamic> response) {
    // ── Jaringan ─────────────────────────────────────────────────────────
    if (response.isNetworkError) {
      return response.message ??
          'Tidak dapat terhubung ke server (${AppConfig.baseUrl}). '
          'Periksa koneksi internet Anda.';
    }
    if (response.isTimeoutError) {
      return response.message ??
          'Koneksi ke server timeout. Coba lagi di jaringan yang lebih stabil.';
    }
    if (response.isSslError) {
      return response.message ??
          'Koneksi HTTPS gagal karena masalah sertifikat SSL. '
          'Hubungi administrator.';
    }

    // ── HTTP status ──────────────────────────────────────────────────────
    switch (response.statusCode) {
      case 400:
        return response.message ?? 'Permintaan tidak valid.';
      case 401:
        return 'Sesi Anda telah berakhir. Silakan login kembali.';
      case 403:
        return 'Aksi ini tidak diizinkan untuk akun Anda.';
      case 404:
        return 'Data tidak ditemukan di server.';
      case 409:
        return response.message ?? 'Terjadi konflik status laporan.';
      case 422:
        // Gabungkan semua field error jika ada
        if (response.errors != null && response.errors!.isNotEmpty) {
          return response.errors!.values
              .map((e) => e is List ? e.join('\n') : e.toString())
              .join('\n');
        }
        return response.message ?? 'Data yang diisi tidak valid.';
      case 429:
        return 'Terlalu banyak permintaan. '
            'Silakan tunggu beberapa menit dan coba lagi.';
      case 500:
      case 502:
        return 'Terjadi kesalahan internal pada server. Coba lagi nanti.';
      case 503:
        return 'Server sedang tidak tersedia (maintenance). Coba lagi nanti.';
      case 504:
        return 'Server tidak merespons (gateway timeout). '
            'Periksa koneksi atau coba lagi nanti.';
      default:
        if (response.statusCode == 0) {
          // statusCode 0 = tidak ada respons HTTP (error jaringan murni)
          return response.message ??
              'Tidak dapat terhubung ke server. '
              'Periksa koneksi internet Anda.';
        }
        return response.message ??
            'Terjadi kesalahan tidak terduga (HTTP ${response.statusCode}).';
    }
  }

  /// True jika error disebabkan oleh masalah jaringan/koneksi.
  static bool isConnectionProblem(ApiResponse<dynamic> response) =>
      response.isNetworkError ||
      response.isTimeoutError ||
      response.isSslError ||
      response.statusCode == 0;

  // ── UI helpers ────────────────────────────────────────────────────────────

  /// Tampilkan [SnackBar] merah dengan pesan error dari [ApiResponse].
  static void showApiError(
    BuildContext context,
    ApiResponse<dynamic> response, {
    SnackBarAction? action,
  }) {
    if (!context.mounted) return;
    final msg = getErrorMessage(response);
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              isConnectionProblem(response)
                  ? Icons.cloud_off
                  : Icons.error_outline,
              color: Colors.white,
              size: 18,
            ),
            const SizedBox(width: 8),
            Expanded(child: Text(msg)),
          ],
        ),
        backgroundColor: Colors.red.shade700,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 5),
        action: action,
      ),
    );
  }

  /// Tampilkan pesan sukses (SnackBar hijau).
  static void showSuccess(BuildContext context, String message) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            const Icon(Icons.check_circle, color: Colors.white, size: 18),
            const SizedBox(width: 8),
            Expanded(child: Text(message)),
          ],
        ),
        backgroundColor: Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  // ── Validasi ──────────────────────────────────────────────────────────────

  /// Validasi ekstensi foto sebelum upload.
  static String? validatePhoto(String filePath) {
    final ext = filePath.split('.').last.toLowerCase();
    const allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!allowed.contains(ext)) {
      return 'Format foto harus berupa JPG, PNG, atau WEBP. '
          'Ekstensi "$ext" tidak diizinkan.';
    }
    return null;
  }
}
