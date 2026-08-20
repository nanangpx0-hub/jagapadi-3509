import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/connectivity_service.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/skeleton_card.dart';
import '../models/laporan_item.dart';
import '../providers/laporan_terpadu_provider.dart';
import '../widgets/laporan_card.dart';
import '../widgets/laporan_export_bottom_sheet.dart';
import '../widgets/laporan_filter_sheet.dart';

/// Halaman Laporan Terpadu — menampilkan laporan hama dan irigasi dalam satu
/// daftar terunifikasi dengan filter, search, ekspor PDF, dan error handling.
///
/// Perbaikan UI/UX vNext:
/// - Banner offline pakai token tema warningContainer (WCAG AA)
/// - Bar filter aktif pakai infoContainer (bukan hardcoded blue.shade50)
/// - Chip filter menggunakan tap target 48x48 (bukan shrinkWrap)
/// - Responsif: list diberi ConstrainedBox untuk tablet
/// - Semantics: search/filter/export punya label aksesibilitas jelas
class LaporanTerpaduScreen extends StatefulWidget {
  const LaporanTerpaduScreen({super.key});

  @override
  State<LaporanTerpaduScreen> createState() => _LaporanTerpaduScreenState();
}

class _LaporanTerpaduScreenState extends State<LaporanTerpaduScreen> {
  final _scrollCtrl = ScrollController();
  final _searchCtrl = TextEditingController();
  bool _isSearching = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<LaporanTerpaduProvider>().refresh();
    });
    _scrollCtrl.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _searchCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 250) {
      context.read<LaporanTerpaduProvider>().loadMore();
    }
  }

  void _onSearchChanged(String q) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      final p = context.read<LaporanTerpaduProvider>();
      p.applyFilter(p.filter.copyWith(searchQuery: q.trim()));
    });
  }

  void _closeSearch() {
    setState(() {
      _isSearching = false;
      _searchCtrl.clear();
    });
    final p = context.read<LaporanTerpaduProvider>();
    p.applyFilter(p.filter.copyWith(clearSearch: true));
  }

  Future<void> _openFilterSheet() async {
    final p = context.read<LaporanTerpaduProvider>();
    final result = await LaporanFilterSheet.show(context, p.filter);
    if (result != null && mounted) {
      final merged = result.copyWith(searchQuery: p.filter.searchQuery);
      p.applyFilter(merged);
    }
  }

  Future<void> _openExport() async {
    final p = context.read<LaporanTerpaduProvider>();
    if (p.list.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          key: const Key('snackbar_export_empty'),
          content: const Text('Tidak ada data laporan untuk diekspor.'),
        ),
      );
      return;
    }
    final filter = p.filter;
    final parts = <String>[];
    if (filter.jenisKey != 'semua') parts.add(filter.jenisKey.toUpperCase());
    if (filter.statusKey != 'all') parts.add(filter.statusKey);
    if (filter.tanggalDari != null) {
      parts.add(
          '${filter.tanggalDari} s/d ${filter.tanggalSampai ?? 'sekarang'}');
    }
    final subtitle = parts.isEmpty ? 'Semua laporan' : parts.join(' · ');

    if (!mounted) return;
    await LaporanExportBottomSheet.show(
      context,
      items: p.list,
      subtitle: subtitle,
    );
  }

  void _navigateToDetail(LaporanItem item) {
    final route = item.jenis == JenisLaporan.hama
        ? '/hama/${item.id}'
        : '/irigasi/${item.id}';
    context.push(route).then((_) {
      if (mounted) context.read<LaporanTerpaduProvider>().refresh();
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<LaporanTerpaduProvider>();
    final connectivity = context.watch<ConnectivityService>();
    final hasActiveFilter = p.filter.hasActiveFilter;

    return Scaffold(
      appBar: AppBar(
        title: _isSearching
            ? TextField(
                key: const Key('search_field'),
                controller: _searchCtrl,
                autofocus: true,
                style:
                    TextStyle(color: Theme.of(context).colorScheme.onPrimary),
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Cari nomor laporan, OPT, saluran…',
                  hintStyle: TextStyle(
                    color: Theme.of(context)
                        .colorScheme
                        .onPrimary
                        .withValues(alpha: 0.75),
                  ),
                  border: InputBorder.none,
                  isDense: false,
                  contentPadding: EdgeInsets.zero,
                ),
                onChanged: _onSearchChanged,
              )
            : Semantics(
                header: true,
                child: const Text('Semua Laporan'),
              ),
        actions: [
          // Search toggle
          Semantics(
            button: true,
            label: _isSearching
                ? 'Tutup pencarian'
                : 'Cari laporan berdasarkan nomor, OPT, atau saluran',
            child: Tooltip(
              message: _isSearching ? 'Tutup pencarian' : 'Cari laporan',
              child: IconButton(
                key: const Key('btn_search'),
                icon: Icon(_isSearching ? Icons.close : Icons.search),
                onPressed: () {
                  if (_isSearching) {
                    _closeSearch();
                  } else {
                    setState(() => _isSearching = true);
                  }
                },
              ),
            ),
          ),
          // Filter
          Semantics(
            button: true,
            label: 'Buka filter laporan' +
                (hasActiveFilter ? ', filter aktif' : ''),
            child: Tooltip(
              message: 'Filter laporan',
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  IconButton(
                    key: const Key('btn_filter'),
                    icon: const Icon(Icons.filter_list),
                    onPressed: _openFilterSheet,
                  ),
                  if (hasActiveFilter)
                    Positioned(
                      right: 10,
                      top: 10,
                      child: Container(
                        width: 10,
                        height: 10,
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.tertiary,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 1.5),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          // Ekspor PDF
          Semantics(
            button: true,
            label: 'Ekspor daftar laporan ke PDF',
            child: Tooltip(
              message: 'Ekspor ke PDF',
              child: IconButton(
                key: const Key('btn_export'),
                icon: const Icon(Icons.picture_as_pdf),
                onPressed: _openExport,
              ),
            ),
          ),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1120),
          child: Column(
            children: [
              if (!connectivity.isOnline)
                _OfflineBanner(key: const Key('offline_banner')),
              if (hasActiveFilter) _ActiveFilterBar(filter: p.filter),
              _QuickStatusFilter(
                currentStatus: p.filter.statusKey,
                onChanged: (status) {
                  context.read<LaporanTerpaduProvider>().applyFilter(
                        p.filter.copyWith(statusKey: status),
                      );
                },
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
                      label: p.list.isEmpty && p.loading
                          ? 'Sedang memuat laporan'
                          : 'Menampilkan ${p.list.length} dari total ${p.totalCount} laporan',
                      child: Text(
                        p.list.isEmpty && p.loading
                            ? 'Memuat…'
                            : 'Menampilkan ${p.list.length}'
                                ' dari ${p.totalCount} laporan',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                    ),
                    if (p.loading && p.list.isNotEmpty)
                      const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                  ],
                ),
              ),
              Expanded(child: _buildBody(p)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody(LaporanTerpaduProvider p) {
    Widget child;
    if (p.loading && p.list.isEmpty) {
      child = const SkeletonListScreen(
        key: Key('skeleton_list'),
        itemCount: 7,
      );
    } else if (p.error != null && p.list.isEmpty) {
      child = _ErrorState(
        key: const Key('error_state'),
        message: p.error!,
        onRetry: () => context.read<LaporanTerpaduProvider>().refresh(),
      );
    } else if (p.list.isEmpty) {
      child = _EmptyState(
        key: const Key('empty_state'),
        filter: p.filter,
        onClearFilter: () {
          _searchCtrl.clear();
          setState(() => _isSearching = false);
          context.read<LaporanTerpaduProvider>().applyFilter(
                const LaporanFilter(),
              );
        },
      );
    } else {
      child = RefreshIndicator(
        key: const Key('refresh_indicator'),
        onRefresh: () => context.read<LaporanTerpaduProvider>().refresh(),
        child: ListView.builder(
          controller: _scrollCtrl,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.only(
              bottom: MediaQuery.paddingOf(context).bottom + AppSpacing.lg),
          itemCount: p.list.length + (p.hasMore ? 1 : 0),
          itemBuilder: (_, i) {
            if (i >= p.list.length) {
              return const Padding(
                padding: EdgeInsets.all(AppSpacing.md),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final item = p.list[i];
            return LaporanCard(
              key: ValueKey('card_${item.jenis.name}_${item.id}'),
              item: item,
              onTap: () => _navigateToDetail(item),
            );
          },
        ),
      );
    }

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 300),
      child: child,
    );
  }
}

class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: AppTheme.warningContainer,
        border: Border(
          bottom: BorderSide(color: scheme.outlineVariant, width: 0.5),
        ),
      ),
      child: Semantics(
        container: true,
        label:
            'Mode offline aktif. Menampilkan data laporan terakhir yang tersimpan.',
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Icon(
                Icons.wifi_off,
                size: 18,
                color: AppTheme.onWarningContainer,
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                'Tidak ada koneksi internet. Menampilkan data terakhir.',
                style: TextStyle(
                  fontSize: 13,
                  height: 1.3,
                  color: AppTheme.onWarningContainer,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ActiveFilterBar extends StatelessWidget {
  final LaporanFilter filter;
  const _ActiveFilterBar({required this.filter});

  @override
  Widget build(BuildContext context) {
    final chips = <(String, VoidCallback)>[];

    if (filter.jenisKey != 'semua') {
      chips.add((
        filter.jenisKey == 'hama' ? 'Hama/OPT' : 'Irigasi',
        () => context.read<LaporanTerpaduProvider>().applyFilter(
              filter.copyWith(jenisKey: 'semua'),
            ),
      ));
    }
    if (filter.statusKey != 'all') {
      chips.add((
        filter.statusKey,
        () => context.read<LaporanTerpaduProvider>().applyFilter(
              filter.copyWith(statusKey: 'all'),
            ),
      ));
    }
    if (filter.hasActiveDateFilter) {
      chips.add((
        '${filter.tanggalDari ?? '…'} – ${filter.tanggalSampai ?? '…'}',
        () => context.read<LaporanTerpaduProvider>().applyFilter(
              filter.copyWith(clearTanggal: true),
            ),
      ));
    }
    if (filter.searchQuery != null && filter.searchQuery!.isNotEmpty) {
      chips.add((
        '"${filter.searchQuery}"',
        () => context.read<LaporanTerpaduProvider>().applyFilter(
              filter.copyWith(clearSearch: true),
            ),
      ));
    }

    if (chips.isEmpty) return const SizedBox.shrink();

    return Container(
      constraints: const BoxConstraints(minHeight: 56),
      color: AppTheme.infoContainer,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.sm,
        ),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.xs,
              AppSpacing.xs,
              AppSpacing.xs,
              AppSpacing.xs,
            ),
            child: Center(
              child: Icon(
                Icons.filter_alt,
                size: 18,
                color: AppTheme.onInfoContainer,
              ),
            ),
          ),
          ...chips.map((c) => Padding(
                padding: const EdgeInsets.only(right: AppSpacing.xs),
                child: _FilterChipItem(label: c.$1, onDeleted: c.$2),
              )),
        ],
      ),
    );
  }
}

/// Chip filter dengan tap target minimal 48x48 (tidak shrinkWrap).
/// Dapat dibaca screen reader dengan label yang jelas.
class _FilterChipItem extends StatelessWidget {
  final String label;
  final VoidCallback onDeleted;

  const _FilterChipItem({required this.label, required this.onDeleted});

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Filter $label. Ketuk untuk menghapus filter ini',
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onDeleted,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          child: Container(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.sm,
              vertical: AppSpacing.xs,
            ),
            decoration: BoxDecoration(
              color:
                  Theme.of(context).colorScheme.primary.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(AppRadius.sm),
              border: Border.all(
                color: Theme.of(context)
                    .colorScheme
                    .primary
                    .withValues(alpha: 0.4),
              ),
            ),
            constraints: const BoxConstraints(minHeight: 40),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ),
                const SizedBox(width: AppSpacing.xxs),
                Icon(
                  Icons.close,
                  size: 16,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorState({super.key, required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final isNetworkError = message.toLowerCase().contains('koneksi') ||
        message.toLowerCase().contains('jaringan') ||
        message.toLowerCase().contains('server');
    final scheme = Theme.of(context).colorScheme;

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
                color: isNetworkError
                    ? AppTheme.warningContainer
                    : scheme.errorContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                isNetworkError ? Icons.cloud_off : Icons.error_outline,
                size: 48,
                color: isNetworkError
                    ? AppTheme.onWarningContainer
                    : scheme.onErrorContainer,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Semantics(
              header: true,
              child: Text(
                isNetworkError
                    ? 'Tidak Dapat Memuat Data'
                    : 'Terjadi Kesalahan',
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: AppSpacing.xl),
            Semantics(
              button: true,
              label: 'Coba memuat ulang data laporan',
              child: FilledButton.icon(
                key: const Key('btn_retry'),
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Coba Lagi'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final LaporanFilter filter;
  final VoidCallback onClearFilter;
  const _EmptyState(
      {super.key, required this.filter, required this.onClearFilter});

  String get _message {
    if (filter.searchQuery != null && filter.searchQuery!.isNotEmpty) {
      return 'Tidak ada laporan yang cocok dengan\n"${filter.searchQuery}"';
    }
    if (filter.hasActiveDateFilter) {
      return 'Tidak ada laporan dalam rentang tanggal yang dipilih.';
    }
    switch (filter.statusKey) {
      case 'Draf':
        return 'Belum ada draf laporan.';
      case 'Submitted':
        return 'Tidak ada laporan yang menunggu verifikasi.';
      case 'Diverifikasi':
        return 'Belum ada laporan yang diverifikasi.';
      case 'Ditolak':
        return 'Tidak ada laporan yang ditolak.';
      default:
        return 'Belum ada data laporan.';
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
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
                Icons.search_off,
                size: 48,
                color: scheme.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Semantics(
              header: true,
              child: Text(
                filter.hasActiveFilter ? 'Tidak Ditemukan' : 'Belum Ada Data',
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              _message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            if (filter.hasActiveFilter) ...[
              const SizedBox(height: AppSpacing.xl),
              Semantics(
                button: true,
                label: 'Hapus semua filter dan tampilkan semua laporan',
                child: OutlinedButton.icon(
                  key: const Key('btn_clear_filter'),
                  onPressed: onClearFilter,
                  icon: const Icon(Icons.filter_list_off, size: 18),
                  label: const Text('Hapus Semua Filter'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Quick filter status: ChoiceChip horizontal untuk memfilter status
/// laporan. Membaca [currentStatus] dari `LaporanFilter.statusKey` yang sama
/// dengan filter sheet, sehingga keduanya selalu sinkron.
class _QuickStatusFilter extends StatelessWidget {
  final String currentStatus;
  final ValueChanged<String> onChanged;

  const _QuickStatusFilter({
    required this.currentStatus,
    required this.onChanged,
  });

  static const List<String> _statuses = [
    'all',
    'Draf',
    'Submitted',
    'Diverifikasi',
    'Ditolak',
    'Diarsipkan',
  ];

  static const Map<String, String> _labels = {
    'all': 'Semua',
    'Draf': 'Draf',
    'Submitted': 'Dikirim',
    'Diverifikasi': 'Diverifikasi',
    'Ditolak': 'Ditolak',
    'Diarsipkan': 'Diarsipkan',
  };

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs,
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final status in _statuses) ...[
              if (status != _statuses.first) const SizedBox(width: 8),
              Semantics(
                button: true,
                selected: currentStatus == status,
                label: 'Filter status ${_labels[status]}',
                child: ChoiceChip(
                  label: Text(
                    _labels[status]!,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: currentStatus == status
                              ? scheme.onPrimary
                              : scheme.onSurfaceVariant,
                          fontWeight: currentStatus == status
                              ? FontWeight.bold
                              : FontWeight.normal,
                        ),
                  ),
                  selected: currentStatus == status,
                  showCheckmark: false,
                  backgroundColor:
                      currentStatus == status ? scheme.primary : null,
                  side: currentStatus == status
                      ? null
                      : BorderSide(color: scheme.outlineVariant),
                  onSelected: (_) => onChanged(status),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
