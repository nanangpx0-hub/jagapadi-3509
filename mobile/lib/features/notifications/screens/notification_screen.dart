import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/notification_item.dart';
import '../providers/notification_provider.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => context.read<NotificationProvider>().load());
  }

  void _onTap(NotificationItem n) async {
    if (!n.isRead) {
      await context.read<NotificationProvider>().markRead(n.id);
    }
    if (n.entity != null && n.laporanId != null && mounted) {
      final route = n.entity == 'hama' ? '/hama/${n.laporanId}' : '/irigasi/${n.laporanId}';
      Navigator.pushNamed(context, route);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<NotificationProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi'),
        actions: [
          if (p.hasUnread)
            TextButton(onPressed: () => p.markAllRead(), child: const Text('Baca Semua')),
        ],
      ),
      body: p.loading
          ? const Center(child: CircularProgressIndicator())
          : p.list.isEmpty
              ? const Center(child: Text('Belum ada notifikasi'))
              : RefreshIndicator(
                  onRefresh: () => p.load(),
                  child: ListView.builder(
                    itemCount: p.list.length,
                    itemBuilder: (_, i) {
                      final n = p.list[i];
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                        child: ListTile(
                          leading: Icon(n.isRead ? Icons.notifications_off : Icons.notifications_active, color: n.isRead ? Colors.grey : Colors.blue),
                          title: Text(n.title, style: TextStyle(fontWeight: n.isRead ? FontWeight.normal : FontWeight.w600)),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (n.body.isNotEmpty) Text(n.body, maxLines: 2, overflow: TextOverflow.ellipsis),
                              if (n.createdAt != null) Text(n.createdAt!, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                            ],
                          ),
                          onTap: () => _onTap(n),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
