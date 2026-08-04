<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/models/JenisLaporan.php';
require_once ROOT_PATH . '/app/models/LaporanLainnya.php';
require_once ROOT_PATH . '/app/helpers/Logger.php';

class LaporanLainnyaController extends BaseApiController {

    private JenisLaporan $jenisModel;
    private LaporanLainnya $laporanModel;

    public function __construct() {
        $this->jenisModel = new JenisLaporan();
        $this->laporanModel = new LaporanLainnya();
    }

    public function jenisIndex() {
        try {
            $jenis = $this->jenisModel->findAllActive();
            $this->sendResponse($jenis, 'Jenis laporan retrieved successfully');
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve jenis laporan: ' . $e->getMessage(), 500);
        }
    }

    public function index() {
        try {
            $pagination = $this->getPaginationParams();

            $filters = [
                'jenis_id' => $_GET['jenis_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'desa_id' => $_GET['desa_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null,
                'user_id' => ($_SESSION['role'] === 'petugas') ? $_SESSION['user_id'] : null,
            ];

            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $laporan = $this->laporanModel->getAllWithFilters(
                $filters,
                $pagination['limit'],
                $pagination['offset']
            );
            $total = $this->laporanModel->getCountWithFilters($filters);

            $response = $this->formatPaginatedResponse($laporan, $total, $pagination['page'], $pagination['limit']);
            $this->sendResponse($response, 'Laporan lainnya retrieved successfully');
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function show($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $laporan = $this->laporanModel->getById((int)$id);

            if (!$laporan) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if ($_SESSION['role'] === 'petugas' && $laporan['user_id'] != $_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }

            $dataJson = json_decode($laporan['data_json'], true) ?? [];
            $laporan['data'] = $dataJson;

            $this->sendResponse($laporan, 'Laporan lainnya retrieved successfully');
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function store() {
        try {
            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);

            $jenisId = (int)($data['jenis_id'] ?? 0);
            if ($jenisId <= 0) {
                $this->sendError('jenis_id wajib diisi', 422);
            }

            $jenis = $this->jenisModel->findById($jenisId);
            if (!$jenis) {
                $this->sendError('Jenis laporan tidak ditemukan', 422);
            }

            $fields = $this->jenisModel->getFields($jenisId);
            $dataJson = $data['data_json'] ?? [];

            if (!is_array($dataJson)) {
                $this->sendError('data_json harus berupa object', 422);
            }

            $errors = [];
            foreach ($fields as $field) {
                $fieldName = $field['name'];
                $value = $dataJson[$fieldName] ?? null;

                if (!empty($field['required']) && ($value === null || $value === '')) {
                    $errors[] = "Field '{$field['label']}' wajib diisi";
                }
            }

            if (!empty($errors)) {
                $this->sendError('Validasi gagal', 422, $errors);
            }

            $kodeLaporan = $this->laporanModel->generateKodeLaporan();

            $reportData = [
                'user_id' => $_SESSION['user_id'],
                'jenis_id' => $jenisId,
                'kode_laporan' => $kodeLaporan,
                'desa_id' => !empty($data['desa_id']) ? (int)$data['desa_id'] : null,
                'tanggal_kejadian' => $data['tanggal_kejadian'] ?? null,
                'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'deskripsi' => $data['deskripsi'] ?? null,
                'latitude' => !empty($data['latitude']) ? (float)$data['latitude'] : null,
                'longitude' => !empty($data['longitude']) ? (float)$data['longitude'] : null,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $reportId = $this->laporanModel->createReport($reportData);

            if ($reportId) {
                $laporan = $this->laporanModel->getById($reportId);
                $this->sendResponse($laporan, 'Laporan lainnya created successfully', 201);
            } else {
                $this->sendError('Failed to create laporan lainnya', 500);
            }
        } catch (Exception $e) {
            $this->sendError('Failed to create laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function update($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if ($_SESSION['role'] === 'petugas' && $existing['user_id'] != $_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }

            if ($existing['status'] !== 'draft') {
                $this->sendError('Hanya laporan berstatus draft yang dapat diupdate', 422);
            }

            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);

            $jenisId = (int)($data['jenis_id'] ?? $existing['jenis_id']);
            $jenis = $this->jenisModel->findById($jenisId);
            if (!$jenis) {
                $this->sendError('Jenis laporan tidak ditemukan', 422);
            }

            $fields = $this->jenisModel->getFields($jenisId);
            $dataJson = $data['data_json'] ?? [];

            if (!is_array($dataJson)) {
                $this->sendError('data_json harus berupa object', 422);
            }

            $errors = [];
            foreach ($fields as $field) {
                $fieldName = $field['name'];
                $value = $dataJson[$fieldName] ?? null;

                if (!empty($field['required']) && ($value === null || $value === '')) {
                    $errors[] = "Field '{$field['label']}' wajib diisi";
                }
            }

            if (!empty($errors)) {
                $this->sendError('Validasi gagal', 422, $errors);
            }

            $updateData = [
                'jenis_id' => $jenisId,
                'desa_id' => !empty($data['desa_id']) ? (int)$data['desa_id'] : null,
                'tanggal_kejadian' => $data['tanggal_kejadian'] ?? null,
                'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'deskripsi' => $data['deskripsi'] ?? null,
                'latitude' => !empty($data['latitude']) ? (float)$data['latitude'] : null,
                'longitude' => !empty($data['longitude']) ? (float)$data['longitude'] : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $success = $this->laporanModel->updateReport((int)$id, $updateData);

            if ($success) {
                $laporan = $this->laporanModel->getById((int)$id);
                $this->sendResponse($laporan, 'Laporan lainnya updated successfully');
            } else {
                $this->sendError('Failed to update laporan lainnya', 500);
            }
        } catch (Exception $e) {
            $this->sendError('Failed to update laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function submit($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if ($existing['status'] !== 'draft') {
                $this->sendError('Hanya laporan berstatus draft yang dapat disubmit', 422);
            }

            if ($existing['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden', 403);
            }

            $success = $this->laporanModel->submitReport((int)$id);

            if ($success) {
                $laporan = $this->laporanModel->getById((int)$id);
                $this->sendResponse($laporan, 'Laporan lainnya submitted and verified successfully');
            } else {
                $this->sendError('Failed to submit laporan lainnya', 500);
            }
        } catch (Exception $e) {
            $this->sendError('Failed to submit laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            if ($_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden: only admin can delete', 403);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            $success = $this->laporanModel->delete((int)$id);

            if ($success) {
                $this->sendResponse(null, 'Laporan lainnya deleted successfully');
            } else {
                $this->sendError('Failed to delete laporan lainnya', 500);
            }
        } catch (Exception $e) {
            $this->sendError('Failed to delete laporan lainnya: ' . $e->getMessage(), 500);
        }
    }

    public function uploadFoto($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if ($existing['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden', 403);
            }

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $this->sendError('No file uploaded', 422);
            }

            $uploadDir = ROOT_PATH . '/public/uploads/laporan-lainnya/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['foto']['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($mimeType, $allowedMimes, true)) {
                $this->sendError('Tipe file tidak diizinkan. Hanya JPG, PNG, WEBP', 422);
            }

            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw new Exception('Tipe MIME tidak dikenal'),
            };

            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $filepath = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $filepath)) {
                $this->sendError('Gagal menyimpan file', 500);
            }

            $fotoUrl = 'uploads/laporan-lainnya/' . $filename;

            $updateData = [
                'foto_url' => $fotoUrl,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $this->laporanModel->updateReport((int)$id, $updateData);

            $laporan = $this->laporanModel->getById((int)$id);
            $this->sendResponse($laporan, 'Foto uploaded successfully');
        } catch (Exception $e) {
            $this->sendError('Failed to upload foto: ' . $e->getMessage(), 500);
        }
    }
}