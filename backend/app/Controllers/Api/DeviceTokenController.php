<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Models\DeviceToken;

class DeviceTokenController
{
    public function store(): void
    {
        $user = $GLOBALS['auth_user'];
        $input = Request::all();

        $token = isset($input['token']) ? trim($input['token']) : '';
        if ($token === '' || strlen($token) < 32 || strlen($token) > 512) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Token tidak valid.',
                'errors' => ['token' => 'Token harus diisi (32-512 karakter).'],
            ]);
            return;
        }

        $platform = isset($input['platform']) ? trim($input['platform']) : 'android';
        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $id = DeviceToken::upsertForUser((int) $user['id'], $token, $platform, $userAgent);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Token berhasil didaftarkan.',
            'data' => ['id' => $id],
        ]);
    }

    public function destroy(): void
    {
        $user = $GLOBALS['auth_user'];
        $input = Request::all();

        $token = isset($input['token']) ? trim($input['token']) : '';
        if ($token === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Token wajib diisi.',
            ]);
            return;
        }

        $deleted = DeviceToken::deleteByTokenForUser((int) $user['id'], $token);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $deleted ? 'Token berhasil dihapus.' : 'Token tidak ditemukan.',
        ]);
    }

    public function destroyAll(): void
    {
        $user = $GLOBALS['auth_user'];
        $count = DeviceToken::deleteAllForUser((int) $user['id']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "{$count} token berhasil dihapus.",
            'data' => ['count' => $count],
        ]);
    }
}
