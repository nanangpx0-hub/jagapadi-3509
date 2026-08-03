<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/models/Irigasi.php';

class IrigasiController extends BaseApiController {
    
    private $irigasiModel;
    
    public function __construct() {
        $this->irigasiModel = new Irigasi();
    }
    
    /**
     * Get all irigasi data with pagination and filters
     * GET /api/irigasi
     */
    public function index() {
        try {
            $pagination = $this->getPaginationParams();
            
            // Get filters
            $filters = [
                'status_kondisi' => $_GET['status_kondisi'] ?? null,
                'kabupaten_id' => $_GET['kabupaten_id'] ?? null,
                'kecamatan_id' => $_GET['kecamatan_id'] ?? null,
                'desa_id' => $_GET['desa_id'] ?? null,
                'jenis_irigasi' => $_GET['jenis_irigasi'] ?? null,
                'user_id' => $_GET['user_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            
            // Remove null filters
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            // Apply user-based filtering for petugas
            if ($_SESSION['role'] === 'petugas') {
                $filters['user_id'] = $_SESSION['user_id'];
            }
            
            // Get data
            $irigasi = $this->irigasiModel->getAllWithFilters($filters, $pagination['limit'], $pagination['offset']);
            $total = $this->irigasiModel->getCountWithFilters($filters);
            
            // Format response
            $response = $this->formatPaginatedResponse($irigasi, $total, $pagination['page'], $pagination['limit']);
            
            $this->sendResponse($response, 'Irigasi data retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve irigasi data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get specific irigasi by ID
     * GET /api/irigasi/{id}
     */
    public function show($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
            $irigasi = $this->irigasiModel->getById($id);
            
            if (!$irigasi) {
                $this->sendError('Irigasi not found', 404);
            }
            
            // Check permission for petugas
            if ($_SESSION['role'] === 'petugas' && $irigasi['user_id'] != $_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }
            
            $this->sendResponse($irigasi, 'Irigasi data retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve irigasi data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new irigasi data
     * POST /api/irigasi
     */
    public function store() {
        try {
            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);
            
            // Validate required fields
            $requiredFields = [
                'nama_irigasi', 'jenis_irigasi', 'kabupaten_id', 'kecamatan_id', 
                'desa_id', 'alamat_lengkap', 'status_kondisi'
            ];
            
            $errors = $this->validateRequired($data, $requiredFields);
            if (!empty($errors)) {
                $this->sendError('Validation failed', 422, $errors);
            }
            
            // Set user_id from session
            $data['user_id'] = $_SESSION['user_id'];
            
            // Set default values
            $data['luas_layanan'] = $data['luas_layanan'] ?? 0;
            $data['debit_air'] = $data['debit_air'] ?? 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Handle file upload if present
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $data['foto'] = $this->handleFileUpload($_FILES['foto'], 'irigasi');
            }
            
            $irigasiId = $this->irigasiModel->create($data);
            
            if ($irigasiId) {
                $irigasi = $this->irigasiModel->getById($irigasiId);
                $this->sendResponse($irigasi, 'Irigasi data created successfully', 201);
            } else {
                $this->sendError('Failed to create irigasi data', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to create irigasi data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update irigasi data
     * PUT /api/irigasi/{id}
     */
    public function update($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
            $existingIrigasi = $this->irigasiModel->getById($id);
            if (!$existingIrigasi) {
                $this->sendError('Irigasi not found', 404);
            }
            
            // Check permission
            if ($_SESSION['role'] === 'petugas' && $existingIrigasi['user_id'] != $_SESSION['user_id']) {
                $this->sendError('Forbidden', 403);
            }
            
            $data = $this->getRequestData();
            $data = $this->sanitizeData($data);
            
            // Set updated timestamp
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Handle file upload if present
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $data['foto'] = $this->handleFileUpload($_FILES['foto'], 'irigasi');
            }
            
            $success = $this->irigasiModel->update($id, $data);
            
            if ($success) {
                $irigasi = $this->irigasiModel->getById($id);
                $this->sendResponse($irigasi, 'Irigasi data updated successfully');
            } else {
                $this->sendError('Failed to update irigasi data', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to update irigasi data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete irigasi data
     * DELETE /api/irigasi/{id}
     */
    public function destroy($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
            $existingIrigasi = $this->irigasiModel->getById($id);
            if (!$existingIrigasi) {
                $this->sendError('Irigasi not found', 404);
            }
            
            // Check permission - only admin can delete
            if ($_SESSION['role'] !== 'admin') {
                $this->sendError('Forbidden', 403);
            }
            
            $success = $this->irigasiModel->delete($id);
            
            if ($success) {
                $this->sendResponse(null, 'Irigasi data deleted successfully');
            } else {
                $this->sendError('Failed to delete irigasi data', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to delete irigasi data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get irigasi statistics
     * GET /api/irigasi/stats
     */
    public function getStats() {
        try {
            $stats = $this->irigasiModel->getStatistics();
            $this->sendResponse($stats, 'Irigasi statistics retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve irigasi statistics: ' . $e->getMessage(), 500);
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
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
            // Get irigasi data
            $irigasi = $this->irigasiModel->getById($id);
            if (!$irigasi) {
                $this->sendError('Irigasi not found', 404);
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
            
            $this->sendResponse($response, 'Monitoring data retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve monitoring data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get weather data for an irigasi location
     * GET /api/irigasi/{id}/weather
     */
    public function weather($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
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
            
            $this->sendResponse($response, 'Weather data retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve weather data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get irrigation rules for an irigasi
     * GET /api/irigasi/{id}/rules
     */
    public function getRules($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
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
            
            $this->sendResponse($response, 'Rules retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve rules: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create a new rule
     * POST /api/irigasi/rules
     */
    public function createRule() {
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
            $conditions = $data['conditions'] ?? ['operator' => 'AND', 'conditions' => []];
            $actions = $data['actions'] ?? ['actions' => []];
            
            $errors = $engine->validateRule($conditions, $actions);
            if (!empty($errors)) {
                $this->sendError('Invalid rule configuration', 422, $errors);
            }
            
            // Create rule
            $ruleData = [
                'irigasi_id' => $data['irigasi_id'],
                'rule_name' => $data['rule_name'],
                'description' => $data['description'] ?? null,
                'conditions' => $conditions,
                'actions' => $actions,
                'priority' => $data['priority'] ?? 10,
                'is_active' => $data['is_active'] ?? 1,
                'cooldown_minutes' => $data['cooldown_minutes'] ?? 60,
                'created_by' => $_SESSION['user_id']
            ];
            
            $ruleId = $ruleModel->createRule($ruleData);
            
            if ($ruleId) {
                $rule = $ruleModel->getRuleById($ruleId);
                $this->sendResponse($rule, 'Rule created successfully', 201);
            } else {
                $this->sendError('Failed to create rule', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to create rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update a rule
     * PUT /api/irigasi/rules/{id}
     */
    public function updateRule($id) {
        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }
            
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid rule ID', 400);
            }
            
            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();
            
            $rule = $ruleModel->getRuleById($id);
            if (!$rule) {
                $this->sendError('Rule not found', 404);
            }
            
            $data = $this->getRequestData();
            
            // Update fields
            $updateData = [];
            if (isset($data['rule_name'])) $updateData['rule_name'] = $data['rule_name'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];
            if (isset($data['conditions'])) $updateData['conditions'] = $data['conditions'];
            if (isset($data['actions'])) $updateData['actions'] = $data['actions'];
            if (isset($data['priority'])) $updateData['priority'] = $data['priority'];
            if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
            if (isset($data['cooldown_minutes'])) $updateData['cooldown_minutes'] = $data['cooldown_minutes'];
            
            $success = $ruleModel->updateRule($id, $updateData);
            
            if ($success) {
                $rule = $ruleModel->getRuleById($id);
                $this->sendResponse($rule, 'Rule updated successfully');
            } else {
                $this->sendError('Failed to update rule', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to update rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Toggle rule active status
     * POST /api/irigasi/rules/{id}/toggle
     */
    public function toggleRule($id) {
        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }
            
            require_once ROOT_PATH . '/app/models/IrrigationRule.php';
            $ruleModel = new IrrigationRule();
            
            $rule = $ruleModel->getRuleById($id);
            if (!$rule) {
                $this->sendError('Rule not found', 404);
            }
            
            $newStatus = !$rule['is_active'];
            $success = $ruleModel->toggleRule($id, $newStatus);
            
            if ($success) {
                $this->sendResponse([
                    'id' => $id,
                    'is_active' => $newStatus
                ], 'Rule ' . ($newStatus ? 'activated' : 'deactivated') . ' successfully');
            } else {
                $this->sendError('Failed to toggle rule', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to toggle rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Manually execute a rule
     * POST /api/irigasi/rules/{id}/execute
     */
    public function executeRule($id) {
        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }
            
            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';
            $engine = new IrrigationRuleEngine();
            
            $result = $engine->manualTrigger($id);
            
            $this->sendResponse($result, $result['success'] ? 'Rule executed successfully' : 'Rule execution failed');
            
        } catch (Exception $e) {
            $this->sendError('Failed to execute rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Evaluate all rules for an irigasi
     * POST /api/irigasi/{id}/evaluate-rules
     */
    public function evaluateRules($id) {
        try {
            if (!in_array($_SESSION['role'], ['admin', 'operator'])) {
                $this->sendError('Forbidden', 403);
            }
            
            require_once ROOT_PATH . '/app/services/IrrigationRuleEngine.php';
            $engine = new IrrigationRuleEngine();
            
            $results = $engine->evaluateRules($id);
            
            $this->sendResponse($results, 'Rules evaluated successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to evaluate rules: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get dashboard summary for all irigasi
     * GET /api/irigasi/dashboard-summary
     */
    public function dashboardSummary() {
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
            
            $this->sendResponse($response, 'Dashboard summary retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve dashboard summary: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get analytics data for charts
     * GET /api/irigasi/{id}/analytics
     */
    public function analytics($id) {
        try {
            if (!$id || !is_numeric($id)) {
                $this->sendError('Invalid irigasi ID', 400);
            }
            
            $db = Database::getInstance()->getConnection();
            $days = $_GET['days'] ?? 30;
            
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
            
            $this->sendResponse($response, 'Analytics data retrieved successfully');
            
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve analytics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Dismiss a weather alert
     * POST /api/irigasi/alerts/{id}/dismiss
     */
    public function dismissAlert($id) {
        try {
            require_once ROOT_PATH . '/app/services/WeatherService.php';
            $weatherService = new WeatherService();
            
            $success = $weatherService->dismissAlert($id);
            
            if ($success) {
                $this->sendResponse(['id' => $id], 'Alert dismissed successfully');
            } else {
                $this->sendError('Failed to dismiss alert', 500);
            }
            
        } catch (Exception $e) {
            $this->sendError('Failed to dismiss alert: ' . $e->getMessage(), 500);
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