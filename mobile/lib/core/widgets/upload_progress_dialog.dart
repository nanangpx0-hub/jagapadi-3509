import 'package:flutter/material.dart';

/// Menampilkan dialog progress real-time selama proses pengiriman laporan /
/// unggah foto, agar pengguna tidak menganggap aplikasi hang.
///
/// Pemakaian:
/// ```dart
/// final fotoError = await showUploadProgress(
///   context,
///   title: 'Mengirim laporan…',
///   task: (onProgress) => p.api.uploadFoto(
///     '/laporan-hama/$serverId/foto',
///     _foto!.path,
///     onSendProgress: onProgress,
///   ),
/// );
/// ```
///
/// Dialog ditutup otomatis saat task selesai (berhasil maupun gagal) dan
/// hasil task diteruskan ke pemanggil. Tidak memblokir navigasi back —
/// dialog hanya menandai status, task tetap berjalan sampai selesai.
Future<T> showUploadProgress<T>(
  BuildContext context, {
  required Future<T> Function(void Function(double progress)) task,
  String title = 'Mengirim laporan…',
  String subtitle = 'Mohon tunggu, jangan tutup aplikasi.',
}) async {
  final progress = ValueNotifier<double>(0);

  showDialog<void>(
    context: context,
    barrierDismissible: false,
    useRootNavigator: true,
    builder: (dialogContext) => PopScope(
      canPop: false,
      child: AlertDialog(
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.cloud_upload_outlined),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(dialogContext).textTheme.titleSmall,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            ValueListenableBuilder<double>(
              valueListenable: progress,
              builder: (_, value, __) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: value > 0 ? value : null,
                      minHeight: 6,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    value > 0
                        ? '${(value * 100).clamp(0, 100).round()}% terkirim'
                        : 'Menyiapkan pengiriman…',
                    style: Theme.of(dialogContext).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Text(
              subtitle,
              style: Theme.of(dialogContext).textTheme.bodySmall?.copyWith(
                    color: Theme.of(dialogContext).colorScheme.onSurfaceVariant,
                  ),
            ),
          ],
        ),
      ),
    ),
  );

  try {
    final result = await task((value) => progress.value = value);
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
    }
    return result;
  } catch (_) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
    }
    rethrow;
  } finally {
    progress.dispose();
  }
}
