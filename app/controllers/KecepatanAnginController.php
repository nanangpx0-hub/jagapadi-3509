<?php
/**
 * Kecepatan Angin Controller
 * Controller untuk dashboard dan API kecepatan angin
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class KecepatanAnginController extends Controller {
    
    private $model;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/KecepatanAngin.php';
        require_once ROOT_PATH . '/app/core/CacheManager.php';
        $this->model = new KecepatanAngin();
        
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
            'openmeteo' => ['sumber_data_like' => '%Open-Meteo%'],
            'simulation' => ['sumber_data_like' => '%Simulasi%'],
            'all' => [],
            default => ['sumber_data_like' => '%NASA%'],
        };
    }
    
    /**
     * Dashboard utama kecepatan angin
     */
    public function index() {
        $this->checkAuth();

        $selectedSource = in_array($_GET['data_source'] ?? 'nasa', ['nasa', 'openmeteo', 'simulation', 'all'], true)
            ? ($_GET['data_source'] ?? 'nasa')
            : 'nasa';

        $maxYear = (int) date('Y');
        $requestedYear = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
        $selectedYear = ($requestedYear !== false && $requestedYear !== null && $requestedYear >= 2000 && $requestedYear <= $maxYear)
            ? $requestedYear
            : ($this->model->getLatestYearWithData($this->getSourceFilter($selectedSource)) ?: $maxYear);

        $selectedMonth = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
        $selectedMonth = $selectedMonth && $selectedMonth >= 1 && $selectedMonth <= 12
            ? $selectedMonth
            : null;
        $filters = array_merge(['year' => $selectedYear], $this->getSourceFilter($selectedSource));
        if ($selectedMonth !== null) {
            $filters['month'] = $selectedMonth;
        }

        $data = [
            'title' => 'Kecepatan Angin - JAGAPADI',
            'page_title' => 'Data Kecepatan Angin Kabupaten Jember',
            'availableYears' => $this->model->getAvailableYears(),
            'currentYear' => $selectedYear,
            'currentMonth' => $selectedMonth,
            'currentSource' => $selectedSource
        ];
        
        $data['statistics'] = $this->model->getStatistics($filters);
        
        $data['monthlyData'] = $this->model->getMonthlyAverage($selectedYear, $this->getSourceFilter($selectedSource));
        
        $data['recentData'] = $this->model->getAll(array_merge($filters, [
            'limit' => 10,
            'offset' => 0
        ]));

        // --- Data lintas tahun untuk perbandingan (Grafik, Distribusi, Tabel) ---
        $sourceOnlyFilters = $this->getSourceFilter($selectedSource);
        $sourceMonthFilters = $sourceOnlyFilters;
        if ($selectedMonth !== null) {
            $sourceMonthFilters['month'] = $selectedMonth;
        }
        $allYears = $data['availableYears'];
        if (empty($allYears)) {
            $allYears = [$selectedYear];
        }
        sort($allYears);
        $minYear = min($allYears);
        $maxYear = max($allYears);
        $trendRows = $this->model->getTrendAnalysis($minYear, $maxYear, $sourceOnlyFilters);
        $allMonthlyByYear = [];
        foreach ($allYears as $y) {
            $allMonthlyByYear[$y] = array_fill(1, 12, 0);
        }
        foreach ($trendRows as $row) {
            $y = (int) ($row['tahun'] ?? 0);
            $m = (int) ($row['bulan'] ?? 0);
            if ($y && $m >= 1 && $m <= 12) {
                if (!isset($allMonthlyByYear[$y])) {
                    $allMonthlyByYear[$y] = array_fill(1, 12, 0);
                }
                $allMonthlyByYear[$y][$m] = (float) ($row['rata_rata'] ?? 0);
            }
        }
        // Hapus tahun tanpa data untuk sumber terpilih agar grafik tidak menampilkan garis nol
        foreach ($allMonthlyByYear as $y => $months) {
            if (array_sum($months) == 0) {
                unset($allMonthlyByYear[$y]);
            }
        }
        ksort($allMonthlyByYear);
        $data['allMonthlyByYear'] = $allMonthlyByYear;
        $data['allYearsList'] = array_keys($allMonthlyByYear);
        $windRose = $this->model->getDirectionDistribution($sourceMonthFilters);
        $windRoseTotal = array_sum(array_column($windRose, 'count'));
        if ($windRoseTotal === 0) {
            // NASA tidak menyimpan arah — fallback ke semua sumber agar radar tetap tampil
            $fallbackFilters = $selectedMonth !== null ? ['month' => $selectedMonth] : [];
            $windRose = $this->model->getDirectionDistribution($fallbackFilters);
            $data['windRoseFallback'] = true;
        } else {
            $data['windRoseFallback'] = false;
        }
        $data['windRoseAll'] = $windRose;
        $data['tableAllYears'] = $this->model->getAll(array_merge($sourceMonthFilters, [
            'limit' => 100,
            'offset' => 0
        ]));
        $data['tableAllYearsTotal'] = $this->model->countAll($sourceMonthFilters);
        // Yearly summary untuk header ringkas perbandingan
        $data['yearlySummaryAll'] = $this->model->getYearlySummary(count($allYears), $sourceOnlyFilters);
        
        // Get logs for admin
        if ($_SESSION['role'] === 'admin') {
            $data['recentLogs'] = $this->model->getRecentLogs(5);
        }

        $data['lastScrape'] = null;
        $lastLog = $this->model->getRecentLogs(1);
        if (!empty($lastLog)) {
            $data['lastScrape'] = $lastLog[0];
        }
        
        $this->view('kecepatan_angin/index', $data);
    }
    
    /**
     * Format record for API response
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
            'kecepatan_angin' => (float)$record['kecepatan_angin'],
            'kecepatan_max' => $record['kecepatan_max'] ? (float)$record['kecepatan_max'] : null,
            'arah_angin' => $record['arah_angin'] ?? null,
            'arah_angin_desc' => $record['arah_angin_desc'] ?? null,
            'satuan' => $record['satuan'] ?? 'km/h',
            'sumber_data' => $record['sumber_data'],
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
            $filters = [
                'year' => $_GET['year'] ?? null,
                'month' => $_GET['month'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'openmeteo') {
                $filters['sumber_data_like'] = '%Open-Meteo%';
            } elseif ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $filters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'simulation') {
                $filters['sumber_data_like'] = '%Simulasi%';
            }
            
            $filters = array_filter($filters, function($v) { return $v !== null; });
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);
            $statistics = $this->model->getStatistics($filters);
            
            $baseFilters = $filters;
            unset($baseFilters['sumber_data_like']);
            $statistics['data_composition'] = $this->model->getDataSourceBreakdown($baseFilters);
            
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

        $cacheKey = 'stats_kecepatan_angin_chart_' . md5($_SERVER['QUERY_STRING'] ?? '');
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
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            if ($type === 'monthly') {
                $data = $this->model->getMonthlyAverage($year, $sourceFilters);
                
                $labels = [];
                $avgValues = [];
                $maxValues = [];
                
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                
                for ($i = 1; $i <= 12; $i++) {
                    $labels[] = $monthNames[$i - 1];
                    $avgValues[$i] = 0;
                    $maxValues[$i] = 0;
                }
                
                foreach ($data as $row) {
                    $bulan = (int) $row['bulan'];
                    $avgValues[$bulan] = (float) $row['rata_rata'];
                    $maxValues[$bulan] = (float) ($row['maksimum'] ?? 0);
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Rata-rata Kecepatan Angin (km/h)',
                            'data' => array_values($avgValues),
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                            'tension' => 0.3
                        ],
                        [
                            'label' => 'Kecepatan Maksimum (km/h)',
                            'data' => array_values($maxValues),
                            'borderColor' => 'rgb(255, 99, 132)',
                            'backgroundColor' => 'rgba(255, 99, 132, 0.5)',
                            'tension' => 0.3
                        ]
                    ]
                ]);
            } elseif ($type === 'yearly') {
                $data = $this->model->getYearlySummary(5, $sourceFilters);
                
                $labels = [];
                $avgValues = [];
                $maxValues = [];
                
                foreach (array_reverse($data) as $row) {
                    $labels[] = $row['tahun'];
                    $avgValues[] = (float) $row['rata_rata'];
                    $maxValues[] = (float) ($row['maksimum'] ?? 0);
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Rata-rata (km/h)',
                            'data' => $avgValues,
                            'backgroundColor' => 'rgba(75, 192, 192, 0.7)'
                        ],
                        [
                            'label' => 'Maksimum (km/h)',
                            'data' => $maxValues,
                            'backgroundColor' => 'rgba(255, 99, 132, 0.7)'
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

        $cacheKey = 'stats_kecepatan_angin_stats_' . md5($_SERVER['QUERY_STRING'] ?? '');
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
                'end_date' => $_GET['end_date'] ?? null
            ];

            $dataSource = $_GET['data_source'] ?? 'nasa';
            if ($dataSource === 'nasa' || $dataSource === 'nasa_power') {
                $filters['sumber_data_like'] = '%NASA%';
            } elseif ($dataSource === 'openmeteo') {
                $filters['sumber_data_like'] = '%Open-Meteo%';
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
            require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
            $scraper = new KecepatanAnginScraper();
            
            $options = [
                'year' => $_POST['year'] ?? date('Y'),
                'month' => $_POST['month'] ?? date('m'),
                'source' => $_POST['source'] ?? $_POST['data_source'] ?? 'nasa',
                'force_simulation' => isset($_POST['force_simulation'])
            ];
            
            $result = $scraper->run($options);
            if ($result['success']) {
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
            }
            
            $jsonOutput = json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'no_data' => $result['no_data'] ?? false,
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
     * Endpoint terdedikasi NASA POWER API untuk kecepatan angin
     */
    public function fetch_nasa_kecepatan_angin() {
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
            require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
            $scraper = new KecepatanAnginScraper();
            
            $year = $_POST['year'] ?? date('Y');
            $month = $_POST['month'] ?? date('m');
            
            $result = $scraper->run([
                'year' => $year,
                'month' => $month,
                'source' => 'nasa',
                'allow_fallback' => true,
            ]);

            if ($result['success']) {
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
            }

            $json = [
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'records_success' => $result['records_success'],
                'records_failed' => $result['records_failed'],
                'execution_time' => $result['execution_time'],
                'fallback_used' => $result['fallback_used'] ?? false,
                'fallback_reason' => $result['fallback_reason'] ?? '',
            ];

            if (!$result['success']) {
                $json['error'] = $result['message'];
                unset($json['message']);
            }

            $jsonOutput = json_encode($json);


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
     * Store manual data entry
     */
    public function store() {
        $this->checkAuth();
        $this->checkAdmin();
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Token keamanan tidak valid';
            header('Location: ' . BASE_URL . '/kecepatanAngin');
            exit;
        }
        
        try {
            $data = [
                'tanggal' => $_POST['tanggal'] ?? null,
                'lokasi' => $_POST['lokasi'] ?? 'Jember',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'kecepatan_angin' => $_POST['kecepatan_angin'] ?? 0,
                'kecepatan_max' => $_POST['kecepatan_max'] ?? null,
                'arah_angin' => $_POST['arah_angin'] ?? null,
                'arah_angin_desc' => $_POST['arah_angin_desc'] ?? null,
                'satuan' => 'km/h',
                'sumber_data' => 'Manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            if (empty($data['tanggal'])) {
                throw new Exception('Tanggal harus diisi');
            }

            $date = DateTime::createFromFormat('!Y-m-d', (string) $data['tanggal']);
            if (!$date || $date->format('Y-m-d') !== $data['tanggal'] || $data['tanggal'] > date('Y-m-d')) {
                throw new Exception('Tanggal tidak valid atau berada di masa depan');
            }
            
            if (!is_numeric($data['kecepatan_angin']) || $data['kecepatan_angin'] < 0 || $data['kecepatan_angin'] > 200) {
                throw new Exception('Kecepatan angin harus antara 0-200 km/h');
            }

            if ($data['kecepatan_max'] !== null && $data['kecepatan_max'] !== ''
                && (!is_numeric($data['kecepatan_max']) || $data['kecepatan_max'] < $data['kecepatan_angin'] || $data['kecepatan_max'] > 250)) {
                throw new Exception('Kecepatan maksimum harus lebih besar atau sama dengan rata-rata dan maksimal 250 km/h');
            }

            if ($data['arah_angin'] !== null && $data['arah_angin'] !== ''
                && (!is_numeric($data['arah_angin']) || $data['arah_angin'] < 0 || $data['arah_angin'] >= 360)) {
                throw new Exception('Arah angin harus berada pada rentang 0-359 derajat');
            }
            
            $result = $this->model->insert($data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
                $this->model->logActivity('manual_entry', 'success', 'Data kecepatan angin ditambahkan', [
                    'processed' => 1,
                    'success' => 1,
                    'failed' => 0
                ]);
                
                $_SESSION['success'] = 'Data kecepatan angin berhasil ditambahkan';
            } else {
                throw new Exception('Gagal menyimpan data');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . '/kecepatanAngin');
        exit;
    }
    
    /**
     * API: Get single record for editing (Admin only)
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
     */
    public function update($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $tanggal = $_POST['tanggal'] ?? null;
            $kecepatanAngin = $_POST['kecepatan_angin'] ?? null;
            $lokasi = $_POST['lokasi'] ?? 'Jember';
            
            if (empty($tanggal)) {
                throw new Exception('Tanggal harus diisi');
            }

            $date = DateTime::createFromFormat('!Y-m-d', (string) $tanggal);
            if (!$date || $date->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
                throw new Exception('Tanggal tidak valid atau berada di masa depan');
            }
            
            if (!is_numeric($kecepatanAngin) || $kecepatanAngin < 0 || $kecepatanAngin > 200) {
                throw new Exception('Kecepatan angin harus antara 0-200 km/h');
            }
            
            $existing = $this->model->getById($id);
            if (!$existing) {
                throw new Exception('Data tidak ditemukan');
            }
            
            $data = [
                'tanggal' => $tanggal,
                'lokasi' => $lokasi,
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'kecepatan_angin' => floatval($kecepatanAngin),
                'kecepatan_max' => $_POST['kecepatan_max'] ?? null,
                'arah_angin' => $_POST['arah_angin'] ?? null,
                'arah_angin_desc' => $_POST['arah_angin_desc'] ?? null,
                'sumber_data' => $existing['sumber_data'] ?? 'Manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
                $this->model->logActivity('update', 'success', "Data kecepatan angin ID {$id} diperbarui", [
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
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $result = $this->model->delete($id);
            if ($result) {
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
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
     * Delete multiple data records (Admin only)
     */
    public function deleteMultiple() {
        $this->checkAuth();
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
                $this->invalidateStatsCache(['stats_kecepatan_angin_']);
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
            $logs = $this->model->getRecentLogs($limit);
            
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
            // Check if file was uploaded
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi limit server)',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi limit form)',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                    UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
                ];
                $errorCode = $_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                throw new Exception($errorMessages[$errorCode] ?? 'Upload file gagal');
            }
            
            $file = $_FILES['excel_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Format file tidak didukung. Gunakan xlsx, xls, atau csv');
            }
            
            // Move uploaded file to temp location
            $uploadDir = ROOT_PATH . '/storage/uploads/temp/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $tempFile = $uploadDir . uniqid('import_') . '.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Gagal memindahkan file upload');
            }
            
            // Process import
            require_once ROOT_PATH . '/app/services/ExcelImportService.php';
            $importService = new ExcelImportService();
            $result = $importService->import($tempFile, 'kecepatan_angin');
            
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            if ($result['success']) {
                $this->model->logActivity('import_excel', 'success', 'Import data Excel berhasil', [
                    'processed' => $result['totalProcessed'],
                    'success' => $result['successCount'],
                    'failed' => $result['failedCount']
                ]);
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
     * Download Excel import template
     */
    public function downloadTemplate() {
        $this->checkAuth();
        
        require_once ROOT_PATH . '/app/services/ExcelImportService.php';
        $importService = new ExcelImportService();
        $csv = $importService->generateTemplate('kecepatan_angin');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_kecepatan_angin.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }
    
    // ========== DASHBOARD ANALYSIS API ENDPOINTS ==========
    
    /**
     * API: Get trend analysis data
     */
    public function getTrendData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $startYear = $_GET['start_year'] ?? (date('Y') - 4);
            $endYear = $_GET['end_year'] ?? date('Y');
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $data = $this->model->getTrendAnalysis($startYear, $endYear, $sourceFilters);
            
            $years = [];
            foreach ($data as $row) {
                $year = $row['tahun'];
                if (!isset($years[$year])) {
                    $years[$year] = array_fill(1, 12, 0);
                }
                $years[$year][$row['bulan']] = (float) $row['rata_rata'];
            }
            
            $datasets = [];
            $colors = ['#1cc88a', '#36b9cc', '#4e73df', '#f6c23e', '#e74a3b'];
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
                
                $colors[] = $row['klasifikasi'] === 'Angin Kencang' ? 'rgba(255, 99, 132, 0.7)' :
                           ($row['klasifikasi'] === 'Angin Sedang' ? 'rgba(255, 206, 86, 0.7)' :
                            'rgba(75, 192, 192, 0.7)');
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
     * API: Check for wind alerts
     */
    public function checkAlerts() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $threshold = $_GET['threshold'] ?? 30.0;
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
     * API: Get daily chart data
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
            } elseif ($dataSource === 'openmeteo') {
                $filters['sumber_data_like'] = '%Open-Meteo%';
            } elseif ($dataSource === 'simulation') {
                $filters['sumber_data_like'] = '%Simulasi%';
            }
            $data = $this->model->getDailyData($year, $month, $filters);
            
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $labels = range(1, $daysInMonth);
            $values = array_fill(0, $daysInMonth, 0);
            
            foreach ($data as $row) {
                $day = (int) $row['hari'];
                $values[$day - 1] = (float) $row['kecepatan_angin'];
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Kecepatan Angin (km/h)',
                    'data' => $values,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.7)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'borderWidth' => 1
                ]]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get map data
     */
    public function getMapData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            $sourceFilters = $this->getSourceFilter($_GET['data_source'] ?? 'nasa');
            
            $windData = $this->model->getWindByLocation($year, $month, $sourceFilters);
            
            // Load kecamatan coordinates lookup (nama_kecamatan without ", Jember" => [lat, lon, kode])
            $db = Database::getInstance()->getConnection();
            $kecStmt = $db->prepare("SELECT nama_kecamatan, latitude, longitude, kode FROM master_kecamatan WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
            $kecStmt->execute();
            $kecLookup = [];
            foreach ($kecStmt->fetchAll(PDO::FETCH_ASSOC) as $k) {
                $kecLookup[trim($k['nama_kecamatan'])] = [
                    'latitude'  => (float) $k['latitude'],
                    'longitude' => (float) $k['longitude'],
                    'kode'      => $k['kode']
                ];
            }
            // Fallback: key with full lokasi format "NamaKec, Jember"
            foreach ($kecLookup as $nama => $coord) {
                $kecLookup[$nama . ', Jember'] = $coord;
            }
            $defaultLatLng = ['latitude' => -8.1706, 'longitude' => 113.7003];
            
            $mapData = [];
            foreach ($windData as $row) {
                $namaLokasi = trim($row['lokasi']);
                $coord = $kecLookup[$namaLokasi] ?? $defaultLatLng;
                $kecNameOnly = explode(',', $namaLokasi)[0];
                if (!isset($kecLookup[$namaLokasi]) && isset($kecLookup[$kecNameOnly])) {
                    $coord = $kecLookup[$kecNameOnly];
                }
                
                $mapData[] = [
                    'lokasi' => $row['lokasi'],
                    'rata_rata' => (float) $row['rata_rata'],
                    'maksimum' => (float) ($row['maksimum'] ?? 0),
                    'jumlah_data' => (int) $row['jumlah_data'],
                    'latitude' => $coord['latitude'],
                    'longitude' => $coord['longitude'],
                    'kode_wilayah' => $coord['kode'] ?? null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'matched_coordinates' => count(array_filter($mapData, fn($m) => !($m['latitude'] == -8.1706 && $m['longitude'] == 113.7003))),
                'data' => $mapData
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // ========== NEW WIND ANALYTICS API ENDPOINTS ==========
    
    /**
     * API: Get spray recommendation based on current wind conditions
     */
    public function sprayRecommendation() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
            $analytics = new WindAnalyticsService();
            
            // Get latest wind data or use parameters
            $speed = $_GET['speed'] ?? null;
            $direction = $_GET['direction'] ?? null;
            
            if ($speed === null) {
                // Get latest data from database
                $filters = array_merge(
                    ['limit' => 1, 'order' => 'tanggal DESC'],
                    $this->getSourceFilter($_GET['data_source'] ?? 'nasa')
                );
                $latestData = $this->model->getAll($filters);
                if (!empty($latestData)) {
                    $speed = $latestData[0]['kecepatan_angin'];
                    $direction = $latestData[0]['arah_angin'];
                } else {
                    $speed = 0;
                }
            }
            
            $recommendation = $analytics->getSprayRecommendation(
                floatval($speed),
                $direction !== null ? floatval($direction) : null
            );
            
            echo json_encode([
                'success' => true,
                'recommendation' => $recommendation,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get wind rose data for visualization
     */
    public function windRoseData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
            $analytics = new WindAnalyticsService();
            
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            
            $filters = ['year' => $year];
            if ($month) {
                $filters['month'] = $month;
            }
            $filters = array_merge($filters, $this->getSourceFilter($_GET['data_source'] ?? 'nasa'));
            
            $data = $this->model->getAll($filters);
            $windRose = $analytics->getWindRoseData($data);
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'windRose' => $windRose
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get pest risk analysis based on wind conditions
     */
    public function pestRiskAnalysis() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
            $analytics = new WindAnalyticsService();
            
            $speed = $_GET['speed'] ?? null;
            $direction = $_GET['direction'] ?? null;
            
            if ($speed === null || $direction === null) {
                // Get latest data
                $filters = array_merge(
                    ['limit' => 1, 'order' => 'tanggal DESC'],
                    $this->getSourceFilter($_GET['data_source'] ?? 'nasa')
                );
                $latestData = $this->model->getAll($filters);
                if (!empty($latestData)) {
                    $speed = $speed ?? $latestData[0]['kecepatan_angin'];
                    $direction = $direction ?? $latestData[0]['arah_angin'];
                } else {
                    throw new Exception('Tidak ada data angin tersedia');
                }
            }
            
            $analysis = $analytics->analyzeWindImpact(
                floatval($direction),
                floatval($speed)
            );
            
            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get or generate daily summary with analytics
     */
    public function dailySummary() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
            $analytics = new WindAnalyticsService();
            
            $date = $_GET['date'] ?? date('Y-m-d');
            $lokasi = $_GET['lokasi'] ?? null;
            $regenerate = isset($_GET['regenerate']);
            
            if ($regenerate) {
                // Generate fresh summary
                $summaries = $analytics->generateDailySummary($date, $lokasi);
                echo json_encode([
                    'success' => true,
                    'generated' => true,
                    'date' => $date,
                    'summaries' => $summaries
                ]);
            } else {
                // Get from database
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT * FROM wind_daily_summary WHERE tanggal = ?";
                $params = [$date];
                
                if ($lokasi) {
                    $sql .= " AND lokasi LIKE ?";
                    $params[] = "%$lokasi%";
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'generated' => false,
                    'date' => $date,
                    'summaries' => $summaries
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get evapotranspiration analysis for irrigation
     */
    public function evapotranspirationAnalysis() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindAnalyticsService.php';
            $analytics = new WindAnalyticsService();
            
            $speed = $_GET['speed'] ?? null;
            $temperature = $_GET['temperature'] ?? null;
            $humidity = $_GET['humidity'] ?? null;
            
            if ($speed === null) {
                // Get latest wind data
                $filters = ['limit' => 1, 'order' => 'tanggal DESC'];
                $latestData = $this->model->getAll($filters);
                if (!empty($latestData)) {
                    $speed = $latestData[0]['kecepatan_angin'];
                } else {
                    $speed = 0;
                }
            }
            
            $etAnalysis = $analytics->calculateEvapotranspiration(
                floatval($speed),
                $temperature !== null ? floatval($temperature) : null,
                $humidity !== null ? floatval($humidity) : null
            );
            
            echo json_encode([
                'success' => true,
                'evapotranspiration' => $etAnalysis,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // ========== INTEGRATION API ENDPOINTS ==========
    
    /**
     * API: Get wind-pest correlation data
     */
    public function windPestCorrelation() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindIntegrationService.php';
            $integration = new WindIntegrationService();
            
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $lokasi = $_GET['lokasi'] ?? null;
            
            $result = $integration->getWindPestCorrelation($startDate, $endDate, $lokasi);
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get pest spread prediction based on wind
     */
    public function pestSpreadPrediction() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindIntegrationService.php';
            $integration = new WindIntegrationService();
            
            $speed = $_GET['speed'] ?? null;
            $direction = $_GET['direction'] ?? null;
            
            if ($speed === null || $direction === null) {
                // Get latest wind data
                $filters = ['limit' => 1, 'order' => 'tanggal DESC'];
                $latestData = $this->model->getAll($filters);
                if (!empty($latestData)) {
                    $speed = $speed ?? $latestData[0]['kecepatan_angin'];
                    $direction = $direction ?? $latestData[0]['arah_angin'];
                } else {
                    throw new Exception('Tidak ada data angin tersedia');
                }
            }
            
            $prediction = $integration->getPestSpreadPrediction(floatval($speed), floatval($direction));
            
            echo json_encode([
                'success' => true,
                'prediction' => $prediction,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * API: Get irrigation adjustment recommendation
     */
    public function irrigationAdjustment() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/WindIntegrationService.php';
            $integration = new WindIntegrationService();
            
            $windSpeed = isset($_GET['speed']) ? floatval($_GET['speed']) : null;
            $temperature = isset($_GET['temperature']) ? floatval($_GET['temperature']) : null;
            $humidity = isset($_GET['humidity']) ? floatval($_GET['humidity']) : null;
            
            $result = $integration->getIrrigationAdjustment($windSpeed, $temperature, $humidity);
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Export data to CSV
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
        
        $filename = 'kecepatan_angin_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, ['Tanggal', 'Lokasi', 'Kecepatan (km/h)', 'Maks (km/h)', 'Arah', 'Sumber Data', 'Keterangan']);
        
        // Data rows
        foreach ($data as $row) {
            $csvRow = $this->sanitizeCsvRow([
                $row['tanggal'],
                $row['lokasi'],
                $row['kecepatan_angin'],
                $row['kecepatan_max'] ?? '',
                $row['arah_angin_desc'] ?? '',
                $row['sumber_data'],
                $row['keterangan']
            ]);
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
}
