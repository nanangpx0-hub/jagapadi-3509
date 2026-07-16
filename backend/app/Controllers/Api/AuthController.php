<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\Helpers\RateLimiter;
use App\Helpers\PasswordValidator;

class AuthController extends BaseApiController
{
    public function login(): void
    {
        $ip = Request::ip();
        $maxAttempts = (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
        $decay = (int) ($_ENV['LOGIN_DECAY_SECONDS'] ?? 900);

        if (!RateLimiter::attempt('login', "api_$ip", $maxAttempts, $decay)) {
            $this->error('TooManyRequests', 'Terlalu banyak percobaan login. Coba lagi nanti.', [], 429);
            return;
        }

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
            $this->error('Unauthorized', 'Akun Anda tidak aktif. Hubungi administrator.', [], 401);
            return;
        }

        $jwtExpiry = (int) Env::get('JWT_EXPIRY', '3600');
        $token = Jwt::encode([
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'username' => $user['username'],
            'exp' => time() + $jwtExpiry,
        ]);

        RateLimiter::reset('login', "api_$ip");
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

        $newToken = Jwt::refresh($token);
        if ($newToken === null) {
            $this->error('TokenInvalid', 'Token tidak valid atau sudah kedaluwarsa.', [], 401);
            return;
        }

        $this->success([
            'token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) Env::get('JWT_EXPIRY', '3600'),
        ], 'Token berhasil diperbarui');
    }

    public function logout(): void
    {
        $userId = $GLOBALS['auth_user']['id'] ?? null;
        ActivityLog::log($userId, 'logout', null, null, 'API logout');

        $this->success([], 'Logout berhasil');
    }

    public function changePassword(): void
    {
        $user = $GLOBALS['auth_user'] ?? null;
        if ($user === null) {
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

        if (!User::verifyPassword($currentPassword, $user['password'])) {
            $this->error('ValidationError', 'Password saat ini tidak cocok.', [], 422);
            return;
        }

        $hash = User::hashPassword($newPassword);
        User::updatePassword((int) $user['id'], $hash);

        ActivityLog::log((int) $user['id'], 'password_changed', 'users', (int) $user['id'], 'Password diubah via API');

        $this->success([], 'Password berhasil diubah');
    }
}
