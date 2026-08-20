<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Helpers\Idempotency;
use PDO;
use PDOException;

/**
 * Middleware idempotensi berbasis `Idempotency-Key` (opsional).
 * Tanpa header -> request normal. Key sama+payload sama -> replay respons
 * tersimpan. Key sama+payload beda -> 409. Entry `processing` (konkurensi)
 * -> tunggu lalu replay. Pasang SETELAH middleware autentikasi.
 */
class IdempotencyMiddleware
{
    private const POLL_INTERVAL_MS = 150000; // microseconds
    private const POLL_TOTAL_MS = 6000000;   // microseconds (6 detik)

    public function handle(array $route, array $params): bool
    {
        $method = Request::method();
        $path = Request::uri();
        $key = Idempotency::key();

        if ($key === null || !Idempotency::isEligible($method, $path)) {
            return true;
        }

        $userId = (int) ($GLOBALS['auth_user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if ($userId <= 0) {
            return true;
        }

        $pdo = Database::connect();
        Idempotency::purgeExpired($pdo);

        $requestHash = Idempotency::hashRequest($userId, $method, $path, Idempotency::requestInput());

        $existing = Idempotency::lookup($pdo, $userId, $key, $method, $path);

        if ($existing !== null) {
            if (Idempotency::isExpired($existing)) {
                Idempotency::reset($pdo, $userId, $key, $method, $path, $requestHash);
                $existing = null;
            } elseif ($existing['status'] === 'completed') {
                if (hash_equals((string) $existing['request_hash'], $requestHash)) {
                    return self::replay($existing);
                }
                return self::conflict();
            } elseif ($existing['status'] === 'failed') {
                Idempotency::reset($pdo, $userId, $key, $method, $path, $requestHash);
                $existing = null;
            } else {
                if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
                    return self::conflict();
                }
                return self::waitForCompletion($pdo, $userId, $key, $method, $path, $requestHash);
            }
        }

        if ($existing === null) {
            try {
                Idempotency::insert($pdo, $userId, $key, $method, $path, $requestHash);
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $row = Idempotency::lookup($pdo, $userId, $key, $method, $path);
                if ($row === null) {
                    throw $e;
                }
                if ($row['status'] === 'completed' && hash_equals((string) $row['request_hash'], $requestHash)) {
                    return self::replay($row);
                }
                if ($row['status'] === 'processing' && hash_equals((string) $row['request_hash'], $requestHash)) {
                    return self::waitForCompletion($pdo, $userId, $key, $method, $path, $requestHash);
                }
                return self::conflict();
            }
        }

        // Request baru: kunci idempotensi dipegang, jalankan handler lalu catat respons.
        if (ob_get_level() === 0) {
            ob_start();
        }

        register_shutdown_function(
            static function () use ($pdo, $userId, $key, $method, $path): void {
                $body = ob_get_level() > 0 ? (string) ob_get_clean() : '';
                $status = (int) http_response_code();
                Idempotency::finalize(
                    $pdo,
                    $userId,
                    $key,
                    $method,
                    $path,
                    $status >= 200 && $status < 500,
                    $status,
                    $body
                );
            }
        );

        return true;
    }

    private static function replay(array $row): bool
    {
        $status = (int) ($row['response_status'] ?? 200);
        $body = (string) ($row['response_body'] ?? '');

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        return false;
    }

    private static function conflict(): bool
    {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Conflict',
            'message' => 'Idempotency-Key yang sama digunakan dengan payload berbeda.',
        ]);
        return false;
    }

    private static function waitForCompletion(
        PDO $pdo,
        int $userId,
        string $key,
        string $method,
        string $path,
        string $requestHash
    ): bool {
        $elapsed = 0;
        while ($elapsed < self::POLL_TOTAL_MS) {
            usleep(self::POLL_INTERVAL_MS);
            $elapsed += self::POLL_INTERVAL_MS;

            $row = Idempotency::lookup($pdo, $userId, $key, $method, $path);
            if ($row === null) {
                return self::conflict();
            }

            if ($row['status'] === 'completed') {
                if (hash_equals((string) $row['request_hash'], $requestHash)) {
                    return self::replay($row);
                }
                return self::conflict();
            }

            if ($row['status'] === 'failed') {
                return self::conflict();
            }
        }

        return self::conflict();
    }
}