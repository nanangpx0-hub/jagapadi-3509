<?php

declare(strict_types=1);

namespace App\Middleware;

class AdminMiddleware
{
    public function handle(array $route, array $params): bool
    {
        $user = $GLOBALS['auth_user'] ?? null;
        $role = $user['role'] ?? ($_SESSION['role'] ?? '');

        if ($role !== 'admin') {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($uri ?? '', '/api/')) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'Akses ditolak. Hanya admin yang diizinkan.',
                ]);
            } else {
                $_SESSION['flash_error'] = 'Akses ditolak. Halaman ini hanya untuk admin.';
                header('Location: /dashboard');
            }
            return false;
        }

        return true;
    }
}
