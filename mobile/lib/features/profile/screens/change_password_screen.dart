import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../auth/providers/auth_provider.dart';

/// Halaman ubah password — form terpisah dari halaman profil.
///
/// Dapat dipaksa via [forceChangePassword]: pengguna tidak boleh keluar
/// tanpa mengganti password. Tombol kembali menampilkan dialog konfirmasi
/// "keluar tanpa ganti password?" dan logout jika ya.
class ChangePasswordScreen extends StatefulWidget {
  final bool forceChangePassword;
  const ChangePasswordScreen({super.key, this.forceChangePassword = false});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _currentPwCtrl = TextEditingController();
  final _newPwCtrl = TextEditingController();
  final _confirmPwCtrl = TextEditingController();
  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _loading = false;

  @override
  void dispose() {
    _currentPwCtrl.dispose();
    _newPwCtrl.dispose();
    _confirmPwCtrl.dispose();
    super.dispose();
  }

  Future<void> _changePassword() async {
    if (_newPwCtrl.text != _confirmPwCtrl.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Konfirmasi password tidak cocok')),
      );
      return;
    }
    setState(() => _loading = true);
    final auth = context.read<AuthProvider>();
    final err = await auth.changePassword(
        _currentPwCtrl.text, _newPwCtrl.text, _confirmPwCtrl.text);
    if (!mounted) return;
    setState(() => _loading = false);
    if (err == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Password berhasil diubah')),
      );
      if (widget.forceChangePassword) {
        context.go('/home');
      } else {
        setState(() {
          _currentPwCtrl.clear();
          _newPwCtrl.clear();
          _confirmPwCtrl.clear();
        });
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
    }
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
    final scheme = Theme.of(context).colorScheme;

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
            child: const Text('Ubah Password'),
          ),
          leading: widget.forceChangePassword
              ? Semantics(
                  button: true,
                  label: 'Keluar dari pengaturan password',
                  child: IconButton(
                    icon: const Icon(Icons.arrow_back),
                    onPressed: _handleForceBack,
                  ),
                )
              : null,
        ),
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                // ── Paksa ubah password banner ─────────────────────
                if (widget.forceChangePassword)
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    margin: const EdgeInsets.only(bottom: AppSpacing.md),
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
                // ── Formulir Ubah Password ─────────────────────────
                Semantics(
                  container: true,
                  label: 'Formulir ubah password',
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Semantics(
                            header: true,
                            child: Text(
                              'Ubah Password',
                              style: Theme.of(context)
                                  .textTheme
                                  .titleLarge
                                  ?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Semantics(
                            textField: true,
                            label: 'Password saat ini',
                            child: TextFormField(
                              controller: _currentPwCtrl,
                              obscureText: _obscureCurrent,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: 'Password Saat Ini',
                                prefixIcon: const Icon(Icons.lock_outline),
                                suffixIcon: Semantics(
                                  button: true,
                                  label: _obscureCurrent
                                      ? 'Tampilkan password saat ini'
                                      : 'Sembunyikan password saat ini',
                                  child: IconButton(
                                    onPressed: () => setState(() =>
                                        _obscureCurrent = !_obscureCurrent),
                                    icon: Icon(_obscureCurrent
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Semantics(
                            textField: true,
                            label: 'Password baru',
                            child: TextFormField(
                              controller: _newPwCtrl,
                              obscureText: _obscureNew,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: 'Password Baru',
                                prefixIcon: const Icon(Icons.lock_outline),
                                suffixIcon: Semantics(
                                  button: true,
                                  label: _obscureNew
                                      ? 'Tampilkan password baru'
                                      : 'Sembunyikan password baru',
                                  child: IconButton(
                                    onPressed: () => setState(
                                        () => _obscureNew = !_obscureNew),
                                    icon: Icon(_obscureNew
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Semantics(
                            textField: true,
                            label:
                                'Konfirmasi password baru, harus sama dengan password baru',
                            child: TextFormField(
                              controller: _confirmPwCtrl,
                              obscureText: _obscureConfirm,
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _changePassword(),
                              decoration: InputDecoration(
                                labelText: 'Konfirmasi Password Baru',
                                prefixIcon: const Icon(Icons.lock_outline),
                                suffixIcon: Semantics(
                                  button: true,
                                  label: _obscureConfirm
                                      ? 'Tampilkan konfirmasi password'
                                      : 'Sembunyikan konfirmasi password',
                                  child: IconButton(
                                    onPressed: () => setState(() =>
                                        _obscureConfirm = !_obscureConfirm),
                                    icon: Icon(_obscureConfirm
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.lg),
                          SizedBox(
                            width: double.infinity,
                            child: Semantics(
                              button: true,
                              label: _loading
                                  ? 'Sedang menyimpan password baru'
                                  : 'Simpan password baru',
                              child: FilledButton(
                                onPressed: _loading ? null : _changePassword,
                                style: FilledButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: AppSpacing.md,
                                  ),
                                ),
                                child: _loading
                                    ? const SizedBox(
                                        height: 20,
                                        width: 20,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2.5,
                                          color: Colors.white,
                                        ),
                                      )
                                    : const Text(
                                        'Simpan',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 15,
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
              ],
            ),
          ),
        ),
      ),
    );
  }
}
