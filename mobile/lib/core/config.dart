import 'dart:io';

import 'package:flutter/foundation.dart';

/// Konfigurasi terpusat JAGAPADI Mobile.
///
/// ## Cara mengatur URL server
///
/// ### Emulator Android (AVD)
/// 10.0.2.2 adalah alias khusus AVD yang menunjuk ke localhost mesin host.
/// Apache/Laragon berjalan di port 80 (default):
///   http://10.0.2.2/jagapadi-3509/api/v1
///
/// ### Perangkat fisik via USB / Wi-Fi (IP LAN: 192.168.10.5)
/// Laragon default port 80 — tanpa nomor port:
///   flutter run --dart-define=API_BASE_URL=http://192.168.10.5/jagapadi-3509/api/v1
///
/// ### Server produksi (Cloudflare Tunnel → https://jagapadi.my.id)
///   flutter build apk --dart-define=API_BASE_URL=https://jagapadi.my.id/api/v1
///   # Tunnel localhost: cloudflared tunnel --url http://localhost:8080
///   # Dashboard web: https://jagapadi.my.id/dashboard
///
/// ## Catatan port Laragon
/// Laragon default: Apache di port 80, Nginx di port 80.
/// Jika Laragon dikonfigurasi port custom (misal 8080), sesuaikan baseUrl di bawah.
class AppConfig {
  // ── URL Server ────────────────────────────────────────────────────────────

  /// Base URL yang digunakan seluruh ApiClient.
  /// Urutan prioritas:
  ///   1. dart-define API_BASE_URL (saat build/run)
  ///   2. 10.0.2.2/jagapadi-3509  — emulator Android (AVD), Laragon port 80
  ///   3. localhost/jagapadi-3509  — iOS Simulator / macOS
  /// Validates URL for the current build mode. Throws StateError with clear
  /// message if release requirements are not met. Used by [baseUrl] and tests.
  static void validateBaseUrl(String url, {bool isRelease = kReleaseMode}) {
    if (!isRelease) return;
    if (url.isEmpty) {
      throw StateError(
        'API_BASE_URL tidak boleh kosong pada release build. '
        'Build dengan: flutter build apk --release --dart-define=API_BASE_URL=https://<host>/api/v1  '
        '(wajib HTTPS Backend v1, contoh https://jagapadi.my.id/api/v1)',
      );
    }
    if (!url.startsWith('https://')) {
      throw StateError(
        'API_BASE_URL release harus memakai HTTPS: $url  '
        '(diterima hanya https://<host>/api/v1, ditolak http://)',
      );
    }
    if (url.contains('/jagapadi-3509')) {
      throw StateError(
        'API_BASE_URL release menunjuk runtime root/integrated yang salah: $url  '
        '(gunakan Backend v1 canonical /api/v1, contoh https://jagapadi.my.id/api/v1)',
      );
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !uri.path.contains('/api/v1')) {
      throw StateError(
        'API_BASE_URL release harus mengandung /api/v1 (Backend v1 canonical): $url',
      );
    }
    if (uri.host == '10.0.2.2' || uri.host == 'localhost' || uri.host == '127.0.0.1') {
      throw StateError(
        'API_BASE_URL release tidak boleh menunjuk emulator/localhost ($url). '
        'Gunakan host produksi HTTPS.',
      );
    }
  }

  static String get baseUrl {
    const defined = String.fromEnvironment('API_BASE_URL', defaultValue: '');
    final String url;
    if (defined.isNotEmpty) {
      url = defined;
    } else {
      // Development fallback (emulator / simulator) — hanya untuk debug/profile
      if (kReleaseMode) {
        // Fail build dengan pesan jelas — cegah release tanpa dart-define
        validateBaseUrl('');
      }
      if (Platform.isAndroid) {
        url = 'http://10.0.2.2/jagapadi-3509/api/v1';
      } else {
        url = 'http://localhost/jagapadi-3509/api/v1';
      }
    }
    validateBaseUrl(url);
    return url;
  }

  // ── Timeout (ms) ─────────────────────────────────────────────────────────

  static const int connectTimeout = 20000;
  static const int receiveTimeout = 30000;
  static const int uploadTimeout = 120000;

  // ── Polling ───────────────────────────────────────────────────────────────

  static const int notifPollIntervalSec = 60;

  // ── Upload ────────────────────────────────────────────────────────────────

  /// Batas maksimal ukuran foto lampiran (MB). Wajib sinkron dengan
  /// PhotoValidator.maxBytes dan backend SecureImageUploader (10 MB).
  static const int maxFotoSizeMB = 2;

  // ── Retry ────────────────────────────────────────────────────────────────

  static const int maxRetries = 2;
  static const int retryBaseDelayMs = 1000;

  // ── Health check ─────────────────────────────────────────────────────────

  /// Endpoint health check untuk NetworkDiagnosticService.
  static String get healthUrl => '$baseUrl/health';

  // ── Debug ─────────────────────────────────────────────────────────────────

  static String get debugInfo =>
      'baseUrl=$baseUrl | connect=${connectTimeout}ms | receive=${receiveTimeout}ms';
}
