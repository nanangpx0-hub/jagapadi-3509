import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/local_drafts_banner.dart';
import '../../../core/widgets/skeleton_card.dart';
import '../../../core/widgets/status_badge.dart';
import '../providers/laporan_hama_provider.dart';

class HamaListScreen extends StatefulWidget {
  final String? initialStatus;
  const HamaListScreen({super.key, this.initialStatus});

  @override
  State<HamaListScreen> createState() => _HamaListScreenState();
}

class _HamaListScreenState extends State<HamaListScreen> {
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
      if (_scrollCtrl.position.pixels >=
          _scrollCtrl.position.maxScrollExtent - 200) {
        final p = context.read<LaporanHamaProvider>();
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
    await context.read<LaporanHamaProvider>().loadList(
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
        return 'Belum ada draf laporan hama. Buat laporan baru dengan tombol +';
      case 'Submitted':
        return 'Tidak ada laporan hama yang sedang menunggu verifikasi';
      case 'Diverifikasi':
        return 'Belum ada laporan hama yang diverifikasi';
      case 'Ditolak':
        return 'Tidak ada laporan hama yang ditolak';
      default:
        return 'Belum ada data laporan hama';
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final p = context.watch<LaporanHamaProvider>();
    return Scaffold(
      appBar: AppBar(
        title: _isSearching
            ? Semantics(
                textField: true,
                label: 'Kolom pencarian laporan hama',
                child: TextField(
                  controller: _searchCtrl,
                  autofocus: true,
                  style: TextStyle(color: scheme.onPrimary),
                  decoration: InputDecoration(
                    hintText: 'Cari laporan hama...',
                    hintStyle: TextStyle(color: scheme.onPrimary.withValues(alpha: 0.7)),
                    border: InputBorder.none,
                  ),
                  onChanged: _onSearchChanged,
                  textInputAction: TextInputAction.search,
                ),
              )
            : Semantics(
                header: true,
                child: const Text('Laporan Hama'),
              ),
        actions: [
          Semantics(
            button: true,
            label: _isSearching ? 'Tutup pencarian' : 'Cari laporan hama',
            child: IconButton(
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
          ),
          Semantics(
            button: true,
            label: 'Buat laporan hama baru',
            child: IconButton(
              icon: const Icon(Icons.add),
              onPressed: () => context.push('/hama/create').then((_) => _refresh()),
            ),
          ),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1120),
          child: Column(
            children: [
              _StatusFilter(
                current: _status,
                onChange: (s) {
                  setState(() => _status = s);
                  context.read<LaporanHamaProvider>().loadList(
                        refresh: true,
                        status: s,
                        search: _searchCtrl.text,
                      );
                },
              ),
              LocalDraftsBanner(
                type: 'hama',
                api: context.read<LaporanHamaProvider>().api,
                onSyncCompleted: _refresh,
              ),
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.md,
                  vertical: AppSpacing.xs,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Semantics(
                      label: 'Menampilkan ${p.total} laporan hama',
                      child: Text(
                        'Menampilkan ${p.total} laporan',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w500,
                            ),
                      ),
                    ),
                    if (p.loading)
                      const SizedBox.square(
                        dimension: 14,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: p.loading && p.list.isEmpty
                    ? const SkeletonListScreen(itemCount: 6)
                    : p.error != null && p.list.isEmpty
                        ? _ErrorState(
                            scheme: scheme,
                            message: p.error!,
                            onRetry: _refresh,
                          )
                        : p.list.isEmpty
                            ? _EmptyState(
                                scheme: scheme,
                                message: _emptyStateMessage(),
                                onAction: () => context
                                    .push('/hama/create')
                                    .then((_) => _refresh()),
                                isSearchOrFilter: _searchCtrl.text.isNotEmpty ||
                                    _status != 'all',
                              )
                            : RefreshIndicator(
                                onRefresh: _refresh,
                                child: ListView.builder(
                                  controller: _scrollCtrl,
                                  padding: EdgeInsets.only(
                                    top: AppSpacing.xxs,
                                    bottom:
                                        MediaQuery.paddingOf(context).bottom +
                                            AppSpacing.md,
                                  ),
                                  itemCount: p.list.length + (p.hasMore ? 1 : 0),
                                  itemBuilder: (_, i) {
                                    if (i >= p.list.length) {
                                      return const Center(
                                        child: Padding(
                                          padding: EdgeInsets.all(AppSpacing.md),
                                          child: CircularProgressIndicator(),
                                        ),
                                      );
                                    }
                                    final item = p.list[i];
                                    return _LaporanCard(
                                      item: item,
                                      onTap: () => context
                                          .push('/hama/${item.id}')
                                          .then((_) => _refresh()),
                                    );
                                  },
                                ),
                              ),
              ),
            ],
          ),
        ),
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
      height: 56,
      color: AppTheme.surfaceContainerLowLight,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.sm,
        ),
        children: _filters.map((f) {
          final active = current == f['key'];
          final label = f['label']!;
          return Padding(
            padding: const EdgeInsets.only(right: AppSpacing.xs),
            child: Semantics(
              button: true,
              selected: active,
              label: 'Filter status $label${active ? ', aktif' : ''}',
              child: _FilterChipItem(
                label: label,
                active: active,
                onTap: () => onChange(f['key']!),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _FilterChipItem extends StatelessWidget {
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _FilterChipItem({
    required this.label,
    required this.active,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: active ? scheme.primary : scheme.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        side: BorderSide(
          color: active ? scheme.primary : scheme.outlineVariant,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Container(
          constraints: const BoxConstraints(minHeight: 40),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.sm,
            vertical: AppSpacing.xs,
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              color: active ? scheme.onPrimary : scheme.onSurface,
              fontWeight: active ? FontWeight.w700 : FontWeight.w500,
              fontSize: 13,
            ),
          ),
        ),
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
    final scheme = Theme.of(context).colorScheme;
    final l = item;
    final isDitolak = l.status == 'Ditolak';

    return Semantics(
      button: true,
      label:
          '${l.nomorLaporan ?? "Draf " + l.id.toString()}, status ${l.statusLabel}, tanggal ${l.tanggal ?? "-"}',
      child: Card(
        margin: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xxs,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: isDitolak
              ? BorderSide(color: scheme.error, width: 1.5)
              : BorderSide(color: scheme.outlineVariant),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.sm,
              vertical: AppSpacing.xs,
            ),
            title: Text(
              l.nomorLaporan ?? 'Draf #${l.id}',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 2),
                Text(
                  '${l.tanggal ?? '-'} · ${l.namaOpt ?? '-'}',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurface,
                      ),
                ),
                if (l.namaKecamatan != null)
                  Text(
                    'Kec. ${l.namaKecamatan}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                  ),
              ],
            ),
            trailing: StatusBadge(status: l.status, label: l.statusLabel),
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final ColorScheme scheme;
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({
    required this.scheme,
    required this.message,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                color: scheme.errorContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.cloud_off,
                size: 48,
                color: scheme.onErrorContainer,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              'Gagal memuat data',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: scheme.onSurface,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: AppSpacing.md),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Coba Lagi'),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final ColorScheme scheme;
  final String message;
  final VoidCallback? onAction;
  final bool isSearchOrFilter;

  const _EmptyState({
    required this.scheme,
    required this.message,
    required this.onAction,
    required this.isSearchOrFilter,
  });

  @override
  Widget build(BuildContext context) {
    final icon = isSearchOrFilter ? Icons.search_off : Icons.assignment_outlined;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                icon,
                size: 48,
                color: scheme.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            if (onAction != null && !isSearchOrFilter) ...[
              const SizedBox(height: AppSpacing.md),
              FilledButton.icon(
                onPressed: onAction,
                icon: const Icon(Icons.add),
                label: const Text('Buat Laporan Baru'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
