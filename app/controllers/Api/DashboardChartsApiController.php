<?php
/**
 * Dashboard Charts API Controller
 * API endpoints untuk data grafik dashboard
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/services/DashboardDataAggregator.php';

class DashboardChartsApiController extends BaseApiController {
    private $aggregator;
    
    public function __construct() {
        // Authentication middleware has already populated $_SESSION. These
        // chart endpoints are read-only, so release PHP's exclusive session
        // lock before running aggregate queries. Otherwise navigation in the
        // same browser session is blocked until the chart request finishes.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->aggregator = new DashboardDataAggregator();
    }
    
    // =========================================
    // DEFENSE-IN-DEPTH AUTHENTICATION
    // =========================================
    
    private function assertAuthenticated(): void {
        if (empty($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }
    }
    
    private function isDevEnvironment(): bool {
        return in_array(
            strtolower((string)(getenv('APP_ENV') ?: 'production')),
            ['local', 'development', 'dev'],
            true
        );
    }
    
    private function handleApiException(string $label, Throwable $e): never {
        error_log(sprintf(
            '[DashboardChartsApi::%s] %s | user_id=%s role=%s',
            $label,
            $e->getMessage(),
            $_SESSION['user_id'] ?? 'null',
            $_SESSION['role'] ?? 'null'
        ));
        $message = $this->isDevEnvironment()
            ? "Gagal memuat data {$label}: " . $e->getMessage()
            : "Gagal memuat data {$label}.";
        $this->errorResponse($message, 500);
        exit;
    }
    
    private function resolveYear(): int {
        $raw = $_GET['year'] ?? date('Y');
        $year = filter_var($raw, FILTER_VALIDATE_INT);
        $current = (int) date('Y');
        return ($year !== false && $year >= 2000 && $year <= $current + 1)
            ? $year
            : $current;
    }
    
    private function resolveDays(int $default = 30, int $min = 1, int $max = 365): int {
        $raw = $_GET['days'] ?? $default;
        $days = filter_var($raw, FILTER_VALIDATE_INT);
        return ($days !== false && $days >= $min && $days <= $max) ? $days : $default;
    }
    
    private function resolveMonths(int $default = 6, int $min = 1, int $max = 24): int {
        $raw = $_GET['months'] ?? $default;
        $months = filter_var($raw, FILTER_VALIDATE_INT);
        return ($months !== false && $months >= $min && $months <= $max) ? $months : $default;
    }
    
    // =========================================
    // RATE LIMITING HELPER
    // =========================================
    
    private function assertRateLimit(string $action, int $max = 30, int $window = 60): void {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $key    = "charts_api_{$action}_{$userId}";
        // Rate limiter hanya di development jika Security class ada, di produksi
        // kemungkinan besar middleware rate_limit di Router yang menangani ini.
        // Namun kita tambahkan defense-in-depth di sini dengan fallback aman:
        if (class_exists('Security') && method_exists('Security', 'checkRateLimit')) {
            if (Security::checkRateLimit($key, $max, $window) === true) {
                http_response_code(429);
                header('Retry-After: ' . $window);
                $this->jsonResponse([
                    'success' => false,
                    'error'   => 'Too many requests',
                    'message' => 'Terlalu banyak permintaan. Coba lagi dalam beberapa saat.',
                ], 429);
                exit;
            }
        }
    }
    
    // =========================================
    // RAINFALL ENDPOINT
    // =========================================
    
    /**
     * Get rainfall time-series data
     * GET /api/dashboard/charts/rainfall
     */
    public function rainfall() {
        $this->assertAuthenticated();
        $this->assertRateLimit('rainfall', 30, 60);
        
        try {
            $year = $this->resolveYear();
            $month = null; // full year series, skip monthly filter at controller level
            
            $filters = [
                'year' => $year,
                'month' => $month
            ];
            
            $data = $this->aggregator->getRainfallSummary($filters);
            
            // Format for Chart.js
            $chartData = $this->formatTimeSeriesData($data['monthly'], 'bulan', 'avg_rainfall');
            
            $this->jsonResponse([
                'success' => true,
                'data' => $chartData,
                'statistics' => $data['statistics'],
                'year' => $data['year'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('curah hujan', $e);
        }
    }
    
    // =========================================
    // WIND ENDPOINT
    // =========================================
    
    /**
     * Get wind speed time-series data
     * GET /api/dashboard/charts/wind
     */
    public function wind() {
        $this->assertAuthenticated();
        $this->assertRateLimit('wind', 30, 60);
        
        try {
            $year = $this->resolveYear();
            
            $filters = [
                'year' => $year,
                'month' => null
            ];
            
            $data = $this->aggregator->getWindSummary($filters);
            
            // Format for Chart.js
            $chartData = $this->formatTimeSeriesData($data['monthly'], 'bulan', 'avg_speed');
            
            $this->jsonResponse([
                'success' => true,
                'data' => $chartData,
                'statistics' => $data['statistics'],
                'year' => $data['year'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('kecepatan angin', $e);
        }
    }
    
    // =========================================
    // WEATHER ENDPOINT
    // =========================================
    
    /**
     * Get weather combined data (rainfall + wind)
     * GET /api/dashboard/charts/weather
     */
    public function weather() {
        $this->assertAuthenticated();
        $this->assertRateLimit('weather', 60, 60);
        
        try {
            $year = $this->resolveYear();
            $days = $this->resolveDays(7, 1, 30);  // max 30 hari untuk alert
            
            $filters = [
                'year' => $year,
                'days' => $_GET['days'] ?? 7
            ];
            
            $data = $this->aggregator->getWeatherSummary($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('cuaca', $e);
        }
    }
    
    // =========================================
    // PRICES ENDPOINT
    // =========================================
    
    /**
     * Get price trend data
     * GET /api/dashboard/charts/prices
     */
    public function prices() {
        $this->assertAuthenticated();
        $this->assertRateLimit('prices', 20, 60);
        
        try {
            $months = $this->resolveMonths();
            
            $data = $this->aggregator->getPriceSummary(['months' => $months]);
            
            // Format trend data for multiple line chart
            $chartData = $this->formatPriceTrendData($data['trend']);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'chart' => $chartData,
                    'latest' => $data['latest'],
                    'comparison' => $data['comparison']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('harga komoditas', $e);
        }
    }
    
    // =========================================
    // PRODUCTION ENDPOINT
    // =========================================
    
    /**
     * Get production/BPS data
     * GET /api/dashboard/charts/production
     */
    public function production() {
        $this->assertAuthenticated();
        $this->assertRateLimit('production', 20, 60);
        
        try {
            $year = $this->resolveYear();
            
            $data = $this->aggregator->getProductionSummary(['year' => $year]);
            
            // Format for charts
            $trendChart = $this->formatProductionTrendData($data['trend']);
            $topProducersChart = $this->formatBarChartData(
                $data['topProducers'], 
                'kabupaten', 
                'produksi_gabah'
            );
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'trendChart' => $trendChart,
                    'topProducersChart' => $topProducersChart
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('produksi', $e);
        }
    }
    
    // =========================================
    // HAMA ENDPOINT (scoped untuk petugas)
    // =========================================
    
    /**
     * Get hama/pest statistics data
     * GET /api/dashboard/charts/hama
     */
    public function hama() {
        $this->assertAuthenticated();
        $this->assertRateLimit('hama', 30, 60);
        
        try {
            $year = $this->resolveYear();
            $userId = $this->getPetugasUserId();
            $filters = ['year' => $year];
            if ($userId !== null) {
                $filters['user_id'] = $userId;
            }

            // Dual-scope: admin/operator dapat memfilter per kecamatan dan/atau
            // memasukkan laporan Draf milik sendiri.
            if (isset($_GET['kecamatan_id'])) {
                $filters['kecamatan_id'] = (int) $_GET['kecamatan_id'];
            }
            if (isset($_GET['scope']) && $_GET['scope'] === 'territory') {
                $filters['scope'] = 'territory';
            }
            $includeDraft = isset($_GET['include_draft'])
                ? filter_var($_GET['include_draft'], FILTER_VALIDATE_BOOLEAN)
                : false;
            if ($includeDraft) {
                $filters['include_draft'] = true;
            }
            
            $data = $this->aggregator->getHamaSummary($filters);
            
            // Format distribution for line chart
            $distributionChart = $this->formatTimeSeriesData(
                $data['distribution'], 
                'bulan', 
                'total_laporan'
            );
            
            // Format topOPT for bar chart
            $topOPTChart = $this->formatBarChartData(
                $data['topOPT'], 
                'nama_opt', 
                'total_laporan'
            );
            
            $responseScope = ($userId !== null) ? 'user' : 'kabupaten';
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'distributionChart' => $distributionChart,
                    'topOPTChart' => $topOPTChart,
                    'byKecamatan' => $data['byKecamatan']
                ],
                'scope' => $responseScope,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('hama', $e);
        }
    }
    
    // =========================================
    // IRRIGATION ENDPOINT
    // =========================================
    
    /**
     * Get irrigation data for charts
     * GET /api/dashboard/charts/irrigation
     */
    public function irrigation() {
        $this->assertAuthenticated();
        $this->assertRateLimit('irrigation', 20, 60);
        
        try {
            $data = $this->aggregator->getIrrigationSummary();
            
            // Format trend for line chart
            $trendChart = $this->formatIrrigationTrendData($data['trend']);
            
            // Format by area for bar chart
            $byAreaChart = $this->formatBarChartData(
                $data['byArea'],
                'daerah_irigasi',
                'avg_debit'
            );
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $data['statistics'],
                    'trendChart' => $trendChart,
                    'byAreaChart' => $byAreaChart
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('irigasi', $e);
        }
    }
    
    // =========================================
    // SUMMARY ENDPOINT
    // =========================================
    
    /**
     * Get all dashboard summary data
     * GET /api/dashboard/charts/summary
     */
    public function summary() {
        $this->assertAuthenticated();
        $this->assertRateLimit('summary', 60, 60);
        
        try {
            $year = $this->resolveYear();
            $userId = $this->getPetugasUserId();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'hama' => $this->aggregator->getHamaStats($year, $userId),
                    'weather' => [
                        'rainfall' => $this->aggregator->getRainfallSummary(['year' => $year])['statistics'],
                        'wind' => $this->aggregator->getWindSummary(['year' => $year])['statistics']
                    ],
                    'prices' => $this->aggregator->getLatestPrices(),
                    'production' => $this->aggregator->getProductionStats($year),
                    'irrigation' => $this->aggregator->getIrrigationStats()
                ],
                'year' => $year,
                'availableYears' => $this->aggregator->getAvailableYears(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            $this->handleApiException('summary', $e);
        }
    }
    
    // =========================================
    // EXPORT ENDPOINT
    // =========================================
    
    /**
     * Export data to CSV or JSON
     * GET /api/dashboard/charts/export
     */
    public function export() {
        $this->assertAuthenticated();
        $this->assertRateLimit('export', 5, 60);  // file download - batas ketat
        
        try {
            $type   = $_GET['type'] ?? 'hama';
            $format = $_GET['format'] ?? 'csv';
            $year   = $this->resolveYear();
            
            // Validasi type dan format
            $allowedTypes   = ['rainfall', 'wind', 'prices', 'production', 'hama', 'irrigation'];
            $allowedFormats = ['csv', 'json'];
            $type   = in_array($_GET['type'] ?? '', $allowedTypes, true)
                      ? $_GET['type']
                      : 'hama';
            $format = in_array($_GET['format'] ?? '', $allowedFormats, true)
                      ? $_GET['format']
                      : 'csv';
            
            $data = [];
            $filename = '';
            
            switch ($type) {
                case 'rainfall':
                    $result = $this->aggregator->getRainfallSummary(['year' => $year]);
                    $data = $result['monthly'];
                    $filename = "curah_hujan_{$year}";
                    break;
                    
                case 'wind':
                    $result = $this->aggregator->getWindSummary(['year' => $year]);
                    $data = $result['monthly'];
                    $filename = "kecepatan_angin_{$year}";
                    break;
                    
                case 'prices':
                    $result = $this->aggregator->getPriceTrend(12);
                    $data = $result;
                    $filename = "harga_komoditas";
                    break;
                    
                case 'production':
                    $result = $this->aggregator->getTopProducers($year, 50);
                    $data = $result;
                    $filename = "produksi_bps_{$year}";
                    break;
                    
                case 'hama':
                default:
                    $userId = $this->getPetugasUserId();
                    $includeDraft = filter_var($_GET['include_draft'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $exportKecamatanId = isset($_GET['kecamatan_id']) ? (int) $_GET['kecamatan_id'] : null;
                    if ($userId !== null) {
                        // Petugas: ekspor detail laporan milik mereka (termasuk Draf jika diminta)
                        $result = $this->aggregator->getHamaDetailForExport($year, $userId, null, $includeDraft);
                        $filename = "laporan_hama_saya_{$year}";
                    } else {
                        // Admin/operator: ekspor agregat per kecamatan
                        $result = $this->aggregator->getHamaByKecamatan($year, $userId, $exportKecamatanId, $includeDraft);
                        $filename = "sebaran_hama_{$year}";
                    }
                    break;
            }
            
            if (empty($data)) {
                $this->errorResponse('Tidak ada data untuk diekspor', 404);
                return;
            }
            
            // Harden Content-Disposition filename
            $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
            
            if ($format === 'csv') {
                $csv = $this->aggregator->exportToCSV($data, $safeFilename);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $safeFilename . '.csv"');
                echo $csv;
                exit;
            }
            
            // JSON format fallback
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'filename' => $safeFilename
            ]);
            
        } catch (Throwable $e) {
            $this->handleApiException('export', $e);
        }
    }
    
    // =========================================
    // HELPER METHODS FOR CHART FORMATTING
    // =========================================
    
    /**
     * Format time series data for Chart.js
     */
    private function formatTimeSeriesData($data, $labelKey, $valueKey) {
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        
        // Initialize all months with 0
        $labels = $monthNames;
        $values = array_fill(0, 12, 0);
        
        foreach ($data as $item) {
            $month = (int)$item[$labelKey];
            if ($month >= 1 && $month <= 12) {
                $values[$month - 1] = (float)($item[$valueKey] ?? 0);
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values
                ]
            ]
        ];
    }
    
    /**
     * Format price trend data for multiple line chart
     */
    private function formatPriceTrendData($data) {
        $commodities = [];
        $periods = [];
        
        foreach ($data as $item) {
            $commodity = $item['komoditas'];
            $period = $item['period'];
            
            if (!isset($commodities[$commodity])) {
                $commodities[$commodity] = [];
            }
            
            $commodities[$commodity][$period] = (float)$item['avg_price'];
            
            if (!in_array($period, $periods)) {
                $periods[] = $period;
            }
        }
        
        sort($periods);
        
        $datasets = [];
        $colors = ['#dc3545', '#198754', '#0d6efd', '#ffc107', '#6f42c1'];
        $colorIndex = 0;
        
        foreach ($commodities as $name => $values) {
            $dataPoints = [];
            foreach ($periods as $period) {
                $dataPoints[] = $values[$period] ?? null;
            }
            
            $datasets[] = [
                'label' => $name,
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'fill' => false
            ];
            
            $colorIndex++;
        }
        
        return [
            'labels' => $periods,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Format production trend data for line chart
     */
    private function formatProductionTrendData($data) {
        $labels = [];
        $produksiGabah = [];
        $produksiBeras = [];
        $luasPanen = [];
        
        foreach ($data as $item) {
            $labels[] = (string)$item['tahun'];
            $produksiGabah[] = (float)($item['total_produksi_gabah'] ?? 0);
            $produksiBeras[] = (float)($item['total_produksi_beras'] ?? 0);
            $luasPanen[] = (float)($item['total_luas_panen'] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Produksi Gabah (ton)',
                    'data' => $produksiGabah,
                    'borderColor' => '#198754',
                    'fill' => false
                ],
                [
                    'label' => 'Produksi Beras (ton)',
                    'data' => $produksiBeras,
                    'borderColor' => '#0d6efd',
                    'fill' => false
                ]
            ]
        ];
    }
    
    /**
     * Format irrigation trend data for line chart
     */
    private function formatIrrigationTrendData($data) {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = date('d M', strtotime($item['tanggal']));
            $values[] = (float)($item['avg_debit'] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Rata-rata Debit (L/det)',
                    'data' => $values,
                    'borderColor' => '#0d6efd',
                    'fill' => true,
                    'backgroundColor' => 'rgba(13, 110, 253, 0.1)'
                ]
            ]
        ];
    }
    
    /**
     * Format data for bar chart
     */
    private function formatBarChartData($data, $labelKey, $valueKey) {
        $labels = [];
        $values = [];
        
        foreach ($data as $item) {
            $labels[] = $item[$labelKey] ?? 'Unknown';
            $values[] = (float)($item[$valueKey] ?? 0);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values
                ]
            ]
        ];
    }
    
    private function getPetugasUserId(): ?int {
        $role   = $_SESSION['role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        if ($role !== 'petugas') {
            return null;
        }
        $id = filter_var($userId, FILTER_VALIDATE_INT);
        return ($id !== false && $id > 0) ? $id : null;
    }
}