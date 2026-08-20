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

    /**
     * Defense-in-depth: pastikan sesi pengguna aktif di level controller,
     * terlepas dari middleware yang dipasang di Router.
     */
    private function assertAuthenticated(): void {
        if (empty($_SESSION['user_id'])) {
            $this->sendError('Unauthorized', 401);
            exit;
        }
    }

    private function isDevEnvironment(): bool {
        return in_array(
            strtolower((string)(getenv('APP_ENV') ?: 'production')),
            ['local', 'development', 'dev'],
            true
        );
    }

    /**
     * Log exception secara aman dan kirimkan pesan generik (kecuali env dev).
     */
    private function handleApiException(string $label, Throwable $e): never {
        error_log(sprintf('[Api\LaporanLainnya::%s] %s | user_id=%s',
            $label, $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
        $msg = $this->isDevEnvironment()
            ? "Gagal {$label}: " . $e->getMessage()
            : "Terjadi kesalahan pada operasi {$label}.";
        $this->sendError($msg, 500);
        exit;
    }

    public function jenisIndex() {
        $this->assertAuthenticated();
        try {
            $jenis = $this->jenisModel->findAllActive();
            $this->sendResponse($jenis, 'Jenis laporan retrieved successfully');
        } catch (Throwable $e) {
            $this->handleApiException('mengambil jenis laporan', $e);
        }
    }

    public function index() {
        $this->assertAuthenticated();
        try {
            $pagination = $this->getPaginationParams();

            $filters = [
                'jenis_id' => $_GET['jenis_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'desa_id' => $_GET['desa_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null,
                'user_id' => ($_SESSION['role'] ?? '') === 'petugas' ? $_SESSION['user_id'] : null,
                'show_own_draft' => ($_SESSION['role'] ?? '') === 'petugas',
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
        } catch (Throwable $e) {
            $this->handleApiException('mengambil daftar laporan lainnya', $e);
        }
    }

    public function show($id) {
        $this->assertAuthenticated();
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
        } catch (Throwable $e) {
            $this->handleApiException('mengambil laporan lainnya', $e);
        }
    }

    public function store() {
        $this->assertAuthenticated();
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

            // ============ Validasi tanggal kejadian (Perbaikan 7) ============
            $tanggalKejadian = $data['tanggal_kejadian'] ?? '';
            if (empty($tanggalKejadian)) {
                $errors[] = 'tanggal_kejadian wajib diisi';
            } else {
                $date = DateTime::createFromFormat('Y-m-d', $tanggalKejadian);
                if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                    $errors[] = 'Format tanggal tidak valid (YYYY-MM-DD)';
                } elseif ($date > new DateTime()) {
                    $errors[] = 'Tanggal kejadian tidak boleh di masa depan';
                }
            }

            // ============ Validasi koordinat GPS (Perbaikan 7) ============
            $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
            $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
            if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
                $errors[] = 'Latitude harus antara -90 dan 90';
            }
            if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
                $errors[] = 'Longitude harus antara -180 dan 180';
            }
            if (($latitude === null) !== ($longitude === null)) {
                $errors[] = 'Latitude dan longitude harus diisi bersama';
            }

            if (!empty($errors)) {
                $this->sendError('Validasi gagal', 422, $errors);
            }

            $reportData = [
                'user_id' => $_SESSION['user_id'],
                'jenis_id' => $jenisId,
                'kode_laporan' => $this->laporanModel->generateKodeLaporan(),
                'desa_id' => !empty($data['desa_id']) ? (int)$data['desa_id'] : null,
                'tanggal_kejadian' => $tanggalKejadian,
                'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'deskripsi' => $data['deskripsi'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
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
        } catch (Throwable $e) {
            $this->handleApiException('membuat laporan lainnya', $e);
        }
    }

    public function update($id) {
        $this->assertAuthenticated();
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if (($_SESSION['role'] ?? '') === 'petugas' && $existing['user_id'] != $_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }

            if (!in_array($existing['status'] ?? null, ['draft', 'rejected'], true)) {
                $this->sendError('Hanya laporan berstatus draft atau rejected yang dapat diupdate', 422);
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

            // ============ Validasi tanggal kejadian (Perbaikan 7) ============
            $tanggalKejadian = $data['tanggal_kejadian'] ?? $existing['tanggal_kejadian'] ?? '';
            if (empty($tanggalKejadian)) {
                $errors[] = 'tanggal_kejadian wajib diisi';
            } else {
                $date = DateTime::createFromFormat('Y-m-d', $tanggalKejadian);
                if (!$date || $date->format('Y-m-d') !== $tanggalKejadian) {
                    $errors[] = 'Format tanggal tidak valid (YYYY-MM-DD)';
                } elseif ($date > new DateTime()) {
                    $errors[] = 'Tanggal kejadian tidak boleh di masa depan';
                }
            }

            // ============ Validasi koordinat GPS (Perbaikan 7) ============
            $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
            $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
            if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
                $errors[] = 'Latitude harus antara -90 dan 90';
            }
            if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
                $errors[] = 'Longitude harus antara -180 dan 180';
            }
            if (($latitude === null) !== ($longitude === null)) {
                $errors[] = 'Latitude dan longitude harus diisi bersama';
            }

            if (!empty($errors)) {
                $this->sendError('Validasi gagal', 422, $errors);
            }

            $updateData = [
                'jenis_id' => $jenisId,
                'desa_id' => !empty($data['desa_id']) ? (int)$data['desa_id'] : null,
                'tanggal_kejadian' => $tanggalKejadian,
                'data_json' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'deskripsi' => $data['deskripsi'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $success = $this->laporanModel->updateReport((int)$id, $updateData);

            if ($success) {
                $laporan = $this->laporanModel->getById((int)$id);
                $this->sendResponse($laporan, 'Laporan lainnya updated successfully');
            } else {
                $this->sendError('Failed to update laporan lainnya', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('memperbarui laporan lainnya', $e);
        }
    }

    public function submit($id) {
        $this->assertAuthenticated();
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid laporan ID', 400);
            }

            $existing = $this->laporanModel->getById((int)$id);
            if (!$existing) {
                $this->sendError('Laporan lainnya not found', 404);
            }

            if (!in_array($existing['status'] ?? null, ['draft', 'rejected'], true)) {
                $this->sendError('Hanya laporan berstatus draft atau rejected yang dapat disubmit', 422);
            }

            if ($existing['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden', 403);
            }

            $success = $this->laporanModel->submitReport(
                (int)$id,
                (int)$_SESSION['user_id'],
                (string)$_SESSION['role']
            );

            if ($success) {
                $laporan = $this->laporanModel->getById((int)$id);
                $this->sendResponse($laporan, 'Laporan lainnya submitted successfully');
            } else {
                $this->sendError('Failed to submit laporan lainnya', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('menyubmit laporan lainnya', $e);
        }
    }

    public function destroy($id) {
        $this->assertAuthenticated();
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
        } catch (Throwable $e) {
            $this->handleApiException('menghapus laporan lainnya', $e);
        }
    }

    public function uploadFoto($id) {
        $this->assertAuthenticated();
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

            // Hapus foto lama jika ada
            if (!empty($existing['foto_url'])) {
                $oldFilePath = ROOT_PATH . '/public/' . $existing['foto_url'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // Kompresi otomatis bila ukuran > 2MB (kurangi payload & hemat storage)
            $maxSize = 2 * 1024 * 1024;
            if ($_FILES['foto']['size'] > $maxSize) {
                require_once ROOT_PATH . '/app/helpers/ImageCompressor.php';
                $compressor = new ImageCompressor();
                $result = $compressor->compress($_FILES['foto']['tmp_name'], $filepath, $maxSize);

                if (!$result['success']) {
                    $this->sendError('Gagal mengkompresi foto: ' . ($result['error'] ?? 'Unknown error'), 500);
                }
            } else {
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $filepath)) {
                    $this->sendError('Gagal menyimpan file', 500);
                }
            }

            $fotoUrl = 'uploads/laporan-lainnya/' . $filename;

            $updateData = [
                'foto_url' => $fotoUrl,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $success = $this->laporanModel->updateReport((int)$id, $updateData);

            if (!$success) {
                // Gagal simpan ke DB: hapus file yang baru diupload agar tidak jadi file yatim
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                $this->sendError('Failed to update foto_url', 500);
            }

            $laporan = $this->laporanModel->getById((int)$id);
            $this->sendResponse($laporan, 'Foto uploaded successfully');
        } catch (Throwable $e) {
            $this->handleApiException('mengunggah foto', $e);
        }
    }

    /**
     * Get performance summary for the authenticated petugas
     * Restricted to petugas role only
     */
    public function summary() {
        $this->assertAuthenticated();
        
        // Role check - only petugas can access this endpoint
        if (($_SESSION['role'] ?? '') !== 'petugas') {
            $this->sendError('Forbidden: Akses khusus untuk Petugas Lapangan', 403);
            exit;
        }
        
        try {
            $userId = (int)$_SESSION['user_id'];
            $year = (int)($_GET['year'] ?? date('Y'));
            
            // Validate year range
            if ($year < 2020 || $year > (date('Y') + 1)) {
                $year = (int)date('Y');
            }

            // Get performance data using the model methods
            $performanceSummary = $this->laporanModel->getPetugasPerformanceSummary($userId, $year);
            $monthlyTrend = $this->laporanModel->getPetugasMonthlyTrend($userId, $year);
            $jenisBreakdown = $this->laporanModel->getPetugasBreakdownByJenis($userId, $year);
            
            $response = [
                'year' => $year,
                'user_id' => $userId,
                'performance_summary' => $performanceSummary,
                'monthly_trend' => $monthlyTrend,
                'jenis_breakdown' => $jenisBreakdown,
            ];
            
            $this->sendResponse($response, 'Ringkasan kinerja petugas retrieved successfully');
        } catch (Throwable $e) {
            $this->handleApiException('mengambil ringkasan kinerja', $e);
        }
    }

    /**
     * Export petugas reports to CSV format
     * Restricted to petugas role only
     */
    public function export() {
        $this->assertAuthenticated();
        
        // Role check - only petugas can access this endpoint
        if (($_SESSION['role'] ?? '') !== 'petugas') {
            $this->sendError('Forbidden: Akses khusus untuk Petugas Lapangan', 403);
            exit;
        }
        
        try {
            $userId = (int)$_SESSION['user_id'];
            
            // Get filters from request
            $year = (int)($_GET['year'] ?? date('Y'));
            $status = $_GET['status'] ?? '';
            $jenisId = $_GET['jenis_id'] ?? '';
            
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

            // Get all reports for the user with filters
            $reports = $this->laporanModel->getPetugasReportList($userId, $filters, 10000, 0);
            
            if (empty($reports)) {
                $this->sendError('Tidak ada data untuk diekspor', 404);
                exit;
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
            $sanitizedHeaders = array_map(function($cell) {
                if ($cell === null) {
                    return '';
                }
                
                $stringValue = (string)$cell;
                
                // Check if the value starts with dangerous characters
                $firstChar = mb_substr($stringValue, 0, 1);
                $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
                
                if (in_array($firstChar, $dangerousChars, true)) {
                    // Prepend a single quote to prevent formula interpretation
                    return "'" . $stringValue;
                }
                
                return $stringValue;
            }, $headers);
            
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
                $sanitizedRow = array_map(function($cell) {
                    if ($cell === null) {
                        return '';
                    }
                    
                    $stringValue = (string)$cell;
                    
                    // Check if the value starts with dangerous characters
                    $firstChar = mb_substr($stringValue, 0, 1);
                    $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
                    
                    if (in_array($firstChar, $dangerousChars, true)) {
                        // Prepend a single quote to prevent formula interpretation
                        return "'" . $stringValue;
                    }
                    
                    return $stringValue;
                }, $row);
                
                fputcsv($output, $sanitizedRow);
            }
            
            fclose($output);
            
            // Log export activity
            error_log(sprintf('[Api\LaporanLainnya::export] User %d exported reports for year %d, count: %d',
                $userId, $year, count($reports)));
            
            exit;
            
        } catch (Throwable $e) {
            $this->handleApiException('mengekspor laporan', $e);
        }
    }
}