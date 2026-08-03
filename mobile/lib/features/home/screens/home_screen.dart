import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../auth/providers/auth_provider.dart';
import '../../notifications/providers/notification_provider.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationProvider>().startPolling();
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final notif = context.watch<NotificationProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('JAGAPADI'),
        actions: [
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined),
                onPressed: () => _go(context, '/notifications'),
              ),
              if (notif.unreadCount > 0)
                Positioned(
                  right: 6,
                  top: 6,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Colors.red,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '${notif.unreadCount > 99 ? '99+' : notif.unreadCount}',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Selamat datang,\n${user?.namaLengkap ?? ''}',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    )),
            const SizedBox(height: 4),
            Text(auth.isAdmin ? 'Admin' : 'Petugas Lapangan',
                style: const TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: 24),
            if (auth.isAdmin)
              _MenuCard(
                icon: Icons.verified_user,
                title: 'Antrian Verifikasi',
                subtitle: '${notif.unreadCount} laporan menunggu',
                color: Colors.indigo,
                onTap: () => _go(context, '/hama?status=Submitted'),
              ),
            if (auth.isAdmin) const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.bug_report,
              title: 'Laporan Hama',
              subtitle: auth.isAdmin ? 'Verifikasi & kelola semua laporan' : 'Buat & kirim laporan hama/OPT',
              color: Colors.orange,
              onTap: () => _go(context, '/hama'),
            ),
            const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.water_drop,
              title: 'Laporan Irigasi',
              subtitle: auth.isAdmin ? 'Verifikasi & kelola semua laporan' : 'Buat & kirim laporan irigasi',
              color: Colors.blue,
              onTap: () => _go(context, '/irigasi'),
            ),
            const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.notifications,
              title: 'Notifikasi',
              subtitle: notif.unreadCount > 0
                  ? '${notif.unreadCount} belum dibaca'
                  : 'Tidak ada notifikasi baru',
              color: Colors.teal,
              onTap: () => _go(context, '/notifications'),
            ),
            const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.person,
              title: 'Profil',
              subtitle: 'Pengaturan akun & ganti password',
              color: Colors.purple,
              onTap: () => _go(context, '/profile'),
            ),
          ],
        ),
      ),
    );
  }

  void _go(BuildContext context, String path) {
    Navigator.of(context).pushNamed(path);
  }
}

class _MenuCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _MenuCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.15),
          child: Icon(icon, color: color),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
