<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Helpers\JwtBlacklist;
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

        if (isset($payload['jti']) && JwtBlacklist::isRevoked((string) $payload['jti'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'TokenRevoked',
                'message' => 'Token sudah tidak berlaku (logout/revoked).',
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

        // Revokasi berbasis `token_version`: jika user pernah mengganti password
        // (token_version > 0), JWT harus membawa klaim `ver` yang sama. Token lama
        // (ver lebih kecil / tanpa ver) ditolak.
        $tokenVersion = (int) ($user['token_version'] ?? 0);
        if ($tokenVersion > 0 && (int) ($payload['ver'] ?? 0) !== $tokenVersion) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'TokenRevoked',
                'message' => 'Token sudah tidak berlaku (password telah diubah).',
            ]);
            return false;
        }

        $mustChangePassword = (bool) ($user['must_change_password'] ?? false);
        if ($mustChangePassword) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $allowedPaths = ['/api/v1/auth/change-password', '/api/v1/auth/logout'];

            $isAllowed = false;
            foreach ($allowedPaths as $allowed) {
                if ($currentPath === $allowed || str_starts_with($currentPath, $allowed)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'PasswordChangeRequired',
                    'message' => 'Password sementara masih berlaku. Anda harus mengganti password sebelum melanjutkan.',
                    'must_change_password' => true,
                ]);
                return false;
            }
        }

        $GLOBALS['auth_user'] = User::withoutSensitiveFields($user);
        $GLOBALS['auth_payload'] = $payload;

        return true;
    }
}
