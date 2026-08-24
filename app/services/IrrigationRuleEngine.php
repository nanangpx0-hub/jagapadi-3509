<?php
/**
 * Irrigation Rule Engine
 * 
 * Service untuk mengevaluasi dan mengeksekusi rule otomasi pengairan.
 * Mendukung multi-condition evaluation dengan integrasi weather data.
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class IrrigationRuleEngine {
    
    private IrrigationRule $ruleModel;
    private WeatherService $weatherService;
    private $db;
    private $debug = false;
    private $logFile;
    
    // Supported condition operators
    private const OPERATORS = ['=', '==', '!=', '<>', '<', '>', '<=', '>=', 'BETWEEN', 'IN', 'NOT IN'];
    
    // Logical operators
    private const LOGICAL_OPS = ['AND', 'OR'];
    
    // Sensor types mapping
    private const SENSOR_TYPES = [
        'soil_moisture' => ['unit' => '%', 'range' => [0, 100]],
        'water_ph' => ['unit' => 'pH', 'range' => [0, 14]],
        'water_flow' => ['unit' => 'L/s', 'range' => [0, 1000]],
        'temperature' => ['unit' => '°C', 'range' => [-10, 50]],
        'humidity' => ['unit' => '%', 'range' => [0, 100]],
        'water_level' => ['unit' => 'cm', 'range' => [0, 500]],
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logFile = ROOT_PATH . '/logs/rule_engine.log';
        
        // Load dependencies
        require_once ROOT_PATH . '/app/models/IrrigationRule.php';
        require_once ROOT_PATH . '/app/services/WeatherService.php';
        
        $this->ruleModel = new IrrigationRule();
        $this->weatherService = new WeatherService();
    }
    
    /**
     * Evaluate all active rules for an irigasi
     * 
     * @param int $irigasiId
     * @return array Results with triggered rules and actions
     */
    public function evaluateRules(int $irigasiId): array {
        $startTime = microtime(true);
        $this->log("Starting rule evaluation for irigasi #{$irigasiId}");
        
        $results = [
            'irigasi_id' => $irigasiId,
            'evaluated_at' => date('Y-m-d H:i:s'),
            'rules_evaluated' => 0,
            'rules_triggered' => 0,
            'actions_executed' => [],
            'skipped_rules' => [],
            'errors' => [],
        ];
        
        // Get active rules
        $rules = $this->ruleModel->getActiveRules($irigasiId);
        $results['rules_evaluated'] = count($rules);
        
        if (empty($rules)) {
            $this->log("No active rules found for irigasi #{$irigasiId}");
            return $results;
        }
        
        // Get current sensor data
        $sensorData = $this->getSensorData($irigasiId);
        
        // Get current weather
        $weatherData = $this->weatherService->getCurrentConditions($irigasiId);
        
        // Evaluate each rule
        foreach ($rules as $rule) {
            try {
                // Check cooldown
                if ($this->ruleModel->isOnCooldown($rule['id'])) {
                    $results['skipped_rules'][] = [
                        'id' => $rule['id'],
                        'name' => $rule['rule_name'],
                        'reason' => 'On cooldown'
                    ];
                    continue;
                }
                
                // Parse conditions
                $conditions = json_decode($rule['conditions'], true);
                
                // Evaluate conditions
                $triggered = $this->evaluateConditions($conditions, $sensorData, $weatherData);
                
                if ($triggered) {
                    $results['rules_triggered']++;
                    $this->log("Rule triggered: {$rule['rule_name']} (#{$rule['id']})");
                    
                    // Parse and execute actions
                    $actions = json_decode($rule['actions'], true);
                    $executedActions = $this->executeActions($actions, $irigasiId, $rule);
                    
                    $results['actions_executed'][] = [
                        'rule_id' => $rule['id'],
                        'rule_name' => $rule['rule_name'],
                        'actions' => $executedActions
                    ];
                    
                    // Record execution
                    $this->ruleModel->recordExecution($rule['id']);
                    
                    // Log to database
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                    $this->ruleModel->logExecution(
                        $rule['id'],
                        $irigasiId,
                        array_merge($sensorData, ['weather' => $weatherData]),
                        $executedActions,
                        'success',
                        $durationMs,
                        null,
                        $weatherData
                    );
                }
                
            } catch (Exception $e) {
                $this->log("Error evaluating rule #{$rule['id']}: " . $e->getMessage(), 'ERROR');
                $results['errors'][] = [
                    'rule_id' => $rule['id'],
                    'error' => $e->getMessage()
                ];
                
                // Log failed execution
                $this->ruleModel->logExecution(
                    $rule['id'],
                    $irigasiId,
                    $sensorData,
                    null,
                    'failed',
                    null,
                    $e->getMessage()
                );
            }
        }
        
        $results['execution_time_ms'] = (int) ((microtime(true) - $startTime) * 1000);
        $this->log("Evaluation complete: {$results['rules_triggered']}/{$results['rules_evaluated']} rules triggered");
        
        return $results;
    }
    
    /**
     * Evaluate conditions recursively
     * 
     * @param array $conditions Conditions configuration
     * @param array $sensorData Current sensor values
     * @param array $weatherData Current weather conditions
     * @return bool
     */
    public function evaluateConditions(array $conditions, array $sensorData, array $weatherData): bool {
        $operator = strtoupper($conditions['operator'] ?? 'AND');
        $conditionList = $conditions['conditions'] ?? [];
        
        if (empty($conditionList)) {
            return true; // No conditions = always true
        }
        
        $results = [];
        
        foreach ($conditionList as $condition) {
            // Handle nested conditions
            if (isset($condition['operator']) && isset($condition['conditions'])) {
                $results[] = $this->evaluateConditions($condition, $sensorData, $weatherData);
                continue;
            }
            
            // Handle sensor conditions
            if (isset($condition['sensor'])) {
                $results[] = $this->evaluateSensorCondition($condition, $sensorData);
                continue;
            }
            
            // Handle weather conditions
            if (isset($condition['weather'])) {
                $results[] = $this->evaluateWeatherCondition($condition, $weatherData);
                continue;
            }
            
            // Handle time conditions
            if (isset($condition['time'])) {
                $results[] = $this->evaluateTimeCondition($condition['time']);
                continue;
            }
        }
        
        if (empty($results)) {
            return true;
        }
        
        // Apply logical operator
        if ($operator === 'AND') {
            return !in_array(false, $results, true);
        } else { // OR
            return in_array(true, $results, true);
        }
    }
    
    /**
     * Evaluate a single sensor condition
     */
    private function evaluateSensorCondition(array $condition, array $sensorData): bool {
        $sensorType = $condition['sensor'];
        $operator = $condition['operator'];
        $targetValue = $condition['value'] ?? null;
        
        // Get current sensor value
        $currentValue = $sensorData[$sensorType] ?? null;
        
        if ($currentValue === null) {
            $this->log("Sensor data not available: {$sensorType}", 'WARNING');
            return false; // No data = condition not met
        }
        
        return $this->compareValues($currentValue, $operator, $targetValue, 
            $condition['min'] ?? null, $condition['max'] ?? null);
    }
    
    /**
     * Evaluate a weather condition
     */
    private function evaluateWeatherCondition(array $condition, array $weatherData): bool {
        $weatherField = $condition['weather'];
        $operator = $condition['operator'];
        $targetValue = $condition['value'] ?? null;
        
        $currentValue = null;
        
        switch ($weatherField) {
            case 'precipitation':
            case 'rain':
                $currentValue = $weatherData['precipitation'] ?? 0;
                break;
            case 'forecast':
            case 'category':
                $currentValue = $weatherData['category'] ?? 'unknown';
                break;
            case 'temperature':
            case 'temp_max':
                $currentValue = $weatherData['temperature_max'] ?? null;
                break;
            case 'temp_min':
                $currentValue = $weatherData['temperature_min'] ?? null;
                break;
            default:
                $currentValue = $weatherData[$weatherField] ?? null;
        }
        
        if ($currentValue === null) {
            return false;
        }
        
        return $this->compareValues($currentValue, $operator, $targetValue);
    }
    
    /**
     * Evaluate a time condition
     */
    private function evaluateTimeCondition(array $timeConfig): bool {
        $now = new DateTime();
        $currentTime = $now->format('H:i');
        $currentDay = $now->format('D');
        
        // Check time range
        if (isset($timeConfig['start']) && isset($timeConfig['end'])) {
            $start = $timeConfig['start'];
            $end = $timeConfig['end'];
            
            if ($start <= $end) {
                // Same day range
                if ($currentTime < $start || $currentTime > $end) {
                    return false;
                }
            } else {
                // Crosses midnight
                if ($currentTime < $start && $currentTime > $end) {
                    return false;
                }
            }
        }
        
        // Check days of week
        if (isset($timeConfig['days']) && !empty($timeConfig['days'])) {
            if (!in_array($currentDay, $timeConfig['days'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Compare values using operator
     */
    private function compareValues($current, string $operator, $target, $min = null, $max = null): bool {
        switch (strtoupper($operator)) {
            case '=':
            case '==':
                return $current == $target;
            case '!=':
            case '<>':
                return $current != $target;
            case '<':
                return $current < $target;
            case '>':
                return $current > $target;
            case '<=':
                return $current <= $target;
            case '>=':
                return $current >= $target;
            case 'BETWEEN':
                return $current >= $min && $current <= $max;
            case 'IN':
                return is_array($target) && in_array($current, $target);
            case 'NOT IN':
                return is_array($target) && !in_array($current, $target);
            default:
                $this->log("Unknown operator: {$operator}", 'WARNING');
                return false;
        }
    }
    
    /**
     * Execute rule actions
     * 
     * @param array $actionsConfig
     * @param int $irigasiId
     * @param array $rule
     * @return array Executed actions
     */
    public function executeActions(array $actionsConfig, int $irigasiId, array $rule): array {
        $actions = $actionsConfig['actions'] ?? [];
        $executed = [];
        
        foreach ($actions as $action) {
            $type = $action['type'] ?? 'unknown';
            $result = ['type' => $type, 'status' => 'pending'];
            
            try {
                switch ($type) {
                    case 'irrigation_start':
                        $result = $this->actionIrrigationStart($irigasiId, $action);
                        break;
                        
                    case 'irrigation_stop':
                        $result = $this->actionIrrigationStop($irigasiId, $action);
                        break;
                        
                    case 'alert':
                        $result = $this->actionAlert($irigasiId, $action, $rule);
                        break;
                        
                    case 'log':
                        $result = $this->actionLog($irigasiId, $action, $rule);
                        break;
                        
                    case 'notification':
                        $result = $this->actionNotification($irigasiId, $action, $rule);
                        break;
                        
                    case 'adjust_threshold':
                        $result = $this->actionAdjustThreshold($irigasiId, $action);
                        break;
                        
                    default:
                        $this->log("Unknown action type: {$type}", 'WARNING');
                        $result = ['type' => $type, 'status' => 'skipped', 'reason' => 'Unknown action'];
                }
            } catch (Exception $e) {
                $result = ['type' => $type, 'status' => 'failed', 'error' => $e->getMessage()];
            }
            
            $executed[] = $result;
        }
        
        return $executed;
    }
    
    // =========================================================================
    // Action Handlers
    // =========================================================================
    
    private function actionIrrigationStart(int $irigasiId, array $action): array {
        $duration = $action['duration_minutes'] ?? 30;
        $intensity = $action['intensity'] ?? 'medium';
        
        // Log irrigation start
        $this->logIrrigationAction($irigasiId, 'irrigation_start', [
            'duration' => $duration,
            'intensity' => $intensity,
            'triggered_by' => 'automatic'
        ]);
        
        // In production, this would send MQTT command to actuator
        // For now, just log it
        $this->log("ACTION: Start irrigation on #{$irigasiId} for {$duration} minutes");
        
        return [
            'type' => 'irrigation_start',
            'status' => 'executed',
            'duration_minutes' => $duration,
            'intensity' => $intensity
        ];
    }
    
    private function actionIrrigationStop(int $irigasiId, array $action): array {
        // Log irrigation stop
        $this->logIrrigationAction($irigasiId, 'irrigation_stop', [
            'triggered_by' => 'automatic',
            'reason' => $action['reason'] ?? 'Rule triggered'
        ]);
        
        $this->log("ACTION: Stop irrigation on #{$irigasiId}");
        
        return [
            'type' => 'irrigation_stop',
            'status' => 'executed'
        ];
    }
    
    private function actionAlert(int $irigasiId, array $action, array $rule): array {
        $level = $action['level'] ?? 'info';
        $message = $action['message'] ?? "Rule '{$rule['rule_name']}' triggered";
        
        // Insert into system alerts or notifications
        $stmt = $this->db->prepare("
            INSERT INTO system_alerts (alert_type, severity, title, message, related_id, related_type)
            VALUES ('irrigation_rule', ?, ?, ?, ?, 'irigasi')
        ");
        $stmt->execute([$level, "Rule Alert: {$rule['rule_name']}", $message, $irigasiId]);
        
        return [
            'type' => 'alert',
            'status' => 'executed',
            'level' => $level,
            'message' => $message
        ];
    }
    
    private function actionLog(int $irigasiId, array $action, array $rule): array {
        $category = $action['category'] ?? 'automation';
        
        $this->logIrrigationAction($irigasiId, 'rule_triggered', [
            'rule_id' => $rule['id'],
            'rule_name' => $rule['rule_name'],
            'category' => $category
        ]);
        
        return [
            'type' => 'log',
            'status' => 'executed',
            'category' => $category
        ];
    }
    
    private function actionNotification(int $irigasiId, array $action, array $rule): array {
        // Would send email/SMS/push notification
        // For now, just log
        $this->log("NOTIFICATION: Rule '{$rule['rule_name']}' triggered for irigasi #{$irigasiId}");
        
        return [
            'type' => 'notification',
            'status' => 'queued',
            'recipients' => $action['recipients'] ?? ['admin']
        ];
    }
    
    private function actionAdjustThreshold(int $irigasiId, array $action): array {
        $sensorType = $action['sensor'] ?? 'soil_moisture';
        $adjustment = $action['adjustment'] ?? 0;
        $adjustType = $action['adjust_type'] ?? 'min'; // 'min', 'max', 'both'
        
        // This would update the adaptive thresholds
        $this->log("ADJUST: Threshold for {$sensorType} on #{$irigasiId} by {$adjustment}");
        
        return [
            'type' => 'adjust_threshold',
            'status' => 'executed',
            'sensor' => $sensorType,
            'adjustment' => $adjustment
        ];
    }
    
    // =========================================================================
    // Helper Methods
    // =========================================================================
    
    /**
     * Get current sensor data for an irigasi
     */
    private function getSensorData(int $irigasiId): array {
        $stmt = $this->db->prepare("
            SELECT sensor_type, sensor_value, last_reading
            FROM pengairan_otomatis
            WHERE irigasi_id = ? AND status = 'active'
        ");
        $stmt->execute([$irigasiId]);
        $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($sensors as $sensor) {
            $data[$sensor['sensor_type']] = (float) $sensor['sensor_value'];
        }
        
        // Also get from data_irigasi for debit info
        $stmt = $this->db->prepare("
            SELECT debit_air, status_pintu
            FROM data_irigasi
            WHERE daerah_irigasi = (
                SELECT nama_saluran FROM laporan_irigasi WHERE id = ? AND deleted_at IS NULL
            )
            ORDER BY tanggal DESC
            LIMIT 1
        ");
        $stmt->execute([$irigasiId]);
        $irigasiData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($irigasiData) {
            $data['water_flow'] = (float) ($irigasiData['debit_air'] ?? 0);
            $data['status'] = $irigasiData['status_pintu'] ?? 'Unknown';
        }
        
        return $data;
    }
    
    /**
     * Log irrigation action to irrigation_logs
     */
    private function logIrrigationAction(int $irigasiId, string $actionType, array $data): void {
        // Check if table exists
        try {
            $stmt = $this->db->prepare("
                INSERT INTO irrigation_logs 
                (irigasi_id, action_type, action_data, triggered_by)
                VALUES (?, ?, ?, 'automatic')
            ");
            $stmt->execute([$irigasiId, $actionType, json_encode($data)]);
        } catch (PDOException $e) {
            // Table might not exist, just log to file
            $this->log("Could not log to irrigation_logs: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Get adaptive thresholds for an irigasi
     */
    public function getAdaptiveThresholds(int $irigasiId): array {
        $thresholds = [];
        
        foreach (array_keys(self::SENSOR_TYPES) as $sensorType) {
            $thresholds[$sensorType] = $this->weatherService->getAdjustedThresholds($irigasiId, $sensorType);
        }
        
        return $thresholds;
    }
    
    /**
     * Manually trigger a rule for testing
     */
    public function manualTrigger(int $ruleId): array {
        $rule = $this->ruleModel->getRuleById($ruleId);
        
        if (!$rule) {
            return ['success' => false, 'error' => 'Rule not found'];
        }
        
        $actions = json_decode($rule['actions'], true);
        $executed = $this->executeActions($actions, $rule['irigasi_id'], $rule);
        
        $this->ruleModel->logExecution(
            $ruleId,
            $rule['irigasi_id'],
            ['manual_trigger' => true],
            $executed,
            'success',
            null,
            null
        );
        
        return [
            'success' => true,
            'rule' => $rule['rule_name'],
            'actions_executed' => $executed
        ];
    }
    
    /**
     * Validate rule configuration
     */
    public function validateRule(array $conditions, array $actions): array {
        $errors = [];
        
        // Validate conditions
        if (!isset($conditions['operator']) || !in_array(strtoupper($conditions['operator']), self::LOGICAL_OPS)) {
            $errors[] = "Invalid logical operator. Must be 'AND' or 'OR'";
        }
        
        if (!isset($conditions['conditions']) || !is_array($conditions['conditions'])) {
            $errors[] = "Conditions must be an array";
        } else {
            foreach ($conditions['conditions'] as $i => $cond) {
                if (isset($cond['sensor']) && !isset(self::SENSOR_TYPES[$cond['sensor']])) {
                    $errors[] = "Unknown sensor type at condition {$i}: {$cond['sensor']}";
                }
                if (isset($cond['operator']) && !in_array($cond['operator'], self::OPERATORS) && !in_array(strtoupper($cond['operator']), self::LOGICAL_OPS)) {
                    $errors[] = "Invalid operator at condition {$i}: {$cond['operator']}";
                }
            }
        }
        
        // Validate actions
        if (!isset($actions['actions']) || !is_array($actions['actions'])) {
            $errors[] = "Actions must be an array";
        } else {
            $validActionTypes = ['irrigation_start', 'irrigation_stop', 'alert', 'log', 'notification', 'adjust_threshold'];
            foreach ($actions['actions'] as $i => $action) {
                if (!isset($action['type']) || !in_array($action['type'], $validActionTypes)) {
                    $errors[] = "Invalid action type at action {$i}";
                }
            }
        }
        
        return $errors;
    }
    
    private function log(string $message, string $level = 'INFO'): void {
        $logEntry = sprintf(
            "[%s] [%s] [RuleEngine] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        if ($this->debug) {
            echo $logEntry;
        }
    }
    
    public function setDebug(bool $enabled): void {
        $this->debug = $enabled;
        $this->weatherService->setDebug($enabled);
    }
}
