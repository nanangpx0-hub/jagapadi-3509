<?php
/**
 * Harga Komoditas Controller
 * Controller untuk dashboard dan API harga gabah dan beras
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class HargaKomoditasController extends Controller {
    
    private $model;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/HargaKomoditas.php';
        require_once ROOT_PATH . '/app/core/CacheManager.php';
        $this->model = new HargaKomoditas();
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
    
    /**
     * Dashboard utama harga komoditas
     */
    public function index() {
        $this->checkAuth();
        
        $data = [
            'title' => 'Harga Gabah & Beras - JAGAPADI',
            'page_title' => 'Data Harga Gabah & Beras Kabupaten Jember',
            'availableYears' => $this->model->getAvailableYears(),
            'currentYear' => date('Y'),
            'currentMonth' => date('m'),
            'komoditasTypes' => HargaKomoditas::getKomoditasTypes()
        ];
        
        // Get overall statistics
        $defaultFilters = ['metode_data' => 'non_simulasi'];
        $data['statistics'] = $this->model->getOverallStats($defaultFilters);
        
        // Get latest prices
        $data['latestPrices'] = $this->model->getLatestPrices($defaultFilters);
        
        // Get unread alerts count
        $data['unreadAlerts'] = $this->model->countUnreadAlerts();
        
        // Get recent data
        $data['recentData'] = $this->model->getAll([
            'metode_data' => 'non_simulasi',
            'limit' => 10,
            'offset' => 0
        ]);
        
        // Get logs for admin
        if ($_SESSION['role'] === 'admin') {
            $data['recentLogs'] = $this->model->getRecentLogs(5);
        }
        
        $this->view('harga_komoditas/index', $data);
    }
    
    /**
     * Format record for API response
     */
    private function formatRecordForResponse($record) {
        return [
            'id' => $record['id'],
            'tanggal' => $record['tanggal'],
            'jenis_komoditas' => $record['jenis_komoditas'],
            'komoditas_label' => HargaKomoditas::getKomoditasLabel($record['jenis_komoditas']),
            'harga' => (float)$record['harga'],
            'harga_formatted' => HargaKomoditas::formatHarga($record['harga']),
            'satuan' => $record['satuan'] ?? 'Rp/kg',
            'lokasi' => $record['lokasi'],
            'kode_wilayah' => $record['kode_wilayah'] ?? null,
            'sumber_data' => $record['sumber_data'],
            'metode_data' => $record['metode_data'] ?? 'manual',
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
            $filters = $this->getRequestFilters();
            $filters['limit'] = max(1, min(500, (int) ($_GET['limit'] ?? 50)));
            $filters['offset'] = max(0, (int) ($_GET['offset'] ?? 0));
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);
            $statistics = $this->model->getStatistics($filters);
            $overallStats = $this->model->getOverallStats($filters);
            
            $formattedData = array_map([$this, 'formatRecordForResponse'], $data);
            
            echo json_encode([
                'success' => true,
                'data' => $formattedData,
                'total' => $total,
                'statistics' => $statistics,
                'overall' => $overallStats
            ]);
        } catch (Throwable $e) {
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

        $cacheKey = 'stats_harga_komoditas_chart_' . md5($_SERVER['QUERY_STRING'] ?? '');
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
            $type = (string) ($_GET['type'] ?? 'trend');
            $days = max(1, min(366, (int) ($_GET['days'] ?? 30)));
            $filters = $this->getRequestFilters();
            
            if ($type === 'trend') {
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime("-{$days} days"));
                
                unset($filters['start_date'], $filters['end_date']);
                $data = $this->model->getTrendAnalysis($startDate, $endDate, $filters);
                
                // Organize by commodity
                $commodities = [];
                foreach ($data as $row) {
                    $kom = $row['jenis_komoditas'];
                    if (!isset($commodities[$kom])) {
                        $commodities[$kom] = [];
                    }
                    $commodities[$kom][$row['tanggal']] = $row['harga'];
                }
                
                // Build datasets
                $labels = [];
                $dates = array_unique(array_column($data, 'tanggal'));
                sort($dates);
                $labels = $dates;
                
                $colors = [
                    'gabah_kering_panen' => 'rgb(255, 159, 64)',
                    'gabah_kering_giling' => 'rgb(255, 205, 86)',
                    'beras_medium' => 'rgb(54, 162, 235)',
                    'beras_premium' => 'rgb(75, 192, 192)'
                ];
                
                $datasets = [];
                foreach ($commodities as $kom => $prices) {
                    $values = [];
                    foreach ($labels as $date) {
                        $values[] = $prices[$date] ?? null;
                    }
                    $datasets[] = [
                        'label' => HargaKomoditas::getKomoditasLabel($kom),
                        'data' => $values,
                        'borderColor' => $colors[$kom] ?? 'rgb(153, 102, 255)',
                        'backgroundColor' => rtrim(
                            str_replace('rgb(', 'rgba(', $colors[$kom] ?? 'rgb(153, 102, 255)'),
                            ')'
                        ) . ', 0.5)',
                        'tension' => 0.3,
                        'fill' => false
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $labels,
                    'datasets' => $datasets
                ]);
                
            } elseif ($type === 'comparison') {
                $data = $this->model->getPriceComparison(6, $filters);
                
                $periods = [];
                $gabahPrices = [];
                $berasPrices = [];
                
                foreach ($data as $row) {
                    if (!in_array($row['periode'], $periods)) {
                        $periods[] = $row['periode'];
                    }
                    if ($row['kategori'] === 'Gabah') {
                        $gabahPrices[$row['periode']] = $row['rata_rata'];
                    } else {
                        $berasPrices[$row['periode']] = $row['rata_rata'];
                    }
                }
                
                sort($periods);
                $gabahData = [];
                $berasData = [];
                foreach ($periods as $p) {
                    $gabahData[] = $gabahPrices[$p] ?? 0;
                    $berasData[] = $berasPrices[$p] ?? 0;
                }
                
                echo json_encode([
                    'success' => true,
                    'labels' => $periods,
                    'datasets' => [
                        [
                            'label' => 'Gabah (Rata-rata)',
                            'data' => $gabahData,
                            'backgroundColor' => 'rgba(255, 159, 64, 0.7)',
                            'borderColor' => 'rgb(255, 159, 64)',
                            'borderWidth' => 1
                        ],
                        [
                            'label' => 'Beras (Rata-rata)',
                            'data' => $berasData,
                            'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                            'borderColor' => 'rgb(54, 162, 235)',
                            'borderWidth' => 1
                        ]
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid chart type']);
            }
        } catch (Throwable $e) {
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

        $cacheKey = 'stats_harga_komoditas_stats_' . md5($_SERVER['QUERY_STRING'] ?? '');
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
            $filters = $this->getRequestFilters();
            
            $statistics = $this->model->getStatistics($filters);
            $overallStats = $this->model->getOverallStats($filters);
            
            echo json_encode([
                'success' => true,
                'statistics' => $statistics,
                'overall' => $overallStats
            ]);
        } catch (Throwable $e) {
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
     * API: Get price alerts
     */
    public function getAlerts() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $limit = $_GET['limit'] ?? 20;
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            
            $alerts = $this->model->getAlerts($limit, $unreadOnly);
            $unreadCount = $this->model->countUnreadAlerts();
            
            // Format alerts
            $formattedAlerts = array_map(function($alert) {
                return [
                    'id' => $alert['id'],
                    'komoditas' => HargaKomoditas::getKomoditasLabel($alert['jenis_komoditas']),
                    'tipe' => $alert['tipe_alert'],
                    'persentase' => $alert['persentase'],
                    'harga_sebelum' => HargaKomoditas::formatHarga($alert['harga_sebelum']),
                    'harga_sesudah' => HargaKomoditas::formatHarga($alert['harga_sesudah']),
                    'tanggal' => $alert['tanggal'],
                    'is_read' => (bool)$alert['is_read'],
                    'level' => $alert['persentase'] >= 10 ? 'critical' : 'warning',
                    'created_at' => $alert['created_at']
                ];
            }, $alerts);
            
            echo json_encode([
                'success' => true,
                'alerts' => $formattedAlerts,
                'unread_count' => $unreadCount
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
     * API: Mark alert as read
     */
    public function markAlertRead($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            if ($id) {
                $this->model->markAlertRead($id);
            } else {
                $this->model->markAllAlertsRead();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Alert ditandai sudah dibaca'
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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
            $komoditas = $_GET['komoditas'] ?? null;
            if ($komoditas !== null && !array_key_exists($komoditas, HargaKomoditas::getKomoditasTypes())) {
                throw new InvalidArgumentException('Jenis komoditas tidak valid');
            }
            
            $filters = $this->getRequestFilters();
            $priceData = $this->model->getPriceByLocation($komoditas, $filters);
            
            $mapData = [];
            foreach ($priceData as $row) {
                $mapData[] = [
                    'lokasi' => $row['lokasi'],
                    'komoditas' => HargaKomoditas::getKomoditasLabel($row['jenis_komoditas']),
                    'rata_rata' => (float)$row['rata_rata'],
                    'tertinggi' => (float)$row['tertinggi'],
                    'terendah' => (float)$row['terendah'],
                    'rata_rata_formatted' => HargaKomoditas::formatHarga($row['rata_rata']),
                    'jumlah_data' => (int)$row['jumlah_data'],
                    'latitude' => (float) $row['latitude'],
                    'longitude' => (float) $row['longitude']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $mapData
            ]);
        } catch (Throwable $e) {
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
        
        ob_start();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_end_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';
            $scraper = new HargaKomoditasScraper();
            
            $options = [
                'year' => $_POST['year'] ?? date('Y'),
                'month' => $_POST['month'] ?? date('m'),
                'source' => $_POST['source'] ?? $_POST['data_source'] ?? 'siskaperbapo',
                'force_simulation' => isset($_POST['force_simulation'])
            ];
            
            $result = $scraper->run($options);

            if ($result['records_inserted'] > 0 || $result['records_updated'] > 0) {
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
            }
            
            $jsonOutput = json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'no_data' => $result['no_data'],
                'records_success' => $result['records_success'],
                'records_inserted' => $result['records_inserted'],
                'records_updated' => $result['records_updated'],
                'records_skipped' => $result['records_skipped'],
                'records_failed' => $result['records_failed'],
                'errors' => $result['errors'],
                'execution_time' => $result['execution_time']
            ]);

            if (ob_get_length()) {
                ob_end_clean();
            }
            echo $jsonOutput;
        } catch (Throwable $e) {
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
        
        $tempFile = null;
        try {
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
            $extension = $this->validateImportUpload($file);
            
            $uploadDir = ROOT_PATH . '/storage/uploads/temp/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $tempFile = $uploadDir . uniqid('import_harga_') . '.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Gagal memindahkan file upload');
            }
            
            require_once ROOT_PATH . '/app/services/ExcelImportService.php';
            $importService = new ExcelImportService();
            $result = $importService->import($tempFile, 'harga_komoditas');
            
            if ($result['success']) {
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
                $this->model->rebuildAlerts();
                $this->model->logActivity('import_excel', 'success', 'Import data harga komoditas dari Excel', [
                    'processed' => $result['totalProcessed'],
                    'success' => $result['successCount'],
                    'failed' => $result['failedCount']
                ]);
            }
            
            echo json_encode($result);
            
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        } finally {
            if (is_string($tempFile) && is_file($tempFile)) {
                unlink($tempFile);
            }
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
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        $tempFile = null;
        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Tidak ada file yang diupload');
            }
            
            $file = $_FILES['excel_file'];
            $extension = $this->validateImportUpload($file);
            
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
            
            echo json_encode($preview);
            
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        } finally {
            if (is_string($tempFile) && is_file($tempFile)) {
                unlink($tempFile);
            }
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
        $csv = $importService->generateTemplate('harga_komoditas');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_harga_komoditas.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }
    
    /**
     * Store manual data entry
     */
    public function store() {
        $this->checkAuth();
        $this->checkAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed');
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Token keamanan tidak valid';
            header('Location: ' . BASE_URL . '/hargaKomoditas');
            exit;
        }
        
        try {
            $data = [
                'tanggal' => $_POST['tanggal'] ?? null,
                'jenis_komoditas' => $_POST['jenis_komoditas'] ?? null,
                'harga' => $_POST['harga'] ?? 0,
                'lokasi' => $_POST['lokasi'] ?? 'Jember',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'sumber_data' => 'Manual',
                'metode_data' => 'manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            $this->validatePriceInput($data);
            
            $result = $this->model->insert($data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
                $this->model->logActivity('manual_entry', 'success', 'Data harga ditambahkan', [
                    'komoditas' => $data['jenis_komoditas'],
                    'harga' => $data['harga']
                ]);
                
                $_SESSION['success'] = 'Data harga berhasil ditambahkan';
            } else {
                throw new Exception('Gagal menyimpan data');
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . BASE_URL . '/hargaKomoditas');
        exit;
    }
    
    /**
     * API: Get single record for editing
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
     * Update existing data
     */
    public function update($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $existing = $this->model->getById((int) $id);
            if (!$existing) {
                throw new RuntimeException('Data tidak ditemukan');
            }
            $data = [
                'tanggal' => $_POST['tanggal'] ?? null,
                'jenis_komoditas' => $_POST['jenis_komoditas'] ?? null,
                'harga' => $_POST['harga'] ?? 0,
                'lokasi' => $_POST['lokasi'] ?? 'Jember',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'satuan' => $existing['satuan'] ?? 'Rp/kg',
                'sumber_data' => $existing['sumber_data'],
                'metode_data' => $existing['metode_data'] ?? 'manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            $this->validatePriceInput($data);
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Data berhasil diperbarui'
                ]);
            } else {
                throw new Exception('Gagal memperbarui data');
            }
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Delete data
     */
    public function delete($id = null) {
        $this->checkAuth();
        $this->checkAdmin();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
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
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
            }
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Data berhasil dihapus' : 'Gagal menghapus data'
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Delete multiple records
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
            
            $deleted = $this->model->deleteMultiple($ids);
            if ($deleted > 0) {
                $this->invalidateStatsCache(['stats_harga_komoditas_']);
            }
            
            echo json_encode([
                'success' => true, 
                'deleted' => $deleted,
                'message' => "Berhasil menghapus {$deleted} data"
            ]);
            
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Export data to CSV
     */
    public function export() {
        $this->checkAuth();
        
        $filters = $this->getRequestFilters();
        
        $data = $this->model->getAll($filters);
        
        $filename = 'harga_komoditas_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, ['Tanggal', 'Komoditas', 'Harga (Rp/kg)', 'Lokasi', 'Sumber Data', 'Metode Data', 'Keterangan']);
        
        // Data rows
        foreach ($data as $row) {
            $csvRow = $this->sanitizeCsvRow([
                $row['tanggal'],
                HargaKomoditas::getKomoditasLabel($row['jenis_komoditas']),
                $row['harga'],
                $row['lokasi'],
                $row['sumber_data'],
                $row['metode_data'] ?? 'manual',
                $row['keterangan']
            ]);
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Semua endpoint tabel/statistik/ekspor memakai penyusun filter yang sama.
     * Default non-simulasi mencegah data sintetis mendominasi analisis tanpa
     * menghilangkan akses pengguna ke data simulasi melalui filter eksplisit.
     */
    private function getRequestFilters(): array {
        $filters = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'jenis_komoditas' => $_GET['jenis_komoditas'] ?? null,
            'lokasi' => $_GET['lokasi'] ?? null,
            'sumber_data' => $_GET['sumber_data'] ?? null,
            'metode_data' => $_GET['metode_data'] ?? 'non_simulasi'
        ];
        $filters = array_filter($filters, static fn($value) => $value !== null && $value !== '');

        foreach (['start_date', 'end_date'] as $field) {
            if (!isset($filters[$field])) {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $filters[$field]);
            if ($date === false || $date->format('Y-m-d') !== $filters[$field]) {
                throw new InvalidArgumentException('Format tanggal filter tidak valid');
            }
        }
        if (isset($filters['start_date'], $filters['end_date'])
            && $filters['start_date'] > $filters['end_date']) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh melewati tanggal akhir');
        }

        $commodity = (string) ($filters['jenis_komoditas'] ?? '');
        $allowedCommodities = array_merge(['', 'gabah', 'beras'], array_keys(HargaKomoditas::getKomoditasTypes()));
        if (!in_array($commodity, $allowedCommodities, true)) {
            throw new InvalidArgumentException('Jenis komoditas tidak valid');
        }
        $allowedMethods = ['non_simulasi', 'semua', 'aktual', 'estimasi', 'simulasi', 'manual'];
        if (!in_array($filters['metode_data'] ?? 'non_simulasi', $allowedMethods, true)) {
            throw new InvalidArgumentException('Metode data tidak valid');
        }
        if (isset($filters['lokasi']) && mb_strlen((string) $filters['lokasi']) > 100) {
            throw new InvalidArgumentException('Filter lokasi terlalu panjang');
        }
        if (isset($filters['sumber_data']) && mb_strlen((string) $filters['sumber_data']) > 100) {
            throw new InvalidArgumentException('Filter sumber terlalu panjang');
        }
        return $filters;
    }

    private function validatePriceInput(array &$data): void {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['tanggal'] ?? ''));
        if ($date === false || $date->format('Y-m-d') !== (string) ($data['tanggal'] ?? '')) {
            throw new InvalidArgumentException('Tanggal tidak valid');
        }
        if (!array_key_exists((string) ($data['jenis_komoditas'] ?? ''), HargaKomoditas::getKomoditasTypes())) {
            throw new InvalidArgumentException('Jenis komoditas tidak valid');
        }
        if (!is_numeric($data['harga'] ?? null)) {
            throw new InvalidArgumentException('Harga harus berupa angka');
        }
        $data['harga'] = (float) $data['harga'];
        if ($data['harga'] <= 0 || $data['harga'] > 100000) {
            throw new InvalidArgumentException('Harga harus antara Rp1 dan Rp100.000 per kg');
        }
        $data['lokasi'] = trim((string) ($data['lokasi'] ?? 'Jember'));
        if ($data['lokasi'] === '' || mb_strlen($data['lokasi']) > 100) {
            throw new InvalidArgumentException('Lokasi tidak valid');
        }
        $data['keterangan'] = trim((string) ($data['keterangan'] ?? '')) ?: null;
    }

    private function validateImportUpload(array $file): string {
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('Ukuran file harus antara 1 byte dan 10 MB');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Format file tidak didukung. Gunakan xlsx atau csv');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $allowedMimes = $extension === 'xlsx'
            ? ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream']
            : ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException('Isi file tidak sesuai dengan ekstensi');
        }
        return $extension;
    }

}
