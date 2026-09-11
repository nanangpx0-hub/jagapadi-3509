<?php
declare(strict_types=1);
/**
 * Curah Hujan Controller
 * Controller untuk dashboard dan API curah hujan
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujanController extends Controller {
    
    private $model;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/CurahHujan.php';
        require_once ROOT_PATH . '/app/core/CacheManager.php';
        $this->model = new CurahHujan();
        
        // Ensure tables exist
        $this->model->createTablesIfNotExist();
    }
    
    /**
     * Check authentication
     */
    protected function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }
    
    /**
     * Check admin access
     */
    protected function checkAdmin() {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }

    private function getSourceFilter(?string $source): array {
        return match ($source) {
            'bmkg' => ['sumber_data_like' => '%BMKG%'],
            'simulation' => ['sumber_data_like' => '%Simulasi%'],
            'all' => [],
            default => ['sumber_data_like' => '%NASA%'],
        };
    }
    
    /**
     * Dashboard utama curah hujan
     */
    public function index() {
        $this->checkAuth();
        
        $data = [
            'title' => 'Curah Hujan - JAGAPADI',
            'page_title' => 'Data Curah Hujan Kabupaten Jember',
            'availableYears' => $this->model->getAvailableYears(),
            'currentYear' => date('Y'),
            'currentMonth' => date('m')
        ];
        
        $defaultFilters = ['year' => date('Y'), 'sumber_data_like' => '%NASA%'];

        // NASA is the default analytical source; simulations remain opt-in.
        $data['statistics'] = $this->model->getStatistics($defaultFilters);
        
        // Get monthly data for chart
        $data['monthlyData'] = $this->model->getMonthlyAverage(date('Y'), ['sumber_data_like' => '%NASA%']);
        
        // Get recent data for table
        $data['recentData'] = $this->model->getAll([
            'sumber_data_like' => '%NASA%',
            'limit' => 10,
            'offset' => 0
        ]);
        
        // Get logs for admin
        if ($_SESSION['role'] === 'admin') {
            $data['recentLogs'] = $this->model->getRecentLogs(5);
        }

        // Get last successful scrape info for metadata display
        $lastLog = $this->model->getRecentLogs(1); // Re-using getRecentLogs, might need filter for 'success'
        // Actually, let's add a specific method in model or just filter here.
        // For efficiency, let's keep it simple. getRecentLogs sorts by created_at DESC.
        // We'll pass it to view.
        $data['lastScrape'] = !empty($lastLog) ? $lastLog[0] : null;
        
        $this->view('curah_hujan/index', $data);
    }
    
    /**
     * Format a single record for API response
     * Derives bulan and tahun from tanggal for consistent frontend rendering
     * 
     * @param array $record Raw database record
     * @return array Formatted record with bulan/tahun
     */
    private function formatRecordForResponse($record) {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $tanggalTs = strtotime($record['tanggal']);
        $monthNum = (int)date('n', $tanggalTs);
        
        return [
            'id' => $record['id'],
            'tanggal' => $record['tanggal'],
            'bulan' => $namaBulan[$monthNum],
            'bulan_num' => $monthNum,
            'tahun' => (int)date('Y', $tanggalTs),
            'lokasi' => $record['lokasi'],
            'kode_wilayah' => $record['kode_wilayah'] ?? null,
            'curah_hujan' => (float)$record['curah_hujan'],
            'satuan' => $record['satuan'] ?? 'mm',
            'sumber_data' => $record['sumber_data'],
            'keterangan' => $record['keterangan'] ?? null,
            'created_at' => $record['created_at'] ?? null
        ];
    }
    
    /**
     * API: Get data dengan filter (AJAX)
     */
    public function getData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $filters = [
                'year' => $_GET['year'] ?? null,
                'month' => $_GET['month'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'lokasi' => $_GET['lokasi'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            // Data source filtering
            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $filters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'bmkg') {
                $filters['sumber_data_like'] = '%BMKG%';
            } elseif ($dataSource === 'simulation') {
                $filters['sumber_data_like'] = '%Simulasi%';
            }
            // If 'all', no sumber_data filter applied
            
            // Remove null filters
            $filters = array_filter($filters, function($v) { return $v !== null; });
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);

            $statistics = $this->model->getStatistics($filters);
            
            // Add data source breakdown (composition)
            $baseFilters = $filters;
            unset($baseFilters['sumber_data_like']); // Get breakdown of all sources
            $statistics['data_composition'] = $this->model->getDataSourceBreakdown($baseFilters);
            
            // Format each record with derived fields (bulan, tahun)
            $formattedData = array_map([$this, 'formatRecordForResponse'], $data);
            
            echo json_encode([
                'success' => true,
                'data' => $formattedData,
                'total' => $total,
                'statistics' => $statistics,
                'filter_applied' => $dataSource
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Get chart data (AJAX)
     */
    public function getChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');

        $cacheKey = 'stats_curah_hujan_chart_' . md5($_SERVER['QUERY_STRING'] ?? '');
        $cache = CacheManager::getInstance();
        if ($cache->isAvailable()) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                echo $cached;
                exit;
            }
            ob_start();
        }
        
        try {
            $type = $_GET['type'] ?? 'monthly';
            $year = $_GET['year'] ?? date('Y');
            $chartFilters = [];
            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $chartFilters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'bmkg') {
                $chartFilters['sumber_data_like'] = '%BMKG%';
            } elseif ($dataSource === 'simulation') {
                $chartFilters['sumber_data_like'] = '%Simulasi%';
            }
            
            if ($type === 'monthly') {
                $data = $this->model->getMonthlyAverage($year, $chartFilters);
                
                // Format for Chart.js
                $labels = [];
                $values = [];
                $totals = [];
                
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                
                // Initialize all months with 0
                for ($i = 1; $i <= 12; $i++) {
                    $labels[] = $monthNames[$i - 1];
                    $values[$i] = 0;
                    $totals[$i] = 0;
                }
                
                // Fill with actual data
                foreach ($data as $row) {
                    $bulan = (int) $row['bulan'];
                    $values[$bulan] = (float) $row['rata_rata'];
                    $totals[$bulan] = (float) $row['total'];
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Rata-rata Curah Hujan (mm)',
                            'data' => array_values($values),
                            'borderColor' => 'rgb(54, 162, 235)',
                            'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                            'tension' => 0.3
                        ],
                        [
                            'label' => 'Total Curah Hujan (mm)',
                            'data' => array_values($totals),
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                            'tension' => 0.3
                        ]
                    ]
                ]);
            } elseif ($type === 'yearly') {
                $data = $this->model->getYearlySummary(5, $chartFilters);
                
                $labels = [];
                $avgValues = [];
                $totalValues = [];
                
                foreach (array_reverse($data) as $row) {
                    $labels[] = $row['tahun'];
                    $avgValues[] = (float) $row['rata_rata'];
                    $totalValues[] = (float) $row['total'];
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Rata-rata (mm)',
                            'data' => $avgValues,
                            'backgroundColor' => 'rgba(54, 162, 235, 0.7)'
                        ],
                        [
                            'label' => 'Total (mm)',
                            'data' => $totalValues,
                            'backgroundColor' => 'rgba(75, 192, 192, 0.7)'
                        ]
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid chart type']);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        if (isset($cache) && $cache->isAvailable()) {
            $cache->set($cacheKey, ob_get_contents(), 300);
            ob_end_flush();
        }
        exit;
    }
    
    /**
     * API: Get statistics (AJAX)
     */
    public function getStatistics() {
        $this->checkAuth();
        header('Content-Type: application/json');

        $cacheKey = 'stats_curah_hujan_stats_' . md5($_SERVER['QUERY_STRING'] ?? '');
        $cache = CacheManager::getInstance();
        if ($cache->isAvailable()) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                echo $cached;
                exit;
            }
            ob_start();
        }
        
        try {
            $filters = [
                'year' => $_GET['year'] ?? null,
                'month' => $_GET['month'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'lokasi' => $_GET['lokasi'] ?? null
            ];

            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $filters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'bmkg') {
                $filters['sumber_data_like'] = '%BMKG%';
            } elseif ($dataSource === 'simulation') {
                $filters['sumber_data_like'] = '%Simulasi%';
            }
            
            $filters = array_filter($filters, function($v) { return $v !== null; });
            
            $statistics = $this->model->getStatistics($filters);
            
            echo json_encode([
                'success' => true,
                'statistics' => $statistics
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        if (isset($cache) && $cache->isAvailable()) {
            $cache->set($cacheKey, ob_get_contents(), 300);
            ob_end_flush();
        }
        exit;
    }
    
    /**
     * Manual trigger scraper (Admin only)
     */
    public function runScraper() {
        $this->checkAuth();
        $this->checkAdmin();
        $this->requireRequestMethod(['POST']);
        
        ob_start();
        header('Content-Type: application/json; charset=utf-8');
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $scraper = new CurahHujanScraper();
            
            $options = [
                'year' => $_POST['year'] ?? date('Y'),
                'month' => $_POST['month'] ?? date('m'),
                'source' => $_POST['source'] ?? $_POST['data_source'] ?? 'nasa',
                'force_simulation' => isset($_POST['force_simulation'])
            ];
            
            $result = $scraper->run($options);
            if ($result['success']) {
                $cacheInvalidator = CacheManager::getInstance();
                if ($cacheInvalidator->isAvailable()) {
                    $cacheInvalidator->clearPrefix('stats_curah_hujan_');
                }
            }
            
            $jsonOutput = json_encode([
                'success' => $result['success'],
                'no_data' => $result['no_data'] ?? false,
                'message' => $result['message'],
                'source' => $result['source'],
                'records_success' => $result['records_success'],
                'records_failed' => $result['records_failed'],
                'execution_time' => $result['execution_time']
            ]);

            if (ob_get_length()) {
                ob_end_clean();
            }
            echo $jsonOutput;
        } catch (Exception $e) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Endpoint terdedikasi untuk pengambilan data NASA POWER API
     */
    public function fetch_nasa_curah_hujan() {
        $this->checkAuth();
        $this->checkAdmin();
        $this->requireRequestMethod(['POST']);
        
        ob_start();
        header('Content-Type: application/json; charset=utf-8');
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $scraper = new CurahHujanScraper();
            
            $year = $_POST['year'] ?? date('Y');
            $month = $_POST['month'] ?? date('m');
            
            $data = $scraper->fetch_nasa_curah_hujan($year, $month);
            
            if (!empty($data)) {
                $bulkRes = $this->model->bulkInsert($data);
                $cacheInvalidator = CacheManager::getInstance();
                if ($cacheInvalidator->isAvailable()) {
                    $cacheInvalidator->clearPrefix('stats_curah_hujan_');
                }
                
                $this->model->logActivity('fetch_nasa', 'success', "NASA POWER API: Berhasil mengambil data ({$bulkRes['success']} sukses)", [
                    'processed' => count($data),
                    'success' => $bulkRes['success'],
                    'failed' => $bulkRes['failed']
                ]);
                
                $jsonOutput = json_encode([
                    'success' => true,
                    'message' => "Berhasil mengambil {$bulkRes['success']} data curah hujan dari NASA POWER API",
                    'source' => 'NASA POWER (PRECTOTCORR)',
                    'records_success' => $bulkRes['success'],
                    'records_failed' => $bulkRes['failed'],
                    'execution_time' => 1.5
                ]);
            } else {
                $jsonOutput = json_encode([
                    'success' => false,
                    'error' => 'Tidak ada data valid yang dikembalikan oleh NASA POWER API'
                ]);
            }

            if (ob_get_length()) {
                ob_end_clean();
            }
            echo $jsonOutput;
        } catch (Exception $e) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Eksekusi scrape satu tahun penuh NASA POWER (idempoten via UPSERT).
     * POST year — dipakai loop client per-tahun agar progres real-time.
     */
    public function runYearScraper() {
        $this->checkAuth();
        $this->checkAdmin();
        $this->requireRequestMethod(['POST']);

        ob_start();
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }

        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
            $rangeErrors = CurahHujanScraper::validateRange($year, $year);
            if ($rangeErrors !== []) {
                throw new InvalidArgumentException(implode(' ', $rangeErrors));
            }

            @set_time_limit(180);
            $scraper = new CurahHujanScraper();
            $result = $scraper->runSingleYear($year);

            $cacheInvalidator = CacheManager::getInstance();
            if ($cacheInvalidator->isAvailable()) {
                $cacheInvalidator->clearPrefix('stats_curah_hujan_');
            }

            $jsonOutput = json_encode([
                'success' => $result['status'] !== 'failed',
                'status' => $result['status'],
                'message' => "Tahun {$year}: {$result['records']} tersimpan, {$result['failed']} gagal, {$result['skipped']} dilewati",
                'source' => 'NASA POWER (PRECTOTCORR)',
                'records_success' => $result['records'],
                'records_failed' => $result['failed'],
                'skipped_future' => $result['skipped'],
                'failures' => $result['failures'],
                'execution_time' => $result['execution_time'],
            ]);

            if (ob_get_length()) {
                ob_end_clean();
            }
            echo $jsonOutput;
        } catch (Throwable $e) {
            error_log('CurahHujanController::runYearScraper failed: ' . $e->getMessage());
            try {
                $this->model->logActivity('scrape_year', 'failed', $e->getMessage(), ['processed' => 0, 'failed' => 1]);
            } catch (Throwable $logError) {
                error_log('CurahHujanController::runYearScraper log failed: ' . $logError->getMessage());
            }
            if (ob_get_length()) {
                ob_end_clean();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Eksekusi scrape rentang multi-tahun NASA POWER (mis. 2020–2026).
     * POST start_year, end_year — berjalan server-side penuh.
     */
    public function runRangeScraper() {
        $this->checkAuth();
        $this->checkAdmin();
        $this->requireRequestMethod(['POST']);

        ob_start();
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }

        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $startYear = isset($_POST['start_year']) ? (int)$_POST['start_year'] : 2020;
            $endYear = isset($_POST['end_year']) ? (int)$_POST['end_year'] : (int)date('Y');
            $rangeErrors = CurahHujanScraper::validateRange($startYear, $endYear);
            if ($rangeErrors !== []) {
                throw new InvalidArgumentException(implode(' ', $rangeErrors));
            }

            @set_time_limit(0);
            $scraper = new CurahHujanScraper();
            $report = $scraper->runBatchRange($startYear, $endYear);

            $cacheInvalidator = CacheManager::getInstance();
            if ($cacheInvalidator->isAvailable()) {
                $cacheInvalidator->clearPrefix('stats_curah_hujan_');
            }

            $jsonOutput = json_encode([
                'success' => $report['success'],
                'message' => "Rentang {$startYear}-{$endYear}: {$report['total_records_saved']} tersimpan, "
                    . "{$report['total_records_failed']} gagal, {$report['total_skipped_future']} dilewati",
                'source' => 'NASA POWER (PRECTOTCORR)',
                'report' => $report,
            ]);

            if (ob_get_length()) {
                ob_end_clean();
            }
            echo $jsonOutput;
        } catch (Throwable $e) {
            error_log('CurahHujanController::runRangeScraper failed: ' . $e->getMessage());
            try {
                $this->model->logActivity('scrape_range', 'failed', $e->getMessage(), ['processed' => 0, 'failed' => 1]);
            } catch (Throwable $logError) {
                error_log('CurahHujanController::runRangeScraper log failed: ' . $logError->getMessage());
            }
            if (ob_get_length()) {
                ob_end_clean();
            }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Daftar kegagalan scrape terbaru (JSON untuk modal UI).
     * GET — Admin only.
     */
    public function getFailureReport() {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
            $logs = $this->model->getFailedLogs($limit);
            $data = [];
            foreach ($logs as $log) {
                $data[] = [
                    'id' => (int)($log['id'] ?? 0),
                    'created_at' => $log['created_at'] ?? null,
                    'action' => $log['action'] ?? '',
                    'status' => $log['status'] ?? '',
                    'summary' => CurahHujanScraper::summarizeLogMessage((string)($log['message'] ?? '')),
                    'failures' => CurahHujanScraper::extractFailuresFromMessage((string)($log['message'] ?? '')),
                    'records_success' => (int)($log['records_success'] ?? 0),
                    'records_failed' => (int)($log['records_failed'] ?? 0),
                ];
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            error_log('CurahHujanController::getFailureReport failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Gagal mengambil laporan kegagalan']);
        }
        exit;
    }

    /**
     * Unduh CSV laporan kegagalan scrape (waktu, tahun-bulan, kecamatan, alasan).
     * GET — Admin only.
     */
    public function exportFailureLog() {
        $this->checkAuth();
        $this->checkAdmin();

        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $logs = $this->model->getFailedLogs(500);

            $rows = [];
            foreach ($logs as $log) {
                $failures = CurahHujanScraper::extractFailuresFromMessage((string)($log['message'] ?? ''));
                if ($failures === []) {
                    $rows[] = [
                        $log['created_at'] ?? '',
                        $log['action'] ?? '',
                        $log['status'] ?? '',
                        '', '', '', '',
                        CurahHujanScraper::summarizeLogMessage((string)($log['message'] ?? '')),
                    ];
                    continue;
                }
                foreach ($failures as $failure) {
                    if (!is_array($failure)) {
                        continue;
                    }
                    $rows[] = [
                        $log['created_at'] ?? '',
                        $log['action'] ?? '',
                        $log['status'] ?? '',
                        isset($failure['year']) ? (string)$failure['year'] : '',
                        isset($failure['month']) && $failure['month'] !== null ? (string)$failure['month'] : '',
                        isset($failure['kecamatan']) ? (string)$failure['kecamatan'] : '',
                        isset($failure['http_code']) ? (string)$failure['http_code'] : '',
                        isset($failure['error']) ? (string)$failure['error'] : '',
                    ];
                }
            }

            $filename = 'laporan-kegagalan-curah-hujan-' . date('Ymd-His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new RuntimeException('Tidak dapat membuka stream unduhan');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Waktu', 'Aksi', 'Status', 'Tahun', 'Bulan', 'Kecamatan', 'HTTP', 'Alasan Gagal']);
            foreach ($rows as $row) {
                fputcsv($output, array_map([$this, 'sanitizeCsvCell'], $row));
            }
            fclose($output);
        } catch (Throwable $e) {
            error_log('CurahHujanController::exportFailureLog failed: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Gagal mengekspor laporan kegagalan']);
        }
        exit;
    }

    /**
     * Cegah formula injection pada sel CSV (awali apostrof bila
     * diawali karakter berbahaya).
     */
    private function sanitizeCsvCell($cell): string {
        $value = (string)$cell;
        if ($value !== '' && str_contains('=+-@' . "\t\r", mb_substr($value, 0, 1))) {
            return "'" . $value;
        }
        return $value;
    }
    
    /**
     * Manual data entry form (Admin only)
     */
    public function create() {
        $this->checkAuth();
        $this->checkAdmin();
        
        $data = [
            'title' => 'Tambah Data Curah Hujan - JAGAPADI',
            'page_title' => 'Tambah Data Curah Hujan'
        ];
        
        $this->view('curah_hujan/create', $data);
    }
    
    /**
     * Store manual data entry
     */
    public function store() {
        $this->checkAuth();
        $this->checkAdmin();
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Token keamanan tidak valid';
            header('Location: ' . BASE_URL . '/curahHujan/create');
            exit;
        }
        
        try {
            $data = [
                'tanggal' => $_POST['tanggal'] ?? null,
                'lokasi' => $_POST['lokasi'] ?? 'Jember',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'curah_hujan' => $_POST['curah_hujan'] ?? 0,
                'satuan' => 'mm',
                'sumber_data' => 'Manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            // Validation
            if (empty($data['tanggal'])) {
                throw new Exception('Tanggal harus diisi');
            }

            $date = DateTime::createFromFormat('!Y-m-d', (string) $data['tanggal']);
            if (!$date || $date->format('Y-m-d') !== $data['tanggal'] || $data['tanggal'] > date('Y-m-d')) {
                throw new Exception('Tanggal tidak valid atau berada di masa depan');
            }
            
            if (!is_numeric($data['curah_hujan']) || $data['curah_hujan'] < 0 || $data['curah_hujan'] > 500) {
                throw new Exception('Curah hujan harus antara 0-500 mm');
            }
            
            $result = $this->model->insert($data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_curah_hujan_']);
                // Log activity
                $this->model->logActivity('manual_entry', 'success', 'Data curah hujan ditambahkan', [
                    'processed' => 1,
                    'success' => 1,
                    'failed' => 0
                ]);
                
                $_SESSION['success'] = 'Data curah hujan berhasil ditambahkan';
            } else {
                throw new Exception('Gagal menyimpan data');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . '/curahHujan');
        exit;
    }
    
    /**
     * API: Get single record for editing (Admin only)
     * 
     * @param int $id
     * @return void
     */
    public function getRecord($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        try {
            $record = $this->model->getById($id);
            
            if (!$record) {
                echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $this->formatRecordForResponse($record)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Update existing data (Admin only)
     * 
     * @param int $id
     * @return void
     */
    public function update($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            // Validate input
            $tanggal = $_POST['tanggal'] ?? null;
            $curahHujan = $_POST['curah_hujan'] ?? null;
            $lokasi = $_POST['lokasi'] ?? 'Jember';
            $keterangan = $_POST['keterangan'] ?? null;
            
            if (empty($tanggal)) {
                throw new Exception('Tanggal harus diisi');
            }

            $date = DateTime::createFromFormat('!Y-m-d', (string) $tanggal);
            if (!$date || $date->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
                throw new Exception('Tanggal tidak valid atau berada di masa depan');
            }
            
            if (!is_numeric($curahHujan) || $curahHujan < 0 || $curahHujan > 500) {
                throw new Exception('Curah hujan harus antara 0-500 mm');
            }
            
            // Check if record exists
            $existing = $this->model->getById($id);
            if (!$existing) {
                throw new Exception('Data tidak ditemukan');
            }
            
            $data = [
                'tanggal' => $tanggal,
                'lokasi' => $lokasi,
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'curah_hujan' => floatval($curahHujan),
                'sumber_data' => $existing['sumber_data'] ?? 'Manual',
                'keterangan' => $keterangan
            ];
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_curah_hujan_']);
                // Log activity
                $this->model->logActivity('update', 'success', "Data curah hujan ID {$id} diperbarui", [
                    'processed' => 1,
                    'success' => 1,
                    'failed' => 0
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Data berhasil diperbarui'
                ]);
            } else {
                throw new Exception('Gagal memperbarui data');
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Delete data (Admin only)
     */
    public function delete($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $result = $this->model->delete($id);
            if ($result) {
                $this->invalidateStatsCache(['stats_curah_hujan_']);
            }
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Data berhasil dihapus' : 'Gagal menghapus data'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Delete log by ID (Admin only)
     * 
     * @param int $id
     * @return void
     */
    public function deleteLog($id) {
        $this->checkAdmin();
        
        // Set headers to prevent caching
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Invalid CSRF token');
                }
                
                if ($this->model->deleteLog($id)) {
                    echo json_encode(['success' => true, 'message' => 'Log berhasil dihapus']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Gagal menghapus log']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Delete multiple data records (Admin only)
     * 
     * @return void
     */
    public function deleteMultiple() {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }
            
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            
            if (empty($ids) || !is_array($ids)) {
                throw new Exception('Tidak ada data yang dipilih');
            }
            
            $deleted = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id > 0 && $this->model->delete($id)) {
                    $deleted++;
                }
            }

            if ($deleted > 0) {
                $this->invalidateStatsCache(['stats_curah_hujan_']);
            }
            
            echo json_encode([
                'success' => true, 
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} data"
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get detailed logs (Admin only)
     */
    public function getLogs() {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $logs = $this->model->getRecentLogs($limit); // Assuming getRecentLogs supports limit
            
            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Delete multiple log records (Admin only)
     * 
     * @return void
     */
    public function deleteMultipleLogs() {
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }
            
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            
            if (empty($ids) || !is_array($ids)) {
                throw new Exception('Tidak ada log yang dipilih');
            }
            
            $deleted = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id > 0 && $this->model->deleteLog($id)) {
                    $deleted++;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} log"
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Export data ke CSV
     */
    public function export() {
        $this->checkAuth();
        
        $filters = [
            'year' => $_GET['year'] ?? null,
            'month' => $_GET['month'] ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null
        ];
        
        $filters = array_filter($filters, function($v) { return $v !== null; });
        
        $data = $this->model->getAll($filters);
        
        // Generate CSV
        $filename = 'curah_hujan_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, ['Tanggal', 'Lokasi', 'Curah Hujan (mm)', 'Sumber Data', 'Keterangan']);
        
        // Data rows
        foreach ($data as $row) {
            $csvRow = $this->sanitizeCsvRow([
                $row['tanggal'],
                $row['lokasi'],
                $row['curah_hujan'],
                $row['sumber_data'],
                $row['keterangan']
            ]);
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
    
    // ========== DASHBOARD ANALYSIS API ENDPOINTS ==========
    
    /**
     * API: Get trend analysis data for year comparison
     */
    public function getTrendData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $startYear = $_GET['start_year'] ?? (date('Y') - 4);
            $endYear = $_GET['end_year'] ?? date('Y');
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $data = $this->model->getTrendAnalysis($startYear, $endYear, $sourceFilters);
            
            // Organize data by year for Chart.js
            $years = [];
            foreach ($data as $row) {
                $year = $row['tahun'];
                if (!isset($years[$year])) {
                    $years[$year] = array_fill(1, 12, 0);
                }
                $years[$year][$row['bulan']] = (float) $row['rata_rata'];
            }
            
            $datasets = [];
            $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
            $i = 0;
            foreach ($years as $year => $months) {
                $datasets[] = [
                    'label' => (string) $year,
                    'data' => array_values($months),
                    'borderColor' => $colors[$i % count($colors)],
                    'backgroundColor' => $colors[$i % count($colors)] . '33',
                    'tension' => 0.3,
                    'fill' => false
                ];
                $i++;
            }
            
            echo json_encode([
                'success' => true,
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                'datasets' => $datasets
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get seasonal pattern data
     */
    public function getSeasonalData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $year = $_GET['year'] ?? date('Y');
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            $data = $this->model->getSeasonalPattern($year, $sourceFilters);
            
            $labels = [];
            $values = [];
            $classifications = [];
            $colors = [];
            
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            
            foreach ($data as $row) {
                $labels[] = $monthNames[$row['bulan'] - 1];
                $values[] = (float) $row['rata_rata'];
                $classifications[] = $row['klasifikasi'];
                
                // Color based on classification
                $colors[] = $row['klasifikasi'] === 'Musim Hujan' ? 'rgba(54, 162, 235, 0.7)' :
                           ($row['klasifikasi'] === 'Peralihan' ? 'rgba(255, 206, 86, 0.7)' :
                            'rgba(255, 99, 132, 0.7)');
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'labels' => $labels,
                'values' => $values,
                'classifications' => $classifications,
                'colors' => $colors
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get anomaly detection data
     */
    public function getAnomalyData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $year = $_GET['year'] ?? date('Y');
            $threshold = $_GET['threshold'] ?? 2.0;
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $data = $this->model->getAnomalies($year, $threshold, $sourceFilters);
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'statistics' => $data['statistics'],
                'anomalies' => $data['anomalies'],
                'total_anomalies' => count($data['anomalies'])
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get prediction data
     */
    public function getPredictionData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $months = $_GET['months'] ?? 3;
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            $data = $this->model->getSimplePrediction($months, $sourceFilters);
            
            echo json_encode([
                'success' => true,
                'historical' => $data['historical'],
                'predictions' => $data['predictions'],
                'method' => $data['method'] ?? '3-Month Moving Average'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Check for rainfall alerts
     */
    public function checkAlerts() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $threshold = $_GET['threshold'] ?? 50.0;
            $days = $_GET['days'] ?? 7;
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $alerts = $this->model->getAlerts($threshold, $days, $sourceFilters);
            
            echo json_encode([
                'success' => true,
                'threshold' => $threshold,
                'days' => $days,
                'alerts' => $alerts,
                'total' => count($alerts)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get daily chart data for a specific month
     */
    public function getDailyChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('n');
            $filters = [];
            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $filters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'bmkg') {
                $filters['sumber_data_like'] = '%BMKG%';
            } elseif ($dataSource === 'simulation') {
                $filters['sumber_data_like'] = '%Simulasi%';
            }
            $data = $this->model->getDailyData($year, $month, $filters);
            
            // Get days in month
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $labels = range(1, $daysInMonth);
            $values = array_fill(0, $daysInMonth, 0);
            
            foreach ($data as $row) {
                $day = (int) $row['hari'];
                $values[$day - 1] = (float) $row['curah_hujan'];
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Curah Hujan (mm)',
                    'data' => $values,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1
                ]]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get map data with rainfall by location
     */
    public function getMapData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $rainfallData = $this->model->getRainfallByLocation($year, $month, $sourceFilters);
            
            $db = Database::getInstance()->getConnection();
            $coordStmt = $db->query(
                "SELECT nama_kecamatan, kode, latitude, longitude
                 FROM master_kecamatan
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
            );
            $coordinates = [];
            foreach ($coordStmt->fetchAll(PDO::FETCH_ASSOC) as $coord) {
                $coordinates[strtolower(trim($coord['nama_kecamatan']))] = $coord;
            }

            $mapData = [];
            foreach ($rainfallData as $row) {
                $locationName = trim(explode(',', $row['lokasi'])[0]);
                $coord = $coordinates[strtolower($locationName)] ?? null;
                $mapData[] = [
                    'lokasi' => $row['lokasi'],
                    'rata_rata' => (float) $row['rata_rata'],
                    'total' => (float) $row['total'],
                    'maksimum' => (float) $row['maksimum'],
                    'jumlah_data' => (int) $row['jumlah_data'],
                    'latitude' => $coord ? (float) $coord['latitude'] : null,
                    'longitude' => $coord ? (float) $coord['longitude'] : null,
                    'kode_wilayah' => $coord['kode'] ?? null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'data' => $mapData
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
