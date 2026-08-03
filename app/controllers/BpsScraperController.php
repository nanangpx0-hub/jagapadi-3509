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
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
        $this->model = new DataPertanianBps();
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
     * Main scraping interface
     */
    public function index() {
        $this->checkAuth();
        
        $data = [
            'title' => 'Data BPS Pertanian - JAGAPADI',
            'page_title' => 'Data Pertanian BPS Provinsi Jawa Timur',
            'availableYears' => $this->model->getAvailableYears(),
            'kabupatenList' => $this->model->getKabupatenList(),
            'currentYear' => date('Y')
        ];
        
        // Get statistics for current year if data exists
        $data['statistics'] = $this->model->getStatistics(date('Y'));
        
        // Get recent data
        $data['recentData'] = $this->model->getAll([
            'tahun' => date('Y'),
            'limit' => 10
        ]);
        
        // Get logs for admin
        if ($_SESSION['role'] === 'admin') {
            $data['recentLogs'] = $this->model->getRecentLogs(5);
        }
        
        $this->view('bps_scraper/index', $data);
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
            $filters = [
                'tahun' => $_GET['tahun'] ?? null,
                'kabupaten_kota' => $_GET['kabupaten'] ?? null,
                'sumber_data_type' => $_GET['source'] ?? null,
                'tipe_skenario' => $_GET['skenario'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);
            $statistics = $this->model->getStatistics($filters['tahun'] ?? date('Y'));
            
            $formattedData = array_map([$this, 'formatRecordForResponse'], $data);
            
            echo json_encode([
                'success' => true,
                'data' => $formattedData,
                'total' => $total,
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
     * API: Get statistics (AJAX)
     */
    public function getStatistics() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $tahun = $_GET['tahun'] ?? date('Y');
            $statistics = $this->model->getStatistics($tahun);
            
            echo json_encode([
                'success' => true,
                'tahun' => $tahun,
                'statistics' => [
                    'jumlah_kabupaten' => (int)($statistics['jumlah_kabupaten'] ?? 0),
                    'total_luas_panen' => (float)($statistics['total_luas_panen'] ?? 0),
                    'total_luas_panen_formatted' => DataPertanianBps::formatNumber($statistics['total_luas_panen'] ?? 0),
                    'total_produksi_gabah' => (float)($statistics['total_produksi_gabah'] ?? 0),
                    'total_produksi_gabah_formatted' => DataPertanianBps::formatNumber($statistics['total_produksi_gabah'] ?? 0),
                    'total_produksi_beras' => (float)($statistics['total_produksi_beras'] ?? 0),
                    'total_produksi_beras_formatted' => DataPertanianBps::formatNumber($statistics['total_produksi_beras'] ?? 0),
                    'rata_produktivitas' => (float)($statistics['rata_produktivitas'] ?? 0)
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
     * API: Get chart data
     */
    public function getChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $type = $_GET['type'] ?? 'yearly';
            
            if ($type === 'yearly') {
                $data = $this->model->getYearlyTrend();
                
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
                
                echo json_encode([
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
                ]);
                
            } elseif ($type === 'top') {
                $tahun = $_GET['tahun'] ?? date('Y');
                $data = $this->model->getTopProducers($tahun, 10);
                
                $labels = [];
                $gabahData = [];
                
                foreach ($data as $row) {
                    $labels[] = $row['kabupaten_kota'];
                    $gabahData[] = round($row['produksi_gabah'] / 1000, 1); // In thousand tons
                }
                
                echo json_encode([
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
            if ($tahun < 2000 || $tahun > 2100) {
                throw new Exception('Tahun tidak valid (2000-2100)');
            }

            $source = $_POST['source'] ?? 'simulasi';
            if (!in_array($source, ['simulasi', 'resmi_webapi', 'auto'])) {
                $source = 'simulasi';
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

            $options = [
                'tahun' => $tahun,
                'kabupaten' => $kabupaten,
                'source' => $source,
                'skenario' => $skenario,
                'force_refresh' => isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true',
                'fallback' => $source === 'auto'
            ];
            
            $result = $scraper->run($options);
            
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'],
                'source' => $result['source'],
                'records_success' => $result['records_success'],
                'records_failed' => $result['records_failed'],
                'records_skipped' => $result['records_skipped'],
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
            'kabupaten_kota' => $_GET['kabupaten'] ?? null
        ];
        
        $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
        
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
            fputcsv($output, [
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
            ]);
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

            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Format file tidak didukung. Gunakan xlsx, xls, atau csv');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
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
            $result = $importService->import($tempFile, 'data_pertanian_bps');
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
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

            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Format file tidak didukung');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
                'application/csv'
            ];
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Tipe file tidak didukung');
            }

            // Size limit check (10MB)
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new Exception('File terlalu besar (maksimal 10MB)');
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
            
            $result = $this->model->insert($data);
            
            if ($result) {
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
     * Get single record for edit
     */
    public function getRecord($id = null) {
        $this->checkAuth();
        
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
            
            $data = [
                'tahun' => $_POST['tahun'] ?? null,
                'kabupaten_kota' => $_POST['kabupaten_kota'] ?? null,
                'kode_wilayah' => $_POST['kode_wilayah'] ?? null,
                'luas_panen' => floatval($_POST['luas_panen'] ?? 0),
                'produksi_gabah' => floatval($_POST['produksi_gabah'] ?? 0),
                'produksi_beras' => floatval($_POST['produksi_beras'] ?? 0),
                'produktivitas' => floatval($_POST['produktivitas'] ?? 0),
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            // Auto-calculate if not provided
            if ($data['produksi_beras'] <= 0 && $data['produksi_gabah'] > 0) {
                $data['produksi_beras'] = round($data['produksi_gabah'] * 0.577, 2);
            }
            if ($data['produktivitas'] <= 0 && $data['luas_panen'] > 0) {
                $data['produktivitas'] = round(($data['produksi_gabah'] / $data['luas_panen']) * 10, 2);
            }
            
            $result = $this->model->update($id, $data);
            
            if ($result) {
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
            
            $result = $this->model->delete($id);
            
            if ($result) {
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
}
