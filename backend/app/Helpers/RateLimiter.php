<?php

declare(strict_types=1);

namespace App\Helpers;

class RateLimiter
{
    private static function getPath(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function key(string $prefix, string $identifier): string
    {
        return self::getPath() . '/' . $prefix . '_' . md5($identifier) . '.lock';
    }

    /**
     * Atomic attempt — file-lock (single-instance) + Redis (multi-instance) bila tersedia.
     * Mengembalikan true bila masih dalam batas, false bila melebihi.
     */
    public static function attempt(string $prefix, string $identifier, int $maxAttempts = 5, int $decaySeconds = 900): bool
    {
        // Coba Redis dahulu bila tersedia
        $redis = self::redis();
        if ($redis !== null) {
            return self::attemptRedis($redis, $prefix, $identifier, $maxAttempts, $decaySeconds);
        }
        return self::attemptFile($prefix, $identifier, $maxAttempts, $decaySeconds);
    }

    private static function attemptFile(string $prefix, string $identifier, int $maxAttempts, int $decaySeconds): bool
    {
        $file = self::key($prefix, $identifier);
        $now = time();
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return false;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        $content = stream_get_contents($fp);
        $data = null;
        if ($content !== false && $content !== '') {
            $data = json_decode($content, true);
        }
        if ($data === null || !is_array($data)) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }
        if ($now - (int)($data['first_attempt'] ?? $now) > $decaySeconds) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }
        $data['attempts'] = (int)($data['attempts'] ?? 0) + 1;
        $allowed = $data['attempts'] <= $maxAttempts;
        // Tulis atomik
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $allowed;
    }

    private static function attemptRedis($redis, string $prefix, string $identifier, int $maxAttempts, int $decaySeconds): bool
    {
        $key = "rl:" . $prefix . ":" . md5($identifier);
        try {
            $current = $redis->incr($key);
            if ($current === 1) {
                $redis->expire($key, $decaySeconds);
            } elseif ($current === false) {
                return false;
            }
            return $current <= $maxAttempts;
        } catch (\Throwable $e) {
            error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            // Fallback ke file bila Redis gagal
            return self::attemptFile($prefix, $identifier, $maxAttempts, $decaySeconds);
        }
    }

    private static function redis(): ?object
    {
        $host = \App\Core\Env::get('REDIS_HOST', \App\Core\Env::get('REDIS_URL', ''));
        if ($host === '' || $host === null) {
            return null;
        }
        // Predis / PhpRedis — coba PhpRedis extension dulu
        if (extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                $redisHost = \App\Core\Env::get('REDIS_HOST', '127.0.0.1');
                $redisPort = (int) \App\Core\Env::get('REDIS_PORT', '6379');
                $redis->connect($redisHost, $redisPort, 1.0);
                $pass = \App\Core\Env::get('REDIS_PASSWORD', '');
                if ($pass !== '') {
                    $redis->auth($pass);
                }
                $redis->select((int) \App\Core\Env::get('REDIS_DB', '0'));
                return $redis;
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
                return null;
            }
        }
        return null;
    }

    public static function remaining(string $prefix, string $identifier, int $maxAttempts = 5): int
    {
        $redis = self::redis();
        if ($redis !== null) {
            try {
                $key = "rl:" . $prefix . ":" . md5($identifier);
                $val = $redis->get($key);
                if ($val === false || $val === null) {
                    return $maxAttempts;
                }
                return max(0, $maxAttempts - (int)$val);
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
        $file = self::key($prefix, $identifier);
        $fp = @fopen($file, 'r');
        if ($fp === false) {
            return $maxAttempts;
        }
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($content === false || $content === '') {
            return $maxAttempts;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $maxAttempts;
        }
        return max(0, $maxAttempts - (int)($data['attempts'] ?? 0));
    }

    public static function reset(string $prefix, string $identifier): void
    {
        $file = self::key($prefix, $identifier);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function availableIn(string $prefix, string $identifier, int $decaySeconds = 900): int
    {
        $redis = self::redis();
        if ($redis !== null) {
            try {
                $key = "rl:" . $prefix . ":" . md5($identifier);
                $ttl = $redis->ttl($key);
                if ($ttl === false || $ttl < 0) {
                    return 0;
                }
                return (int)$ttl;
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
        $file = self::key($prefix, $identifier);
        $fp = @fopen($file, 'r');
        if ($fp === false) {
            return 0;
        }
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($content === false || $content === '') {
            return 0;
        }
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['first_attempt'])) {
            return 0;
        }
        $elapsed = time() - (int)$data['first_attempt'];
        return max(0, $decaySeconds - $elapsed);
    }
}
