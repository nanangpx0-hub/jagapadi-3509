<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\Helpers\JwtBlacklist;
use App\Helpers\PasswordValidator;

class AuthController extends BaseApiController
{
    public function login(): void
    {
        $username = Request::input('username', '');
        $password = Request::input('password', '');

        if ($username === '' || $password === '') {
            $this->error('ValidationError', 'Username dan password harus diisi.', [
                'username' => $username === '' ? 'Username wajib diisi.' : null,
                'password' => $password === '' ? 'Password wajib diisi.' : null,
            ], 422);
            return;
        }

        $user = User::findByUsername($username);

        if ($user === null || !User::verifyPassword($password, $user['password'])) {
            ActivityLog::log(null, 'login_failed', 'users', null, "API login gagal untuk username: $username");
            $this->error('Unauthorized', 'Username atau password salah.', [], 401);
            return;
        }

        if (!User::isActive($user)) {
            ActivityLog::log((int) $user['id'], 'login_failed', 'users', $user['id'], 'API login: akun tidak aktif');
            $this->error('Unauthorized', 'Autentikasi gagal.', [], 401);
            return;
        }

        $jwtExpiry = (int) Env::get('JWT_EXPIRY', '3600');
        $token = Jwt::encode([
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'username' => $user['username'],
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            'ver' => (int) ($user['token_version'] ?? 0),
            'exp' => time() + $jwtExpiry,
        ]);

        ActivityLog::log((int) $user['id'], 'login_success', 'users', (int) $user['id'], 'API login berhasil');

        $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $jwtExpiry,
            'user' => User::toPublicArray($user),
        ], 'Login berhasil');
    }

    public function refresh(): void
    {
        $token = Request::bearerToken();
        if ($token === null) {
            $this->error('Unauthenticated', 'Token tidak ditemukan.', [], 401);
            return;
        }

        $payload = Jwt::decode($token);
        if ($payload === null) {
            $this->error('TokenInvalid', 'Token tidak valid atau sudah kedaluwarsa.', [], 401);
            return;
        }

        $user = User::find((int) $payload['sub']);
        if ($user === null || !User::isActive($user)) {
            $this->error('UserInactive', 'Akun tidak aktif atau tidak ditemukan.', [], 401);
            return;
        }

        $newToken = Jwt::refresh($token);
        if ($newToken === null) {
            $this->error('TokenInvalid', 'Token tidak valid atau sudah kedaluwarsa.', [], 401);
            return;
        }

        // Revoke token lama agar tidak dapat dipakai lagi setelah refresh.
        if (isset($payload['jti']) && isset($payload['exp'])) {
            JwtBlacklist::revoke((string) $payload['jti'], (int) $payload['exp'], (int) $user['id']);
        }
        JwtBlacklist::purgeExpired();

        $this->success([
            'token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) Env::get('JWT_EXPIRY', '3600'),
        ], 'Token berhasil diperbarui');
    }

    public function logout(): void
    {
        $userId = $GLOBALS['auth_user']['id'] ?? null;
        $payload = $GLOBALS['auth_payload'] ?? null;

        // Revoke token saat ini agar tidak dapat dipakai lagi setelah logout.
        if (isset($payload['jti']) && isset($payload['exp'])) {
            JwtBlacklist::revoke((string) $payload['jti'], (int) $payload['exp'], $userId !== null ? (int) $userId : null);
        }
        JwtBlacklist::purgeExpired();

        ActivityLog::log($userId, 'logout', null, null, 'API logout');

        $this->success([], 'Logout berhasil');
    }

    public function changePassword(): void
    {
        $authUser = $GLOBALS['auth_user'] ?? null;
        if ($authUser === null) {
            $this->error('Unauthenticated', 'User tidak terautentikasi.', [], 401);
            return;
        }

        $currentPassword = Request::input('current_password', '');
        $newPassword = Request::input('new_password', '');
        $newPasswordConfirmation = Request::input('new_password_confirmation', '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirmation === '') {
            $this->error('ValidationError', 'Semua field harus diisi.', [], 422);
            return;
        }

        if ($newPassword !== $newPasswordConfirmation) {
            $this->error('ValidationError', 'Konfirmasi password tidak cocok.', [], 422);
            return;
        }

        $validation = PasswordValidator::validate($newPassword);
        if (!$validation['valid']) {
            $this->error('ValidationError', implode('; ', $validation['errors']), [], 422);
            return;
        }

        $user = User::find((int) $authUser['id']);
        if ($user === null) {
            $this->error('Unauthenticated', 'User tidak ditemukan.', [], 401);
            return;
        }

        if (!User::verifyPassword($currentPassword, $user['password'])) {
            $this->error('ValidationError', 'Password saat ini tidak cocok.', [], 422);
            return;
        }

        $hash = User::hashPassword($newPassword);
        User::updatePassword((int) $user['id'], $hash);

        // Kebijakan: perubahan password mencabut seluruh token aktif user.
        // `token_version` dinaikkan sehingga semua JWT lama (klaim `ver`) ditolak
        // middleware; token saat ini juga di-blacklist via `jti`.
        User::bumpTokenVersion((int) $user['id']);

        $payload = $GLOBALS['auth_payload'] ?? null;
        if (isset($payload['jti']) && isset($payload['exp'])) {
            JwtBlacklist::revoke((string) $payload['jti'], (int) $payload['exp'], (int) $user['id']);
        }
        JwtBlacklist::purgeExpired();

        ActivityLog::log((int) $user['id'], 'password_changed', 'users', (int) $user['id'], 'Password diubah via API (seluruh token dicabut)');

        $this->success([], 'Password berhasil diubah');
    }
}
