import 'dart:async';
import 'package:flutter_test/flutter_test.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/features/notifications/models/notification_item.dart';
import 'package:jagapadi_mobile/features/notifications/providers/notification_provider.dart';

class _Api extends ApiClient {
  String? postedPath;
  bool succeeds = true;
  Completer<ApiResponse<Map<String, dynamic>>>? pendingPost;
  Completer<ApiResponse<Map<String, dynamic>>>? pending;

  @override
  Future<ApiResponse<Map<String, dynamic>>> get(String path,
      {Map<String, dynamic>? queryParams}) async {
    if (pending != null) return pending!.future;
    return ApiResponse.fromJson({
      'success': true,
      'data': [
        {'id': 1, 'title': 'Laporan', 'read_at': null},
        {'id': 2, 'title': 'Selesai', 'read_at': '2026-09-17 08:00:00'},
      ],
      'meta': {'unread': 75},
    }, 200);
  }

  @override
  Future<ApiResponse<Map<String, dynamic>>> post(String path,
      {Map<String, dynamic>? data,
      Map<String, String>? headers,
      int maxRetries = 2}) async {
    postedPath = path;
    if (pendingPost != null) return pendingPost!.future;
    return ApiResponse(
        success: succeeds,
        statusCode: succeeds ? 200 : 503,
        message: succeeds ? 'OK' : 'Server tidak tersedia');
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  late _Api api;
  late NotificationProvider provider;
  setUp(() {
    api = _Api();
    provider = NotificationProvider(api);
  });
  tearDown(() => provider.dispose());

  test('read_at canonical and total unread metadata survive pagination',
      () async {
    await provider.load();
    expect(provider.list[1].isRead, isTrue);
    expect(provider.unreadCount, 75);
  });

  test('read-all uses canonical endpoint after server confirmation', () async {
    await provider.load();
    await provider.markAllRead();
    expect(api.postedPath, '/notifications/read-all');
    expect(provider.unreadCount, 0);
    expect(provider.list.every((n) => n.isRead), isTrue);
  });

  for (final succeeds in [true, false]) {
    test('pending read-all preserves badge until confirmation: $succeeds',
        () async {
      await provider.load();
      api.pendingPost = Completer();
      final reading = provider.markAllRead();
      await Future<void>.delayed(Duration.zero);
      expect(api.postedPath, '/notifications/read-all');
      expect(provider.unreadCount, 75);
      expect(provider.hasUnread, isTrue);
      expect(provider.list.first.isRead, isFalse);

      api.pendingPost!.complete(ApiResponse(
        success: succeeds,
        statusCode: succeeds ? 200 : 503,
        message: succeeds ? 'OK' : 'Server tidak tersedia',
      ));
      await reading;
      expect(provider.unreadCount, succeeds ? 0 : 75);
      expect(provider.list.first.isRead, succeeds);
      expect(provider.error, succeeds ? isNull : 'Server tidak tersedia');
    });
  }

  test('failed read-all preserves unread data and exposes error', () async {
    await provider.load();
    api.succeeds = false;
    await provider.markAllRead();
    expect(provider.list.first.isRead, isFalse);
    expect(provider.unreadCount, 75);
    expect(provider.error, 'Server tidak tersedia');
  });

  test('single read is confirmed and repeated read does not decrement twice',
      () async {
    await provider.load();
    api.succeeds = false;
    await provider.markRead(1);
    expect(provider.list.first.isRead, isFalse);
    api.succeeds = true;
    await provider.markRead(1);
    await provider.markRead(1);
    expect(provider.unreadCount, 74);
    expect(provider.error, isNull);
  });

  test('notification routes cover six domains and reject unknown entities', () {
    for (final entry in {
      'hama': 'hama',
      'irigasi': 'irigasi',
      'pupuk': 'pupuk',
      'panen': 'panen',
      'cuaca': 'cuaca',
      'alat_sarana': 'alat-sarana',
    }.entries) {
      final item = NotificationItem(
          id: 1, title: '', body: '', entity: entry.key, laporanId: 9);
      expect(item.reportRoute, '/${entry.value}/9');
    }
    expect(
        NotificationItem(
                id: 1, title: '', body: '', entity: 'unknown', laporanId: 9)
            .reportRoute,
        isNull);
  });

  test('reset clears session data and ignores a previous pending response',
      () async {
    await provider.load();
    api.pending = Completer();
    final loading = provider.load();
    provider.reset();
    api.pending!
        .complete(const ApiResponse(success: true, statusCode: 200, data: {
      'data': [
        {'id': 99, 'title': 'Old session'}
      ]
    }));
    await loading;
    expect(provider.list, isEmpty);
    expect(provider.unreadCount, 0);
    expect(provider.loading, isFalse);
    expect(provider.error, isNull);
  });
}
