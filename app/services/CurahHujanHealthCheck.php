<?php
/**
 * Curah Hujan Health Check Service
 * Performs periodic data validation and quality checks
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujanHealthCheck {
    
    private $model;
    private $db;
    private $logFile;
    
    /**
     * Health check results
     */
    private $results = [
        'duplicates' => [],
        'missing_gaps' => [],
        'boundary_values' => [],
        'anomalies' => [],
        'summary' => []
    ];
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/CurahHujan.php';
        require_once ROOT_PATH . '/app/helpers/CurahHujanValidator.php';
        $this->model = new CurahHujan();
        $this->db = Database::getInstance()->getConnection();
        $this->logFile = ROOT_PATH . '/logs/curah_hujan_health_check.log';
    }
    
    /**
     * Run all health checks
     * 
     * @param array $options Optional filters (year, month)
     * @return array Health check results
     */
    public function run($options = []) {
        $startTime = microtime(true);
        $this->log("=== Starting Health Check ===");
        
        $year = $options['year'] ?? date('Y');
        
        // Run individual checks
        $this->checkDuplicates($year);
        $this->checkMissingDataGaps($year);
        $this->checkBoundaryValues($year);
        $this->checkSeasonalAnomalies($year);
        
        // Generate summary
        $this->results['summary'] = [
            'execution_time' => round(microtime(true) - $startTime, 4),
            'year_checked' => $year,
            'duplicate_count' => count($this->results['duplicates']),
            'gap_count' => count($this->results['missing_gaps']),
            'boundary_issue_count' => count($this->results['boundary_values']),
            'anomaly_count' => count($this->results['anomalies']),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Log to database
        $this->logToDatabase();
        
        $this->log("=== Health Check Complete ===");
        
        return $this->results;
    }
    
    /**
     * Check for duplicate tanggal-lokasi pairs
     */
    private function checkDuplicates($year) {
        $this->log("Checking for duplicates...");
        
        $sql = "SELECT tanggal, lokasi, COUNT(*) as cnt 
                FROM curah_hujan 
                WHERE YEAR(tanggal) = :year 
                GROUP BY tanggal, lokasi 
                HAVING cnt > 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year]);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->results['duplicates'] = $duplicates;
        $this->log("Found " . count($duplicates) . " duplicate pairs");
    }
    
    /**
     * Check for missing data gaps (3+ consecutive days without data)
     */
    private function checkMissingDataGaps($year) {
        $this->log("Checking for data gaps...");
        
        // Get all dates with data for the year
        $sql = "SELECT DISTINCT tanggal FROM curah_hujan 
                WHERE YEAR(tanggal) = :year 
                ORDER BY tanggal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $gaps = [];
        $today = new DateTime();
        
        for ($i = 1; $i < count($dates); $i++) {
            $prev = new DateTime($dates[$i - 1]);
            $curr = new DateTime($dates[$i]);
            $diff = $prev->diff($curr)->days;
            
            if ($diff > 3) {
                $gaps[] = [
                    'start_date' => $dates[$i - 1],
                    'end_date' => $dates[$i],
                    'gap_days' => $diff - 1
                ];
            }
        }
        
        $this->results['missing_gaps'] = $gaps;
        $this->log("Found " . count($gaps) . " data gaps (>3 days)");
    }
    
    /**
     * Check for boundary values (0 or 500mm - might indicate data issues)
     */
    private function checkBoundaryValues($year) {
        $this->log("Checking for boundary values...");
        
        $sql = "SELECT id, tanggal, lokasi, curah_hujan 
                FROM curah_hujan 
                WHERE YEAR(tanggal) = :year 
                AND (curah_hujan = 0 OR curah_hujan >= 450)
                ORDER BY tanggal DESC
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year]);
        $boundaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->results['boundary_values'] = $boundaries;
        $this->log("Found " . count($boundaries) . " boundary value records");
    }
    
    /**
     * Check for seasonal anomalies using validator
     */
    private function checkSeasonalAnomalies($year) {
        $this->log("Checking for seasonal anomalies...");
        
        // Get all data for the year
        $sql = "SELECT id, tanggal, lokasi, curah_hujan, keterangan 
                FROM curah_hujan 
                WHERE YEAR(tanggal) = :year 
                AND curah_hujan > 0
                ORDER BY tanggal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $anomalies = [];
        foreach ($records as $record) {
            $anomaly = CurahHujanValidator::detectSeasonalAnomaly(
                $record['tanggal'], 
                (float)$record['curah_hujan']
            );
            
            if ($anomaly !== null) {
                $record['anomaly_message'] = $anomaly;
                $anomalies[] = $record;
            }
        }
        
        $this->results['anomalies'] = $anomalies;
        $this->log("Found " . count($anomalies) . " seasonal anomalies");
    }
    
    /**
     * Log health check results to database
     */
    private function logToDatabase() {
        $message = sprintf(
            "Health Check: %d duplicates, %d gaps, %d boundary issues, %d anomalies",
            $this->results['summary']['duplicate_count'],
            $this->results['summary']['gap_count'],
            $this->results['summary']['boundary_issue_count'],
            $this->results['summary']['anomaly_count']
        );
        
        $hasIssues = ($this->results['summary']['duplicate_count'] > 0 || 
                      $this->results['summary']['gap_count'] > 0 ||
                      $this->results['summary']['anomaly_count'] > 5);
        
        $this->model->logActivity(
            'health_check',
            $hasIssues ? 'partial' : 'success',
            $message,
            [
                'processed' => 1,
                'success' => $hasIssues ? 0 : 1,
                'failed' => 0,
                'execution_time' => $this->results['summary']['execution_time']
            ]
        );
    }
    
    /**
     * Log message to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get results
     */
    public function getResults() {
        return $this->results;
    }
    
    /**
     * Check if any critical issues found
     */
    public function hasCriticalIssues() {
        return $this->results['summary']['duplicate_count'] > 0 ||
               $this->results['summary']['gap_count'] > 5;
    }
}
