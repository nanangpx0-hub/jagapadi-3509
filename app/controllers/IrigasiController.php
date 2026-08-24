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

    private function isDevEnvironment(): bool {
        return in_array(
            strtolower((string)(getenv('APP_ENV') ?: 'production')),
            ['local', 'development', 'dev'],
            true
        );
    }

    private function resolveId(mixed $id): int {
        $resolved = filter_var($id, FILTER_VALIDATE_INT);
        if ($resolved === false || $resolved <= 0) {
            ErrorMessage::set('ID laporan tidak valid');
            $this->redirect('irigasi/index');
            exit;
        }
        return $resolved;
    }

    /**
     * READ: Menampilkan daftar laporan
     * Rule: Petugas hanya melihat data sendiri, Admin/Operator melihat semua.
     */
    public function index() {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));
        $userId = $user['role'] === 'petugas' ? (int) $user['id'] : null;
        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'search' => trim((string) ($_GET['search'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        foreach (['date_from', 'date_to'] as $dateKey) {
            if ($filters[$dateKey] !== '') {
                $date = DateTime::createFromFormat('Y-m-d', $filters[$dateKey]);
                if (!$date || $date->format('Y-m-d') !== $filters[$dateKey]) {
                    $filters[$dateKey] = '';
                }
            }
        }
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            $filters['date_from'] = '';
            $filters['date_to'] = '';
        }
        $total = $this->model->countWithFilters($filters, $userId);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $laporan = $this->model->getWithFilters(
            $filters,
            $userId,
            $perPage,
            ($page - 1) * $perPage
        );

        $this->view('irigasi/index', [
            'title' => 'Sebaran Irigasi',
            'laporan' => $laporan,
            'userRole' => $user['role'],
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'status' => $filters['status'],
            'search' => $filters['search'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'petugasReportType' => $user['role'] === 'petugas' ? 'irigasi' : null,
            // Seluruh ID aktif dalam scope user (admin: semua data) untuk pilih-semua lintas halaman.
            'allIds' => $this->model->getAllActiveIds($userId),
        ]);
    }

    /**
     * CREATE: Form tambah laporan baru
     */
    public function create() {
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk membuat laporan irigasi.'
        );

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireStateChangingRequest(['POST']);

            // Rate limiting
            $user = $this->getCurrentUser();
            $rateLimitKey = 'irigasi_create_' . ($user['id'] ?? '0');
            if (Security::checkBruteForce($rateLimitKey, maxAttempts: 10, timeWindow: 3600)) {
                ErrorMessage::set('Terlalu banyak pengiriman laporan. Coba lagi dalam 1 jam.');
                $this->redirect('irigasi/create');
                return;
            }

            // Honeypot check
            if (!empty($_POST['website_hp'])) {
                error_log('Honeypot triggered on irigasi/create - IP: ' .
                    ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                ErrorMessage::set('Terjadi kesalahan. Silakan coba lagi.');
                $this->redirect('irigasi/index');
                return;
            }

            try {
                error_log("Irigasi Create - POST fields: " . implode(', ', array_keys($_POST)));
                
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
                $statusPerbaikan = $kondisiFisik === 'Baik'
                    ? 'Normal'
                    : ($_POST['status_perbaikan'] ?? null);
                
                $data = [
                    'user_id' => $_SESSION['user_id'],
                    'kabupaten_id' => !empty($_POST['kabupaten_id']) ? (int)$_POST['kabupaten_id'] : null,
                    'kecamatan_id' => !empty($_POST['kecamatan_id']) ? (int)$_POST['kecamatan_id'] : null,
                    'desa_id' => !empty($_POST['desa_id']) ? (int)$_POST['desa_id'] : null,
                    'nama_saluran' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'daerah_irigasi' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'luas_layanan' => (float) $_POST['luas_layanan'],
                    'jenis_saluran' => $_POST['jenis_saluran'],
                    'kondisi_fisik' => $kondisiFisikDb,
                    'debit_air' => !empty($_POST['debit_air']) ? $_POST['debit_air'] : 'Cukup',
                    'status_perbaikan' => $statusPerbaikan,
                    'aksi_dilakukan' => Security::sanitizeInput($_POST['aksi_dilakukan'] ?? ''),
                    'tanggal' => $_POST['tanggal'],
                    'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
                    'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
                    'catatan' => Security::sanitizeInput($_POST['catatan'] ?? '')
                ];

                // Handle File Upload with Automatic Compression
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';
                    
                    $uploadDir = ROOT_PATH . '/public/uploads/irigasi/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $file = $_FILES['foto'];
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    
                    // Validate file type using finfo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, $allowedTypes)) {
                        ErrorMessage::set('Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.');
                        $this->view('irigasi/create', [
                            'title' => 'Input Data Irigasi',
                            'kabupaten' => $this->wilayahModel->getAllOrdered(),
                            'data' => $_POST,
                            'errors' => ['foto' => 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.']
                        ]);
                        return;
                    }
                    
                    $extension = match ($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    };
                    
                    // Generate secure filename
                    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
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
                $reportId = $this->model->createSubmitted($data);
                $savedReport = $this->model->find($reportId);
                $noLaporan = (string) ($savedReport['nomor_laporan'] ?? '');
                
                error_log("Irigasi Create - Success: laporan {$noLaporan} saved for user " . $_SESSION['user_id']);
                ErrorMessage::setSuccess('Data irigasi berhasil disubmit dengan nomor: ' . $noLaporan);
                $this->redirect('irigasi/index');

} catch (Throwable $e) {
                error_log(sprintf(
                    '[IrigasiController::create] %s | user_id=%s',
                    $e->getMessage(),
                    $_SESSION['user_id'] ?? 'null'
                ));
                $msg = $this->isDevEnvironment()
                    ? 'Gagal menyimpan data irigasi: ' . $e->getMessage()
                    : 'Gagal menyimpan data irigasi. Silakan coba lagi.';
                ErrorMessage::set($msg);
                $this->view('irigasi/create', [
                    'title' => 'Input Data Irigasi',
                    'kabupaten' => $this->wilayahModel->getAllOrdered(),
                    'data' => $_POST,
                    'errors' => ['server' => $msg]
                ]);
                return;
            }
        }

        // Load data master untuk dropdown
        $kabupaten = $this->wilayahModel->getAllOrdered();

        $this->view('irigasi/create', [
            'title' => 'Input Data Irigasi',
            'kabupaten' => $kabupaten,
            'data' => [
                'tanggal' => (new DateTimeImmutable(
                    'now',
                    new DateTimeZone('Asia/Jakarta')
                ))->format('Y-m-d'),
            ],
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
        } else {
            $localTimezone = new DateTimeZone('Asia/Jakarta');
            $date = DateTime::createFromFormat(
                '!Y-m-d',
                (string) $data['tanggal'],
                $localTimezone
            );
            if (!$date || $date->format('Y-m-d') !== $data['tanggal']) {
                $errors[] = 'Format tanggal laporan tidak valid';
            } elseif ($date > new DateTime('today', $localTimezone)) {
                $errors[] = 'Tanggal tidak boleh melebihi hari ini';
            } elseif ($date < new DateTime('-10 years', $localTimezone)) {
                $errors[] = 'Tanggal laporan tidak boleh lebih dari 10 tahun yang lalu';
            }
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
        } elseif (!is_numeric($data['luas_layanan'])
            || (float)$data['luas_layanan'] <= 0
            || (float)$data['luas_layanan'] > 500000) {
            $errors[] = 'Luas layanan harus antara 0 dan 500.000 hektar';
        }

        // Status workflow ditentukan server; klien tidak boleh menaikkan status sendiri.
        if (isset($data['status']) && $data['status'] !== 'Submitted') {
            $errors[] = 'Status laporan tidak dapat ditentukan dari formulir';
        }

        if (empty($data['jenis_saluran'])
            || !in_array($data['jenis_saluran'], ['Primer', 'Sekunder', 'Tersier'], true)) {
            $errors[] = 'Jenis saluran tidak valid';
        }

        $repairStatuses = ['Selesai Diperbaiki', 'Dalam Perbaikan', 'Belum Ditangani'];
        if (($data['kondisi_fisik'] ?? '') === 'Baik') {
            if (!empty($data['status_perbaikan']) && $data['status_perbaikan'] !== 'Normal') {
                $errors[] = 'Status saluran baik harus Normal';
            }
        } elseif (empty($data['status_perbaikan'])
            || !in_array($data['status_perbaikan'], $repairStatuses, true)) {
            $errors[] = 'Status perbaikan wajib dipilih untuk saluran rusak';
        }

        if (!empty($data['kabupaten_id']) && !empty($data['kecamatan_id']) && !empty($data['desa_id'])) {
            $kabupaten = $this->wilayahModel->findByIdOrKode($data['kabupaten_id']);
            $kecamatan = $this->model('MasterKecamatan')->findById((int) $data['kecamatan_id']);
            $desa = $this->model('MasterDesa')->findById((int) $data['desa_id']);

            if (!$kabupaten || !$kecamatan || !$desa
                || (int) $kecamatan['kabupaten_id'] !== (int) $kabupaten['id']
                || (int) $desa['kecamatan_id'] !== (int) $kecamatan['id']) {
                $errors[] = 'Relasi kabupaten, kecamatan, dan desa tidak valid';
            }
        }

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        if (($latitude === null || $latitude === '') xor ($longitude === null || $longitude === '')) {
            $errors[] = 'Latitude dan longitude harus diisi bersama';
        } elseif ($latitude !== null && $latitude !== '' && $longitude !== null && $longitude !== '') {
            require_once ROOT_PATH . '/app/helpers/GeoValidator.php';
            $geoResult = GeoValidator::validateJemberCoordinates((float) $latitude, (float) $longitude);
            if (!$geoResult['valid']) {
                $errors[] = $geoResult['message'];
            }
        }

        if (!empty($data['aksi_dilakukan']) && mb_strlen((string)$data['aksi_dilakukan']) > 2000) {
            $errors[] = 'Aksi dilakukan maksimal 2000 karakter';
        }

        if (!empty($data['catatan']) && mb_strlen((string)$data['catatan']) > 5000) {
            $errors[] = 'Catatan maksimal 5000 karakter';
        }

        // Warning data quality (bukan error — tetap diizinkan)
        if (($data['kondisi_fisik'] ?? '') === 'Rusak Berat' &&
            ($data['debit_air'] ?? '') === 'Cukup') {
            $_SESSION['warning'] = 'Perhatian: Kondisi saluran Rusak Berat dengan debit Cukup perlu dikonfirmasi.';
        }

        return $errors;
    }

/**
     * VERIFY: Verifikasi laporan (hanya Admin)
     */
    public function verify($id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat memverifikasi laporan irigasi.');
        $this->requireStateChangingRequest(['POST']);

        // Validasi ID
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            ErrorMessage::set('ID laporan tidak valid');
            $this->redirect('irigasi/index');
            return;
        }

        // Validasi status
        $status = $_POST['status'] ?? '';
        $allowedStatuses = ['Diverifikasi', 'Ditolak'];
        if (!in_array($status, $allowedStatuses, true)) {
            ErrorMessage::set('Status verifikasi tidak valid');
            $this->redirect('irigasi/index');
            return;
        }

        // Catatan wajib jika ditolak
        $catatan = trim($_POST['catatan_verifikasi'] ?? '');
        if ($status === 'Ditolak' && $catatan === '') {
            ErrorMessage::set('Alasan penolakan wajib diisi');
            $this->redirect('irigasi/detail/' . $id);
            return;
        }

        try {
            $this->model->verify($id, $status, (int)$_SESSION['user_id'], $catatan ?: null);
            ErrorMessage::setSuccess('Laporan berhasil ' .
                ($status === 'Diverifikasi' ? 'diverifikasi' : 'ditolak'));
        } catch (LogicException $e) {
            // Pesan bisnis yang aman ditampilkan ke user
            ErrorMessage::set($e->getMessage());
        } catch (Throwable $e) {
            error_log('[IrigasiController::verify] ' . $e->getMessage());
            ErrorMessage::set('Gagal memproses verifikasi. Silakan coba lagi.');
        }

        $this->redirect('irigasi/index');
    }

    /**
     * READ: Detail laporan irigasi
     */
    public function detail($id) {
        $this->checkAuth();
        $id = $this->resolveId($id);
        
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
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk mengedit laporan irigasi.'
        );
        $id = $this->resolveId($id);
        
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
        
// Hanya laporan berstatus Ditolak yang dapat diedit
        // (laporan irigasi langsung dibuat berstatus Submitted; tidak ada alur Draf)
        $editableStatuses = ['Ditolak'];
        if (!in_array($data['status'] ?? '', $editableStatuses, true)) {
            ErrorMessage::set('Hanya laporan yang berstatus Ditolak yang dapat diedit');
            $this->redirect('irigasi/detail/' . $id);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireStateChangingRequest(['POST']);

            // Rate limiting
            $editRateKey = 'irigasi_edit_' . ($user['id'] ?? '0');
            if (Security::checkBruteForce($editRateKey, maxAttempts: 20, timeWindow: 3600)) {
                ErrorMessage::set('Terlalu banyak permintaan edit. Coba lagi nanti.');
                $this->redirect('irigasi/index');
                return;
            }
            
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
                $statusPerbaikan = $kondisiFisik === 'Baik'
                    ? 'Normal'
                    : ($_POST['status_perbaikan'] ?? null);
                
                $updateData = [
                    'kabupaten_id' => !empty($_POST['kabupaten_id']) ? (int)$_POST['kabupaten_id'] : null,
                    'kecamatan_id' => !empty($_POST['kecamatan_id']) ? (int)$_POST['kecamatan_id'] : null,
                    'desa_id' => !empty($_POST['desa_id']) ? (int)$_POST['desa_id'] : null,
                    'nama_saluran' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'daerah_irigasi' => trim(Security::sanitizeInput($_POST['nama_saluran'])),
                    'luas_layanan' => (float) $_POST['luas_layanan'],
                    'jenis_saluran' => $_POST['jenis_saluran'],
                    'kondisi_fisik' => $kondisiFisikDb,
                    'debit_air' => !empty($_POST['debit_air']) ? $_POST['debit_air'] : 'Cukup',
                    'status_perbaikan' => $statusPerbaikan,
                    'aksi_dilakukan' => Security::sanitizeInput($_POST['aksi_dilakukan'] ?? ''),
                    'tanggal' => $_POST['tanggal'],
                    'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
                    'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
                    'catatan' => Security::sanitizeInput($_POST['catatan'] ?? '')
                ];

                $oldPhotoToDelete = null;
                
                // Handle new photo upload
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';
                    
                    $uploadDir = ROOT_PATH . '/public/uploads/irigasi/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $file = $_FILES['foto'];
                    $maxSize = 2 * 1024 * 1024;
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $extensions = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($extensions[$mimeType])) {
                        throw new InvalidArgumentException('Tipe foto tidak valid. Gunakan JPG, PNG, atau WEBP.');
                    }

                    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
                    $targetPath = $uploadDir . $fileName;

                    $uploadSucceeded = false;
                    if ($file['size'] > $maxSize) {
                        $compressor = new ImageCompressor();
                        $result = $compressor->compress($file['tmp_name'], $targetPath, $maxSize);
                        $uploadSucceeded = (bool) ($result['success'] ?? false);
                    } else {
                        $uploadSucceeded = move_uploaded_file($file['tmp_name'], $targetPath);
                    }

                    if ($uploadSucceeded) {
                        $oldPhotoToDelete = !empty($data['foto_url'])
                            ? ROOT_PATH . '/' . $data['foto_url']
                            : null;
                        $updateData['foto_url'] = 'public/uploads/irigasi/' . $fileName;
                    } else {
                        error_log("Irigasi Edit - Failed to store uploaded file");
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
                
                $this->model->resubmit((int) $id, $updateData);
                if ($oldPhotoToDelete !== null && is_file($oldPhotoToDelete)) {
                    unlink($oldPhotoToDelete);
                }
                error_log("Irigasi Edit - Success: laporan {$id} updated");
                ErrorMessage::setSuccess('Data irigasi berhasil diperbarui');
                $this->redirect('irigasi/index');
                
} catch (Throwable $e) {
                error_log(sprintf(
                    '[IrigasiController::edit] id=%s | %s | user_id=%s',
                    $id,
                    $e->getMessage(),
                    $_SESSION['user_id'] ?? 'null'
                ));
                $msg = $this->isDevEnvironment()
                    ? 'Gagal mengupdate data irigasi: ' . $e->getMessage()
                    : 'Gagal mengupdate data irigasi. Silakan coba lagi.';
                ErrorMessage::set($msg);
                $this->view('irigasi/edit', [
                    'title' => 'Edit Data Irigasi',
                    'data' => array_merge($data, $_POST),
                    'kabupaten' => $this->wilayahModel->getAllOrdered(),
                    'errors' => ['server' => $msg]
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
        $id = $this->resolveId($id);
        
        try {
            $db = Database::getInstance()->getConnection();
            $data = $this->model->find($id);
            
            if (!$data) {
                ErrorMessage::set('Data irigasi tidak ditemukan');
                $this->redirect('irigasi/index');
                return;
            }
            
            $db->beginTransaction();
            $deleted = $this->model->softDelete($id, (int) $_SESSION['user_id']);
            if (!$deleted) {
                $db->rollBack();
                ErrorMessage::set('Data irigasi sudah dipindahkan ke recycle bin');
                $this->redirect('irigasi');
                return;
            }
            $this->logRecycleBinActivity('soft_delete', $id, 'Laporan irigasi dipindahkan ke recycle bin');
            $db->commit();
            $this->clearRecycleBinCaches();
            ErrorMessage::setSuccess('Data irigasi dipindahkan ke recycle bin');
            
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log(sprintf(
                '[IrigasiController::delete] id=%s | %s | user_id=%s',
                $id,
                $e->getMessage(),
                $_SESSION['user_id'] ?? 'null'
            ));
            $msg = $this->isDevEnvironment()
                ? 'Gagal menghapus data irigasi: ' . $e->getMessage()
                : 'Gagal menghapus data irigasi. Silakan coba lagi.';
            ErrorMessage::set($msg);
        }
        
        $this->redirect('irigasi/index');
    }

    public function bulkDelete(): void {
        $this->checkAuth();
        $this->checkRole(['admin'], 'Hanya admin yang dapat menghapus data.');
        $this->requireStateChangingRequest(['POST']);
        $ids = $_POST['ids'] ?? [];

        // AJAX request (sama pola dengan LaporanController::bulkDelete): balas JSON.
        if ($this->expectsJson() || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json');

            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'Tidak ada data yang dipilih']);
                exit;
            }
            foreach ($ids as $id) {
                if (!is_numeric($id)) {
                    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                    exit;
                }
            }
        }

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $count = is_array($ids)
                ? $this->model->softDeleteMany($ids, (int) $_SESSION['user_id'])
                : 0;
            if ($count > 0) {
                $this->logRecycleBinActivity(
                    'bulk_soft_delete',
                    null,
                    "{$count} laporan irigasi dipindahkan ke recycle bin"
                );
            }
            $db->commit();
            if ($count > 0) {
                $this->clearRecycleBinCaches();
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('IrigasiController::bulkDelete failed: ' . $e->getMessage());
            if ($this->expectsJson()) {
                echo json_encode(['success' => false, 'message' => 'Gagal memindahkan laporan irigasi ke recycle bin']);
                exit;
            }
            $_SESSION['error'] = 'Laporan irigasi gagal dipindahkan ke recycle bin.';
            $this->redirect('irigasi');
        }

        // Respons JSON untuk AJAX bulk delete dari view (pola halaman /laporan).
        if ($this->expectsJson()) {
            echo json_encode([
                'success' => true,
                'message' => $count > 0
                    ? "{$count} laporan irigasi dipindahkan ke recycle bin"
                    : 'Tidak ada laporan irigasi yang dipilih',
                'deleted' => (int) $count,
            ]);
            exit;
        }

        $_SESSION[$count > 0 ? 'success' : 'info'] = $count > 0
            ? "{$count} laporan irigasi dipindahkan ke recycle bin"
            : 'Tidak ada laporan irigasi yang dipilih';
        $this->redirect('irigasi');
    }

    private function logRecycleBinActivity(string $action, ?int $recordId, string $description): void
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) ($_SESSION['user_id'] ?? 0),
            $action,
            'laporan_irigasi',
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    }

    private function clearRecycleBinCaches(): void
    {
        $this->invalidateStatsCache(['dashboard:', 'stats_', 'map_', 'export_']);
        if (class_exists('DashboardDataAggregator')) {
            (new DashboardDataAggregator())->clearCache('irrigation');
        }
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
        
        // Batasi jumlah data yang dimuat untuk monitoring
        // View monitoring hanya menampilkan status terbaru
        $monitoringLimit = 100;
        
        if ($user['role'] === 'petugas') {
            $irigasiList = $this->model->getAllWithDetails((int)$user['id'], $monitoringLimit, 0);
        } else {
            $irigasiList = $this->model->getAllWithDetails(null, $monitoringLimit, 0);
        }
        
        $this->view('irigasi/monitoring', [
            'title' => 'Monitoring Irigasi',
            'irigasiList' => $irigasiList,
            'userRole' => $user['role'],
            'isLimited' => true,
            'limitCount' => $monitoringLimit
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
                'irigasi_id' => filter_var($data['irigasi_id'] ?? 0, FILTER_VALIDATE_INT),
                'rule_name' => mb_substr(trim((string)($data['rule_name'] ?? '')), 0, 200),
                'description' => !empty($data['description'])
                              ? mb_substr(trim((string)$data['description']), 0, 1000)
                              : null,
                'conditions' => is_array($data['conditions'] ?? null)
                              ? $data['conditions']
                              : ['operator' => 'AND', 'conditions' => []],
                'actions' => is_array($data['actions'] ?? null)
                              ? $data['actions']
                              : ['actions' => []],
                'priority' => max(1, min(100, (int)($data['priority'] ?? 10))),
                'is_active' => (int)(bool)($data['is_active'] ?? 1),
                'cooldown_minutes' => max(1, min(1440, (int)($data['cooldown_minutes'] ?? 60))),
                'created_by' => (int)$_SESSION['user_id'],
            ];

            // Validasi irigasi_id
            if ($ruleData['irigasi_id'] === false || $ruleData['irigasi_id'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'irigasi_id tidak valid']);
                return;
            }

            // Validasi rule_name
            if (empty($ruleData['rule_name'])) {
                echo json_encode(['success' => false, 'message' => 'rule_name wajib diisi']);
                return;
            }
            
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
        $ruleId = $this->resolveId($ruleId);
        
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
        $ruleId = $this->resolveId($ruleId);
        
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
        $irigasiId = $this->resolveId($irigasiId);
        
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

