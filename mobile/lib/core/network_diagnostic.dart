import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'config.dart';

/// Jenis kegagalan koneksi yang terdiagnosis.
enum ConnectionFailure {
  /// Tidak ada koneksi jaringan sama sekali (Wi-Fi/data mati)
  noNetwork,

  /// Ada jaringan tapi server tidak bisa dijangkau (IP salah, server mati)
  serverUnreachable,

  /// Server bisa dijangkau tapi respons lambat / timeout
  serverTimeout,

  /// Masalah SSL/TLS — sertifikat tidak valid atau koneksi HTTPS ditolak
  sslError,

  /// Respons server bukan JSON / API path salah
  invalidResponse,

  /// Tidak ada masalah
  none,
}

/// Hasil diagnostik lengkap.
class DiagnosticResult {
  final ConnectionFailure failure;
  final String summary;
  final String userMessage;
  final String? technicalDetail;
  final List<String> suggestions;
  final Duration elapsed;

  const DiagnosticResult({
    required this.failure,
    required this.summary,
    required this.userMessage,
    required this.suggestions,
    required this.elapsed,
    this.technicalDetail,
  });

  bool get isHealthy => failure == ConnectionFailure.none;

  @override
  String toString() =>
      'DiagnosticResult(failure=$failure, elapsed=${elapsed.inMilliseconds}ms, '
      'summary=$summary)';
}

/// Service untuk mendiagnosis penyebab error "tidak dapat terhubung ke server"
/// secara terstruktur, memberikan pesan error yang actionable kepada pengguna.
///
/// Urutan pemeriksaan:
///   1. Cek DNS / network layer (socket lookup)
///   2. Cek TCP ke host:port server
///   3. Cek HTTP health endpoint
///   4. Validasi format respons JSON
class NetworkDiagnosticService {
  NetworkDiagnosticService._();

  static const Duration _tcpTimeout     = Duration(seconds: 5);
  static const Duration _httpTimeout    = Duration(seconds: 10);
  static const Duration _dnsTimeout     = Duration(seconds: 5);
  static const String   _publicDnsHost  = '8.8.8.8';

  /// Jalankan rangkaian diagnosis dan kembalikan hasil.
  static Future<DiagnosticResult> diagnose() async {
    final stopwatch = Stopwatch()..start();

    // ── Langkah 1: Cek koneksi internet umum ────────────────────────────────
    final hasInternet = await _checkInternetConnectivity();
    if (!hasInternet) {
      stopwatch.stop();
      return DiagnosticResult(
        failure: ConnectionFailure.noNetwork,
        elapsed: stopwatch.elapsed,
        summary: 'Tidak ada koneksi internet',
        userMessage:
            'Perangkat Anda tidak terhubung ke internet. '
            'Aktifkan Wi-Fi atau data seluler lalu coba lagi.',
        suggestions: [
          'Aktifkan Wi-Fi dan pastikan terhubung ke jaringan',
          'Atau aktifkan data seluler',
          'Jika sudah aktif, matikan lalu nyalakan ulang Wi-Fi',
          'Periksa apakah mode pesawat aktif',
        ],
      );
    }

    // ── Langkah 2: Parse URL server ──────────────────────────────────────────
    final serverUri = Uri.tryParse(AppConfig.baseUrl);
    if (serverUri == null) {
      stopwatch.stop();
      return DiagnosticResult(
        failure: ConnectionFailure.serverUnreachable,
        elapsed: stopwatch.elapsed,
        summary: 'URL server tidak valid: ${AppConfig.baseUrl}',
        userMessage: 'Konfigurasi URL server tidak valid. Hubungi administrator.',
        technicalDetail: 'API_BASE_URL="${AppConfig.baseUrl}" tidak dapat di-parse',
        suggestions: [
          'Verifikasi nilai API_BASE_URL saat build',
          'Format: http://HOST:PORT/api/v1',
        ],
      );
    }

    final host = serverUri.host;
    final port = serverUri.port > 0 ? serverUri.port : (serverUri.scheme == 'https' ? 443 : 80);

    // ── Langkah 3: Cek TCP ke server ─────────────────────────────────────────
    final tcpReachable = await _checkTcpConnection(host, port);
    if (!tcpReachable) {
      stopwatch.stop();
      final isLocalhost = host == 'localhost' || host == '127.0.0.1';
      final isEmulatorAlias = host == '10.0.2.2';

      return DiagnosticResult(
        failure: ConnectionFailure.serverUnreachable,
        elapsed: stopwatch.elapsed,
        summary: 'Server $host:$port tidak dapat dijangkau',
        userMessage: _buildServerUnreachableMessage(host, port),
        technicalDetail: 'TCP connect ke $host:$port gagal dalam ${_tcpTimeout.inSeconds}s',
        suggestions: _buildUnreachableSuggestions(
          host: host,
          port: port,
          isLocalhost: isLocalhost,
          isEmulatorAlias: isEmulatorAlias,
        ),
      );
    }

    // ── Langkah 4: Cek HTTP health endpoint ──────────────────────────────────
    final httpResult = await _checkHttpHealth(serverUri.scheme, host, port);
    if (httpResult != null) {
      stopwatch.stop();
      return DiagnosticResult(
        failure: httpResult.failure,
        elapsed: stopwatch.elapsed,
        summary: httpResult.summary,
        userMessage: httpResult.userMessage,
        technicalDetail: httpResult.technicalDetail,
        suggestions: httpResult.suggestions,
      );
    }

    // ── Semua pemeriksaan lulus ──────────────────────────────────────────────
    stopwatch.stop();
    return DiagnosticResult(
      failure: ConnectionFailure.none,
      elapsed: stopwatch.elapsed,
      summary: 'Koneksi ke server berhasil',
      userMessage: 'Koneksi ke server normal.',
      suggestions: const [],
    );
  }

