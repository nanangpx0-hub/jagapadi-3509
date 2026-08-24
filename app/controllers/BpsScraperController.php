<?php
/**
 * BPS Scraper Controller
 * Controller untuk halaman scraping data pertanian dari BPS
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class BpsScraperController extends Controller {
    
    private $model;
    private $cache;
    private $db;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
        require_once ROOT_PATH . '/app/core/CacheManager.php';
        $this->model = new DataPertanianBps();
        $this->cache = CacheManager::getInstance();
        $this->db = Database::getInstance()->getConnection();
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
        if ($_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
    
    /**
     * Main scraping interface
     */
    public function index() {
        $this->checkAuth();
        
        $availableYears = $this->model->getAvailableYears();
        // Gunakan tahun terakhir yang tersedia di database, bukan date('Y')
        // yang mungkin belum ada datanya (misal: 2026 belum ada datanya)
        $defaultYear = !empty($availableYears) ? (int)$availableYears[0] : (int)date('Y');
        
        $data = [
            'title' => 'Data BPS Pertanian - JAGAPADI',
            'page_title' => 'Data Pertanian BPS Provinsi Jawa Timur',
            'availableYears' => $availableYears,
            'kabupatenList' => $this->model->getKabupatenList(),
            'currentYear' => $defaultYear,
            'defaultYear' => $defaultYear,
            'bpsApiConfigured' => defined('BPS_API_KEY') && trim((string) BPS_API_KEY) !== '',
        ];

        $defaultFilters = [
            'tahun' => $defaultYear,
            'tipe_skenario' => 'baseline',
            'is_validated' => 1,
            'preferred_only' => true,
        ];
        
        // Get statistics for default year
        $data['statistics'] = $this->model->getStatistics($defaultYear, $defaultFilters);
        $data['jemberStatistics'] = $this->model->getStatistics($defaultYear, [
            ...$defaultFilters,
            'kabupaten_kota' => 'Jember',
        ]);
        
        // Completeness: berapa kabupaten terisi untuk tahun default
        $data['kabupatenTerisi'] = $this->model->countDistinctKabupaten($defaultFilters);
        $data['totalKabupaten'] = count($this->model->getKabupatenList());
        
        // Get recent data
        $data['recentData'] = $this->model->getAll([
            ...$defaultFilters,
            'limit' => 10,
        ]);
        
        // Get logs for admin
        if ($_SESSION['role'] === 'admin') {
            $data['recentLogs'] = $this->model->getRecentLogs(5);
        }
        
        $this->view('bps_scraper/index', $data);
    }
    
    /**
     * Sanitize a CSV value to prevent formula injection.
     * Prefixes string values starting with dangerous characters (=, +, -, @, TAB, CR)
     * with a single-quote so Excel treats them as text.
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeCsvValue($value) {
        if (is_string($value) && strlen($value) > 0) {
            $firstChar = $value[0];
            if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"])) {
                return "'" . $value;
            }
        }
        return $value;
    }
    
    /**
     * Format record for API response
     */
    /**
     * Format record for API response
     */
    private function formatRecordForResponse($record) {
        return [
            'id' => $record['id'],
            'tahun' => (int)$record['tahun'],
            'kabupaten_kota' => $record['kabupaten_kota'],
            'kode_wilayah' => $record['kode_wilayah'],
            'luas_panen' => (float)$record['luas_panen'],
            'luas_panen_formatted' => DataPertanianBps::formatNumber($record['luas_panen']),
            'produksi_gabah' => (float)$record['produksi_gabah'],
            'produksi_gabah_formatted' => DataPertanianBps::formatNumber($record['produksi_gabah']),
            'produksi_beras' => (float)$record['produksi_beras'],
            'produksi_beras_formatted' => DataPertanianBps::formatNumber($record['produksi_beras']),
            'produktivitas' => (float)$record['produktivitas'],
            'sumber_data' => $record['sumber_data'],
            'sumber_data_type' => $record['sumber_data_type'] ?? null,
            'tipe_skenario' => $record['tipe_skenario'] ?? null,
            'is_validated' => (bool)($record['is_validated'] ?? false),
            'validation_notes' => $record['validation_notes'] ?? null,
            'keterangan' => $record['keterangan'] ?? null,
            'created_at' => $record['created_at'] ?? null
        ];
    }
    
    /**
     * API: Get data with filters (AJAX)
     */
    public function getData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $availableYears = $this->model->getAvailableYears();
            $defaultYear = !empty($availableYears) ? (int) $availableYears[0] : (int) date('Y');
            $requestedYear = (int) ($_GET['tahun'] ?? $defaultYear);
            $source = trim((string) ($_GET['source'] ?? ''));
            if ($source !== '' && !in_array($source, ['ksa', 'resmi_webapi', 'manual', 'simulasi'], true)) {
                throw new InvalidArgumentException('Filter sumber data tidak valid');
            }
            $scenario = trim((string) ($_GET['skenario'] ?? 'baseline'));
            if (!in_array($scenario, ['baseline', 'optimis', 'pesimis'], true)) {
                throw new InvalidArgumentException('Skenario tidak valid');
            }

            $filters = [
                'tahun' => $requestedYear,
                'kabupaten_kota' => $_GET['kabupaten'] ?? null,
                'sumber_data_type' => $source,
                'sumber_data_like' => $_GET['sumber'] ?? null,
                'tipe_skenario' => $scenario,
                'is_validated' => 1,
                'preferred_only' => $source === '',
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0,
            ];
            
            $filters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);
            $statistics = $this->model->getStatistics($requestedYear, $filters);
            $jemberFilters = $filters;
            $jemberFilters['kabupaten_kota'] = 'Jember';
            unset($jemberFilters['limit'], $jemberFilters['offset']);
            $jemberStatistics = $this->model->getStatistics($requestedYear, $jemberFilters);
            
            // Completeness badge
            $kabupatenTerisi = $this->model->countDistinctKabupaten($filters);
            
            $formattedData = array_map([$this, 'formatRecordForResponse'], $data);
            
            echo json_encode([
                'success' => true,
                'data' => $formattedData,
                'total' => $total,
                'kabupatenTerisi' => (int) $kabupatenTerisi,
                'statistics' => $this->sanitizeNullStats($statistics),
                'jember_statistics' => $this->sanitizeNullStats($jemberStatistics),
                'current_year' => $requestedYear,
                'available_years' => $availableYears
            ]);
        } catch (Exception $e) {
            error_log("[BpsScraper] getData error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Sanitize statistics: convert NULL values to 0 for consistent frontend handling
     */
    private function sanitizeNullStats($stats) {
        if (!$stats) return null;
        return [
            'jumlah_kabupaten' => (int)($stats['jumlah_kabupaten'] ?? 0),
            'total_luas_panen' => (float)($stats['total_luas_panen'] ?? 0),
            'total_produksi_gabah' => (float)($stats['total_produksi_gabah'] ?? 0),
            'total_produksi_beras' => (float)($stats['total_produksi_beras'] ?? 0),
            'rata_produktivitas' => (float)($stats['rata_produktivitas'] ?? 0)
        ];
    }
    
    /**
     * Get the most recent year that has data, falling back to current year
     */
    private function _getDefaultYear(): int {
        $years = $this->model->getAvailableYears();
        return !empty($years) ? (int)$years[0] : (int)date('Y');
    }
    
    /**
     * Clear all BPS-related cache entries after write operations
     */
    private function clearCache() {
        if ($this->cache && $this->cache->isAvailable()) {
            $this->cache->clearPrefix('bps_stats_');
            $this->cache->clearPrefix('bps_chart_');
            $this->cache->clearPrefix('bps_years_');
        }
    }

    private function refreshYearlySummary(int $tahun): void {
        require_once ROOT_PATH . '/app/services/BpsDataService.php';
        $service = new BpsDataService();
        $service->updateYearlySummary($tahun);
    }
    
    /**
     * API: Get statistics (AJAX)
     */
    public function getStatistics() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $tahun = (int) ($_GET['tahun'] ?? $this->_getDefaultYear());
            $source = trim((string) ($_GET['source'] ?? ''));
            if ($source !== '' && !in_array($source, ['ksa', 'resmi_webapi', 'manual', 'simulasi'], true)) {
                throw new InvalidArgumentException('Filter sumber data tidak valid');
            }
            $scenario = trim((string) ($_GET['skenario'] ?? 'baseline'));
            if (!in_array($scenario, ['baseline', 'optimis', 'pesimis'], true)) {
                throw new InvalidArgumentException('Skenario tidak valid');
            }
            $filters = [
                'sumber_data_type' => $source,
                'tipe_skenario' => $scenario,
                'is_validated' => 1,
                'preferred_only' => $source === '',
            ];
            $filters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');

            $cacheKey = 'bps_stats_' . hash('sha256', json_encode([$tahun, $filters]));
            $cached = $this->cache && $this->cache->isAvailable() ? $this->cache->get($cacheKey) : null;
            if ($cached !== null) {
                $statistics = $cached;
            } else {
                $statistics = $this->model->getStatistics($tahun, $filters);
                $statistics = [
                    'jumlah_kabupaten' => (int)($statistics['jumlah_kabupaten'] ?? 0),
                    'total_luas_panen' => (float)($statistics['total_luas_panen'] ?? 0),
                    'total_luas_panen_formatted' => DataPertanianBps::formatNumber($statistics['total_luas_panen'] ?? 0),
                    'total_produksi_gabah' => (float)($statistics['total_produksi_gabah'] ?? 0),
                    'total_produksi_gabah_formatted' => DataPertanianBps::formatNumber($statistics['total_produksi_gabah'] ?? 0),
                    'total_produksi_beras' => (float)($statistics['total_produksi_beras'] ?? 0),
                    'total_produksi_beras_formatted' => DataPertanianBps::formatNumber($statistics['total_produksi_beras'] ?? 0),
                    'rata_produktivitas' => (float)($statistics['rata_produktivitas'] ?? 0)
                ];
                if ($this->cache && $this->cache->isAvailable()) {
                    $this->cache->set($cacheKey, $statistics, 300);
                }
            }
            
            echo json_encode([
                'success' => true,
                'tahun' => $tahun,
                'statistics' => $statistics
            ]);
        } catch (Exception $e) {
            error_log("[BpsScraper] getStatistics error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Get chart data
     */
    public function getChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $type = $_GET['type'] ?? 'yearly';
            $cacheTtl = 600; // 10 minutes
            $source = trim((string) ($_GET['source'] ?? ''));
            if ($source !== '' && !in_array($source, ['ksa', 'resmi_webapi', 'manual', 'simulasi'], true)) {
                throw new InvalidArgumentException('Filter sumber data tidak valid');
            }
            $scenario = trim((string) ($_GET['skenario'] ?? 'baseline'));
            if (!in_array($scenario, ['baseline', 'optimis', 'pesimis'], true)) {
                throw new InvalidArgumentException('Skenario tidak valid');
            }
            $chartFilters = [
                'sumber_data_type' => $source,
                'tipe_skenario' => $scenario,
            ];
            $chartFilters = array_filter(
                $chartFilters,
                static fn ($value): bool => $value !== null && $value !== ''
            );
            
            if ($type === 'yearly') {
                $cacheKey = 'bps_chart_yearly_' . hash('sha256', json_encode($chartFilters));
                $cached = $this->cache && $this->cache->isAvailable() ? $this->cache->get($cacheKey) : null;
                if ($cached !== null) {
                    echo json_encode($cached);
                    exit;
                }
                
                $data = $this->model->getYearlyTrend(null, null, $chartFilters);
                
                $labels = [];
                $luasData = [];
                $gabahData = [];
                $berasData = [];
                
                foreach ($data as $row) {
                    $labels[] = $row['tahun'];
                    $luasData[] = round($row['total_luas_panen'] / 1000, 1); // In thousand ha
                    $gabahData[] = round($row['total_produksi_gabah'] / 1000000, 2); // In million tons
                    $berasData[] = round($row['total_produksi_beras'] / 1000000, 2);
                }
                
                $response = [
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Luas Panen (ribu Ha)',
                            'data' => $luasData,
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                            'yAxisID' => 'y'
                        ],
                        [
                            'label' => 'Produksi Gabah (juta Ton)',
                            'data' => $gabahData,
                            'borderColor' => 'rgb(255, 159, 64)',
                            'backgroundColor' => 'rgba(255, 159, 64, 0.5)',
                            'yAxisID' => 'y1'
                        ],
                        [
                            'label' => 'Produksi Beras (juta Ton)',
                            'data' => $berasData,
                            'borderColor' => 'rgb(54, 162, 235)',
                            'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                            'yAxisID' => 'y1'
                        ]
                    ]
                ];
                
                if ($this->cache && $this->cache->isAvailable()) {
                    $this->cache->set($cacheKey, $response, $cacheTtl);
                }
                
                echo json_encode($response);
                
            } elseif ($type === 'top') {
                $tahun = (int) ($_GET['tahun'] ?? $this->_getDefaultYear());
                
                $cacheKey = 'bps_chart_top_' . hash('sha256', json_encode([$tahun, $chartFilters]));
                $cached = $this->cache && $this->cache->isAvailable() ? $this->cache->get($cacheKey) : null;
                if ($cached !== null) {
                    echo json_encode($cached);
                    exit;
                }
                
                $data = $this->model->getTopProducers($tahun, 10, $chartFilters);
                
                $labels = [];
                $gabahData = [];
                
                foreach ($data as $row) {
                    $labels[] = $row['kabupaten_kota'];
                    $gabahData[] = round($row['produksi_gabah'] / 1000, 1); // In thousand tons
                }
                
                $response = [
                    'success' => true,
                    'tahun' => $tahun,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Produksi Gabah (ribu Ton)',
                            'data' => $gabahData,
                            'backgroundColor' => [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 205, 86, 0.7)',
                                'rgba(201, 203, 207, 0.7)',
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 159, 64, 0.5)'
                            ]
                        ]
                    ]
                ];
                
                if ($this->cache && $this->cache->isAvailable()) {
                    $this->cache->set($cacheKey, $response, $cacheTtl);
                }
                
                echo json_encode($response);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid chart type']);
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
     * API: Get kabupaten list
     */
    public function getKabupatenList() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/BpsScraper.php';
            $scraper = new BpsScraper();
            
            echo json_encode([
                'success' => true,
                'data' => $scraper->getKabupatenList()
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
     * Execute scraping process (Admin only)
     */
    public function runScraper() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/BpsScraper.php';
            $scraper = new BpsScraper();
            $scraper->setDebug(APP_DEBUG);

            // Input validation with whitelisting
            $tahun = (int)($_POST['tahun'] ?? date('Y'));
            $maxYear = (int) date('Y') + 1;
            if ($tahun < 2018 || $tahun > $maxYear) {
                throw new Exception("Tahun tidak valid (2018-{$maxYear})");
            }

            $source = (string) ($_POST['source'] ?? '');
            if (!in_array($source, ['simulasi', 'resmi_webapi'], true)) {
                throw new Exception('Pilih sumber eksekusi WebAPI atau simulasi');
            }
            if ($source === 'resmi_webapi'
                && (!defined('BPS_API_KEY') || trim((string) BPS_API_KEY) === '')) {
                throw new Exception('BPS WebAPI belum dikonfigurasi. Gunakan data KSA atau pasang BPS_API_KEY.');
            }

            $skenario = $_POST['skenario'] ?? 'baseline';
            if (!in_array($skenario, ['baseline', 'optimis', 'pesimis'])) {
                $skenario = 'baseline';
            }

             $availableKabupaten = $scraper->getKabupatenList();
            $kabupaten = !empty($_POST['kabupaten']) ? $_POST['kabupaten'] : null;
            if ($kabupaten && !array_key_exists($kabupaten, $availableKabupaten)) {
                throw new Exception('Kabupaten tidak valid');
            }

            // Background mode: queue the job instead of running synchronously
            $useBackground = isset($_POST['background']) && $_POST['background'] === 'true';
            if ($useBackground) {
                $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
                $sql = "INSERT INTO bps_scraping_queue
                            (tahun, kabupaten, source, skenario, force_refresh, created_by, status, progress)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $tahun,
                    $kabupaten,
                    $source,
                    $skenario,
                    (int) $forceRefresh,
                    $_SESSION['user_id'] ?? null
                ]);
                $jobId = (int)$this->db->lastInsertId();

                $this->model->logActivity(
                    'scrape_queue',
                    'success',
                    "Background scraping job #{$jobId} queued: tahun={$tahun}, source={$source}",
                    ['job_id' => $jobId, 'tahun' => $tahun, 'source' => $source, 'kabupaten' => $kabupaten ?? 'all']
                );

                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'background' => true,
                    'message' => 'Scraping berhasil di-queue. Pantau progres di panel ini.'
                ]);
                exit;
            }

            $options = [
                'tahun' => $tahun,
                'kabupaten' => $kabupaten,
                'source' => $source,
                'skenario' => $skenario,
                'force_refresh' => isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true',
            ];
            
            $result = $scraper->run($options);
            
            // Invalidate cache after data write
            $this->clearCache();
            
            // Log scraper execution to database
            try {
                $this->model->logActivity(
                    'scrape_run',
                    $result['success'] ? 'success' : 'error',
                    $result['message'],
                    [
                        'tahun' => $tahun,
                        'source' => $result['source'],
                        'skenario' => $skenario,
                        'kabupaten' => $kabupaten ?? 'all',
                        'records_success' => $result['records_success'],
                        'records_failed' => $result['records_failed'],
                        'records_skipped' => $result['records_skipped'],
                        'execution_time' => $result['execution_time']
                    ]
                );
            } catch (Exception $e) {
                error_log("[BpsScraperController] Failed to log activity: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'records_success' => $result['records_success'],
                'records_failed' => $result['records_failed'],
                'records_skipped' => $result['records_skipped'],
                'execution_time' => $result['execution_time'],
                'errors' => $result['errors'] ?? []
            ]);
        } catch (Exception $e) {
            error_log("[BpsScraperController] runScraper error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Get yearly summary
     */
    public function getYearlySummary() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/BpsDataService.php';
            $service = new BpsDataService();
            
            $tahun = $_GET['tahun'] ?? null;
            $data = $service->getYearlySummary($tahun);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * API: Data luas panen bulanan KSA dengan pagination server-side.
     */
    public function getMonthlyHarvestArea() {
        $this->checkAuth();
        header('Content-Type: application/json');

        try {
            require_once ROOT_PATH . '/app/models/DataKsaBulanan.php';
            $model = new DataKsaBulanan();

            $tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
            $bulan = filter_input(INPUT_GET, 'bulan', FILTER_VALIDATE_INT);
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = (int) ($_GET['per_page'] ?? 38);
            $allowedPerPage = [10, 25, 38, 50, 100];

            if ($tahun !== false && $tahun !== null) {
                $maxYear = (int) date('Y') + 1;
                if ($tahun < 2018 || $tahun > $maxYear) {
                    throw new InvalidArgumentException("Tahun harus antara 2018 dan {$maxYear}");
                }
            }
            if ($bulan !== false && $bulan !== null && ($bulan < 1 || $bulan > 12)) {
                throw new InvalidArgumentException('Bulan harus antara 1 dan 12');
            }
            if (!in_array($perPage, $allowedPerPage, true)) {
                throw new InvalidArgumentException('Jumlah baris harus 10, 25, atau 50');
            }

            $status = trim((string) ($_GET['status_data'] ?? ''));
            if ($status !== '' && !in_array($status, ['tetap', 'sementara', 'potensi'], true)) {
                throw new InvalidArgumentException('Status data tidak valid');
            }

            $filters = array_filter([
                'tahun' => $tahun ?: null,
                'bulan' => $bulan ?: null,
                'kabupaten_kota' => trim((string) ($_GET['kabupaten'] ?? '')),
                'status_data' => $status,
            ], static fn ($value): bool => $value !== null && $value !== '');

            $total = $model->getCountWithFilters($filters);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $rows = $model->getAll($filters, $perPage, ($page - 1) * $perPage);

            $monthNames = [
                1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];
            $data = array_map(static function (array $row) use ($monthNames): array {
                $month = (int) $row['bulan'];
                return [
                    'id' => (int) $row['id'],
                    'kabupaten_kota' => (string) $row['kabupaten_kota'],
                    'bulan' => $month,
                    'nama_bulan' => $monthNames[$month] ?? (string) $month,
                    'tahun' => (int) $row['tahun'],
                    'luas_panen' => $row['luas_panen'] !== null ? (float) $row['luas_panen'] : null,
                    'satuan' => 'Ha',
                    'status_data' => (string) ($row['status_data'] ?? '-'),
                    'sumber_data' => (string) ($row['sumber_file'] ?? 'KSA BPS'),
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ]);
        } catch (Throwable $e) {
            error_log('[BpsScraper] getMonthlyHarvestArea error: ' . $e->getMessage());
            http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
            echo json_encode([
                'success' => false,
                'error' => $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Gagal memuat data luas panen bulanan',
            ]);
        }
        exit;
    }

    /**
     * API: Agregasi luas panen bulanan untuk line chart.
     */
    public function getMonthlyHarvestChart() {
        $this->checkAuth();
        header('Content-Type: application/json');

        try {
            require_once ROOT_PATH . '/app/models/DataKsaBulanan.php';
            $model = new DataKsaBulanan();
            $tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
            $maxYear = (int) date('Y') + 1;
            if ($tahun === false || $tahun === null || $tahun < 2018 || $tahun > $maxYear) {
                throw new InvalidArgumentException("Tahun harus antara 2018 dan {$maxYear}");
            }

            $kabupaten = trim((string) ($_GET['kabupaten'] ?? ''));
            if (mb_strlen($kabupaten) > 100) {
                throw new InvalidArgumentException('Nama kabupaten/kota terlalu panjang');
            }

            $rows = $model->getMonthlyHarvestAreaChart((int) $tahun, $kabupaten);
            $monthNames = [
                1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
            ];
            $values = array_fill(1, 12, null);
            $coverage = array_fill(1, 12, 0);
            foreach ($rows as $row) {
                $month = (int) $row['bulan'];
                if ($month >= 1 && $month <= 12) {
                    $values[$month] = $row['luas_panen'] !== null ? (float) $row['luas_panen'] : null;
                    $coverage[$month] = (int) $row['jumlah_wilayah'];
                }
            }

            // Multi-series: satu dataset per kabupaten/kota (bukan gabungan)
            $seriesPerKab = $model->getMonthlyHarvestChartPerKabupaten((int) $tahun, 38);
            $datasetsPerKab = [];
            $palette = ['#1cc88a','#4e73df','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#fd7e14','#20c9a6','#6610f2','#e83e8c','#ffc107','#17a2b8','#6f42c1','#28a745','#dc3545','#007bff','#6c757d'];
            $idx = 0;
            foreach ($seriesPerKab as $kabName => $monthValues) {
                $data12 = [];
                for ($m = 1; $m <= 12; $m++) {
                    $data12[] = isset($monthValues[$m]) ? (float) $monthValues[$m] : null;
                }
                $datasetsPerKab[] = [
                    'label' => $kabName,
                    'data' => $data12,
                    'borderColor' => $palette[$idx % count($palette)],
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 2,
                    'tension' => 0.25,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 4,
                    'spanGaps' => true,
                ];
                $idx++;
            }

            echo json_encode([
                'success' => true,
                'labels' => array_values($monthNames),
                'values' => array_values($values),
                'coverage' => array_values($coverage),
                'datasets_per_kabupaten' => $datasetsPerKab,
                'meta' => [
                    'tahun' => (int) $tahun,
                    'scope' => $kabupaten !== '' ? $kabupaten : 'Jawa Timur',
                    'satuan' => 'Ha',
                ],
            ]);
        } catch (Throwable $e) {
            error_log('[BpsScraper] getMonthlyHarvestChart error: ' . $e->getMessage());
            http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
            echo json_encode([
                'success' => false,
                'error' => $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Gagal memuat grafik luas panen bulanan',
            ]);
        }
        exit;
    }
    
    /**
     * API: Get anomalies (Admin only)
     */
    public function getAnomalies() {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/BpsDataService.php';
            $service = new BpsDataService();
            
            $filters = [
                'tahun' => $_GET['tahun'] ?? null,
                'status' => $_GET['status'] ?? null,
                'limit' => $_GET['limit'] ?? 50
            ];
            
            $anomalies = $service->getAnomalies($filters);
            
            echo json_encode([
                'success' => true,
                'data' => $anomalies
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Delete data by year (Admin only)
     */
    public function deleteByYear() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $tahun = $_POST['tahun'] ?? null;
            
            if (!$tahun) {
                throw new Exception('Tahun harus diisi');
            }
            
            $result = $this->model->deleteByYear($tahun);
            $this->clearCache();
            $this->refreshYearlySummary((int) $tahun);
            $this->model->logActivity('delete', 'success', "Data tahun {$tahun} dihapus", []);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? "Data tahun {$tahun} berhasil dihapus" : "Gagal menghapus data"
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
     * Export data to CSV
     */
    public function export() {
        $this->checkAuth();
        
        $filters = [
            'tahun' => $_GET['tahun'] ?? null,
            'kabupaten_kota' => $_GET['kabupaten'] ?? null,
            'sumber_data_type' => $_GET['source'] ?? null,
            'sumber_data_like' => $_GET['sumber'] ?? null,
            'tipe_skenario' => $_GET['skenario'] ?? 'baseline',
            'is_validated' => 1,
        ];
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        $allowedSources = ['ksa', 'resmi_webapi', 'manual', 'simulasi'];
        if (!empty($filters['sumber_data_type'])
            && !in_array($filters['sumber_data_type'], $allowedSources, true)) {
            throw new InvalidArgumentException('Filter sumber data tidak valid');
        }
        if (empty($filters['sumber_data_type'])) {
            $filters['preferred_only'] = true;
        }
        
        $data = $this->model->getAll($filters);
        
        $filename = 'data_pertanian_bps_jatim_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, [
            'Tahun',
            'Kabupaten/Kota',
            'Kode Wilayah',
            'Luas Panen (Ha)',
            'Produksi Gabah (Ton)',
            'Produksi Beras (Ton)',
            'Produktivitas (Ku/Ha)',
            'Sumber Data',
            'Tipe',
            'Skenario',
            'Validasi'
        ]);
        
        // Data rows
        foreach ($data as $row) {
            $csvRow = [
                $row['tahun'],
                $row['kabupaten_kota'],
                $row['kode_wilayah'],
                $row['luas_panen'],
                $row['produksi_gabah'],
                $row['produksi_beras'],
                $row['produktivitas'],
                $row['sumber_data'],
                $row['sumber_data_type'] ?? '-',
                $row['tipe_skenario'] ?? '-',
                $row['is_validated'] ? 'Valid' : 'Invalid'
            ];
            
            // Sanitize to prevent CSV formula injection
            $csvRow = array_map([$this, 'sanitizeCsvValue'], $csvRow);
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Import data from Excel file (Admin only)
     */
    public function importExcel() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }

        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Tidak ada file yang diupload');
            }

            $file = $_FILES['excel_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ['xlsx', 'csv'], true)) {
                throw new Exception('Format file tidak didukung. Gunakan xlsx atau csv');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'application/csv'
            ];
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Tipe file tidak didukung');
            }

            $maxSize = 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new Exception('File terlalu besar (maksimal 5MB)');
            }

            $uploadDir = ROOT_PATH . '/storage/uploads/temp/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $tempFile = $uploadDir . uniqid('import_bps_') . '.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Gagal memindahkan file upload');
            }
            
            require_once ROOT_PATH . '/app/services/ExcelImportService.php';
            $importService = new ExcelImportService();

            if (isset($_SESSION['import_temp_file']) && is_file($_SESSION['import_temp_file'])) {
                unlink($_SESSION['import_temp_file']);
            }
            $result = $importService->import($tempFile, 'data_pertanian_bps');
            
            // Invalidate cache after data import
            $this->clearCache();
            foreach ($this->model->getAvailableYears() as $year) {
                $this->refreshYearlySummary((int) $year);
            }
            
            // Cleanup temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            // Clear session reference
            if (isset($_SESSION['import_temp_file'])) {
                unset($_SESSION['import_temp_file']);
            }
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Preview import data (Admin only)
     */
    public function previewImport() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');

        // Cleanup orphan temporary file from previous preview/import
        if (isset($_SESSION['import_temp_file']) && file_exists($_SESSION['import_temp_file'])) {
            @unlink($_SESSION['import_temp_file']);
        }
        unset($_SESSION['import_temp_file']);
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Tidak ada file yang diupload');
            }

            $file = $_FILES['excel_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ['xlsx', 'csv'], true)) {
                throw new Exception('Format file tidak didukung');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'application/csv'
            ];
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Tipe file tidak didukung');
            }

            // Samakan dengan batas import agar file yang lolos preview pasti dapat diimpor.
            $maxSize = 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new Exception('File terlalu besar (maksimal 5MB)');
            }

            $uploadDir = ROOT_PATH . '/storage/uploads/temp/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $tempFile = $uploadDir . uniqid('preview_') . '.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Gagal memindahkan file');
            }
            
            require_once ROOT_PATH . '/app/services/ExcelImportService.php';
            $importService = new ExcelImportService();
            $preview = $importService->generatePreview($tempFile, 10);
            
            $_SESSION['import_temp_file'] = $tempFile;
            
            echo json_encode($preview);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Download Excel import template
     */
    public function downloadTemplate() {
        $this->checkAuth();
        
        require_once ROOT_PATH . '/app/services/ExcelImportService.php';
        $importService = new ExcelImportService();
        $csv = $importService->generateTemplate('data_pertanian_bps');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_data_pertanian_bps.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }
    
    /**
     * Store new record (Admin only)
     */
    public function store() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $data = [
                'tahun' => $_POST['tahun'] ?? null,
                'kabupaten_kota' => $_POST['kabupaten_kota'] ?? null,
                'kode_wilayah' => $_POST['kode_wilayah'] ?? null,
                'luas_panen' => floatval($_POST['luas_panen'] ?? 0),
                'produksi_gabah' => floatval($_POST['produksi_gabah'] ?? 0),
                'produksi_beras' => floatval($_POST['produksi_beras'] ?? 0),
                'produktivitas' => floatval($_POST['produktivitas'] ?? 0),
                'sumber_data' => 'Manual',
                'sumber_data_type' => 'manual',
                'tipe_skenario' => 'baseline',
                'is_validated' => 1,
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            // Validasi
            if (empty($data['tahun'])) {
                throw new Exception('Tahun harus diisi');
            }
            if (empty($data['kabupaten_kota'])) {
                throw new Exception('Kabupaten/Kota harus diisi');
            }
            if ($data['luas_panen'] <= 0) {
                throw new Exception('Luas panen harus lebih dari 0');
            }
            if ($data['produksi_gabah'] <= 0) {
                throw new Exception('Produksi gabah harus lebih dari 0');
            }
            
            // Auto-calculate if not provided
            if ($data['produksi_beras'] <= 0) {
                $data['produksi_beras'] = round($data['produksi_gabah'] * 0.577, 2);
            }
            if ($data['produktivitas'] <= 0 && $data['luas_panen'] > 0) {
                $data['produktivitas'] = round(($data['produksi_gabah'] / $data['luas_panen']) * 10, 2);
            }

            require_once ROOT_PATH . '/app/services/BpsDataService.php';
            $validation = (new BpsDataService())->validateRecord($data);
            if (!$validation['valid']) {
                throw new InvalidArgumentException(
                    'Data tidak lolos validasi: ' . implode('; ', $validation['issues'])
                );
            }
            
            $result = $this->model->insert($data);
            
            if ($result) {
                $this->clearCache();
                $this->refreshYearlySummary((int) $data['tahun']);
                $this->model->logActivity('store', 'success', "Data {$data['kabupaten_kota']} tahun {$data['tahun']} ditambahkan", []);
                echo json_encode([
                    'success' => true,
                    'message' => 'Data berhasil ditambahkan'
                ]);
            } else {
                throw new Exception('Gagal menyimpan data');
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
     * Queue a background scraping job (Admin only)
     * Returns job_id for polling via getScraperStatus()
     */
    public function runScraperBackground() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        try {
            $tahun = (int) ($_POST['tahun'] ?? 0);
            $kabupaten = $_POST['kabupaten'] ?? null;
            $source = (string) ($_POST['source'] ?? '');
            $skenario = $_POST['skenario'] ?? 'baseline';
            
            $maxYear = (int) date('Y') + 1;
            if ($tahun < 2018 || $tahun > $maxYear) {
                throw new Exception("Tahun tidak valid (2018-{$maxYear})");
            }
            if (!in_array($source, ['simulasi', 'resmi_webapi'], true)) {
                throw new Exception('Sumber scraper tidak valid');
            }
            if (!in_array($skenario, ['baseline', 'optimis', 'pesimis'], true)) {
                throw new Exception('Skenario tidak valid');
            }
            if ($kabupaten !== null && $kabupaten !== ''
                && !in_array($kabupaten, $this->model->getKabupatenList(), true)) {
                throw new Exception('Kabupaten tidak valid');
            }
            if ($source === 'resmi_webapi'
                && (!defined('BPS_API_KEY') || trim((string) BPS_API_KEY) === '')) {
                throw new Exception('BPS WebAPI belum dikonfigurasi');
            }
            
            if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
                exit;
            }
            
            $forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            $sql = "INSERT INTO bps_scraping_queue
                        (tahun, kabupaten, source, skenario, force_refresh, created_by, status, progress)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $tahun,
                $kabupaten ?: null,
                $source,
                $skenario,
                (int) $forceRefresh,
                $_SESSION['user_id'] ?? null
            ]);
            $jobId = (int)$this->db->lastInsertId();
            
            $this->model->logActivity(
                'scrape_queue',
                'success',
                "Background scraping job #{$jobId} queued: tahun={$tahun}, source={$source}",
                ['job_id' => $jobId, 'tahun' => $tahun, 'source' => $source, 'kabupaten' => $kabupaten]
            );
            
            echo json_encode([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Scraping berhasil di-queue. Gunakan getScraperStatus untuk memantau progres.'
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
     * Get background scraping job status
     */
    public function getScraperStatus($jobId = null) {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        try {
            $jobId = filter_var($jobId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$jobId) {
                throw new Exception('Job ID tidak ditemukan');
            }
            
            $stmt = $this->db->prepare(
                "SELECT id, tahun, kabupaten, source, skenario, force_refresh, status, progress, result, error_message,
                        created_at, started_at, completed_at
                 FROM bps_scraping_queue WHERE id = ?"
            );
            $stmt->execute([$jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) {
                throw new Exception('Job tidak ditemukan');
            }
            
            $result = null;
            if ($job['result']) {
                $result = json_decode($job['result'], true);
            }
            
            echo json_encode([
                'success' => true,
                'job' => [
                    'id' => (int)$job['id'],
                    'tahun' => (int)$job['tahun'],
                    'kabupaten' => $job['kabupaten'],
                    'source' => $job['source'],
                    'skenario' => $job['skenario'],
                    'status' => $job['status'],
                    'progress' => (int)$job['progress'],
                    'result' => $result,
                    'error_message' => $job['error_message'],
                    'created_at' => $job['created_at'],
                    'started_at' => $job['started_at'],
                    'completed_at' => $job['completed_at']
                ]
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
     * Get single record for edit
     */
    public function getRecord($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        try {
            if (!$id) {
                throw new Exception('ID tidak ditemukan');
            }
            
            $record = $this->model->getById($id);
            
            if (!$record) {
                throw new Exception('Data tidak ditemukan');
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
     * Update record (Admin only)
     */
    public function update($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            if (!$id) {
                throw new Exception('ID tidak ditemukan');
            }

            $existing = $this->model->getById((int) $id);
            if (!$existing) {
                throw new Exception('Data tidak ditemukan');
            }

            $data = [
                'tahun' => $_POST['tahun'] ?? null,
                'kabupaten_kota' => $_POST['kabupaten_kota'] ?? null,
                'kode_provinsi' => $existing['kode_provinsi'] ?? '35',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? ($existing['kode_wilayah'] ?? null),
                'luas_panen' => floatval($_POST['luas_panen'] ?? 0),
                'produksi_gabah' => floatval($_POST['produksi_gabah'] ?? 0),
                'produksi_beras' => floatval($_POST['produksi_beras'] ?? 0),
                'produktivitas' => floatval($_POST['produktivitas'] ?? 0),
                'sumber_data' => $existing['sumber_data'] ?? 'Input Manual',
                'sumber_data_type' => $existing['sumber_data_type'] ?? 'manual',
                'tipe_skenario' => $existing['tipe_skenario'] ?? 'baseline',
                'is_validated' => $existing['is_validated'] ?? 0,
                'validation_notes' => $existing['validation_notes'] ?? null,
                'keterangan' => $_POST['keterangan'] ?? ($existing['keterangan'] ?? null),
            ];

            if (empty($data['tahun']) || empty($data['kabupaten_kota'])
                || $data['luas_panen'] <= 0 || $data['produksi_gabah'] <= 0) {
                throw new InvalidArgumentException('Tahun, kabupaten, luas panen, dan produksi wajib valid');
            }
            
            // Auto-calculate if not provided
            if ($data['produksi_beras'] <= 0 && $data['produksi_gabah'] > 0) {
                $data['produksi_beras'] = round($data['produksi_gabah'] * 0.577, 2);
            }
            if ($data['produktivitas'] <= 0 && $data['luas_panen'] > 0) {
                $data['produktivitas'] = round(($data['produksi_gabah'] / $data['luas_panen']) * 10, 2);
            }

            require_once ROOT_PATH . '/app/services/BpsDataService.php';
            $validation = (new BpsDataService())->validateRecord($data);
            if (!$validation['valid']) {
                throw new InvalidArgumentException(
                    'Data tidak lolos validasi: ' . implode('; ', $validation['issues'])
                );
            }
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
                $this->clearCache();
                $this->refreshYearlySummary((int) $existing['tahun']);
                if ((int) $existing['tahun'] !== (int) $data['tahun']) {
                    $this->refreshYearlySummary((int) $data['tahun']);
                }
                $this->model->logActivity('update', 'success', "Data ID {$id} diupdate", []);
                echo json_encode([
                    'success' => true,
                    'message' => 'Data berhasil diupdate'
                ]);
            } else {
                throw new Exception('Gagal mengupdate data');
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
     * Delete record (Admin only)
     */
    public function delete($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            if (!$id) {
                throw new Exception('ID tidak ditemukan');
            }

            $existing = $this->model->getById((int) $id);
            if (!$existing) {
                throw new Exception('Data tidak ditemukan');
            }

            $result = $this->model->delete($id);
            
            if ($result) {
                $this->clearCache();
                $this->refreshYearlySummary((int) $existing['tahun']);
                $this->model->logActivity('delete', 'success', "Data ID {$id} dihapus", []);
                echo json_encode([
                    'success' => true,
                    'message' => 'Data berhasil dihapus'
                ]);
            } else {
                throw new Exception('Gagal menghapus data');
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
     * Import file dataset KSA (Angka Tetap / Bulanan) ke data_ksa_bulanan (Admin only)
     *
     * POST 'file' (upload XLSX) atau POST 'path' (path file di server, dibatasi
     * ke direktori ROOT_PATH/data/ksa). Tipe file dideteksi dari nama file:
     *   - mengandung "Angka Tetap"  -> importAngkaTetap()
     *   - pola "2026.XX KSA Jatim"  -> importKsaBulanan()
     */
    public function importKsa() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/KsaImportService.php';
            $service = new KsaImportService();
            $filePath = null;
            $fileName = null;
            
            // Opsi 1: path file di server (dibatasi ke data/ksa)
            if (!empty($_POST['path'])) {
                $baseDir = realpath(ROOT_PATH . '/data/ksa');
                $requested = realpath((string) $_POST['path']);
                if (($requested === false || $baseDir === false) && !str_contains((string) $_POST['path'], DIRECTORY_SEPARATOR)) {
                    // Path berupa nama file saja -> resolve ke data/ksa
                    $requested = realpath($baseDir . DIRECTORY_SEPARATOR . basename((string) $_POST['path']));
                }
                if ($requested === false || $baseDir === false || !str_starts_with($requested, $baseDir . DIRECTORY_SEPARATOR)) {
                    throw new Exception('Path file tidak diizinkan (hanya dari direktori data/ksa)');
                }
                if (!is_file($requested)) {
                    throw new Exception('File tidak ditemukan di server');
                }
                $filePath = $requested;
                $fileName = basename($requested);
            }
            
            // Opsi 2: upload file
            if ($filePath === null) {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Tidak ada file yang diupload (atau field "path" kosong)');
                }
                
                $file = $_FILES['file'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($extension !== 'xlsx') {
                    throw new Exception('Format file tidak didukung. Gunakan xlsx');
                }
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($mimeType !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                    throw new Exception('Tipe file tidak didukung (bukan XLSX)');
                }
                
                $maxSize = 10 * 1024 * 1024;
                if ($file['size'] > $maxSize) {
                    throw new Exception('File terlalu besar (maksimal 10MB)');
                }
                
                $uploadDir = ROOT_PATH . '/storage/uploads/temp/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $tempFile = $uploadDir . uniqid('ksa_import_') . '.xlsx';
                if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                    throw new Exception('Gagal memindahkan file upload');
                }
                $filePath = $tempFile;
                $fileName = $file['name'];
            }
            
            // Deteksi tipe file dari nama
            $lowerName = strtolower($fileName);
            if (str_contains($lowerName, 'angka tetap')) {
                $result = $service->importAngkaTetap($filePath);
            } elseif (preg_match('/^2025\.\d{1,2}.*ksa jatim/i', $fileName) || preg_match('/^2026\.\d{1,2}.*ksa jatim/i', $fileName)) {
                $result = $service->importKsaBulanan($filePath);
            } else {
                throw new Exception('Nama file tidak dikenali (harus "Angka Tetap" atau "2026.XX KSA Jatim")');
            }
            
            // Invalidate cache after KSA import
            $this->clearCache();
            
            if (!empty($_FILES['file']) && isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * API: Status data KSA (total per tahun, breakdown status, kabupaten tercakup,
     * riwayat import terakhir, dan file sumber yang tersedia)
     */
    public function getKsaStatus() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/KsaImportService.php';
            require_once ROOT_PATH . '/app/models/DataKsaBulanan.php';
            $service = new KsaImportService();
            $model = new DataKsaBulanan();
            
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query(
                "SELECT tahun,
                        COUNT(*) AS total,
                        SUM(status_data = 'tetap')     AS tetap,
                        SUM(status_data = 'sementara') AS sementara,
                        SUM(status_data = 'potensi')   AS potensi
                 FROM data_ksa_bulanan
                 GROUP BY tahun ORDER BY tahun DESC"
            );
            $perTahun = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $db->query("SELECT COUNT(DISTINCT kode_wilayah) FROM data_ksa_bulanan");
            $jumlahKabupaten = (int) $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM data_ksa_bulanan");
            $totalRecords = (int) $stmt->fetchColumn();
            
            $stmt = $db->query(
                "SELECT action, status, message, created_at
                 FROM bps_scraping_logs
                 WHERE action LIKE 'ksa_%'
                 ORDER BY created_at DESC LIMIT 10"
            );
            $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'total_records' => $totalRecords,
                'jumlah_kabupaten' => $jumlahKabupaten,
                'per_tahun' => $perTahun,
                'recent_imports' => $recentLogs,
                'files' => [
                    'angka_tetap' => array_map('basename', $service->getAngkaTetapFiles(ROOT_PATH . '/data/ksa')),
                    'bulanan' => array_map('basename', $service->getKsaBulananFiles(ROOT_PATH . '/data/ksa')),
                ]
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
     * Sinkronisasi agregat tahunan KSA (status tetap) ke data_pertanian_bps (Admin only)
     */
    public function syncKsaToAnnual() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $tahun = (int) ($_POST['tahun'] ?? 0);
            if ($tahun < 2000 || $tahun > 2100) {
                throw new Exception('Tahun tidak valid (2000-2100)');
            }
            
            require_once ROOT_PATH . '/app/services/KsaImportService.php';
            $service = new KsaImportService();
            $result = $service->syncToDataPertanianBps($tahun);
            
            // Invalidate cache after KSA sync to annual table
            $this->clearCache();
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}
