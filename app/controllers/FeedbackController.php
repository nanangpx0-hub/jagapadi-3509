<?php
declare(strict_types=1);

/**
 * Feedback Controller
 *
 * Aturan akses:
 *  - Petugas : hanya dapat melihat & mengirim masukan MILIK SENDIRI.
 *              Default index menampilkan feedback milik sendiri.
 *              Tidak dapat mengakses halaman rekap admin (adminSummary, report).
 *  - Admin   : dapat melihat SEMUA feedback, mengubah status, menghapus,
 *              dan mengakses halaman rekap eksklusif (adminSummary, report).
 *
 * @version V.1.4.0
 */
class FeedbackController extends Controller
{
    private const PETUGAS_ROLE = ['petugas'];
    private const DETAIL_ROLES = ['admin', 'petugas'];
    private const VALID_STATUS   = ['diterima', 'dalam_proses', 'selesai', 'ditolak'];
    private const VALID_JENIS    = ['bug', 'fitur_baru', 'peningkatan'];
    private const VALID_PRIORITAS = ['rendah', 'medium', 'tinggi'];

    private $feedbackModel;

    public function __construct()
    {
        $this->feedbackModel = $this->model('Feedback');
    }

    // =========================================================
    // PUBLIC ROUTES
    // =========================================================

    /**
     * Daftar feedback.
     *  - Petugas : hanya melihat milik sendiri (ownership enforced di query).
     *  - Admin   : melihat semua + tombol ke Rekap Admin.
     * Route: GET /feedback
     */
    public function index(): void
    {
        $this->checkRole(self::PETUGAS_ROLE);

        $user = $this->getCurrentUser();

        // Bangun filter
        $filters = [
            'jenis'     => $_GET['jenis']     ?? '',
            'status'    => $_GET['status']    ?? '',
            'prioritas' => $_GET['prioritas'] ?? '',
            'search'    => $_GET['search']    ?? '',
        ];

        // Petugas: selalu filter ke user_id sendiri — tidak bisa dihapus lewat URL
        $filters['user_id'] = (int) $user['id'];

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 15;
        $result = $this->feedbackModel->getAll($filters, $page, $limit);

        // Tandai apakah user sudah vote tiap item
        foreach ($result['data'] as &$item) {
            $item['has_voted'] = $this->feedbackModel->hasUserVoted(
                (int) $item['id'],
                (int) $user['id']
            );
        }
        unset($item);

        // Statistik untuk kartu dashboard
        $stats = $this->feedbackModel->getDashboardStatsByUser((int) $user['id']);

        $this->view('feedback/index', [
            'title'          => 'Masukan & Saran',
            'pageTitle'      => 'Masukan & Saran',
            'feedback'       => $result['data'],
            'pagination'     => [
                'total'      => $result['total'],
                'page'       => $result['page'],
                'limit'      => $result['limit'],
                'totalPages' => $result['totalPages'],
            ],
            'filters'        => $filters,
            'stats'          => $stats,
            'popularFeedback' => [],
            'isAdmin'        => false,
            'currentUser'    => $user,
        ]);
    }

    /**
     * Form kirim masukan baru.
     *  - Semua role yang diizinkan (petugas & admin) bisa kirim.
     * Route: GET|POST /feedback/create
     */
    public function create(): void
    {
        $this->checkRole(self::PETUGAS_ROLE);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCreatePost();
            return;
        }

