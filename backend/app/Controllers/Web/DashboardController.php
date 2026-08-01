<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        try {
            $tahun = DashboardService::validateTahun($tahun);
        } catch (\DomainException) {
            $tahun = (int) date('Y');
        }

        $service = new DashboardService($role, $userId, $tahun);
        $stats = $service->getStats();

        $data = [
            'pageTitle' => 'Dashboard — JAGAPADI',
            'stats' => $stats,
            'tahun' => $tahun,
            'role' => $role,
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'username' => $_SESSION['username'] ?? '',
        ];

        $this->view('dashboard/index', $data);
    }

    public function statsJson(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $data = $service->getStats();
            $this->jsonResponse($data);
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function chartsHamaJson(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $this->jsonResponse($service->getChartsHama());
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function chartsIrigasiJson(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $this->jsonResponse($service->getChartsIrigasi());
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function mapHamaJson(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));
        $status = $_GET['status'] ?? 'aktif';
        $limit = min(1000, max(1, (int) ($_GET['limit'] ?? 500)));

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $this->jsonResponse($service->getMapHama($status, $limit));
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function mapIrigasiJson(): void
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? date('Y'));
        $status = $_GET['status'] ?? 'aktif';
        $limit = min(1000, max(1, (int) ($_GET['limit'] ?? 500)));

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $this->jsonResponse($service->getMapIrigasi($status, $limit));
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    private function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
