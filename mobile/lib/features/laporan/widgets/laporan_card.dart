import 'package:flutter/material.dart';
import '../models/laporan_item.dart';
import '../../../core/theme.dart';
import '../../../core/widgets/status_badge.dart';

/// Card satu laporan di daftar LaporanTerpaduScreen.
/// Menampilkan jenis badge, nomor/id, tanggal, ringkasan isi, wilayah, status.
class LaporanCard extends StatelessWidget {
  final LaporanItem item;
  final VoidCallback onTap;

  const LaporanCard({
    super.key,
    required this.item,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isDitolak = item.isDitolak;
    final jenisColor = item.jenis == JenisLaporan.hama
        ? scheme.tertiary
        : scheme.primary;
    final jenisIcon = item.jenis == JenisLaporan.hama
        ? Icons.bug_report
        : Icons.water_drop;

    return Semantics(
      label:
          '${item.jenisLabel}, ${item.nomorLaporan ?? "Draf"}, ${item.statusLabel}',
      button: true,
      child: Card(
        key: ValueKey('laporan_card_${item.jenis.name}_${item.id}'),
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
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.sm,
              vertical: AppSpacing.sm,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: jenisColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: Icon(jenisIcon, color: jenisColor, size: 20),
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
                              item.nomorLaporan ?? 'Draf #${item.id}',
                              style: Theme.of(context)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.xs),
                          _JenisBadge(item.jenisLabel, jenisColor, scheme),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.xxs),
                      Text(
                        item.judulRingkas,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: scheme.onSurface,
                              fontWeight: FontWeight.w500,
                            ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (item.jenis == JenisLaporan.hama &&
                          item.tingkatKeparahan != null)
                        Text(
                          'Keparahan: ${item.tingkatKeparahan}'
                          '${item.luasSerangan != null ? ' · ${item.luasSerangan} ha' : ''}',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                        ),
                      if (item.jenis == JenisLaporan.irigasi &&
                          item.kondisiFisik != null)
                        Text(
                          'Kondisi: ${item.kondisiFisik}'
                          '${item.debitAir != null ? ' · Debit: ${item.debitAir}' : ''}',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                        ),
                      const SizedBox(height: AppSpacing.xxs),
                      Row(
                        children: [
                          if (item.namaKecamatan != null) ...[
                            Icon(
                              Icons.location_on,
                              size: 12,
                              color: scheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: 2),
                            Expanded(
                              child: Text(
                                'Kec. ${item.namaKecamatan}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                          if (item.tanggal != null)
                            Text(
                              item.tanggal!,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                    fontSize: 11,
                                  ),
                            ),
                        ],
                      ),
                      if (item.isDitolak && item.catatanVerifikasi != null)
                        Padding(
                          padding: const EdgeInsets.only(top: AppSpacing.xs),
                          child: Text(
                            'Ditolak: ${item.catatanVerifikasi}',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: scheme.error,
                                  fontWeight: FontWeight.w500,
                                ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: AppSpacing.xs),
                StatusBadge(status: item.status, label: item.statusLabel),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _JenisBadge extends StatelessWidget {
  final String label;
  final Color color;
  final ColorScheme scheme;
  const _JenisBadge(this.label, this.color, this.scheme);

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Jenis laporan: $label',
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.xs,
          vertical: 2,
        ),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w600,
            color: color,
            letterSpacing: 0.1,
          ),
        ),
      ),
    );
  }
}
