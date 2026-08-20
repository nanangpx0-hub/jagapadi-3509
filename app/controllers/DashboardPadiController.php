<?php

class DashboardPadiController extends Controller {
    private DashboardPadi $dashboardPadiModel;
    private CacheManager $cache;

    public function __construct() {
        parent::__construct();
        $this->dashboardPadiModel = $this->model('DashboardPadi');
        $this->cache = CacheManager::getInstance();
    }

    public function index(): void {
        $this->checkAuth();
        
        $role = $_SESSION['role'] ?? '';
        if ($role === 'petugas') {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $data = [];

        try {
            $filterUserId = $this->getFilterUserId();
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

            // Caching: cek cache sebelum query database
            $cacheKey = 'dashboard_padi_index_' . ($filterUserId ?? 'all') . '_' . $selectedYear . '_' . ($selectedKecamatanId ?? 'all');
            $cached = $this->cache->isAvailable() ? $this->cache->get($cacheKey) : null;
            if (is_array($cached) && !empty($cached)) {
                $data = $cached;
            } else {
                $data = [
                    'title' => 'Dashboard Padi',
                    'availableYears' => $availableYears,
                    'selectedYear' => $selectedYear,
                    'kecamatanList' => $kecamatanList,
                    'selectedKecamatanId' => $selectedKecamatanId,
                    'selectedKecamatan' => $selectedKecamatan,
                    'filterUserId' => $filterUserId,
                    'summary' => $this->dashboardPadiModel->getSummary($selectedYear, $selectedKecamatanId, $filterUserId),
                    'trend' => $this->dashboardPadiModel->getTrend($selectedYear, $selectedKecamatanId, $filterUserId),
                    'kecamatanBreakdown' => $this->dashboardPadiModel->getKecamatanBreakdown($selectedYear, $filterUserId),
                    'statusBreakdown' => $this->dashboardPadiModel->getStatusBreakdown($selectedYear, $selectedKecamatanId, $filterUserId),
                ];

                // Simpan ke cache untuk 5 menit
                $this->cache->set($cacheKey, $data, 300);
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                'DashboardPadi::index error: %s | user_id=%s role=%s',
                $e->getMessage(),
                $_SESSION['user_id'] ?? 'null',
                $_SESSION['role'] ?? 'null'
            ));

            $data = [
                'title' => 'Dashboard Padi',
                'availableYears' => $availableYears ?? [],
                'selectedYear' => $selectedYear ?? (int)date('Y'),
                'kecamatanList' => $kecamatanList ?? [],
                'selectedKecamatanId' => $selectedKecamatanId ?? null,
                'selectedKecamatan' => $selectedKecamatan ?? null,
                'filterUserId' => $filterUserId ?? null,
                'summary' => [],
                'trend' => [],
                'kecamatanBreakdown' => [],
                'statusBreakdown' => [],
                'flash_error' => 'Gagal memuat data dashboard. Silakan muat ulang halaman.',
            ];
        }

        $this->view('dashboard_padi/index', $data);
    }

    private function getFilterUserId(): ?int {
        $role = $_SESSION['role'] ?? '';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        return ($role === 'petugas' && $userId > 0) ? $userId : null;
    }

    private function resolveYear(array $availableYears): int {
        $currentYear = (int) date('Y');
        $year = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;

        if ($year >= 2000 && $year <= $currentYear + 1) {
            return $year;
        }

        // Fallback ke tahun pertama yang valid di list, atau tahun sekarang
        foreach ($availableYears as $y) {
            $y = (int) $y;
            if ($y >= 2000 && $y <= $currentYear + 1) {
                return $y;
            }
        }

        return $currentYear;
    }

    private function resolveKecamatanId(): ?int {
        if (!isset($_GET['kecamatan_id']) || trim((string)$_GET['kecamatan_id']) === '') {
            return null;
        }
        $kecamatanId = (int) $_GET['kecamatan_id'];
        // Tolak nilai negatif atau terlalu besar (BIGINT UNSIGNED max ~1.8×10^19)
        return ($kecamatanId > 0 && $kecamatanId <= PHP_INT_MAX) ? $kecamatanId : null;
    }
}
