import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../core/permissions.dart';
import '../../../core/sync_service.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/connection_error_banner.dart';
import '../../auth/providers/auth_provider.dart';
import '../../notifications/providers/notification_provider.dart';
import '../providers/dashboard_provider.dart';

/// Layar beranda JAGAPADI — menampilkan statistik ringkas, tombol sinkron,
/// menu layanan, dan bottom navigasi.
///
/// Perbaikan UI/UX vNext:
/// - BottomNav route-aware (indeks terpilih mengikuti lokasi saat ini)
/// - Responsif: tinggi hero dan card ringkas menyesuaikan lebar layar
/// - Semantics: ikon notifikasi benar, semua tombol punya label aksesibilitas
/// - Tap target: item menu dibungkus Material (ripple) dengan area ≥ 48x48
/// - Tidak ada lagi duplikasi menu Notifikasi (bug screenshot lampiran)
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationProvider>().startPolling();
      // Admin melihat Antrian Verifikasi, bukan ringkasan statistik petugas.
      final isVerifier = context
              .read<AuthProvider>()
              .user
              ?.can(ReportCapability.canVerifyReport) ??
          false;
      if (!isVerifier) {
        context.read<DashboardProvider>().loadStats();
      }
    });
  }

  Future<void> _refresh() async {
    final notifications = context.read<NotificationProvider>();
    final dashboard = context.read<DashboardProvider>();
    final isVerifier = context
            .read<AuthProvider>()
            .user
            ?.can(ReportCapability.canVerifyReport) ??
        false;
    await notifications.load();
    if (!isVerifier) {
      await dashboard.loadStats();
    }
  }

  Future<void> _syncNow() async {
    if (_syncing) return;
    setState(() => _syncing = true);
    final dashboard = context.read<DashboardProvider>();
    final result = await SyncService.syncPendingDrafts(dashboard.api);
    if (!mounted) return;
    await dashboard.loadStats();
    if (!mounted) return;
    setState(() => _syncing = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        key: const Key('snackbar_sync_result'),
        content: Text(
          result.failed > 0
              ? '${result.synced} tersinkron, ${result.failed} gagal. Coba lagi.'
              : '${result.synced} laporan berhasil disinkronkan.',
          semanticsLabel: result.failed > 0
              ? '${result.synced} laporan tersinkron, ${result.failed} laporan gagal disinkronkan. Silakan coba lagi.'
              : '${result.synced} laporan berhasil disinkronkan.',
        ),
      ),
    );
  }

  /// Ambil indeks bottom nav terpilih berdasarkan GoRouter lokasi saat ini.
  /// Indeks "Sinkron" hanya ada bila role dapat membuat laporan (draf).
  int _selectedNavIndex(BuildContext context) {
    final canSync = context
            .read<AuthProvider>()
            .user
            ?.can(ReportCapability.canCreateReport) ??
        false;
    final profileIndex = canSync ? 3 : 2;
    final location = GoRouterState.of(context).uri.toString();
    if (location.startsWith('/laporan') ||
        location.startsWith('/hama') ||
        location.startsWith('/irigasi') ||
        location.startsWith('/pupuk') ||
        location.startsWith('/panen') ||
        location.startsWith('/cuaca') ||
        location.startsWith('/alat-sarana')) {
      return 1;
    }
    if (location.startsWith('/profile')) return profileIndex;
    return 0;
  }

  /// Handler untuk tap bottom navigation bar — menggunakan [context.go]
  /// (bukan push) agar stack tidak menumpuk. Indeks sinkron dinamis.
  void _onNavTap(int index, BuildContext ctx) {
    final canSync =
        ctx.read<AuthProvider>().user?.can(ReportCapability.canCreateReport) ??
            false;
    switch (index) {
      case 0:
        ctx.go('/home');
        break;
      case 1:
        ctx.go('/laporan');
        break;
      case 2:
        if (canSync) {
          _syncNow();
        } else {
          ctx.go('/profile');
        }
        break;
      case 3:
        if (canSync) {
          ctx.go('/profile');
        }
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final notif = context.watch<NotificationProvider>();
    final dashboard = context.watch<DashboardProvider>();
    final hama = dashboard.hamaStats;
    final irigasi = dashboard.irigasiStats;
    final selectedNav = _selectedNavIndex(context);
    final canSync = auth.user?.can(ReportCapability.canCreateReport) ?? false;
    final canVerify = auth.user?.can(ReportCapability.canVerifyReport) ?? false;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _refresh,
        edgeOffset: MediaQuery.paddingOf(context).top,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: LayoutBuilder(
                builder: (context, constraints) => _HeroHeader(
                  name: auth.user?.namaLengkap ?? 'Pengguna',
                  role: _roleDisplayName(auth.user?.role),
                  offline: auth.offlineMode,
                  unreadCount: notif.unreadCount,
                  onNotificationTap: () => context.push('/notifications'),
                  screenWidth: constraints.maxWidth,
                ),
              ),
            ),
            SliverPadding(
              padding: EdgeInsets.fromLTRB(
                AppSpacing.md,
                0,
                AppSpacing.md,
                AppSpacing.xxl + MediaQuery.paddingOf(context).bottom,
              ),
              sliver: SliverToBoxAdapter(
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 1120),
                    child: LayoutBuilder(
                      builder: (context, constraints) => Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          TweenAnimationBuilder<double>(
                            tween: Tween(begin: 0.0, end: 1.0),
                            duration: const Duration(milliseconds: 600),
                            curve: Curves.easeOutCubic,
                            builder: (context, value, child) =>
                                Transform.translate(
                              offset: Offset(0, -44 + (20 * (1 - value))),
                              child: Opacity(
                                opacity: value,
                                child: child,
                              ),
                            ),
                            child: _FloatingSummaryCard(
                              active: (hama?.totalAktif ?? 0) +
                                  (irigasi?.totalAktif ?? 0),
                              drafts: (hama?.totalDraf ?? 0) +
                                  (irigasi?.totalDraf ?? 0),
                              rejected: (hama?.totalDitolak ?? 0) +
                                  (irigasi?.totalDitolak ?? 0),
                              loading: dashboard.loading,
                              state: dashboard.state,
                              error: dashboard.error,
                              lastUpdatedAt: dashboard.lastUpdatedAt,
                              onRetry: () =>
                                  context.read<DashboardProvider>().loadStats(),
                              screenWidth: constraints.maxWidth,
                            ),
                          ),
                          Transform.translate(
                            offset: Offset(0, -24),
                            child: _SectionHeader(
                              syncing: _syncing,
                              onSync: _syncNow,
                            ),
                          ),
                          const ConnectionErrorBanner(),
                          if (canVerify) ...[
                            const SizedBox(height: AppSpacing.md),
                            _FeatureCard(
                              icon: Icons.fact_check_outlined,
                              title: 'Antrian Verifikasi',
                              subtitle:
                                  '${notif.unreadCount} laporan menunggu pemeriksaan',
                              color: Theme.of(context).colorScheme.tertiary,
                              onTap: () =>
                                  context.push('/hama?status=Submitted'),
                              semanticsLabel:
                                  'Antrian Verifikasi, ${notif.unreadCount} laporan menunggu pemeriksaan',
                            ),
                          ],
                          const SizedBox(height: AppSpacing.lg),
                          Text(
                            'Layanan JAGAPADI',
                            style: Theme.of(context).textTheme.titleLarge,
                            semanticsLabel:
                                'Layanan JAGAPADI. Pilih jenis laporan atau layanan yang Anda perlukan.',
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            'Pilih jenis laporan atau layanan yang Anda perlukan.',
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .onSurfaceVariant,
                                ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          _MenuGrid(
                            width: constraints.maxWidth,
                            role: auth.user?.role,
                            unreadCount: notif.unreadCount,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        key: const Key('bottom_navigation'),
        selectedIndex: selectedNav,
        onDestinationSelected: (index) => _onNavTap(index, context),
        destinations: [
          NavigationDestination(
            icon: Semantics(
              label: 'Beranda, menu tidak terpilih',
              child: const Icon(Icons.home_outlined),
            ),
            selectedIcon: Semantics(
              label: 'Beranda, menu terpilih',
              child: const Icon(Icons.home),
            ),
            label: 'Beranda',
          ),
          NavigationDestination(
            icon: Semantics(
              label: 'Laporan, menu tidak terpilih',
              child: const Icon(Icons.assignment_outlined),
            ),
            selectedIcon: Semantics(
              label: 'Laporan, menu terpilih',
              child: const Icon(Icons.assignment),
            ),
            label: 'Laporan',
          ),
          if (canSync)
            NavigationDestination(
              icon: Semantics(
                label: 'Sinkronisasi draf laporan',
                child: Badge(
                  isLabelVisible: _syncing,
                  child: const Icon(Icons.cloud_sync_outlined),
                ),
              ),
              selectedIcon: Semantics(
                label: 'Sinkronisasi sedang berlangsung',
                child: _syncing
                    ? const SizedBox.square(
                        dimension: 26,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.cloud_sync),
              ),
              label: 'Sinkron',
            ),
          NavigationDestination(
            icon: Semantics(
              label: 'Profil, menu tidak terpilih',
              child: const Icon(Icons.person_outline),
            ),
            selectedIcon: Semantics(
              label: 'Profil, menu terpilih',
              child: const Icon(Icons.person),
            ),
            label: 'Profil',
          ),
        ],
      ),
    );
  }

  /// Label role yang ramah pengguna.
  String _roleDisplayName(String? role) {
    return switch (role) {
      'admin' => 'Admin Verifikator',
      'petugas' => 'Petugas Lapangan',
      'operator' => 'Operator',
      'statistisi' => 'Statistisi',
      'viewer' => 'Viewer',
      _ => 'Petugas Lapangan',
    };
  }
}

class _HeroHeader extends StatelessWidget {
  final String name;
  final String role;
  final bool offline;
  final int unreadCount;
  final VoidCallback onNotificationTap;
  final double screenWidth;

  const _HeroHeader({
    required this.name,
    required this.role,
    required this.offline,
    required this.unreadCount,
    required this.onNotificationTap,
    required this.screenWidth,
  });

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final topPadding = MediaQuery.paddingOf(context).top;
    final isTablet = screenWidth >= AppBreakpoints.tablet;
    final heroHeight = isTablet ? 260.0 : 220.0;

    return Semantics(
      container: true,
      label:
          'Header beranda. Halo $name. $role. ${offline ? "Mode offline aktif." : ""} '
          '${unreadCount > 0 ? "$unreadCount notifikasi belum dibaca." : "Tidak ada notifikasi baru."}',
      child: Stack(
        children: [
          Container(
            width: double.infinity,
            constraints: BoxConstraints(minHeight: heroHeight),
            padding: EdgeInsets.fromLTRB(
              AppSpacing.lg,
              topPadding + AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.xxl + 12,
            ),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  colors.primary,
                  Color.lerp(colors.primary, colors.secondary, .72)!,
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        'Halo, ${name.toUpperCase()}!',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style:
                            Theme.of(context).textTheme.headlineSmall?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: isTablet ? 28 : null,
                                ),
                      ),
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        offline
                            ? '$role • Mode offline'
                            : 'Selamat datang di JAGAPADI • $role',
                        maxLines: 2,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                              color: Colors.white.withValues(alpha: .9),
                              height: 1.3,
                              fontSize: isTablet ? 16 : null,
                            ),
                      ),
                      if (offline) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.sm,
                            vertical: AppSpacing.xxs,
                          ),
                          decoration: BoxDecoration(
                            color:
                                AppTheme.warningContainer.withValues(alpha: .9),
                            borderRadius: BorderRadius.circular(AppRadius.sm),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.wifi_off,
                                size: 14,
                                color: AppTheme.onWarningContainer,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                'Mode Offline',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: AppTheme.onWarningContainer,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Semantics(
                  button: true,
                  label:
                      'Buka notifikasi. ${unreadCount > 0 ? "$unreadCount pesan belum dibaca." : "Semua notifikasi sudah dibaca."}',
                  child: Badge(
                    isLabelVisible: unreadCount > 0,
                    label: Text(unreadCount > 99 ? '99+' : '$unreadCount'),
                    child: IconButton.filledTonal(
                      onPressed: onNotificationTap,
                      style: IconButton.styleFrom(
                        minimumSize: const Size.square(52),
                        backgroundColor: Colors.white.withValues(alpha: .18),
                        foregroundColor: Colors.white,
                      ),
                      tooltip: 'Notifikasi ($unreadCount belum dibaca)',
                      icon: const Icon(Icons.notifications_none, size: 28),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: ClipPath(
              clipper: const _WaveClipper(),
              child: Container(
                height: 24,
                color: colors.surface,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WaveClipper extends CustomClipper<Path> {
  const _WaveClipper();

  @override
  Path getClip(Size size) {
    final path = Path();
    path.moveTo(0, 0);
    path.quadraticBezierTo(
      size.width * 0.25,
      size.height * 0.8,
      size.width * 0.5,
      size.height * 0.5,
    );
    path.quadraticBezierTo(
      size.width * 0.75,
      size.height * 0.2,
      size.width,
      size.height * 0.6,
    );
    path.lineTo(size.width, size.height);
    path.lineTo(0, size.height);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

class _FloatingSummaryCard extends StatelessWidget {
  final int active;
  final int drafts;
  final int rejected;
  final bool loading;
  final DashboardViewState state;
  final String? error;
  final DateTime? lastUpdatedAt;
  final VoidCallback onRetry;
  final double screenWidth;

  const _FloatingSummaryCard({
    required this.active,
    required this.drafts,
    required this.rejected,
    required this.loading,
    required this.state,
    required this.error,
    required this.lastUpdatedAt,
    required this.onRetry,
    required this.screenWidth,
  });

  String? get _stateLabel {
    return switch (state) {
      DashboardViewState.offline => 'Data offline — menampilkan data terakhir',
      DashboardViewState.stale =>
        'Server bermasalah — menampilkan data terakhir',
      DashboardViewState.error => error ?? 'Gagal memuat data statistik',
      _ => null,
    };
  }

  @override
  Widget build(BuildContext context) {
    final isTablet = screenWidth >= AppBreakpoints.tablet;
    final minHeight = isTablet ? 120.0 : 104.0;

    // Error tanpa cache: JANGAN tampilkan angka nol — tampilkan pesan + retry.
    final showErrorState = (state == DashboardViewState.error);
    final showStaleHint = state == DashboardViewState.offline ||
        state == DashboardViewState.stale;

    return Semantics(
      container: true,
      label: showErrorState
          ? 'Statistik gagal dimuat. Tekan tombol coba lagi.'
          : 'Ringkasan laporan. $active laporan aktif. $drafts laporan draf. $rejected laporan ditolak.',
      child: Card(
        elevation: 6,
        shadowColor: Colors.black26,
        surfaceTintColor: Theme.of(context).colorScheme.surface,
        child: Container(
          constraints: BoxConstraints(minHeight: minHeight),
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : showErrorState
                  ? Padding(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.error_outline,
                            size: 28,
                            color: AppTheme.errorColor,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            'Statistik tidak dapat dimuat',
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                          const SizedBox(height: AppSpacing.xxs),
                          Text(
                            error ?? 'Terjadi kesalahan saat memuat data.',
                            textAlign: TextAlign.center,
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurfaceVariant,
                                    ),
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          Semantics(
                            button: true,
                            label: 'Coba lagi memuat statistik',
                            child: OutlinedButton.icon(
                              onPressed: onRetry,
                              icon: const Icon(Icons.refresh, size: 18),
                              label: const Text('Coba Lagi'),
                            ),
                          ),
                        ],
                      ),
                    )
                  : Column(
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: _SummaryItem(
                                icon: Icons.assignment_turned_in_outlined,
                                label: 'Aktif',
                                value: active,
                                color: AppTheme.successColor,
                                semanticsHint:
                                    'Jumlah laporan yang sedang aktif',
                                onTap: () =>
                                    context.push('/hama?status=Diverifikasi'),
                              ),
                            ),
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                  vertical: AppSpacing.sm),
                              child: VerticalDivider(
                                indent: 8,
                                endIndent: 8,
                                width: 1,
                                thickness: 1,
                                color: Theme.of(context)
                                    .colorScheme
                                    .outlineVariant,
                              ),
                            ),
                            Expanded(
                              child: _SummaryItem(
                                icon: Icons.schedule_outlined,
                                label: 'Draf',
                                value: drafts,
                                color: const Color(0xFF616161),
                                semanticsHint:
                                    'Jumlah laporan dalam status draf',
                                onTap: () => context.push('/hama?status=Draf'),
                              ),
                            ),
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                  vertical: AppSpacing.sm),
                              child: VerticalDivider(
                                indent: 8,
                                endIndent: 8,
                                width: 1,
                                thickness: 1,
                                color: Theme.of(context)
                                    .colorScheme
                                    .outlineVariant,
                              ),
                            ),
                            Expanded(
                              child: _SummaryItem(
                                icon: Icons.report_problem_outlined,
                                label: 'Ditolak',
                                value: rejected,
                                color: AppTheme.errorColor,
                                semanticsHint:
                                    'Jumlah laporan yang ditolak admin',
                                onTap: () =>
                                    context.push('/hama?status=Ditolak'),
                              ),
                            ),
                          ],
                        ),
                        if (showStaleHint) ...[
                          Padding(
                            padding: const EdgeInsets.symmetric(
                                horizontal: AppSpacing.md),
                            child: Row(
                              children: [
                                Icon(
                                  state == DashboardViewState.offline
                                      ? Icons.wifi_off
                                      : Icons.warning_amber_rounded,
                                  size: 16,
                                  color: AppTheme.warningColor,
                                ),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    _stateLabel!,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall
                                        ?.copyWith(
                                          color: AppTheme.warningColor,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ),
                                TextButton(
                                  onPressed: onRetry,
                                  child: const Text('Coba Lagi'),
                                ),
                              ],
                            ),
                          ),
                          if (lastUpdatedAt != null)
                            Padding(
                              padding:
                                  const EdgeInsets.only(bottom: AppSpacing.xs),
                              child: Text(
                                'Diperbarui ${_formatTime(lastUpdatedAt!)}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurfaceVariant,
                                    ),
                              ),
                            ),
                        ],
                      ],
                    ),
        ),
      ),
    );
  }

  String _formatTime(DateTime t) {
    final h = t.hour.toString().padLeft(2, '0');
    final m = t.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

class _SummaryItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int value;
  final Color color;
  final String semanticsHint;
  final VoidCallback? onTap;

  const _SummaryItem({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    required this.semanticsHint,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) => Semantics(
        label: '$label, $value laporan',
        hint: onTap == null
            ? semanticsHint
            : 'Ketuk untuk melihat daftar laporan $label',
        button: onTap != null,
        child: ExcludeSemantics(
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(AppRadius.md),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.xs),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    icon,
                    color: color,
                    size: 26,
                  ),
                  const SizedBox(height: AppSpacing.xxs),
                  Text(
                    label,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  const SizedBox(height: 2),
                  TweenAnimationBuilder<int>(
                    tween: IntTween(begin: 0, end: value),
                    duration: const Duration(milliseconds: 800),
                    curve: Curves.easeOutCubic,
                    builder: (context, val, _) => Text(
                      '$val',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: color,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
}

class _SectionHeader extends StatelessWidget {
  final bool syncing;
  final VoidCallback onSync;

  const _SectionHeader({required this.syncing, required this.onSync});

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Icon(
            Icons.assignment_outlined,
            color: Theme.of(context).colorScheme.primary,
            size: 28,
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Daftar Laporan',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
          ),
          Semantics(
            button: true,
            label: syncing
                ? 'Sinkronisasi sedang berjalan'
                : 'Sinkronkan laporan sekarang',
            child: FilledButton.icon(
              onPressed: syncing ? null : onSync,
              icon: syncing
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.sync, size: 20),
              label: Text(syncing ? 'PROSES' : 'SINKRON'),
            ),
          ),
        ],
      );
}

class _MenuGrid extends StatefulWidget {
  final double width;
  final String? role;
  final int unreadCount;

  const _MenuGrid({
    required this.width,
    required this.role,
    required this.unreadCount,
  });

  @override
  State<_MenuGrid> createState() => _MenuGridState();
}

class _MenuGridState extends State<_MenuGrid>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entranceController;

  int _countEntries() {
    final isAdmin = RolePermissions.can(
      widget.role ?? '',
      ReportCapability.canVerifyReport,
    );
    final canCreate = RolePermissions.can(
      widget.role ?? '',
      ReportCapability.canCreateReport,
    );
    final canViewAll = RolePermissions.can(
      widget.role ?? '',
      ReportCapability.canViewAllReports,
    );
    var count = 8;
    if (!isAdmin && (canViewAll || canCreate)) count += 1;
    return count;
  }

  @override
  void initState() {
    super.initState();
    final count = _countEntries();
    _entranceController = AnimationController(
      vsync: this,
      duration: Duration(milliseconds: 350 + count * 50),
    )..forward();
  }

  @override
  void dispose() {
    _entranceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final isAdmin = RolePermissions.can(
        widget.role ?? '', ReportCapability.canVerifyReport);
    final canCreate = RolePermissions.can(
        widget.role ?? '', ReportCapability.canCreateReport);
    final canViewAll = RolePermissions.can(
        widget.role ?? '', ReportCapability.canViewAllReports);

    // Label aksi per role: admin memverifikasi, penulis membuat, sisanya membaca.
    String actionLabel(
        {required String verify,
        required String create,
        required String read}) {
      if (isAdmin) return verify;
      if (canCreate) return create;
      return read;
    }

    String actionHint(
            {required String verify,
            required String create,
            required String read}) =>
        '${actionLabel(verify: verify, create: create, read: read)}.';

    // — Entri menu: pastikan TIDAK ADA DUPLIKAT (perbaiki bug lampiran) —
    // "Semua Laporan" hanya untuk role yang dapat melihat/membuat laporan
    // (admin memakai menu verifikasi per jenis, bukan daftar gabungan).
    final entries = <Widget>[
      if (!isAdmin && (canViewAll || canCreate))
        _FeatureCard(
          key: const Key('menu_semua_laporan'),
          icon: Icons.view_list_outlined,
          title: 'Semua Laporan',
          subtitle: 'Cari, filter, dan ekspor laporan Anda',
          color: colors.primary,
          onTap: () => context.go('/laporan'),
          semanticsLabel:
              'Semua Laporan. Cari, filter, dan ekspor laporan Anda.',
        ),
      _FeatureCard(
        key: const Key('menu_hama'),
        icon: Icons.pest_control_outlined,
        title: 'Hama / OPT',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan hama',
          create: 'Laporkan gangguan tanaman',
          read: 'Lihat laporan hama',
        ),
        color: const Color(0xFF9A4A00),
        onTap: () => context.push('/hama'),
        semanticsLabel:
            'Hama dan Organisme Pengganggu Tumbuhan. ${actionHint(verify: 'Verifikasi laporan hama', create: 'Laporkan gangguan tanaman', read: 'Lihat laporan hama')}',
      ),
      _FeatureCard(
        key: const Key('menu_irigasi'),
        icon: Icons.water_drop_outlined,
        title: 'Irigasi',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan irigasi',
          create: 'Laporkan kondisi saluran',
          read: 'Lihat laporan irigasi',
        ),
        color: const Color(0xFF00639A),
        onTap: () => context.push('/irigasi'),
        semanticsLabel:
            'Irigasi. ${actionHint(verify: 'Verifikasi laporan irigasi', create: 'Laporkan kondisi saluran', read: 'Lihat laporan irigasi')}',
      ),
      _FeatureCard(
        key: const Key('menu_pupuk'),
        icon: Icons.grass_outlined,
        title: 'Pupuk',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan pupuk',
          create: 'Catat penggunaan pupuk',
          read: 'Lihat laporan pupuk',
        ),
        color: const Color(0xFF397A22),
        onTap: () => context.push('/pupuk'),
        semanticsLabel:
            'Pupuk. ${actionHint(verify: 'Verifikasi laporan pupuk', create: 'Catat penggunaan pupuk', read: 'Lihat laporan pupuk')}',
      ),
      _FeatureCard(
        key: const Key('menu_panen'),
        icon: Icons.agriculture_outlined,
        title: 'Panen',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan panen',
          create: 'Catat hasil dan luas panen',
          read: 'Lihat laporan panen',
        ),
        color: const Color(0xFF7A5D00),
        onTap: () => context.push('/panen'),
        semanticsLabel:
            'Panen. ${actionHint(verify: 'Verifikasi laporan panen', create: 'Catat hasil dan luas panen', read: 'Lihat laporan panen')}',
      ),
      _FeatureCard(
        key: const Key('menu_cuaca'),
        icon: Icons.cloud_outlined,
        title: 'Cuaca',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan cuaca',
          create: 'Laporkan kondisi cuaca',
          read: 'Lihat laporan cuaca',
        ),
        color: const Color(0xFF006874),
        onTap: () => context.push('/cuaca'),
        semanticsLabel:
            'Cuaca. ${actionHint(verify: 'Verifikasi laporan cuaca', create: 'Laporkan kondisi cuaca', read: 'Lihat laporan cuaca')}',
      ),
      _FeatureCard(
        key: const Key('menu_alat_sarana'),
        icon: Icons.handyman_outlined,
        title: 'Alat & Sarana',
        subtitle: actionLabel(
          verify: 'Verifikasi laporan sarana',
          create: 'Laporkan kondisi sarana',
          read: 'Lihat laporan sarana',
        ),
        color: const Color(0xFF695A4A),
        onTap: () => context.push('/alat-sarana'),
        semanticsLabel:
            'Alat dan Sarana. ${actionHint(verify: 'Verifikasi laporan sarana', create: 'Laporkan kondisi sarana', read: 'Lihat laporan sarana')}',
      ),
      _FeatureCard(
        key: const Key('menu_usulan_opt'),
        icon: Icons.bug_report_outlined,
        title: 'Usulan OPT',
        subtitle: 'Usulkan hama/penyakit/gulma baru',
        color: const Color(0xFF2E7D32),
        onTap: () => context.push('/usulan-opt'),
        semanticsLabel:
            'Usulan OPT. Usulkan organisme pengganggu tanaman baru untuk direview Admin.',
      ),
      _FeatureCard(
        key: const Key('menu_notifikasi'),
        icon: Icons.notifications_outlined,
        title: 'Notifikasi',
        subtitle: widget.unreadCount > 0
            ? '${widget.unreadCount} belum dibaca'
            : 'Semua sudah dibaca',
        color: colors.secondary,
        onTap: () => context.push('/notifications'),
        badgeCount: widget.unreadCount,
        semanticsLabel:
            'Notifikasi. ${widget.unreadCount > 0 ? "${widget.unreadCount} pesan belum dibaca." : "Semua notifikasi sudah dibaca."}',
      ),
      _FeatureCard(
        key: const Key('menu_profil'),
        icon: Icons.account_circle_outlined,
        title: 'Profil Saya',
        subtitle: 'Akun dan keamanan',
        color: const Color(0xFF65558F),
        onTap: () => context.go('/profile'),
        semanticsLabel:
            'Profil Saya. Lihat informasi akun dan pengaturan keamanan.',
      ),
    ];

    final columns = AppBreakpoints.columnsForWidth(widget.width);
    final aspect = switch (columns) {
      3 => 2.0,
      2 => 2.15,
      _ => 3.2,
    };

    final totalMs = _entranceController.duration!.inMilliseconds;
    final stepMs = 50;
    const animMs = 350;

    Widget staggered(int index, Widget child) {
      final t0 = index * stepMs / totalMs;
      final t1 = (index * stepMs + animMs) / totalMs;
      final animation = CurvedAnimation(
        parent: _entranceController,
        curve: Interval(t0, t1, curve: Curves.easeOutCubic),
      );
      return FadeTransition(
        opacity: animation,
        child: SlideTransition(
          position: Tween<Offset>(
            begin: const Offset(0, 0.1),
            end: Offset.zero,
          ).animate(animation),
          child: child,
        ),
      );
    }

    return GridView.count(
      crossAxisCount: columns,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: AppSpacing.md,
      crossAxisSpacing: AppSpacing.md,
      childAspectRatio: aspect,
      children: <Widget>[
        for (var i = 0; i < entries.length; i++) staggered(i, entries[i]),
      ],
    );
  }
}

