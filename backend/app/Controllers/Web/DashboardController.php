<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Logger;
use App\Models\ActivityLog;
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

        $scope = $role === 'admin' ? 'all_data' : 'own_data';
        $this->logDashboardAccess($userId, $role, 'web_dashboard_index', $scope, ['tahun' => $tahun]);

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

        $masterOptId = isset($_GET['master_opt_id']) && $_GET['master_opt_id'] !== '' ? (int) $_GET['master_opt_id'] : null;
        $kecamatanId = isset($_GET['kecamatan_id']) && $_GET['kecamatan_id'] !== '' ? (int) $_GET['kecamatan_id'] : null;
        $desaId = isset($_GET['desa_id']) && $_GET['desa_id'] !== '' ? (int) $_GET['desa_id'] : null;

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $data = $service->getMapHama($status, $limit, $masterOptId, $kecamatanId, $desaId);

            $scope = $role === 'admin' ? 'all_data' : 'own_data';
            $this->logDashboardAccess($userId, $role, 'web_dashboard_map_hama', $scope, [
                'tahun' => $tahun,
                'status' => $status,
                'master_opt_id' => $masterOptId,
                'kecamatan_id' => $kecamatanId,
                'desa_id' => $desaId,
            ]);

            $this->jsonResponse($data);
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

        $kecamatanId = isset($_GET['kecamatan_id']) && $_GET['kecamatan_id'] !== '' ? (int) $_GET['kecamatan_id'] : null;
        $desaId = isset($_GET['desa_id']) && $_GET['desa_id'] !== '' ? (int) $_GET['desa_id'] : null;
        $kondisiFisik = isset($_GET['kondisi_fisik']) && $_GET['kondisi_fisik'] !== '' ? (string) $_GET['kondisi_fisik'] : null;

        try {
            $tahun = DashboardService::validateTahun($tahun);
            $service = new DashboardService($role, $userId, $tahun);
            $data = $service->getMapIrigasi($status, $limit, $kecamatanId, $desaId, $kondisiFisik);

            $scope = $role === 'admin' ? 'all_data' : 'own_data';
            $this->logDashboardAccess($userId, $role, 'web_dashboard_map_irigasi', $scope, [
                'tahun' => $tahun,
                'status' => $status,
                'kecamatan_id' => $kecamatanId,
                'desa_id' => $desaId,
                'kondisi_fisik' => $kondisiFisik,
            ]);

            $this->jsonResponse($data);
        } catch (\DomainException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    private function logDashboardAccess(int $userId, string $role, string $action, string $scope, array $details = []): void
    {
        $description = sprintf('Akses %s oleh user #%d (%s) dengan scope %s', $action, $userId, $role, $scope);
        if (!empty($details)) {
            $description .= ' | Filter: ' . json_encode($details);
        }

        Logger::info("Dashboard Access Log: {$description}", ['user_id' => $userId, 'role' => $role, 'scope' => $scope]);
        try {
            ActivityLog::log($userId, $action, 'dashboard', null, $description);
        } catch (\Throwable $e) {
            // Ignore DB log failures if table unavailable in tests
        }
    }

    private function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
