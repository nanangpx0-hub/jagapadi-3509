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

    public static function attempt(string $prefix, string $identifier, int $maxAttempts = 5, int $decaySeconds = 900): bool
    {
        $file = self::key($prefix, $identifier);
        $now = time();

        $data = null;
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
            }
        }

        if ($data === null || !is_array($data)) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }

        if ($now - $data['first_attempt'] > $decaySeconds) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }

        $data['attempts']++;

        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['attempts'] <= $maxAttempts;
    }

    public static function remaining(string $prefix, string $identifier, int $maxAttempts = 5): int
    {
        $file = self::key($prefix, $identifier);
        if (!file_exists($file)) {
            return $maxAttempts;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return $maxAttempts;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $data['attempts']);
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
        $file = self::key($prefix, $identifier);
        if (!file_exists($file)) {
            return 0;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['first_attempt'])) {
            return 0;
        }

        $elapsed = time() - $data['first_attempt'];
        return max(0, $decaySeconds - $elapsed);
    }
}
