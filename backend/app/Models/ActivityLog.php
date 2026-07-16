<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ActivityLog extends Model
{
    protected static function table(): string
    {
        return 'activity_log';
    }

    public static function log(
        ?int $userId,
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int|string {
        return self::insert([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
    }

    public static function getRecent(int $limit = 50): array
    {
        $pdo = self::db();
        $stmt = $pdo->query("SELECT al.*, u.username, u.nama_lengkap
            FROM `activity_log` al
            LEFT JOIN `users` u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT " . (int) $limit);
        return $stmt->fetchAll();
    }
}
