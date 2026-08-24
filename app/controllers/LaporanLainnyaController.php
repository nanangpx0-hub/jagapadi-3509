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
    private CacheManager $cache;

    public function __construct() {
        parent::__construct();
        $this->jenisModel = new JenisLaporan();
        $this->laporanModel = new LaporanLainnya();
        $this->cache = CacheManager::getInstance();
    }

    public function index() {
        $this->checkAuth();
        $status = $_GET['status'] ?? '';
        $jenisId = $_GET['jenis_id'] ?? '';
        $desaId = $_GET['desa_id'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $search = $_GET['search'] ?? '';
        $includeDraftRaw = $_GET['include_draft'] ?? null;

        // Halaman ini adalah daftar kerja/pengelolaan, bukan agregat resmi.
        // Karena itu draf ditampilkan secara default agar data yang baru disimpan
        // langsung terlihat. Pengguna tetap dapat mengecualikannya lewat filter.
        $includeDraft = $includeDraftRaw !== null
            ? in_array(strtolower((string)$includeDraftRaw), ['1', 'true', 'yes'], true)
            : true;

        // ============ Validasi Filter Tanggal (Perbaikan 3d) ============
        if ($dateFrom !== '') {
            $df = DateTime::createFromFormat('Y-m-d', $dateFrom);
            if (!$df || $df->format('Y-m-d') !== $dateFrom) {
                $dateFrom = ''; // abaikan filter tidak valid
            }
        }
        if ($dateTo !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
            if (!$dt || $dt->format('Y-m-d') !== $dateTo) {
                $dateTo = ''; // abaikan filter tidak valid
            }
        }
        // Pastikan date_from <= date_to jika keduanya diset
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            $dateFrom = '';
            $dateTo = '';
        }

        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }
        $filters['include_draft'] = $includeDraft;
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

        // Scoping petugas: model tidak baca $_SESSION (Perbaikan 1),
        // jadi filter user_id & show_own_draft dipaksa eksplisit dari controller (Perbaikan 8)
        $currentUser = $this->getCurrentUser();
        if ($currentUser['role'] === 'petugas') {
            $filters['user_id'] = (int)$currentUser['id'];
            $filters['show_own_draft'] = true;
        }

        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $laporan = $this->laporanModel->getAllWithFilters($filters, $perPage, $offset);
        $total = $this->laporanModel->getCountWithFilters($filters);
        $totalPages = ceil($total / $perPage);
        $jenisList = $this->cache->remember(
            'jenis_laporan:active',
            fn() => $this->jenisModel->findAllActive(),
            3600
        );

        $this->view('laporan-lainnya/index', [
            'title' => 'Laporan Lainnya',
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
            'includeDraft' => $includeDraft,
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
            'title' => 'Buat Laporan Lainnya',
            'jenisList' => $jenisList,
            'currentUser' => $currentUser,
            'users' => $users,
            'tanggalHariIni' => (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Jakarta')
            ))->format('Y-m-d'),
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
        // Rate limiter berbasis IP/keseluruhan aktivitas (Security::checkBruteForce),
        // bukan $_SESSION yang bisa di-bypass. Pengecekan dilakukan SETELAH honeypot
        // dan SEBELUM validasi input agar counter hanya bertambah untuk request
        // yang benar-benar lolos anti-bot.
        $user = $this->getCurrentUser();
        $rateLimitKey = 'laporan_lainnya_store_' . $user['id'];
        if (Security::checkBruteForce($rateLimitKey, maxAttempts: 10, timeWindow: 3600)) {
            $_SESSION['error'] = 'Terlalu banyak pengiriman laporan. Coba lagi dalam 1 jam.';
            $this->redirect('laporan-lainnya/create');
            return;
        }

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
            $localTimezone = new DateTimeZone('Asia/Jakarta');
            $date = DateTime::createFromFormat('!Y-m-d', $tanggalKejadian, $localTimezone);
            if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                $validationErrors[] = 'Format tanggal tidak valid';
            } elseif ($date > new DateTime('today', $localTimezone)) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh di masa depan';
            } elseif ($date < new DateTime('-10 years', $localTimezone)) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh lebih dari 10 tahun yang lalu';
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

        // ============ Type Coercion Field Dinamis (Perbaikan 3c) ============
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $dataJson[$fieldName] ?? null;

            // Type coercion berdasarkan field type
            if ($value !== null && $value !== '') {
                switch ($field['type'] ?? 'text') {
                    case 'number':
                    case 'integer':
                        if (!is_numeric($value)) {
                            $validationErrors[] = "Field '{$field['label']}' harus berupa angka";
                        } else {
                            $dataJson[$fieldName] = (float)$value;
                        }
                        break;
                    case 'date':
                        $d = DateTime::createFromFormat('Y-m-d', (string)$value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $validationErrors[] = "Field '{$field['label']}' harus berupa tanggal valid (YYYY-MM-DD)";
                        }
                        break;
                    case 'text':
                    case 'textarea':
                        if (mb_strlen((string)$value) > 2000) {
                            $validationErrors[] = "Field '{$field['label']}' maksimal 2000 karakter";
                        }
                        break;
                }
            }
        }

        // ============ Validasi Panjang Deskripsi (Perbaikan 3b) ============
        if (!empty($data['deskripsi']) && mb_strlen($data['deskripsi']) > 5000) {
            $validationErrors[] = 'Deskripsi maksimal 5000 karakter';
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

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect('laporan-lainnya/create');
                return;
            }

            // Ekstensi diturunkan dari MIME yang sudah divalidasi (bukan dari nama file user)
            $extension = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };

            if ($extension === null) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
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
            $reportData = [
                'user_id' => $targetUserId,
                'jenis_id' => $jenisId,
                'kabupaten_id' => $kabupatenResolvedId,
                'kecamatan_id' => $kecamatanResolvedId,
                'kode_laporan' => null,
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
                $this->clearDashboardCache();
                $successMessage = "Laporan #{$reportId} berhasil dibuat dan disimpan sebagai draf. Kirim laporan untuk masuk ke antrian verifikasi.";

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
            error_log(sprintf('[LaporanLainnya::store] PDO: %s | user_id=%s',
                $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
            // Pesan spesifik untuk error DB yang umum
            $msg = 'Terjadi kesalahan database saat menyimpan laporan.';
            if (str_contains($e->getMessage(), 'NOT NULL')) {
                $msg .= ' Pastikan semua field wajib sudah diisi.';
            } elseif (str_contains($e->getMessage(), 'FOREIGN KEY')) {
                $msg .= ' Data referensi tidak valid.';
            }
            $_SESSION['error'] = $msg;
            $this->redirect('laporan-lainnya/create');
            return;
        } catch (Throwable $e) {
            error_log(sprintf('[LaporanLainnya::store] Error: %s | user_id=%s',
                $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
            $isDev = in_array(strtolower((string)(getenv('APP_ENV') ?: 'production')),
                              ['local', 'development', 'dev'], true);
            $_SESSION['error'] = $isDev
                ? 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage())
                : 'Terjadi kesalahan saat menyimpan laporan. Silakan coba lagi.';
            $this->redirect('laporan-lainnya/create');
        }
    }

    private function clearDashboardCache(): void {
        try {
            CacheManager::getInstance()->clearPrefix('dashboard:');
            CacheManager::getInstance()->clearPrefix('dash_summary_');
            if (class_exists('DashboardDataAggregator')) {
                (new DashboardDataAggregator())->clearCache('lainnya');
            }
        } catch (Throwable $e) {
            error_log('Failed to clear dashboard cache: ' . $e->getMessage());
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

        // ============ Type Coercion Field Dinamis (Perbaikan 3c) ============
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $dataJson[$fieldName] ?? null;

            // Type coercion berdasarkan field type
            if ($value !== null && $value !== '') {
                switch ($field['type'] ?? 'text') {
                    case 'number':
                    case 'integer':
                        if (!is_numeric($value)) {
                            $validationErrors[] = "Field '{$field['label']}' harus berupa angka";
                        } else {
                            $dataJson[$fieldName] = (float)$value;
                        }
                        break;
                    case 'date':
                        $d = DateTime::createFromFormat('Y-m-d', (string)$value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $validationErrors[] = "Field '{$field['label']}' harus berupa tanggal valid (YYYY-MM-DD)";
                        }
                        break;
                    case 'text':
                    case 'textarea':
                        if (mb_strlen((string)$value) > 2000) {
                            $validationErrors[] = "Field '{$field['label']}' maksimal 2000 karakter";
                        }
                        break;
                }
            }
        }

        $kabupatenId = $data['kabupaten_id'] ?? '';
        $kecamatanId = $data['kecamatan_id'] ?? '';
        $desaId = $data['desa_id'] ?? '';
        $alamatLengkap = trim($data['alamat_lengkap'] ?? '');

        if (in_array($_SESSION['role'], ['petugas', 'operator']) && (empty($kabupatenId) || empty($kecamatanId) || empty($desaId))) {
            $validationErrors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi';
        }

        $tanggalKejadian = $data['tanggal_kejadian'] ?? '';
        // Tanggal kejadian wajib (Perbaikan 3a): tidak boleh dikosongkan saat update
        if (empty($tanggalKejadian)) {
            $validationErrors[] = 'Tanggal kejadian wajib diisi';
        } else {
            $localTimezone = new DateTimeZone('Asia/Jakarta');
            $date = DateTime::createFromFormat('!Y-m-d', $tanggalKejadian, $localTimezone);
            if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                $validationErrors[] = 'Format tanggal tidak valid';
            } elseif ($date > new DateTime('today', $localTimezone)) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh di masa depan';
            } elseif ($date < new DateTime('-10 years', $localTimezone)) {
                $validationErrors[] = 'Tanggal kejadian tidak boleh lebih dari 10 tahun yang lalu';
            }
        }

        // ============ Validasi Panjang Deskripsi (Perbaikan 3b) ============
        if (!empty($data['deskripsi']) && mb_strlen($data['deskripsi']) > 5000) {
            $validationErrors[] = 'Deskripsi maksimal 5000 karakter';
        }

        // ============ Validasi Alamat (per role) ============
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole === 'petugas' && strlen($alamatLengkap) < 10) {
            $validationErrors[] = 'Alamat lengkap wajib diisi minimal 10 karakter untuk petugas';
        }
        if ($userRole === 'operator' && strlen($alamatLengkap) < 5) {
            $validationErrors[] = 'Alamat lengkap wajib diisi minimal 5 karakter';
        }
        if ($userRole === 'admin' && empty($alamatLengkap)) {
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
            $this->redirect("laporan-lainnya/edit/{$id}");
            return;
        }

        // ============ Upload Foto (dengan kompresi otomatis) ============
        $fotoUrl = $laporan['foto_url'] ?? null;
        $oldPhotoToDelete = null; // foto lama dihapus hanya SETELAH upload baru berhasil (Perbaikan 4)
        $newPhotoPath = null; // file foto baru: dibersihkan bila update DB gagal
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';

            $uploadDir = ROOT_PATH . '/public/uploads/laporan-lainnya/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['foto'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            // Ekstensi diturunkan dari MIME yang sudah divalidasi (bukan dari nama file user)
            $extension = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };

            if ($extension === null) {
                $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                $this->redirect("laporan-lainnya/edit/{$id}");
                return;
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $targetPath = $uploadDir . $fileName;
            $newPhotoPath = $targetPath;

            if ($file['size'] > $maxSize) {
                $compressor = new ImageCompressor();
                $result = $compressor->compress($file['tmp_name'], $targetPath, $maxSize);

                if ($result['success']) {
                    $oldPhotoToDelete = !empty($fotoUrl) ? ROOT_PATH . '/public/' . $fotoUrl : null;
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
                    $oldPhotoToDelete = !empty($fotoUrl) ? ROOT_PATH . '/public/' . $fotoUrl : null;
                    $fotoUrl = 'uploads/laporan-lainnya/' . $fileName;
                } else {
                    $_SESSION['error'] = 'Gagal mengupload file.';
                    $this->redirect("laporan-lainnya/edit/{$id}");
                    return;
                }
            }
        }

        // ============ Hapus Foto (tanpa upload baru), file dihapus SETELAH DB berhasil diupdate (Perbaikan 4) ============
        $photoToDeleteAfterUpdate = null;
        if (!empty($data['hapus_foto']) && $data['hapus_foto'] === '1' && empty($_FILES['foto']['name'])) {
            if (!empty($fotoUrl)) {
                $photoToDeleteAfterUpdate = ROOT_PATH . '/public/' . $fotoUrl;
            }
            $fotoUrl = null;
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
            'latitude' => $latitude !== null ? (float)$latitude : null,
            'longitude' => $longitude !== null ? (float)$longitude : null,
            'foto_url' => $fotoUrl,
        ];

        try {
            $success = $this->laporanModel->updateReport($id, $updateData);
        } catch (PDOException $e) {
            // Bersihkan file foto baru bila DB gagal (hindari file yatim)
            if ($newPhotoPath !== null && is_file($newPhotoPath)) {
                @unlink($newPhotoPath);
            }
            error_log(sprintf('[LaporanLainnya::update] PDO: %s | user_id=%s',
                $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
            $msg = 'Terjadi kesalahan database saat memperbarui laporan.';
            if (str_contains($e->getMessage(), 'NOT NULL')) {
                $msg .= ' Pastikan semua field wajib sudah diisi.';
            } elseif (str_contains($e->getMessage(), 'FOREIGN KEY')) {
                $msg .= ' Data referensi tidak valid.';
            }
            $_SESSION['error'] = $msg;
            $this->redirect("laporan-lainnya/edit/{$id}");
            return;
        } catch (Throwable $e) {
            // Bersihkan file foto baru bila DB gagal (hindari file yatim)
            if ($newPhotoPath !== null && is_file($newPhotoPath)) {
                @unlink($newPhotoPath);
            }
            error_log(sprintf('[LaporanLainnya::update] Error: %s | user_id=%s',
                $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
            $isDev = in_array(strtolower((string)(getenv('APP_ENV') ?: 'production')),
                              ['local', 'development', 'dev'], true);
            $_SESSION['error'] = $isDev
                ? 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage())
                : 'Terjadi kesalahan saat memperbarui laporan. Silakan coba lagi.';
            $this->redirect("laporan-lainnya/edit/{$id}");
            return;
        }

        if ($success) {
            // Hapus foto lama hanya SETELAH update DB berhasil (Perbaikan 4)
            if ($photoToDeleteAfterUpdate !== null && is_file($photoToDeleteAfterUpdate)) {
                @unlink($photoToDeleteAfterUpdate);
            }
            if ($oldPhotoToDelete !== null && is_file($oldPhotoToDelete)) {
                @unlink($oldPhotoToDelete);
            }
            $this->logActivity('Update', 'laporan_lainnya', $id, 'Laporan lainnya diperbarui');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan lainnya berhasil diperbarui';
        } else {
            // File foto baru tidak jadi dipakai karena DB gagal
            if ($newPhotoPath !== null && is_file($newPhotoPath)) {
                @unlink($newPhotoPath);
            }
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

        if (($_SESSION['role'] ?? '') === 'petugas' && $laporan['user_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke laporan ini';
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

        if (!in_array($laporan['status'], ['draft', 'rejected'], true)) {
            $_SESSION['error'] = 'Hanya laporan berstatus draft atau rejected yang dapat disubmit';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        if ($laporan['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'Anda tidak dapat submit laporan orang lain';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $user = $this->getCurrentUser();
        try {
            $success = $this->laporanModel->submitReport($id, (int)$user['id'], $user['role']);
        } catch (LogicException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('laporan-lainnya');
            return;
        }

        if ($success) {
            $this->logActivity('Submit', 'laporan_lainnya', $id, 'Laporan lainnya disubmit dan masuk antrian verifikasi');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan berhasil disubmit dan masuk antrian verifikasi';
        } else {
            $_SESSION['error'] = 'Gagal submit laporan';
        }

        $this->redirect('laporan-lainnya');
    }

    public function verify(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat memverifikasi laporan');
        $this->requireStateChangingRequest();

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if ($laporan['status'] !== 'submitted') {
            $_SESSION['error'] = 'Hanya laporan berstatus Submitted yang dapat diverifikasi';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $user = $this->getCurrentUser();
        $catatan = trim($_POST['catatan_verifikasi'] ?? '');
        $success = $this->laporanModel->verifyReport($id, (int)$user['id'], $catatan);

        if ($success) {
            $this->logActivity('Verify', 'laporan_lainnya', $id, 'Laporan lainnya diverifikasi');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan berhasil diverifikasi';
        } else {
            $_SESSION['error'] = 'Gagal memverifikasi laporan';
        }

        $this->redirect("laporan-lainnya/show/{$id}");
    }

    public function reject(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat menolak laporan');
        $this->requireStateChangingRequest();

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if ($laporan['status'] !== 'submitted') {
            $_SESSION['error'] = 'Hanya laporan berstatus Submitted yang dapat ditolak';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $catatan = trim($_POST['catatan_verifikasi'] ?? '');
        if ($catatan === '') {
            $_SESSION['error'] = 'Alasan penolakan wajib diisi';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $user = $this->getCurrentUser();
        $success = $this->laporanModel->rejectReport($id, (int)$user['id'], $catatan);

        if ($success) {
            $this->logActivity('Reject', 'laporan_lainnya', $id, 'Laporan lainnya ditolak: ' . $catatan);
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan berhasil ditolak';
        } else {
            $_SESSION['error'] = 'Gagal menolak laporan';
        }

        $this->redirect("laporan-lainnya/show/{$id}");
    }

    public function archive(int $id) {
        $this->checkRole(['admin'], 'Hanya admin yang dapat mengarsipkan laporan');
        $this->requireStateChangingRequest();

        $laporan = $this->laporanModel->getById($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan-lainnya');
            return;
        }

        if (!in_array($laporan['status'], ['verified', 'submitted', 'rejected'], true)) {
            $_SESSION['error'] = 'Hanya laporan berstatus Submitted, Diverifikasi, atau Ditolak yang dapat diarsipkan';
            $this->redirect("laporan-lainnya/show/{$id}");
            return;
        }

        $success = $this->laporanModel->archiveReport($id);

        if ($success) {
            $this->logActivity('Archive', 'laporan_lainnya', $id, 'Laporan lainnya diarsipkan');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan berhasil diarsipkan';
        } else {
            $_SESSION['error'] = 'Gagal mengarsipkan laporan';
        }

        $this->redirect("laporan-lainnya/show/{$id}");
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

        $success = $this->laporanModel->softDelete($id, (int) $_SESSION['user_id']);

        if ($success) {
            $this->logActivity('SoftDelete', 'laporan_lainnya', $id, 'Laporan lainnya dipindahkan ke recycle bin');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan dipindahkan ke recycle bin';
        } else {
            $_SESSION['error'] = 'Gagal menghapus laporan';
        }

        $this->redirect('laporan-lainnya');
    }

    public function bulkDelete(): void {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        if (!empty($_POST['delete_all'])) {
            $this->deleteAll();
            return;
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            if ($this->expectsJson()) {
                $this->json(['success' => false, 'message' => 'Tidak ada laporan yang dipilih'], 400);
                return;
            }
            $_SESSION['info'] = 'Tidak ada laporan yang dipilih';
            $this->redirect('laporan-lainnya');
            return;
        }

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $count = $this->laporanModel->softDeleteMany($ids, (int) $_SESSION['user_id']);
            if ($count > 0) {
                $this->logActivity('BulkSoftDelete', 'laporan_lainnya', null, "{$count} laporan dipindahkan ke recycle bin");
            }
            $db->commit();
            $this->clearDashboardCache();

            if ($this->expectsJson()) {
                $this->json([
                    'success' => true,
                    'message' => "{$count} laporan lainnya dipindahkan ke recycle bin",
                    'count' => $count
                ]);
                return;
            }

            $_SESSION[$count > 0 ? 'success' : 'info'] = $count > 0
                ? "{$count} laporan lainnya dipindahkan ke recycle bin"
                : 'Tidak ada laporan yang dipilih';
            $this->redirect('laporan-lainnya');
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('LaporanLainnyaController::bulkDelete failed: ' . $e->getMessage());

            if ($this->expectsJson()) {
                $this->json(['success' => false, 'message' => 'Laporan gagal dipindahkan ke recycle bin.'], 500);
                return;
            }

            $_SESSION['error'] = 'Laporan gagal dipindahkan ke recycle bin.';
            $this->redirect('laporan-lainnya');
        }
    }

    public function deleteAll(): void {
        $this->checkAuth();
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST']);

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $count = $this->laporanModel->softDeleteAll((int) $_SESSION['user_id']);
            if ($count > 0) {
                $this->logActivity('DeleteAll', 'laporan_lainnya', null, "Semua laporan ({$count} data) dipindahkan ke recycle bin");
            }
            $db->commit();
            $this->clearDashboardCache();

            if ($this->expectsJson()) {
                $this->json([
                    'success' => true,
                    'message' => "Semua data ({$count} laporan) berhasil dipindahkan ke recycle bin",
                    'count' => $count
                ]);
                return;
            }

            $_SESSION['success'] = "Semua data ({$count} laporan) berhasil dipindahkan ke recycle bin";
            $this->redirect('laporan-lainnya');
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('LaporanLainnyaController::deleteAll failed: ' . $e->getMessage());

            if ($this->expectsJson()) {
                $this->json(['success' => false, 'message' => 'Gagal memindahkan semua data ke recycle bin.'], 500);
                return;
            }

            $_SESSION['error'] = 'Gagal memindahkan semua data ke recycle bin.';
            $this->redirect('laporan-lainnya');
        }
    }

    /**
     * Display performance summary/report for petugas
     * This method is restricted to petugas role only
     */
    public function summary(): void {
        $this->report();
    }

    /**
     * Display performance report for petugas (menu: Report)
     * Alias baru untuk summary() — URL: /laporan-lainnya/report
     * This method is restricted to petugas role only
     */
    public function report(): void {
        $this->checkRole(['petugas'], 'Akses khusus untuk Petugas Lapangan.');
        
        $currentUser = $this->getCurrentUser();
        $userId = (int)$currentUser['id'];
        
        // Get current year or from request
        $year = (int)($_GET['year'] ?? date('Y'));
        
        // Validate year range
        if ($year < 2020 || $year > (date('Y') + 1)) {
            $year = (int)date('Y');
        }

        // Get performance data using the new model methods
        $performanceSummary = $this->laporanModel->getPetugasPerformanceSummary($userId, $year);
        $monthlyTrend = $this->laporanModel->getPetugasMonthlyTrend($userId, $year);
        $jenisBreakdown = $this->laporanModel->getPetugasBreakdownByJenis($userId, $year);
        
        // Get recent reports for the user
        $recentReports = $this->laporanModel->getPetugasReportList($userId, [], 10, 0);

        $this->view('laporan-lainnya/report', [
            'title' => 'Report',
            'year' => $year,
            'performanceSummary' => $performanceSummary,
            'monthlyTrend' => $monthlyTrend,
            'jenisBreakdown' => $jenisBreakdown,
            'recentReports' => $recentReports,
            'currentUser' => $currentUser,
        ]);
    }

    /**
     * Export petugas reports to CSV format
     * This method is restricted to petugas role only
     */
    public function export() {
        $this->checkRole(['petugas'], 'Akses khusus untuk Petugas Lapangan.');
        $this->requireStateChangingRequest();

        $currentUser = $this->getCurrentUser();
        $userId = (int)$currentUser['id'];
        
        // Get filters from request
        $year = (int)($_POST['year'] ?? date('Y'));
        $status = $_POST['status'] ?? '';
        $jenisId = $_POST['jenis_id'] ?? '';
        
        // Validate year range
        if ($year < 2020 || $year > (date('Y') + 1)) {
            $year = (int)date('Y');
        }

        // Build filters
        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }
        if ($jenisId !== '') {
            $filters['jenis_id'] = (int)$jenisId;
        }
        
        // Add date range filter for the year
        $filters['date_from'] = "{$year}-01-01";
        $filters['date_to'] = "{$year}-12-31";

        try {
            // Get all reports for the user with filters
            $reports = $this->laporanModel->getPetugasReportList($userId, $filters, 10000, 0);
            
            if (empty($reports)) {
                $_SESSION['error'] = 'Tidak ada data untuk diekspor';
                $this->redirect('laporan-lainnya/report');
                return;
            }

            // Set headers for CSV download
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="laporan-lainnya-' . $userId . '-' . $year . '.csv"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Open output stream
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new RuntimeException('Cannot open output stream');
            }
            
            // Write UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            
            // CSV headers
            $headers = [
                'ID',
                'Kode Laporan',
                'Tanggal Kejadian',
                'Status',
                'Jenis Laporan',
                'Desa',
                'Kecamatan',
                'Kabupaten',
                'Alamat Lengkap',
                'Deskripsi',
                'Latitude',
                'Longitude',
                'Diverifikasi Oleh',
                'Tanggal Verifikasi',
                'Catatan Verifikasi',
                'Dibuat Pada',
                'Diperbarui Pada'
            ];
            
            // Sanitize headers for CSV injection prevention
            $sanitizedHeaders = array_map([Security::class, 'sanitizeCell'], $headers);
            fputcsv($output, $sanitizedHeaders);
            
            // Write data rows
            foreach ($reports as $report) {
                $row = [
                    $report['id'] ?? '',
                    $report['kode_laporan'] ?? '',
                    $report['tanggal_kejadian'] ?? '',
                    $report['status'] ?? '',
                    $report['jenis_nama'] ?? '',
                    $report['nama_desa'] ?? '',
                    $report['nama_kecamatan'] ?? '',
                    $report['nama_kabupaten'] ?? '',
                    $report['alamat_lengkap'] ?? '',
                    $report['deskripsi'] ?? '',
                    $report['latitude'] ?? '',
                    $report['longitude'] ?? '',
                    $report['verifikator_nama'] ?? '',
                    $report['verified_at'] ?? '',
                    $report['catatan_verifikasi'] ?? '',
                    $report['created_at'] ?? '',
                    $report['updated_at'] ?? ''
                ];
                
                // Sanitize row for CSV injection prevention
                $sanitizedRow = array_map([Security::class, 'sanitizeCell'], $row);
                fputcsv($output, $sanitizedRow);
            }
            
            fclose($output);
            
            // Log export activity
            $this->logActivity('Export', 'laporan_lainnya', 0, 
                'Export laporan lainnya untuk user_id=' . $userId . ' tahun=' . $year . ' jumlah=' . count($reports));
            
            exit;
            
        } catch (Exception $e) {
            error_log('Export failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Gagal mengekspor data: ' . $e->getMessage();
            $this->redirect('laporan-lainnya/report');
        }
    }
}
