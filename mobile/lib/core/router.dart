import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/screens/login_screen.dart';
import '../features/home/screens/home_screen.dart';
import '../features/hama/screens/hama_list_screen.dart';
import '../features/hama/screens/hama_detail_screen.dart';
import '../features/hama/screens/hama_form_screen.dart';
import '../features/irigasi/screens/irigasi_list_screen.dart';
import '../features/irigasi/screens/irigasi_detail_screen.dart';
import '../features/irigasi/screens/irigasi_form_screen.dart';
import '../features/notifications/screens/notification_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import 'secure_storage.dart';

class AppRouter {
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
  late final GoRouter router;
  final ChangeNotifier _authNotifier = ChangeNotifier();
  String? _tokenCache;

  AppRouter() {
    AppSecureStorage.getToken().then((token) {
      _tokenCache = token;
      _authNotifier.notifyListeners();
    });
    router = GoRouter(
      navigatorKey: navigatorKey,
      initialLocation: '/login',
      redirect: _guard,
      refreshListenable: _authNotifier,
      routes: [
        GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
        GoRoute(
          path: '/home',
          builder: (_, __) => const HomeScreen(),
        ),
        GoRoute(
          path: '/hama',
          builder: (_, state) => HamaListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const HamaFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) => HamaDetailScreen(
                id: int.parse(state.pathParameters['id']!),
              ),
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) => HamaFormScreen(
                    id: int.parse(state.pathParameters['id']!),
                  ),
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/irigasi',
          builder: (_, __) => const IrigasiListScreen(),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const IrigasiFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) => IrigasiDetailScreen(
                id: int.parse(state.pathParameters['id']!),
              ),
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) => IrigasiFormScreen(
                    id: int.parse(state.pathParameters['id']!),
                  ),
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/notifications',
          builder: (_, __) => const NotificationScreen(),
        ),
        GoRoute(
          path: '/profile',
          builder: (_, __) => const ProfileScreen(),
        ),
      ],
    );
  }

  // Auth guard: arahkan pengguna tanpa token ke /login, dan pengguna yang
  // sudah login menjauh dari /login ke /home. Deep-link aman karena hanya
  // mengecek keberadaan token, bukan validitasnya (divalidasi oleh API 401).
  String? _guard(BuildContext context, GoRouterState state) {
    final location = state.matchedLocation;
    final hasToken = _tokenCache != null;
    final onLogin = location == '/login';

    if (!hasToken && !onLogin) {
      return '/login';
    }
    if (hasToken && onLogin) {
      return '/home';
    }
    return null;
  }

  void setToken(String? token) {
    _tokenCache = token;
    _authNotifier.notifyListeners();
  }

  void redirectToLogin() {
    router.go('/login');
  }
}
