<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class User extends Model
{
    protected static function table(): string
    {
        return 'users';
    }

    public static function findByUsername(string $username): ?array
    {
        return self::findBy('username', $username);
    }

    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function updatePassword(int $userId, string $newHash): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0, `last_password_change_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([$newHash, $userId]);
    }

    public static function markPasswordChanged(int $userId): bool
    {
        $pdo = self::db();
        $stmt = $pdo->prepare("UPDATE `users` SET `must_change_password` = 0, `last_password_change_at` = NOW() WHERE `id` = ?");
        return $stmt->execute([$userId]);
    }

    public static function isActive(array $user): bool
    {
        return (bool) ($user['aktif'] ?? false);
    }

    public static function toPublicArray(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role' => $user['role'],
            'aktif' => (bool) $user['aktif'],
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            'last_password_change_at' => $user['last_password_change_at'] ?? null,
            'created_at' => $user['created_at'],
        ];
    }
}
