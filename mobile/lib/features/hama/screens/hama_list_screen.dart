import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../providers/laporan_hama_provider.dart';

class HamaListScreen extends StatefulWidget {
  final String? initialStatus;
  const HamaListScreen({super.key, this.initialStatus});

  @override
  State<HamaListScreen> createState() => _HamaListScreenState();
}

class _HamaListScreenState extends State<HamaListScreen> {
  final _scrollCtrl = ScrollController();
  late String _status;

  @override
  void initState() {
    super.initState();
    _status = widget.initialStatus ?? 'all';
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
        final p = context.read<LaporanHamaProvider>();
        if (!p.loading && p.hasMore) p.loadList();
      }
    });
  }

  Future<void> _refresh() async {
    await context.read<LaporanHamaProvider>().loadList(refresh: true, status: _status);
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanHamaProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan Hama'), actions: [
        IconButton(
          icon: const Icon(Icons.add),
          onPressed: () => Navigator.pushNamed(context, '/hama/create').then((_) => _refresh()),
        ),
      ]),
      body: Column(
        children: [
          _StatusFilter(
            current: _status,
            onChange: (s) {
              setState(() => _status = s);
              context.read<LaporanHamaProvider>().loadList(refresh: true, status: s);
            },
          ),
          Expanded(
            child: p.loading && p.list.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : p.list.isEmpty
                    ? Center(
                        child: Text('Belum ada laporan', style: TextStyle(color: AppColors.textSecondary)))
                    : RefreshIndicator(
                        onRefresh: _refresh,
                        child: ListView.builder(
                          controller: _scrollCtrl,
                          itemCount: p.list.length + (p.hasMore ? 1 : 0),
                          itemBuilder: (_, i) {
                            if (i >= p.list.length) {
                              return const Center(child: Padding(
                                padding: EdgeInsets.all(16), child: CircularProgressIndicator(),
                              ));
                            }
                            final item = p.list[i];
                            return _LaporanCard(
                              item: item,
                              onTap: () =>
                                  Navigator.pushNamed(context, '/hama/${item.id}').then((_) => _refresh()),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _StatusFilter extends StatelessWidget {
  final String current;
  final ValueChanged<String> onChange;
  const _StatusFilter({required this.current, required this.onChange});

  final _filters = const ['all', 'Draf', 'Submitted', 'Diverifikasi', 'Ditolak'];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 40,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        children: _filters.map((f) {
          final active = current == f;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(f == 'all' ? 'Semua' : f),
              selected: active,
              onSelected: (_) => onChange(f),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _LaporanCard extends StatelessWidget {
  final dynamic item;
  final VoidCallback onTap;

  const _LaporanCard({required this.item, required this.onTap});

  Color _statusColor(String s) {
    switch (s) {
      case 'Draf': return Colors.grey;
      case 'Submitted': return Colors.orange;
      case 'Diverifikasi': return Colors.green;
      case 'Ditolak': return Colors.red;
      case 'Diarsipkan': return Colors.blueGrey;
      default: return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = item as dynamic;
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      child: ListTile(
        title: Text(l.nomorLaporan ?? 'Draf #${l.id}',
            style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(
            '${l.tanggal ?? '-'} · ${l.namaOpt ?? '-'}'),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: _statusColor(l.status).withOpacity(0.15),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(l.statusLabel ?? l.status,
              style: TextStyle(
                  color: _statusColor(l.status), fontSize: 12, fontWeight: FontWeight.w600)),
        ),
        onTap: onTap,
      ),
    );
  }
}
