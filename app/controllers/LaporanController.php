<?php
class LaporanController extends Controller {
    private $laporanModel;
    private $optModel;

    public function __construct() {
        $this->laporanModel = $this->model('LaporanHama');
        $this->optModel = $this->model('MasterOpt');
    }

    public function index() {
        $this->checkAuth();

        $status = $_GET['status'] ?? null;
        $user = $this->getCurrentUser();

        if ($user['role'] === 'petugas') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));
            $filters = [
                'status' => trim((string) ($status ?? '')),
                'search' => trim((string) ($_GET['search'] ?? '')),
                'master_opt_id' => (int) ($_GET['master_opt_id'] ?? 0),
                'kecamatan_id' => (int) ($_GET['kecamatan_id'] ?? 0),
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
            try {
                $result = $this->laporanModel->fetchPaginated($filters, $page, $perPage, (int) $user['id']);
            } catch (InvalidArgumentException $e) {
                $filters['status'] = '';
                $result = $this->laporanModel->fetchPaginated($filters, $page, $perPage, (int) $user['id']);
            }

            $this->view('laporan/index', [
                'title' => 'Laporan Hama',
                'laporan' => $result['rows'],
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'totalPages' => $result['totalPages'],
                'status' => $filters['status'],
                'search' => $filters['search'],
                'dateFrom' => $filters['date_from'],
                'dateTo' => $filters['date_to'],
                'currentUser' => $user,
                'petugasReportType' => 'hama',
            ]);
            return;
        }

        // Role-based filtering: petugas can only see their own reports
        if ($user['role'] !== 'petugas') {
            // Admin and operator can see all reports
            if ($status) {
                $laporan = $this->laporanModel->getByStatus($status);
            } else {
                $laporan = $this->laporanModel->getAllWithDetails();
            }
        }

        // Compute counts for filter badges (always from ALL reports, unfiltered)
        $userId = $user['role'] === 'petugas' ? (int)$user['id'] : null;
        if ($userId !== null) {
            $countAll = $this->laporanModel->getCountByStatus('Draf', $userId)
                + $this->laporanModel->getCountByStatus('Submitted', $userId)
                + $this->laporanModel->getCountByStatus('Diverifikasi', $userId)
                + $this->laporanModel->getCountByStatus('Ditolak', $userId)
                + $this->laporanModel->getCountByStatus('Diarsipkan', $userId);
        } else {
            $countAll = $this->laporanModel->count();
        }
        $countDraft = $this->laporanModel->getCountByStatus('Draf', $userId);
        $countActive = $this->laporanModel->getCountByStatus('Submitted', $userId)
            + $this->laporanModel->getCountByStatus('Diverifikasi', $userId);

        $data = [
            'title' => 'Daftar Laporan',
            'laporan' => $laporan,
            'countAll' => $countAll,
            'countDraft' => $countDraft,
            'countActive' => $countActive,
            'status' => $status,
            'currentUser' => $user,
            'rejectedCount' => 0
        ];

        $this->view('laporan/index', $data);
    }

    /**
     * API: Get tag suggestions for autocomplete
     * Route: GET /laporan/tagSuggestions?q=query
     */
    public function tagSuggestions() {
        $this->checkAuth();

        $query = $_GET['q'] ?? '';
        if (empty($query) || strlen($query) < 2) {
            $this->json(['success' => true, 'data' => []]);
        }

        try {
            $tagModel = $this->model('Tag');
            $tags = $tagModel->search($query, 10);
            $this->json(['success' => true, 'data' => $tags]);
        } catch (Exception $e) {
            error_log("Error in tagSuggestions: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Terjadi kesalahan'], 500);
        }
    }

    /**
     * API: Generate auto tags based on laporan content
     * Route: POST /laporan/generateAutoTags
     */
    public function generateAutoTags() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        try {
            // Get JSON input or POST data
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input) && !empty($_POST)) {
                $input = $_POST;
            }

            // Get OPT name if master_opt_id is provided
            $namaOpt = '';
            if (!empty($input['master_opt_id'])) {
                $opt = $this->optModel->find($input['master_opt_id']);
                if ($opt) {
                    $namaOpt = $opt['nama_opt'] ?? '';
                }
            }

            $laporanData = [
                'catatan' => $input['catatan'] ?? '',
                'tingkat_keparahan' => $input['tingkat_keparahan'] ?? '',
                'populasi' => isset($input['populasi']) ? (float)$input['populasi'] : 0,
                'luas_serangan' => isset($input['luas_serangan']) ? (float)$input['luas_serangan'] : 0,
                'nama_opt' => $input['nama_opt'] ?? $namaOpt
            ];

            $tagModel = $this->model('Tag');
            $suggestions = $tagModel->generateAutoTags($laporanData);

            $this->json(['success' => true, 'data' => $suggestions]);
        } catch (Exception $e) {
            error_log("Error in generateAutoTags: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Terjadi kesalahan saat menghasilkan tag'], 500);
        }
    }

    public function create() {
        // Validasi level pengguna sebelum proses pembuatan laporan dimulai
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk membuat laporan hama. Hanya akun dengan level Admin, Operator, dan Petugas yang dapat membuat laporan.'
        );

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();

            // Simpan old input: isian tidak boleh hilang saat validasi/redirect
            // gagal (dibersihkan setelah laporan sukses dibuat).
            $_SESSION['form_data'] = $_POST;

            // ==============================================
            // PHASE 1 SECURITY: Honeypot anti-bot detection
            // ==============================================
            if (!empty($_POST['website_hp'])) {
                // Honeypot field was filled - this is a bot
                error_log('Honeypot triggered on laporan/create - potential bot. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                $_SESSION['error'] = 'Terjadi kesalahan. Silakan coba lagi.';
                $this->redirect('laporan');
                return;
            }

            // ==============================================
            // PHASE 1 SECURITY: Submission rate limiting
            // ==============================================
            $user = $this->getCurrentUser();
            $rateLimitKey = 'laporan_submit_' . $user['id'];
            $lastSubmit = $_SESSION[$rateLimitKey] ?? 0;
            $cooldownSeconds = 30;

            if (time() - $lastSubmit < $cooldownSeconds) {
                $remaining = $cooldownSeconds - (time() - $lastSubmit);
                $_SESSION['error'] = "Mohon tunggu {$remaining} detik sebelum mengirim laporan berikutnya.";
                $this->redirect('laporan/create');
                return;
            }

            $userRole = $user['role'];

            // Role-based user_id assignment
            // Admin dapat membuat laporan atas nama user lain
            // Operator dan Petugas hanya dapat membuat laporan atas nama sendiri
            $targetUserId = $user['id'];
            if ($userRole === 'admin' && !empty($_POST['target_user_id']) && is_numeric($_POST['target_user_id'])) {
                // Verify target user exists and is active
                $userModel = $this->model('User');
                $targetUser = $userModel->find($_POST['target_user_id']);
                if ($targetUser && $targetUser['aktif'] == 1) {
                    $targetUserId = (int)$_POST['target_user_id'];
                } else {
                    $_SESSION['error'] = 'User yang dipilih tidak ditemukan atau tidak aktif';
                    $this->redirect('laporan/create');
                }
            }

            // Prepare post data with proper defaults
            $alamatLengkap = trim($_POST['alamat_lengkap'] ?? $_POST['lokasi'] ?? '');

            // Ensure lokasi is never empty (required by database)
            // If alamat_lengkap is empty, use a default value or construct from wilayah
            $lokasi = $alamatLengkap;
            if (empty($lokasi)) {
                // Try to construct from wilayah if available
                $kabId = $_POST['kabupaten_id'] ?? null;
                $kecId = $_POST['kecamatan_id'] ?? null;
                $desId = $_POST['desa_id'] ?? null;

                if ($kabId && $kecId && $desId && $kabId !== 'unknown' && $kecId !== 'unknown' && $desId !== 'unknown') {
                    // Will be filled after wilayah validation
                    $lokasi = 'Lokasi akan diisi setelah validasi wilayah';
                } else {
                    $lokasi = 'Lokasi belum ditentukan';
                }
            }

            $metodePengukuran = $_POST['metode_pengukuran'] ?? 'absolut';
            $persentaseSerangan = $_POST['persentase_serangan'] ?? null;
            $luasArealDiamati = $_POST['luas_areal_diamati'] ?? null;
            $luasEstimasi = null;
            if ($metodePengukuran === 'persentase' && is_numeric($persentaseSerangan) && is_numeric($luasArealDiamati)) {
                $luasEstimasi = round((float) $luasArealDiamati * (float) $persentaseSerangan / 100, 2);
            }

            $hasOptProposal = empty($_POST['master_opt_id']) && trim($_POST['nama_hama_baru'] ?? '') !== '';
            $usulanOptId = $hasOptProposal ? -1 : null;

            $postData = [
                'user_id' => $targetUserId,
                'master_opt_id' => $_POST['master_opt_id'] ?? null,
                'usulan_opt_id' => $usulanOptId ?: null,
                'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
                'lokasi' => $lokasi, // Always set, required by database
                'kabupaten_id' => $_POST['kabupaten_id'] !== 'unknown' ? ($_POST['kabupaten_id'] ?? null) : null,
                'kecamatan_id' => $_POST['kecamatan_id'] !== 'unknown' ? ($_POST['kecamatan_id'] ?? null) : null,
                'desa_id' => $_POST['desa_id'] !== 'unknown' ? ($_POST['desa_id'] ?? null) : null,
                'alamat_lengkap' => $alamatLengkap ?: null,
                'latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
                'longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
                'tingkat_keparahan' => $_POST['tingkat_keparahan'] ?? null,
                'populasi' => isset($_POST['populasi']) && $_POST['populasi'] !== '' ? (int)$_POST['populasi'] : 0,
                'luas_serangan' => isset($_POST['luas_serangan']) && $_POST['luas_serangan'] !== '' ? (float)$_POST['luas_serangan'] : 0,
                'metode_pengukuran' => $metodePengukuran,
                'persentase_serangan' => $persentaseSerangan !== null && $persentaseSerangan !== '' ? (float) $persentaseSerangan : null,
                'luas_areal_diamati' => $luasArealDiamati !== null && $luasArealDiamati !== '' ? (float) $luasArealDiamati : null,
                'luas_serangan_estimasi' => $luasEstimasi,
                'catatan' => $_POST['catatan'] ?? '',
                'status' => 'Submitted'
            ];

            // Update lokasi after wilayah validation if needed
            if (!empty($postData['kabupaten_id']) && !empty($postData['kecamatan_id']) && !empty($postData['desa_id'])) {
                // Will be updated after wilayah names are fetched
            }

            // Role-based validation
            $validationErrors = $this->validateLaporanData($postData, $userRole);
            if (!empty($validationErrors)) {
                $_SESSION['error'] = implode('<br>', $validationErrors);
                $this->redirect('laporan/create');
            }
            // Validate wilayah relationship if wilayah data is provided
            if (!empty($postData['kabupaten_id']) && !empty($postData['kecamatan_id']) && !empty($postData['desa_id'])) {
                $kabModel = $this->model('MasterKabupaten');
                $kecModel = $this->model('MasterKecamatan');
                $desaModel = $this->model('MasterDesa');
                // findByIdOrKode supports DB id, BPS kode (3509), short kode (09), dan format lain
                $kab = $kabModel->findByIdOrKode($postData['kabupaten_id']);
                $kec = $kecModel->findById($postData['kecamatan_id']);
                $des = $desaModel->findById($postData['desa_id']);

                if (!$kab || !$kec || !$des) {
                    $_SESSION['error'] = 'Data wilayah tidak ditemukan di database';
                    $this->redirect('laporan/create');
                }

                if ($kec['kabupaten_id'] != $kab['id'] || $des['kecamatan_id'] != $kec['id']) {
                    $_SESSION['error'] = 'Relasi wilayah tidak valid. Pastikan kecamatan berada di kabupaten yang dipilih dan desa berada di kecamatan yang dipilih.';
                    $this->redirect('laporan/create');
                }

                // Normalize ke id asli di database (form bisa mengirim format padded seperti '09')
                $postData['kabupaten_id'] = $kab['id'];

                // Update lokasi with complete address if alamat_lengkap is empty
                if (empty($postData['alamat_lengkap']) || $postData['lokasi'] === 'Lokasi akan diisi setelah validasi wilayah') {
                    $postData['lokasi'] = $kab['nama_kabupaten'] . ', ' . $kec['nama_kecamatan'] . ', ' . $des['nama_desa'];
                } else {
                    $postData['lokasi'] = $postData['alamat_lengkap'];
                }
            } else {
                // If no wilayah but alamat_lengkap exists, use it
                if (!empty($postData['alamat_lengkap'])) {
                    $postData['lokasi'] = $postData['alamat_lengkap'];
                }
                // If still empty, ensure lokasi has a value (required by database)
                if (empty($postData['lokasi'])) {
                    $postData['lokasi'] = 'Lokasi belum ditentukan';
                }
            }

            // Foto wajib. Periksa kode error PHP secara eksplisit agar file yang
            // sudah dipilih tidak salah dilaporkan sebagai "tidak ada foto".
            $photo = $_FILES['foto'] ?? null;
            $photoUploadError = is_array($photo)
                ? (int) ($photo['error'] ?? UPLOAD_ERR_NO_FILE)
                : UPLOAD_ERR_NO_FILE;
            if ($photoUploadError !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = $this->laporanPhotoUploadErrorMessage($photoUploadError);
                $this->redirect('laporan/create');
            }

            // Handle file upload with automatic compression
            if (is_array($photo)) {
                require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';

                // URL yang disimpan adalah public/uploads/laporan/*, sehingga
                // file fisik wajib ditulis ke direktori public yang sama.
                $uploadDir = ROOT_PATH . '/public/uploads/laporan/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $file = $photo;
                $maxSize = 2 * 1024 * 1024; // 2MB
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                    $_SESSION['error'] = 'Sumber file foto tidak valid. Silakan pilih ulang foto.';
                    $this->redirect('laporan/create');
                }

                // Validate file type using finfo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedTypes)) {
                    $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                    $this->redirect('laporan/create');
                }

                // Validate file extension
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    $_SESSION['error'] = 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                    $this->redirect('laporan/create');
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
                        $postData['foto_url'] = 'public/uploads/laporan/' . $fileName;

                        // Set info message about compression
                        if ($result['compressed']) {
                            $originalSize = ImageCompressor::formatFileSize($result['original_size']);
                            $finalSize = ImageCompressor::formatFileSize($result['final_size']);
                            $_SESSION['info'] = "Foto berhasil dikompresi dari {$originalSize} menjadi {$finalSize} (pengurangan {$result['reduction_percent']}%)";
                        }
                    } else {
                        $_SESSION['error'] = 'Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error');
                        $this->redirect('laporan/create');
                    }
                } else {
                    // File size is acceptable, just move it
                    if (move_uploaded_file($tempPath, $targetPath)) {
                        $postData['foto_url'] = 'public/uploads/laporan/' . $fileName;
                    } else {
                        $_SESSION['error'] = 'Gagal mengupload file.';
                        $this->redirect('laporan/create');
                    }
                }
            }

            if (empty($postData['foto_url'])) {
                $_SESSION['error'] = 'Foto laporan gagal disimpan. Silakan pilih ulang foto dan coba lagi.';
                $this->redirect('laporan/create');
            }

            // Video benar-benar opsional: UPLOAD_ERR_NO_FILE diterima oleh
            // VideoUploader dan menghasilkan path null tanpa memblokir laporan.
            if (isset($_FILES['video'])) {
                require_once ROOT_PATH . '/app/helpers/VideoUploader.php';
                $videoResult = (new VideoUploader())->upload($_FILES['video']);
                if (!$videoResult['success']) {
                    $_SESSION['error'] = $videoResult['error'];
                    $this->redirect('laporan/create');
                }
                if (!empty($videoResult['path'])) {
                    $postData['video_url'] = $videoResult['path'];
                }
            }

            // Ensure required fields are not null before database insert
            if (empty($postData['lokasi'])) {
                $_SESSION['error'] = 'Field lokasi tidak boleh kosong. Pastikan alamat lengkap atau data wilayah sudah diisi.';
                $this->redirect('laporan/create');
            }
            if (empty($postData['tanggal'])) {
                $_SESSION['error'] = 'Field tanggal tidak boleh kosong.';
                $this->redirect('laporan/create');
            }
            if (empty($postData['tingkat_keparahan'])) {
                $_SESSION['error'] = 'Field tingkat_keparahan tidak boleh kosong.';
                $this->redirect('laporan/create');
            }

            // Try to create the report with comprehensive error handling
            $db = Database::getInstance()->getConnection();
            try {
                if ($hasOptProposal) {
                    $db->beginTransaction();

                    $usulanService = new UsulanOptService($db);
                    $createdUsulanId = $usulanService->createFromLaporan(
                        (int) $targetUserId,
                        $usulanService->normalize([
                            'nama_nasional' => trim($_POST['nama_nasional_baru'] ?? ''),
                            'nama_lokal' => trim($_POST['nama_hama_baru']),
                            'jenis' => $_POST['jenis_opt_baru'] ?? '',
                            'komoditas' => trim($_POST['komoditas_opt_baru'] ?? ''),
                            'ciri_ciri' => trim($_POST['ciri_opt_baru'] ?? ''),
                            'wilayah' => $alamatLengkap ?: null,
                            'tanggal_ditemukan' => (string) ($_POST['tanggal'] ?? ''),
                            'kabupaten_id' => $postData['kabupaten_id'] ?? null,
                            'kecamatan_id' => $postData['kecamatan_id'] ?? null,
                            'desa_id' => $postData['desa_id'] ?? null,
                            'alamat_lokasi' => $postData['alamat_lengkap'] ?? '',
                            'latitude' => $postData['latitude'] ?? '',
                            'longitude' => $postData['longitude'] ?? '',
                        ]),
                        (int) $user['id'],
                        !empty($postData['foto_url']) ? (string) $postData['foto_url'] : null
                    );
                    $postData['usulan_opt_id'] = $createdUsulanId;
                }

                $id = $this->laporanModel->create($postData);

                if (!$id || $id <= 0) {
                    throw new Exception('Gagal menyimpan laporan ke database. ID tidak valid.');
                }

                // Rate limit hanya dicatat setelah laporan benar-benar tersimpan.
                // Pengguna yang perlu memperbaiki upload tidak terblokir 30 detik.
                $_SESSION[$rateLimitKey] = time();

                if ($db->inTransaction()) {
                    $db->commit();
                }

                // Save tags if provided
                if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                    $tagModel = $this->model('Tag');
                    $tagIds = [];

                    foreach ($_POST['tags'] as $tagInput) {
                        if (is_numeric($tagInput)) {
                            // Existing tag ID
                            $tagIds[] = (int)$tagInput;
                        } else if (!empty(trim($tagInput))) {
                            // New tag - create if not exists
                            $tagId = $tagModel->findOrCreate(trim($tagInput));
                            $tagIds[] = $tagId;
                        }
                    }

                    if (!empty($tagIds)) {
                        $tagModel->setForLaporan($id, $tagIds);
                    }
                }

                // Log successful creation
                error_log("Laporan created successfully: ID {$id} by user {$user['id']} ({$user['role']})");

                // Log status history for new reports
                $this->logStatusHistory($id, null, $postData['status'], $user['id'], 'Laporan baru dibuat');

                // Check if exceeds ETL and create notification
                $opt = $this->optModel->find($postData['master_opt_id']);
                if ($opt && $opt['etl_acuan'] > 0 && $postData['populasi'] > $opt['etl_acuan']) {
                    $this->createNotification(
                        1, // Admin user
                        'Alert ETL Terlampaui',
                        "Laporan #{$id} melampaui ETL: {$opt['nama_opt']} dengan populasi {$postData['populasi']}",
                        'danger'
                    );
                }

                $this->clearDashboardCache();

                $successMessage = "Laporan #{$id} berhasil dibuat dan langsung masuk sebagai laporan aktif.";

                // Add role-specific info
                if ($userRole === 'admin' && $targetUserId != $user['id']) {
                    $userModel = $this->model('User');
                    $targetUser = $userModel->find($targetUserId);
                    if ($targetUser) {
                        $successMessage .= " Laporan dibuat atas nama: " . htmlspecialchars($targetUser['nama_lengkap']);
                    }
                }

                $_SESSION['success'] = $successMessage;
                $_SESSION['created_laporan_id'] = $id; // Store ID for confirmation page

                // Old input selesai dipakai — jangan bocor ke form berikutnya
                unset($_SESSION['form_data']);

                // Redirect to detail page for confirmation
                $this->redirect('laporan/detail/' . $id);

            } catch (PDOException $e) {
                error_log("Database error creating laporan: " . $e->getMessage());
                error_log("SQL Error Code: " . $e->getCode());

                $errorMessage = 'Terjadi kesalahan database saat menyimpan laporan.';

                // Provide more specific error messages
                if (strpos($e->getMessage(), 'NOT NULL') !== false) {
                    $errorMessage .= ' Pastikan semua field wajib sudah diisi.';
                } elseif (strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
                    $errorMessage .= ' Data referensi tidak valid (user atau OPT tidak ditemukan).';
                } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $errorMessage .= ' Data duplikat terdeteksi.';
                }

                $_SESSION['error'] = $errorMessage;

                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                $this->redirect('laporan/create');
            } catch (Exception $e) {
                error_log("Error creating laporan: " . get_class($e));
                $_SESSION['error'] = 'Terjadi kesalahan saat menyimpan laporan. Silakan coba lagi.';
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $this->redirect('laporan/create');
            }
        }

        $data_opt = $this->optModel->all();

        $data = [
            'title' => 'Buat Laporan Baru',
            'data_opt' => $data_opt
        ];

        $this->view('laporan/create', $data);
    }

    public function edit($id) {
        $this->checkRole(
            ['admin', 'operator', 'petugas'],
            'Anda tidak memiliki akses untuk mengedit laporan hama. Hanya akun dengan level Admin, Operator, dan Petugas yang dapat mengedit laporan.'
        );

        $laporan = $this->laporanModel->find($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan');
        }

        // Creator, admin, and operator can edit laporan hama.
        $user = $this->getCurrentUser();
        if ($laporan['user_id'] != $user['id'] && !in_array($user['role'], ['admin', 'operator'])) {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit laporan ini';
            $this->redirect('laporan');
            return;
        }

        // Petugas hanya dapat mengedit laporan dengan status Draf atau Ditolak
        if ($user['role'] === 'petugas' && !in_array(strtolower($laporan['status'] ?? ''), ['draf', 'ditolak', 'draft', 'rejected'], true)) {
            $_SESSION['error'] = 'Petugas hanya dapat mengedit laporan dengan status Draf atau Ditolak.';
            $this->redirect('laporan');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();

            // Simpan old input: isian tidak boleh hilang saat validasi/redirect gagal
            // (dibersihkan setelah laporan sukses diupdate — lihat bawah).
            $_SESSION['form_data'] = $_POST;

            // Pertahankan wilayah existing bila dikirim kosong (anti-hilang
            // tak sengaja); validasi relasi tetap berjalan atas nilai efektif.
            foreach (['kabupaten_id', 'kecamatan_id', 'desa_id'] as $wilayahField) {
                $val = $_POST[$wilayahField] ?? '';
                if ($val === '' || $val === 'unknown' || $val === null) {
                    if (!empty($laporan[$wilayahField])) {
                        $_POST[$wilayahField] = (string) $laporan[$wilayahField];
                    }
                }
            }

            $editMethod = $_POST['metode_pengukuran'] ?? 'absolut';
            $editPercentage = $_POST['persentase_serangan'] ?? null;
            $editObservedArea = $_POST['luas_areal_diamati'] ?? null;
            $postData = [
                'master_opt_id' => $_POST['master_opt_id'],
                'tanggal' => $_POST['tanggal'],
                'lokasi' => $_POST['alamat_lengkap'] ?? $_POST['lokasi'] ?? '',
                'kabupaten_id' => $_POST['kabupaten_id'] !== 'unknown' ? ($_POST['kabupaten_id'] ?? null) : null,
                'kecamatan_id' => $_POST['kecamatan_id'] !== 'unknown' ? ($_POST['kecamatan_id'] ?? null) : null,
                'desa_id' => $_POST['desa_id'] !== 'unknown' ? ($_POST['desa_id'] ?? null) : null,
                'alamat_lengkap' => $_POST['alamat_lengkap'] ?? null,
                'latitude' => isset($_POST['latitude']) && $_POST['latitude'] !== '' && is_numeric($_POST['latitude']) ? (float) $_POST['latitude'] : null,
                'longitude' => isset($_POST['longitude']) && $_POST['longitude'] !== '' && is_numeric($_POST['longitude']) ? (float) $_POST['longitude'] : null,
                'tingkat_keparahan' => $_POST['tingkat_keparahan'],
                'populasi' => $_POST['populasi'] ?? 0,
                'luas_serangan' => $_POST['luas_serangan'] ?? 0,
                'metode_pengukuran' => $editMethod,
                'persentase_serangan' => $editPercentage !== null && $editPercentage !== '' ? (float) $editPercentage : null,
                'luas_areal_diamati' => $editObservedArea !== null && $editObservedArea !== '' ? (float) $editObservedArea : null,
                'luas_serangan_estimasi' => $editMethod === 'persentase' && is_numeric($editPercentage) && is_numeric($editObservedArea)
                    ? round((float) $editObservedArea * (float) $editPercentage / 100, 2) : null,
                'catatan' => $_POST['catatan'] ?? '',
                'status' => in_array(($laporan['status'] ?? ''), ['Draf', 'Ditolak']) ? 'Submitted' : ($laporan['status'] ?? 'Submitted')
            ];
            if (!in_array($editMethod, ['absolut', 'persentase'], true)
                || ($editMethod === 'persentase' && ($postData['persentase_serangan'] === null
                    || $postData['persentase_serangan'] < 0 || $postData['persentase_serangan'] > 100))) {
                $_SESSION['error'] = 'Metode atau persentase serangan tidak valid.';
                $this->redirect('laporan/edit/' . $id);
            }

            // Role-based validation for location fields
            $userRole = $user['role'];
            if ($userRole === 'petugas') {
                // Validasi khusus untuk role Petugas - field lokasi wajib
                $errors = [];

                if (empty($postData['kabupaten_id'])) {
                    $errors[] = 'Kabupaten wajib dipilih';
                }

                if (empty($postData['kecamatan_id'])) {
                    $errors[] = 'Kecamatan wajib dipilih';
                }

                if (empty($postData['desa_id'])) {
                    $errors[] = 'Desa wajib dipilih';
                }

                if (!empty($errors)) {
                    $_SESSION['error'] = implode('<br>', $errors);
                    $this->redirect('laporan/edit/' . $id);
                }
            } else {
                // For admin/operator, location fields are optional but if provided must be complete
                if (!empty($postData['kabupaten_id']) || !empty($postData['kecamatan_id']) || !empty($postData['desa_id']) || !empty($postData['alamat_lengkap'])) {
                    if (empty($postData['kabupaten_id']) || empty($postData['kecamatan_id']) || empty($postData['desa_id']) || empty($postData['alamat_lengkap'])) {
                        $_SESSION['error'] = 'Jika mengisi data lokasi, semua field lokasi (kabupaten, kecamatan, desa, alamat lengkap) harus diisi lengkap';
                        $this->redirect('laporan/edit/' . $id);
                    }
                }
            }
            $kabModel = $this->model('MasterKabupaten');
            $kecModel = $this->model('MasterKecamatan');
            $desaModel = $this->model('MasterDesa');
            // findByIdOrKode supports DB id, BPS kode (3509), short kode (09), dan format lain
            $kab = $kabModel->findByIdOrKode($postData['kabupaten_id']);
            $kec = $kecModel->findById($postData['kecamatan_id']);
            $des = $desaModel->findById($postData['desa_id']);
            if (!$kab || !$kec || !$des || $kec['kabupaten_id'] != $kab['id'] || $des['kecamatan_id'] != $kec['id']) {
                $_SESSION['error'] = 'Relasi wilayah tidak valid';
                $this->redirect('laporan/edit/' . $id);
            }
            // Normalize ke id asli di database (form bisa mengirim format padded seperti '09')
            $postData['kabupaten_id'] = $kab['id'];

            if (!empty($postData['latitude']) && !empty($postData['longitude'])) {
                require_once ROOT_PATH . '/app/helpers/GeoValidator.php';
                $geoRes = GeoValidator::validateJemberCoordinates((float)$postData['latitude'], (float)$postData['longitude']);
                if (!$geoRes['valid']) {
                    $_SESSION['error'] = $geoRes['message'];
                    $this->redirect('laporan/edit/' . $id);
                }
            }

            // Handle file upload with automatic compression
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';

                $uploadDir = ROOT_PATH . '/public/uploads/laporan/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $file = $_FILES['foto'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                // Validate file type using finfo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedTypes)) {
                    $_SESSION['error'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                    $this->redirect('laporan/edit/' . $id);
                }

                // Validate file extension
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    $_SESSION['error'] = 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diizinkan.';
                    $this->redirect('laporan/edit/' . $id);
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
                        $postData['foto_url'] = 'public/uploads/laporan/' . $fileName;

                        // Delete old photo if exists
                        if (!empty($laporan['foto_url']) && file_exists(ROOT_PATH . '/' . $laporan['foto_url'])) {
                            unlink(ROOT_PATH . '/' . $laporan['foto_url']);
                        }

                        // Set info message about compression
                        if ($result['compressed']) {
                            $originalSize = ImageCompressor::formatFileSize($result['original_size']);
                            $finalSize = ImageCompressor::formatFileSize($result['final_size']);
                            $_SESSION['info'] = "Foto berhasil dikompresi dari {$originalSize} menjadi {$finalSize} (pengurangan {$result['reduction_percent']}%)";
                        }
                    } else {
                        $_SESSION['error'] = 'Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error');
                        $this->redirect('laporan/edit/' . $id);
                    }
                } else {
                    // File size is acceptable, just move it
                    if (move_uploaded_file($tempPath, $targetPath)) {
                        $postData['foto_url'] = 'public/uploads/laporan/' . $fileName;

                        // Delete old photo if exists
                        if (!empty($laporan['foto_url']) && file_exists(ROOT_PATH . '/' . $laporan['foto_url'])) {
                            unlink(ROOT_PATH . '/' . $laporan['foto_url']);
                        }
                    } else {
                        $_SESSION['error'] = 'Gagal mengupload file.';
                        $this->redirect('laporan/edit/' . $id);
                    }
                }
            }

            if (isset($_FILES['video'])) {
                require_once ROOT_PATH . '/app/helpers/VideoUploader.php';
                $videoResult = (new VideoUploader())->upload($_FILES['video']);
                if (!$videoResult['success']) {
                    $_SESSION['error'] = $videoResult['error'];
                    $this->redirect('laporan/edit/' . $id);
                }
                if (!empty($videoResult['path'])) {
                    $postData['video_url'] = $videoResult['path'];
                }
            }

            $this->laporanModel->update($id, $postData);

            if (($laporan['status'] ?? '') !== $postData['status']) {
                $this->logStatusHistory($id, $laporan['status'] ?? null, $postData['status'], $user['id'], 'Laporan diaktifkan setelah diedit');
            }

            $_SESSION['success'] = 'Laporan berhasil diupdate';
            unset($_SESSION['form_data']);
            $this->redirect('laporan');
        }

        $data_opt = $this->optModel->all();

        $data = [
            'title' => 'Edit Laporan',
            'laporan' => $laporan,
            'data_opt' => $data_opt
        ];

        $this->view('laporan/edit', $data);
    }

    public function detail($id) {
        $this->checkAuth();

        $sql = "SELECT
                    lh.*,
                    mo.kode_opt,
                    mo.nama_opt,
                    mo.jenis,
                    mo.etl_acuan,
                    mo.deskripsi AS rekomendasi,
                    u.nama_lengkap as pelapor_nama,
                    u.email as pelapor_email,
                    v.nama_lengkap as verifikator_nama
                FROM laporan_hama lh
                LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
                LEFT JOIN users u ON lh.user_id = u.id
                LEFT JOIN users v ON lh.verified_by = v.id
                WHERE lh.id = ? AND lh.deleted_at IS NULL";

        $result = $this->laporanModel->query($sql, [$id]);
        $laporan = $result[0] ?? null;

        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan');
        }

        // Fetch status history
        $statusHistory = $this->getStatusHistory($id);

        $data = [
            'title' => 'Detail Laporan',
            'laporan' => $laporan,
            'statusHistory' => $statusHistory
        ];

        $this->view('laporan/view', $data);
    }

    private function getStatusHistory($laporanId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT sh.*, u.nama_lengkap as changed_by_name
                FROM laporan_status_history sh
                LEFT JOIN users u ON sh.changed_by = u.id
                WHERE sh.laporan_id = ?
                ORDER BY sh.created_at DESC
            ");
            $stmt->execute([$laporanId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table might not exist yet
            error_log("Failed to get status history: " . $e->getMessage());
            return [];
        }
    }

    public function verify($id) {
        $this->checkRole(['admin', 'operator']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle AJAX request
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                // Validate CSRF token for AJAX
                $input = json_decode(file_get_contents('php://input'), true);
                if (empty($input['csrf_token']) || !$this->validateCsrfTokenAjax($input['csrf_token'])) {
                    $this->json(['success' => false, 'message' => 'Token CSRF tidak valid'], 403);
                }

                // Get the current report
                $laporan = $this->laporanModel->find($id);
                if (!$laporan) {
                    $this->json(['success' => false, 'message' => 'Laporan tidak ditemukan'], 404);
                }

                // Validate status transition: only Submitted can be verified/rejected
                if ($laporan['status'] !== 'Submitted') {
                    $this->json(['success' => false, 'message' => 'Hanya laporan dengan status "Submitted" yang dapat diverifikasi'], 400);
                }

                $user = $this->getCurrentUser();
                $status = $input['status'] ?? '';
                $catatan = $input['catatan_verifikasi'] ?? '';

                // Validate status value
                if (!in_array($status, ['Diverifikasi', 'Ditolak'])) {
                    $this->json(['success' => false, 'message' => 'Status verifikasi tidak valid'], 400);
                }

                // Require comment for rejection
                if ($status === 'Ditolak' && empty(trim($catatan))) {
                    $this->json(['success' => false, 'message' => 'Alasan penolakan wajib diisi'], 400);
                }

                try {
                    // Perform verification
                    $this->laporanModel->verify($id, $user['id'], $status, $catatan);

                    // Log to status history table
                    $this->logStatusHistory($id, 'Submitted', $status, $user['id'], $catatan);

                    // Log to activity_log
                    $db = Database::getInstance()->getConnection();
                    $action = $status === 'Diverifikasi' ? 'VerifyReport' : 'RejectReport';
                    $description = $status === 'Diverifikasi'
                        ? "Laporan #{$id} diverifikasi"
                        : "Laporan #{$id} ditolak: {$catatan}";
                    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $action, 'laporan_hama', $id, $description, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

                    // Create notification for the report creator
                    if ($laporan['user_id']) {
                        $notifTitle = $status === 'Diverifikasi'
                            ? 'Laporan Anda Telah Diverifikasi'
                            : 'Laporan Anda Ditolak';
                        $notifMessage = $status === 'Diverifikasi'
                            ? "Laporan #{$id} telah diverifikasi oleh {$user['nama_lengkap']}"
                            : "Laporan #{$id} ditolak oleh {$user['nama_lengkap']}. Alasan: {$catatan}";
                        $notifType = $status === 'Diverifikasi' ? 'success' : 'danger';

                        $this->createNotification($laporan['user_id'], $notifTitle, $notifMessage, $notifType);
                    }

                    $this->clearDashboardCache();

                    $successMsg = $status === 'Diverifikasi'
                        ? 'Laporan berhasil diverifikasi'
                        : 'Laporan berhasil ditolak';

                    $this->json(['success' => true, 'message' => $successMsg]);

                } catch (Exception $e) {
                    error_log("Verification error: " . $e->getMessage());
                    $this->json(['success' => false, 'message' => 'Terjadi kesalahan saat memproses laporan'], 500);
                }
            } else {
                // Handle traditional form submission
                $this->validateCsrfToken();

                // Get the current report
                $laporan = $this->laporanModel->find($id);
                if (!$laporan) {
                    $_SESSION['error'] = 'Laporan tidak ditemukan';
                    $this->redirect('laporan');
                }

                // Validate status transition: only Submitted can be verified/rejected
                if ($laporan['status'] !== 'Submitted') {
                    $_SESSION['error'] = 'Hanya laporan dengan status "Submitted" yang dapat diverifikasi';
                    $this->redirect('laporan/detail/' . $id);
                }

                $user = $this->getCurrentUser();
                $status = $_POST['status'];
                $catatan = $_POST['catatan_verifikasi'] ?? '';

                // Validate status value
                if (!in_array($status, ['Diverifikasi', 'Ditolak'])) {
                    $_SESSION['error'] = 'Status verifikasi tidak valid';
                    $this->redirect('laporan/detail/' . $id);
                }

                // Require comment for rejection
                if ($status === 'Ditolak' && empty(trim($catatan))) {
                    $_SESSION['error'] = 'Alasan penolakan wajib diisi';
                    $this->redirect('laporan/detail/' . $id);
                }

                // Perform verification
                $this->laporanModel->verify($id, $user['id'], $status, $catatan);

                // Log to status history table
                $this->logStatusHistory($id, 'Submitted', $status, $user['id'], $catatan);

                // Log to activity_log
                $db = Database::getInstance()->getConnection();
                $action = $status === 'Diverifikasi' ? 'VerifyReport' : 'RejectReport';
                $description = $status === 'Diverifikasi'
                    ? "Laporan #{$id} diverifikasi"
                    : "Laporan #{$id} ditolak: {$catatan}";
                $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $action, 'laporan_hama', $id, $description, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

                // Create notification for the report creator
                if ($laporan['user_id']) {
                    $notifTitle = $status === 'Diverifikasi'
                        ? 'Laporan Anda Telah Diverifikasi'
                        : 'Laporan Anda Ditolak';
                    $notifMessage = $status === 'Diverifikasi'
                        ? "Laporan #{$id} telah diverifikasi oleh {$user['nama_lengkap']}"
                        : "Laporan #{$id} ditolak oleh {$user['nama_lengkap']}. Alasan: {$catatan}";
                    $notifType = $status === 'Diverifikasi' ? 'success' : 'danger';

                    $this->createNotification($laporan['user_id'], $notifTitle, $notifMessage, $notifType);
                }

                $this->clearDashboardCache();

                $successMsg = $status === 'Diverifikasi'
                    ? 'Laporan berhasil diverifikasi'
                    : 'Laporan berhasil ditolak';
                $_SESSION['success'] = $successMsg;

                // Check if redirect_to parameter is set (for AJAX calls from index page)
                $redirectTo = $_POST['redirect_to'] ?? 'detail';
                if ($redirectTo === 'index') {
                    $this->redirect('laporan?status=Submitted');
                } else {
                    $this->redirect('laporan/detail/' . $id);
                }
            }
        }
    }

    private function logStatusHistory($laporanId, $oldStatus, $newStatus, $userId, $komentar = '') {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO laporan_status_history (laporan_id, old_status, new_status, changed_by, komentar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$laporanId, $oldStatus, $newStatus, $userId, $komentar]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to log status history: " . $e->getMessage());
            return false;
        }
    }

    private function clearDashboardCache(): void {
        try {
            CacheManager::getInstance()->clearPrefix('dashboard:');
            CacheManager::getInstance()->clearPrefix('dash_summary_');
            if (class_exists('DashboardDataAggregator')) {
                (new DashboardDataAggregator())->clearCache('hama');
            }
        } catch (Throwable $e) {
            error_log('Failed to clear dashboard cache: ' . $e->getMessage());
        }
    }

    public function delete($id) {
        $this->checkAuth();
        $this->requireStateChangingRequest(['POST', 'DELETE']);

        $laporan = $this->laporanModel->find($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan');
            return;
        }

        $role = $_SESSION['role'] ?? '';
        if ($role !== 'admin') {
            $status = $laporan['status'] ?? '';
            $ownerId = (int) ($laporan['user_id'] ?? 0);
            $currentId = (int) ($_SESSION['user_id'] ?? 0);
            if ($role !== 'petugas' || !in_array($status, ['Draf', 'Ditolak'], true) || $ownerId !== $currentId) {
                $_SESSION['error'] = 'Anda hanya dapat menghapus laporan Draf/Ditolak milik Anda.';
                $this->redirect('laporan');
                return;
            }
        }

        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $deleted = $this->laporanModel->softDelete((int) $id, (int) $_SESSION['user_id']);
            if (!$deleted) {
                $db->rollBack();
                $_SESSION['info'] = 'Laporan sudah dipindahkan ke recycle bin';
                $this->redirect('laporan');
            }
            $this->logRecycleBinActivity('soft_delete', (int) $id, 'Laporan hama dipindahkan ke recycle bin');
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('LaporanController::delete failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Laporan gagal dipindahkan ke recycle bin.';
            $this->redirect('laporan');
        }
        $this->clearDashboardCache();
        $_SESSION['success'] = 'Laporan dipindahkan ke recycle bin';
        $this->redirect('laporan');
    }

    public function archive($id) {
        $this->checkRole(
            ['admin', 'operator'],
            'Anda tidak memiliki akses untuk mengarsipkan laporan hama.'
        );

        $this->requireStateChangingRequest(['POST']);

        $laporan = $this->laporanModel->find($id);
        if (!$laporan) {
            $_SESSION['error'] = 'Laporan tidak ditemukan';
            $this->redirect('laporan');
        }

        if (($laporan['status'] ?? '') === 'Diarsipkan') {
            $_SESSION['info'] = 'Laporan sudah diarsipkan';
            $this->redirect('laporan');
        }

        try {
            $this->laporanModel->archive((int)$id);
            $this->logStatusHistory($id, $laporan['status'] ?? null, 'Diarsipkan', $this->getCurrentUser()['id'], 'Laporan diarsipkan');
            $this->clearDashboardCache();
            $_SESSION['success'] = 'Laporan berhasil diarsipkan';
        } catch (Exception $e) {
            error_log('Failed to archive laporan hama: ' . $e->getMessage());
            $_SESSION['error'] = 'Gagal mengarsipkan laporan. Pastikan migration status arsip sudah dijalankan.';
        }

        $this->redirect('laporan');
    }

    public function bulkDelete() {
        $this->checkRole(['admin']);

        // Handle AJAX request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');

            // Validate CSRF token
            $token = $_POST['csrf_token'] ?? '';
            if (!$this->validateCsrfTokenAjax($token)) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid']);
                exit;
            }

            $ids = $_POST['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'Tidak ada data yang dipilih']);
                exit;
            }

            // Validate all IDs are numeric
            foreach ($ids as $id) {
                if (!is_numeric($id)) {
                    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
                    exit;
                }
            }

            try {
                $db = Database::getInstance()->getConnection();
                $db->beginTransaction();
                $deletedCount = $this->laporanModel->softDeleteMany(
                    $ids,
                    (int) $_SESSION['user_id']
                );
                if ($deletedCount > 0) {
                    $this->logRecycleBinActivity(
                        'bulk_soft_delete',
                        null,
                        "{$deletedCount} laporan hama dipindahkan ke recycle bin"
                    );
                }
                $db->commit();
                if ($deletedCount > 0) {
                    $this->clearDashboardCache();
                }

                echo json_encode([
                    'success' => true,
                    'message' => "{$deletedCount} laporan dipindahkan ke recycle bin",
                    'count' => $deletedCount
                ]);
            } catch (Throwable $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('LaporanController::bulkDelete failed: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Gagal memindahkan laporan ke recycle bin']);
            }
            exit;
        }
    }

    private function validateCsrfTokenAjax($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
            'laporan_hama',
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    }

    private function createNotification($userId, $title, $message, $type = 'info') {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, body, type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $title, $message, $type]);
    }

    private function notifyAdminsOperatorsNewSubmission($laporanId, $creator) {
        try {
            $db = Database::getInstance()->getConnection();
            // Get all admin and operator users
            $stmt = $db->query("SELECT id FROM users WHERE role IN ('admin', 'operator') AND aktif = 1");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                $this->createNotification(
                    $admin['id'],
                    'Laporan Hama Baru',
                    "Laporan #{$laporanId} telah dibuat oleh {$creator['nama_lengkap']} dan langsung aktif.",
                    'info'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to notify admins/operators: " . $e->getMessage());
        }
    }

    private function notifyAdminsOperatorsResubmission($laporanId, $creator, $previousRejectionReason) {
        try {
            $db = Database::getInstance()->getConnection();
            // Get all admin and operator users
            $stmt = $db->query("SELECT id FROM users WHERE role IN ('admin', 'operator') AND aktif = 1");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                $this->createNotification(
                    $admin['id'],
                    'Laporan Hama Diperbarui',
                    "Laporan #{$laporanId} telah diperbarui oleh {$creator['nama_lengkap']}.",
                    'info'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to notify admins/operators about resubmission: " . $e->getMessage());
        }
    }

    private function laporanPhotoUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_NO_FILE => 'Foto laporan wajib disertakan sebelum laporan dapat disimpan.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'Ukuran foto melebihi batas upload server. Pilih foto yang lebih kecil.',
            UPLOAD_ERR_PARTIAL =>
                'Upload foto tidak lengkap. Periksa koneksi lalu pilih ulang foto.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'Penyimpanan sementara foto tidak tersedia di server.',
            UPLOAD_ERR_CANT_WRITE =>
                'Server gagal menulis file foto. Silakan coba lagi.',
            UPLOAD_ERR_EXTENSION =>
                'Upload foto diblokir oleh konfigurasi server.',
            default => 'Upload foto gagal. Silakan pilih ulang foto dan coba lagi.',
        };
    }

    /**
     * Validate laporan data based on user role
     * Different validation rules for admin, operator, and petugas
     */
    private function validateLaporanData($data, $userRole) {
        $errors = [];

        // Common validations for all roles
        if (empty($data['master_opt_id']) && empty($data['usulan_opt_id'])) {
            $errors[] = 'OPT wajib dipilih atau nama hama baru wajib diusulkan';
        }

        if (empty($data['tanggal'])) {
            $errors[] = 'Tanggal pelaporan wajib diisi';
        } else {
            // Validate date format
            $date = DateTime::createFromFormat('Y-m-d', $data['tanggal']);
            if (!$date || $date->format('Y-m-d') !== $data['tanggal']) {
                $errors[] = 'Format tanggal tidak valid';
            }
            // Check if date is not in the future
            if ($date > new DateTime()) {
                $errors[] = 'Tanggal pelaporan tidak boleh di masa depan';
            }
        }

        if (empty($data['tingkat_keparahan'])) {
            $errors[] = 'Tingkat keparahan wajib dipilih';
        } else {
            $allowedSeverity = ['Ringan', 'Sedang', 'Berat'];
            if (!in_array($data['tingkat_keparahan'], $allowedSeverity)) {
                $errors[] = 'Tingkat keparahan tidak valid';
            }
        }

        // Role-specific validations
        if ($userRole === 'petugas') {
            // Petugas: Validasi lebih ketat, semua field lokasi wajib
            if (empty($data['kabupaten_id']) || empty($data['kecamatan_id']) || empty($data['desa_id'])) {
                $errors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi untuk petugas';
            }

            if (empty($data['alamat_lengkap']) || strlen(trim($data['alamat_lengkap'])) < 10) {
                $errors[] = 'Alamat lengkap wajib diisi minimal 10 karakter untuk petugas';
            }

            // Petugas hanya dapat membuat dengan status Submitted
            if (!in_array($data['status'], ['Submitted'])) {
                $errors[] = 'Status tidak valid untuk petugas. Hanya Submitted yang diizinkan.';
            }

            // Petugas wajib mengisi populasi jika tingkat keparahan Berat
            if ($data['tingkat_keparahan'] === 'Berat' && empty($data['populasi'])) {
                $errors[] = 'Populasi wajib diisi untuk tingkat keparahan Berat';
            }

        } elseif ($userRole === 'operator') {
            // Operator: Validasi standar
            if (empty($data['kabupaten_id']) || empty($data['kecamatan_id']) || empty($data['desa_id'])) {
                $errors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi';
            }

            if (empty($data['alamat_lengkap']) || strlen(trim($data['alamat_lengkap'])) < 5) {
                $errors[] = 'Alamat lengkap wajib diisi minimal 5 karakter';
            }

            // Operator membuat laporan aktif tanpa approval.
            if (!in_array($data['status'], ['Submitted'])) {
                $errors[] = 'Status tidak valid untuk operator';
            }

        } elseif ($userRole === 'admin') {
            // Admin: Validasi lebih fleksibel
            // Admin dapat membuat laporan dengan lokasi tidak lengkap (untuk data entry)
            if (empty($data['alamat_lengkap'])) {
                $errors[] = 'Alamat lengkap wajib diisi';
            }

            // Admin membuat laporan aktif tanpa approval.
            $allowedStatuses = ['Submitted'];
            if (!in_array($data['status'], $allowedStatuses)) {
                $errors[] = 'Status tidak valid';
            }
        }

        // Validate numeric fields
        if (isset($data['populasi']) && $data['populasi'] < 0) {
            $errors[] = 'Populasi tidak boleh negatif';
        }

        if (isset($data['luas_serangan']) && $data['luas_serangan'] < 0) {
            $errors[] = 'Luas serangan tidak boleh negatif';
        }

        if (!in_array($data['metode_pengukuran'] ?? '', ['absolut', 'persentase'], true)) {
            $errors[] = 'Metode pengukuran serangan tidak valid';
        } elseif ($data['metode_pengukuran'] === 'persentase') {
            $persentase = $data['persentase_serangan'];
            if ($persentase === null || $persentase < 0 || $persentase > 100) {
                $errors[] = 'Persentase serangan harus antara 0 dan 100';
            }
        }

        // Validate luas serangan tidak boleh melebihi populasi (boleh sama dengan)
        if (($data['metode_pengukuran'] ?? 'absolut') === 'absolut' && isset($data['populasi']) && isset($data['luas_serangan'])) {
            $populasi = (float)$data['populasi'];
            $luasSerangan = (float)$data['luas_serangan'];

            // Only validate if both values are provided and greater than 0
            // Luas serangan boleh sama dengan populasi (<=), tidak boleh lebih besar (>)
            if ($populasi > 0 && $luasSerangan > 0 && $luasSerangan > $populasi) {
                $errors[] = 'Luas Serangan (' . number_format($luasSerangan, 2) . ' Ha) tidak boleh melebihi Populasi/Intensitas (' . number_format($populasi, 2) . ' Ha). Nilai maksimal yang diizinkan: ' . number_format($populasi, 2) . ' Ha';
            }
        }

        // Validate GPS coordinates if provided
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $lat = (float)$data['latitude'];
            $lon = (float)$data['longitude'];

            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Latitude harus antara -90 dan 90';
            }

            if ($lon < -180 || $lon > 180) {
                $errors[] = 'Longitude harus antara -180 dan 180';
            }

            // Check Jember boundaries and land geofencing
            require_once ROOT_PATH . '/app/helpers/GeoValidator.php';
            $geoValidation = GeoValidator::validateJemberCoordinates($lat, $lon);
            if (!$geoValidation['valid']) {
                $errors[] = $geoValidation['message'];
            }
        }

        return $errors;
    }

    /**
     * API: AJAX pagination fetch for Daftar Laporan page
     * GET /laporan/fetch
     *
     * Query params:
     *   page       Page number (default: 1)
     *   per_page   Rows per page: 10, 20, 50, 100, or all (default: 10)
     *   status     Filter by status (optional)
     *   search    Search query (optional)
     *   sort_col  Sort column (optional)
     *   sort_dir  Sort direction ASC|DESC (optional)
     */
    public function fetch() {
        $this->checkAuth();
        header('Content-Type: application/json');

        error_log("[LaporanController::fetch] Request received - GET: " . json_encode($_GET));

        try {
            $page    = max(1, intval($_GET['page'] ?? 1));
            $perPage = $_GET['per_page'] ?? '10';
            $search  = trim($_GET['search'] ?? '');
            $status  = trim($_GET['status'] ?? '');
            $sortCol = trim($_GET['sort_col'] ?? 'tanggal');
            $sortCol = preg_replace('/^[a-z_]+\./i', '', $sortCol);
            $allowedSortCols = ['id', 'tanggal', 'tingkat_keparahan', 'status', 'created_at', 'populasi', 'luas_serangan'];
            if (!in_array($sortCol, $allowedSortCols, true)) {
                $sortCol = 'tanggal';
            }
            $sortDir = trim($_GET['sort_dir'] ?? 'desc');

            $statusMap = [
                'draft' => 'Draf',
                'draf' => 'Draf',
                'submitted' => 'Submitted',
                'diverifikasi' => 'Diverifikasi',
                'verified' => 'Diverifikasi',
                'ditolak' => 'Ditolak',
                'rejected' => 'Ditolak',
                'diarsipkan' => 'Diarsipkan',
                'archived' => 'Diarsipkan',
            ];
            if ($status !== '' && isset($statusMap[strtolower($status)])) {
                $status = $statusMap[strtolower($status)];
            }

            error_log("[LaporanController::fetch] Parsed params: page=$page, perPage=$perPage, search='$search', status='$status', sortCol='$sortCol', sortDir='$sortDir'");

            $perPage = match (strtolower($perPage)) {
                '20'   => 20,
                '50'   => 50,
                '100'  => 100,
                'all'  => -1,
                default => 10,
            };

            $user = $this->getCurrentUser();
            error_log("[LaporanController::fetch] Current user: id={$user['id']}, role={$user['role']}");

            $userId = $user['role'] === 'petugas' ? $user['id'] : null;
            if ($userId) {
                error_log("[LaporanController::fetch] Petugas user - filtering by user_id=$userId");
            }

            $filters = [
                'search'    => $search,
                'status'    => $status,
                'order_col' => 'lh.' . $sortCol,
                'order_dir' => in_array(strtoupper($sortDir), ['ASC', 'DESC']) ? strtoupper($sortDir) : 'DESC',
            ];

            error_log("[LaporanController::fetch] Calling model->fetchPaginated");
            $result = $this->laporanModel->fetchPaginated($filters, $page, $perPage, $userId);

            error_log("[LaporanController::fetch] Model returned: total={$result['total']}, rows=" . count($result['rows']));

            echo json_encode([
                'success' => true,
                'data'    => $result,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            error_log("[LaporanController::fetch] Exception: " . $e->getMessage());
            error_log("[LaporanController::fetch] Trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
                'data'    => ['rows' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0, 'statusCounts' => []]
            ]);
            exit;
        }
    }
}
