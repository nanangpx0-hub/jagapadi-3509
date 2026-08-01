<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Notification extends Model
{
    protected static function table(): string
    {
        return 'notifications';
    }

    public static function listForUser(int $userId, int $page, int $limit, ?bool $unreadOnly = null): array
    {
        $pdo = self::db();
        $conditions = ['user_id = ?'];
        $params = [$userId];

        if ($unreadOnly === true) {
            $conditions[] = 'read_at IS NULL';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) FROM `notifications` {$where}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM `notifications` {$where} ORDER BY `created_at` DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return ['data' => $data, 'total' => $total];
    }

    public static function unreadCount(int $userId): int
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ? AND `read_at` IS NULL");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $userId, int $notificationId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE `notifications` SET `read_at` = NOW() WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(int $userId): int
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE `notifications` SET `read_at` = NOW() WHERE `user_id` = ? AND `read_at` IS NULL");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public static function deleteForUser(int $userId, int $notificationId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `notifications` WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function findForUser(int $userId, int $notificationId): ?array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `notifications` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
        $stmt->execute([$notificationId, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getRecentForUser(int $userId, int $limit = 5): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `notifications` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT " . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function pruneOlderThan(int $days): int
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `notifications` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
