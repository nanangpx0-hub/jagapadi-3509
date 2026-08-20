import 'package:flutter/material.dart';
import '../theme.dart';
import '../api_client.dart';
import '../local_db.dart';
import '../sync_service.dart';

class LocalDraftsBanner extends StatefulWidget {
  final String type;
  final ApiClient api;
  final VoidCallback onSyncCompleted;

  const LocalDraftsBanner({
    super.key,
    required this.type,
    required this.api,
    required this.onSyncCompleted,
  });

  @override
  State<LocalDraftsBanner> createState() => _LocalDraftsBannerState();
}

class _LocalDraftsBannerState extends State<LocalDraftsBanner> {
  List<LocalDraftItem> _unsyncedList = [];
  bool _loading = false;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    _loadUnsynced();
  }

  Future<void> _loadUnsynced() async {
    setState(() => _loading = true);
    final items = await LocalDb.instance.getUnsyncedDrafts(widget.type);
    if (mounted) {
      setState(() {
        _unsyncedList = items;
        _loading = false;
      });
    }
  }

  Future<void> _triggerSync() async {
    setState(() => _syncing = true);
    final res = await SyncService.syncPendingDrafts(widget.api);
    if (mounted) {
      setState(() => _syncing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message)),
      );
      await _loadUnsynced();
      widget.onSyncCompleted();
    }
  }

  Future<void> _deleteLocalDraft(LocalDraftItem item) async {
    final scheme = Theme.of(context).colorScheme;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Draf Lokal?'),
        content: const Text(
          'Data draf ini belum tersinkronisasi ke server. Data akan hilang permanen.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(
              backgroundColor: scheme.errorContainer,
              foregroundColor: scheme.onErrorContainer,
            ),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );

    if (confirm == true && item.id != null) {
      await LocalDb.instance.deleteDraft(item.id!);
      await _loadUnsynced();
      widget.onSyncCompleted();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading || _unsyncedList.isEmpty) {
      return const SizedBox.shrink();
    }

    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      container: true,
      label:
          '${_unsyncedList.length} draf lokal belum tersinkronisasi. Tekan tombol Sinkronkan Sekarang untuk mengunggah.',
      child: Card(
        margin: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs,
        ),
        color: AppTheme.warningContainer,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: BorderSide(
            color: AppTheme.onWarningContainer.withValues(alpha: 0.3),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.cloud_off,
                    color: AppTheme.onWarningContainer,
                  ),
                  const SizedBox(width: AppSpacing.xs),
                  Expanded(
                    child: Text(
                      '${_unsyncedList.length} draf lokal belum tersinkronisasi',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppTheme.onWarningContainer,
                      ),
                    ),
                  ),
                  Semantics(
                    button: true,
                    label: _syncing
                        ? 'Sedang menyinkronkan draf'
                        : 'Sinkronkan ${_unsyncedList.length} draf sekarang',
                    child: FilledButton.icon(
                      onPressed: _syncing ? null : _triggerSync,
                      icon: _syncing
                          ? SizedBox.square(
                              dimension: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: scheme.onPrimary,
                              ),
                            )
                          : const Icon(Icons.sync, size: 18),
                      label: Text(_syncing ? 'Sinkronisasi…' : 'Sinkronkan'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _unsyncedList.length,
                separatorBuilder: (_, __) => Divider(
                  height: 1,
                  color:
                      AppTheme.onWarningContainer.withValues(alpha: 0.12),
                ),
                itemBuilder: (ctx, idx) {
                  final draft = _unsyncedList[idx];
                  final title = draft.payload['nama_saluran'] ??
                      draft.payload['lokasi'] ??
                      'Draf Lokal #${draft.id}';
                  final dateStr = draft.createdAt.split('T').first;
                  return Padding(
                    padding:
                        const EdgeInsets.symmetric(vertical: AppSpacing.xs),
                    child: Row(
                      children: [
                        Expanded(
                          child: Semantics(
                            label: 'Draf $title, tanggal $dateStr',
                            child: Text(
                              '$title ($dateStr)',
                              style: TextStyle(
                                fontSize: 12,
                                color: AppTheme.onWarningContainer,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                        Semantics(
                          button: true,
                          label: 'Hapus draf $title',
                          child: IconButton(
                            icon: Icon(
                              Icons.delete_outline,
                              size: 20,
                              color: scheme.error,
                            ),
                            onPressed: () => _deleteLocalDraft(draft),
                            tooltip: 'Hapus draf',
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
