<?php
/**
 * Scraper Queue Controller
 *
 * Menyediakan endpoint untuk men-queue job scraping ke tabel
 * scraping_job_queue yang diproses secara background oleh
 * scripts/scraper_worker.php (CLI worker).
 *
 * Endpoint:
 *   POST /api/scraper-queue/run                  -> {success, job_id, message}
 *   GET  /api/scraper-queue/status/{jobId}       -> {status, progress, result, error_message}
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

class ScraperQueueController extends Controller {

    private $db;

    public function __construct(?Container $container = null) {
        parent::__construct($container);
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Queue a scraping job (Admin only)
     */
    public function runScraperAsync() {
        $jobType = trim((string)($_POST['job_type'] ?? ''));
        $params = $_POST['params'] ?? [];

        $allowedTypes = ['curah_hujan', 'angin', 'harga', 'bps'];
        if (!in_array($jobType, $allowedTypes, true)) {
            $this->json(['success' => false, 'error' => 'job_type harus salah satu dari: ' . implode(', ', $allowedTypes)], 422);
        }

        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
        if ($paramsJson === false) {
            $this->json(['success' => false, 'error' => 'params tidak valid'], 422);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO scraping_job_queue (job_type, parameters, created_by, status, progress)
             VALUES (?, ?, ?, 'pending', 0)"
        );
        $stmt->execute([
            $jobType,
            $paramsJson,
            $_SESSION['user_id'] ?? null,
        ]);
        $jobId = (int)$this->db->lastInsertId();

        $this->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Scraping dijadwalkan. Gunakan endpoint status untuk memantau progres.'
        ]);
    }

    /**
     * Get background scraping job status (Admin only)
     *
     * @param int|null $jobId
     */
    public function getJobStatus($jobId = null) {
        $jobId = (int)$jobId;
        if ($jobId <= 0) {
            $this->json(['success' => false, 'error' => 'Job ID tidak valid'], 422);
        }

        $stmt = $this->db->prepare(
            "SELECT id, job_type, status, progress, result, error_message, created_at, started_at, completed_at
             FROM scraping_job_queue WHERE id = ?"
        );
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $this->json(['success' => false, 'error' => 'Job tidak ditemukan'], 404);
        }

        $this->json([
            'success' => true,
            'id' => (int)$job['id'],
            'job_type' => $job['job_type'],
            'status' => $job['status'],
            'progress' => (int)$job['progress'],
            'result' => $job['result'] ? json_decode($job['result'], true) : null,
            'error_message' => $job['error_message'],
            'created_at' => $job['created_at'],
            'started_at' => $job['started_at'],
            'completed_at' => $job['completed_at'],
        ]);
    }
}
