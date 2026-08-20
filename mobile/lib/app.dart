import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'core/api_client.dart';
import 'core/connectivity_service.dart';
import 'core/router.dart';
import 'core/sync_service.dart';
import 'core/theme.dart';
import 'features/auth/providers/auth_provider.dart';
import 'features/hama/providers/laporan_hama_provider.dart';
import 'features/home/providers/dashboard_provider.dart';
import 'features/irigasi/providers/laporan_irigasi_provider.dart';
import 'features/pupuk/providers/laporan_pupuk_provider.dart';
import 'features/panen/providers/laporan_panen_provider.dart';
import 'features/cuaca/providers/laporan_cuaca_provider.dart';
import 'features/alat_sarana/providers/laporan_alat_sarana_provider.dart';
import 'features/laporan/providers/laporan_terpadu_provider.dart';
import 'features/notifications/providers/notification_provider.dart';
import 'features/wilayah/providers/wilayah_provider.dart';

/// Root widget aplikasi JAGAPADI.
///
/// Pola yang diterapkan:
/// - [ApiClient] dibuat SEKALI sebagai field — bukan di [build] agar tidak
///   dibuat ulang setiap rebuild dan tidak terjadi memory leak Dio instance.
/// - Semua provider yang dibutuhkan screen manapun didaftarkan di sini
///   agar tidak terjadi [ProviderNotFoundException] saat runtime.
/// - [AuthProvider] menggunakan [ApiClient]-nya sendiri (via internal init)
///   sehingga bisa handle onUnauthorized callback ke [AppRouter].
class JagapadiApp extends StatefulWidget {
  final AppRouter appRouter;
  const JagapadiApp({super.key, required this.appRouter});

  @override
  State<JagapadiApp> createState() => _JagapadiAppState();
}

class _JagapadiAppState extends State<JagapadiApp> with WidgetsBindingObserver {
  /// Satu instance ApiClient yang dipakai semua provider kecuali AuthProvider.
  late final ApiClient _apiClient;
  late final ConnectivityService _connectivity;

  @override
  void initState() {
    super.initState();
    _apiClient = ApiClient();
    _connectivity = ConnectivityService();
    WidgetsBinding.instance.addObserver(this);

    // Auto-sync draf lokal saat koneksi kembali online.
    _connectivity.addListener(_onConnectivityChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_connectivity.isOnline) {
        SyncService.syncPendingDrafts(_apiClient);
      }
      // Daftarkan logout callback setelah semua provider tersedia di tree.
      _registerLogoutCallback();
    });
  }

  /// Daftarkan callback ke AuthProvider untuk membersihkan cache saat logout.
  /// Dilakukan setelah tree siap agar context.read() bisa digunakan.
  void _registerLogoutCallback() {
    final auth = context.read<AuthProvider>();
    auth.onLogoutCallback = () {
      context.read<WilayahProvider>().clearCache();
      context.read<LaporanHamaProvider>().clearOptCache();
      context.read<NotificationProvider>().stopPolling();
      // Dashboard: reset agar data user lama tidak tampil kepada user baru.
      context.read<DashboardProvider>().reset();
    };
  }

  @override
  void dispose() {
    _connectivity.removeListener(_onConnectivityChanged);
    _connectivity.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Dipanggil otomatis setiap kali status koneksi berubah.
  void _onConnectivityChanged() {
    if (_connectivity.isOnline) {
      // Jalankan sinkronisasi draf lokal tanpa menunggu hasilnya di UI.
      SyncService.syncPendingDrafts(_apiClient);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _connectivity.isOnline) {
      SyncService.syncPendingDrafts(_apiClient);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        // ── Auth — punya ApiClient sendiri karena butuh onUnauthorized callback ──
        ChangeNotifierProvider(
          create: (_) => AuthProvider(widget.appRouter),
        ),

        // ── Koneksi & Diagnostik ───────────────────────────────────────────
        ChangeNotifierProvider<ConnectivityService>.value(value: _connectivity),

        // ── Notifikasi (polling badge 60s) ────────────────────────────────
        ChangeNotifierProvider(
          create: (_) => NotificationProvider(_apiClient),
        ),

        // ── Dashboard statistik ───────────────────────────────────────────
        ChangeNotifierProvider(
          create: (_) => DashboardProvider(_apiClient),
        ),

        // ── Laporan terpadu (hama + irigasi digabung) ─────────────────────
        ChangeNotifierProvider(
          create: (_) => LaporanTerpaduProvider(_apiClient),
        ),

        // ── Laporan Hama — dibutuhkan oleh HamaListScreen, HamaDetailScreen,
        //    HamaFormScreen, dan LocalDraftsBanner ──────────────────────────
        ChangeNotifierProvider(
          create: (_) => LaporanHamaProvider(_apiClient),
        ),

        // ── Laporan Irigasi ───────────────────────────────────────────────
        ChangeNotifierProvider(
          create: (_) => LaporanIrigasiProvider(_apiClient),
        ),

        ChangeNotifierProvider(
          create: (_) => LaporanPupukProvider(_apiClient),
        ),
        ChangeNotifierProvider(
          create: (_) => LaporanPanenProvider(_apiClient),
        ),
        ChangeNotifierProvider(
          create: (_) => LaporanCuacaProvider(_apiClient),
        ),
        ChangeNotifierProvider(
          create: (_) => LaporanAlatSaranaProvider(_apiClient),
        ),

        // ── Wilayah cascading (Kabupaten → Kecamatan → Desa) ─────────────
        // Diperlukan oleh WilayahPicker di form hama dan irigasi.
        ChangeNotifierProvider(
          create: (_) => WilayahProvider(_apiClient),
        ),
      ],
      child: MaterialApp.router(
        title: 'JAGAPADI',
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        themeMode: ThemeMode.system,
        locale: const Locale('id', 'ID'),
        supportedLocales: const [Locale('id', 'ID'), Locale('en', 'US')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: widget.appRouter.router,
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
