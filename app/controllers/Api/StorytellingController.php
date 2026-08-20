<?php

declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/services/DataStoryService.php';

/**
 * Session-authenticated API adapter for the storytelling service.
 */
class StorytellingController extends BaseApiController
{
    private PDO $db;
    private DataStoryService $service;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->service = new DataStoryService($this->db);
    }

    public function getAnalyses(): void
    {
        $this->checkPermission();

        try {
            $pagination = $this->getPaginationParams();
            $where = [];
            $params = [];

            if (isset($_GET['tahun']) && $_GET['tahun'] !== '') {
                $tahun = $this->validYear($_GET['tahun']);
                $where[] = 'apb.periode_tahun = ?';
                $params[] = $tahun;
            }
            if (isset($_GET['wilayah_id']) && $_GET['wilayah_id'] !== '') {
                $wilayahId = $this->validPositiveInt($_GET['wilayah_id'], 'Wilayah');
                $where[] = 'apb.wilayah_id = ?';
                $params[] = $wilayahId;
            }
            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $status = (string) $_GET['status'];
                if (!in_array($status, ['draft', 'published', 'archived'], true)) {
                    throw new InvalidArgumentException('Status analisis tidak valid.');
                }
                $where[] = 'apb.status_analisis = ?';
                $params[] = $status;
            }

            $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
            $count = $this->db->prepare(
                'SELECT COUNT(*) FROM analisis_produksi_bulanan apb' . $whereSql
            );
            $count->execute($params);
            $total = (int) $count->fetchColumn();

            $query = $this->db->prepare(
                'SELECT apb.*, mk.nama_kecamatan, u.nama_lengkap AS created_by_name
                 FROM analisis_produksi_bulanan apb
                 LEFT JOIN master_kecamatan mk ON apb.wilayah_id = mk.id
                 LEFT JOIN users u ON apb.created_by = u.id'
                . $whereSql
                . ' ORDER BY apb.updated_at DESC LIMIT ? OFFSET ?'
            );
            $position = 1;
            foreach ($params as $value) {
                $query->bindValue($position++, $value);
            }
            $query->bindValue($position++, $pagination['limit'], PDO::PARAM_INT);
            $query->bindValue($position, $pagination['offset'], PDO::PARAM_INT);
            $query->execute();

            $payload = $this->formatPaginatedResponse(
                $query->fetchAll(PDO::FETCH_ASSOC),
                $total,
                $pagination['page'],
                $pagination['limit']
            );
            $this->sendResponse($payload, 'Daftar analisis berhasil dimuat.');
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal memuat daftar analisis.');
        }
    }

    public function getAnalysis(mixed $id): void
    {
        $this->checkPermission();

        try {
            $analysisId = $this->validPositiveInt($id, 'ID analisis');
            $analysis = $this->service->getAnalysisById($analysisId);
            if ($analysis === null) {
                $this->sendError('Analisis tidak ditemukan.', 404);
            }
            $this->sendResponse($analysis, 'Detail analisis berhasil dimuat.');
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal memuat detail analisis.');
        }
    }

    public function generateAnalysis(): void
    {
        $this->checkPermission();

        try {
            $input = $this->sanitizeData($this->getRequestData());
            [$bulan, $tahun, $wilayahId] = $this->validFilter($input);
            $analysis = $this->service->analyzeCauses($bulan, $tahun, $wilayahId);

            if (!$analysis['success']) {
                $this->sendError(
                    $analysis['error'] ?? 'Data tidak cukup untuk dianalisis.',
                    ($analysis['error_code'] ?? '') === 'InsufficientData' ? 422 : 400,
                    [
                        'error_code' => $analysis['error_code'] ?? 'AnalysisFailed',
                        'data_quality' => $analysis['data_quality'] ?? null,
                    ]
                );
            }

            $analysis['chart_data'] = $this->service->getChartData(
                $bulan,
                $tahun,
                $wilayahId,
                6
            );
            $this->sendResponse($analysis, 'Analisis berhasil dibuat.');
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal membuat analisis.');
        }
    }

    public function saveAnalysis(): void
    {
        $this->checkPermission();

        try {
            $input = $this->sanitizeData($this->getRequestData());
            $result = $this->service->saveAnalysis($input, (int) $_SESSION['user_id']);
            $this->sendResponse($result, $result['message'] ?? 'Analisis berhasil disimpan.');
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal menyimpan analisis.');
        }
    }

    public function publishAnalysis(mixed $id): void
    {
        $this->checkPermission();

        try {
            $analysisId = $this->validPositiveInt($id, 'ID analisis');
            $this->service->publishAnalysis($analysisId, (int) $_SESSION['user_id']);
            $this->sendResponse(
                ['id' => $analysisId, 'status' => 'published'],
                'Analisis berhasil dipublikasikan.'
            );
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal mempublikasikan analisis.');
        }
    }

    public function getChartData(): void
    {
        $this->checkPermission();

        try {
            [$bulan, $tahun, $wilayahId] = $this->validFilter($_GET);
            $months = max(1, min(24, (int) ($_GET['months'] ?? 6)));
            $chart = $this->service->getChartData(
                $bulan,
                $tahun,
                $wilayahId,
                $months
            );
            $this->sendResponse($chart, 'Data grafik berhasil dimuat.');
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal memuat data grafik.');
        }
    }

    public function getStats(): void
    {
        $this->checkPermission();

        try {
            $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== ''
                ? $this->validYear($_GET['tahun'])
                : (int) date('Y');
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_analyses,
                        SUM(status_analisis = 'published') AS published_count,
                        SUM(status_analisis = 'draft') AS draft_count,
                        SUM(status_analisis = 'archived') AS archived_count
                 FROM analisis_produksi_bulanan
                 WHERE periode_tahun = ?"
            );
            $stmt->execute([$tahun]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($stats as $key => $value) {
                $stats[$key] = (int) ($value ?? 0);
            }

            $this->sendResponse(
                ['tahun' => $tahun, 'stats' => $stats],
                'Statistik analisis berhasil dimuat.'
            );
        } catch (Throwable $error) {
            $this->handleError($error, 'Gagal memuat statistik analisis.');
        }
    }

    private function validFilter(array $input): array
    {
        $bulan = filter_var($input['bulan'] ?? null, FILTER_VALIDATE_INT);
        if ($bulan === false || $bulan < 1 || $bulan > 12) {
            throw new InvalidArgumentException('Bulan tidak valid (1-12).');
        }

        return [
            $bulan,
            $this->validYear($input['tahun'] ?? null),
            $this->validPositiveInt($input['wilayah_id'] ?? null, 'Wilayah'),
        ];
    }

    private function validYear(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > ((int) date('Y') + 1)) {
            throw new InvalidArgumentException('Tahun tidak valid.');
        }
        return $year;
    }

    private function validPositiveInt(mixed $value, string $label): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer <= 0) {
            throw new InvalidArgumentException("{$label} tidak valid.");
        }
        return $integer;
    }

    private function handleError(Throwable $error, string $fallback): never
    {
        if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
            $this->sendError($error->getMessage(), 422);
        }

        error_log('[STORYTELLING API] ' . $error->getMessage());
        $this->sendError($fallback, 500);
    }
}
