<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Models\User;

class ApiAuthMiddleware
{
    public function handle(array $route, array $params): bool
    {
        $token = Request::bearerToken();
        if ($token === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthenticated',
                'message' => 'Token autentikasi tidak ditemukan.',
            ]);
            return false;
        }

        $payload = Jwt::decode($token);
        if ($payload === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'TokenInvalid',
                'message' => 'Token tidak valid atau sudah kedaluwarsa.',
            ]);
            return false;
        }

        $user = User::find((int) $payload['sub']);
        if ($user === null || !User::isActive($user)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'UserInactive',
                'message' => 'Akun tidak aktif atau tidak ditemukan.',
            ]);
            return false;
        }

        $GLOBALS['auth_user'] = $user;
        $GLOBALS['auth_payload'] = $payload;

        return true;
    }
}
