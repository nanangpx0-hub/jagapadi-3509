<?php

declare(strict_types=1);

/**
 * EarlyWarningController
 * Mengelola antarmuka web dan API Sistem Peringatan Dini Serangan Hama (EWS) JAGAPADI.
 */
class EarlyWarningController extends Controller
{
    private EarlyWarningOrchestrator $orchestrator;

    public function __construct()
    {
        parent::__construct();
        require_once ROOT_PATH . '/app/services/PestDataCollectorService.php';
        require_once ROOT_PATH . '/app/services/PestVisionAndPredictiveService.php';
        require_once ROOT_PATH . '/app/services/PestRiskAssessmentService.php';
        require_once ROOT_PATH . '/app/services/EarlyWarningNotificationService.php';
        require_once ROOT_PATH . '/app/services/EarlyWarningOrchestrator.php';
        require_once ROOT_PATH . '/app/models/EarlyWarningAlert.php';

        $this->orchestrator = new EarlyWarningOrchestrator();
    }

    /**
     * Tampilan utama Dashboard Early Warning System.
     */
    public function index(): void
    {
        $this->checkAuth();

        $summary = $this->orchestrator->getDashboardSummary();

        $this->view('early_warning/index', [
            'title' => 'Early Warning System — Peringatan Dini Serangan Hama',
            'summary' => $summary,
            'recent_alerts' => $summary['recent_alerts'],
            'recent_detections' => $summary['recent_detections'],
            'opt_catalog' => $summary['opt_catalog'],
            'kecamatan_list' => $summary['kecamatan_list'],
            'user_role' => $_SESSION['role'] ?? 'petugas',
            'user_name' => $_SESSION['nama_lengkap'] ?? 'User',
            'csrf_token' => Security::generateCsrfToken(),
        ]);
    }

    /**
     * API Endpoint: Ringkasan data EWS untuk widget atau background polling.
     */
    public function apiSummary(): void
    {
        $this->checkAuth();
        $summary = $this->orchestrator->getDashboardSummary();
        $this->json(['success' => true, 'data' => $summary]);
    }

    /**
     * Endpoint Analisis Citra Computer Vision (Upload Foto Hama).
     */
    public function detect(): void
    {
        $this->checkAuth();
        $this->validateCsrfToken();

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Berkas gambar tidak ditemukan atau gagal diunggah.'], 422);
        }

        $file = $_FILES['image'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            $this->json(['success' => false, 'message' => 'Format file tidak didukung. Harap unggah format JPG, PNG, atau WebP.'], 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'Ukuran file melebihi batas maksimal 5MB.'], 422);
        }

        // Simpan file sementara di storage/uploads/early_warning
        $uploadDir = ROOT_PATH . '/storage/uploads/early_warning';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'cv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $this->json(['success' => false, 'message' => 'Gagal memproses penyimpanan berkas.'], 500);
        }

        $kecamatanId = !empty($_POST['kecamatan_id']) ? (int)$_POST['kecamatan_id'] : null;
        $result = $this->orchestrator->analyzeImageUpload($targetPath, $kecamatanId);

        $this->json([
            'success' => true,
            'message' => 'Analisis citra visual dan evaluasi risiko berhasil dijalankan.',
            'data' => $result,
        ]);
    }

    /**
     * Endpoint Penilaian Risiko (Asesmen) per Kecamatan dan Target OPT.
     */
    public function assess(): void
    {
        $this->checkAuth();
        $this->validateCsrfToken();

        $kecamatanId = (int)($_POST['kecamatan_id'] ?? 0);
        $pestCode = strtoupper(trim($_POST['pest_code'] ?? 'WBC'));
        $autoDispatch = !empty($_POST['auto_dispatch']) && $_POST['auto_dispatch'] === '1';

        if ($kecamatanId <= 0) {
            $this->json(['success' => false, 'message' => 'Kecamatan wajib dipilih.'], 422);
        }

        $result = $this->orchestrator->assessKecamatan($kecamatanId, $pestCode, null, $autoDispatch);

        $this->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Endpoint Pemindaian Serentak Se-Kabupaten Jember.
     */
    public function scanAll(): void
    {
        $this->checkAuth();
        $this->validateCsrfToken();

        $autoDispatch = !empty($_POST['auto_dispatch']) && $_POST['auto_dispatch'] === '1';
        $result = $this->orchestrator->runFullRegionalAssessment($autoDispatch);

        $this->json([
            'success' => true,
            'message' => "Pemindaian serentak selesai. {$result['alerts_generated']} peringatan dini diterbitkan.",
            'data' => $result,
        ]);
    }

    /**
     * Menandai alert sebagai selesai.
     */
    public function resolveAlert(): void
    {
        $this->checkAuth();
        $this->validateCsrfToken();

        $alertId = (int)($_POST['alert_id'] ?? 0);
        if ($alertId <= 0) {
            $this->json(['success' => false, 'message' => 'ID Alert tidak valid.'], 422);
        }

        $alertModel = new EarlyWarningAlert();
        $success = $alertModel->markAsResolved($alertId);

        $this->json([
            'success' => $success,
            'message' => $success ? 'Status peringatan telah diperbarui menjadi Selesai.' : 'Gagal memperbarui status alert.',
        ]);
    }
}
