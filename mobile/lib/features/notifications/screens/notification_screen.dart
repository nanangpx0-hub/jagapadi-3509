import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../../core/theme.dart';
import '../models/notification_item.dart';
import '../providers/notification_provider.dart';

/// Nama bulan dan hari dalam Bahasa Indonesia untuk pemformatan manual
/// (tanpa dependency baru / intl).
const List<String> _monthShortNames = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'Mei',
  'Jun',
  'Jul',
  'Agu',
  'Sep',
  'Okt',
  'Nov',
  'Des',
];

const List<String> _monthLongNames = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];

const List<String> _weekdayNames = [
  'Senin',
  'Selasa',
  'Rabu',
  'Kamis',
  'Jumat',
  'Sabtu',
  'Minggu',
];

/// Ubah timestamp string menjadi label relatif:
/// "Baru saja", "X menit lalu", "X jam lalu", "X hari lalu",
/// atau "dd MMM yyyy" untuk yang lebih lama (mis. "11 Agu 2026").
String _relativeTime(String? dateStr) {
  final dt = DateTime.tryParse(dateStr ?? '');
  if (dt == null) return dateStr ?? '';
  final diff = DateTime.now().difference(dt);
  if (diff.inMinutes < 1) return 'Baru saja';
  if (diff.inMinutes < 60) return '${diff.inMinutes} menit lalu';
  if (diff.inHours < 24) return '${diff.inHours} jam lalu';
  if (diff.inDays < 7) return '${diff.inDays} hari lalu';
  return '${dt.day} ${_monthShortNames[dt.month - 1]} ${dt.year}';
}

