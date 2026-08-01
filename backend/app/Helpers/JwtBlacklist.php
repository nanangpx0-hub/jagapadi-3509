<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * Denylist token JWT (revokasi server-side).
 *
 * Token membawa klaim `jti` unik. Saat logout atau refresh, `jti` lama
 * dimasukkan ke tabel `jwt_blacklist` sehingga token tersebut ditolak meski
 * belum kedaluwarsa. Entri otomatis kadaluarsa dan dapat dibersihkan.
 */
class JwtBlacklist
{
    public static function isRevoked(string $jti): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT 1 FROM `jwt_blacklist` WHERE `jti` = ? LIMIT 1");
        $stmt->execute([$jti]);
        return $stmt->fetch() !== false;
    }

    public static function revoke(string $jti, int $expiresAt, ?int $userId = null): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO `jwt_blacklist` (`jti`, `user_id`, `expires_at`)
             VALUES (?, ?, FROM_UNIXTIME(?))"
        );
        return $stmt->execute([$jti, $userId, $expiresAt]);
    }

    /**
     * Hapus entri yang sudah melewati masa berlaku tokennya.
     */
    public static function purgeExpired(): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("DELETE FROM `jwt_blacklist` WHERE `expires_at` < NOW()");
        $stmt->execute();
        return (int) $stmt->rowCount();
    }
}
