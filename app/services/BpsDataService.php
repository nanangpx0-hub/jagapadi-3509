<?php
/**
 * BPS Data Service
 * Core service untuk validasi, konversi, dan penyimpanan data pertanian BPS
 * 
 * Responsibilities:
 * - Validasi rentang wajar data (produktivitas, luas, produksi)
 * - Konversi GKG ke beras (57.7%)
 * - Perhitungan produktivitas
 * - Upsert ke database
 * - Logging aktivitas
 * - Anomaly detection
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class BpsDataService {
    
    private $db;
    private $model;
    private $logFile;
    
    // Gabah to Beras conversion rate (BPS standard)
    private const CONVERSION_RATE = 0.577;
    
    // Validation thresholds
    private const VALIDATION_RULES = [
        'produktivitas' => ['min' => 30, 'max' => 80], // ku/ha
        'luas_panen' => ['min' => 100, 'max' => 200000], // ha
        'produksi_gabah' => ['min' => 500, 'max' => 1200000], // ton
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
        $this->model = new DataPertanianBps();
        $this->db = Database::getInstance()->getConnection();
        $this->logFile = ROOT_PATH . '/logs/bps_data_service.log';
    }
    
    /**
     * Process and save records with full validation
     * 
     * @param array $records Raw records from simulation or API
     * @param array $options Processing options
     * @return array Result with success/failed counts
     */
    public function processRecords($records, $options = []) {
        $forceRefresh = $options['force_refresh'] ?? false;
        $skipValidation = $options['skip_validation'] ?? false;
        
        $result = [
            'success' => false,
            'records_success' => 0,
            'records_failed' => 0,
            'records_skipped' => 0,
            'anomalies' => [],
            'errors' => []
        ];
        
        foreach ($records as $record) {
            try {
                // Apply conversions
                $record = $this->applyConversions($record);
                
                // Validate if not skipped
                if (!$skipValidation) {
                    $validation = $this->validateRecord($record);
                    
                    if (!$validation['valid']) {
                        // Record anomaly but still save
                        $result['anomalies'][] = [
                            'kabupaten' => $record['kabupaten_kota'],
                            'issues' => $validation['issues']
                        ];
                        $record['is_validated'] = false;
                        $record['validation_notes'] = implode('; ', $validation['issues']);
                    } else {
                        $record['is_validated'] = true;
                    }
                }
                
                // Check existing
                $existing = $this->model->getByYearAndKabupaten(
                    $record['tahun'], 
                    $record['kabupaten_kota']
                );
                
                if ($existing && !$forceRefresh) {
                    $result['records_skipped']++;
                    continue;
                }
                
                // Save record
                if ($this->saveRecord($record, $existing)) {
                    $result['records_success']++;
                    
                    // Log anomaly separately if detected
                    if (!empty($record['validation_notes'])) {
                        $this->logAnomaly($record, $validation['issues'] ?? []);
                    }
                } else {
                    $result['records_failed']++;
                }
                
            } catch (Exception $e) {
                $result['records_failed']++;
                $result['errors'][] = [
                    'kabupaten' => $record['kabupaten_kota'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
                $this->log("Error processing record: " . $e->getMessage(), 'ERROR');
            }
        }
        
        $result['success'] = $result['records_success'] > 0;
        
        // Update yearly summary if any records saved
        if ($result['records_success'] > 0 && !empty($records)) {
            $tahun = $records[0]['tahun'] ?? date('Y');
            $this->updateYearlySummary($tahun);
        }
        
        return $result;
    }
    
    /**
     * Apply conversions to record
     * - Calculate produksi_beras from produksi_gabah
     * - Calculate produktivitas if not present
     * 
     * @param array $record
     * @return array
     */
    public function applyConversions($record) {
        // Convert gabah to beras
        if (isset($record['produksi_gabah'])) {
            $record['produksi_beras'] = round($record['produksi_gabah'] * self::CONVERSION_RATE);
        }
        
        // Calculate produktivitas if missing
        if ((!isset($record['produktivitas']) || $record['produktivitas'] == 0) 
            && isset($record['luas_panen']) && $record['luas_panen'] > 0
            && isset($record['produksi_gabah'])) {
            // produktivitas = produksi (ton) / luas (ha) * 10 = ku/ha
            $record['produktivitas'] = round(
                ($record['produksi_gabah'] / $record['luas_panen']) * 10, 
                2
            );
        }
        
        // Ensure sumber_data is set
        if (!isset($record['sumber_data'])) {
            $record['sumber_data'] = $record['sumber_data_type'] === 'resmi_webapi' 
                ? 'BPS WebAPI Resmi'
                : 'Simulasi (Berdasarkan Data BPS Jawa Timur)';
        }
        
        return $record;
    }
    
    /**
     * Validate record against business rules
     * 
     * @param array $record
     * @return array ['valid' => bool, 'issues' => array]
     */
    public function validateRecord($record) {
        $issues = [];
        
        // Validate produktivitas
        if (isset($record['produktivitas'])) {
            $prod = $record['produktivitas'];
            $rules = self::VALIDATION_RULES['produktivitas'];
            if ($prod < $rules['min'] || $prod > $rules['max']) {
                $issues[] = sprintf(
                    'Produktivitas %.2f ku/ha di luar rentang wajar (%.0f-%.0f)',
                    $prod, $rules['min'], $rules['max']
                );
            }
        }
        
        // Validate luas_panen
        if (isset($record['luas_panen'])) {
            $luas = $record['luas_panen'];
            $rules = self::VALIDATION_RULES['luas_panen'];
            if ($luas < $rules['min'] || $luas > $rules['max']) {
                $issues[] = sprintf(
                    'Luas panen %.0f ha di luar rentang wajar (%.0f-%.0f)',
                    $luas, $rules['min'], $rules['max']
                );
            }
        }
        
        // Validate produksi_gabah
        if (isset($record['produksi_gabah'])) {
            $prod = $record['produksi_gabah'];
            $rules = self::VALIDATION_RULES['produksi_gabah'];
            if ($prod < $rules['min'] || $prod > $rules['max']) {
                $issues[] = sprintf(
                    'Produksi gabah %.0f ton di luar rentang wajar (%.0f-%.0f)',
                    $prod, $rules['min'], $rules['max']
                );
            }
        }
        
        // Cross-validation: produktivitas vs calculated
        if (isset($record['produktivitas']) && isset($record['luas_panen']) && $record['luas_panen'] > 0) {
            $calculated = ($record['produksi_gabah'] / $record['luas_panen']) * 10;
            $diff = abs($calculated - $record['produktivitas']);
            if ($diff > 5) { // More than 5 ku/ha difference
                $issues[] = sprintf(
                    'Produktivitas tidak konsisten: tercatat %.2f, kalkulasi %.2f',
                    $record['produktivitas'], $calculated
                );
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
    
    /**
     * Save record to database (insert or update)
     * 
     * @param array $record
     * @param array|null $existing Existing record if updating
     * @return bool
     */
    private function saveRecord($record, $existing = null) {
        $data = [
            'tahun' => $record['tahun'],
            'kabupaten_kota' => $record['kabupaten_kota'],
            'kode_wilayah' => $record['kode_wilayah'] ?? null,
            'luas_panen' => $record['luas_panen'],
            'produksi_gabah' => $record['produksi_gabah'],
            'produksi_beras' => $record['produksi_beras'] ?? null,
            'produktivitas' => $record['produktivitas'] ?? null,
            'sumber_data' => $record['sumber_data'] ?? 'Simulasi',
            'sumber_data_type' => $record['sumber_data_type'] ?? 'simulasi',
            'tipe_skenario' => $record['tipe_skenario'] ?? 'baseline',
            'is_validated' => $record['is_validated'] ?? true,
            'validation_notes' => $record['validation_notes'] ?? null,
            'keterangan' => $record['keterangan'] ?? null
        ];
        
        if ($existing) {
            return $this->model->update($existing['id'], $data);
        } else {
            return $this->model->insert($data);
        }
    }
    
    /**
     * Log anomaly to separate table
     * 
     * @param array $record
     * @param array $issues
     */
    private function logAnomaly($record, $issues) {
        $this->createAnomalyTableIfNotExists();
        
        try {
            // Get the record ID
            $saved = $this->model->getByYearAndKabupaten($record['tahun'], $record['kabupaten_kota']);
            if (!$saved) return;
            
            foreach ($issues as $issue) {
                // Parse issue to determine field
                $fieldName = 'general';
                if (strpos($issue, 'Produktivitas') !== false) $fieldName = 'produktivitas';
                elseif (strpos($issue, 'Luas') !== false) $fieldName = 'luas_panen';
                elseif (strpos($issue, 'Produksi') !== false) $fieldName = 'produksi_gabah';
                
                $sql = "INSERT INTO bps_data_anomalies 
                        (data_id, field_name, value_actual, anomaly_type, notes) 
                        VALUES (?, ?, ?, 'out_of_range', ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $saved['id'],
                    $fieldName,
                    $record[$fieldName] ?? 0,
                    $issue
                ]);
            }
        } catch (Exception $e) {
            $this->log("Failed to log anomaly: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Update yearly summary table
     * 
     * @param int $tahun
     */
    public function updateYearlySummary($tahun) {
        $this->createSummaryTableIfNotExists();
        
        try {
            $stats = $this->model->getStatistics($tahun);
            
            $sql = "INSERT INTO bps_yearly_summary 
                    (tahun, total_kabupaten, total_luas_panen, total_produksi_gabah, 
                     total_produksi_beras, rata_produktivitas)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    total_kabupaten = VALUES(total_kabupaten),
                    total_luas_panen = VALUES(total_luas_panen),
                    total_produksi_gabah = VALUES(total_produksi_gabah),
                    total_produksi_beras = VALUES(total_produksi_beras),
                    rata_produktivitas = VALUES(rata_produktivitas)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $tahun,
                $stats['jumlah_kabupaten'] ?? 0,
                $stats['total_luas_panen'] ?? 0,
                $stats['total_produksi_gabah'] ?? 0,
                $stats['total_produksi_beras'] ?? 0,
                $stats['rata_produktivitas'] ?? 0
            ]);
            
            $this->log("Updated yearly summary for {$tahun}");
            
        } catch (Exception $e) {
            $this->log("Failed to update yearly summary: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Get yearly summary from cache table
     * 
     * @param int|null $tahun
     * @return array
     */
    public function getYearlySummary($tahun = null) {
        $this->createSummaryTableIfNotExists();
        
        $sql = "SELECT * FROM bps_yearly_summary";
        $params = [];
        
        if ($tahun) {
            $sql .= " WHERE tahun = ?";
            $params[] = $tahun;
        }
        
        $sql .= " ORDER BY tahun DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $tahun ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get anomalies
     * 
     * @param array $filters
     * @return array
     */
    public function getAnomalies($filters = []) {
        $this->createAnomalyTableIfNotExists();
        
        $sql = "SELECT a.*, d.kabupaten_kota, d.tahun 
                FROM bps_data_anomalies a
                JOIN data_pertanian_bps d ON a.data_id = d.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND d.tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create anomaly table if not exists
     */
    private function createAnomalyTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS bps_data_anomalies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data_id INT NOT NULL,
            field_name VARCHAR(50) NOT NULL,
            value_actual DECIMAL(15,2),
            value_expected_min DECIMAL(15,2),
            value_expected_max DECIMAL(15,2),
            anomaly_type ENUM('out_of_range', 'suspicious', 'duplicate') DEFAULT 'out_of_range',
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_data_id (data_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    /**
     * Create summary table if not exists
     */
    private function createSummaryTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS bps_yearly_summary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tahun INT NOT NULL UNIQUE,
            total_kabupaten INT DEFAULT 0,
            total_luas_panen DECIMAL(15,2) DEFAULT 0,
            total_produksi_gabah DECIMAL(15,2) DEFAULT 0,
            total_produksi_beras DECIMAL(15,2) DEFAULT 0,
            rata_produktivitas DECIMAL(10,2) DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tahun (tahun)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'INFO') {
        $logEntry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Get conversion rate
     */
    public static function getConversionRate() {
        return self::CONVERSION_RATE;
    }
    
    /**
     * Get validation rules
     */
    public static function getValidationRules() {
        return self::VALIDATION_RULES;
    }
}
