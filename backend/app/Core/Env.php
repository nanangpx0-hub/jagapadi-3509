<?php

declare(strict_types=1);

namespace App\Core;

class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($path) || !is_readable($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            if ($key === '') {
                continue;
            }

            if (getenv($key) !== false && getenv($key) !== '') {
                continue;
            }

            self::$vars[$key] = $value;
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function reset(): void
    {
        self::$vars = [];
        self::$loaded = false;
    }
}
