<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Models\User;

class ProfileController extends Controller
{
    public function index(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            $this->redirect('/login');
            return;
        }

        $user = User::find((int) $userId);
        if ($user === null) {
            $this->redirect('/login');
            return;
        }

        $this->view('profile/index', [
            'pageTitle' => 'Profil Saya',
            'user' => User::toPublicArray($user),
        ]);
    }
}
