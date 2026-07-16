<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Security;
use App\Core\Request;
use App\Core\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Helpers\PasswordValidator;

class PasswordController extends Controller
{
    public function showChangeForm(): void
    {
        $this->view('auth/change_password', ['pageTitle' => 'Ganti Password'], 'main');
    }

    public function change(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            header('Location: /login');
            return;
        }

        $currentPassword = Request::input('current_password', '');
        $newPassword = Request::input('new_password', '');
        $newPasswordConfirmation = Request::input('new_password_confirmation', '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirmation === '') {
            $_SESSION['flash_error'] = 'Semua field harus diisi.';
            header('Location: /password/change');
            return;
        }

        if ($newPassword !== $newPasswordConfirmation) {
            $_SESSION['flash_error'] = 'Konfirmasi password tidak cocok.';
            header('Location: /password/change');
            return;
        }

        $validation = PasswordValidator::validate($newPassword);
        if (!$validation['valid']) {
            $_SESSION['flash_error'] = implode('<br>', $validation['errors']);
            header('Location: /password/change');
            return;
        }

        $user = User::find($userId);
        if ($user === null) {
            header('Location: /login');
            return;
        }

        if (!User::verifyPassword($currentPassword, $user['password'])) {
            $_SESSION['flash_error'] = 'Password saat ini tidak cocok.';
            header('Location: /password/change');
            return;
        }

        $hash = User::hashPassword($newPassword);
        User::updatePassword($userId, $hash);

        Security::regenerateSession();
        Security::regenerateCsrfToken();

        $_SESSION['must_change_password'] = false;
        $_SESSION['flash_success'] = 'Password berhasil diubah.';

        ActivityLog::log($userId, 'password_changed', 'users', $userId, 'Password diubah via web');

        header('Location: /dashboard');
    }
}
