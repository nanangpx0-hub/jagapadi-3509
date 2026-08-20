<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class LaporanHama extends Model
{
    private const ALLOWED_FILTERS = ['status', 'tanggal_from', 'tanggal_to', 'kabupaten_id', 'kecamatan_id', 'desa_id', 'master_opt_id', 'tingkat_keparahan', 'q'];
    private const ALLOWED_ORDER_COLS = [
        'lh.tanggal', 'lh.created_at', 'lh.status',
        'lh.tingkat_keparahan', 'lh.lokasi', 'lh.luas_serangan',
        'mo.nama_opt', 'u.nama_lengkap',
    ];

    protected static function table(): string
    {
        return 'laporan_hama';
    }

    public static function findAccessibleById(int $id, array $currentUser): ?array
    {
        $pdo = self::db();
        $sql = "SELECT lh.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                       v.nama_lengkap AS verifikator_nama,
                       mo.nama_opt, mo.jenis AS opt_jenis,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
                FROM `laporan_hama` lh
                LEFT JOIN `users` u ON u.id = lh.user_id
                LEFT JOIN `users` v ON v.id = lh.verified_by
                LEFT JOIN `master_opt` mo ON mo.id = lh.master_opt_id
                LEFT JOIN `master_kabupaten` mk ON mk.id = lh.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = lh.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = lh.desa_id
                WHERE lh.id = ?";

        if ($currentUser['role'] === 'petugas') {
            $sql .= " AND lh.user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, (int) $currentUser['id']]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function listForPetugas(int $userId, array $filters, int $page, int $limit): array
    {
        $pdo = self::db();
        $conditions = ['lh.user_id = ?'];
        $params = [$userId];

        $sql = self::buildListQuery($conditions, $params, $filters);

        $countSql = preg_replace('/^SELECT lh\.\*.*?\bFROM\b/s', 'SELECT COUNT(*) FROM', $sql);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return ['data' => $data, 'total' => $total];
    }

    public static function listForAdmin(array $filters, int $page, int $limit): array
    {
        $pdo = self::db();
        $conditions = [];
        $params = [];

        $sql = self::buildListQuery($conditions, $params, $filters);

        $countSql = preg_replace('/^SELECT lh\.\*.*?\bFROM\b/s', 'SELECT COUNT(*) FROM', $sql);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return ['data' => $data, 'total' => $total];
    }

    public static function updateStatusAndVerification(int $id, string $status, ?int $verifiedBy, ?string $catatanVerifikasi): bool
    {
        $pdo = self::db();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE `laporan_hama`
            SET `status` = ?,
                `verified_by` = ?,
                `verified_at` = ?,
                `catatan_verifikasi` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$status, $verifiedBy, $now, $catatanVerifikasi, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function archiveVerified(int $id): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare(
            "UPDATE `laporan_hama`
             SET `status` = 'Diarsipkan'
             WHERE `id` = ? AND `status` = 'Diverifikasi'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    public static function resetVerification(int $id): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE `laporan_hama`
            SET `verified_by` = NULL,
                `verified_at` = NULL,
                `catatan_verifikasi` = NULL
            WHERE `id` = ?
        ");
        return $stmt->execute([$id]);
    }

    public static function deleteDraft(int $id, int $userId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `laporan_hama` WHERE `id` = ? AND `user_id` = ? AND `status` = 'Draf'");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    private static function buildListQuery(array $conditions, array &$params, array $filters): string
    {
        $sql = "SELECT lh.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                       v.nama_lengkap AS verifikator_nama,
                       mo.nama_opt, mo.jenis AS opt_jenis,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
                FROM `laporan_hama` lh
                LEFT JOIN `users` u ON u.id = lh.user_id
                LEFT JOIN `users` v ON v.id = lh.verified_by
                LEFT JOIN `master_opt` mo ON mo.id = lh.master_opt_id
                LEFT JOIN `master_kabupaten` mk ON mk.id = lh.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = lh.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = lh.desa_id";

        $where = '';

        foreach (self::ALLOWED_FILTERS as $key) {
            if (!isset($filters[$key]) || (is_string($filters[$key]) && trim($filters[$key]) === '')) {
                continue;
            }
            $val = $filters[$key];

            switch ($key) {
                case 'status':
                    $statuses = explode(',', (string) $val);
                    $statuses = array_filter(array_map('trim', $statuses), static fn($s) => $s !== '');
                    if (count($statuses) > 0) {
                        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
                        $conditions[] = "lh.status IN ($placeholders)";
                        foreach ($statuses as $s) {
                            $params[] = $s;
                        }
                    }
                    break;
                case 'tanggal_from':
                    $conditions[] = 'lh.tanggal >= ?';
                    $params[] = $val;
                    break;
                case 'tanggal_to':
                    $conditions[] = 'lh.tanggal <= ?';
                    $params[] = $val;
                    break;
                case 'kabupaten_id':
                    $conditions[] = 'lh.kabupaten_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'kecamatan_id':
                    $conditions[] = 'lh.kecamatan_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'desa_id':
                    $conditions[] = 'lh.desa_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'master_opt_id':
                    $conditions[] = 'lh.master_opt_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'tingkat_keparahan':
                    $conditions[] = 'lh.tingkat_keparahan = ?';
                    $params[] = $val;
                    break;
                case 'q':
                    $conditions[] = '(lh.nomor_laporan LIKE ? OR lh.lokasi LIKE ? OR lh.catatan LIKE ?)';
                    $q = '%' . $val . '%';
                    $params[] = $q;
                    $params[] = $q;
                    $params[] = $q;
                    break;
            }
        }

        if (count($conditions) > 0) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $orderCol = in_array($filters['order_col'] ?? '', self::ALLOWED_ORDER_COLS, true)
        ? $filters['order_col']
        : 'lh.tanggal';

        $orderDir = in_array($filters['order_dir'] ?? 'DESC', ['ASC', 'DESC'], true)
            ? $filters['order_dir']
            : 'DESC';

        if (count($conditions) > 0) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= $where;
        $sql .= " ORDER BY " . $orderCol . ' ' . $orderDir;

        return $sql;
    }

    public static function findWithRelations(int $id): ?array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT lh.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                   v.nama_lengkap AS verifikator_nama,
                   mo.nama_opt, mo.jenis AS opt_jenis,
                   mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
            FROM `laporan_hama` lh
            LEFT JOIN `users` u ON u.id = lh.user_id
            LEFT JOIN `users` v ON v.id = lh.verified_by
            LEFT JOIN `master_opt` mo ON mo.id = lh.master_opt_id
            LEFT JOIN `master_kabupaten` mk ON mk.id = lh.kabupaten_id
            LEFT JOIN `master_kecamatan` mkc ON mkc.id = lh.kecamatan_id
            LEFT JOIN `master_desa` md ON md.id = lh.desa_id
            WHERE lh.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
