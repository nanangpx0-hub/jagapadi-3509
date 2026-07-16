<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Models\User;
use App\Models\ActivityLog;

class MeController extends BaseApiController
{
    public function index(): void
    {
        $user = $GLOBALS['auth_user'] ?? null;
        if ($user === null) {
            $this->error('Unauthenticated', 'User tidak terautentikasi.', [], 401);
            return;
        }

        $this->success(User::toPublicArray($user));
    }
}
