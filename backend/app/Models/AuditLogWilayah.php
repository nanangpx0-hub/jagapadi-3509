<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AuditLogWilayah extends Model
{
    protected static function table(): string
    {
        return 'audit_log_wilayah';
    }

    public static function log(int $adminId, string $tabel, int $recordId, string $aksi, ?array $dataLama = null, ?array $dataBaru = null): int|string
    {
        return self::insert([
            'admin_id' => $adminId,
            'tabel' => $tabel,
            'record_id' => $recordId,
            'aksi' => $aksi,
            'data_lama' => $dataLama !== null ? json_encode($dataLama) : null,
            'data_baru' => $dataBaru !== null ? json_encode($dataBaru) : null,
        ]);
    }

    public static function getByRecord(string $tabel, int $recordId): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT alw.*, u.username, u.nama_lengkap
            FROM `audit_log_wilayah` alw
            LEFT JOIN `users` u ON u.id = alw.admin_id
            WHERE alw.`tabel` = ? AND alw.`record_id` = ?
            ORDER BY alw.created_at DESC");
        $stmt->execute([$tabel, $recordId]);
        return $stmt->fetchAll();
    }
}
