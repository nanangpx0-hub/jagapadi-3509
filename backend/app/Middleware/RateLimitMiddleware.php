<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Helpers\RateLimiter;

class RateLimitMiddleware
{
    private static array $limits = [];

    public function handle(array $route, array $params): bool
    {
        $uri = Request::uri();

        $config = $this->resolveConfig($uri);

        $identifier = $this->resolveIdentifier();
        $prefix = $config['prefix'];
        $maxAttempts = $config['max'];
        $decaySeconds = $config['decay'];

        $allowed = RateLimiter::attempt($prefix, $identifier, $maxAttempts, $decaySeconds);

        $remaining = RateLimiter::remaining($prefix, $identifier, $maxAttempts);
        $resetIn = RateLimiter::availableIn($prefix, $identifier, $decaySeconds);

        header("X-RateLimit-Limit: $maxAttempts");
        header("X-RateLimit-Remaining: $remaining");
        header("X-RateLimit-Reset: $resetIn");

        if (!$allowed) {
            http_response_code(429);
            $isApi = str_starts_with($uri, '/api/');
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'TooManyRequests',
                    'message' => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
                ]);
            } else {
                $_SESSION['flash_error'] = 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.';
                $redirect = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
                header("Location: $redirect");
            }
            return false;
        }

        return true;
    }

    private function resolveConfig(string $uri): array
    {
        if (str_contains($uri, '/export')) {
            return ['prefix' => 'export', 'max' => 20, 'decay' => 3600];
        }

        if (str_contains($uri, '/notifications/unread-count')
            || str_contains($uri, '/notifications/recent')) {
            return ['prefix' => 'notif_poll', 'max' => 120, 'decay' => 3600];
        }

        if (str_contains($uri, '/laporan-hama/submit')
            || str_contains($uri, '/laporan-irigasi/submit')
            || preg_match('#submit|store#', $uri)) {
            return ['prefix' => 'submit', 'max' => 60, 'decay' => 3600];
        }

        if (str_starts_with($uri, '/api/')) {
            return ['prefix' => 'api', 'max' => 1000, 'decay' => 3600];
        }

        return ['prefix' => 'web', 'max' => 500, 'decay' => 3600];
    }

    private function resolveIdentifier(): string
    {
        $userId = $_SESSION['user_id'] ?? $GLOBALS['auth_user']['id'] ?? 0;
        if ($userId > 0) {
            return 'user_' . $userId;
        }

        $ip = Request::ip();
        return 'ip_' . str_replace(':', '_', $ip);
    }
}
