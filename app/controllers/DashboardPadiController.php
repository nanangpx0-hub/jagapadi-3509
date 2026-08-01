<?php

class DashboardPadiController extends Controller {
    private DashboardPadi $dashboardPadiModel;

    public function __construct() {
        parent::__construct();
        $this->dashboardPadiModel = $this->model('DashboardPadi');
    }

    public function index(): void {
        $this->checkAuth();

        $availableYears = $this->dashboardPadiModel->getAvailableYears();
        $selectedYear = $this->resolveYear($availableYears);
        $kecamatanList = $this->dashboardPadiModel->getKecamatanList();
        $selectedKecamatanId = $this->resolveKecamatanId();
        $selectedKecamatan = null;

        if ($selectedKecamatanId !== null) {
            $selectedKecamatan = $this->dashboardPadiModel->getKecamatanById($selectedKecamatanId);
            if ($selectedKecamatan === null) {
                $selectedKecamatanId = null;
            }
        }

        $data = [
            'title' => 'Dashboard Padi',
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'kecamatanList' => $kecamatanList,
            'selectedKecamatanId' => $selectedKecamatanId,
            'selectedKecamatan' => $selectedKecamatan,
            'summary' => $this->dashboardPadiModel->getSummary($selectedYear, $selectedKecamatanId),
            'trend' => $this->dashboardPadiModel->getTrend($selectedYear, $selectedKecamatanId),
            'kecamatanBreakdown' => $this->dashboardPadiModel->getKecamatanBreakdown($selectedYear),
            'statusBreakdown' => $this->dashboardPadiModel->getStatusBreakdown($selectedYear, $selectedKecamatanId),
        ];

        $this->view('dashboard_padi/index', $data);
    }

    private function resolveYear(array $availableYears): int {
        $year = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;
        $currentYear = (int) date('Y');

        if ($year >= 2000 && $year <= $currentYear + 1) {
            return $year;
        }

        return (int) ($availableYears[0] ?? $currentYear);
    }

    private function resolveKecamatanId(): ?int {
        if (!isset($_GET['kecamatan_id']) || $_GET['kecamatan_id'] === '') {
            return null;
        }

        $kecamatanId = (int) $_GET['kecamatan_id'];

        return $kecamatanId > 0 ? $kecamatanId : null;
    }
}
