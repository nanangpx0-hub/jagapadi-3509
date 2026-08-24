<?php

declare(strict_types=1);

/**
 * Web controller untuk fitur Data Storytelling.
 */
class StorytellingController extends Controller
{
    private DataStoryService $dataStoryService;
    private StorytellingAnalysisService $analysisService;
    private MasterKecamatan $wilayahModel;

    public function __construct()
    {
        parent::__construct();
        require_once ROOT_PATH . '/app/services/DataStoryService.php';
        require_once ROOT_PATH . '/app/services/StorytellingAnalysisService.php';
        require_once ROOT_PATH . '/app/models/MasterKecamatan.php';

        $this->dataStoryService = new DataStoryService();
        $this->analysisService = new StorytellingAnalysisService();
        $this->wilayahModel = new MasterKecamatan();
    }

    public function index(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();

        $this->view('storytelling/index', [
            'title' => 'Dashboard Data Storytelling - JAGAPADI',
            'page_title' => 'Data Storytelling: Indikasi Faktor Produksi Padi',
            'user_role' => $_SESSION['role'],
            'user_name' => $_SESSION['nama_lengkap'] ?? 'User',
            'kecamatan_list' => $this->wilayahModel->getAllOrdered(),
            'current_month' => date('m'),
            'current_year' => date('Y'),
            'available_years' => $this->getAvailableYears(),
            'initial_stats' => $this->getInitialStats(),
            'recent_analyses' => $this->getRecentAnalyses(5),
            'data_availability' => $this->getDataAvailability(),
        ]);
    }

