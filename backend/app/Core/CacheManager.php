<?php

declare(strict_types=1);

namespace App\Core;

class CacheManager
{
    private static ?string $basePath = null;
    private static int $defaultTtl = 300;

    public static function init(string $basePath, int $defaultTtl = 300): void
    {
        self::$basePath = rtrim($basePath, '\\/');
        self::$defaultTtl = $defaultTtl;

        if (!is_dir(self::$basePath)) {
            if (!mkdir(self::$basePath, 0755, true) && !is_dir(self::$basePath)) {
                throw new \RuntimeException('Cannot create cache directory: ' . self::$basePath);
            }
        }
    }

    public static function get(string $key): mixed
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = unserialize($content);
        if ($data === false || !isset($data['expires'], $data['value'])) {
            @unlink($path);
            return null;
        }

        if (time() > $data['expires']) {
            @unlink($path);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $path = self::path($key);
        $expires = time() + ($ttl ?? self::$defaultTtl);

        $data = serialize(['expires' => $expires, 'value' => $value]);
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
        $path = self::path($key);
        if (is_file($path)) {
            return @unlink($path);
        }
        return false;
    }

    public static function deletePrefix(string $prefix): int
    {
        $dir = self::$basePath;
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $escapedPrefix = preg_quote($prefix, '/');
        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $name = basename($file);
                if (str_starts_with($name, $prefix)) {
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
