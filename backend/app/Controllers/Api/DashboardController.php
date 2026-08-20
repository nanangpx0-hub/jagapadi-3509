<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Database;
use App\Core\Logger;
use App\Models\ActivityLog;
use App\Services\DashboardService;

class DashboardController extends BaseApiController
{
    public function chartsLainnya(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            if (($currentUser['role'] ?? '') !== 'petugas') {
                $this->error('Forbidden', 'Endpoint ini khusus Petugas.', [], 403);
                return;
            }

            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $includeDraft = isset($_GET['include_draft'])
                ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN)
                : false;
            $statusSql = $includeDraft ? '' : " AND ll.status <> 'draft'";
            $pdo = Database::connect();
            $params = [':user_id' => (int) $currentUser['id'], ':tahun' => $tahun];

            $trend = $pdo->prepare(
                "SELECT MONTH(ll.tanggal_kejadian) AS bulan, COUNT(*) AS total
                 FROM laporan_lainnya ll
                 WHERE ll.user_id = :user_id AND YEAR(ll.tanggal_kejadian) = :tahun{$statusSql}
                 GROUP BY MONTH(ll.tanggal_kejadian) ORDER BY bulan"
            );
            $trend->execute($params);
            $types = $pdo->prepare(
                "SELECT COALESCE(mjl.nama, 'Tanpa Jenis') AS label, COUNT(*) AS total
                 FROM laporan_lainnya ll
                 LEFT JOIN master_jenis_laporan mjl ON ll.jenis_id = mjl.id
                 WHERE ll.user_id = :user_id AND YEAR(ll.tanggal_kejadian) = :tahun{$statusSql}
                 GROUP BY ll.jenis_id, mjl.nama ORDER BY total DESC"
            );
            $types->execute($params);

            $this->success([
                'trend' => $trend->fetchAll(),
                'by_type' => $types->fetchAll(),
                'tahun' => $tahun,
                'include_draft' => $includeDraft,
            ], 'Chart laporan lainnya');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }

    public function stats(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getStats();

            $scope = $currentUser['role'] === 'admin' ? 'all_data' : 'own_data';
            $this->logDashboardAccess((int) $currentUser['id'], $currentUser['role'], 'api_dashboard_stats', $scope, ['tahun' => $tahun, 'include_draft' => $includeDraft]);

            $this->success($data['hama'] !== [] || $data['irigasi'] !== [] ? $data : $data, 'Dashboard stats');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }

    public function chartsHama(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getChartsHama();

            $this->success($data, 'Chart hama');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }

    public function chartsIrigasi(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getChartsIrigasi();

            $this->success($data, 'Chart irigasi');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }

    public function mapHama(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $status = $_GET['status'] ?? 'aktif';
            $limit = min(1000, max(1, (int) ($_GET['limit'] ?? 500)));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $masterOptId = isset($_GET['master_opt_id']) && $_GET['master_opt_id'] !== '' ? (int) $_GET['master_opt_id'] : null;
            $kecamatanId = isset($_GET['kecamatan_id']) && $_GET['kecamatan_id'] !== '' ? (int) $_GET['kecamatan_id'] : null;
            $desaId = isset($_GET['desa_id']) && $_GET['desa_id'] !== '' ? (int) $_GET['desa_id'] : null;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getMapHama($status, $limit, $masterOptId, $kecamatanId, $desaId);

            $scope = $currentUser['role'] === 'admin' ? 'all_data' : 'own_data';
            $this->logDashboardAccess((int) $currentUser['id'], $currentUser['role'], 'api_dashboard_map_hama', $scope, [
                'tahun' => $tahun,
                'status' => $status,
                'master_opt_id' => $masterOptId,
                'kecamatan_id' => $kecamatanId,
                'desa_id' => $desaId,
            ]);

            $this->success($data, 'Map hama');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }

    public function mapIrigasi(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $status = $_GET['status'] ?? 'aktif';
            $limit = min(1000, max(1, (int) ($_GET['limit'] ?? 500)));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $kecamatanId = isset($_GET['kecamatan_id']) && $_GET['kecamatan_id'] !== '' ? (int) $_GET['kecamatan_id'] : null;
            $desaId = isset($_GET['desa_id']) && $_GET['desa_id'] !== '' ? (int) $_GET['desa_id'] : null;
            $kondisiFisik = isset($_GET['kondisi_fisik']) && $_GET['kondisi_fisik'] !== '' ? (string) $_GET['kondisi_fisik'] : null;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getMapIrigasi($status, $limit, $kecamatanId, $desaId, $kondisiFisik);

            $scope = $currentUser['role'] === 'admin' ? 'all_data' : 'own_data';
            $this->logDashboardAccess((int) $currentUser['id'], $currentUser['role'], 'api_dashboard_map_irigasi', $scope, [
                'tahun' => $tahun,
                'status' => $status,
                'kecamatan_id' => $kecamatanId,
                'desa_id' => $desaId,
                'kondisi_fisik' => $kondisiFisik,
            ]);

            $this->success($data, 'Map irigasi');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
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
}
