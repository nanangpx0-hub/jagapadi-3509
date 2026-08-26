import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../providers/usulan_opt_provider.dart';
import 'usulan_opt_form_screen.dart';
import 'usulan_opt_detail_screen.dart';

class UsulanOptListScreen extends StatefulWidget {
  const UsulanOptListScreen({super.key});

  @override
  State<UsulanOptListScreen> createState() => _UsulanOptListScreenState();
}

class _UsulanOptListScreenState extends State<UsulanOptListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<UsulanOptProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<UsulanOptProvider>();
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Usulan OPT Saya')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final created = await Navigator.of(context).push<bool>(
            MaterialPageRoute(builder: (_) => const UsulanOptFormScreen()),
          );
          if (created == true && context.mounted) {
            context.read<UsulanOptProvider>().load();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Buat Usulan'),
      ),
      body: RefreshIndicator(
        onRefresh: () => p.load(),
        child: p.loading && p.list.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : p.error != null && p.list.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(p.error!, textAlign: TextAlign.center),
                        const SizedBox(height: AppSpacing.md),
                        FilledButton.icon(
                          onPressed: () => p.load(),
                          icon: const Icon(Icons.refresh),
                          label: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  )
                : p.list.isEmpty
                    ? const Center(
                        child: Text(
                          'Belum ada usulan OPT.\nTekan tombol + untuk membuat usulan baru.',
                          textAlign: TextAlign.center,
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        itemCount: p.list.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, i) {
                          final item = p.list[i];
                          return Card(
                            margin: EdgeInsets.zero,
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: scheme.primaryContainer,
                                child: Icon(
                                  item.jenis == 'penyakit'
                                      ? Icons.coronavirus
                                      : item.jenis == 'gulma'
                                          ? Icons.grass
                                          : Icons.bug_report,
                                  size: 20,
                                ),
                              ),
                              title: Text(
                                item.namaLokal ?? '(tanpa nama lokal)',
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${item.jenis} · ${item.komoditas ?? '-'}'),
                                  if (item.needsRevision && item.catatanReview != null)
                                    Text(
                                      'Catatan: ${item.catatanReview}',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                          color: scheme.error, fontSize: 12),
                                    ),
                                ],
                              ),
                              trailing: _StatusChip(status: item.status),
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        UsulanOptDetailScreen(id: item.id),
                                  ),
                                );
                                if (context.mounted) p.load();
                              },
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  Color get _color {
    switch (status) {
      case 'Draf':
        return Colors.grey;
      case 'Menunggu Review':
        return Colors.blue;
      case 'Perlu Perbaikan':
        return Colors.orange;
      case 'Disetujui':
        return Colors.green;
      case 'Digabungkan':
        return Colors.teal;
      case 'Ditolak Permanen':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = switch (status) {
      'Draf' => 'Draf',
      'Menunggu Review' => 'Review',
      'Perlu Perbaikan' => 'Perbaikan',
      'Disetujui' => 'Disetujui',
      'Digabungkan' => 'Digabungkan',
      'Ditolak Permanen' => 'Ditolak',
      _ => status,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.15),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _color),
      ),
      child: Text(
        label,
        style: TextStyle(
            color: _color, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }
}
