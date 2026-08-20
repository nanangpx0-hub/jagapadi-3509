<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Request;
use PDO;

/**
 * Idempotency untuk operasi mutasi API yang bisa di-retry oleh mobile
 * (draft, submit, resubmit, upload foto). Opsional (header Idempotency-Key),
 * backward compatible. Key terikat user+method+path+hash request; request
 * identik dibalas respons tersimpan, key sama payload beda => 409; unique
 * constraint + status processing mencegah duplikasi bersamaan; ada TTL.
 */
class Idempotency
{
    public const TTL_SECONDS = 86400;

    private const PROCESSING_TIMEOUT_SECONDS = 60;

    public static function key(): ?string
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        // Key overlong / karakter aneh tidak mengganggu request normal.
        if (strlen($key) > 128 || !preg_match('/^[A-Za-z0-9._-]+$/', $key)) {
            return null;
        }

        return $key;
    }

    public static function isEligible(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), ['POST', 'PUT'], true)) {
            return false;
        }

        if (!str_starts_with($path, '/api/v1/')) {
            return false;
        }

        // Auth & device-token tidak di-dedup (refresh memakai jti baru).
        if (str_starts_with($path, '/api/v1/auth/') || str_starts_with($path, '/api/v1/device-tokens')) {
            return false;
        }

        return true;
    }

    /**
     * Hash deterministik dari request. Urutan key dinormalisasi (ksort)
     * agar payload sama dalam urutan berbeda menghasilkan hash yang sama.
     */
    public static function hashRequest(int $userId, string $method, string $path, array $input): string
    {
        $normalized = [
            'user_id' => $userId,
            'method' => strtoupper($method),
            'path' => $path,
            'input' => self::normalizeValue($input),
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function lookup(PDO $pdo, int $userId, string $key, string $method, string $path): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT `idempotency_key`, `request_hash`, `status`, `response_status`, `response_body`, `expires_at`, `created_at`
             FROM `idempotency_keys`
             WHERE `user_id` = ? AND `idempotency_key` = ? AND `method` = ? AND `path` = ?
             LIMIT 1"
        );
        $stmt->execute([$userId, $key, strtoupper($method), $path]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function insert(PDO $pdo, int $userId, string $key, string $method, string $path, string $requestHash): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO `idempotency_keys`
                (`user_id`, `idempotency_key`, `method`, `path`, `request_hash`, `status`, `expires_at`)
             VALUES (?, ?, ?, ?, ?, 'processing', DATE_ADD(NOW(), INTERVAL ? SECOND))"
        );
        return $stmt->execute([$userId, $key, strtoupper($method), $path, $requestHash, self::TTL_SECONDS]);
    }

    public static function reset(PDO $pdo, int $userId, string $key, string $method, string $path, string $requestHash): bool
    {
        $stmt = $pdo->prepare(
            "UPDATE `idempotency_keys`
             SET `request_hash` = ?, `status` = 'processing', `response_status` = NULL, `response_body` = NULL,
                 `expires_at` = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE `user_id` = ? AND `idempotency_key` = ? AND `method` = ? AND `path` = ?"
        );
        return $stmt->execute([$requestHash, self::TTL_SECONDS, $userId, $key, strtoupper($method), $path]);
    }

    public static function finalize(
        PDO $pdo,
        int $userId,
        string $key,
        string $method,
        string $path,
        bool $success,
        int $statusCode,
        string $responseBody
    ): bool {
        $stmt = $pdo->prepare(
            "UPDATE `idempotency_keys`
             SET `status` = ?, `response_status` = ?, `response_body` = ?
             WHERE `user_id` = ? AND `idempotency_key` = ? AND `method` = ? AND `path` = ?"
        );
        return $stmt->execute([
            $success ? 'completed' : 'failed',
            $statusCode,
            $responseBody,
            $userId,
            $key,
            strtoupper($method),
            $path,
        ]);
    }

    public static function isExpired(array $row): bool
    {
        $expires = $row['expires_at'] ?? null;
        if ($expires === null) {
            return false;
        }
        return strtotime((string) $expires) < time();
    }

    public static function isProcessingTimedOut(array $row): bool
    {
        $created = $row['created_at'] ?? null;
        if ($created === null) {
            return false;
        }
        return strtotime((string) $created) < time() - self::PROCESSING_TIMEOUT_SECONDS;
    }

    public static function purgeExpired(PDO $pdo): int
    {
        $stmt = $pdo->prepare("DELETE FROM `idempotency_keys` WHERE `expires_at` < NOW()");
        $stmt->execute();
        return (int) $stmt->rowCount();
    }

    public static function requestInput(): array
    {
        return Request::all();
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            ksort($value);
            $out = [];
            foreach ($value as $k => $v) {
                // Abaikan isi file multipart agar hash stabil saat retry.
                if ($k === 'foto' || $k === 'file') {
                    continue;
                }
                $out[$k] = self::normalizeValue($v);
            }
            return $out;
        }

        return $value;
    }
}