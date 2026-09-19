import 'dart:async';
import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/notification_item.dart';

/// Provider notifikasi in-app.
///
/// Perbaikan R-02:
/// - Menambahkan [WidgetsBindingObserver] agar polling otomatis berhenti
///   saat aplikasi masuk background (AppLifecycleState.paused/hidden) dan
///   dilanjutkan saat kembali ke foreground (AppLifecycleState.resumed).
/// - Mencegah drain baterai akibat HTTP polling 60s yang terus berjalan
///   di background.
///
/// Badge memakai total unread dari metadata server, bukan jumlah pada halaman
/// lokal. Polling ringan mengambil total yang sama melalui unread-count.
class NotificationProvider extends ChangeNotifier with WidgetsBindingObserver {
  final ApiClient api;

  List<NotificationItem> _list = [];
  bool _loading = false;
  String? _error;
  int _unreadCount = 0;
  Timer? _pollTimer;
  bool _disposed = false;
  int _sessionGeneration = 0;

  /// True jika polling sedang aktif (foreground).
  bool _pollingActive = false;

  NotificationProvider(this.api) {
    // Daftarkan observer lifecycle di konstruktor.
    WidgetsBinding.instance.addObserver(this);
  }

  // ── Getters ───────────────────────────────────────────────────────────────

  List<NotificationItem> get list => _list;
  bool get loading => _loading;
  String? get error => _error;
  int get unreadCount => _unreadCount;
  bool get hasUnread => _unreadCount > 0;

  // ── Lifecycle observer ───────────────────────────────────────────────────

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        // App kembali ke foreground — lanjutkan polling jika sebelumnya aktif.
        if (_pollingActive) {
          _startTimer();
          _loadUnreadCount(); // langsung fetch satu kali setelah resume
        }
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
      case AppLifecycleState.inactive:
        // App masuk background — hentikan timer, hemat baterai.
        _stopTimer();
        break;
      case AppLifecycleState.detached:
        break;
    }
  }

  // ── Public API ────────────────────────────────────────────────────────────

  /// Mulai polling badge unread setiap 60 detik.
  /// Dipanggil dari [HomeScreen.initState()].
  void startPolling() {
    _pollingActive = true;
    _loadUnreadCount();
    _startTimer();
  }

  /// Hentikan polling sepenuhnya (misal saat logout).
  void stopPolling() {
    _pollingActive = false;
    _stopTimer();
  }

  /// Batalkan hasil request sesi lama tanpa menghapus data server.
  void reset() {
    _sessionGeneration++;
    stopPolling();
    _list = [];
    _unreadCount = 0;
    _loading = false;
    _error = null;
    notifyListeners();
  }

  /// Muat halaman pertama; badge menggunakan total unread dari server.
  Future<void> load() async {
    final generation = _sessionGeneration;
    _loading = true;
    _error = null;
    notifyListeners();

    final res = await api.get('/notifications', queryParams: {'limit': 50});
    if (_disposed || generation != _sessionGeneration) return;
    if (res.success && res.data != null) {
      final raw = res.data!['data'] as List<dynamic>? ?? [];
      _list = raw.map((e) => NotificationItem.fromJson({'data': e})).toList();
      final meta = res.data!['meta'];
      final unread = meta is Map ? meta['unread'] : null;
      _unreadCount =
          unread is num ? unread.toInt() : _list.where((n) => !n.isRead).length;
    } else {
      _error = res.message;
    }

    _loading = false;
    notifyListeners();
  }

  /// Ubah state hanya setelah server mengonfirmasi pembacaan.
  Future<void> markRead(int id) async {
    final generation = _sessionGeneration;
    final res = await api.post('/notifications/$id/read');
    if (_disposed || generation != _sessionGeneration) return;
    if (!res.success) {
      _error = res.message ?? 'Gagal menandai notifikasi dibaca.';
      notifyListeners();
      return;
    }
    _error = null;
    final wasUnread = _list.any((n) => n.id == id && !n.isRead);
    _list = _list
        .map((n) => n.id == id
            ? NotificationItem(
                id: n.id,
                title: n.title,
                body: n.body,
                isRead: true,
                createdAt: n.createdAt,
                entity: n.entity,
                laporanId: n.laporanId,
              )
            : n)
        .toList();
    if (wasUnread && _unreadCount > 0) _unreadCount--;
    notifyListeners();
  }

  /// Tandai semua notifikasi sudah dibaca.
  Future<void> markAllRead() async {
    final generation = _sessionGeneration;
    final res = await api.post('/notifications/read-all');
    if (_disposed || generation != _sessionGeneration) return;
    if (!res.success) {
      _error = res.message ?? 'Gagal menandai semua notifikasi dibaca.';
      notifyListeners();
      return;
    }
    _error = null;
    _list = _list
        .map((n) => NotificationItem(
              id: n.id,
              title: n.title,
              body: n.body,
              isRead: true,
              createdAt: n.createdAt,
              entity: n.entity,
              laporanId: n.laporanId,
            ))
        .toList();
    _unreadCount = 0;
    notifyListeners();
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  void _startTimer() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(
      const Duration(seconds: 60),
      (_) => _loadUnreadCount(),
    );
  }

  void _stopTimer() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  /// Fetch ringan: hanya jumlah unread untuk badge AppBar.
  /// Tidak memuat seluruh list — hemat bandwidth.
  Future<void> _loadUnreadCount() async {
    final generation = _sessionGeneration;
    final res = await api.get('/notifications/unread-count');
    if (_disposed || generation != _sessionGeneration || !_pollingActive) {
      return;
    }
    if (res.success && res.data != null) {
      final count = res.data!['count'] as int? ?? 0;
      if (_unreadCount != count) {
        _unreadCount = count;
        notifyListeners();
      }
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _stopTimer();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}