/// Label group tanggal: "Hari Ini", "Kemarin", nama hari, atau "dd MMMM yyyy".
String _dateGroupLabel(String? dateStr) {
  final dt = DateTime.tryParse(dateStr ?? '');
  if (dt == null) return 'Lainnya';
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(dt.year, dt.month, dt.day);
  final diffDays = today.difference(day).inDays;
  if (diffDays == 0) return 'Hari Ini';
  if (diffDays == 1) return 'Kemarin';
  if (diffDays >= 2 && diffDays < 7) {
    return _weekdayNames[dt.weekday - 1];
  }
  return '${dt.day} ${_monthLongNames[dt.month - 1]} ${dt.year}';
}

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  bool _filterUnreadOnly = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
        (_) => context.read<NotificationProvider>().load());
  }

  void _onTap(NotificationItem n) async {
    if (!n.isRead) {
      await context.read<NotificationProvider>().markRead(n.id);
    }
    if (n.entity != null && n.laporanId != null && mounted) {
      final route = n.entity == 'hama'
          ? '/hama/${n.laporanId}'
          : '/irigasi/${n.laporanId}';
      context.push(route);
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Detail laporan tidak tersedia')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final p = context.watch<NotificationProvider>();

    final items =
        _filterUnreadOnly ? p.list.where((n) => !n.isRead).toList() : p.list;

    // Group by tanggal dengan label "Hari Ini", "Kemarin", nama hari,
    // atau "dd MMMM yyyy" untuk yang lebih lama.
    final grouped = <String, List<NotificationItem>>{};
    for (final n in items) {
      grouped.putIfAbsent(_dateGroupLabel(n.createdAt), () => []).add(n);
    }

    return Scaffold(
      appBar: AppBar(
        title: Semantics(
          header: true,
          child: Text('Notifikasi'),
        ),
        actions: [
          if (p.hasUnread)
            Semantics(
              button: true,
              label: 'Tandai semua notifikasi sudah dibaca',
              child: TextButton(
                onPressed: () => p.markAllRead(),
                child: Text(
                  'Baca Semua',
                  style: TextStyle(color: scheme.onPrimary),
                ),
              ),
            ),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 720),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.md,
                  AppSpacing.sm,
                  AppSpacing.md,
                  0,
                ),
                child: Row(
                  children: [
                    ChoiceChip(
                      label: Text(
                        'Semua',
                        style: TextStyle(
                          color: !_filterUnreadOnly
                              ? scheme.onPrimary
                              : scheme.onSurface,
                        ),
                      ),
                      selected: !_filterUnreadOnly,
                      selectedColor: scheme.primary,
                      side: BorderSide(color: scheme.outline),
                      onSelected: (_) =>
                          setState(() => _filterUnreadOnly = false),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    ChoiceChip(
                      label: Text(
                        'Belum Dibaca',
                        style: TextStyle(
                          color: _filterUnreadOnly
                              ? scheme.onPrimary
                              : scheme.onSurface,
                        ),
                      ),
                      selected: _filterUnreadOnly,
                      selectedColor: scheme.primary,
                      side: BorderSide(color: scheme.outline),
                      onSelected: (_) =>
                          setState(() => _filterUnreadOnly = true),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: p.loading
                    ? const Center(child: CircularProgressIndicator())
                    : items.isEmpty
                        ? _buildEmptyState(scheme)
                        : RefreshIndicator(
                            onRefresh: () => p.load(),
                            child: ListView(
                              padding: EdgeInsets.only(
                                top: AppSpacing.sm,
                                bottom: MediaQuery.paddingOf(context).bottom +
                                    AppSpacing.md,
                              ),
                              children: [
                                for (final entry in grouped.entries) ...[
                                  Padding(
                                    padding: const EdgeInsets.fromLTRB(
                                        16, 16, 16, 8),
                                    child: Text(
                                      entry.key.toUpperCase(),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: scheme.onSurfaceVariant,
                                            fontWeight: FontWeight.bold,
                                          ),
                                    ),
                                  ),
                                  for (final n in entry.value)
                                    Dismissible(
                                      key: ValueKey('notif_${n.id}'),
                                      direction: DismissDirection.startToEnd,
                                      background: Container(
                                        color: const Color(0xFF2E7D32),
                                        alignment: Alignment.centerLeft,
                                        padding: const EdgeInsets.only(
                                          left: AppSpacing.lg,
                                        ),
                                        child: const Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(
                                              Icons.mail_outline,
                                              color: Colors.white,
                                            ),
                                            SizedBox(width: AppSpacing.xs),
                                            Text(
                                              'Tandai Dibaca',
                                              style: TextStyle(
                                                color: Colors.white,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      confirmDismiss: (_) async {
                                        if (!n.isRead) {
                                          if (!context.mounted) return false;
                                          await context
                                              .read<NotificationProvider>()
                                              .markRead(n.id);
                                        }
                                        return false;
                                      },
                                      child: _NotificationTile(
                                        n: n,
                                        scheme: scheme,
                                        onTap: () => _onTap(n),
                                      ),
                                    ),
                                ],
                              ],
                            ),
                          ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState(ColorScheme scheme) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.notifications_none,
                size: 48,
                color: scheme.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              _filterUnreadOnly
                  ? 'Tidak ada notifikasi belum dibaca'
                  : 'Belum ada notifikasi',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: scheme.onSurface,
                    fontWeight: FontWeight.w600,
                  ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              _filterUnreadOnly
                  ? 'Semua notifikasi sudah dibaca.'
                  : 'Notifikasi laporan dan pembaruan akan muncul di sini.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  final NotificationItem n;
  final ColorScheme scheme;
  final VoidCallback onTap;

  const _NotificationTile({
    required this.n,
    required this.scheme,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final unread = !n.isRead;
    final lower = n.title.toLowerCase();

    // Visual pembeda per tipe notifikasi berdasarkan title.
    Color? stripColor;
    Color? avatarBg;
    Color? avatarFg;
    IconData avatarIcon;
    if (lower.contains('diverifikasi')) {
      stripColor = const Color(0xFF2E7D32);
      avatarBg = const Color(0xFFC8E6C9);
      avatarFg = const Color(0xFF2E7D32);
      avatarIcon = Icons.check_circle;
    } else if (lower.contains('ditolak')) {
      stripColor = const Color(0xFFD32F2F);
      avatarBg = const Color(0xFFFFCDD2);
      avatarFg = const Color(0xFFD32F2F);
      avatarIcon = Icons.cancel;
    } else if (lower.contains('diarsipkan')) {
      stripColor = const Color(0xFF607D8B);
      avatarBg = const Color(0xFFCFD8DC);
      avatarFg = const Color(0xFF607D8B);
      avatarIcon = Icons.archive;
    } else {
      avatarIcon =
          unread ? Icons.notifications_active : Icons.notifications_none;
    }

    return Semantics(
      button: true,
      label:
          '${unread ? 'Belum dibaca. ' : ''}${n.title}. ${n.body.isNotEmpty ? n.body : ''}. ${n.createdAt ?? ''}',
      child: Container(
        margin: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xxs,
        ),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (stripColor != null) Container(width: 4, color: stripColor),
            Expanded(
              child: Card(
                margin: EdgeInsets.zero,
                color: unread
                    ? scheme.primaryContainer.withValues(alpha: 0.12)
                    : null,
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: AppSpacing.sm,
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CircleAvatar(
                          radius: 24,
                          backgroundColor: avatarBg ??
                              (unread
                                  ? scheme.primaryContainer
                                  : scheme.surfaceContainerHighest),
                          child: Icon(
                            avatarIcon,
                            color: avatarFg ??
                                (unread
                                    ? scheme.onPrimaryContainer
                                    : scheme.onSurfaceVariant),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      n.title,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleSmall
                                          ?.copyWith(
                                            fontWeight: unread
                                                ? FontWeight.w700
                                                : FontWeight.w500,
                                            color: scheme.onSurface,
                                          ),
                                    ),
                                  ),
                                  if (unread)
                                    Container(
                                      width: 8,
                                      height: 8,
                                      margin: const EdgeInsets.only(
                                          left: AppSpacing.xs),
                                      decoration: BoxDecoration(
                                        color: scheme.primary,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                ],
                              ),
                              if (n.body.isNotEmpty) ...[
                                const SizedBox(height: AppSpacing.xxs),
                                Text(
                                  n.body,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                      ),
                                ),
                              ],
                              if (n.createdAt != null) ...[
                                const SizedBox(height: AppSpacing.xxs),
                                Text(
                                  _relativeTime(n.createdAt),
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                        fontSize: 11,
                                      ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
