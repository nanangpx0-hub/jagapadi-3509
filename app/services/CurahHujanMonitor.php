<?php
/**
 * Curah Hujan Monitor Service
 * Daily monitoring for data quality, anomaly detection, and alerting
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujanMonitor {
    
    private $db;
    
    /**
     * Monitoring thresholds
     */
    private const THRESHOLDS = [
        'missing_days' => 3,           // Alert if 3 consecutive days without data
        'extreme_low' => 0,            // Consecutive days with 0 rainfall
        'extreme_low_days' => 14,      // ... for 14 days (unusual even in dry season)
        'extreme_high' => 200,         // Daily rainfall > 200mm
        'anomaly_stddev' => 3,         // Value > 3 standard deviations from mean
    ];
    
    /**
     * Alert severity levels
     */
    private const SEVERITY_CRITICAL = 'critical';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_INFO = 'info';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTablesExist();
    }
    
    /**
     * Run all daily checks
     * 
     * @return array List of alerts generated
     */
    public function runDailyCheck(): array {
        $alerts = [];
        $startTime = microtime(true);
        
        $this->log("Starting daily monitoring check...");
        
        try {
            // 1. Check for missing data
            $missingAlerts = $this->checkMissingData();
            $alerts = array_merge($alerts, $missingAlerts);
            
            // 2. Check for extreme values
            $extremeAlerts = $this->checkExtremeValues();
            $alerts = array_merge($alerts, $extremeAlerts);
            
            // 3. Check for statistical anomalies
            $anomalyAlerts = $this->checkStatisticalAnomalies();
            $alerts = array_merge($alerts, $anomalyAlerts);
            
            // 4. Check scraper health
            $scraperAlerts = $this->checkScraperHealth();
            $alerts = array_merge($alerts, $scraperAlerts);
            
            // Save and send alerts
            foreach ($alerts as $alert) {
                $this->saveAlert($alert);
                $this->sendAlert($alert);
            }
            
            $executionTime = round(microtime(true) - $startTime, 4);
            $this->log("Monitoring check completed. Found " . count($alerts) . " alerts in {$executionTime}s");
            
        } catch (Exception $e) {
            $this->log("Monitoring check failed: " . $e->getMessage(), 'ERROR');
            
            // Create error alert
            $alerts[] = [
                'type' => 'MONITOR_ERROR',
                'severity' => self::SEVERITY_CRITICAL,
                'message' => 'Monitoring check failed: ' . $e->getMessage(),
                'data' => ['error' => $e->getMessage()]
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for missing data (no records for consecutive days)
     * 
     * @return array
     */
    private function checkMissingData(): array {
        $alerts = [];
        
        // Get days since last data
        $stmt = $this->db->prepare("
            SELECT DATEDIFF(CURDATE(), MAX(tanggal)) as missing_days 
            FROM curah_hujan 
            WHERE lokasi LIKE '%Jember%'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $missingDays = (int) ($result['missing_days'] ?? 0);
        
        if ($missingDays >= self::THRESHOLDS['missing_days']) {
            $alerts[] = [
                'type' => 'DATA_MISSING',
                'severity' => $missingDays >= 7 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
                'message' => "Tidak ada data curah hujan Jember selama {$missingDays} hari terakhir",
                'data' => [
                    'missing_days' => $missingDays,
                    'threshold' => self::THRESHOLDS['missing_days']
                ]
            ];
        }
        
        // Check for gaps in data (missing dates in between)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_days 
            FROM curah_hujan 
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND lokasi = 'Jember'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $actualDays = (int) ($result['total_days'] ?? 0);
        $expectedDays = min(30, (int) date('j')); // Days in current month up to today
        
        if ($actualDays < ($expectedDays * 0.7)) { // Less than 70% coverage
            $alerts[] = [
                'type' => 'DATA_INCOMPLETE',
                'severity' => self::SEVERITY_WARNING,
                'message' => "Data curah hujan bulan ini tidak lengkap: {$actualDays}/{$expectedDays} hari",
                'data' => [
                    'actual_days' => $actualDays,
                    'expected_days' => $expectedDays,
                    'coverage_percent' => round(($actualDays / $expectedDays) * 100, 1)
                ]
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for extreme rainfall values
     * 
     * @return array
     */
    private function checkExtremeValues(): array {
        $alerts = [];
        
        // Check for extremely high values in last 7 days
        $stmt = $this->db->prepare("
            SELECT id, tanggal, lokasi, curah_hujan
            FROM curah_hujan 
            WHERE curah_hujan > ?
            AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY curah_hujan DESC
            LIMIT 5
        ");
        $stmt->execute([self::THRESHOLDS['extreme_high']]);
        $extremeRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($extremeRecords as $record) {
            $alerts[] = [
                'type' => 'EXTREME_VALUE',
                'severity' => self::SEVERITY_WARNING,
                'message' => sprintf(
                    "Curah hujan ekstrem terdeteksi: %.1fmm pada %s di %s",
                    $record['curah_hujan'],
                    $record['tanggal'],
                    $record['lokasi']
                ),
                'data' => $record
            ];
        }
        
        // Check for extended dry period (0 rainfall for many consecutive days)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as dry_days
            FROM curah_hujan 
            WHERE curah_hujan = 0
            AND tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND lokasi = 'Jember'
        ");
        $stmt->execute([self::THRESHOLDS['extreme_low_days']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $dryDays = (int) ($result['dry_days'] ?? 0);
        
        if ($dryDays >= self::THRESHOLDS['extreme_low_days']) {
            $alerts[] = [
                'type' => 'EXTENDED_DRY_PERIOD',
                'severity' => self::SEVERITY_INFO,
                'message' => "Periode kering panjang terdeteksi: {$dryDays} hari tanpa hujan",
                'data' => [
                    'dry_days' => $dryDays,
                    'threshold' => self::THRESHOLDS['extreme_low_days']
                ]
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for statistical anomalies
     * 
     * @return array
     */
    private function checkStatisticalAnomalies(): array {
        $alerts = [];
        
        // Get historical stats for current month (from previous years)
        $currentMonth = (int) date('m');
        
        $stmt = $this->db->prepare("
            SELECT 
                AVG(curah_hujan) as mean,
                STDDEV(curah_hujan) as stddev,
                COUNT(*) as sample_size
            FROM curah_hujan
            WHERE MONTH(tanggal) = ?
            AND YEAR(tanggal) < YEAR(CURDATE())
            AND lokasi = 'Jember'
        ");
        $stmt->execute([$currentMonth]);
        $historical = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Only proceed if we have enough historical data
        if (($historical['sample_size'] ?? 0) < 30) {
            return $alerts;
        }
        
        $mean = (float) $historical['mean'];
        $stddev = (float) $historical['stddev'];
        
        if ($stddev == 0) {
            return $alerts;
        }
        
        // Check current month's data for anomalies
        $stmt = $this->db->prepare("
            SELECT tanggal, curah_hujan
            FROM curah_hujan
            WHERE MONTH(tanggal) = ?
            AND YEAR(tanggal) = YEAR(CURDATE())
            AND lokasi = 'Jember'
            AND ABS(curah_hujan - ?) > (? * ?)
        ");
        $stmt->execute([$currentMonth, $mean, $stddev, self::THRESHOLDS['anomaly_stddev']]);
        $anomalies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($anomalies) > 0) {
            $alerts[] = [
                'type' => 'STATISTICAL_ANOMALY',
                'severity' => self::SEVERITY_INFO,
                'message' => count($anomalies) . " nilai curah hujan di luar pola normal bulan ini",
                'data' => [
                    'anomaly_count' => count($anomalies),
                    'historical_mean' => round($mean, 2),
                    'historical_stddev' => round($stddev, 2),
                    'threshold_stddev' => self::THRESHOLDS['anomaly_stddev'],
                    'anomalies' => $anomalies
                ]
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check scraper health (based on logs)
     * 
     * @return array
     */
    private function checkScraperHealth(): array {
        $alerts = [];
        
        // Check for recent failures
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                MAX(created_at) as last_run
            FROM curah_hujan_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = (int) ($stats['total'] ?? 0);
        $failed = (int) ($stats['failed'] ?? 0);
        $lastRun = $stats['last_run'] ?? null;
        
        // Check if scraper hasn't run recently
        if ($lastRun) {
            $lastRunTime = new DateTime($lastRun);
            $now = new DateTime();
            $hoursSinceRun = ($now->getTimestamp() - $lastRunTime->getTimestamp()) / 3600;
            
            if ($hoursSinceRun > 48) {
                $alerts[] = [
                    'type' => 'SCRAPER_STALE',
                    'severity' => self::SEVERITY_WARNING,
                    'message' => "Scraper tidak berjalan sejak " . round($hoursSinceRun) . " jam yang lalu",
                    'data' => [
                        'last_run' => $lastRun,
                        'hours_since_run' => round($hoursSinceRun)
                    ]
                ];
            }
        }
        
        // Check failure rate
        if ($total > 0) {
            $failureRate = ($failed / $total) * 100;
            
            if ($failureRate > 30) {
                $alerts[] = [
                    'type' => 'SCRAPER_HIGH_FAILURE_RATE',
                    'severity' => $failureRate > 50 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
                    'message' => sprintf(
                        "Tingkat kegagalan scraper tinggi: %.1f%% (%d/%d) dalam 7 hari terakhir",
                        $failureRate,
                        $failed,
                        $total
                    ),
                    'data' => [
                        'total_runs' => $total,
                        'failed_runs' => $failed,
                        'failure_rate' => round($failureRate, 1)
                    ]
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Ensure system_alerts table exists
     */
    private function ensureTablesExist(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `system_alerts` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `type` VARCHAR(50) NOT NULL,
                `severity` ENUM('critical', 'warning', 'info') NOT NULL DEFAULT 'info',
                `message` TEXT NOT NULL,
                `data` JSON,
                `acknowledged` TINYINT(1) DEFAULT 0,
                `acknowledged_by` INT(11) DEFAULT NULL,
                `acknowledged_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_type` (`type`),
                INDEX `idx_severity` (`severity`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_acknowledged` (`acknowledged`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    /**
     * Save alert to database
     * 
     * @param array $alert
     * @return int|false Alert ID or false on failure
     */
    private function saveAlert(array $alert) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_alerts (type, severity, message, data)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $alert['type'],
                $alert['severity'],
                $alert['message'],
                json_encode($alert['data'] ?? [])
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            $this->log("Failed to save alert: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Send alert notification
     * 
     * @param array $alert
     */
    private function sendAlert(array $alert): void {
        // Only send email for critical and warning alerts
        if (!in_array($alert['severity'], [self::SEVERITY_CRITICAL, self::SEVERITY_WARNING])) {
            return;
        }
        
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@jagapadi.local';
        
        $subject = sprintf(
            "[JAGAPADI %s] %s",
            strtoupper($alert['severity']),
            $alert['type']
        );
        
        $body = "Alert Type: {$alert['type']}\n";
        $body .= "Severity: {$alert['severity']}\n";
        $body .= "Message: {$alert['message']}\n\n";
        $body .= "Data:\n" . json_encode($alert['data'] ?? [], JSON_PRETTY_PRINT) . "\n\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        
        $headers = "From: noreply@jagapadi.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Attempt to send email
        if (function_exists('mail')) {
            @mail($adminEmail, $subject, $body, $headers);
        }
        
        // Also log to file
        $this->log("[ALERT:{$alert['severity']}] {$alert['type']}: {$alert['message']}");
    }
    
    /**
     * Get recent alerts from database
     * 
     * @param int $limit
     * @param bool $unacknowledgedOnly
     * @return array
     */
    public function getRecentAlerts(int $limit = 20, bool $unacknowledgedOnly = false): array {
        $sql = "SELECT * FROM system_alerts";
        $params = [];
        
        if ($unacknowledgedOnly) {
            $sql .= " WHERE acknowledged = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Acknowledge an alert
     * 
     * @param int $alertId
     * @param int $userId
     * @return bool
     */
    public function acknowledgeAlert(int $alertId, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE system_alerts 
            SET acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$userId, $alertId]);
    }
    
    /**
     * Get alert statistics
     * 
     * @return array
     */
    public function getAlertStats(): array {
        $stmt = $this->db->prepare("
            SELECT 
                severity,
                COUNT(*) as count,
                SUM(CASE WHEN acknowledged = 0 THEN 1 ELSE 0 END) as unacknowledged
            FROM system_alerts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY severity
        ");
        $stmt->execute();
        
        $stats = [
            'total' => 0,
            'unacknowledged' => 0,
            'by_severity' => []
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['by_severity'][$row['severity']] = [
                'count' => (int) $row['count'],
                'unacknowledged' => (int) $row['unacknowledged']
            ];
            $stats['total'] += (int) $row['count'];
            $stats['unacknowledged'] += (int) $row['unacknowledged'];
        }
        
        return $stats;
    }
    
    /**
     * Log message
     * 
     * @param string $message
     * @param string $level
     */
    private function log(string $message, string $level = 'INFO'): void {
        $logFile = ROOT_PATH . '/logs/curah_hujan_monitor.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
