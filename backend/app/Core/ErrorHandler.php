<?php

declare(strict_types=1);

namespace App\Core;

class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $context = [
            'file' => self::sanitizePath($file),
            'line' => $line,
            'severity' => $severity,
        ];

        Logger::error("PHP Error: $message", Logger::cleanContext($context));

        if (self::isDebugMode()) {
            self::sendErrorResponse(500, 'Internal Error', [
                'message' => $message,
                'file' => self::sanitizePath($file),
                'line' => $line,
            ]);
        } else {
            self::sendErrorResponse(500, 'Terjadi kesalahan internal. Silakan coba kembali.');
        }

        return true;
    }

    public static function handleException(\Throwable $e): void
    {
        $context = [
            'file' => self::sanitizePath($e->getFile()),
            'line' => $e->getLine(),
            'class' => get_class($e),
        ];

        Logger::error("Uncaught Exception: {$e->getMessage()}", Logger::cleanContext($context));

        if (self::isDebugMode()) {
            self::sendErrorResponse(500, 'Internal Server Error', [
                'message' => $e->getMessage(),
                'file' => self::sanitizePath($e->getFile()),
                'line' => $e->getLine(),
                'trace' => self::sanitizeTrace($e->getTrace()),
            ]);
        } else {
            self::sendErrorResponse(500, 'Terjadi kesalahan internal. Silakan coba kembali.');
        }
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $context = [
                'file' => self::sanitizePath($error['file']),
                'line' => $error['line'],
                'type' => $error['type'],
            ];

            Logger::error("Fatal Error: {$error['message']}", Logger::cleanContext($context));

            if (ob_get_level() > 0) {
                ob_clean();
            }

            if (self::isDebugMode()) {
                self::sendErrorResponse(500, 'Fatal Error', [
                    'message' => $error['message'],
                    'file' => self::sanitizePath($error['file']),
                    'line' => $error['line'],
                ]);
            } else {
                self::sendErrorResponse(500, 'Terjadi kesalahan internal. Silakan coba kembali.');
            }
        }
    }

    private static function isDebugMode(): bool
    {
        $env = Env::get('APP_ENV', 'production');
        $debug = Env::get('APP_DEBUG', 'false');

        if ($env === 'production' && $debug !== 'true') {
            return false;
        }

        return $debug === 'true';
    }

    private static function sendErrorResponse(int $statusCode, string $message, array $extra = []): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($statusCode);

        $isApi = isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/');

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');

            $response = [
                'success' => false,
                'error' => 'ServerError',
                'message' => $message,
            ];

            if (!empty($extra)) {
                $response['debug'] = $extra;
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $message;
        }
    }

    private static function sanitizePath(string $path): string
    {
        $root = realpath(__DIR__ . '/../../') ?: '';
        if ($root !== '') {
            $path = str_replace($root, '{ROOT}', $path);
        }
        return $path;
    }

    private static function sanitizeTrace(array $trace): array
    {
        $sanitized = [];
        foreach ($trace as $i => $frame) {
            $sanitized[$i] = [
                'file' => isset($frame['file']) ? self::sanitizePath($frame['file']) : 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? '',
            ];

            if ($i >= 10) {
                break;
            }
        }
        return $sanitized;
    }
}
