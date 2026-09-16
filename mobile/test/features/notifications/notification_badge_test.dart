import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:jagapadi_mobile/core/api_client.dart';
import 'package:jagapadi_mobile/core/connectivity_service.dart';
import 'package:jagapadi_mobile/core/network_diagnostic.dart';
import 'package:jagapadi_mobile/features/auth/models/user.dart';
import 'package:jagapadi_mobile/features/auth/providers/auth_provider.dart';
import 'package:jagapadi_mobile/features/home/providers/dashboard_provider.dart';
import 'package:jagapadi_mobile/features/home/screens/home_screen.dart';
import 'package:jagapadi_mobile/features/notifications/providers/notification_provider.dart';
import 'package:jagapadi_mobile/features/notifications/screens/notification_screen.dart';
import 'package:provider/provider.dart';

class _Connectivity extends ChangeNotifier implements ConnectivityService {
  @override
  bool get isOnline => true;

  @override
  DiagnosticResult? get lastDiagnostic => null;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _Auth extends ChangeNotifier implements AuthProvider {
  @override
  User? get user => null;

  @override
  bool get offlineMode => false;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _DelayedApi extends ApiClient {
  final response = Completer<ApiResponse<Map<String, dynamic>>>();
  String? postedPath;

  @override
  Future<ApiResponse<Map<String, dynamic>>> get(String path,
      {Map<String, dynamic>? queryParams}) async {
    if (path == '/notifications') {
      return ApiResponse.fromJson({
        'success': true,
        'data': [
          {'id': 1, 'title': 'Laporan diperiksa', 'read_at': null},
        ],
        'meta': {'unread': 1},
      }, 200);
    }
    return ApiResponse(
      success: true,
      statusCode: 200,
      data: path == '/notifications/unread-count' ? {'count': 1} : {},
    );
  }

  @override
  Future<ApiResponse<Map<String, dynamic>>> post(String path,
      {Map<String, dynamic>? data,
      Map<String, String>? headers,
      int maxRetries = 2}) {
    postedPath = path;
    return response.future;
  }
}

void main() {
  for (final succeeds in [true, false]) {
    testWidgets(
        'home badge waits for read-all confirmation (success=$succeeds)',
        (tester) async {
      final previousFetching = GoogleFonts.config.allowRuntimeFetching;
      GoogleFonts.config.allowRuntimeFetching = false;
      final api = _DelayedApi();
      final notifications = NotificationProvider(api);
      final dashboard = DashboardProvider(api);
      final auth = _Auth();
      final router = GoRouter(initialLocation: '/home', routes: [
        GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
        GoRoute(
            path: '/notifications',
            builder: (_, __) => const NotificationScreen()),
      ]);
      addTearDown(() {
        router.dispose();
        notifications.dispose();
        dashboard.dispose();
        auth.dispose();
        GoogleFonts.config.allowRuntimeFetching = previousFetching;
      });
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider<ConnectivityService>(
              create: (_) => _Connectivity()),
          ChangeNotifierProvider<AuthProvider>.value(value: auth),
          ChangeNotifierProvider<NotificationProvider>.value(
              value: notifications),
          ChangeNotifierProvider<DashboardProvider>.value(value: dashboard),
        ],
        child: MaterialApp.router(routerConfig: router),
      ));
      await tester.pumpAndSettle();

      final badge = find.ancestor(
        of: find.byIcon(Icons.notifications_none, skipOffstage: false),
        matching: find.byType(Badge, skipOffstage: false),
      );
      expect(tester.widget<Badge>(badge).isLabelVisible, isTrue);
      await tester.tap(find.byTooltip('Notifikasi (1 belum dibaca)'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Baca Semua'));
      await tester.pump(const Duration(milliseconds: 500));

      // The actual home badge must remain unread while the server is pending.
      expect(notifications.unreadCount, 1);
      expect(tester.widget<Badge>(badge).isLabelVisible, isTrue);
      expect(find.text('Baca Semua'), findsOneWidget);
      expect(api.postedPath, '/notifications/read-all');

      api.response.complete(ApiResponse(
        success: succeeds,
        statusCode: succeeds ? 200 : 503,
        message: succeeds ? 'OK' : 'Server tidak tersedia',
      ));
      await tester.pumpAndSettle();
      router.pop();
      await tester.pumpAndSettle();
      expect(notifications.unreadCount, succeeds ? 0 : 1);
      expect(tester.widget<Badge>(badge).isLabelVisible, !succeeds);
      expect(find.byTooltip('Notifikasi (${succeeds ? 0 : 1} belum dibaca)'),
          findsOneWidget);
      expect(tester.takeException(), isNull);
      notifications.stopPolling();
      await tester.pumpWidget(const SizedBox.shrink());
    });
  }
}
