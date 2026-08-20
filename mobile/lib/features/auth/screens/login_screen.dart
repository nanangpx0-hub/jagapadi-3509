import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../providers/auth_provider.dart';

import '../../../core/connectivity_service.dart';

/// Layar login JAGAPADI — mendukung mode online & offline login.
///
/// Perbaikan UI/UX vNext:
/// - Responsif: konten diberi ConstrainedBox agar tidak melebar di tablet
/// - Konsistensi warna: error banner pakai errorContainer ColorScheme (bukan merah hardcoded)
/// - Aksesibilitas: semua field + tombol punya semantics label
/// - Area sentuh: suffix icon visibility toggle menggunakan IconButton 48x48
/// - Konsistensi: tombol Login menggunakan minimumSize 48x52 dari tema
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    final isOnline = context.read<ConnectivityService>().isOnline;
    final auth = context.read<AuthProvider>();
    auth.clearError();
    final ok = await auth.login(
      _usernameCtrl.text.trim(),
      _passwordCtrl.text,
      isOnline: isOnline,
    );
    if (ok && mounted) {
      if (auth.offlineMode) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text(
              'Mode offline aktif. Draf akan disinkronkan saat server tersedia.',
            ),
          ),
        );
      }
      if (auth.mustChangePassword) {
        context.go('/profile');
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Silakan ubah password terlebih dahulu')),
        );
      } else {
        context.go('/home');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isOnline = context.watch<ConnectivityService>().isOnline;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // ── Banner offline ──────────────────────────────────
                    if (!isOnline) ...[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(AppSpacing.md),
                        margin: const EdgeInsets.only(bottom: AppSpacing.lg),
                        decoration: BoxDecoration(
                          color: AppTheme.warningContainer,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                          border: Border.all(
                            color: AppTheme.onWarningContainer.withValues(alpha: .25),
                          ),
                        ),
                        child: Semantics(
                          container: true,
                          label: 'Mode offline aktif. Hanya bisa login menggunakan akun yang sebelumnya berhasil login online di perangkat ini.',
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Icon(
                                  Icons.wifi_off,
                                  color: AppTheme.onWarningContainer,
                                  size: 22,
                                ),
                              ),
                              const SizedBox(width: AppSpacing.sm),
                              Expanded(
                                child: Text(
                                  'Mode offline: gunakan akun yang sebelumnya pernah berhasil login online di perangkat ini.',
                                  style: TextStyle(
                                    color: AppTheme.onWarningContainer,
                                    fontSize: 13,
                                    height: 1.35,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                    // ── Logo + tagline ─────────────────────────────────
                    Semantics(
                      container: true,
                      label: 'Selamat datang di JAGAPADI, Jember Agrikultur Gapai Prestasi Digital',
                      child: Column(
                        children: [
                          Container(
                            width: 88,
                            height: 88,
                            decoration: BoxDecoration(
                              color: scheme.primaryContainer,
                              borderRadius: BorderRadius.circular(24),
                            ),
                            child: Icon(
                              Icons.agriculture,
                              size: 48,
                              color: scheme.onPrimaryContainer,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            'JAGAPADI',
                            style:
                                Theme.of(context).textTheme.headlineMedium?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      color: scheme.primary,
                                      letterSpacing: 0.5,
                                    ),
                          ),
                          const SizedBox(height: AppSpacing.xxs),
                          Text(
                            'Jember Agrikultur Gapai Prestasi Digital',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                  fontWeight: FontWeight.w500,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    // ── Banner error auth ─────────────────────────────
                    Consumer<AuthProvider>(
                      builder: (_, auth, __) {
                        if (auth.error == null) return const SizedBox.shrink();
                        final isConnectionError =
                            auth.error!.toLowerCase().contains('server') ||
                                auth.error!.toLowerCase().contains('offline') ||
                                auth.error!.toLowerCase().contains('koneksi') ||
                                auth.error!.toLowerCase().contains('timeout');
                        return Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(AppSpacing.md),
                          margin: const EdgeInsets.only(bottom: AppSpacing.md),
                          decoration: BoxDecoration(
                            color: scheme.errorContainer,
                            borderRadius: BorderRadius.circular(AppRadius.md),
                            border: Border.all(
                              color: scheme.error.withValues(alpha: .3),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Semantics(
                                container: true,
                                label: '${isConnectionError ? "Masalah koneksi." : "Terjadi kesalahan."} ${auth.error!}',
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Padding(
                                      padding: const EdgeInsets.only(top: 2),
                                      child: Icon(
                                        isConnectionError
                                            ? Icons.cloud_off
                                            : Icons.error_outline,
                                        color: scheme.onErrorContainer,
                                        size: 22,
                                      ),
                                    ),
                                    const SizedBox(width: AppSpacing.sm),
                                    Expanded(
                                      child: Text(
                                        auth.error!,
                                        style: TextStyle(
                                          color: scheme.onErrorContainer,
                                          fontSize: 13,
                                          height: 1.35,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              if (isConnectionError) ...[
                                const SizedBox(height: AppSpacing.sm),
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: Semantics(
                                    button: true,
                                    label: 'Coba login lagi',
                                    child: TextButton.icon(
                                      onPressed: auth.loading ? null : _login,
                                      icon: const Icon(Icons.refresh, size: 18),
                                      label: const Text('Coba Lagi'),
                                      style: TextButton.styleFrom(
                                        foregroundColor: scheme.onErrorContainer,
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: AppSpacing.md,
                                          vertical: AppSpacing.xs,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        );
                      },
                    ),
                    // ── Input Username ────────────────────────────────
                    Semantics(
                      textField: true,
                      label: 'Username, kolom teks wajib diisi',
                      child: TextFormField(
                        key: const Key('input_username'),
                        controller: _usernameCtrl,
                        autocorrect: false,
                        enableSuggestions: false,
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: 'Username',
                          prefixIcon: const Icon(Icons.person_outline),
                        ),
                        validator: (v) =>
                            v == null || v.trim().isEmpty ? 'Username wajib diisi' : null,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // ── Input Password ────────────────────────────────
                    Semantics(
                      textField: true,
                      label: 'Password, kolom teks wajib diisi',
                      child: TextFormField(
                        key: const Key('input_password'),
                        controller: _passwordCtrl,
                        obscureText: _obscure,
                        textInputAction: TextInputAction.send,
                        onFieldSubmitted: (_) => _login(),
                        decoration: InputDecoration(
                          labelText: 'Password',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: Semantics(
                            button: true,
                            label: _obscure
                                ? 'Tampilkan password'
                                : 'Sembunyikan password',
                            child: IconButton(
                              onPressed: () =>
                                  setState(() => _obscure = !_obscure),
                              icon: Icon(
                                _obscure
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                            ),
                          ),
                        ),
                        validator: (v) =>
                            v == null || v.isEmpty ? 'Password wajib diisi' : null,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    // ── Tombol Login ──────────────────────────────────
                    SizedBox(
                      width: double.infinity,
                      child: Consumer<AuthProvider>(
                        builder: (_, auth, __) => Semantics(
                          button: true,
                          label: auth.loading
                              ? 'Sedang memproses login'
                              : isOnline
                                  ? 'Masuk ke aplikasi'
                                  : 'Masuk ke aplikasi dalam mode offline',
                          child: FilledButton(
                            key: const Key('button_login'),
                            onPressed: auth.loading ? null : _login,
                            style: FilledButton.styleFrom(
                              padding: const EdgeInsets.symmetric(
                                vertical: AppSpacing.md,
                              ),
                            ),
                            child: auth.loading
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.5,
                                      color: Colors.white,
                                    ),
                                  )
                                : Text(
                                    isOnline ? 'Masuk' : 'Masuk Offline',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 15,
                                    ),
                                  ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