  // ── Implementasi pemeriksaan ─────────────────────────────────────────────

  /// Cek koneksi internet dasar dengan lookup DNS publik.
  static Future<bool> _checkInternetConnectivity() async {
    try {
      final result = await InternetAddress.lookup(_publicDnsHost)
          .timeout(_dnsTimeout);
      return result.isNotEmpty;
    } on SocketException {
      return false;
    } on TimeoutException {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Cek apakah TCP socket ke host:port bisa dibuka.
  static Future<bool> _checkTcpConnection(String host, int port) async {
    Socket? socket;
    try {
      socket = await Socket.connect(host, port, timeout: _tcpTimeout);
      return true;
    } on SocketException {
      return false;
    } on TimeoutException {
      return false;
    } catch (_) {
      return false;
    } finally {
      socket?.destroy();
    }
  }

  /// Cek HTTP health endpoint dan validasi respons JSON.
  static Future<_PartialResult?> _checkHttpHealth(
    String scheme,
    String host,
    int port,
  ) async {
    final healthUrl = AppConfig.healthUrl;
    final client = HttpClient()
      ..connectionTimeout = _httpTimeout
      ..badCertificateCallback =
          (X509Certificate cert, String host, int port) => false; // strict SSL

    try {
      final req = await client
          .getUrl(Uri.parse(healthUrl))
          .timeout(_httpTimeout);
      req.headers.set(HttpHeaders.acceptHeader, 'application/json');
      final res = await req.close().timeout(_httpTimeout);

      // Baca body
      final body = await res.transform(const SystemEncoding().decoder)
          .join()
          .timeout(_httpTimeout);

      if (res.statusCode >= 200 && res.statusCode < 300) {
        // Validasi JSON minimal
        if (!body.contains('"success"') && !body.contains('"JAGAPADI"')) {
          return _PartialResult(
            failure: ConnectionFailure.invalidResponse,
            summary: 'Health endpoint merespons tapi bukan dari API JAGAPADI',
            userMessage:
                'Server merespons tapi URL API tidak valid. '
                'Periksa konfigurasi path /api/v1.',
            technicalDetail:
                'GET $healthUrl → HTTP ${res.statusCode}, '
                'body tidak mengandung response JAGAPADI',
            suggestions: [
              'Pastikan path API: /api/v1',
              'Pastikan document root backend = backend/public',
              'Cek apakah server mengembalikan HTML (halaman error PHP)',
            ],
          );
        }
        return null; // Sehat
      }

      if (res.statusCode == 503) {
        return _PartialResult(
          failure: ConnectionFailure.serverUnreachable,
          summary: 'Server API mengembalikan 503 (Database tidak tersedia)',
          userMessage:
              'Server aktif tapi database tidak tersedia. '
              'Coba lagi beberapa saat.',
          technicalDetail: 'GET $healthUrl → HTTP 503',
          suggestions: [
            'Periksa apakah MySQL/MariaDB berjalan',
            'Cek konfigurasi DB_HOST di .env backend',
          ],
        );
      }

      return null; // Status lain dianggap server berfungsi

    } on HandshakeException catch (e) {
      return _PartialResult(
        failure: ConnectionFailure.sslError,
        summary: 'SSL/TLS handshake gagal',
        userMessage:
            'Koneksi HTTPS ke server gagal karena masalah sertifikat. '
            'Hubungi administrator server.',
        technicalDetail: 'HandshakeException: ${e.message}',
        suggestions: [
          'Periksa sertifikat SSL server (expired / self-signed)',
          'Jika development: gunakan HTTP atau pasang sertifikat yang valid',
          'Pastikan jam/tanggal perangkat benar',
        ],
      );
    } on TimeoutException {
      return _PartialResult(
        failure: ConnectionFailure.serverTimeout,
        summary: 'Health check timeout setelah ${_httpTimeout.inSeconds}s',
        userMessage:
            'Server aktif tapi merespons sangat lambat. '
            'Koneksi mungkin tidak stabil.',
        technicalDetail: 'GET $healthUrl timeout > ${_httpTimeout.inMilliseconds}ms',
        suggestions: [
          'Coba beralih ke jaringan yang lebih stabil',
          'Periksa beban server (CPU/RAM)',
        ],
      );
    } catch (e) {
      // Jika TCP bisa tapi HTTP gagal — kemungkinan bukan HTTP server
      return _PartialResult(
        failure: ConnectionFailure.invalidResponse,
        summary: 'HTTP request gagal: $e',
        userMessage: 'Tidak dapat berkomunikasi dengan server API.',
        technicalDetail: e.toString(),
        suggestions: [
          'Pastikan backend PHP berjalan (php -S atau Nginx + PHP-FPM)',
          'Pastikan port server benar (8080 untuk Laragon dev)',
        ],
      );
    } finally {
      client.close();
    }
  }

  // ── Pesan helper ──────────────────────────────────────────────────────────

  static String _buildServerUnreachableMessage(String host, int port) {
    if (host == '10.0.2.2') {
      return 'Tidak dapat terhubung ke server pengembangan ($host:$port). '
          'Pastikan backend berjalan di mesin host dan emulator digunakan '
          '(bukan perangkat fisik).';
    }
    if (host == 'localhost' || host == '127.0.0.1') {
      return 'Tidak dapat terhubung ke localhost. '
          'Pada perangkat fisik, gunakan IP LAN mesin host '
          '(contoh: 192.168.1.x), bukan localhost.';
    }
    return 'Tidak dapat terhubung ke server ($host:$port). '
        'Pastikan server aktif dan perangkat berada di jaringan yang sama.';
  }

  static List<String> _buildUnreachableSuggestions({
    required String host,
    required int port,
    required bool isLocalhost,
    required bool isEmulatorAlias,
  }) {
    if (isEmulatorAlias) {
      return [
        'Pastikan menggunakan Android Emulator (AVD), bukan perangkat fisik',
        'Pastikan backend berjalan: php -S localhost:$port -t public',
        'Periksa apakah firewall memblokir port $port',
        'Coba: adb reverse tcp:$port tcp:$port (untuk perangkat fisik via USB)',
      ];
    }
    if (isLocalhost) {
      return [
        'Untuk perangkat fisik, ganti localhost dengan IP LAN mesin host',
        'Cari IP LAN: ipconfig (Windows) / ifconfig (Linux/Mac)',
        'Jalankan: flutter run --dart-define=API_BASE_URL=http://192.168.x.x:$port/api/v1',
        'Atau gunakan adb reverse: adb reverse tcp:$port tcp:$port',
      ];
    }
    return [
      'Pastikan server aktif di $host:$port',
      'Pastikan perangkat dan server di jaringan yang sama (Wi-Fi sama)',
      'Coba akses http://$host:$port/api/v1/health dari browser perangkat',
      'Periksa apakah firewall/antivirus memblokir port $port',
    ];
  }

  /// Log hasil diagnostik ke console (hanya di debug mode).
  static void logResult(DiagnosticResult result) {
    if (!kDebugMode) return;
    debugPrint(
      '[NetworkDiagnostic] ${result.failure.name.toUpperCase()} '
      '(${result.elapsed.inMilliseconds}ms): ${result.summary}',
    );
    if (result.technicalDetail != null) {
      debugPrint('[NetworkDiagnostic] Detail: ${result.technicalDetail}');
    }
    if (result.suggestions.isNotEmpty) {
      debugPrint('[NetworkDiagnostic] Saran:');
      for (final s in result.suggestions) {
        debugPrint('  • $s');
      }
    }
  }
}

/// Data class internal untuk hasil parsial dari pemeriksaan HTTP.
class _PartialResult {
  final ConnectionFailure failure;
  final String summary;
  final String userMessage;
  final String? technicalDetail;
  final List<String> suggestions;

  const _PartialResult({
    required this.failure,
    required this.summary,
    required this.userMessage,
    this.technicalDetail,
    required this.suggestions,
  });
}
