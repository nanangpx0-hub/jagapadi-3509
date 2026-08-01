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
        $this->model = new HargaKomoditas();
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
        $data['statistics'] = $this->model->getOverallStats();
        
        // Get latest prices
        $data['latestPrices'] = $this->model->getLatestPrices();
        
        // Get unread alerts count
        $data['unreadAlerts'] = $this->model->countUnreadAlerts();
        
        // Get recent data
        $data['recentData'] = $this->model->getAll([
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
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'jenis_komoditas' => $_GET['jenis_komoditas'] ?? null,
                'lokasi' => $_GET['lokasi'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
            
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
            $type = $_GET['type'] ?? 'trend';
            $days = $_GET['days'] ?? 30;
            
            if ($type === 'trend') {
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime("-{$days} days"));
                
                $data = $this->model->getTrendAnalysis($startDate, $endDate);
                
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
                        'backgroundColor' => str_replace('rgb', 'rgba', $colors[$kom] ?? 'rgb(153, 102, 255)') . ', 0.5)',
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
                $data = $this->model->getPriceComparison(6);
                
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
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];
            
            $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
            
            $statistics = $this->model->getStatistics($filters);
            $overallStats = $this->model->getOverallStats($filters);
            
            echo json_encode([
                'success' => true,
                'statistics' => $statistics,
                'overall' => $overallStats
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
        header('Content-Type: application/json');
        
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
        } catch (Exception $e) {
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
            
            $priceData = $this->model->getPriceByLocation($komoditas);
            
            // Add coordinates (randomized sekitar pusat Jember; master_kecamatan tidak menyimpan koordinat)
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
                    'latitude' => -8.1706 + (rand(-50, 50) / 1000), // Slight variation
                    'longitude' => 113.7003 + (rand(-50, 50) / 1000)
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $mapData
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
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';
            $scraper = new HargaKomoditasScraper();
            
            $options = [
                'year' => $_POST['year'] ?? date('Y'),
                'month' => $_POST['month'] ?? date('m'),
                'force_simulation' => true
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
            
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Format file tidak didukung. Gunakan xlsx, xls, atau csv');
            }
            
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
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            if ($result['success']) {
                $this->model->logActivity('import_excel', 'success', 'Import data harga komoditas dari Excel', [
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
        
        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Tidak ada file yang diupload');
            }
            
            $file = $_FILES['excel_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Format file tidak didukung');
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
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            if (empty($data['tanggal'])) {
                throw new Exception('Tanggal harus diisi');
            }
            
            if (empty($data['jenis_komoditas'])) {
                throw new Exception('Jenis komoditas harus dipilih');
            }
            
            if (!is_numeric($data['harga']) || $data['harga'] <= 0) {
                throw new Exception('Harga harus lebih dari 0');
            }
            
            $result = $this->model->insert($data);
            
            if ($result) {
                $this->model->logActivity('manual_entry', 'success', 'Data harga ditambahkan', [
                    'komoditas' => $data['jenis_komoditas'],
                    'harga' => $data['harga']
                ]);
                
                $_SESSION['success'] = 'Data harga berhasil ditambahkan';
            } else {
                throw new Exception('Gagal menyimpan data');
            }
        } catch (Exception $e) {
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
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID tidak valid']);
            exit;
        }
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Token keamanan tidak valid']);
            exit;
        }
        
        try {
            $data = [
                'tanggal' => $_POST['tanggal'] ?? null,
                'jenis_komoditas' => $_POST['jenis_komoditas'] ?? null,
                'harga' => $_POST['harga'] ?? 0,
                'lokasi' => $_POST['lokasi'] ?? 'Jember',
                'kode_wilayah' => $_POST['kode_wilayah'] ?? '35.09',
                'sumber_data' => $_POST['sumber_data'] ?? 'Manual',
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            if (empty($data['tanggal']) || empty($data['jenis_komoditas']) || $data['harga'] <= 0) {
                throw new Exception('Data tidak lengkap atau tidak valid');
            }
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
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
     * Delete data
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
     * Delete multiple records
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
     * Export data to CSV
     */
    public function export() {
        $this->checkAuth();
        
        $filters = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'jenis_komoditas' => $_GET['jenis_komoditas'] ?? null
        ];
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
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
        fputcsv($output, ['Tanggal', 'Komoditas', 'Harga (Rp/kg)', 'Lokasi', 'Sumber Data', 'Keterangan']);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['tanggal'],
                HargaKomoditas::getKomoditasLabel($row['jenis_komoditas']),
                $row['harga'],
                $row['lokasi'],
                $row['sumber_data'],
                $row['keterangan']
            ]);
        }
        
        fclose($output);
        exit;
    }
}
