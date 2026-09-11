<?php

declare(strict_types=1);

namespace App\Core;

class CacheManager
{
    private static ?string $basePath = null;
    private static int $defaultTtl = 300;
    private const CACHE_VERSION = 'v2'; // bump saat deploy untuk invalidasi global
    private static ?object $redis = null;

    public static function init(string $basePath, int $defaultTtl = 300): void
    {
        self::$basePath = rtrim($basePath, '\\/');
        self::$defaultTtl = $defaultTtl;

        if (!is_dir(self::$basePath)) {
            if (!mkdir(self::$basePath, 0755, true) && !is_dir(self::$basePath)) {
                throw new \RuntimeException('Cannot create cache directory: ' . self::$basePath);
            }
        }
        self::$redis = self::connectRedis();
    }

    private static function connectRedis(): ?object
    {
        $host = Env::get('REDIS_HOST', Env::get('REDIS_URL', ''));
        if ($host === '' || $host === null) {
            return null;
        }
        if (!extension_loaded('redis')) {
            return null;
        }
        try {
            $redis = new \Redis();
            $redisHost = Env::get('REDIS_HOST', '127.0.0.1');
            $redisPort = (int) Env::get('REDIS_PORT', '6379');
            $redis->connect($redisHost, $redisPort, 1.0);
            $pass = Env::get('REDIS_PASSWORD', '');
            if ($pass !== '') {
                $redis->auth($pass);
            }
            $redis->select((int) Env::get('REDIS_DB', '0'));
            return $redis;
        } catch (\Throwable $e) {
            error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            return null;
        }
    }

    private static function namespacedKey(string $key): string
    {
        $env = Env::get('APP_ENV', 'local');
        $runtime = 'backend-v1';
        // namespace: runtime:env:version:key (user/role/filter sudah ada di key asli)
        return implode(':', [$runtime, $env, self::CACHE_VERSION, $key]);
    }

    public static function get(string $key): mixed
    {
        $nsKey = self::namespacedKey($key);
        if (self::$redis !== null) {
            try {
                $val = self::$redis->get($nsKey);
                if ($val !== false && $val !== null) {
                    $data = self::decodePayload((string) $val);
                    if (is_array($data) && isset($data['expires'], $data['value'])) {
                        if (time() > (int) $data['expires']) {
                            self::$redis->del($nsKey);
                            return null;
                        }
                        return $data['value'];
                    }
                    if ($data !== null) {
                        error_log('[CacheManager] invalid Redis payload for ' . $nsKey);
                    }
                }
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
        $path = self::path($nsKey);
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = self::decodePayload($content);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            @unlink($path);
            if ($data !== null || str_contains($content, 'O:')) {
                error_log('[CacheManager] rejected invalid file payload');
            }
            return null;
        }

        if (time() > (int) $data['expires']) {
            @unlink($path);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $nsKey = self::namespacedKey($key);
        $expires = time() + ($ttl ?? self::$defaultTtl);
        $payload = ['v' => 2, 'expires' => $expires, 'value' => $value];
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($data === false) {
            return false;
        }
        $ttlSec = $ttl ?? self::$defaultTtl;

        if (self::$redis !== null) {
            try {
                // Redis SETEX atomic
                $ok = self::$redis->setex($nsKey, $ttlSec, $data);
                if ($ok) {
                    return true;
                }
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }

        $path = self::path($nsKey);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));

        $written = @file_put_contents($tmp, $data, LOCK_EX);
        if ($written === false) {
            @unlink($tmp);
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            @unlink($path);
        }
        $renamed = @rename($tmp, $path);
        if (!$renamed) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    public static function delete(string $key): bool
    {
        $nsKey = self::namespacedKey($key);
        if (self::$redis !== null) {
            try {
                self::$redis->del($nsKey);
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
        $path = self::path($nsKey);
        if (is_file($path)) {
            return @unlink($path);
        }
        return false;
    }

    public static function deletePrefix(string $prefix): int
    {
        $nsPrefix = self::namespacedKey($prefix);
        $count = 0;
        if (self::$redis !== null) {
            try {
                $cursor = '0';
                do {
                    $res = self::$redis->scan($cursor, $nsPrefix . '*', 100);
                    if ($res === false) {
                        break;
                    }
                    [$cursor, $keys] = is_array($res) && count($res) === 2 ? $res : [$res, []];
                    if (!empty($keys)) {
                        $count += self::$redis->del(...$keys);
                    }
                } while ($cursor !== '0');
            } catch (\Throwable $e) {
                error_log(sprintf("[%s] %s: %s in %s:%d", date("Y-m-d H:i:s"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
        $dir = self::$basePath;
        if (!is_dir($dir)) {
            return $count;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return $count;
        }

        // File keys are sanitized, so search for sanitized prefix
        $safePrefix = preg_replace('/[^a-zA-Z0-9_:.-]/', '_', $nsPrefix);
        $safePrefix = substr($safePrefix, 0, 200);
        foreach ($files as $file) {
            if (is_file($file)) {
                $name = basename($file);
                if (str_starts_with($name, $safePrefix)) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public static function flush(): int
    {
        $dir = self::$basePath;
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public static function isWritable(): bool
    {
        $path = self::path('_test');
        $result = @file_put_contents($path, '1', LOCK_EX);
        if ($result === false) {
            return false;
        }
        @unlink($path);
        return true;
    }

    private static function decodePayload(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        $legacy = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($legacy)) {
            return $legacy;
        }
        if (str_contains($raw, 'O:')) {
            error_log('[CacheManager] rejected serialized object');
        }
        return null;
    }

    private static function path(string $key): string
    {
        if (self::$basePath === null) {
            $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
            self::init($base);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_:.-]/', '_', $key);
        $safe = substr($safe, 0, 200);

        return self::$basePath . DIRECTORY_SEPARATOR . $safe;
    }
}
