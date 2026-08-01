<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class DeviceToken extends Model
{
    protected static function table(): string
    {
        return 'device_tokens';
    }

    public static function findByToken(string $token): ?array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `device_tokens` WHERE `token` = ? LIMIT 1");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function upsertForUser(int $userId, string $token, string $platform = 'android', ?string $userAgent = null): int
    {
        $pdo = self::db();
        $existing = self::findByToken($token);

        if ($existing !== null) {
            if ((int) $existing['user_id'] !== $userId) {
                $stmt = $pdo->prepare("UPDATE `device_tokens` SET `user_id` = ?, `platform` = ?, `user_agent` = ?, `last_seen_at` = NOW() WHERE `token` = ?");
                $stmt->execute([$userId, $platform, $userAgent, $token]);
            } else {
                $stmt = $pdo->prepare("UPDATE `device_tokens` SET `platform` = ?, `user_agent` = ?, `last_seen_at` = NOW() WHERE `token` = ?");
                $stmt->execute([$platform, $userAgent, $token]);
            }
            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO `device_tokens` (`user_id`, `token`, `platform`, `user_agent`, `last_seen_at`) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $token, $platform, $userAgent]);
        return (int) $pdo->lastInsertId();
    }

    public static function deleteByTokenForUser(int $userId, string $token): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `device_tokens` WHERE `token` = ? AND `user_id` = ?");
        return $stmt->execute([$token, $userId]);
    }

    public static function deleteAllForUser(int $userId): int
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `device_tokens` WHERE `user_id` = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public static function listByUserId(int $userId): array
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("SELECT * FROM `device_tokens` WHERE `user_id` = ? ORDER BY `last_seen_at` DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function deleteByToken(string $token): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("DELETE FROM `device_tokens` WHERE `token` = ?");
        return $stmt->execute([$token]);
    }
}
