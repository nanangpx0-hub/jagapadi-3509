<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/models/LaporanIrigasi.php';
require_once ROOT_PATH . '/app/models/Irigasi.php';

class IrigasiController extends BaseApiController {

    private LaporanIrigasi $laporanModel;
    private Irigasi $irigasiModel;

    public function __construct() {
        $this->laporanModel = new LaporanIrigasi();
        // Model operasional (sensor/scraper) untuk monitoring, weather, rules, analytics
        $this->irigasiModel = new Irigasi();
    }

    /**
     * Defense-in-depth: pastikan user terautentikasi (middleware 'auth' juga
     * menjalankan ini, tapi jangan hanya bergantung pada satu lapisan).
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

    private function handleApiException(string $label, Throwable $e): never {
        error_log(sprintf('[Api\IrigasiController::%s] %s | user_id=%s',
            $label, $e->getMessage(), $_SESSION['user_id'] ?? 'null'));
        $msg = $this->isDevEnvironment()
            ? "Gagal {$label}: " . $e->getMessage()
            : "Terjadi kesalahan pada {$label}.";
        $this->sendError($msg, 500);
        exit;
    }

    private function resolveId(mixed $id): int {
        $resolved = filter_var($id, FILTER_VALIDATE_INT);
        if ($resolved === false || $resolved <= 0) {
            $this->sendError('ID irigasi tidak valid', 400);
        }
        return $resolved;
    }

    /**
     * Get all laporan irigasi with pagination
     * GET /api/irigasi
     */
    public function index() {
        $this->assertAuthenticated();

        try {
            $userId = ($_SESSION['role'] === 'petugas') ? (int)$_SESSION['user_id'] : null;
            $pagination = $this->getPaginationParams();
            $laporan = $this->laporanModel->getAllWithDetails(
                $userId,
                $pagination['limit'],
                $pagination['offset']
            );
            $total = $this->laporanModel->countAll($userId);
            $response = $this->formatPaginatedResponse(
                $laporan, $total, $pagination['page'], $pagination['limit']
            );
            $this->sendResponse($response, 'Laporan irigasi berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('index', $e);
        }
    }

    /**
     * Get specific laporan irigasi by ID
     * GET /api/irigasi/{id}
     */
    public function show($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            $laporan = $this->laporanModel->getDetailById($id);

            if (!$laporan) {
                $this->sendError('Laporan irigasi tidak ditemukan', 404);
            }

            // Check permission untuk petugas
            if ($_SESSION['role'] === 'petugas'
                && (int)($laporan['user_id'] ?? 0) !== (int)$_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }

            $this->sendResponse($laporan, 'Laporan irigasi berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('show', $e);
        }
    }

    /**
     * Create new laporan irigasi (status langsung Submitted)
     * POST /api/irigasi
     */
    public function store() {
        $this->assertAuthenticated();

        $uploadedPhotoPath = null;

        try {
            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);
            unset($data['foto'], $data['csrf_token'], $data['_token'], $data['action']);

            // Validate required fields
            $requiredFields = [
                'tanggal', 'nama_saluran', 'kabupaten_id', 'kecamatan_id',
                'desa_id', 'kondisi_fisik', 'debit_air', 'luas_layanan',
                'jenis_saluran', 'status_perbaikan'
            ];

            $errors = $this->validateRequired($data, $requiredFields);
            if (!empty($errors)) {
                $this->sendError('Validation failed', 422, $errors);
            }

            // Map kondisi form values ke DB enum values
            // Form: Baik → DB: Bagus, Rusak Ringan → DB: Tidak Bagus, Rusak Berat → DB: Rusak
            $kondisiFisikMap = [
                'Baik' => 'Bagus',
                'Rusak Ringan' => 'Tidak Bagus',
                'Rusak Berat' => 'Rusak',
            ];
            $data['kondisi_fisik'] = $kondisiFisikMap[$data['kondisi_fisik']] ?? $data['kondisi_fisik'];

            // Set user_id dari session
            $data['user_id'] = (int)$_SESSION['user_id'];
            $data['ip_pengirim'] = $_SERVER['REMOTE_ADDR'] ?? null;

            // Foto wajib
            $file = $_FILES['foto'] ?? null;
            if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $this->sendError(
                    'Foto laporan wajib disertakan sebelum laporan dapat disimpan.',
                    422,
                    ['foto' => 'Foto laporan wajib disertakan.']
                );
            }

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $this->sendError('Foto gagal diunggah. Silakan coba lagi.', 422, ['foto' => 'Upload foto tidak lengkap.']);
            }

