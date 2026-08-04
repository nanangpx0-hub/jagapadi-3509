<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/core/Controller.php';
require_once ROOT_PATH . '/app/models/JenisLaporan.php';
require_once ROOT_PATH . '/app/models/LaporanLainnya.php';
require_once ROOT_PATH . '/app/helpers/Logger.php';
require_once ROOT_PATH . '/app/traits/LogsActivity.php';

class LaporanLainnyaController extends Controller {

    use LogsActivity;

    private JenisLaporan $jenisModel;
    private LaporanLainnya $laporanModel;

    public function __construct() {
        parent::__construct();
        $this->jenisModel = new JenisLaporan();
        $this->laporanModel = new LaporanLainnya();
    }

    public function index() {
        $this->checkAuth();
        $status = $_GET['status'] ?? '';
        $jenisId = $_GET['jenis_id'] ?? '';
        $desaId = $_GET['desa_id'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $search = $_GET['search'] ?? '';

        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }
        if ($jenisId !== '') {
            $filters['jenis_id'] = (int)$jenisId;
        }
        if ($desaId !== '') {
            $filters['desa_id'] = (int)$desaId;
        }
        if ($dateFrom !== '') {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $filters['date_to'] = $dateTo;
        }
        if ($search !== '') {
            $filters['search'] = $search;
        }

        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $laporan = $this->laporanModel->getAllWithFilters($filters, $perPage, $offset);
        $total = $this->laporanModel->getCountWithFilters($filters);
        $totalPages = ceil($total / $perPage);
        $jenisList = $this->jenisModel->findAllActive();
        $currentUser = $this->getCurrentUser();

        $this->view('laporan-lainnya/index', [
            'laporan' => $laporan,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'status' => $status,
            'jenisId' => $jenisId,
            'desaId' => $desaId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'jenisList' => $jenisList,
            'currentUser' => $currentUser,
        ]);
    }

    public function create() {
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk membuat laporan lainnya. Hanya akun dengan level Admin, Operator, dan Petugas yang dapat membuat laporan.'
        );

        $jenisList = $this->jenisModel->findAllActive();
        $currentUser = $this->getCurrentUser();
        $users = [];
        if (($currentUser['role'] ?? '') === 'admin') {
            $userModel = $this->model('User');
            $users = $userModel->getAllUsers(1, 500, '', '', '', true);
        }

