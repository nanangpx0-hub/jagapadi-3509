<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Services\DashboardService;

class DashboardController extends BaseApiController
{
    public function stats(): void
    {
        try {
            $currentUser = $GLOBALS['auth_user'];
            $tahun = DashboardService::validateTahun((int) ($_GET['tahun'] ?? date('Y')));
            $includeDraft = isset($_GET['include_draft']) ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN) : false;

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun, $includeDraft);
            $data = $service->getStats();

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

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun);
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

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun);
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

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun);
            $data = $service->getMapHama($status, $limit);

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

            $service = new DashboardService($currentUser['role'], (int) $currentUser['id'], $tahun);
            $data = $service->getMapIrigasi($status, $limit);

            $this->success($data, 'Map irigasi');
        } catch (\DomainException $e) {
            $this->error('ValidationError', $e->getMessage(), [], 422);
        }
    }
}