            try {
                $data['foto_url'] = $this->handleFileUpload($file, 'irigasi');
            } catch (Exception $e) {
                $this->sendError($e->getMessage(), 422, ['foto' => $e->getMessage()]);
            }
            $uploadedPhotoPath = ROOT_PATH . '/public/' . ltrim($data['foto_url'], '/');

            $reportId = $this->laporanModel->createSubmitted($data);

            if ($reportId) {
                $laporan = $this->laporanModel->getDetailById($reportId);
                $this->sendResponse($laporan, 'Laporan irigasi berhasil dikirim', 201);
            }

            // Gagal menyimpan: bersihkan file foto yang baru diupload
            if ($uploadedPhotoPath !== null && is_file($uploadedPhotoPath)) {
                unlink($uploadedPhotoPath);
            }
            $this->sendError('Gagal menyimpan laporan irigasi', 500);
        } catch (Throwable $e) {
            if ($uploadedPhotoPath !== null && is_file($uploadedPhotoPath)) {
                unlink($uploadedPhotoPath);
            }
            $this->handleApiException('store', $e);
        }
    }

    /**
     * Update laporan irigasi (hanya status Ditolak yang dapat diperbarui)
     * PUT /api/irigasi/{id}
     */
    public function update($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            $existingLaporan = $this->laporanModel->getDetailById($id);
            if (!$existingLaporan) {
                $this->sendError('Laporan irigasi tidak ditemukan', 404);
            }

            // Check permission
            if ($_SESSION['role'] === 'petugas'
                && (int)($existingLaporan['user_id'] ?? 0) !== (int)$_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }

            // Hanya laporan Ditolak yang bisa diperbarui (alur resubmit)
            if (($existingLaporan['status'] ?? '') !== 'Ditolak') {
                $this->sendError('Hanya laporan berstatus Ditolak yang dapat diperbarui', 409);
            }

            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);

            // Petugas tidak boleh mengubah status/verifikasi/nomor laporan
            unset($data['status'], $data['verified_by'], $data['verified_at'],
                $data['catatan_verifikasi'], $data['nomor_laporan'], $data['user_id']);

            // Map kondisi form values ke DB enum values
            $kondisiFisikMap = [
                'Baik' => 'Bagus',
                'Rusak Ringan' => 'Tidak Bagus',
                'Rusak Berat' => 'Rusak',
            ];
            if (!empty($data['kondisi_fisik'])) {
                $data['kondisi_fisik'] = $kondisiFisikMap[$data['kondisi_fisik']] ?? $data['kondisi_fisik'];
            }

            $oldPhotoToDelete = null;

            // Handle file upload jika ada foto baru
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $data['foto_url'] = $this->handleFileUpload($_FILES['foto'], 'irigasi');
                $oldPhotoToDelete = !empty($existingLaporan['foto_url'])
                    ? ROOT_PATH . '/public/' . ltrim($existingLaporan['foto_url'], '/')
                    : null;
            }

            // resubmit(): status → Submitted, nomor baru, reset verifikasi
            $success = $this->laporanModel->resubmit($id, $data);

            if ($success) {
                if ($oldPhotoToDelete !== null && is_file($oldPhotoToDelete)) {
                    unlink($oldPhotoToDelete);
                }
                $laporan = $this->laporanModel->getDetailById($id);
                $this->sendResponse($laporan, 'Laporan irigasi berhasil diperbarui');
            }

            $this->sendError('Gagal memperbarui laporan irigasi', 500);
        } catch (Throwable $e) {
            $this->handleApiException('update', $e);
        }
    }

    /**
     * Delete laporan irigasi (admin only)
     * DELETE /api/irigasi/{id}
     */
    public function destroy($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            $existingLaporan = $this->laporanModel->getDetailById($id);
            if (!$existingLaporan) {
                $this->sendError('Laporan irigasi tidak ditemukan', 404);
            }

            // Check permission - only admin can delete
            if ($_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden', 403);
            }

            $success = $this->laporanModel->delete($id);

            if ($success) {
                // File cleanup hanya setelah baris DB terhapus
                if (!empty($existingLaporan['foto_url'])) {
                    $photoPath = ROOT_PATH . '/public/' . ltrim($existingLaporan['foto_url'], '/');
                    if (is_file($photoPath)) {
                        unlink($photoPath);
                    }
                }
                $this->sendResponse(null, 'Laporan irigasi berhasil dihapus');
            }

            $this->sendError('Gagal menghapus laporan irigasi', 500);
        } catch (Throwable $e) {
            $this->handleApiException('destroy', $e);
        }
    }

    /**
     * Get irigasi statistics (data operasional)
     * GET /api/irigasi/stats
     */
    public function getStats() {
        $this->assertAuthenticated();

        try {
            $stats = $this->irigasiModel->getStatistics();
            $this->sendResponse($stats, 'Statistik irigasi berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('getStats', $e);
        }
    }

    // =========================================================================
    // NEW ENDPOINTS: Monitoring, Weather, Rules, Analytics
    // =========================================================================

    /**
     * Get real-time monitoring data for an irigasi
     * GET /api/irigasi/{id}/monitoring
     */
    public function monitoring($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            // Get irigasi data (tabel operasional data_irigasi)
            $irigasi = $this->irigasiModel->getById($id);
            if (!$irigasi) {
                $this->sendError('Irigasi tidak ditemukan', 404);
            }

            // Get sensor data
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT po.id, po.status, po.triggered_by, po.started_at, po.ended_at, po.created_at
                FROM pengairan_otomatis po
                WHERE po.irigasi_id = ?
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$id]);
            $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get recent activity logs
            $stmt = $db->prepare("
                SELECT il.action, il.status, il.message, il.created_at
                FROM irrigation_logs il
                WHERE il.irigasi_id = ?
                ORDER BY il.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get active alerts count
            $stmt = $db->prepare("
                SELECT COUNT(*) as alert_count
                FROM irrigation_rule_logs
                WHERE irigasi_id = ? AND execution_status = 'error'
            ");
            $stmt->execute([$id]);
            $alertCount = $stmt->fetch(PDO::FETCH_ASSOC)['alert_count'] ?? 0;

            // Calculate KPIs
            $activeSensors = array_filter($sensors, function ($sensor) {
                return $sensor['status'] === 'active';
            });

            $response = [
                'irigasi' => $irigasi,
                'sensors' => $sensors,
                'recent_logs' => $logs,
                'kpi' => [
                    'active_sensors' => count($activeSensors),
                    'total_sensors' => count($sensors),
                    'alert_count' => $alertCount,
                    'last_update' => $sensors[0]['created_at'] ?? null
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->sendResponse($response, 'Data monitoring berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('monitoring', $e);
        }
    }

    /**
     * Get weather data for an irigasi location
     * GET /api/irigasi/{id}/weather
     */
    public function weather($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/services/WeatherService.php';
            $weatherService = new WeatherService();

            // Get forecast
            $forecast = $weatherService->getForIrigasi((int) $id);

            // Get current conditions
            $current = $weatherService->getCurrentConditions((int) $id);

            // Get adaptive multiplier
            $multiplier = $weatherService->getAdaptiveMultiplier((int) $id);

            // Get active alerts
            $alerts = $weatherService->getActiveAlerts((int) $id);

            // Check and create new alerts if needed
            $newAlerts = $weatherService->checkAndCreateAlerts((int) $id);

            $response = [
                'current' => $current,
                'forecast' => $forecast,
                'irrigation_adjustment' => [
                    'multiplier' => $multiplier,
                    'recommendation' => $this->getIrrigationRecommendation($multiplier)
                ],
                'alerts' => $alerts,
                'new_alerts' => $newAlerts,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->sendResponse($response, 'Data cuaca berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('weather', $e);
        }
    }

    /**
     * Get irrigation rules for an irigasi
     * GET /api/irigasi/{id}/rules
     */
    public function getRules($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();

            $rules = $ruleModel->getAllRulesForIrigasi($id);

            // Parse JSON for each rule
            foreach ($rules as &$rule) {
                $rule['conditions_parsed'] = json_decode($rule['conditions'], true);
                $rule['actions_parsed'] = json_decode($rule['actions'], true);
            }

            // Get statistics
            $stats = $ruleModel->getStatistics($id);

            // Get templates
            $templates = $ruleModel->getTemplates();

            $response = [
                'rules' => $rules,
                'statistics' => $stats,
                'templates' => $templates
            ];

            $this->sendResponse($response, 'Rules berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('getRules', $e);
        }
    }

    /**
     * Create a new rule
     * POST /api/irigasi/rules
     */
    public function createRule() {
        $this->assertAuthenticated();

        try {
            // Only admin/operator can create rules
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }

            $data = $this->getRequestData();

            // Validate
            if (empty($data['irigasi_id']) || empty($data['rule_name'])) {
                $this->sendError('Missing required fields: irigasi_id, rule_name', 422);
            }

            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';

            $ruleModel = new IrrigationRule();
            $engine = new IrrigationRuleEngine();

            // Validate rule configuration
            $conditions = is_array($data['conditions'] ?? null)
                ? $data['conditions']
                : ['operator' => 'AND', 'conditions' => []];
            $actions = is_array($data['actions'] ?? null)
                ? $data['actions']
                : ['actions' => []];

            $errors = $engine->validateRule($conditions, $actions);
            if (!empty($errors)) {
                $this->sendError('Invalid rule configuration', 422, $errors);
            }

            // Create rule
            $ruleData = [
                'irigasi_id' => filter_var($data['irigasi_id'] ?? 0, FILTER_VALIDATE_INT),
                'rule_name' => mb_substr(trim((string)($data['rule_name'] ?? '')), 0, 200),
                'description' => !empty($data['description'])
                              ? mb_substr(trim((string)$data['description']), 0, 1000)
                              : null,
                'conditions' => $conditions,
                'actions' => $actions,
                'priority' => max(1, min(100, (int)($data['priority'] ?? 10))),
                'is_active' => (int)(bool)($data['is_active'] ?? 1),
                'cooldown_minutes' => max(1, min(1440, (int)($data['cooldown_minutes'] ?? 60))),
                'created_by' => (int)$_SESSION['user_id']
            ];

            if ($ruleData['irigasi_id'] === false || $ruleData['irigasi_id'] <= 0) {
                $this->sendError('irigasi_id tidak valid', 422);
            }

            if (empty($ruleData['rule_name'])) {
                $this->sendError('rule_name wajib diisi', 422);
            }

            $ruleId = $ruleModel->createRule($ruleData);

            if ($ruleId) {
                $rule = $ruleModel->getRuleById($ruleId);
                $this->sendResponse($rule, 'Rule berhasil dibuat', 201);
            } else {
                $this->sendError('Gagal membuat rule', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('createRule', $e);
        }
    }

    /**
     * Update a rule
     * PUT /api/irigasi/rules/{id}
     */
    public function updateRule($id) {
        $this->assertAuthenticated();

        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }

            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();

            $rule = $ruleModel->getRuleById($id);
            if (!$rule) {
                $this->sendError('Rule tidak ditemukan', 404);
            }

            $data = $this->getRequestData();

            // Update fields
            $updateData = [];
            if (isset($data['rule_name'])) $updateData['rule_name'] = mb_substr(trim((string)$data['rule_name']), 0, 200);
            if (isset($data['description'])) $updateData['description'] = !empty($data['description']) ? mb_substr(trim((string)$data['description']), 0, 1000) : null;
            if (isset($data['conditions'])) $updateData['conditions'] = is_array($data['conditions']) ? $data['conditions'] : ['operator' => 'AND', 'conditions' => []];
            if (isset($data['actions'])) $updateData['actions'] = is_array($data['actions']) ? $data['actions'] : ['actions' => []];
            if (isset($data['priority'])) $updateData['priority'] = max(1, min(100, (int)$data['priority']));
            if (isset($data['is_active'])) $updateData['is_active'] = (int)(bool)$data['is_active'];
            if (isset($data['cooldown_minutes'])) $updateData['cooldown_minutes'] = max(1, min(1440, (int)$data['cooldown_minutes']));

            $success = $ruleModel->updateRule($id, $updateData);

            if ($success) {
                $rule = $ruleModel->getRuleById($id);
                $this->sendResponse($rule, 'Rule berhasil diperbarui');
            } else {
                $this->sendError('Gagal memperbarui rule', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('updateRule', $e);
        }
    }

    /**
     * Toggle rule active status
     * POST /api/irigasi/rules/{id}/toggle
     */
    public function toggleRule($id) {
        $this->assertAuthenticated();

        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }

            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();

            $rule = $ruleModel->getRuleById($id);
            if (!$rule) {
                $this->sendError('Rule tidak ditemukan', 404);
            }

            $newStatus = !$rule['is_active'];
            $success = $ruleModel->toggleRule($id, $newStatus);

            if ($success) {
                $this->sendResponse([
                    'id' => $id,
                    'is_active' => $newStatus
                ], 'Rule ' . ($newStatus ? 'diaktifkan' : 'dinonaktifkan') . ' berhasil');
            } else {
                $this->sendError('Gagal mengubah status rule', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('toggleRule', $e);
        }
    }

    /**
     * Manually execute a rule
     * POST /api/irigasi/rules/{id}/execute
     */
    public function executeRule($id) {
        $this->assertAuthenticated();

        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }

            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';
            $engine = new IrrigationRuleEngine();

            $result = $engine->manualTrigger($id);

            $this->sendResponse($result, $result['success'] ? 'Rule berhasil dieksekusi' : 'Eksekusi rule gagal');
        } catch (Throwable $e) {
            $this->handleApiException('executeRule', $e);
        }
    }

    /**
     * Evaluate all rules for an irigasi
     * POST /api/irigasi/{id}/evaluate-rules
     */
    public function evaluateRules($id) {
        $this->assertAuthenticated();

        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }

            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';
            $engine = new IrrigationRuleEngine();

            $results = $engine->evaluateRules($id);

            $this->sendResponse($results, 'Rules berhasil dievaluasi');
        } catch (Throwable $e) {
            $this->handleApiException('evaluateRules', $e);
        }
    }

    /**
     * Get dashboard summary for all irigasi
     * GET /api/irigasi/dashboard-summary
     */
    public function dashboardSummary() {
        $this->assertAuthenticated();

        try {
            $db = Database::getInstance()->getConnection();

            // Get overall statistics
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Diverifikasi' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN kondisi_fisik = 'Rusak' THEN 1 ELSE 0 END) as needs_repair
                FROM laporan_irigasi
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get status distribution
            $stmt = $db->query("
                SELECT status, COUNT(*) as count
                FROM laporan_irigasi
                GROUP BY status
            ");
            $statusDist = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get recent activities
            $stmt = $db->query("
                SELECT li.id, li.nama_saluran, li.status, li.tanggal, u.nama_lengkap
                FROM laporan_irigasi li
                LEFT JOIN users u ON li.user_id = u.id
                ORDER BY li.created_at DESC
                LIMIT 5
            ");
            $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get sensor status overview
            $stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM pengairan_otomatis
                GROUP BY status
            ");
            $sensorStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get today's operations
            $stmt = $db->query("
                SELECT COUNT(*) as today_operations
                FROM irrigation_logs
                WHERE DATE(created_at) = CURDATE()
                AND action IN ('irrigation_start', 'irrigation_stop')
            ");
            $todayOps = $stmt->fetch(PDO::FETCH_ASSOC)['today_operations'] ?? 0;

            // Get active alerts
            $stmt = $db->query("
                SELECT COUNT(*) as active_alerts
                FROM irrigation_rule_logs
                WHERE execution_status = 'error'
                AND triggered_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $alerts = $stmt->fetch(PDO::FETCH_ASSOC)['active_alerts'] ?? 0;

            $response = [
                'overview' => $stats,
                'status_distribution' => $statusDist,
                'recent_activities' => $recentActivities,
                'sensor_status' => $sensorStatus,
                'today_operations' => $todayOps,
                'active_alerts' => $alerts,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->sendResponse($response, 'Ringkasan dashboard berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('dashboardSummary', $e);
        }
    }

    /**
     * Get analytics data for charts
     * GET /api/irigasi/{id}/analytics
     */
    public function analytics($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            $db = Database::getInstance()->getConnection();
            $rawDays = $_GET['days'] ?? 30;
            $days = filter_var($rawDays, FILTER_VALIDATE_INT);
            $days = ($days !== false && $days >= 1 && $days <= 365) ? $days : 30;

            // Get sensor trends
            $stmt = $db->prepare("
                SELECT 
                    DATE(ps.waktu_baca) as date,
                    sp.tipe_sensor as sensor_type,
                    AVG(ps.nilai) as avg_value,
                    MIN(ps.nilai) as min_value,
                    MAX(ps.nilai) as max_value
                FROM pembacaan_sensor ps
                JOIN sensor_pengairan sp ON ps.sensor_id = sp.id
                WHERE ps.waktu_baca >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(ps.waktu_baca), sp.tipe_sensor
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $sensorTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get irrigation history
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    action as action_type,
                    COUNT(*) as count
                FROM irrigation_logs
                WHERE irigasi_id = ?
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), action
                ORDER BY date ASC
            ");
            $stmt->execute([$id, $days]);
            $irrigationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get rule execution stats
            $stmt = $db->prepare("
                SELECT 
                    r.rule_name,
                    COUNT(rl.id) as executions,
                    SUM(CASE WHEN rl.execution_status = 'success' THEN 1 ELSE 0 END) as successful
                FROM irrigation_rules r
                LEFT JOIN irrigation_rule_logs rl ON r.id = rl.rule_id
                WHERE r.irigasi_id = ?
                GROUP BY r.id, r.rule_name
            ");
            $stmt->execute([$id]);
            $ruleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'sensor_trends' => $sensorTrends,
                'irrigation_history' => $irrigationHistory,
                'rule_statistics' => $ruleStats,
                'period_days' => $days,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->sendResponse($response, 'Data analitik berhasil diambil');
        } catch (Throwable $e) {
            $this->handleApiException('analytics', $e);
        }
    }

    /**
     * Dismiss a weather alert
     * POST /api/irigasi/alerts/{id}/dismiss
     */
    public function dismissAlert($id) {
        $this->assertAuthenticated();

        try {
            $id = $this->resolveId($id);

            require_once ROOT_PATH . '/app/services/WeatherService.php';
            $weatherService = new WeatherService();

            $success = $weatherService->dismissAlert($id);

            if ($success) {
                $this->sendResponse(['id' => $id], 'Alert berhasil dihilangkan');
            } else {
                $this->sendError('Gagal menghilangkan alert', 500);
            }
        } catch (Throwable $e) {
            $this->handleApiException('dismissAlert', $e);
        }
    }

    /**
     * Get irrigation recommendation based on multiplier
     */
    private function getIrrigationRecommendation(float $multiplier): string {
        if ($multiplier <= 0) {
            return 'Tidak perlu pengairan - prakiraan hujan lebat';
        } elseif ($multiplier < 0.5) {
            return 'Kurangi pengairan secara signifikan - hujan diperkirakan';
        } elseif ($multiplier < 0.8) {
            return 'Kurangi sedikit durasi pengairan';
        } elseif ($multiplier <= 1.1) {
            return 'Pengairan normal';
        } elseif ($multiplier <= 1.3) {
            return 'Pertimbangkan penambahan frekuensi pengairan - cuaca panas';
        } else {
            return 'Tingkatkan pengairan - kondisi kering ekstrem';
        }
    }
}