class _FeatureCard extends StatefulWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;
  final int? badgeCount;
  final String? semanticsLabel;

  const _FeatureCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
    this.badgeCount,
    this.semanticsLabel,
  });

  @override
  State<_FeatureCard> createState() => _FeatureCardState();
}

class _FeatureCardState extends State<_FeatureCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: widget.semanticsLabel ?? '${widget.title}. ${widget.subtitle}',
        hint: 'Ketuk untuk membuka',
        child: AnimatedScale(
          scale: _pressed ? 0.97 : 1.0,
          duration: const Duration(milliseconds: 100),
          child: Card(
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: widget.onTap,
              onTapDown: (_) => setState(() => _pressed = true),
              onTapUp: (_) => setState(() => _pressed = false),
              onTapCancel: () => setState(() => _pressed = false),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: widget.color.withValues(alpha: .12),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          Icon(widget.icon, color: widget.color, size: 28),
                          if (widget.badgeCount != null &&
                              widget.badgeCount! > 0)
                            Positioned(
                              right: -6,
                              top: -6,
                              child: Badge(
                                label: Text(
                                  widget.badgeCount! > 99
                                      ? '99+'
                                      : '${widget.badgeCount}',
                                  style: const TextStyle(fontSize: 10),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: AppSpacing.xxs),
                          Text(
                            widget.subtitle,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurfaceVariant,
                                      height: 1.3,
                                    ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.xs),
                    Padding(
                      padding: const EdgeInsets.all(AppSpacing.xxs),
                      child: Icon(
                        Icons.chevron_right,
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                        size: 22,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
}
