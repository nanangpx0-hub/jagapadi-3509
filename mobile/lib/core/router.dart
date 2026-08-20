import 'dart:convert';

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
import '../features/laporan/screens/laporan_terpadu_screen.dart';
import '../features/notifications/screens/notification_screen.dart';
import '../features/profile/screens/change_password_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import '../features/pupuk/screens/pupuk_list_screen.dart';
import '../features/pupuk/screens/pupuk_detail_screen.dart';
import '../features/pupuk/screens/pupuk_form_screen.dart';
import '../features/panen/screens/panen_list_screen.dart';
import '../features/panen/screens/panen_detail_screen.dart';
import '../features/panen/screens/panen_form_screen.dart';
import '../features/cuaca/screens/cuaca_list_screen.dart';
import '../features/cuaca/screens/cuaca_detail_screen.dart';
import '../features/cuaca/screens/cuaca_form_screen.dart';
import '../features/alat_sarana/screens/alat_sarana_list_screen.dart';
import '../features/alat_sarana/screens/alat_sarana_detail_screen.dart';
import '../features/alat_sarana/screens/alat_sarana_form_screen.dart';
import 'permissions.dart';
import 'secure_storage.dart';

class _AuthNotifier extends ChangeNotifier {
  void refresh() => notifyListeners();
}

class AppRouter {
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
  late final GoRouter router;
  final _AuthNotifier _authNotifier = _AuthNotifier();
  String? _tokenCache;
  String? _roleCache;

  AppRouter() {
    AppSecureStorage.getToken().then((token) {
      _tokenCache = token;
      _authNotifier.refresh();
    });
    // Role dibaca dari user tersimpan agar guard permission dapat menilai
    // route create/edit (deep link) tanpa menunggu UI.
    AppSecureStorage.getUser().then((userJson) {
      if (userJson != null) {
        try {
          final user = (jsonDecode(userJson) as Map<String, dynamic>);
          _roleCache = user['role'] as String?;
        } catch (_) {
          _roleCache = null;
        }
      }
      _authNotifier.refresh();
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
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return HamaDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return HamaFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/irigasi',
          builder: (_, state) => IrigasiListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const IrigasiFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return IrigasiDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return IrigasiFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/pupuk',
          builder: (_, state) => PupukListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const PupukFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return PupukDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return PupukFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/panen',
          builder: (_, state) => PanenListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const PanenFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return PanenDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return PanenFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/cuaca',
          builder: (_, state) => CuacaListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const CuacaFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return CuacaDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return CuacaFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/alat-sarana',
          builder: (_, state) => AlatSaranaListScreen(
            initialStatus: state.uri.queryParameters['status'],
          ),
          routes: [
            GoRoute(
              path: 'create',
              builder: (_, __) => const AlatSaranaFormScreen(),
            ),
            GoRoute(
              path: ':id',
              builder: (_, state) {
                final id = _parseId(state.pathParameters['id']);
                if (id == null) return const _InvalidRouteScreen();
                return AlatSaranaDetailScreen(id: id);
              },
              routes: [
                GoRoute(
                  path: 'edit',
                  builder: (_, state) {
                    final id = _parseId(state.pathParameters['id']);
                    if (id == null) return const _InvalidRouteScreen();
                    return AlatSaranaFormScreen(id: id);
                  },
                ),
              ],
            ),
          ],
        ),
        GoRoute(
          path: '/laporan',
          builder: (_, __) => const LaporanTerpaduScreen(),
        ),
        GoRoute(
          path: '/notifications',
          builder: (_, __) => const NotificationScreen(),
        ),
        GoRoute(
          path: '/profile',
          builder: (_, __) => const ProfileScreen(),
        ),
        GoRoute(
          path: '/profile/change-password',
          builder: (_, state) => ChangePasswordScreen(
            forceChangePassword: state.uri.queryParameters['force'] == '1',
          ),
        ),
      ],
    );
  }

  // Auth guard: arahkan pengguna tanpa token ke /login, dan pengguna yang
  // sudah login menjauh dari /login ke /home. Deep-link aman karena hanya
  // mengecek keberadaan token, bukan validitasnya (divalidasi oleh API 401).
  // Guard permission: route create/edit dibatasi role (lapisan UX; otorisasi
  // final tetap di backend).
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
    if (hasToken) {
      final role = _roleCache ?? '';
      if (location.endsWith('/create') &&
          !RolePermissions.can(role, ReportCapability.canCreateReport)) {
        return '/home';
      }
      if (location.contains('/edit') &&
          !RolePermissions.can(role, ReportCapability.canEditOwnReport)) {
        return '/home';
      }
    }
    return null;
  }

  void setToken(String? token) {
    _tokenCache = token;
    _authNotifier.refresh();
  }

  void setRole(String? role) {
    _roleCache = role;
    _authNotifier.refresh();
  }

  void redirectToLogin() {
    router.go('/login');
  }
}

/// Parse ID dari path parameter dengan aman — kembalikan null jika tidak valid.
/// Mencegah [FormatException] crash yang berasal dari deep link FCM atau URL
/// yang dimanipulasi.
int? _parseId(String? raw) {
  if (raw == null || raw.isEmpty) return null;
  return int.tryParse(raw);
}

/// Layar fallback untuk route dengan ID tidak valid.
class _InvalidRouteScreen extends StatelessWidget {
  const _InvalidRouteScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Halaman Tidak Valid')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.grey),
            const SizedBox(height: 16),
            const Text(
              'Halaman tidak ditemukan atau\nID laporan tidak valid.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Kembali'),
            ),
          ],
        ),
      ),
    );
  }
}
