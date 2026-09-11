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
            header("Retry-After: $resetIn");
        }

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
                $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
                // Prevent redirect loop: only redirect to a referrer that differs from the current
                // (rate-limited) path. Otherwise emit a plain 429 response to avoid infinite redirects.
                if ($referer !== null && $referer !== '' && $referer !== $currentPath) {
                    header("Location: $referer");
                } else {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo '429 - Terlalu banyak permintaan. Silakan coba beberapa saat lagi.';
                }
            }
            return false;
        }

        return true;
    }

    private function resolveConfig(string $uri): array
    {
        // Login brute force: 5 / 15 menit per IP — ditangani juga di Security::checkBruteForce
        if (str_contains($uri, '/auth/login')) {
            return ['prefix' => 'login', 'max' => 5, 'decay' => 900];
        }
        // Export: 20 / jam (batas ketat, data berat)
        if (str_contains($uri, '/export')) {
            return ['prefix' => 'export', 'max' => 20, 'decay' => 3600];
        }
        // Upload foto/video: 30 / jam per user (hindari spam storage)
        if (str_contains($uri, '/foto') || str_contains($uri, '/video')) {
            return ['prefix' => 'upload', 'max' => 30, 'decay' => 3600];
        }
        // Polling notifikasi: 120 / jam
        if (str_contains($uri, '/notifications/unread-count')
            || str_contains($uri, '/notifications/recent')) {
            return ['prefix' => 'notif_poll', 'max' => 120, 'decay' => 3600];
        }
        // Scraper: 10 / jam
        if (str_contains($uri, 'Scraper') || str_contains($uri, '/scraper') || str_contains($uri, '/bpsScraper') || str_contains($uri, '/kecepatanAngin')) {
            return ['prefix' => 'scraper', 'max' => 10, 'decay' => 3600];
        }
        // API authenticated vs guest — dibedakan via identifier (user vs ip)
        if (str_starts_with($uri, '/api/')) {
            $isAuthenticated = isset($_SESSION['user_id']) || isset($GLOBALS['auth_user']);
            if ($isAuthenticated) {
                return ['prefix' => 'api_auth', 'max' => 60, 'decay' => 60];
            }
            return ['prefix' => 'api_guest', 'max' => 20, 'decay' => 60];
        }
        return ['prefix' => 'web', 'max' => 60, 'decay' => 60];
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
