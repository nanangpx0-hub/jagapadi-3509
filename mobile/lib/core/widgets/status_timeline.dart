import 'package:flutter/material.dart';

class StatusTimeline extends StatelessWidget {
  final String status;
  final String? createdAt;
  final String? verifiedAt;
  final String? catatanVerifikasi;

  const StatusTimeline({
    super.key,
    required this.status,
    this.createdAt,
    this.verifiedAt,
    this.catatanVerifikasi,
  });

  int get _currentIndex {
    switch (status) {
      case 'Draf': return 0;
      case 'Submitted': return 1;
      case 'Diverifikasi':
      case 'Ditolak': return 2;
      case 'Diarsipkan': return 3;
      default: return 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDitolak = status == 'Ditolak';

    final steps = [
      {'title': 'Draf Dibuat', 'date': createdAt},
      {'title': 'Dikirim ke Admin', 'date': status != 'Draf' ? createdAt : null},
      {
        'title': isDitolak ? 'Ditolak Admin' : 'Diverifikasi Admin',
        'date': verifiedAt,
      },
      {'title': 'Diarsipkan', 'date': status == 'Diarsipkan' ? verifiedAt : null},
    ];

    final currentIdx = _currentIndex;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Status & Riwayat Progres',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 16),
            ...List.generate(steps.length, (idx) {
              final step = steps[idx];
              final isPassed = idx < currentIdx;
              final isCurrent = idx == currentIdx;
              final isLast = idx == steps.length - 1;

              Color dotColor = Colors.grey.shade400;
              if (isPassed) dotColor = Colors.green.shade700;
              if (isCurrent) {
                dotColor = isDitolak ? Colors.red.shade700 : Theme.of(context).primaryColor;
              }

              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Container(
                        width: 20,
                        height: 20,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: dotColor,
                        ),
                        child: isPassed
                            ? const Icon(Icons.check, size: 12, color: Colors.white)
                            : isCurrent
                                ? const Icon(Icons.circle, size: 8, color: Colors.white)
                                : null,
                      ),
                      if (!isLast)
                        Container(
                          width: 2,
                          height: 36,
                          color: isPassed ? Colors.green.shade700 : Colors.grey.shade300,
                        ),
                    ],
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            step['title'] as String,
                            style: TextStyle(
                              fontWeight: isCurrent ? FontWeight.bold : FontWeight.w500,
                              fontSize: 14,
                              color: isCurrent
                                  ? (isDitolak ? Colors.red.shade700 : Colors.black87)
                                  : Colors.black87,
                            ),
                          ),
                          if (step['date'] != null)
                            Text(
                              step['date'] as String,
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                            ),
                          if (isCurrent && isDitolak && catatanVerifikasi != null && catatanVerifikasi!.isNotEmpty)
                            Container(
                              margin: const EdgeInsets.only(top: 6),
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.red.shade50,
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: Colors.red.shade200),
                              ),
                              child: Text(
                                'Alasan Penolakan: $catatanVerifikasi',
                                style: TextStyle(
                                  color: Colors.red.shade900,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          const SizedBox(height: 12),
                        ],
                      ),
                    ),
                  ),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }
}
