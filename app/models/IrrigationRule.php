<?php
/**
 * IrrigationRule Model
 * 
 * Model untuk mengelola konfigurasi rule otomasi pengairan.
 * Mendukung multi-condition rules dengan format JSON.
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class IrrigationRule extends Model {
    protected $table = 'irrigation_rules';
    
    /**
     * Get all active rules for a specific irigasi
     * 
     * @param int $irigasiId
     * @return array
     */
    public function getActiveRules(int $irigasiId): array {
        $qb = new QueryBuilder();
        return $qb->table($this->table)
            ->select(['*'])
            ->where('irigasi_id', $irigasiId)
            ->where('is_active', 1)
            ->orderBy('priority', 'DESC')
            ->get();
    }
    
    /**
     * Get all rules for a specific irigasi (including inactive)
     * 
     * @param int $irigasiId
     * @return array
     */
    public function getAllRulesForIrigasi(int $irigasiId): array {
        $qb = new QueryBuilder();
        return $qb->table($this->table . ' r')
            ->select([
                'r.*',
                'u.nama_lengkap as created_by_name'
            ])
            ->leftJoin('users u', 'r.created_by = u.id')
            ->where('r.irigasi_id', $irigasiId)
            ->orderBy('r.priority', 'DESC')
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get rule by ID with parsed JSON
     * 
     * @param int $id
     * @return array|null
     */
    public function getRuleById(int $id): ?array {
        $qb = new QueryBuilder();
        $result = $qb->table($this->table . ' r')
            ->select([
                'r.*',
                'u.nama_lengkap as created_by_name',
                'li.nama_saluran as irigasi_name'
            ])
            ->leftJoin('users u', 'r.created_by = u.id')
            ->leftJoin('laporan_irigasi li', 'r.irigasi_id = li.id')
            ->where('r.id', $id)
            ->limit(1)
            ->get();
        
        if (empty($result)) {
            return null;
        }
        
        $rule = $result[0];
        
        // Parse JSON fields
        $rule['conditions_parsed'] = json_decode($rule['conditions'], true);
        $rule['actions_parsed'] = json_decode($rule['actions'], true);
        
        return $rule;
    }
    
    /**
     * Create a new rule
     * 
     * @param array $data
     * @return int|false New rule ID or false on failure
     */
    public function createRule(array $data): int|false {
        // Validate required fields
        if (empty($data['irigasi_id']) || empty($data['rule_name']) || 
            empty($data['conditions']) || empty($data['actions'])) {
            return false;
        }
        
        // Ensure JSON fields are strings
        if (is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }
        if (is_array($data['actions'])) {
            $data['actions'] = json_encode($data['actions']);
        }
        
        // Set defaults
        $data['priority'] = $data['priority'] ?? 10;
        $data['is_active'] = $data['is_active'] ?? 1;
        $data['cooldown_minutes'] = $data['cooldown_minutes'] ?? 60;
        $data['execution_count'] = 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    /**
     * Update an existing rule
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateRule(int $id, array $data): bool {
        // Ensure JSON fields are strings
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }
        if (isset($data['actions']) && is_array($data['actions'])) {
            $data['actions'] = json_encode($data['actions']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($id, $data);
    }
    
    /**
     * Toggle rule active status
     * 
     * @param int $id
     * @param bool $active
     * @return bool
     */
    public function toggleRule(int $id, bool $active): bool {
        return $this->update($id, [
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Record rule execution
     * 
     * @param int $ruleId
     * @return bool
     */
    public function recordExecution(int $ruleId): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE {$this->table} 
            SET execution_count = execution_count + 1,
                last_executed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$ruleId]);
    }
    
    /**
     * Check if rule is on cooldown
     * 
     * @param int $ruleId
     * @return bool True if on cooldown
     */
    public function isOnCooldown(int $ruleId): bool {
        $rule = $this->find($ruleId);
        if (!$rule || !$rule['last_executed_at']) {
            return false;
        }
        
        $lastExecuted = strtotime($rule['last_executed_at']);
        $cooldownEnd = $lastExecuted + ($rule['cooldown_minutes'] * 60);
        
        return time() < $cooldownEnd;
    }
    
    /**
     * Get rule execution history
     * 
     * @param int $ruleId
     * @param int $limit
     * @return array
     */
    public function getExecutionHistory(int $ruleId, int $limit = 50): array {
        try {
            $qb = new QueryBuilder();
            return $qb->table('irrigation_rule_logs')
                ->select(['*'])
                ->where('rule_id', $ruleId)
                ->orderBy('triggered_at', 'DESC')
                ->limit($limit)
                ->get();
        } catch (\PDOException $e) {
            error_log('IrrigationRule::getExecutionHistory - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log rule execution
     * 
     * @param int $ruleId
     * @param int $irigasiId
     * @param array $conditionsSnapshot
     * @param array|null $actionsExecuted
     * @param string $status
     * @param int|null $durationMs
     * @param string|null $error
     * @param array|null $weatherData
     * @return int|false
     */
    public function logExecution(
        int $ruleId,
        int $irigasiId,
        array $conditionsSnapshot,
        ?array $actionsExecuted = null,
        string $status = 'success',
        ?int $durationMs = null,
        ?string $error = null,
        ?array $weatherData = null
    ): int|false {
        try {
            $db = Database::getInstance()->getConnection();
        } catch (\Throwable $e) {
            error_log('IrrigationRule::logExecution - ' . $e->getMessage());
            return false;
        }
        $stmt = $db->prepare("
            INSERT INTO irrigation_rule_logs 
            (rule_id, irigasi_id, conditions_snapshot, actions_executed, 
             execution_status, execution_duration_ms, error_message, weather_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $ruleId,
            $irigasiId,
            json_encode($conditionsSnapshot),
            $actionsExecuted ? json_encode($actionsExecuted) : null,
            $status,
            $durationMs,
            $error,
            $weatherData ? json_encode($weatherData) : null
        ]);
        
        if ($result) {
            return (int) $db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Get rules by priority for batch evaluation
     * 
     * @param int|null $irigasiId Filter by irigasi (null = all)
     * @return array
     */
    public function getRulesByPriority(?int $irigasiId = null): array {
        $qb = new QueryBuilder();
        $query = $qb->table($this->table)
            ->select(['*'])
            ->where('is_active', 1)
            ->orderBy('priority', 'DESC')
            ->orderBy('id', 'ASC');
        
        if ($irigasiId !== null) {
            $query->where('irigasi_id', $irigasiId);
        }
        
        return $query->get();
    }
    
    /**
     * Get rule templates (common rule configurations)
     * 
     * @return array
     */
    public function getTemplates(): array {
        return [
            [
                'name' => 'Pengairan Saat Kelembaban Rendah',
                'description' => 'Aktifkan pengairan otomatis ketika kelembaban tanah di bawah 35%',
                'conditions' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['sensor' => 'soil_moisture', 'operator' => '<', 'value' => 35],
                        ['time' => ['start' => '06:00', 'end' => '18:00']]
                    ]
                ],
                'actions' => [
                    'actions' => [
                        ['type' => 'irrigation_start', 'duration_minutes' => 30],
                        ['type' => 'log', 'category' => 'automation']
                    ]
                ]
            ],
            [
                'name' => 'Skip Pengairan Saat Hujan',
                'description' => 'Matikan pengairan jika prakiraan hujan tinggi',
                'conditions' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['weather' => 'precipitation', 'operator' => '>', 'value' => 10],
                        ['sensor' => 'soil_moisture', 'operator' => '>', 'value' => 50]
                    ]
                ],
                'actions' => [
                    'actions' => [
                        ['type' => 'irrigation_stop'],
                        ['type' => 'alert', 'level' => 'info', 'message' => 'Pengairan dihentikan - prakiraan hujan']
                    ]
                ]
            ],
            [
                'name' => 'Alert pH Abnormal',
                'description' => 'Kirim alert jika pH air di luar rentang normal',
                'conditions' => [
                    'operator' => 'OR',
                    'conditions' => [
                        ['sensor' => 'water_ph', 'operator' => '<', 'value' => 6.0],
                        ['sensor' => 'water_ph', 'operator' => '>', 'value' => 7.5]
                    ]
                ],
                'actions' => [
                    'actions' => [
                        ['type' => 'alert', 'level' => 'warning', 'message' => 'pH air abnormal - perlu pengecekan'],
                        ['type' => 'log', 'category' => 'alert']
                    ]
                ]
            ],
            [
                'name' => 'Pengairan Terjadwal',
                'description' => 'Pengairan rutin setiap hari pada jam tertentu',
                'conditions' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['time' => ['start' => '06:00', 'end' => '07:00']],
                        ['sensor' => 'soil_moisture', 'operator' => '<', 'value' => 70]
                    ]
                ],
                'actions' => [
                    'actions' => [
                        ['type' => 'irrigation_start', 'duration_minutes' => 20],
                        ['type' => 'log', 'category' => 'scheduled']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get statistics for rules
     * 
     * @param int|null $irigasiId
     * @return array
     */
    public function getStatistics(?int $irigasiId = null): array {
        try {
            $db = Database::getInstance()->getConnection();
        } catch (\Throwable $e) {
            error_log('IrrigationRule::getStatistics - ' . $e->getMessage());
            return [];
        }
        
        $whereClause = $irigasiId ? "WHERE irigasi_id = ?" : "";
        $params = $irigasiId ? [$irigasiId] : [];
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_rules,
                    SUM(is_active) as active_rules,
                    SUM(execution_count) as total_executions,
                    MAX(last_executed_at) as last_execution
                FROM {$this->table}
                {$whereClause}
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('IrrigationRule::getStatistics(rules) - ' . $e->getMessage());
            $stats = [];
        }
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as executions_24h,
                    SUM(CASE WHEN execution_status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN execution_status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM irrigation_rule_logs
                WHERE triggered_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                " . ($irigasiId ? "AND irigasi_id = ?" : "")
            );
            $stmt->execute($params);
            $execStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('IrrigationRule::getStatistics(logs) - ' . $e->getMessage());
            $execStats = [];
        }
        
        return array_merge($stats, $execStats);
    }
}
