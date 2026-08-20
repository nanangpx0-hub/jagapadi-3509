<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Env;
use App\Core\Security;
use App\Core\Request;
use App\Core\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Helpers\PasswordValidator;

class AuthController extends Controller
{
    public function showLoginForm(): void
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard');
            exit;
        }
        $this->view('auth/login', ['pageTitle' => 'Login'], 'auth');
    }

    public function login(): void
    {
        $expiredReason = $_GET['reason'] ?? '';
        if ($expiredReason === 'expired' && empty($_SESSION['error'])) {
            $_SESSION['error'] = 'Sesi Anda berakhir karena tidak aktif. Silakan login kembali.';
        }

        $username = Request::input('username', '');
        $password = Request::input('password', '');

        if ($username === '' || $password === '') {
            $_SESSION['flash_error'] = 'Username dan password harus diisi.';
            header('Location: /login');
            return;
        }

        if (Security::checkBruteForce('login_' . $username, 5, 900)) {
            $_SESSION['error'] = 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.';
            header('Location: /login');
            return;
        }

        $user = User::findByUsername($username);

        if ($user === null || !User::verifyPassword($password, $user['password'])) {
            ActivityLog::log(null, 'login_failed', 'users', null, "Login gagal untuk username: $username");
            $_SESSION['flash_error'] = 'Username atau password salah.';
            header('Location: /login');
            return;
        }

        if (!User::isActive($user)) {
            ActivityLog::log((int) $user['id'], 'login_failed', 'users', $user['id'], 'Akun tidak aktif');
            $_SESSION['flash_error'] = 'Username atau password salah.';
            header('Location: /login');
            return;
        }

        Security::regenerateSession();
        Security::regenerateCsrfToken();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['must_change_password'] = (bool) ($user['must_change_password'] ?? false);
        $_SESSION['login_at'] = time();

        ActivityLog::log((int) $user['id'], 'login_success', 'users', (int) $user['id'], 'Login web berhasil');

        Security::clearBruteForce('login_' . $username);

        if ($_SESSION['must_change_password']) {
            $_SESSION['flash_warning'] = 'Anda harus mengganti password sebelum melanjutkan.';
            header('Location: /password/change');
            return;
        }

        $_SESSION['flash_success'] = 'Selamat datang, ' . Security::e($user['nama_lengkap']) . '!';
        header('Location: /dashboard');
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        ActivityLog::log($userId, 'logout', null, null, 'Logout web');

        Security::destroySession();

        header('Location: /login');
        exit;
    }
}
