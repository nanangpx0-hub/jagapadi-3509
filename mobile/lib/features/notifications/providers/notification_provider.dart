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
/// Perbaikan BUG-M6:
/// - [_unreadCount] hanya diperbarui dari satu sumber: method [load()]
///   yang menghitung dari list lokal. Method [_loadUnreadCount()] tetap ada
///   untuk fetch ringan tanpa memuat seluruh list.
class NotificationProvider extends ChangeNotifier with WidgetsBindingObserver {
  final ApiClient api;

  List<NotificationItem> _list = [];
  bool _loading = false;
  String? _error;
  int _unreadCount = 0;
  Timer? _pollTimer;

  /// True jika polling sedang aktif (foreground).
  bool _pollingActive = false;

  NotificationProvider(this.api) {
    // Daftarkan observer lifecycle di konstruktor.
    WidgetsBinding.instance.addObserver(this);
  }

  // ── Getters ───────────────────────────────────────────────────────────────

  List<NotificationItem> get list        => _list;
  bool                   get loading     => _loading;
  String?                get error       => _error;
  int                    get unreadCount => _unreadCount;
  bool                   get hasUnread   => _unreadCount > 0;

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

  /// Muat semua notifikasi (untuk halaman notifikasi).
  Future<void> load() async {
    _loading = true;
    _error   = null;
    notifyListeners();

    final res = await api.get('/notifications', queryParams: {'limit': 50});
    if (res.success && res.data != null) {
      final raw = res.data!['data'] as List<dynamic>? ?? [];
      _list        = raw.map((e) => NotificationItem.fromJson({'data': e})).toList();
      // BUG-M6 fix: satu sumber kebenaran — hitung dari list lokal.
      _unreadCount = _list.where((n) => !n.isRead).length;
    } else {
      _error = res.message;
    }

    _loading = false;
    notifyListeners();
  }

  /// Tandai satu notifikasi sudah dibaca.
  Future<void> markRead(int id) async {
    // Optimistic update
    _list = _list.map((n) => n.id == id
        ? NotificationItem(
            id: n.id, title: n.title, body: n.body,
            isRead: true, createdAt: n.createdAt,
            entity: n.entity, laporanId: n.laporanId,
          )
        : n).toList();
    _unreadCount = _list.where((n) => !n.isRead).length;
    notifyListeners();

    await api.post('/notifications/$id/read');
  }

  /// Tandai semua notifikasi sudah dibaca.
  Future<void> markAllRead() async {
    // Optimistic update
    _list = _list.map((n) => NotificationItem(
      id: n.id, title: n.title, body: n.body,
      isRead: true, createdAt: n.createdAt,
      entity: n.entity, laporanId: n.laporanId,
    )).toList();
    _unreadCount = 0;
    notifyListeners();

    await api.post('/notifications/mark-all-read');
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
    final res = await api.get('/notifications/unread-count');
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
    _stopTimer();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }
}
