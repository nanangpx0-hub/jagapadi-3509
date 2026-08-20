import 'package:flutter/foundation.dart';
import 'api_client.dart';
import 'local_db.dart';

class SyncResult {
  final int total;
  final int synced;
  final int failed;
  final String message;

  const SyncResult({
    required this.total,
    required this.synced,
    required this.failed,
    required this.message,
  });
}

/// Endpoint API per tipe laporan.
/// Bug fix: sebelumnya semua tipe non-irigasi dikirim ke /laporan-hama.
/// Sekarang setiap tipe punya mapping eksplisit; tipe tidak dikenal di-skip
/// dengan log error agar tidak mengirim data ke endpoint yang salah.
const Map<String, String> _kEndpointMap = {
  'hama': '/laporan-hama',
  'irigasi': '/laporan-irigasi',
  'pupuk': '/laporan-pupuk',
  'panen': '/laporan-panen',
  'cuaca': '/laporan-cuaca',
  'alat_sarana': '/laporan-alat-sarana',
};

/// Sinkronisasi semua draf lokal yang belum dikirim ke server.
///
/// Dipanggil otomatis dari [app.dart] saat koneksi kembali online,
/// dan secara manual dari [LocalDraftsBanner].
class SyncService {
  SyncService._();

  static bool _isSyncing = false;

  static Future<SyncResult> syncPendingDrafts(ApiClient api) async {
    if (_isSyncing) {
      return const SyncResult(
        total: 0,
        synced: 0,
        failed: 0,
        message: 'Sinkronisasi sedang berjalan',
      );
    }
    _isSyncing = true;
    try {
      return await _syncPendingDrafts(api);
    } finally {
      _isSyncing = false;
    }
  }

  static Future<SyncResult> _syncPendingDrafts(ApiClient api) async {
    final pending = await LocalDb.instance.getSyncableDrafts();
    if (pending.isEmpty) {
      return const SyncResult(
        total: 0,
        synced: 0,
        failed: 0,
        message: 'Tidak ada draf lokal yang perlu disinkronkan',
      );
    }

    int synced = 0;
    int failed = 0;

    for (final item in pending) {
      // Bug fix: validasi tipe sebelum proses — skip tipe tidak dikenal
      final path = _kEndpointMap[item.type];
      if (path == null) {
        debugPrint(
            '[SyncService] Tipe tidak dikenal: "${item.type}" — dilewati');
        failed++;
        continue;
      }

      // Idempotency: key stabil per draf — sama untuk semua retry. Backend
      // belum mendukung header ini (lihat docs/mobile/API_COMPATIBILITY.md),
      // namun dikirim agar siap saat backend menerapkannya.
      final idempotencyHeaders = item.clientOperationId == null
          ? null
          : <String, String>{
              'Idempotency-Key': item.clientOperationId!,
            };

      try {
        var serverId = item.serverId;

        if (serverId != null && item.syncState == 'pending_update') {
          final updatePayload = Map<String, dynamic>.from(item.payload)
            ..['action'] = 'draft';
          final updateResult = await api.put(
            '$path/$serverId',
            data: updatePayload,
            headers: idempotencyHeaders,
          );
          if (!updateResult.success) {
            final validation = updateResult.statusCode == 422;
            await LocalDb.instance.markFailed(
              item.id!,
              validation ? 'failed_validation' : 'pending_update',
              updateResult.message ?? 'Pembaruan draf gagal',
            );
            failed++;
            continue;
          }
          await LocalDb.instance.markSynced(item.id!, serverId);
        }

        // Record yang sudah dibuat di server tetapi fotonya tertunda tidak
        // boleh di-POST ulang karena dapat menghasilkan laporan duplikat.
        if (serverId != null) {
          if (item.fotoPath != null && item.fotoPath!.isNotEmpty) {
            // Skip upload jika server sudah mengonfirmasi foto sebelumnya.
            if (item.syncState == 'pending_photo' && item.photoSynced) {
              await LocalDb.instance.markPhotoSynced(item.id!);
              synced++;
              continue;
            }
            final photoResult = await api.uploadFoto(
              '$path/$serverId/foto',
              item.fotoPath!,
            );
            if (photoResult.success) {
              await LocalDb.instance.markPhotoSynced(item.id!);
              synced++;
            } else {
              await LocalDb.instance.markFailed(
                item.id!,
                'pending_photo',
                photoResult.message ?? 'Upload foto gagal',
              );
              failed++;
            }
          } else {
            await LocalDb.instance.markPhotoSynced(item.id!);
            synced++;
          }
          continue;
        }

        final payload = Map<String, dynamic>.from(item.payload)
          ..['action'] = 'draft';

        final res = await api.post(
          path,
          data: payload,
          headers: idempotencyHeaders,
        );

        if (res.success && res.data != null) {
          final rawData = res.data!['data'] is Map
              ? res.data!['data'] as Map<String, dynamic>
              : res.data!;
          serverId = rawData['id'] as int?;

          if (serverId != null) {
            await LocalDb.instance.markSynced(item.id!, serverId);

            // Upload foto jika ada path lokal yang valid
            if (item.fotoPath != null && item.fotoPath!.isNotEmpty) {
              try {
                final photoResult = await api.uploadFoto(
                    '$path/$serverId/foto', item.fotoPath!);
                if (photoResult.success) {
                  await LocalDb.instance.markPhotoSynced(item.id!);
                } else {
                  await LocalDb.instance.markFailed(
                    item.id!,
                    'pending_photo',
                    photoResult.message ?? 'Upload foto gagal',
                  );
                  failed++;
                  continue;
                }
              } catch (e) {
                // Foto gagal upload tapi draf sudah tersinkronisasi — tidak fatal
                debugPrint(
                    '[SyncService] Foto gagal upload untuk #${item.id}: $e');
              }
            }
            synced++;
          } else {
            debugPrint(
                '[SyncService] Server tidak mengembalikan ID untuk #${item.id}');
            failed++;
          }
        } else {
          // 422 validation error: tandai terminal agar tidak retry
          // terus-menerus, lalu catat ke log.
          if (res.statusCode == 422) {
            await LocalDb.instance.markFailed(
              item.id!,
              'failed_validation',
              res.message ?? 'Validasi server gagal',
            );
            debugPrint(
                '[SyncService] Draf #${item.id} ditolak server (422): ${res.message}');
            failed++;
          } else if (res.statusCode == 409) {
            // Konflik (duplikat / sudah ada): tandai terminal, jangan retry.
            await LocalDb.instance.markFailed(
              item.id!,
              'conflict',
              res.message ?? 'Laporan sudah ada di server (konflik)',
            );
            debugPrint(
                '[SyncService] Konflik draf #${item.id}: ${res.message}');
            failed++;
          } else {
            await LocalDb.instance.markFailed(
              item.id!,
              'pending',
              res.message ?? 'Sinkronisasi gagal',
            );
            debugPrint('[SyncService] Gagal sync #${item.id}: ${res.message}');
            failed++;
          }
        }
      } catch (e) {
        await LocalDb.instance.markFailed(
          item.id!,
          item.serverId == null ? 'pending' : 'pending_photo',
          e.toString(),
        );
        debugPrint('[SyncService] Error sync #${item.id}: $e');
        failed++;
      }
    }

    final msg = failed == 0
        ? '$synced draf lokal berhasil disinkronkan ke server'
        : '$synced berhasil, $failed gagal disinkronkan';

    return SyncResult(
      total: pending.length,
      synced: synced,
      failed: failed,
      message: msg,
    );
  }
}
