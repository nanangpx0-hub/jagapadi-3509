<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private static ?string $logPath = null;

    public static function init(string $logDir): void
    {
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        self::$logPath = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . 'app.log';
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        $level = Env::get('APP_LOG_LEVEL', 'debug');
        if (!in_array($level, ['debug', 'all'], true)) {
            return;
        }
        self::log('DEBUG', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (self::$logPath === null) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        // Korelasi antar log: sertakan request_id (trace ID) bila tersedia.
        if (isset($_SERVER['JAGAPADI_REQUEST_ID'])) {
            $context = ['request_id' => $_SERVER['JAGAPADI_REQUEST_ID']] + $context;
        }

        $contextJson = !empty($context) ? ' ' . json_encode($context) : '';
        $line = "[$timestamp] $level: $message$contextJson" . PHP_EOL;

        @file_put_contents(self::$logPath, $line, FILE_APPEND | LOCK_EX);
    }

    public static function getLogPath(): ?string
    {
        return self::$logPath;
    }

    public static function cleanContext(array $context): array
    {
        $secrets = ['password', 'pass', 'pwd', 'secret', 'token', 'jwt', 'authorization', 'cookie'];
        foreach ($context as $key => $value) {
            foreach ($secrets as $secret) {
                if (stripos((string)$key, $secret) !== false) {
                    $context[$key] = '***REDACTED***';
                    break;
                }
            }
        }
        return $context;
    }
}
