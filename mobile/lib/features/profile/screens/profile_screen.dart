import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../auth/providers/auth_provider.dart';
import '../../home/providers/dashboard_provider.dart';

/// Layar profil — menampilkan identitas pengguna, statistik personal,
/// menu akun & tentang, opsi logout. Form ubah password dipindah ke
/// [ChangePasswordScreen] (route `/profile/change-password`).
///
/// Mode [forceChangePassword] menampilkan banner + menu ubah password dan
/// memblokir keluar tanpa mengganti password (PopScope + dialog logout).
class ProfileScreen extends StatefulWidget {
  final bool forceChangePassword;
  const ProfileScreen({super.key, this.forceChangePassword = false});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final dashboard = context.read<DashboardProvider>();
      if (!dashboard.hasCachedData) {
        dashboard.loadStats();
      }
    });
  }

  /// Handler untuk PopScope (tombol kembali) saat forceChangePassword aktif.
  /// Menampilkan dialog konfirmasi: jika ya → logout (karena password belum
  /// diganti sesuai kebijakan keamanan). Jika tidak → batal.
  Future<bool> _handleForceBack() async {
    if (!widget.forceChangePassword) return true;
    final confirm = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Keluar tanpa ganti password?'),
            content: const Text(
              'Untuk keamanan akun, Anda diminta mengubah password sekarang. '
              'Jika keluar tanpa mengganti, Anda akan otomatis logout dan harus login kembali.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                style: FilledButton.styleFrom(
                  backgroundColor: Theme.of(ctx).colorScheme.error,
                  foregroundColor: Theme.of(ctx).colorScheme.onError,
                ),
                child: const Text('Logout'),
              ),
            ],
          ),
        ) ??
        false;
    if (confirm && mounted) {
      context.read<AuthProvider>().logout();
      return false;
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final scheme = Theme.of(context).colorScheme;
    final initials =
        (user?.namaLengkap.isNotEmpty == true ? user!.namaLengkap[0] : 'U')
            .toUpperCase();

    return PopScope(
      canPop: !widget.forceChangePassword,
      onPopInvokedWithResult: (didPop, _) async {
        if (widget.forceChangePassword && !didPop) {
          await _handleForceBack();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Semantics(
            header: true,
            child: const Text('Profil'),
          ),
        ),
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                // ── Identitas pengguna ─────────────────────────────
                Semantics(
                  container: true,
                  label:
                      '${user?.namaLengkap ?? "Pengguna"}. Username ${user?.username ?? "-"}. '
                      'Peran ${user?.role ?? "-"}.',
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      child: Column(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(3),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: LinearGradient(
                                colors: [scheme.primary, scheme.secondary],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ),
                            ),
                            child: CircleAvatar(
                              radius: 52,
                              backgroundColor: scheme.primaryContainer,
                              child: Text(
                                initials,
                                style: TextStyle(
                                  fontSize: 36,
                                  fontWeight: FontWeight.w800,
                                  color: scheme.onPrimaryContainer,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            user?.namaLengkap ?? 'User',
                            style: Theme.of(context)
                                .textTheme
                                .titleLarge
                                ?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: AppSpacing.xxs),
                          Text(
                            '@${user?.username ?? '-'}',
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                ),
                            textAlign: TextAlign.center,
                          ),
                          if (user?.role != null) ...[
                            const SizedBox(height: AppSpacing.sm),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: AppSpacing.md,
                                vertical: AppSpacing.xxs,
                              ),
                              decoration: BoxDecoration(
                                color: user!.role == 'admin'
                                    ? scheme.tertiaryContainer
                                    : scheme.primaryContainer,
                                borderRadius:
                                    BorderRadius.circular(AppRadius.sm),
                              ),
                              child: Text(
                                user.role.toUpperCase(),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.5,
                                  color: user.role == 'admin'
                                      ? scheme.onTertiaryContainer
                                      : scheme.onPrimaryContainer,
                                ),
                              ),
                            ),
                            Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.location_on,
                                    size: 14,
                                    color: scheme.onSurfaceVariant,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    'Kab. Jember',
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall
                                        ?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
                if (widget.forceChangePassword) ...[
                  // ── Paksa ubah password banner ─────────────────────
                  const SizedBox(height: AppSpacing.md),
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: scheme.errorContainer,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                    child: Semantics(
                      container: true,
                      label:
                          'Anda diwajibkan mengubah password sebelum menggunakan aplikasi.',
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Icon(
                              Icons.lock_reset,
                              size: 22,
                              color: scheme.onErrorContainer,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              'Anda harus mengubah password sebelum melanjutkan penggunaan aplikasi.',
                              style: TextStyle(
                                color: scheme.onErrorContainer,
                                fontWeight: FontWeight.w700,
                                height: 1.3,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ] else ...[
                  // ── Statistik personal ────────────────────────────
                  const SizedBox(height: AppSpacing.md),
                  Consumer<DashboardProvider>(
                    builder: (context, dashboard, _) {
                      final hama = dashboard.hamaStats;
                      final irigasi = dashboard.irigasiStats;
                      final loaded = hama != null && irigasi != null;
                      final total = loaded
                          ? '${hama.totalAktif + hama.totalDraf + hama.totalDitolak + irigasi.totalAktif + irigasi.totalDraf + irigasi.totalDitolak}'
                          : '-';
                      final verified = loaded
                          ? '${hama.totalAktif + irigasi.totalAktif}'
                          : '-';
                      return Semantics(
                        container: true,
                        label:
                            'Statistik personal. Total laporan $total. Diverifikasi $verified.',
                        child: Card(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              vertical: AppSpacing.md,
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: _StatColumn(
                                    label: 'Total Laporan',
                                    value: total,
                                    scheme: scheme,
                                  ),
                                ),
                                _StatDivider(scheme: scheme),
                                Expanded(
                                  child: _StatColumn(
                                    label: 'Bulan Ini',
                                    value: '-',
                                    scheme: scheme,
                                  ),
                                ),
                                _StatDivider(scheme: scheme),
                                Expanded(
                                  child: _StatColumn(
                                    label: 'Diverifikasi',
                                    value: verified,
                                    scheme: scheme,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                  // ── Group Akun ────────────────────────────────────
                  const _SectionHeader('Akun'),
                  Card(
                    child: Column(
                      children: [
                        ListTile(
                          leading: const Icon(Icons.lock_outline),
                          title: const Text('Ubah Password'),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/profile/change-password'),
                        ),
                      ],
                    ),
                  ),
                  // ── Group Tentang ─────────────────────────────────
                  const _SectionHeader('Tentang'),
                  Card(
                    child: Column(
                      children: [
                        ListTile(
                          leading: const Icon(Icons.info_outline),
                          title: const Text('Versi Aplikasi'),
                          trailing: Text(
                            'v1.0.0',
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                          ),
                        ),
                        ListTile(
                          leading: const Icon(Icons.help_outline),
                          title: const Text('Tentang JAGAPADI'),
                          onTap: () => showAboutDialog(
                            context: context,
                            applicationName: 'JAGAPADI',
                            applicationVersion: '1.0.0',
                            applicationLegalese: '© 2026 BPS Kab. Jember',
                          ),
                        ),
                      ],
                    ),
                  ),
                  // ── Tombol aksi: Logout ────────────────────────────
                  const SizedBox(height: AppSpacing.md),
                  Semantics(
                    button: true,
                    label: 'Keluar dari akun dan kembali ke halaman login',
                    child: Card(
                      child: Column(
                        children: [
                          ListTile(
                            leading: Icon(
                              Icons.logout,
                              color: scheme.error,
                            ),
                            title: Text(
                              'Logout',
                              style: TextStyle(
                                color: scheme.error,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Text(
                              'Keluar dari akun JAGAPADI',
                              style: TextStyle(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                            onTap: () async {
                              final confirm = await showDialog<bool>(
                                    context: context,
                                    builder: (ctx) => AlertDialog(
                                      title: const Text('Logout'),
                                      content: const Text(
                                        'Yakin keluar dari akun JAGAPADI?',
                                      ),
                                      actions: [
                                        TextButton(
                                          onPressed: () =>
                                              Navigator.pop(ctx, false),
                                          child: const Text('Batal'),
                                        ),
                                        FilledButton(
                                          onPressed: () =>
                                              Navigator.pop(ctx, true),
                                          style: FilledButton.styleFrom(
                                            backgroundColor: scheme.error,
                                            foregroundColor: scheme.onError,
                                          ),
                                          child: const Text('Logout'),
                                        ),
                                      ],
                                    ),
                                  ) ??
                                  false;
                              if (confirm && mounted) {
                                auth.logout();
                              }
                            },
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Kolom statistik: angka besar (titleLarge, bold, primary) + label kecil.
class _StatColumn extends StatelessWidget {
  final String label;
  final String value;
  final ColorScheme scheme;

  const _StatColumn({
    required this.label,
    required this.value,
    required this.scheme,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: scheme.primary,
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}

class _StatDivider extends StatelessWidget {
  final ColorScheme scheme;

  const _StatDivider({required this.scheme});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: VerticalDivider(
        indent: 8,
        endIndent: 8,
        width: 1,
        thickness: 1,
        color: scheme.outlineVariant,
      ),
    );
  }
}

/// Header grup menu: uppercase, bold, bodySmall, onSurfaceVariant.
class _SectionHeader extends StatelessWidget {
  final String title;

  const _SectionHeader(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24, bottom: 8, left: 4),
      child: Text(
        title.toUpperCase(),
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
            ),
      ),
    );
  }
}
