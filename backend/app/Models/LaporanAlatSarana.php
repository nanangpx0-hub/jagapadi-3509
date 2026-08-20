<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class LaporanAlatSarana extends Model
{
    private const ALLOWED_FILTERS = ['status', 'tanggal_from', 'tanggal_to', 'kabupaten_id', 'kecamatan_id', 'desa_id', 'jenis_sarana', 'kondisi', 'q'];

    protected static function table(): string
    {
        return 'laporan_alat_sarana';
    }

    public static function findAccessibleById(int $id, array $currentUser): ?array
    {
        $pdo = self::db();
        $sql = "SELECT las.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                       v.nama_lengkap AS verifikator_nama,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
                FROM `laporan_alat_sarana` las
                LEFT JOIN `users` u ON u.id = las.user_id
                LEFT JOIN `users` v ON v.id = las.verified_by
                LEFT JOIN `master_kabupaten` mk ON mk.id = las.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = las.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = las.desa_id
                WHERE las.id = ?";

        if ($currentUser['role'] === 'petugas') {
            $sql .= " AND las.user_id = ?";
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
        $conditions = ['las.user_id = ?'];
        $params = [$userId];

        $sql = self::buildListQuery($conditions, $params, $filters);

        $countSql = preg_replace('/^SELECT las\.\*.*?\bFROM\b/s', 'SELECT COUNT(*) FROM', $sql);
        $countSql = preg_replace('/\s+ORDER BY.*/i', '', $countSql);
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

        $countSql = preg_replace('/^SELECT las\.\*.*?\bFROM\b/s', 'SELECT COUNT(*) FROM', $sql);
        $countSql = preg_replace('/\s+ORDER BY.*/i', '', $countSql);
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
            UPDATE `laporan_alat_sarana`
            SET `status` = ?,
                `verified_by` = ?,
                `verified_at` = ?,
                `catatan_verifikasi` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$status, $verifiedBy, $now, $catatanVerifikasi, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function resetVerification(int $id): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            UPDATE `laporan_alat_sarana`
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
        $stmt = $pdo->prepare("DELETE FROM `laporan_alat_sarana` WHERE `id` = ? AND `user_id` = ? AND `status` = 'Draf'");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    private static function buildListQuery(array $conditions, array &$params, array $filters): string
    {
        $sql = "SELECT las.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                       v.nama_lengkap AS verifikator_nama,
                       mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
                FROM `laporan_alat_sarana` las
                LEFT JOIN `users` u ON u.id = las.user_id
                LEFT JOIN `users` v ON v.id = las.verified_by
                LEFT JOIN `master_kabupaten` mk ON mk.id = las.kabupaten_id
                LEFT JOIN `master_kecamatan` mkc ON mkc.id = las.kecamatan_id
                LEFT JOIN `master_desa` md ON md.id = las.desa_id";

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
                        $conditions[] = "las.status IN ($placeholders)";
                        foreach ($statuses as $s) {
                            $params[] = $s;
                        }
                    }
                    break;
                case 'tanggal_from':
                    $conditions[] = 'las.tanggal >= ?';
                    $params[] = $val;
                    break;
                case 'tanggal_to':
                    $conditions[] = 'las.tanggal <= ?';
                    $params[] = $val;
                    break;
                case 'kabupaten_id':
                    $conditions[] = 'las.kabupaten_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'kecamatan_id':
                    $conditions[] = 'las.kecamatan_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'desa_id':
                    $conditions[] = 'las.desa_id = ?';
                    $params[] = (int) $val;
                    break;
                case 'jenis_sarana':
                    $conditions[] = 'las.jenis_sarana = ?';
                    $params[] = $val;
                    break;
                case 'kondisi':
                    $conditions[] = 'las.kondisi = ?';
                    $params[] = $val;
                    break;
                case 'q':
                    $conditions[] = '(las.nomor_laporan LIKE ? OR las.nama_alat LIKE ? OR las.catatan LIKE ?)';
                    $q = '%' . $val . '%';
                    $params[] = $q;
                    $params[] = $q;
                    $params[] = $q;
                    break;
            }
        }

        if (count($conditions) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY las.updated_at DESC, las.id DESC";

        return $sql;
    }

    public static function findWithRelations(int $id): ?array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("
            SELECT las.*, u.nama_lengkap AS pelapor_nama, u.username AS pelapor_username,
                   v.nama_lengkap AS verifikator_nama,
                   mk.nama_kabupaten, mkc.nama_kecamatan, md.nama_desa
            FROM `laporan_alat_sarana` las
            LEFT JOIN `users` u ON u.id = las.user_id
            LEFT JOIN `users` v ON v.id = las.verified_by
            LEFT JOIN `master_kabupaten` mk ON mk.id = las.kabupaten_id
            LEFT JOIN `master_kecamatan` mkc ON mkc.id = las.kecamatan_id
            LEFT JOIN `master_desa` md ON md.id = las.desa_id
            WHERE las.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
