<?php
/**
 * Evaluasi Controller
 * Controller untuk modul evaluasi akurasi estimasi vs rilis BPS
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class EvaluasiController extends Controller {
    
    private $model;
    
    public function __construct() {
        // Load model
        require_once ROOT_PATH . '/app/models/EvaluasiAkurasi.php';
        $this->model = new EvaluasiAkurasi();
    }
    
    /**
     * Check authentication
     */
    protected function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('auth/login');
        }
    }
    
    /**
     * Check admin access
     */
    protected function checkAdmin() {
        $this->checkAuth();
        if (!in_array($_SESSION['role'] ?? '', ['admin'])) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            $this->redirect('dashboard');
        }
    }
    
    /**
     * Index - Dashboard evaluasi akurasi
     */
    public function index() {
        $this->checkAdmin();
        
        // Get filter parameters
        $tahun = $_GET['tahun'] ?? date('Y');
        $bulan = $_GET['bulan'] ?? null;
        
        // Get data
        $filters = ['tahun' => $tahun];
        if ($bulan) {
            $filters['bulan'] = $bulan;
        }
        
        $data = $this->model->getAll($filters);
        $statistics = $this->model->getStatistics($tahun);
        $chartData = $this->model->getChartData($tahun);
        $availableYears = $this->model->getAvailableYears();
        
        // Can snapshot for current month?
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        $canSnapshot = $this->model->canSnapshot($currentMonth, $currentYear);
        $currentDay = (int) date('j');
        
        // Pass to view
        $this->view('evaluasi/index', [
            'title' => 'Evaluasi Akurasi Data',
            'data' => $data,
            'statistics' => $statistics,
            'chartData' => $chartData,
            'availableYears' => $availableYears,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'canSnapshot' => $canSnapshot,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
            'currentDay' => $currentDay,
            'csrfToken' => $_SESSION['csrf_token'] ?? ''
        ]);
    }
    
    /**
     * Generate Snapshot - API endpoint
     * Dijalankan untuk menyimpan data estimasi (biasanya tgl 6)
     */
    public function generateSnapshot() {
        $this->checkAdmin();
        
        // Validate CSRF for POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        // Get parameters
        $bulan = $_POST['bulan'] ?? date('n');
        $tahun = $_POST['tahun'] ?? date('Y');
        
        // Validate month
        if ($bulan < 1 || $bulan > 12) {
            $this->json(['success' => false, 'message' => 'Bulan tidak valid'], 400);
        }

        if (!is_numeric($tahun) || (int) $tahun < 2000 || (int) $tahun > (int) date('Y')) {
            $this->json(['success' => false, 'message' => 'Tahun tidak valid'], 400);
        }
        
        // Execute snapshot
        $result = $this->model->snapshotEstimasi($bulan, $tahun);
        
        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
    }
    
    /**
     * Store Rilis - API endpoint
     * Menyimpan input angka resmi dari BPS Pusat
     */
    public function storeRilis() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        // Get parameters
        $id = $_POST['id'] ?? null;
        $nilaiRilis = $_POST['nilai_rilis'] ?? null;
        $catatan = $_POST['catatan'] ?? '';
        
        // Validate
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID tidak valid'], 400);
        }
        
        if ($nilaiRilis === null || $nilaiRilis === '' || !is_numeric($nilaiRilis)) {
            $this->json(['success' => false, 'message' => 'Nilai rilis harus berupa angka'], 400);
        }

        if ((float) $nilaiRilis < 0) {
            $this->json(['success' => false, 'message' => 'Nilai rilis tidak boleh negatif'], 400);
        }
        
        // Update
        $result = $this->model->updateRilisResmi($id, (float) $nilaiRilis, $catatan);
        
        if ($result['success']) {
            // Get updated record
            $record = $this->model->getById($id);
            $this->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $record
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
    }
    
    /**
     * Get Data - API endpoint for AJAX
     */
    public function getData() {
        $this->checkAdmin();
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $bulan = $_GET['bulan'] ?? null;
        
        $filters = ['tahun' => $tahun];
        if ($bulan) {
            $filters['bulan'] = $bulan;
        }
        
        $data = $this->model->getAll($filters);
        $this->json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Get Chart Data - API endpoint
     */
    public function getChartData() {
        $this->checkAdmin();
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $chartData = $this->model->getChartData($tahun);
        
        $this->json([
            'success' => true,
            'data' => $chartData
        ]);
    }
    
    /**
     * Get Statistics - API endpoint
     */
    public function getStatistics() {
        $this->checkAdmin();
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $statistics = $this->model->getStatistics($tahun);
        
        $this->json([
            'success' => true,
            'data' => $statistics
        ]);
    }
    
    /**
     * Get single record by ID - API
     */
    public function getRecord($id = null) {
        $this->checkAdmin();
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID diperlukan'], 400);
        }
        
        $record = $this->model->getById($id);
        
        if (!$record) {
            $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        
        $this->json([
            'success' => true,
            'data' => $record
        ]);
    }
    
    /**
     * Get logs - API endpoint
     */
    public function getLogs() {
        $this->checkAdmin();
        
        $limit = $_GET['limit'] ?? 10;
        $logs = $this->model->getRecentLogs((int) $limit);
        
        $this->json([
            'success' => true,
            'data' => $logs
        ]);
    }
    
    /**
     * Store new record - API endpoint
     */
    public function store() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        $data = [
            'periode_bulan' => $_POST['periode_bulan'] ?? null,
            'periode_tahun' => $_POST['periode_tahun'] ?? null,
            'nama_wilayah' => $_POST['nama_wilayah'] ?? null,
            'wilayah_id' => $_POST['wilayah_id'] ?? null,
            'luas_estimasi_daerah' => floatval($_POST['luas_estimasi_daerah'] ?? 0),
            'luas_rilis_bps' => isset($_POST['luas_rilis_bps']) && $_POST['luas_rilis_bps'] !== ''
                ? floatval($_POST['luas_rilis_bps'])
                : null,
            'catatan_analisis' => $_POST['catatan_analisis'] ?? null
        ];
        
        // Validation
        if (empty($data['periode_bulan']) || $data['periode_bulan'] < 1 || $data['periode_bulan'] > 12) {
            $this->json(['success' => false, 'message' => 'Bulan tidak valid (1-12)'], 400);
        }
        
        if (empty($data['periode_tahun'])) {
            $this->json(['success' => false, 'message' => 'Tahun harus diisi'], 400);
        }

        if ((int) $data['periode_tahun'] < 2000 || (int) $data['periode_tahun'] > (int) date('Y')) {
            $this->json(['success' => false, 'message' => 'Tahun tidak valid'], 400);
        }
        
        if (empty($data['nama_wilayah'])) {
            $this->json(['success' => false, 'message' => 'Nama wilayah harus diisi'], 400);
        }

        if ($data['luas_estimasi_daerah'] < 0 || ($data['luas_rilis_bps'] !== null && $data['luas_rilis_bps'] < 0)) {
            $this->json(['success' => false, 'message' => 'Nilai luas tidak boleh negatif'], 400);
        }
        
        $result = $this->model->insert($data);
        $this->json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Update record - API endpoint
     */
    public function update($id = null) {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID tidak ditemukan'], 400);
        }
        
        $data = [
            'periode_bulan' => $_POST['periode_bulan'] ?? null,
            'periode_tahun' => $_POST['periode_tahun'] ?? null,
            'nama_wilayah' => $_POST['nama_wilayah'] ?? null,
            'luas_estimasi_daerah' => floatval($_POST['luas_estimasi_daerah'] ?? 0),
            'luas_rilis_bps' => isset($_POST['luas_rilis_bps']) && $_POST['luas_rilis_bps'] !== ''
                ? floatval($_POST['luas_rilis_bps'])
                : null,
            'catatan_analisis' => $_POST['catatan_analisis'] ?? null
        ];
        
        if ($data['luas_estimasi_daerah'] < 0 || ($data['luas_rilis_bps'] !== null && $data['luas_rilis_bps'] < 0)) {
            $this->json(['success' => false, 'message' => 'Nilai luas tidak boleh negatif'], 400);
        }

        $result = $this->model->update($id, $data);
        $this->json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Delete record - API endpoint
     */
    public function delete($id = null) {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID tidak ditemukan'], 400);
        }
        
        // Use deleteWithSnapshot to backup before delete
        $result = $this->model->deleteWithSnapshot($id);
        $this->json($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Import Excel - API endpoint
     */
    public function importExcel() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }
        
        $this->validateCsrfToken();
        
        try {
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Tidak ada file yang diupload');
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
            
            $tempFile = $uploadDir . uniqid('import_evaluasi_') . '.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Gagal memindahkan file upload');
            }
            
            require_once ROOT_PATH . '/app/services/ExcelImportService.php';
            $importService = new ExcelImportService();
            $result = $importService->import($tempFile, 'evaluasi_akurasi');
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            $this->json($result);
            
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Preview Import - API endpoint
     */
    public function previewImport() {
        $this->checkAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
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
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            $this->json($preview);
            
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Download template - endpoint
     */
    public function downloadTemplate() {
        $this->checkAdmin();
        
        require_once ROOT_PATH . '/app/services/ExcelImportService.php';
        $importService = new ExcelImportService();
        $csv = $importService->generateTemplate('evaluasi_akurasi');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_evaluasi_akurasi.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }
}
