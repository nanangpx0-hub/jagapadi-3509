import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/local_drafts_banner.dart';
import '../../../core/widgets/skeleton_card.dart';
import '../../../core/widgets/status_badge.dart';
import '../providers/laporan_irigasi_provider.dart';

class IrigasiListScreen extends StatefulWidget {
  final String? initialStatus;
  const IrigasiListScreen({super.key, this.initialStatus});

  @override
  State<IrigasiListScreen> createState() => _IrigasiListScreenState();
}

class _IrigasiListScreenState extends State<IrigasiListScreen> {
  final _scrollCtrl = ScrollController();
  final _searchCtrl = TextEditingController();
  late String _status;
  bool _isSearching = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _status = widget.initialStatus ?? 'all';
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200) {
        final p = context.read<LaporanIrigasiProvider>();
        if (!p.loading && p.hasMore) {
          p.loadList(status: _status, search: _searchCtrl.text);
        }
      }
    });
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _searchCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _refresh() async {
    await context.read<LaporanIrigasiProvider>().loadList(
          refresh: true,
          status: _status,
          search: _searchCtrl.text,
        );
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      _refresh();
    });
  }

  String _emptyStateMessage() {
    if (_searchCtrl.text.isNotEmpty) {
      return 'Tidak ada laporan yang cocok dengan pencarian "${_searchCtrl.text}"';
    }
    switch (_status) {
      case 'Draf':
        return 'Belum ada draf laporan irigasi. Buat laporan baru dengan tombol +';
      case 'Submitted':
        return 'Tidak ada laporan irigasi yang sedang menunggu verifikasi';
      case 'Diverifikasi':
        return 'Belum ada laporan irigasi yang diverifikasi';
      case 'Ditolak':
        return 'Tidak ada laporan irigasi yang ditolak';
      default:
        return 'Belum ada data laporan irigasi';
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanIrigasiProvider>();
    return Scaffold(
      appBar: AppBar(
        title: _isSearching
            ? TextField(
                controller: _searchCtrl,
                autofocus: true,
                style: const TextStyle(color: Colors.white),
                decoration: const InputDecoration(
                  hintText: 'Cari laporan irigasi...',
                  hintStyle: TextStyle(color: Colors.white70),
                  border: InputBorder.none,
                ),
                onChanged: _onSearchChanged,
              )
            : const Text('Laporan Irigasi'),
        actions: [
          IconButton(
            icon: Icon(_isSearching ? Icons.close : Icons.search),
            onPressed: () {
              setState(() {
                if (_isSearching) {
                  _isSearching = false;
                  _searchCtrl.clear();
                  _refresh();
                } else {
                  _isSearching = true;
                }
              });
            },
          ),
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => context.push('/irigasi/create').then((_) => _refresh()),
          ),
        ],
      ),
      body: Column(
        children: [
          _StatusFilter(
            current: _status,
            onChange: (s) {
              setState(() => _status = s);
              context.read<LaporanIrigasiProvider>().loadList(
                    refresh: true,
                    status: s,
                    search: _searchCtrl.text,
                  );
            },
          ),
          LocalDraftsBanner(
            type: 'irigasi',
            api: context.read<LaporanIrigasiProvider>().api,
            onSyncCompleted: _refresh,
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Menampilkan ${p.total} laporan',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                if (p.loading)
                  const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
              ],
            ),
          ),
          Expanded(
            child: p.loading && p.list.isEmpty
                ? const SkeletonListScreen(itemCount: 6)
                : p.error != null && p.list.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.cloud_off, size: 64, color: AppColors.textSecondary),
                              const SizedBox(height: 16),
                              Text(
                                p.error!,
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
                              ),
                              const SizedBox(height: 20),
                              ElevatedButton.icon(
                                onPressed: _refresh,
                                icon: const Icon(Icons.refresh),
                                label: const Text('Coba Lagi'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : p.list.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(24),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.search_off, size: 64, color: AppColors.textSecondary),
                                  const SizedBox(height: 16),
                                  Text(
                                    _emptyStateMessage(),
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
                                  ),
                                  const SizedBox(height: 20),
                                  ElevatedButton.icon(
                                    onPressed: () => context.push('/irigasi/create').then((_) => _refresh()),
                                    icon: const Icon(Icons.add),
                                    label: const Text('Buat Laporan Baru'),
                                  ),
                                ],
                              ),
                            ),
                          )
                    : RefreshIndicator(
                        onRefresh: _refresh,
                        child: ListView.builder(
                          controller: _scrollCtrl,
                          itemCount: p.list.length + (p.hasMore ? 1 : 0),
                          itemBuilder: (_, i) {
                            if (i >= p.list.length) {
                              return const Center(
                                child: Padding(
                                  padding: EdgeInsets.all(16),
                                  child: CircularProgressIndicator(),
                                ),
                              );
                            }
                            final item = p.list[i];
                            return _LaporanCard(
                              item: item,
                              onTap: () => context.push('/irigasi/${item.id}').then((_) => _refresh()),
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

  final _filters = const [
    {'key': 'all', 'label': 'Semua'},
    {'key': 'Draf', 'label': 'Draf'},
    {'key': 'Submitted', 'label': 'Dikirim'},
    {'key': 'Diverifikasi', 'label': 'Diverifikasi'},
    {'key': 'Ditolak', 'label': 'Ditolak'},
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 48,
      color: Colors.grey.shade100,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        children: _filters.map((f) {
          final active = current == f['key'];
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(f['label']!),
              selected: active,
              selectedColor: AppTheme.primaryColor,
              labelStyle: TextStyle(
                color: active ? Colors.white : Colors.black87,
                fontWeight: active ? FontWeight.bold : FontWeight.normal,
              ),
              onSelected: (_) => onChange(f['key']!),
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

  @override
  Widget build(BuildContext context) {
    final l = item;
    final isDitolak = l.status == 'Ditolak';

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: isDitolak
            ? BorderSide(color: Colors.red.shade400, width: 1.5)
            : BorderSide.none,
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        title: Text(
          l.nomorLaporan ?? 'Draf #${l.id}',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 2),
            Text(
              '${l.tanggal ?? '-'} · Saluran: ${l.namaSaluran ?? '-'}',
              style: const TextStyle(color: AppColors.textPrimary, fontSize: 13),
            ),
            if (l.namaKecamatan != null)
              Text(
                'Kec. ${l.namaKecamatan}',
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
          ],
        ),
        trailing: StatusBadge(status: l.status, label: l.statusLabel),
        onTap: onTap,
      ),
    );
  }
}