    public function generateAnalysis(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        $this->requireRequestMethod('POST');
        $this->validateCsrfToken();

        try {
            [$bulan, $tahun, $wilayahId] = $this->validatedFilter($_POST);
            $this->assertKecamatanExists($wilayahId);

            $analysis = $this->dataStoryService->analyzeCauses($bulan, $tahun, $wilayahId);
            if (!$analysis['success']) {
                $status = ($analysis['error_code'] ?? '') === 'InsufficientData' ? 422 : 400;
                $this->json($analysis, $status);
            }

            $existing = $this->checkExistingAnalysis($bulan, $tahun, $wilayahId);
            $analysis['existing_analysis'] = $existing;
            $analysis['has_existing'] = $existing !== null;
            $analysis['chart_data'] = $this->dataStoryService->getChartData(
                $bulan,
                $tahun,
                $wilayahId,
                6
            );

            $this->logActivity(
                'storytelling_analyze_complete',
                "Analisis {$tahun}-{$bulan} wilayah #{$wilayahId} selesai"
            );
            $this->json($analysis);
        } catch (Throwable $e) {
            error_log('[STORYTELLING] generateAnalysis error: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error_code' => 'InvalidRequest',
                'error' => $this->safeErrorMessage($e, 'Gagal melakukan analisis.'),
            ], 400);
        }
    }

    public function store(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        $this->requireRequestMethod('POST');
        $this->validateCsrfToken();

        try {
            $input = $this->getJsonInput();
            $result = $this->dataStoryService->saveAnalysis(
                $input,
                (int) $_SESSION['user_id']
            );

            $this->invalidateStorytellingStatsCache();
            $this->logActivity(
                'storytelling_save',
                'Analisis disimpan dengan rekalkulasi server',
                (int) $result['id']
            );

            $this->json([
                'success' => true,
                'message' => $result['message'],
                'analysis_id' => $result['id'],
                'action' => $result['action'],
            ]);
        } catch (Throwable $e) {
            error_log('[STORYTELLING] store error: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => $this->safeErrorMessage($e, 'Gagal menyimpan analisis.'),
            ], 422);
        }
    }

    public function getChartData(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();

        try {
            [$bulan, $tahun, $wilayahId] = $this->validatedFilter($_GET);
            $this->assertKecamatanExists($wilayahId);
            $months = max(1, min(24, (int) ($_GET['months'] ?? 6)));

            $this->json([
                'success' => true,
                'data' => $this->dataStoryService->getChartData(
                    $bulan,
                    $tahun,
                    $wilayahId,
                    $months
                ),
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $this->safeErrorMessage($e, 'Gagal memuat grafik.'),
            ], 400);
        }
    }

    /** Run a selectable analysis method over server-owned storytelling data. */
    public function runMethod(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        $this->requireRequestMethod('POST');
        $this->validateCsrfToken();

        try {
            $input = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
                ? $this->getJsonInput()
                : $_POST;
            [$bulan, $tahun, $wilayahId] = $this->validatedFilter($input);
            $this->assertKecamatanExists($wilayahId);
            $method = strtolower(trim((string) ($input['method'] ?? 'trend')));
            $months = max(6, min(24, (int) ($input['months'] ?? 12)));
            $parameters = is_array($input['parameters'] ?? null) ? $input['parameters'] : [];
            $chartData = $this->dataStoryService->getChartData(
                $bulan,
                $tahun,
                $wilayahId,
                $months
            );

            $this->json([
                'success' => true,
                'data' => $this->analysisService->analyze($method, $chartData, $parameters),
                'data_window_months' => $months,
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $this->safeErrorMessage($e, 'Gagal menjalankan metode analisis.'),
            ], $e instanceof DomainException ? 422 : 400);
        }
    }

    public function getRecent(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();

        $limit = max(1, min(50, (int) ($_GET['limit'] ?? 5)));
        $this->json([
            'success' => true,
            'data' => $this->getRecentAnalyses($limit),
        ]);
    }

    public function getAnalysis(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();

        $analysisId = (int) ($_GET['id'] ?? 0);
        if ($analysisId <= 0) {
            $this->json(['success' => false, 'error' => 'ID analisis tidak valid.'], 422);
        }

        $analysis = $this->dataStoryService->getAnalysisById($analysisId);
        if ($analysis === null) {
            $this->json(['success' => false, 'error' => 'Analisis tidak ditemukan.'], 404);
        }

        $this->json(['success' => true, 'data' => $analysis]);
    }

    public function publish(): void
    {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        $this->requireRequestMethod('POST');
        $this->validateCsrfToken();

        if (!in_array($_SESSION['role'], ['admin', 'statistisi'], true)) {
            $this->json([
                'success' => false,
                'error' => 'Anda tidak memiliki akses untuk mempublikasikan analisis.',
            ], 403);
        }

        try {
            $input = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
                ? $this->getJsonInput()
                : $_POST;
            $analysisId = (int) ($input['analysis_id'] ?? 0);
            if ($analysisId <= 0) {
                throw new InvalidArgumentException('ID analisis tidak valid.');
            }

            $this->dataStoryService->publishAnalysis(
                $analysisId,
                (int) $_SESSION['user_id']
            );
            $this->invalidateStorytellingStatsCache();
            $this->logActivity(
                'storytelling_publish',
                'Analisis dipublikasikan',
                $analysisId
            );

            $this->json([
                'success' => true,
                'message' => 'Analisis berhasil dipublikasikan.',
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $this->safeErrorMessage($e, 'Gagal mempublikasikan analisis.'),
            ], 422);
        }
    }

    protected function checkStorytellingAccess(): void
    {
        $allowedRoles = ['admin', 'operator', 'statistisi'];
        if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke fitur Data Storytelling';
            $this->redirect('dashboard');
        }
    }

    private function validatedFilter(array $input): array
    {
        $bulan = filter_var($input['bulan'] ?? null, FILTER_VALIDATE_INT);
        $tahun = filter_var($input['tahun'] ?? null, FILTER_VALIDATE_INT);
        $wilayahId = filter_var($input['wilayah_id'] ?? null, FILTER_VALIDATE_INT);

        if ($bulan === false || $bulan < 1 || $bulan > 12) {
            throw new InvalidArgumentException('Bulan tidak valid (1–12).');
        }
        if ($tahun === false || $tahun < 2000 || $tahun > ((int) date('Y') + 1)) {
            throw new InvalidArgumentException('Tahun tidak valid.');
        }
        if ($wilayahId === false || $wilayahId <= 0) {
            throw new InvalidArgumentException('Wilayah harus dipilih.');
        }

        return [$bulan, $tahun, $wilayahId];
    }

    private function assertKecamatanExists(int $wilayahId): void
    {
        if ($this->wilayahModel->getById($wilayahId) === null) {
            throw new InvalidArgumentException('Kecamatan tidak ditemukan.');
        }
    }

    private function getAvailableYears(): array
    {
        try {
            $stmt = Database::getInstance()->getConnection()->query(
                'SELECT DISTINCT tahun FROM produksi_gabah
                 WHERE bulan IS NOT NULL AND status = \'verified\'
                 ORDER BY tahun DESC'
            );
            $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if ($years !== []) {
                return $years;
            }
        } catch (Throwable $e) {
            error_log('[STORYTELLING] getAvailableYears error: ' . $e->getMessage());
        }

        $currentYear = (int) date('Y');
        return range($currentYear, $currentYear - 4);
    }

    private function getDataAvailability(): array
    {
        try {
            $stmt = Database::getInstance()->getConnection()->query(
                "SELECT COUNT(*) AS verified_total,
                        SUM(bulan IS NOT NULL) AS monthly_total,
                        COUNT(DISTINCT CASE WHEN bulan IS NOT NULL THEN kecamatan_id END) AS monthly_regions,
                        MAX(CASE WHEN bulan IS NOT NULL THEN updated_at END) AS monthly_updated_at
                 FROM produksi_gabah
                 WHERE status = 'verified'"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'available' => (int) ($row['monthly_total'] ?? 0) > 0,
                'verified_total' => (int) ($row['verified_total'] ?? 0),
                'monthly_total' => (int) ($row['monthly_total'] ?? 0),
                'monthly_regions' => (int) ($row['monthly_regions'] ?? 0),
                'monthly_updated_at' => $row['monthly_updated_at'] ?? null,
            ];
        } catch (Throwable $e) {
            error_log('[STORYTELLING] getDataAvailability error: ' . $e->getMessage());
            return [
                'available' => false,
                'verified_total' => 0,
                'monthly_total' => 0,
                'monthly_regions' => 0,
                'monthly_updated_at' => null,
            ];
        }
    }

    private function getInitialStats(): array
    {
        try {
            $stmt = Database::getInstance()->getConnection()->prepare(
                "SELECT COUNT(*) AS total_analyses,
                        SUM(status_analisis = 'published') AS published_count,
                        SUM(status_analisis = 'draft') AS draft_count
                 FROM analisis_produksi_bulanan
                 WHERE periode_tahun = ?"
            );
            $stmt->execute([(int) date('Y')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_analyses' => (int) ($result['total_analyses'] ?? 0),
                'published_count' => (int) ($result['published_count'] ?? 0),
                'draft_count' => (int) ($result['draft_count'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[STORYTELLING] getInitialStats error: ' . $e->getMessage());
            return ['total_analyses' => 0, 'published_count' => 0, 'draft_count' => 0];
        }
    }

    private function getRecentAnalyses(int $limit): array
    {
        try {
            $stmt = Database::getInstance()->getConnection()->prepare(
                'SELECT apb.*, mk.nama_kecamatan, u.nama_lengkap AS created_by_name
                 FROM analisis_produksi_bulanan apb
                 LEFT JOIN master_kecamatan mk ON apb.wilayah_id = mk.id
                 LEFT JOIN users u ON apb.created_by = u.id
                 ORDER BY apb.created_at DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[STORYTELLING] getRecentAnalyses error: ' . $e->getMessage());
            return [];
        }
    }

    private function checkExistingAnalysis(int $bulan, int $tahun, int $wilayahId): ?array
    {
        $stmt = Database::getInstance()->getConnection()->prepare(
            'SELECT id, status_analisis, updated_at
             FROM analisis_produksi_bulanan
             WHERE periode_bulan = ? AND periode_tahun = ? AND wilayah_id = ?'
        );
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Format JSON tidak valid.');
        }
        return $data;
    }

    private function invalidateStorytellingStatsCache(): void
    {
        $cacheFile = ROOT_PATH . '/storage/cache/storytelling_stats.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    private function safeErrorMessage(Throwable $error, string $fallback): string
    {
        if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
            return $error->getMessage();
        }
        return $fallback;
    }

    private function logActivity(string $action, string $description, ?int $relatedId = null): void
    {
        error_log(
            '[STORYTELLING] User ' . ($_SESSION['user_id'] ?? 'unknown')
            . ": {$action} - {$description}"
            . ($relatedId !== null ? " (ID: {$relatedId})" : '')
        );
    }
}
