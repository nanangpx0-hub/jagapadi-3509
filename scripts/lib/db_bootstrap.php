<?php

declare(strict_types=1);

/**
 * Bootstrap bersama untuk script CLI di folder scripts/.
 *
 * Memuat .env lalu .env.local (override) dan menyiapkan koneksi Database
 * memakai app/core/Database.php yang env-driven. Jangan pernah menaruh
 * kredensial di dalam file source — sediakan lewat environment.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envFile) {
    if (!is_file($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');

require_once ROOT_PATH . '/app/core/Database.php';

/**
 * Ambil nilai environment wajib; keluar dengan pesan jelas bila kosong.
 */
function jp_env_required(string $key): string
{
    $value = getenv($key) ?: ($_ENV[$key] ?? '');
    if ($value === '') {
        fwrite(STDERR, "[KONFIGURASI] Environment {$key} wajib diisi (tanpa hardcode di source).\n");
        exit(1);
    }

    return $value;
}

/**
 * Ambil nilai environment opsional dengan fallback.
 */
function jp_env_optional(string $key, string $fallback = ''): string
{
    $value = getenv($key) ?: ($_ENV[$key] ?? '');

    return $value !== '' ? $value : $fallback;
}
