<?php
/**
 * IrigasiController
 * * Menangani fitur pelaporan dan monitoring sebaran irigasi.
 * Mengimplementasikan RBAC (Role-Based Access Control).
 * * @package app/controllers
 */
class IrigasiController extends Controller {
    
    private $model;
    private $wilayahModel;

    public function __construct() {
        $this->model = $this->model('LaporanIrigasi');
        // Load model wilayah untuk form dropdown
        $this->wilayahModel = $this->model('MasterKabupaten'); 
    }

    /**
     * READ: Menampilkan daftar laporan
     * Rule: Petugas hanya melihat data sendiri, Admin/Operator melihat semua.
     */
    public function index() {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        
        $laporan = [];
        
        if ($user['role'] === 'petugas') {
            // Filter khusus petugas
            $laporan = $this->model->getAllWithDetails($user['id']);
        } else {
            // Admin & Operator lihat semua
            $laporan = $this->model->getAllWithDetails();
        }

        $this->view('irigasi/index', [
            'title' => 'Sebaran Irigasi',
            'laporan' => $laporan,
            'userRole' => $user['role']
        ]);
    }

    /**
     * CREATE: Form tambah laporan baru
     */
    public function create() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();

            try {
                error_log("Irigasi Create - POST data: " . json_encode($_POST));
                
                // Validasi input
                $errors = $this->validateInput($_POST);
                if (!empty($errors)) {
                    error_log("Irigasi Create - Validation errors: " . implode(', ', $errors));
                    ErrorMessage::set(implode(', ', $errors));
                    $this->view('irigasi/create', [
                        'title' => 'Input Data Irigasi',
                        'kabupaten' => $this->wilayahModel->getAllOrdered(),
                        'data' => $_POST,
                        'errors' => $errors
                    ]);
                    return;
                }

                // Generate Unique ID
                $noLaporan = $this->generateUniqueId();

                // Sanitasi input dasar
                $kondisiFisik = !empty($_POST['kondisi_fisik']) ? $_POST['kondisi_fisik'] : 'Bagus';
                
                // Map kondisi form values to DB enum values
                // Form: Baik → DB: Bagus, Rusak Ringan → DB: Tidak Bagus, Rusak Berat → DB: Rusak
                $kondisiFisikMap = [
                    'Baik' => 'Bagus',
                    'Rusak Ringan' => 'Tidak Bagus',
                    'Rusak Berat' => 'Rusak',
                ];
                $kondisiFisikDb = $kondisiFisikMap[$kondisiFisik] ?? $kondisiFisik;
                
                $data = [
                    'nomor_laporan' => $noLaporan,
                    'user_id' => $_SESSION['user_id'],
                    'kabupaten_id' => !empty($_POST['kabupaten_id']) ? (int)$_POST['kabupaten_id'] : null,
                    'kecamatan_id' => !empty($_POST['kecamatan_id']) ? (int)$_POST['kecamatan_id'] : null,
                    'desa_id' => !empty($_POST['desa_id']) ? (int)$_POST['desa_id'] : null,
                    'nama_saluran' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'daerah_irigasi' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'kondisi_fisik' => $kondisiFisikDb,
                    'debit_air' => !empty($_POST['debit_air']) ? $_POST['debit_air'] : 'Cukup',
                    'tanggal' => $_POST['tanggal'],
                    'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
                    'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
                    'catatan' => Security::sanitizeInput($_POST['catatan'] ?? ''),
                    'status' => $_POST['status'] ?? 'Submitted'
                ];

                // Handle File Upload with Automatic Compression
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';
                    
                    $uploadDir = UPLOAD_PATH . 'irigasi/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $file = $_FILES['foto'];
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    // Validate file type using finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, $allowedTypes)) {
                        ErrorMessage::set('Tipe file tidak diizinkan. Hanya JPG, PNG, dan GIF yang diizinkan.');
                        $this->view('irigasi/create', [
                            'title' => 'Input Data Irigasi',
                            'kabupaten' => $this->wilayahModel->getAllOrdered(),
                            'data' => $_POST,
                            'errors' => ['foto' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan GIF yang diizinkan.']
                        ]);
                        return;
                    }
                    
                    // Validate file extension
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($extension, $allowedExtensions)) {
                        ErrorMessage::set('Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan GIF yang diizinkan.');
                        $this->view('irigasi/create', [
                            'title' => 'Input Data Irigasi',
                            'kabupaten' => $this->wilayahModel->getAllOrdered(),
                            'data' => $_POST,
                            'errors' => ['foto' => 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan GIF yang diizinkan.']
                        ]);
                        return;
                    }
                    
                    // Generate secure filename
                    $fileName = hash('sha256', time() . $file['name'] . uniqid()) . '.' . $extension;
                    $targetPath = $uploadDir . $fileName;
                    
                    // Move uploaded file to temporary location
                    $tempPath = $file['tmp_name'];
                    
                    // Check if compression is needed
                    if ($file['size'] > $maxSize) {
                        // File is too large, compress it
                        $compressor = new ImageCompressor();
                        $result = $compressor->compress($tempPath, $targetPath, $maxSize);
                        
                        if ($result['success']) {
                            $data['foto_url'] = 'public/uploads/irigasi/' . $fileName;
                            
                            // Set info message about compression
                            if ($result['compressed']) {
                                $originalSize = ImageCompressor::formatFileSize($result['original_size']);
                                $finalSize = ImageCompressor::formatFileSize($result['final_size']);
                                $_SESSION['info'] = "Foto berhasil dikompresi dari {$originalSize} menjadi {$finalSize} (pengurangan {$result['reduction_percent']}%). Ukuran file sekarang sesuai batas maksimal.";
                            }
                        } else {
                            error_log("Irigasi Create - Photo compression failed: " . ($result['error'] ?? 'Unknown error'));
                            ErrorMessage::set('Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error'));
                            $this->view('irigasi/create', [
                                'title' => 'Input Data Irigasi',
                                'kabupaten' => $this->wilayahModel->getAllOrdered(),
                                'data' => $_POST,
                                'errors' => ['foto' => 'Gagal mengkompresi foto.']
                            ]);
                            return;
                        }
                    } else {
                        // File size is acceptable, just move it
                        if (move_uploaded_file($tempPath, $targetPath)) {
                            $data['foto_url'] = 'public/uploads/irigasi/' . $fileName;
                        } else {
                            error_log("Irigasi Create - Failed to move uploaded file: " . $file['name']);
                            ErrorMessage::set('Gagal mengupload file foto.');
                            $this->view('irigasi/create', [
                                'title' => 'Input Data Irigasi',
                                'kabupaten' => $this->wilayahModel->getAllOrdered(),
                                'data' => $_POST,
                                'errors' => ['foto' => 'Gagal mengupload file foto.']
                            ]);
                            return;
                        }
                    }
                } else {
                    error_log("Irigasi Create - No photo uploaded or upload error: " . ($_FILES['foto']['error'] ?? 'no file'));
                }

                // Attempt to create record
                $this->model->create($data);
                
                error_log("Irigasi Create - Success: laporan {$noLaporan} saved for user " . $_SESSION['user_id']);
                ErrorMessage::setSuccess('Data irigasi berhasil disimpan dengan nomor: ' . $noLaporan);
                $this->redirect('irigasi/index');

            } catch (Exception $e) {
                error_log("Irigasi Create Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                error_log("Irigasi Create - POST data that caused error: " . json_encode($_POST));
                ErrorMessage::set('Gagal menyimpan data irigasi: ' . $e->getMessage());
                $this->view('irigasi/create', [
                    'title' => 'Input Data Irigasi',
                    'kabupaten' => $this->wilayahModel->getAllOrdered(),
                    'data' => $_POST,
                    'errors' => ['server' => 'Gagal menyimpan data: ' . $e->getMessage()]
                ]);
                return;
            }
        }

        // Load data master untuk dropdown
        $kabupaten = $this->wilayahModel->getAllOrdered();

        $this->view('irigasi/create', [
            'title' => 'Input Data Irigasi',
            'kabupaten' => $kabupaten
        ]);
    }

    /**
     * Validate input data
     * @param array $data
     * @return array Array of error messages
     */
    private function validateInput(array $data): array {
        $errors = [];

        // Required fields - Nama Saluran
        if (empty($data['nama_saluran'])) {
            $errors[] = 'Nama saluran wajib diisi';
        } elseif (strlen(trim($data['nama_saluran'])) < 3) {
            $errors[] = 'Nama saluran minimal 3 karakter';
        } elseif (strlen($data['nama_saluran']) > 200) {
            $errors[] = 'Nama saluran maksimal 200 karakter';
        }

        // Required fields - Tanggal
        if (empty($data['tanggal'])) {
            $errors[] = 'Tanggal laporan wajib diisi';
        } elseif (strtotime($data['tanggal']) > strtotime('today')) {
            $errors[] = 'Tanggal tidak boleh melebihi hari ini';
        }

        // Required fields - Foto Irigasi
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            if (!isset($_FILES['foto'])) {
                $errors[] = 'Foto irigasi wajib diupload';
            } elseif ($_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Foto irigasi wajib diupload';
            } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Terjadi kesalahan saat mengupload foto';
            }
        }

        // Required fields - Kondisi (Radio)
        if (empty($data['kondisi_fisik'])) {
            $errors[] = 'Kondisi saluran wajib dipilih';
        } elseif (!in_array($data['kondisi_fisik'], ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
            $errors[] = 'Kondisi saluran tidak valid';
        }

        // Required fields - Debit Air
        if (empty($data['debit_air'])) {
            $errors[] = 'Debit air wajib dipilih';
        } elseif (!in_array($data['debit_air'], ['Cukup', 'Kurang', 'Kering'], true)) {
            $errors[] = 'Debit air tidak valid';
        }

        // Required fields - Kabupaten
        if (empty($data['kabupaten_id']) || (int)$data['kabupaten_id'] <= 0) {
            $errors[] = 'Kabupaten wajib dipilih';
        }

        // Required fields - Kecamatan
        if (empty($data['kecamatan_id']) || (int)$data['kecamatan_id'] <= 0) {
            $errors[] = 'Kecamatan wajib dipilih';
        }

        // Required fields - Desa
        if (empty($data['desa_id']) || (int)$data['desa_id'] <= 0) {
            $errors[] = 'Desa wajib dipilih';
        }

        // Required fields - Luas Layanan
        if (empty($data['luas_layanan'])) {
            $errors[] = 'Luas layanan wajib diisi';
        } elseif (!is_numeric($data['luas_layanan']) || (float)$data['luas_layanan'] <= 0) {
            $errors[] = 'Luas layanan harus berupa angka positif';
        }

        // Status workflow validation
        if (isset($data['status']) && !in_array($data['status'], ['Draf', 'Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'], true)) {
            $errors[] = 'Status workflow tidak valid';
        }

        return $errors;
    }

    /**
     * Generate Unique ID for Irrigation Report
     * Format: IRG-YYYYMMDD-XXXX
     */
    private function generateUniqueId() {
        $prefix = 'LI-' . date('Ymd') . '-';
        $db = Database::getInstance()->getConnection();
        
        // Count reports today
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM laporan_irigasi WHERE tanggal = ?");
        $stmt->execute([date('Y-m-d')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['total'] + 1;
        
        return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * VERIFY: Verifikasi laporan (Hanya Operator/Admin)
     */
    public function verify($id) {
        $this->checkAuth();
        $this->checkRole(['operator', 'admin'], 'Anda tidak memiliki akses verifikasi.');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();
            
            $status = $_POST['status'];
            $catatan = $_POST['catatan_verifikasi'] ?? null;
            
            try {
                $this->model->verify($id, $status, $_SESSION['user_id'], $catatan);
                ErrorMessage::setSuccess('Laporan berhasil ' . ($status == 'Diverifikasi' ? 'diverifikasi' : 'ditolak'));
            } catch (Exception $e) {
                error_log("Irigasi Verify Error: " . $e->getMessage());
                ErrorMessage::set('Gagal memproses verifikasi: ' . $e->getMessage());
            }
            
            $this->redirect('irigasi/index');
        }
    }

    /**
     * READ: Detail laporan irigasi
     */
    public function detail($id) {
        $this->checkAuth();
        
        $data = $this->model->getDetailById($id);
        
        if (!$data) {
            ErrorMessage::set('Data irigasi tidak ditemukan');
            $this->redirect('irigasi/index');
            return;
        }
        
        // Check access for petugas
        $user = $this->getCurrentUser();
        if ($user['role'] === 'petugas' && $data['user_id'] != $user['id']) {
            ErrorMessage::set('Anda tidak memiliki akses ke data ini');
            $this->redirect('irigasi/index');
            return;
        }
        
        $this->view('irigasi/detail', [
            'title' => 'Detail Laporan Irigasi',
            'data' => $data,
            'userRole' => $user['role']
        ]);
    }

    /**
     * UPDATE: Edit laporan irigasi
     */
    public function edit($id) {
        $this->checkAuth();
        
        $data = $this->model->find($id);
        
        if (!$data) {
            ErrorMessage::set('Data irigasi tidak ditemukan');
            $this->redirect('irigasi/index');
            return;
        }
        
        // Check access
        $user = $this->getCurrentUser();
        if ($user['role'] === 'petugas' && $data['user_id'] != $user['id']) {
            ErrorMessage::set('Anda tidak memiliki akses ke data ini');
            $this->redirect('irigasi/index');
            return;
        }
        
        // Only allow editing Draft or Rejected
        if (!in_array($data['status'], ['Draf', 'Ditolak'])) {
            ErrorMessage::set('Laporan yang sudah disubmit atau diverifikasi tidak dapat diedit');
            $this->redirect('irigasi/index');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();
            
            try {
                // Validate input
                $errors = $this->validateInput($_POST);
                
                // For edit, photo is optional if already exists
                if ((!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) && !empty($data['foto_url'])) {
                    // Remove photo validation error if photo already exists
                    $errors = array_filter($errors, function($err) {
                        return strpos($err, 'Foto') === false;
                    });
                }
                
                if (!empty($errors)) {
                    error_log("Irigasi Edit - Validation errors: " . implode(', ', $errors));
                    ErrorMessage::set(implode(', ', $errors));
                    $this->view('irigasi/edit', [
                        'title' => 'Edit Data Irigasi',
                        'data' => array_merge($data, $_POST),
                        'kabupaten' => $this->wilayahModel->getAllOrdered(),
                        'errors' => $errors
                    ]);
                    return;
                }
                
                // Build update data
                $kondisiFisik = !empty($_POST['kondisi_fisik']) ? $_POST['kondisi_fisik'] : 'Bagus';
                
                // Map kondisi form values to DB enum values
                $kondisiFisikMap = [
                    'Baik' => 'Bagus',
                    'Rusak Ringan' => 'Tidak Bagus',
                    'Rusak Berat' => 'Rusak',
                ];
                $kondisiFisikDb = $kondisiFisikMap[$kondisiFisik] ?? $kondisiFisik;
                
                $updateData = [
                    'kabupaten_id' => !empty($_POST['kabupaten_id']) ? (int)$_POST['kabupaten_id'] : null,
                    'kecamatan_id' => !empty($_POST['kecamatan_id']) ? (int)$_POST['kecamatan_id'] : null,
                    'desa_id' => !empty($_POST['desa_id']) ? (int)$_POST['desa_id'] : null,
                    'nama_saluran' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'daerah_irigasi' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'kondisi_fisik' => $kondisiFisikDb,
                    'debit_air' => !empty($_POST['debit_air']) ? $_POST['debit_air'] : 'Cukup',
                    'tanggal' => $_POST['tanggal'],
                    'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
                    'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
                    'catatan' => Security::sanitizeInput($_POST['catatan'] ?? ''),
                    'status' => $_POST['status'] ?? 'Submitted'
                ];
                
                // Handle new photo upload
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';
                    
                    $uploadDir = UPLOAD_PATH . 'irigasi/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $file = $_FILES['foto'];
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $fileName = hash('sha256', time() . $file['name'] . uniqid()) . '.' . $extension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // Delete old photo if exists
                        if (!empty($data['foto_url'])) {
                            $oldPath = ROOT_PATH . '/' . $data['foto_url'];
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $updateData['foto_url'] = 'public/uploads/irigasi/' . $fileName;
                    } else {
                        error_log("Irigasi Edit - Failed to move uploaded file: " . $file['name']);
                        ErrorMessage::set('Gagal mengupload file foto.');
                        $this->view('irigasi/edit', [
                            'title' => 'Edit Data Irigasi',
                            'data' => array_merge($data, $_POST),
                            'kabupaten' => $this->wilayahModel->getAllOrdered(),
                            'errors' => ['foto' => 'Gagal mengupload file foto.']
                        ]);
                        return;
                    }
                }
                
                $this->model->update($id, $updateData);
                error_log("Irigasi Edit - Success: laporan {$id} updated");
                ErrorMessage::setSuccess('Data irigasi berhasil diperbarui');
                $this->redirect('irigasi/index');
                
            } catch (Exception $e) {
                error_log("Irigasi Edit Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                error_log("Irigasi Edit - POST data that caused error: " . json_encode($_POST));
                ErrorMessage::set('Gagal mengupdate data irigasi: ' . $e->getMessage());
                $this->view('irigasi/edit', [
                    'title' => 'Edit Data Irigasi',
                    'data' => array_merge($data, $_POST),
                    'kabupaten' => $this->wilayahModel->getAllOrdered(),
                    'errors' => ['server' => 'Gagal mengupdate data: ' . $e->getMessage()]
                ]);
                return;
            }
        }
        
        $this->view('irigasi/edit', [
            'title' => 'Edit Data Irigasi',
            'data' => $data,
            'kabupaten' => $this->wilayahModel->getAllOrdered()
        ]);
    }

    /**
     * DELETE: Hapus laporan irigasi (Admin only)
     */
    public function delete($id) {
        $this->checkAuth();
        $this->checkRole(['admin'], 'Hanya admin yang dapat menghapus data.');
        $this->requireStateChangingRequest(['POST', 'DELETE']);
        
        try {
            $data = $this->model->find($id);
            
            if (!$data) {
                ErrorMessage::set('Data irigasi tidak ditemukan');
                $this->redirect('irigasi/index');
                return;
            }
            
            // Delete photo if exists
            if (!empty($data['foto_url'])) {
                $photoPath = ROOT_PATH . '/' . $data['foto_url'];
                if (file_exists($photoPath)) {
                    @unlink($photoPath);
                }
            }
            
            $this->model->delete($id);
            ErrorMessage::setSuccess('Data irigasi berhasil dihapus');
            
        } catch (Exception $e) {
            error_log("Irigasi Delete Error: " . $e->getMessage());
            ErrorMessage::set('Gagal menghapus data: ' . $e->getMessage());
        }
        
        $this->redirect('irigasi/index');
    }

    // =========================================================================
    // NEW METHODS: Monitoring, Rules, Analytics
    // =========================================================================

    /**
     * Monitoring Dashboard - Real-time monitoring view
     */
    public function monitoring() {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        
        // Get all irigasi for map
        $irigasiList = [];
        
        if ($user['role'] === 'petugas') {
            $irigasiList = $this->model->getAllWithDetails($user['id']);
        } else {
            $irigasiList = $this->model->getAllWithDetails();
        }
        
        $this->view('irigasi/monitoring', [
            'title' => 'Monitoring Irigasi',
            'irigasiList' => $irigasiList,
            'userRole' => $user['role']
        ]);
    }

    /**
     * Rules Configuration Page
     */
    public function rules($irigasiId = null) {
        $this->checkAuth();
        $this->checkRole(['admin', 'operator'], 'Anda tidak memiliki akses ke konfigurasi rule.');
        
        require_once ROOT_PATH . '/app/models/IrrigationRule.php';
        $ruleModel = new IrrigationRule();
        
        $rules = [];
        $selectedIrigasi = null;
        
        if ($irigasiId) {
            $rules = $ruleModel->getAllRulesForIrigasi($irigasiId);
            $selectedIrigasi = $this->model->find($irigasiId);
        }
        
        // Get all irigasi for dropdown
        $irigasiList = $this->model->getAllWithDetails();
        
        // Get rule templates
        $templates = $ruleModel->getTemplates();
        
        $this->view('irigasi/rules', [
            'title' => 'Konfigurasi Rule Otomasi',
            'rules' => $rules,
            'templates' => $templates,
            'irigasiList' => $irigasiList,
            'selectedIrigasi' => $selectedIrigasi,
            'irigasiId' => $irigasiId,
            'userRole' => $_SESSION['role'] ?? 'guest'
        ]);
    }

    /**
     * Save Rule - AJAX endpoint
     */
    public function saveRule() {
        $this->checkAuth();
        $this->checkRole(['admin', 'operator']);
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }
        
        try {
            $this->validateCsrfToken();
            
            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();
            
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            
            if (empty($data['irigasi_id']) || empty($data['rule_name'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }
            
            $ruleData = [
                'irigasi_id' => $data['irigasi_id'],
                'rule_name' => $data['rule_name'],
                'description' => $data['description'] ?? null,
                'conditions' => $data['conditions'] ?? ['operator' => 'AND', 'conditions' => []],
                'actions' => $data['actions'] ?? ['actions' => []],
                'priority' => $data['priority'] ?? 10,
                'is_active' => $data['is_active'] ?? 1,
                'cooldown_minutes' => $data['cooldown_minutes'] ?? 60,
                'created_by' => $_SESSION['user_id']
            ];
            
            if (!empty($data['id'])) {
                // Update existing rule
                $success = $ruleModel->updateRule($data['id'], $ruleData);
                $message = $success ? 'Rule berhasil diupdate' : 'Gagal mengupdate rule';
            } else {
                // Create new rule
                $ruleId = $ruleModel->createRule($ruleData);
                $success = $ruleId !== false;
                $message = $success ? 'Rule berhasil dibuat' : 'Gagal membuat rule';
            }
            
            echo json_encode(['success' => $success, 'message' => $message]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Toggle Rule Active Status - AJAX endpoint
     */
    public function toggleRuleStatus($ruleId) {
        $this->checkAuth();
        $this->checkRole(['admin', 'operator']);
        $this->requireStateChangingRequest(['POST', 'PATCH']);
        
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();
            
            $rule = $ruleModel->find($ruleId);
            if (!$rule) {
                echo json_encode(['success' => false, 'message' => 'Rule tidak ditemukan']);
                return;
            }
            
            $newStatus = !$rule['is_active'];
            $success = $ruleModel->toggleRule($ruleId, $newStatus);
            
            echo json_encode([
                'success' => $success,
                'is_active' => $newStatus,
                'message' => $success 
                    ? 'Rule berhasil ' . ($newStatus ? 'diaktifkan' : 'dinonaktifkan')
                    : 'Gagal mengubah status rule'
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete Rule - AJAX endpoint
     */
    public function deleteRule($ruleId) {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST', 'DELETE']);
        
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();
            
            $success = $ruleModel->delete($ruleId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Rule berhasil dihapus' : 'Gagal menghapus rule'
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Run Rule Engine - AJAX endpoint
     */
    public function runRuleEngine($irigasiId) {
        $this->checkAuth();
        $this->checkRole(['admin', 'operator']);
        $this->requireStateChangingRequest(['POST']);
        
        header('Content-Type: application/json');
        
        try {
            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';
            $engine = new IrrigationRuleEngine();
            
            $results = $engine->evaluateRules($irigasiId);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Analytics View
     */
    public function analytics($irigasiId = null) {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        
        $irigasiList = [];
        if ($user['role'] === 'petugas') {
            $irigasiList = $this->model->getAllWithDetails($user['id']);
        } else {
            $irigasiList = $this->model->getAllWithDetails();
        }
        
        $selectedIrigasi = null;
        if ($irigasiId) {
            $selectedIrigasi = $this->model->getDetailById($irigasiId);
        }
        
        $this->view('irigasi/analytics', [
            'title' => 'Analitik Irigasi',
            'irigasiList' => $irigasiList,
            'selectedIrigasi' => $selectedIrigasi,
            'irigasiId' => $irigasiId,
            'userRole' => $user['role']
        ]);
    }
}

