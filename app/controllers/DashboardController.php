<?php
class DashboardController extends Controller {
    private $laporanModel;
    private $optModel;
    private $irigasiModel;
    private $lainnyaModel;
    private CacheManager $cache;
    
    public function __construct() {
        $this->laporanModel = $this->model('LaporanHama');
        $this->optModel = $this->model('MasterOpt');
        $this->irigasiModel = $this->model('LaporanIrigasi');
        $this->lainnyaModel = $this->model('LaporanLainnya');
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Get user ID for filtering (null for admin/operator/viewer, user_id for petugas)
     * @return int|null
     */
    private function getFilterUserId(): ?int {
        $role = $_SESSION['role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        // Only filter by user_id for petugas role
        if ($role === 'petugas' && $userId !== null) {
            return (int)$userId;
        }
        
        // Admin, operator, viewer see all data
        return null;
    }
    
    /**
     * Log data access for monitoring
     */
    private function logDataAccess(string $page, ?int $userId = null): void {
        $role = $_SESSION['role'] ?? 'unknown';
        $username = $_SESSION['username'] ?? 'unknown';
        $filter = $userId !== null ? "filtered by user_id={$userId}" : "all data";
        
        error_log(sprintf(
            "Dashboard Access: page=%s, user=%s (role=%s, user_id=%s), %s",
            $page,
            $username,
            $role,
            $userId ?? 'null',
            $filter
        ));
    }

    private function dashboardCacheKey(string $section, ?int $userId = null, array $params = []): string {
        // Standardisasi kontrak ringkasan dashboard: dash_summary_{role}_{userId}
        if ($section === 'stats') {
            $role = $_SESSION['role'] ?? 'guest';
            return "dash_summary_{$role}_" . ($userId ?? 'all');
        }

        $scope = $userId === null ? 'all' : 'user_' . $userId;
        $paramHash = empty($params) ? 'default' : sha1(json_encode($params));

        return "dashboard:{$scope}:{$section}:{$paramHash}";
    }
    
    public function index() {
        $this->checkAuth();
        
        $filterUserId = $this->getFilterUserId();
        $this->logDataAccess('dashboard/index', $filterUserId);

        $year = (int)date('Y');
        $stats = $this->cache->remember(
            $this->dashboardCacheKey('stats', $filterUserId),
            fn() => $this->laporanModel->getDashboardStats($filterUserId),
            60
        );
        $topPests = $this->cache->remember(
            $this->dashboardCacheKey('top_pests', $filterUserId, ['limit' => 5]),
            fn() => $this->laporanModel->getTopPests(5, $filterUserId),
            120
        );
        $monthlyStats = $this->cache->remember(
            $this->dashboardCacheKey('monthly_stats', $filterUserId, ['year' => $year]),
            fn() => $this->laporanModel->getMonthlyStats($year, $filterUserId),
            300
        );
        $recentReports = $this->cache->remember(
            $this->dashboardCacheKey('recent_reports', $filterUserId, ['limit' => 5]),
            fn() => $this->laporanModel->getRecentForDashboard($filterUserId, 5),
            30
        );

        $isPetugas = ($_SESSION['role'] ?? '') === 'petugas' && $filterUserId !== null;
        $petugasDashboard = [];
        if ($isPetugas) {
            $hamaStats = $this->laporanModel->getDashboardStats($filterUserId, true);
            $petugasDashboard = [
                'hama_summary' => [
                    'Draf' => $hamaStats['draf'] ?? 0,
                    'Submitted' => $hamaStats['pending_verifikasi'] ?? 0,
                    'Diverifikasi' => $hamaStats['terverifikasi'] ?? 0,
                    'Ditolak' => $hamaStats['ditolak'] ?? 0,
                ],
                'irigasi_summary' => $this->irigasiModel->getStatusSummary($filterUserId),
                'lainnya_summary' => $this->lainnyaModel->getStatusSummary($filterUserId),
                'recent_hama' => $this->laporanModel->getRecentForDashboard($filterUserId, 3),
                'recent_irigasi' => $this->irigasiModel->getRecentForDashboard($filterUserId, 3),
                'recent_lainnya' => $this->lainnyaModel->getRecentForDashboard($filterUserId, 3),
                'lainnya_chart' => $this->lainnyaModel->getChartSummary($filterUserId, $year, false),
            ];
        }
        
        $data = [
            'title' => 'Dashboard',
            'stats' => $stats,
            'topPests' => $topPests,
            'monthlyStats' => $monthlyStats,
            'recentReports' => $recentReports,
            'isPetugasDashboard' => $isPetugas,
            'petugasDashboard' => $petugasDashboard,
        ];
        
        $this->view('dashboard/index', $data);
    }

    public function chartsLainnya(): void {
        $this->checkRole(['petugas']);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $year = (int) ($_GET['tahun'] ?? date('Y'));
        $currentYear = (int) date('Y');
        if ($year < 2000 || $year > $currentYear + 1) {
            $year = $currentYear;
        }
        $includeDraft = filter_var($_GET['include_draft'] ?? false, FILTER_VALIDATE_BOOLEAN);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $this->lainnyaModel->getChartSummary(
            $userId,
            $year,
            $includeDraft
        )], JSON_UNESCAPED_UNICODE);
    }
    
