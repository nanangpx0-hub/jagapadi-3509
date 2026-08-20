import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'network_diagnostic.dart';

/// Service yang memantau status koneksi jaringan dan konektivitas ke server.
///
/// Perbedaan dari versi sebelumnya:
/// - [isOnline] = ada/tidaknya koneksi jaringan (Wi-Fi / data seluler)
/// - [isServerReachable] = apakah server JAGAPADI benar-benar bisa dijangkau
/// - [lastDiagnostic] = hasil diagnosis terakhir untuk ditampilkan ke user
/// - [runDiagnostic()] = jalankan diagnosis lengkap secara on-demand
class ConnectivityService extends ChangeNotifier {
  final Connectivity _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _subscription;

  bool _isOnline = true;
  bool _isServerReachable = false;
  bool _isDiagnosing = false;
  DiagnosticResult? _lastDiagnostic;

  ConnectivityService() {
    _initConnectivity();
    _subscription = _connectivity.onConnectivityChanged.listen(
      _onConnectivityChanged,
    );
  }

  // ── Getters ───────────────────────────────────────────────────────────────

  /// True jika perangkat memiliki koneksi jaringan (Wi-Fi/seluler).
  bool get isOnline => _isOnline;

  /// True jika server JAGAPADI berhasil dijangkau pada pemeriksaan terakhir.
  bool get isServerReachable => _isServerReachable;

  /// True jika sedang menjalankan diagnosis.
  bool get isDiagnosing => _isDiagnosing;

  /// Hasil diagnosis terakhir; null jika belum pernah dijalankan.
  DiagnosticResult? get lastDiagnostic => _lastDiagnostic;

  /// Pesan error yang dapat langsung ditampilkan ke user.
  /// Null jika koneksi normal.
  String? get connectionErrorMessage {
    if (_isDiagnosing) return null;
    if (_lastDiagnostic == null) return null;
    if (_lastDiagnostic!.isHealthy) return null;
    return _lastDiagnostic!.userMessage;
  }

  // ── Public API ────────────────────────────────────────────────────────────

  /// Jalankan diagnosis koneksi lengkap secara on-demand.
  /// Notifikasi listener setelah selesai.
  Future<DiagnosticResult> runDiagnostic() async {
    if (_isDiagnosing) {
      // Kembalikan hasil terakhir jika sedang diagnosis
      return _lastDiagnostic ??
          const DiagnosticResult(
            failure: ConnectionFailure.none,
            summary: 'Sedang memeriksa...',
            userMessage: 'Sedang memeriksa koneksi...',
            suggestions: [],
            elapsed: Duration.zero,
          );
    }

    _isDiagnosing = true;
    notifyListeners();

    try {
      final result = await NetworkDiagnosticService.diagnose();
      _lastDiagnostic = result;
      _isServerReachable = result.isHealthy;
      NetworkDiagnosticService.logResult(result);
      return result;
    } finally {
      _isDiagnosing = false;
      notifyListeners();
    }
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  Future<void> _initConnectivity() async {
    try {
      final results = await _connectivity.checkConnectivity();
      await _updateConnectionStatus(results);
    } catch (e) {
      debugPrint('[ConnectivityService] Init error: $e');
      _isOnline = true;
      notifyListeners();
    }
  }

  Future<void> _onConnectivityChanged(List<ConnectivityResult> results) async {
    await _updateConnectionStatus(results);
  }

  Future<void> _updateConnectionStatus(List<ConnectivityResult> results) async {
    final hasConnection = results.any((r) => r != ConnectivityResult.none);
    final changed = _isOnline != hasConnection;
    _isOnline = hasConnection;

    if (changed) {
      notifyListeners();
      // Jalankan diagnosis otomatis saat status berubah ke online
      if (hasConnection) {
        await runDiagnostic();
      } else {
        _isServerReachable = false;
        _lastDiagnostic = const DiagnosticResult(
          failure: ConnectionFailure.noNetwork,
          summary: 'Tidak ada koneksi jaringan',
          userMessage:
              'Perangkat Anda tidak terhubung ke internet.',
          suggestions: [
            'Aktifkan Wi-Fi atau data seluler',
          ],
          elapsed: Duration.zero,
        );
        notifyListeners();
      }
    }
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }
}
