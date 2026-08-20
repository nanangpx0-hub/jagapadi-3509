<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/models/Feedback.php';

final class FeedbackController extends BaseApiController
{
    private Feedback $feedbackModel;

    public function __construct()
    {
        $this->feedbackModel = new Feedback();
    }

    public function summary(): void
    {
        $filters = $this->validatedFilters();
        $this->sendResponse([
            'totals' => $this->feedbackModel->getAdminSummaryStats($filters),
            'by_petugas' => $this->feedbackModel->getRekapPerPetugas($filters),
            'generated_at' => date(DATE_ATOM),
        ], 'Rekap masukan berhasil diambil');
    }

    public function index(): void
    {
        $filters = $this->validatedFilters();
        $filters['search'] = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $result = $this->feedbackModel->getAll($filters, $page, $perPage);

        $this->sendResponse([
            'items' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'per_page' => $result['limit'],
                'total' => $result['total'],
                'total_pages' => $result['totalPages'],
            ],
            'generated_at' => date(DATE_ATOM),
        ], 'Daftar masukan berhasil diambil');
    }

    private function validatedFilters(): array
    {
        $filters = [];
        $jenis = (string) ($_GET['jenis'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $year = (int) ($_GET['year'] ?? 0);
        $month = (int) ($_GET['month'] ?? 0);

        if ($jenis !== '' && !in_array($jenis, ['bug', 'fitur_baru', 'peningkatan'], true)) {
            $this->sendError('Filter jenis tidak valid', 422);
        }
        if ($status !== '' && !in_array($status, ['diterima', 'dalam_proses', 'selesai', 'ditolak'], true)) {
            $this->sendError('Filter status tidak valid', 422);
        }
        if ($year !== 0 && ($year < 2020 || $year > ((int) date('Y') + 1))) {
            $this->sendError('Filter tahun tidak valid', 422);
        }
        if ($month < 0 || $month > 12) {
            $this->sendError('Filter bulan tidak valid', 422);
        }

        if ($jenis !== '') $filters['jenis'] = $jenis;
        if ($status !== '') $filters['status'] = $status;
        if ($year !== 0) $filters['year'] = $year;
        if ($month !== 0) $filters['month'] = $month;
        return $filters;
    }
}
