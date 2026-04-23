<?php
/**
 * GabahBerasController
 * Controller untuk fitur produksi gabah/beras dengan RBAC
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class GabahBerasController {
    
    private $model;
    private $analyticsService;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/ProduksiGabah.php';
        $this->model = new ProduksiGabah();
    }
    
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
    }

    private function requireStateChangingRequest($methods = ['POST']): void {
        $allowedMethods = array_map('strtoupper', (array)$methods);
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($requestMethod, $allowedMethods, true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            echo '405 - Method Not Allowed';
            exit;
        }

        $token = Security::getRequestCsrfToken();
        if (!Security::validateCsrfToken($token)) {
            http_response_code(403);
            $_SESSION['flash_message'] = 'Token keamanan tidak valid';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
    }
    
    private function getAnalyticsService() {
        if (!$this->analyticsService) {
            require_once ROOT_PATH . '/app/services/GabahBerasAnalytics.php';
            $this->analyticsService = new GabahBerasAnalytics();
        }
        return $this->analyticsService;
    }
    
    /**
     * Dashboard - Main view with KPI and charts
     */
    public function index() {
        $this->checkAuth();
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $musim = $_GET['musim'] ?? null;
        
        $filters = [
            'tahun' => $tahun,
            'musim_tanam' => $musim,
            'limit' => 10
        ];
        
        // Role-based filtering
        if ($_SESSION['role'] === 'petugas') {
            $filters['user_id'] = $_SESSION['user_id'];
        }
        
        $data = [
            'page_title' => 'Dashboard Gabah & Beras',
            'statistics' => $this->model->getStatistics($filters),
            'recent_data' => $this->model->getAll($filters),
            'production_trend' => $this->model->getProductionTrend(5),
            'productivity_map' => $this->model->getProductivityByLocation($tahun, $musim),
            'musim_list' => $this->model->getMusimList(),
            'grade_list' => $this->model->getGradeList(),
            'tahun' => $tahun,
            'musim' => $musim
        ];
        
        require_once ROOT_PATH . '/app/views/gabah_beras/index.php';
    }
    
    /**
     * Create - Form untuk input data baru (Wizard)
     */
    public function create() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->store();
        }
        
        $data = [
            'page_title' => 'Input Data Produksi Gabah',
            'musim_list' => $this->model->getMusimList(),
            'grade_list' => $this->model->getGradeList(),
            'years' => range(date('Y'), date('Y') - 5)
        ];
        
        // Get location data
        $db = Database::getInstance()->getConnection();
        
        try {
            $stmt = $db->query("SELECT id, nama FROM kabupaten ORDER BY nama");
            $data['kabupaten_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['kabupaten_list'] = [['id' => 1, 'nama' => 'Jember']];
        }
        
        // Get irigasi list
        try {
            $stmt = $db->query("SELECT id, nama_irigasi FROM irigasi ORDER BY nama_irigasi LIMIT 100");
            $data['irigasi_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['irigasi_list'] = [];
        }
        
        require_once ROOT_PATH . '/app/views/gabah_beras/create.php';
    }
    
    /**
     * Store - Save new record
     */
    private function store() {
        try {
            // Validate CSRF
            if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token keamanan tidak valid');
            }
            
            $inputData = [
                'musim_tanam' => $_POST['musim_tanam'],
                'tahun' => $_POST['tahun'],
                'kabupaten_id' => $_POST['kabupaten_id'],
                'kecamatan_id' => $_POST['kecamatan_id'],
                'desa_id' => $_POST['desa_id'] ?? null,
                'nama_lokasi' => $_POST['nama_lokasi'],
                'irigasi_id' => $_POST['irigasi_id'] ?: null,
                'varietas' => $_POST['varietas'] ?? null,
                'luas_tanam' => $_POST['luas_tanam'],
                'luas_panen' => $_POST['luas_panen'],
                'produksi_total' => $_POST['produksi_total'],
                'kadar_air' => $_POST['kadar_air'] ?: null,
                'grade_kualitas' => $_POST['grade_kualitas'],
                'harga_gabah' => $_POST['harga_gabah'] ?: null,
                'keterangan' => $_POST['keterangan'] ?? null,
                'user_id' => $_SESSION['user_id'],
                'status' => 'pending'
            ];
            
            // Handle file upload
            if (!empty($_FILES['foto']['name'])) {
                $uploadResult = $this->handleFileUpload($_FILES['foto']);
                if ($uploadResult['success']) {
                    $inputData['foto'] = $uploadResult['filename'];
                }
            }
            
            $id = $this->model->create($inputData);
            
            if ($id) {
                $_SESSION['flash_message'] = 'Data produksi gabah berhasil disimpan';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . BASE_URL . 'gabahBeras/detail/' . $id);
                exit;
            } else {
                throw new Exception('Gagal menyimpan data');
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras/create');
            exit;
        }
    }
    
    /**
     * Detail - View single record
     */
    public function detail($id) {
        $this->checkAuth();
        
        $record = $this->model->getById($id);
        if (!$record) {
            $_SESSION['flash_message'] = 'Data tidak ditemukan';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        
        // Check access for petugas
        if ($_SESSION['role'] === 'petugas' && $record['user_id'] != $_SESSION['user_id']) {
            $_SESSION['flash_message'] = 'Anda tidak memiliki akses ke data ini';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        
        $data = [
            'page_title' => 'Detail Produksi Gabah',
            'record' => $record,
            'musim_list' => $this->model->getMusimList(),
            'grade_list' => $this->model->getGradeList()
        ];
        
        require_once ROOT_PATH . '/app/views/gabah_beras/detail.php';
    }
    
    /**
     * Edit - Form edit record
     */
    public function edit($id) {
        $this->checkAuth();
        
        $record = $this->model->getById($id);
        if (!$record) {
            $_SESSION['flash_message'] = 'Data tidak ditemukan';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        
        // Check permission
        if ($_SESSION['role'] === 'petugas' && $record['user_id'] != $_SESSION['user_id']) {
            $_SESSION['flash_message'] = 'Anda tidak memiliki akses untuk mengedit data ini';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->update($id);
        }
        
        $db = Database::getInstance()->getConnection();
        
        $data = [
            'page_title' => 'Edit Data Produksi Gabah',
            'record' => $record,
            'musim_list' => $this->model->getMusimList(),
            'grade_list' => $this->model->getGradeList(),
            'years' => range(date('Y'), date('Y') - 5)
        ];
        
        try {
            $stmt = $db->query("SELECT id, nama FROM kabupaten ORDER BY nama");
            $data['kabupaten_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['kabupaten_list'] = [['id' => 1, 'nama' => 'Jember']];
        }
        
        require_once ROOT_PATH . '/app/views/gabah_beras/edit.php';
    }
    
    /**
     * Update - Save edited record
     */
    private function update($id) {
        try {
            if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token keamanan tidak valid');
            }
            
            $inputData = [
                'musim_tanam' => $_POST['musim_tanam'],
                'tahun' => $_POST['tahun'],
                'kabupaten_id' => $_POST['kabupaten_id'],
                'kecamatan_id' => $_POST['kecamatan_id'],
                'desa_id' => $_POST['desa_id'] ?? null,
                'nama_lokasi' => $_POST['nama_lokasi'],
                'irigasi_id' => $_POST['irigasi_id'] ?: null,
                'varietas' => $_POST['varietas'] ?? null,
                'luas_tanam' => $_POST['luas_tanam'],
                'luas_panen' => $_POST['luas_panen'],
                'produksi_total' => $_POST['produksi_total'],
                'kadar_air' => $_POST['kadar_air'] ?: null,
                'grade_kualitas' => $_POST['grade_kualitas'],
                'harga_gabah' => $_POST['harga_gabah'] ?: null,
                'keterangan' => $_POST['keterangan'] ?? null
            ];
            
            $success = $this->model->update($id, $inputData);
            
            if ($success) {
                $_SESSION['flash_message'] = 'Data produksi gabah berhasil diperbarui';
                $_SESSION['flash_type'] = 'success';
            } else {
                throw new Exception('Gagal memperbarui data');
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
        
        header('Location: ' . BASE_URL . 'gabahBeras/detail/' . $id);
        exit;
    }
    
    /**
     * Delete - Remove record (Admin only)
     */
    public function delete($id) {
        $this->checkAuth();
        
        if ($_SESSION['role'] !== 'admin') {
            $_SESSION['flash_message'] = 'Hanya admin yang dapat menghapus data';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        $this->requireStateChangingRequest(['POST', 'DELETE']);
        
        if ($this->model->delete($id)) {
            $_SESSION['flash_message'] = 'Data berhasil dihapus';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Gagal menghapus data';
            $_SESSION['flash_type'] = 'danger';
        }
        
        header('Location: ' . BASE_URL . 'gabahBeras');
        exit;
    }
    
    /**
     * Verify - Verify record (Operator/Admin)
     */
    public function verify($id) {
        $this->checkAuth();
        
        if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
            $_SESSION['flash_message'] = 'Anda tidak memiliki akses untuk verifikasi';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . 'gabahBeras');
            exit;
        }
        $this->requireStateChangingRequest(['POST', 'PATCH']);
        
        $status = $_POST['status'] ?? 'verified';
        
        if ($this->model->verify($id, $_SESSION['user_id'], $status)) {
            $_SESSION['flash_message'] = 'Data berhasil diverifikasi';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Gagal memverifikasi data';
            $_SESSION['flash_type'] = 'danger';
        }
        
        header('Location: ' . BASE_URL . 'gabahBeras/detail/' . $id);
        exit;
    }
    
    /**
     * Analytics - Dashboard analytics
     */
    public function analytics() {
        $this->checkAuth();
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $musim = $_GET['musim'] ?? null;
        
        $analytics = $this->getAnalyticsService();
        
        $data = [
            'page_title' => 'Analytics Gabah & Beras',
            'summary' => $analytics->getDashboardSummary($tahun, $musim),
            'irrigation_correlation' => $analytics->correlateWithIrrigation($tahun),
            'weather_correlation' => $analytics->correlateWithWeather($tahun),
            'pest_correlation' => $analytics->correlateWithPest($tahun),
            'by_irigasi' => $analytics->getAnalyticsByIrigasi($tahun),
            'tahun' => $tahun,
            'musim' => $musim
        ];
        
        require_once ROOT_PATH . '/app/views/gabah_beras/analytics.php';
    }
    
    // ==================== API ENDPOINTS ====================
    
    /**
     * API: Get dashboard summary
     */
    public function apiSummary() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $musim = $_GET['musim'] ?? null;
        
        $analytics = $this->getAnalyticsService();
        $summary = $analytics->getDashboardSummary($tahun, $musim);
        
        echo json_encode(['success' => true, 'data' => $summary]);
        exit;
    }
    
    /**
     * API: Get production data
     */
    public function apiGetData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $filters = [
            'tahun' => $_GET['tahun'] ?? date('Y'),
            'musim_tanam' => $_GET['musim'] ?? null,
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        if ($_SESSION['role'] === 'petugas') {
            $filters['user_id'] = $_SESSION['user_id'];
        }
        
        $data = $this->model->getAll($filters);
        $total = $this->model->countAll($filters);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'filters' => $filters
        ]);
        exit;
    }
    
    /**
     * API: Validate productivity
     */
    public function apiValidate() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $data = [
            'luas_panen' => $_GET['luas_panen'] ?? 0,
            'produksi_total' => $_GET['produksi_total'] ?? 0,
            'kadar_air' => $_GET['kadar_air'] ?? null
        ];
        
        $result = $this->model->validateProductivity($data);
        
        echo json_encode($result);
        exit;
    }
    
    /**
     * API: Get risk analysis
     */
    public function apiRisk() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $kecamatanId = $_GET['kecamatan_id'] ?? null;
        $tahun = $_GET['tahun'] ?? date('Y');
        
        if (!$kecamatanId) {
            echo json_encode(['success' => false, 'error' => 'kecamatan_id required']);
            exit;
        }
        
        $analytics = $this->getAnalyticsService();
        $risk = $analytics->generateRiskScore($kecamatanId, $tahun);
        
        echo json_encode(['success' => true, 'data' => $risk]);
        exit;
    }
    
    /**
     * API: Get map data
     */
    public function apiMapData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $tahun = $_GET['tahun'] ?? date('Y');
        $musim = $_GET['musim'] ?? null;
        
        $data = $this->model->getProductivityByLocation($tahun, $musim);
        
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    // ==================== HELPER METHODS ====================
    
    private function handleFileUpload($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Ukuran file terlalu besar (max 5MB)'];
        }
        
        $uploadDir = ROOT_PATH . '/public/uploads/gabah_beras/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'gabah_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true, 'filename' => $filename];
        }
        
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}
