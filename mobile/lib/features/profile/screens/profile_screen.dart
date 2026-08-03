import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../auth/providers/auth_provider.dart';

class ProfileScreen extends StatefulWidget {
  final bool forceChangePassword;
  const ProfileScreen({super.key, this.forceChangePassword = false});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _currentPwCtrl = TextEditingController();
  final _newPwCtrl = TextEditingController();
  final _confirmPwCtrl = TextEditingController();
  late bool _showChangePw;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _showChangePw = widget.forceChangePassword;
  }

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
          const SnackBar(content: Text('Konfirmasi password tidak cocok')));
      return;
    }
    setState(() => _loading = true);
    final auth = context.read<AuthProvider>();
    final err = await auth.changePassword(
        _currentPwCtrl.text, _newPwCtrl.text, _confirmPwCtrl.text);
    setState(() => _loading = false);
    if (err == null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password berhasil diubah')));
      if (widget.forceChangePassword) {
        Navigator.pushReplacementNamed(context, '/home');
      } else {
        setState(() {
          _showChangePw = false;
          _currentPwCtrl.clear();
          _newPwCtrl.clear();
          _confirmPwCtrl.clear();
        });
      }
    } else if (err != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil'),
        automaticallyImplyLeading: !widget.forceChangePassword,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          CircleAvatar(
            radius: 40,
            backgroundColor: AppColors.primary.withOpacity(0.2),
            child: Text(
              (user?.namaLengkap.isNotEmpty == true
                  ? user!.namaLengkap[0]
                  : 'U')
                  .toUpperCase(),
              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(height: 16),
          Center(
            child: Text(user?.namaLengkap ?? 'User',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
          ),
          Center(
            child: Text('@${user?.username ?? '-'}',
                style: const TextStyle(color: AppColors.textSecondary)),
          ),
          const SizedBox(height: 8),
          if (user?.role != null)
            Center(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: user!.role == 'admin'
                      ? Colors.blue.withValues(alpha: 0.15)
                      : Colors.green.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  user.role.toUpperCase(),
                  style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: user.role == 'admin' ? Colors.blue : Colors.green),
                ),
              ),
            ),
          const SizedBox(height: 24),
          if (!widget.forceChangePassword)
            Card(
              child: Column(children: [
                ListTile(
                  leading: const Icon(Icons.logout, color: Colors.red),
                  title: const Text('Logout', style: TextStyle(color: Colors.red)),
                  onTap: () {
                    auth.logout();
                    Navigator.pushNamedAndRemoveUntil(
                        context, '/login', (_) => false);
                  },
                ),
              ]),
            ),
          const SizedBox(height: 24),
          if (widget.forceChangePassword)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Text('Anda harus mengubah password sebelum melanjutkan',
                  style: TextStyle(color: Colors.red.shade700, fontWeight: FontWeight.w600)),
            ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Ubah Password',
                      style:
                          TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _currentPwCtrl,
                    decoration:
                        const InputDecoration(labelText: 'Password Saat Ini'),
                    obscureText: true,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _newPwCtrl,
                    decoration:
                        const InputDecoration(labelText: 'Password Baru'),
                    obscureText: true,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _confirmPwCtrl,
                    decoration: const InputDecoration(
                        labelText: 'Konfirmasi Password Baru'),
                    obscureText: true,
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _loading ? null : _changePassword,
                    child: _loading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Simpan'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
