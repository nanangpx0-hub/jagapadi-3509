<?php

declare(strict_types=1);

namespace App\Middleware;

class WebAuthMiddleware
{
    public function handle(array $route, array $params): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            $_SESSION['flash_error'] = 'Silakan login terlebih dahulu.';
            header('Location: /login');
            return false;
        }

        $mustChangePassword = $_SESSION['must_change_password'] ?? false;
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($mustChangePassword && !in_array($currentPath, ['/password/change', '/logout'], true)) {
            $_SESSION['flash_warning'] = 'Anda harus mengganti password sebelum melanjutkan.';
            header('Location: /password/change');
            return false;
        }

        return true;
    }
}
