<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Database;
use PDO;

/**
 * API: Status history laporan untuk timeline di aplikasi mobile.
 *
 * GET /api/v1/laporan-hama/{id}/history
 * GET /api/v1/laporan-irigasi/{id}/history
 *
 * Ownership: petugas hanya melihat history miliknya; admin semua.
 */
class LaporanHistoryController extends BaseApiController
{
    private const TABLES = [
        'hama' => 'laporan_hama',
        'irigasi' => 'laporan_irigasi',
    ];

    public function hamaHistory(array $params = []): void
    {
        $this->respond('hama', (int) ($params['id'] ?? 0));
    }

    public function irigasiHistory(array $params = []): void
    {
        $this->respond('irigasi', (int) ($params['id'] ?? 0));
    }

    private function respond(string $type, int $laporanId): void
    {
        $user = $GLOBALS['auth_user'] ?? null;
        if ($user === null || $laporanId <= 0) {
            $this->error('Unauthenticated', 'Autentikasi diperlukan.', [], 401);
            return;
        }

        $table = self::TABLES[$type] ?? null;
        if ($table === null) {
            $this->error('ValidationError', 'Jenis laporan tidak valid.', [], 422);
            return;
        }

        try {
            $db = Database::connect();
            if ($db === null) {
                $this->error('ServerError', 'Database tidak tersedia.', [], 503);
                return;
            }

            // Ownership check: petugas hanya miliknya
            $ownerStmt = $db->prepare("SELECT user_id FROM `{$table}` WHERE id = ?");
            $ownerStmt->execute([$laporanId]);
            $ownerId = $ownerStmt->fetchColumn();

            if ($ownerId === false) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }

            if ($user['role'] !== 'admin' && (int) $ownerId !== (int) $user['id']) {
                $this->error('NotFound', 'Laporan tidak ditemukan.', [], 404);
                return;
            }

            $stmt = $db->prepare(
                'SELECT h.id, h.old_status, h.new_status, h.komentar,
                        h.created_at, u.nama_lengkap AS changed_by_name
                 FROM laporan_status_history h
                 LEFT JOIN users u ON u.id = h.changed_by
                 WHERE h.laporan_id = ?
                 ORDER BY h.created_at ASC, h.id ASC'
            );
            $stmt->execute([$laporanId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $history = array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'old_status' => $r['old_status'],
                'new_status' => $r['new_status'],
                'komentar' => $r['komentar'],
                'changed_by_name' => $r['changed_by_name'],
                'created_at' => $r['created_at'],
            ], $rows);

            $this->success($history, 'Status history');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('LaporanHistoryController: ' . $e->getMessage());
            $this->error('ServerError', 'Gagal memuat status history.', [], 500);
        }
    }
}
