<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Models\ActivityLog;

class DashboardController extends Controller
{
    public function index(): void
    {
        $data = [
            'pageTitle' => 'Dashboard',
            'username' => $_SESSION['username'] ?? '',
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'role' => $_SESSION['role'] ?? '',
        ];

        $this->view('dashboard/index', $data);
    }
}
