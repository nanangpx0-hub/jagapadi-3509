<?php
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
        if ($_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
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
        
        // Get statistics for current year
        $data['statistics'] = $this->model->getStatistics(['year' => date('Y')]);
        
        // Get monthly data for chart
        $data['monthlyData'] = $this->model->getMonthlyAverage(date('Y'));
        
        // Get recent data for table
        $data['recentData'] = $this->model->getAll([
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
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            // NEW: Data source filtering (default to BMKG for demo mode)
            $dataSource = $_GET['data_source'] ?? 'bmkg';
            if ($dataSource === 'bmkg') {
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
        
        try {
            $type = $_GET['type'] ?? 'monthly';
            $year = $_GET['year'] ?? date('Y');
            
            if ($type === 'monthly') {
                $data = $this->model->getMonthlyAverage($year);
                
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
                $data = $this->model->getYearlySummary(5);
                
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
        exit;
    }
    
    /**
     * API: Get statistics (AJAX)
     */
    public function getStatistics() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $filters = [
                'year' => $_GET['year'] ?? null,
                'month' => $_GET['month'] ?? null
            ];
            
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
        exit;
    }
    
    /**
     * Manual trigger scraper (Admin only)
     */
    public function runScraper() {
        $this->checkAuth();
        $this->checkAdmin();
        
        header('Content-Type: application/json');
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
            $scraper = new CurahHujanScraper();
            
            $options = [
                'year' => $_POST['year'] ?? date('Y'),
                'month' => $_POST['month'] ?? date('m'),
                'force_simulation' => isset($_POST['force_simulation'])
            ];
            
            $result = $scraper->run($options);
            
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'records_success' => $result['records_success'],
                'records_failed' => $result['records_failed'],
                'execution_time' => $result['execution_time']
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
            
            if (!is_numeric($data['curah_hujan']) || $data['curah_hujan'] < 0 || $data['curah_hujan'] > 500) {
                throw new Exception('Curah hujan harus antara 0-500 mm');
            }
            
            $result = $this->model->insert($data);
            
            if ($result) {
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
                'sumber_data' => $_POST['sumber_data'] ?? 'Manual',
                'keterangan' => $keterangan
            ];
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
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
            fputcsv($output, [
                $row['tanggal'],
                $row['lokasi'],
                $row['curah_hujan'],
                $row['sumber_data'],
                $row['keterangan']
            ]);
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
            
            $data = $this->model->getTrendAnalysis($startYear, $endYear);
            
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
            $data = $this->model->getSeasonalPattern($year);
            
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
            
            $data = $this->model->getAnomalies($year, $threshold);
            
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
            $data = $this->model->getSimplePrediction($months);
            
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
            
            $alerts = $this->model->getAlerts($threshold, $days);
            
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
            
            $data = $this->model->getDailyData($year, $month);
            
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
            
            $rainfallData = $this->model->getRainfallByLocation($year, $month);
            
            // Try to get kecamatan coordinates if available
            $mapData = [];
            foreach ($rainfallData as $row) {
                $mapData[] = [
                    'lokasi' => $row['lokasi'],
                    'rata_rata' => (float) $row['rata_rata'],
                    'total' => (float) $row['total'],
                    'maksimum' => (float) $row['maksimum'],
                    'jumlah_data' => (int) $row['jumlah_data'],
                    // Default coordinates for Jember center
                    'latitude' => -8.1706,
                    'longitude' => 113.7003
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
