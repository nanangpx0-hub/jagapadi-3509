import 'dart:io';

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
/// ### Server produksi
///   flutter build apk --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1
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
  static String get baseUrl {
    const defined = String.fromEnvironment('API_BASE_URL');
    if (defined.isNotEmpty) return defined;
    // Laragon default berjalan di port 80 (bukan 8080)
    // Struktur: http://HOST/jagapadi-3509/api/v1
    if (Platform.isAndroid) return 'http://10.0.2.2/jagapadi-3509/api/v1';
    return 'http://localhost/jagapadi-3509/api/v1';
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