        $formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        $this->view('feedback/create', [
            'title'     => 'Kirim Masukan Baru',
            'pageTitle' => 'Kirim Masukan Baru',
            'formData'  => $formData,
        ]);
    }

    /**
     * Detail satu feedback.
     *  - Petugas hanya bisa akses milik sendiri.
     *  - Admin bisa akses semua.
     * Route: GET /feedback/detail/{id}
     */
    public function detail(int $id): void
    {
        $this->checkRole(self::DETAIL_ROLES);

        $feedback = $this->feedbackModel->getById($id);

        if (!$feedback) {
            $_SESSION['error'] = 'Feedback tidak ditemukan.';
            $this->redirect('feedback');
            return;
        }

        $user    = $this->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';

        // Petugas tidak boleh akses feedback orang lain
        if (!$isAdmin && (int) $feedback['user_id'] !== (int) $user['id']) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke masukan ini.';
            $this->redirect('feedback');
            return;
        }

        $statusHistory = $this->feedbackModel->getStatusHistory($id);
        $hasVoted      = $this->feedbackModel->hasUserVoted($id, (int) $user['id']);

        $voters = [];
        if ($isAdmin) {
            $voters = $this->feedbackModel->getVoters($id);
        }

        $this->view('feedback/detail', [
            'feedback'      => $feedback,
            'statusHistory' => $statusHistory,
            'hasVoted'      => $hasVoted,
            'voters'        => $voters,
            'isOwner'       => (int) $feedback['user_id'] === (int) $user['id'],
            'isAdmin'       => $isAdmin,
            'pageTitle'     => 'Detail Masukan',
        ]);
    }

    /**
     * Toggle vote.
     *  - Hanya petugas & admin yang diizinkan.
     *  - Tidak bisa vote milik sendiri.
     * Route: POST /feedback/vote/{id}
     */
    public function vote(int $id): void
    {
        $this->checkRole(self::PETUGAS_ROLE);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
            return;
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCsrfToken($token)) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            return;
        }

        $user    = $this->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';

        // IDOR guard: petugas hanya boleh berinteraksi dengan feedback milik
        // sendiri (satu-satunya yang tampil di index miliknya). Feedback milik
        // petugas lain hanya bisa dijangkau lewat URL yang dimanipulasi → tolak.
        if (!$isAdmin && (int) $feedback['user_id'] !== (int) $user['id']) {
            $this->json(['error' => 'Anda tidak memiliki akses ke masukan ini'], 403);
            return;
        }

        if ((int) $feedback['user_id'] === (int) $user['id']) {
            $this->json(['error' => 'Tidak dapat vote pada masukan sendiri'], 400);
            return;
        }

        $result = $this->feedbackModel->toggleVote($id, (int) $user['id']);

        $this->json([
            'success'    => true,
            'action'     => $result['action'],
            'vote_count' => $result['vote_count'],
            'message'    => $result['action'] === 'added'
                ? 'Vote berhasil ditambahkan'
                : 'Vote berhasil dihapus',
        ]);
    }

    // =========================================================
    // ADMIN-ONLY ROUTES
    // =========================================================

    /**
     * Rekap admin — total saran masuk + daftar aduan per petugas.
     * Eksklusif ADMIN.
     * Route: GET /feedback/adminSummary
     */
    public function adminSummary(): void
    {
        $this->checkRole(['admin']);

        $year   = (int) ($_GET['year']  ?? date('Y'));
        $month  = (int) ($_GET['month'] ?? 0);   // 0 = semua bulan
        $jenis  = $_GET['jenis']  ?? '';
        $status = $_GET['status'] ?? '';

        $filters = array_filter([
            'year'   => $year,
            'month'  => $month > 0 ? $month : null,
            'jenis'  => $jenis  !== '' ? $jenis  : null,
            'status' => $status !== '' ? $status : null,
        ]);

        // Data rekap per petugas
        $rekapPerPetugas = $this->feedbackModel->getRekapPerPetugas($filters);

        // Ringkasan keseluruhan
        $totalStats = $this->feedbackModel->getAdminSummaryStats($filters);

        // Daftar semua feedback untuk tabel aduan (paginated)
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;
        $allFilters = [
            'jenis'  => $jenis,
            'status' => $status,
            'search' => $_GET['search'] ?? '',
            'year'   => $year,
            'month'  => $month > 0 ? $month : null,
        ];
        $daftarFeedback = $this->feedbackModel->getAll($allFilters, $page, $limit);

        // Tandai vote untuk admin
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        foreach ($daftarFeedback['data'] as &$item) {
            $item['has_voted'] = $this->feedbackModel->hasUserVoted((int) $item['id'], $adminId);
        }
        unset($item);

        $this->view('feedback/admin_summary', [
            'title'           => 'Rekap Masukan & Saran',
            'pageTitle'       => 'Rekap Masukan & Saran',
            'rekapPerPetugas' => $rekapPerPetugas,
            'totalStats'      => $totalStats,
            'daftarFeedback'  => $daftarFeedback['data'],
            'pagination'      => [
                'total'      => $daftarFeedback['total'],
                'page'       => $daftarFeedback['page'],
                'limit'      => $daftarFeedback['limit'],
                'totalPages' => $daftarFeedback['totalPages'],
            ],
            'filters' => array_merge($allFilters, [
                'year'  => $year,
                'month' => $month,
            ]),
            'jenisLabels'  => [
                'bug'         => 'Bug',
                'fitur_baru'  => 'Fitur Baru',
                'peningkatan' => 'Peningkatan',
            ],
            'statusLabels' => [
                'diterima'     => 'Diterima',
                'dalam_proses' => 'Dalam Proses',
                'selesai'      => 'Selesai',
                'ditolak'      => 'Ditolak',
            ],
            'generatedAt'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Laporan bulanan (Admin only).
     * Route: GET /feedback/report
     */
    public function report(): void
    {
        $this->checkRole(['admin']);

        $year = (int) ($_GET['year'] ?? date('Y'));

        $monthlyStats = $this->feedbackModel->getMonthlyStats($year);
        $statusStats  = $this->feedbackModel->getCountByStatus();
        $typeStats    = $this->feedbackModel->getCountByType();
        $popularFeedback = $this->feedbackModel->getPopularFeedback(10);

        $this->view('feedback/report', [
            'title'          => 'Laporan Masukan Bulanan',
            'pageTitle'      => 'Laporan Masukan Bulanan',
            'year'           => $year,
            'monthlyStats'   => $monthlyStats,
            'statusStats'    => $statusStats,
            'typeStats'      => $typeStats,
            'popularFeedback' => $popularFeedback,
        ]);
    }

    /**
     * Update status feedback (Admin only).
     * Route: POST /feedback/updateStatus/{id}
     */
    public function updateStatus(int $id): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST', 'PATCH']);

        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            } else {
                $_SESSION['error'] = 'Feedback tidak ditemukan.';
                $this->redirect('feedback');
            }
            return;
        }

        $newStatus = $_POST['status'] ?? '';
        // Simpan mentah (hanya trim); HTML escaping dilakukan saat output di view.
        $notes     = trim($_POST['notes'] ?? '');

        if (!in_array($newStatus, self::VALID_STATUS, true)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Status tidak valid'], 400);
            } else {
                $_SESSION['error'] = 'Status tidak valid.';
                $this->redirect('feedback/detail/' . $id);
            }
            return;
        }

        $success = $this->feedbackModel->updateStatus(
            $id,
            $newStatus,
            (int) $_SESSION['user_id'],
            $notes
        );

        if ($success) {
            $this->notifyUserStatusChange($feedback, $newStatus, $notes);

            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => 'Status berhasil diperbarui']);
            } else {
                $_SESSION['success'] = 'Status masukan berhasil diperbarui.';
                $this->redirect('feedback/detail/' . $id);
            }
        } else {
            if ($this->isAjax()) {
                $this->json(['error' => 'Gagal memperbarui status'], 500);
            } else {
                $_SESSION['error'] = 'Gagal memperbarui status.';
                $this->redirect('feedback/detail/' . $id);
            }
        }
    }

    /**
     * Hapus feedback (Admin only).
     * Route: POST /feedback/delete/{id}
     */
    public function delete(int $id): void
    {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST', 'DELETE']);

        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            } else {
                $_SESSION['error'] = 'Feedback tidak ditemukan.';
                $this->redirect('feedback');
            }
            return;
        }

        // Hapus file lampiran jika ada
        if (!empty($feedback['attachment_url'])) {
            $fullPath = ROOT_PATH . '/' . $feedback['attachment_url'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $success = $this->feedbackModel->delete($id);

        if ($success) {
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => 'Masukan berhasil dihapus']);
            } else {
                $_SESSION['success'] = 'Masukan berhasil dihapus.';
                $this->redirect('feedback');
            }
        } else {
            if ($this->isAjax()) {
                $this->json(['error' => 'Gagal menghapus masukan'], 500);
            } else {
                $_SESSION['error'] = 'Gagal menghapus masukan.';
                $this->redirect('feedback');
            }
        }
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Proses POST /feedback/create
     */
    private function handleCreatePost(): void
    {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            $_SESSION['error'] = 'Sesi Anda kedaluwarsa. Muat ulang halaman dan coba lagi.';
            $this->redirect('feedback/create');
            return;
        }

        $errors = [];

        // Validasi jenis
        $jenis = $_POST['jenis_feedback'] ?? '';
        if (!in_array($jenis, self::VALID_JENIS, true)) {
            $errors[] = 'Jenis masukan wajib dipilih.';
        }

        // Validasi judul (multibyte — panjang dihitung per karakter, bukan byte)
        $judul = trim($_POST['judul'] ?? '');
        if (mb_strlen($judul) < 5) {
            $errors[] = 'Judul minimal 5 karakter.';
        }
        if (mb_strlen($judul) > 255) {
            $errors[] = 'Judul maksimal 255 karakter.';
        }

        // Validasi deskripsi (multibyte)
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if (mb_strlen($deskripsi) < 20) {
            $errors[] = 'Deskripsi minimal 20 karakter.';
        }
        if (mb_strlen($deskripsi) > 5000) {
            $errors[] = 'Deskripsi maksimal 5000 karakter.';
        }

        // Prioritas
        $prioritas = $_POST['prioritas'] ?? 'medium';
        if (!in_array($prioritas, self::VALID_PRIORITAS, true)) {
            $prioritas = 'medium';
        }

        if (!empty($errors)) {
            $_SESSION['error']     = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('feedback/create');
            return;
        }

        // Upload lampiran opsional — error upload dieksplisitkan, tidak diabaikan diam-diam.
        // UPLOAD_ERR_OK (0) harus masuk jalur upload; hanya kode lain (selain NO_FILE)
        // yang diteruskan ke uploadErrorMessage().
        $attachmentUrl  = null;
        $uploadedPath   = null;
        $attachmentError = (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE);
        $hasAttachment   = !empty($_FILES['attachment']['name']);

        if ($hasAttachment && $attachmentError === UPLOAD_ERR_OK) {
            $uploadResult = $this->handleFileUpload($_FILES['attachment']);
            if ($uploadResult['success']) {
                $attachmentUrl = $uploadResult['path'];
                $uploadedPath  = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        } elseif ($hasAttachment && $attachmentError !== UPLOAD_ERR_NO_FILE) {
            $errors[] = $this->uploadErrorMessage($attachmentError);
        }

        if (!empty($errors)) {
            $_SESSION['error']     = implode('<br>', $errors);
            $_SESSION['form_data'] = $_POST;
            $this->redirect('feedback/create');
            return;
        }

        $feedbackId = $this->feedbackModel->create([
            'user_id'        => (int) $_SESSION['user_id'],
            'jenis_feedback' => $jenis,
            'judul'          => $judul,
            'deskripsi'      => $deskripsi,
            'prioritas'      => $prioritas,
            'attachment_url' => $attachmentUrl,
        ]);

        if ($feedbackId) {
            $this->notifyAdminsNewFeedback((int) $feedbackId, (string) ($_SESSION['nama_lengkap'] ?? ''));
            $_SESSION['success'] = 'Masukan berhasil dikirim! Terima kasih atas saran Anda.';
            $this->redirect('feedback');
        } else {
            if ($uploadedPath !== null) {
                $this->deleteAttachmentFile($uploadedPath);
            }
            $_SESSION['error'] = 'Gagal menyimpan masukan. Silakan coba lagi.';
            $this->redirect('feedback/create');
        }
    }

    /**
     * Upload file lampiran dengan validasi magic bytes.
     */
    private function handleFileUpload(array $file): array
    {
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'File lampiran tidak valid.'];
        }

        return $this->storeUploadedFile((string) $file['tmp_name'], (int) $file['size']);
    }

    /**
     * Inti penyimpanan lampiran: validasi ukuran, magic-byte MIME, allowlist,
     * ekstensi dari MIME, direktori bertanggal dengan .htaccess, nama acak.
     * Dipisah dari is_uploaded_file agar dapat diuji tanpa HTTP multipart.
     */
    private function storeUploadedFile(string $tmpPath, int $size): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxSize      = 5 * 1024 * 1024; // 5 MB

        if ($size === 0 || !is_file($tmpPath)) {
            return ['success' => false, 'error' => 'File lampiran kosong (0 byte).'];
        }

        if ($size > $maxSize) {
            return ['success' => false, 'error' => 'Ukuran file maksimal 5 MB.'];
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan (JPG, PNG, GIF, WEBP, PDF).'];
        }

        $uploadDir = ROOT_PATH . '/public/uploads/feedback/' . date('Y/m');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log('Feedback upload: gagal membuat direktori upload (feedback/' . date('Y/m') . ')');
            return ['success' => false, 'error' => 'Penyimpanan lampiran sedang tidak tersedia. Silakan coba lagi nanti.'];
        }
        if (!is_writable($uploadDir)) {
            error_log('Feedback upload: direktori upload tidak writable (feedback/' . date('Y/m') . ')');
            return ['success' => false, 'error' => 'Penyimpanan lampiran sedang tidak tersedia. Silakan coba lagi nanti.'];
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $ext      = $extensions[$mimeType];
        $filename = 'fb_' . (int) ($_SESSION['user_id'] ?? 0) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullPath = $uploadDir . '/' . $filename;

        if (move_uploaded_file($tmpPath, $fullPath) || @rename($tmpPath, $fullPath)) {
            @chmod($fullPath, 0644);

            return ['success' => true, 'path' => 'public/uploads/feedback/' . date('Y/m') . '/' . $filename];
        }

        error_log('Feedback upload: gagal memindahkan lampiran ke direktori tujuan');
        return ['success' => false, 'error' => 'Gagal menyimpan file.'];
    }

    /**
     * Hapus file lampiran yang sudah dipindahkan bila data feedback gagal
     * tersimpan, agar tidak meninggalkan file orphan. Hanya menerima path di
     * dalam direktori upload feedback.
     */
    private function deleteAttachmentFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        if (strpos($normalized, 'public/uploads/feedback/') !== 0) {
            return;
        }

        $fullPath = ROOT_PATH . '/' . $normalized;
        $real     = realpath($fullPath);
        $realDir  = realpath(ROOT_PATH . '/public/uploads/feedback');
        if ($real === false || $realDir === false || strpos($real, $realDir) !== 0) {
            return;
        }

        if (is_file($real)) {
            @unlink($real);
        }
    }

    /**
     * Cek apakah request adalah AJAX.
     */
    private function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /**
     * Pesan error upload berdasarkan kode UPLOAD_ERR_*.
     * UPLOAD_ERR_OK tidak pernah masuk jalur error (ditangani jalur upload),
     * namun mappingnya tetap eksplisit demi kelengkapan.
     */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_OK => 'Tidak ada galat pada unggahan berkas lampiran.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file lampiran melebihi batas maksimum.',
            UPLOAD_ERR_PARTIAL => 'Upload file lampiran tidak lengkap, silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file lampiran yang diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori upload tidak tersedia di server.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file lampiran di server.',
            UPLOAD_ERR_EXTENSION => 'Upload file lampiran diblokir oleh ekstensi server.',
            default => 'Gagal mengunggah file lampiran.',
        };
    }

    /**
     * Kirim notifikasi ke semua admin aktif saat ada feedback baru.
     */
    private function notifyAdminsNewFeedback(int $feedbackId, string $creatorName): void
    {
        try {
            $userModel = $this->model('User');
            $result    = $userModel->getAllUsers(1, 100, '', 'admin', 'aktif', true);
            $admins    = $result['data'] ?? $result;

            $feedback   = $this->feedbackModel->getById($feedbackId);
            $jenisLabel = $this->getJenisLabel($feedback['jenis_feedback'] ?? '');

            foreach ($admins as $admin) {
                if (!empty($admin['id'])) {
                    $this->createNotification(
                        (int) $admin['id'],
                        'Masukan Baru: ' . $jenisLabel,
                        "Petugas {$creatorName} mengirim masukan baru: \"{$feedback['judul']}\"",
                        'info'
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('FeedbackController::notifyAdminsNewFeedback - ' . $e->getMessage());
        }
    }

    /**
     * Kirim notifikasi ke pembuat feedback saat status berubah.
     */
    private function notifyUserStatusChange(array $feedback, string $newStatus, string $notes): void
    {
        try {
            $statusLabel = $this->getStatusLabel($newStatus);
            $message     = "Status masukan Anda \"{$feedback['judul']}\" diperbarui menjadi: {$statusLabel}.";
            if (!empty($notes)) {
                $message .= " Catatan: {$notes}";
            }

            $type = match ($newStatus) {
                'selesai'     => 'success',
                'ditolak'     => 'danger',
                'dalam_proses' => 'warning',
                default       => 'info',
            };

            $this->createNotification((int) $feedback['user_id'], 'Update Status Masukan', $message, $type);
        } catch (\Throwable $e) {
            error_log('FeedbackController::notifyUserStatusChange - ' . $e->getMessage());
        }
    }

    /**
     * Insert notifikasi ke tabel notifications.
     */
    private function createNotification(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $db   = Database::getInstance()->getConnection();
        $sql  = "INSERT INTO notifications (user_id, title, body, type) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $title, $message, $type]);
    }

    /**
     * Label jenis feedback.
     */
    private function getJenisLabel(string $jenis): string
    {
        return match ($jenis) {
            'bug'         => 'Bug Report',
            'fitur_baru'  => 'Fitur Baru',
            'peningkatan' => 'Saran Peningkatan',
            default       => $jenis,
        };
    }

    /**
     * Label status feedback.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'diterima'     => 'Diterima',
            'dalam_proses' => 'Dalam Proses',
            'selesai'      => 'Selesai',
            'ditolak'      => 'Ditolak',
            default        => $status,
        };
    }
}