    public function map() {
        $this->checkAuth();
        
        $filterUserId = $this->getFilterUserId();
        $this->logDataAccess('dashboard/map', $filterUserId);
        
        $mapData = $this->laporanModel->getMapData($filterUserId);
        
        $data = [
            'title' => 'Peta Sebaran Hama',
            'mapData' => $mapData
        ];
        
        $this->view('dashboard/map', $data);
    }
    
    public function charts() {
        $this->checkAuth();
        
        try {
            $filterUserId = $this->getFilterUserId();
            $this->logDataAccess('dashboard/charts', $filterUserId);
            
            $year = date('Y');
            
            // Get comprehensive statistics with user filter
            $monthlyStats = $this->laporanModel->getMonthlyStats($year, $filterUserId);
            $topPests = $this->laporanModel->getTopPests(10, $filterUserId);
            $severityStats = $this->laporanModel->getSeverityDistribution($filterUserId);
            $areaStats = $this->laporanModel->getAreaStatsByMonth($year, $filterUserId);
            $topKecamatan = $this->laporanModel->getTopKecamatan(5, $filterUserId);
            
            // Data integrity check
            $this->validateChartData($monthlyStats, $topPests, $severityStats, $areaStats);
            
            $data = [
                'title' => 'Grafik & Statistik',
                'monthlyStats' => $monthlyStats,
                'topPests' => $topPests,
                'severityStats' => $severityStats,
                'areaStats' => $areaStats,
                'topKecamatan' => $topKecamatan,
                'year' => $year
            ];
            
            $this->view('dashboard/charts', $data);
            
        } catch (Exception $e) {
            error_log("Dashboard Charts Error: " . $e->getMessage());
            
            // Fallback data
            $data = [
                'title' => 'Grafik & Statistik',
                'monthlyStats' => [],
                'topPests' => [],
                'severityStats' => [],
                'areaStats' => [],
                'topKecamatan' => [],
                'year' => date('Y'),
                'error' => 'Terjadi kesalahan saat memuat data grafik'
            ];
            
            $this->view('dashboard/charts', $data);
        }
    }
    
    /**
     * AJAX endpoint for chart data
     */
    public function getChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $filterUserId = $this->getFilterUserId();
            $type = $_GET['type'] ?? 'monthly';
            $year = (int) ($_GET['year'] ?? date('Y'));
            $currentYear = (int) date('Y');
            if ($year < 2000 || $year > $currentYear + 1) {
                $year = $currentYear;
            }
            
            $response = [
                'success' => true,
                'data' => [],
                'timestamp' => time()
            ];
            
            switch ($type) {
                case 'stats':
                    $response['data'] = $this->cache->remember(
                        $this->dashboardCacheKey('stats', $filterUserId),
                        fn() => $this->laporanModel->getDashboardStats($filterUserId),
                        60
                    );
                    break;

                case 'monthly':
                    $response['data'] = $this->cache->remember(
                        $this->dashboardCacheKey('monthly_stats', $filterUserId, ['year' => (int)$year]),
                        fn() => $this->laporanModel->getMonthlyStats((int)$year, $filterUserId),
                        300
                    );
                    break;
                    
                case 'topPests':
                    $limit = $_GET['limit'] ?? 10;
                    $limit = min(50, max(1, (int)$limit));
                    $response['data'] = $this->cache->remember(
                        $this->dashboardCacheKey('top_pests', $filterUserId, ['limit' => $limit]),
                        fn() => $this->laporanModel->getTopPests($limit, $filterUserId),
                        120
                    );
                    break;
                    
                case 'severity':
                    $response['data'] = $this->laporanModel->getSeverityDistribution($filterUserId);
                    break;
                    
                case 'area':
                    $response['data'] = $this->laporanModel->getAreaStatsByMonth((int)$year, $filterUserId);
                    break;

                case 'kecamatan':
                    $response['data'] = $this->laporanModel->getTopKecamatan(5, $filterUserId);
                    break;
                    
                default:
                    throw new Exception('Invalid chart type');
            }
            
            // Validate data integrity
            if (empty($response['data']) && $type !== 'monthly') {
                $response['warning'] = 'Tidak ada data tersedia';
            }
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            error_log("Chart Data Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Gagal memuat data grafik',
                'message' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Silakan hubungi administrator'
            ]);
        }
        exit;
    }
    
    /**
     * Validate chart data integrity
     */
    private function validateChartData(array $monthlyStats, array $topPests, array $severityStats, array $areaStats): bool {
        $errors = [];
        
        // Check monthly stats structure
        if (!is_array($monthlyStats)) {
            $errors[] = 'Invalid monthly stats format';
        }
        
        // Check top pests data
        if (!is_array($topPests)) {
            $errors[] = 'Invalid top pests format';
        }
        
        // Check severity stats
        if (!is_array($severityStats)) {
            $errors[] = 'Invalid severity stats format';
        }
        
        // Check area stats
        if (!is_array($areaStats)) {
            $errors[] = 'Invalid area stats format';
        }
        
        if (!empty($errors)) {
            error_log("Chart Data Validation Errors: " . implode(', ', $errors));
            throw new Exception('Data validation failed');
        }
        
        return true;
    }
}
