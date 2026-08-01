import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../providers/laporan_irigasi_provider.dart';

class IrigasiListScreen extends StatefulWidget {
  const IrigasiListScreen({super.key});

  @override
  State<IrigasiListScreen> createState() => _IrigasiListScreenState();
}

class _IrigasiListScreenState extends State<IrigasiListScreen> {
  final _scrollCtrl = ScrollController();
  String _status = 'all';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
        final p = context.read<LaporanIrigasiProvider>();
        if (!p.loading && p.hasMore) p.loadList();
      }
    });
  }

  Future<void> _refresh() async =>
      context.read<LaporanIrigasiProvider>().loadList(refresh: true, status: _status);

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanIrigasiProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan Irigasi'), actions: [
        IconButton(icon: const Icon(Icons.add),
            onPressed: () => Navigator.pushNamed(context, '/irigasi/create').then((_) => _refresh())),
      ]),
      body: Column(
        children: [
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              children: ['all', 'Draf', 'Submitted', 'Diverifikasi', 'Ditolak'].map((f) {
                final active = _status == f;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(f == 'all' ? 'Semua' : f),
                    selected: active,
                    onSelected: (_) {
                      setState(() => _status = f);
                      context.read<LaporanIrigasiProvider>().loadList(refresh: true, status: f);
                    },
                  ),
                );
              }).toList(),
            ),
          ),
          Expanded(
            child: p.loading && p.list.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : p.list.isEmpty
                    ? Center(child: Text('Belum ada laporan', style: TextStyle(color: AppColors.textSecondary)))
                    : RefreshIndicator(
                        onRefresh: _refresh,
                        child: ListView.builder(
                          controller: _scrollCtrl,
                          itemCount: p.list.length + (p.hasMore ? 1 : 0),
                          itemBuilder: (_, i) {
                            if (i >= p.list.length) {
                              return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()));
                            }
                            final item = p.list[i];
                            return Card(
                              margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                              child: ListTile(
                                title: Text(item.nomorLaporan ?? 'Draf #${item.id}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                subtitle: Text('${item.tanggal ?? '-'} · ${item.namaSaluran ?? '-'}'),
                                trailing: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: (item.status == 'Draf' ? Colors.grey : item.status == 'Submitted' ? Colors.orange : item.status == 'Diverifikasi' ? Colors.green : item.status == 'Ditolak' ? Colors.red : Colors.blueGrey).withOpacity(0.15),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(item.statusLabel, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: item.status == 'Draf' ? Colors.grey : item.status == 'Submitted' ? Colors.orange : item.status == 'Diverifikasi' ? Colors.green : item.status == 'Ditolak' ? Colors.red : Colors.blueGrey)),
                                ),
                                onTap: () => Navigator.pushNamed(context, '/irigasi/${item.id}').then((_) => _refresh()),
                              ),
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
