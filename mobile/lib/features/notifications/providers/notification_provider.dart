import 'dart:async';
import 'package:flutter/material.dart';
import '../../../core/api_client.dart';
import '../models/notification_item.dart';

class NotificationProvider extends ChangeNotifier {
  final ApiClient api;
  List<NotificationItem> _list = [];
  bool _loading = false;
  String? _error;
  int _unreadCount = 0;
  Timer? _pollTimer;

  NotificationProvider(this.api);

  List<NotificationItem> get list => _list;
  bool get loading => _loading;
  String? get error => _error;
  int get unreadCount => _unreadCount;
  bool get hasUnread => _unreadCount > 0;

  void startPolling() {
    _pollTimer?.cancel();
    _loadUnreadCount();
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) => _loadUnreadCount());
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> _loadUnreadCount() async {
    final res = await api.get('/notifications/unread-count');
    if (res.success && res.data != null) {
      _unreadCount = (res.data!['count'] as int? ?? 0);
      notifyListeners();
    }
  }

  Future<void> load() async {
    _loading = true; _error = null; notifyListeners();
    final res = await api.get('/notifications', queryParams: {'limit': 50});
    if (res.success && res.data != null) {
      final raw = res.data!['data'] as List<dynamic>? ?? [];
      _list = raw.map((e) => NotificationItem.fromJson({'data': e})).toList();
      _unreadCount = _list.where((n) => !n.isRead).length;
    } else { _error = res.message; }
    _loading = false; notifyListeners();
  }

  Future<void> markRead(int id) async {
    final res = await api.post('/notifications/$id/read');
    if (res.success) {
      await load();
      await _loadUnreadCount();
    }
  }

  Future<void> markAllRead() async {
    final res = await api.post('/notifications/read-all');
    if (res.success) {
      _list = _list.map((n) => NotificationItem(id: n.id, title: n.title, body: n.body, isRead: true, createdAt: n.createdAt, entity: n.entity, laporanId: n.laporanId)).toList();
      _unreadCount = 0;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}
