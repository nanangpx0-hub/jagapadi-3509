import 'package:flutter/material.dart';
import '../../features/home/providers/dashboard_provider.dart';

class StatsSummaryCard extends StatelessWidget {
  final String title;
  final DashboardStats? stats;
  final bool loading;
  final VoidCallback onRefresh;
  final VoidCallback? onTapAktif;
  final VoidCallback? onTapDraf;
  final VoidCallback? onTapDitolak;

  const StatsSummaryCard({
    super.key,
    required this.title,
    required this.stats,
    required this.loading,
    required this.onRefresh,
    this.onTapAktif,
    this.onTapDraf,
    this.onTapDitolak,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.refresh, size: 20),
                  onPressed: loading ? null : onRefresh,
                  tooltip: 'Muat ulang statistik',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),
            const Divider(height: 20),
            if (loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Center(
                  child: SizedBox(
                    height: 24,
                    width: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
              )
            else
              Row(
                children: [
                  Expanded(
                    child: _StatColumn(
                      label: 'Aktif',
                      value: stats?.totalAktif ?? 0,
                      color: Colors.green.shade700,
                      onTap: onTapAktif,
                    ),
                  ),
                  Container(height: 36, width: 1, color: Colors.grey.shade300),
                  Expanded(
                    child: _StatColumn(
                      label: 'Draf',
                      value: stats?.totalDraf ?? 0,
                      color: Colors.orange.shade700,
                      onTap: onTapDraf,
                    ),
                  ),
                  Container(height: 36, width: 1, color: Colors.grey.shade300),
                  Expanded(
                    child: _StatColumn(
                      label: 'Ditolak',
                      value: stats?.totalDitolak ?? 0,
                      color: Colors.red.shade700,
                      onTap: onTapDitolak,
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _StatColumn extends StatelessWidget {
  final String label;
  final int value;
  final Color color;
  final VoidCallback? onTap;

  const _StatColumn({
    required this.label,
    required this.value,
    required this.color,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(
          children: [
            Text(
              '$value',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                color: Colors.grey,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