        $this->view('laporan-lainnya/create', [
            'jenisList' => $jenisList,
            'currentUser' => $currentUser,
            'users' => $users,
        ]);
    }

    public function store() {
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk membuat laporan lainnya. Hanya akun dengan level Admin, Operator, dan Petugas yang dapat membuat laporan.'
        );
        $this->requireStateChangingRequest();

        // ==============================================
        // PHASE 1 SECURITY: Honeypot anti-bot detection
        // ==============================================
        if (!empty($_POST['website_hp'])) {
            error_log('Honeypot triggered on laporan-lainnya/create - potential bot. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $_SESSION['error'] = 'Terjadi kesalahan. Silakan coba lagi.';
            $this->redirect('laporan-lainnya');
            return;
        }

        // ==============================================
        // PHASE 1 SECURITY: Submission rate limiting
        // ==============================================
        $user = $this->getCurrentUser();
        $rateLimitKey = 'laporan_lainnya_submit_' . $user['id'];
        $lastSubmit = $_SESSION[$rateLimitKey] ?? 0;
        $cooldownSeconds = 30;

        if (time() - $lastSubmit < $cooldownSeconds) {
            $remaining = $cooldownSeconds - (time() - $lastSubmit);
            $_SESSION['error'] = "Mohon tunggu {$remaining} detik sebelum mengirim laporan berikutnya.";
            $this->redirect('laporan-lainnya/create');
            return;
        }

        $_SESSION[$rateLimitKey] = time();

        $userRole = $user['role'];

        // Role-based user_id assignment (admin dapat membuat atas nama user lain)
        $targetUserId = $user['id'];
        if ($userRole === 'admin' && !empty($_POST['target_user_id']) && is_numeric($_POST['target_user_id'])) {
            $userModel = $this->model('User');
            $targetUser = $userModel->find($_POST['target_user_id']);
            if ($targetUser && $targetUser['aktif'] == 1) {
                $targetUserId = (int)$_POST['target_user_id'];
            } else {
                $_SESSION['error'] = 'User yang dipilih tidak ditemukan atau tidak aktif';
                $this->redirect('laporan-lainnya/create');
                return;
            }
        }

        $data = $this->sanitizeRequestData();
        $jenisId = (int)($data['jenis_id'] ?? 0);

        $validationErrors = [];

        // ============ Validasi Jenis Laporan ============
        if ($jenisId <= 0) {
            $validationErrors[] = 'Jenis laporan wajib dipilih';
        } else {
            $jenis = $this->jenisModel->findById($jenisId);
            if (!$jenis) {
                $validationErrors[] = 'Jenis laporan tidak ditemukan';
            }
        }

        // ============ Validasi Tanggal Kejadian ============
        $tanggalKejadian = $data['tanggal_kejadian'] ?? '';
        if (empty($tanggalKejadian)) {
            $validationErrors[] = 'Tanggal kejadian wajib diisi';
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $tanggalKejadian);
            if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                $validationErrors[] = 'Format tanggal tidak valid';
            } elseif ($date > new DateTime()) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh di masa depan';
            }
        }

        // ============ Validasi Field Dinamis per Jenis ============
        $fields = $jenisId > 0 ? $this->jenisModel->getFields($jenisId) : [];
        $dataJson = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $data[$fieldName] ?? null;

            if (!empty($field['required']) && ($value === null || trim((string)$value) === '')) {
                $validationErrors[] = "Field '{$field['label']}' wajib diisi";
            }

            $dataJson[$fieldName] = $value;
        }

        // ============ Validasi Wilayah & Alamat (per role) ============
        $kabupatenId = $data['kabupaten_id'] ?? '';
        $kecamatanId = $data['kecamatan_id'] ?? '';
        $desaId = $data['desa_id'] ?? '';
        $alamatLengkap = trim($data['alamat_lengkap'] ?? '');

        if (in_array($userRole, ['petugas', 'operator'])) {
            if (empty($kabupatenId) || empty($kecamatanId) || empty($desaId)) {
                $validationErrors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi';
            }
            if ($userRole === 'petugas' && strlen($alamatLengkap) < 10) {
                $validationErrors[] = 'Alamat lengkap wajib diisi minimal 10 karakter untuk petugas';
            }
            if ($userRole === 'operator' && strlen($alamatLengkap) < 5) {
                $validationErrors[] = 'Alamat lengkap wajib diisi minimal 5 karakter';
            }
        } elseif ($userRole === 'admin' && empty($alamatLengkap)) {
            $validationErrors[] = 'Alamat lengkap wajib diisi';
        }

        // ============ Validasi Koordinat GPS ============
        $latitude = !empty($data['latitude']) ? $data['latitude'] : null;
        $longitude = !empty($data['longitude']) ? $data['longitude'] : null;

        if ($latitude !== null && $longitude !== null) {
            $lat = (float)$latitude;
            $lon = (float)$longitude;

            if ($lat < -90 || $lat > 90) {
                $validationErrors[] = 'Latitude harus antara -90 dan 90';
            }
            if ($lon < -180 || $lon > 180) {
                $validationErrors[] = 'Longitude harus antara -180 dan 180';
            }

            require_once ROOT_PATH . '/app/helpers/GeoValidator.php';
            $geoValidation = GeoValidator::validateJemberCoordinates($lat, $lon);
            if (!$geoValidation['valid']) {
                $validationErrors[] = $geoValidation['message'];
            }
        } elseif (($latitude === null) !== ($longitude === null)) {
            $validationErrors[] = 'Kedua koordinat (Latitude dan Longitude) harus diisi bersama';
        }

        if (!empty($validationErrors)) {
            $_SESSION['error'] = implode('<br>', $validationErrors);
            $this->redirect('laporan-lainnya/create');
            return;
        }

        // ============ Validasi Relasi Wilayah ============
        $kabupatenResolvedId = null;
        $kecamatanResolvedId = null;
        $desaResolvedId = null;

        if (!empty($kabupatenId) && !empty($kecamatanId) && !empty($desaId)) {
            $kabModel = $this->model('MasterKabupaten');
            $kecModel = $this->model('MasterKecamatan');
            $desaModel = $this->model('MasterDesa');

            $kab = $kabModel->findByIdOrKode($kabupatenId);
            $kec = $kecModel->findById($kecamatanId);
            $des = $desaModel->findById($desaId);

            if (!$kab || !$kec || !$des) {
                $_SESSION['error'] = 'Data wilayah tidak ditemukan di database';
                $this->redirect('laporan-lainnya/create');
                return;
            }

            if ($kec['kabupaten_id'] != $kab['id'] || $des['kecamatan_id'] != $kec['id']) {
                $_SESSION['error'] = 'Relasi wilayah tidak valid. Pastikan kecamatan berada di kabupaten yang dipilih dan desa berada di kecamatan yang dipilih.';
                $this->redirect('laporan-lainnya/create');
                return;
            }

            $kabupatenResolvedId = (int)$kab['id'];
            $kecamatanResolvedId = (int)$kec['id'];
            $desaResolvedId = (int)$des['id'];
        }

        // ============ Upload Foto (dengan kompresi otomatis) ============
        $fotoUrl = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';

            $uploadDir = ROOT_PATH . '/public/uploads/laporan-lainnya/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['foto'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect('laporan-lainnya/create');
                return;
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                $_SESSION['error'] = 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect('laporan-lainnya/create');
                return;
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $targetPath = $uploadDir . $fileName;

            if ($file['size'] > $maxSize) {
                $compressor = new ImageCompressor();
                $result = $compressor->compress($file['tmp_name'], $targetPath, $maxSize);

                if ($result['success']) {
                    $fotoUrl = 'uploads/laporan-lainnya/' . $fileName;

                    if ($result['compressed']) {
                        $originalSize = ImageCompressor::formatFileSize($result['original_size']);
                        $finalSize = ImageCompressor::formatFileSize($result['final_size']);
                        $_SESSION['info'] = "Foto berhasil dikompresi dari {$originalSize} menjadi {$finalSize} (pengurangan {$result['reduction_percent']}%)";
                    }
                } else {
                    $_SESSION['error'] = 'Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error');
                    $this->redirect('laporan-lainnya/create');
                    return;
                }
            } else {
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $fotoUrl = 'uploads/laporan-lainnya/' . $fileName;
                } else {
                    $_SESSION['error'] = 'Gagal mengupload file.';
                    $this->redirect('laporan-lainnya/create');
                    return;
                }
            }
        }

        // ============ Simpan Laporan ============
        try {
            $kodeLaporan = $this->laporanModel->generateKodeLaporan();

            $reportData = [
                'user_id' => $targetUserId,
                'jenis_id' => $jenisId,
                'kabupaten_id' => $kabupatenResolvedId,
                'kecamatan_id' => $kecamatanResolvedId,
                'kode_laporan' => $kodeLaporan,
                'desa_id' => $desaResolvedId,
                'alamat_lengkap' => $alamatLengkap ?: null,
                'foto_url' => $fotoUrl,
                'tanggal_kejadian' => $tanggalKejadian,
                'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'deskripsi' => $data['deskripsi'] ?? null,
                'latitude' => $latitude !== null ? (float)$latitude : null,
                'longitude' => $longitude !== null ? (float)$longitude : null,
                'status' => 'draft',
            ];

            $reportId = $this->laporanModel->createReport($reportData);

            if ($reportId) {
                $this->logActivity('Create', 'laporan_lainnya', $reportId, 'Laporan lainnya baru dibuat');
                $successMessage = "Laporan #{$reportId} berhasil dibuat dan langsung masuk sebagai laporan aktif.";

                if ($userRole === 'admin' && $targetUserId != $user['id']) {
                    $userModel = $this->model('User');
                    $targetUser = $userModel->find($targetUserId);
                    if ($targetUser) {
                        $successMessage .= " Laporan dibuat atas nama: " . htmlspecialchars($targetUser['nama_lengkap']);
                    }
                }

                $_SESSION['success'] = $successMessage;
                $this->redirect('laporan-lainnya/show/' . $reportId);
            } else {
                $_SESSION['error'] = 'Gagal menyimpan laporan';
                $this->redirect('laporan-lainnya/create');
            }
        } catch (PDOException $e) {
            error_log("Database error creating laporan lainnya: " . $e->getMessage());
            $errorMessage = 'Terjadi kesalahan database saat menyimpan laporan.';

            if (strpos($e->getMessage(), 'NOT NULL') !== false) {
                $errorMessage .= ' Pastikan semua field wajib sudah diisi.';
            } elseif (strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
                $errorMessage .= ' Data referensi tidak valid (user atau jenis laporan tidak ditemukan).';
            } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errorMessage .= ' Data duplikat terdeteksi.';
            }

            $_SESSION['error'] = $errorMessage;
            $this->redirect('laporan-lainnya/create');
        } catch (Exception $e) {
            error_log("Error creating laporan lainnya: " . $e->getMessage());
            $_SESSION['error'] = 'Terjadi kesalahan saat menyimpan laporan: ' . htmlspecialchars($e->getMessage());
            $this->redirect('laporan-lainnya/create');
        }
    }

    public function edit(int $id) {
        $this->checkAuth();
        $laporan = $this->laporanModel->getById($id);

        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if (!$this->laporanModel->canEdit($id, $_SESSION['user_id'], $_SESSION['role'] ?? '')) {
            $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengedit laporan ini';
            $this->redirect('laporan-lainnya');
            return;
        }

        $jenisList = $this->jenisModel->findAllActive();
        $jenisFields = $this->jenisModel->getFields($laporan['jenis_id']);
        $dataJson = json_decode($laporan['data_json'], true) ?? [];

        $this->view('laporan-lainnya/edit', [
            'laporan' => $laporan,
            'jenisList' => $jenisList,
            'jenisFields' => $jenisFields,
            'dataJson' => $dataJson,
        ]);
    }

    public function update(int $id) {
        $this->checkAuth();
        $this->requireStateChangingRequest();

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if (!$this->laporanModel->canEdit($id, $_SESSION['user_id'], $_SESSION['role'] ?? '')) {
            $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengedit laporan ini';
            $this->redirect('laporan-lainnya');
            return;
        }

        $data = $this->sanitizeRequestData();
        $jenisId = (int)($data['jenis_id'] ?? $laporan['jenis_id']);
        $jenis = $this->jenisModel->findById($jenisId);

        if (!$jenis) {
            $_SESSION['error'] = 'Jenis laporan tidak ditemukan';
            $this->redirect("laporan-lainnya/edit/{$id}");
            return;
        }

        $fields = $this->jenisModel->getFields($jenisId);
        $dataJson = [];
        $validationErrors = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $data[$fieldName] ?? null;

            if (!empty($field['required']) && trim((string)$value) === '') {
                $validationErrors[] = "Field '{$field['label']}' wajib diisi";
            }

            $dataJson[$fieldName] = $value;
        }

        $kabupatenId = $data['kabupaten_id'] ?? '';
        $kecamatanId = $data['kecamatan_id'] ?? '';
        $desaId = $data['desa_id'] ?? '';
        $alamatLengkap = trim($data['alamat_lengkap'] ?? '');

        if (in_array($_SESSION['role'], ['petugas', 'operator']) && (empty($kabupatenId) || empty($kecamatanId) || empty($desaId))) {
            $validationErrors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi';
        }

        $tanggalKejadian = $data['tanggal_kejadian'] ?? '';
        if (!empty($tanggalKejadian)) {
            $date = DateTime::createFromFormat('Y-m-d', $tanggalKejadian);
            if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                $validationErrors[] = 'Format tanggal tidak valid';
            } elseif ($date > new DateTime()) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh di masa depan';
            }
        }

        if (!empty($validationErrors)) {
            $_SESSION['error'] = implode('<br>', $validationErrors);
            $this->redirect("laporan-lainnya/edit/{$id}");
            return;
        }

        // ============ Upload Foto (dengan kompresi otomatis) ============
        $fotoUrl = $laporan['foto_url'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';

            $uploadDir = ROOT_PATH . '/public/uploads/laporan-lainnya/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['foto'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                $_SESSION['error'] = 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            // Hapus foto lama jika ada
            if (!empty($fotoUrl)) {
                $oldFilePath = ROOT_PATH . '/public/' . $fotoUrl;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $targetPath = $uploadDir . $fileName;

            if ($file['size'] > $maxSize) {
                $compressor = new ImageCompressor();
                $result = $compressor->compress($file['tmp_name'], $targetPath, $maxSize);

                if ($result['success']) {
                    $fotoUrl = 'uploads/laporan-lainnya/' . $fileName;

                    if ($result['compressed']) {
                        $originalSize = ImageCompressor::formatFileSize($result['original_size']);
                        $finalSize = ImageCompressor::formatFileSize($result['final_size']);
                        $_SESSION['info'] = "Foto berhasil dikompresi dari {$originalSize} menjadi {$finalSize} (pengurangan {$result['reduction_percent']}%)";
                    }
                } else {
                    $_SESSION['error'] = 'Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error');
                    $this->redirect("laporan-lainnya/edit/{$id}");
                    return;
                }
            } else {
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $fotoUrl = 'uploads/laporan-lainnya/' . $fileName;
                } else {
                    $_SESSION['error'] = 'Gagal mengupload file.';
                    $this->redirect("laporan-lainnya/edit/{$id}");
                    return;
                }
            }
        }

        $kabupatenResolvedId = null;
        $kecamatanResolvedId = null;
        $desaResolvedId = null;

        if (!empty($kabupatenId) && !empty($kecamatanId) && !empty($desaId)) {
            $kabModel = $this->model('MasterKabupaten');
            $kecModel = $this->model('MasterKecamatan');
            $desaModel = $this->model('MasterDesa');

            $kab = $kabModel->findByIdOrKode($kabupatenId);
            $kec = $kecModel->findById($kecamatanId);
            $des = $desaModel->findById($desaId);

            if (!$kab || !$kec || !$des) {
                $_SESSION['error'] = 'Data wilayah tidak ditemukan di database';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            if ($kec['kabupaten_id'] != $kab['id'] || $des['kecamatan_id'] != $kec['id']) {
                $_SESSION['error'] = 'Relasi wilayah tidak valid.';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            $kabupatenResolvedId = (int)$kab['id'];
            $kecamatanResolvedId = (int)$kec['id'];
            $desaResolvedId = (int)$des['id'];
        }

        $updateData = [
            'jenis_id' => $jenisId,
            'kabupaten_id' => $kabupatenResolvedId,
            'kecamatan_id' => $kecamatanResolvedId,
            'desa_id' => $desaResolvedId,
            'alamat_lengkap' => $alamatLengkap ?: null,
            'tanggal_kejadian' => $tanggalKejadian ?: null,
            'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
            'deskripsi' => $data['deskripsi'] ?? null,
            'latitude' => !empty($data['latitude']) ? (float)$data['latitude'] : null,
            'longitude' => !empty($data['longitude']) ? (float)$data['longitude'] : null,
            'foto_url' => $fotoUrl,
        ];

        $success = $this->laporanModel->updateReport($id, $updateData);

        if ($success) {
            $this->logActivity('Update', 'laporan_lainnya', $id, 'Laporan lainnya diperbarui');
            $_SESSION['success'] = 'Laporan lainnya berhasil diperbarui';
        } else {
            $_SESSION['error'] = 'Gagal memperbarui laporan';
        }

        $this->redirect('laporan-lainnya');
    }

    public function show(int $id) {
        $this->checkAuth();
        $laporan = $this->laporanModel->getById($id);

        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        $dataJson = json_decode($laporan['data_json'], true) ?? [];
        $jenisFields = $this->jenisModel->getFields($laporan['jenis_id']);

        $this->view('laporan-lainnya/show', [
            'laporan' => $laporan,
            'dataJson' => $dataJson,
            'jenisFields' => $jenisFields,
        ]);
    }

    public function submit(int $id) {
        $this->checkAuth();
        $this->requireStateChangingRequest();

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if ($laporan['status'] !== 'draft') {
            $_SESSION['error'] = 'Hanya laporan berstatus draft yang dapat disubmit';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        if ($laporan['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'Anda tidak dapat submit laporan orang lain';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $success = $this->laporanModel->submitReport($id);

        if ($success) {
            $this->logActivity('Submit', 'laporan_lainnya', $id, 'Laporan lainnya disubmit dan otomatis diverifikasi');
            $_SESSION['success'] = 'Laporan berhasil disubmit dan masuk antrian verifikasi';
        } else {
            $_SESSION['error'] = 'Gagal submit laporan';
        }

        $this->redirect('laporan-lainnya');
    }

    public function destroy(int $id) {
        $this->checkAuth();
        $this->requireStateChangingRequest();

        if (($_SESSION['role'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Hanya admin yang dapat menghapus laporan';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        $success = $this->laporanModel->delete($id);

        if ($success) {
            $this->logActivity('Delete', 'laporan_lainnya', $id, 'Laporan lainnya dihapus');
            $_SESSION['success'] = 'Laporan berhasil dihapus';
        } else {
            $_SESSION['error'] = 'Gagal menghapus laporan';
        }

        $this->redirect('laporan-lainnya');
    }
}