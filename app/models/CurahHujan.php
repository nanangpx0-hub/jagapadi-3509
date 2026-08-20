<?php
/**
 * Curah Hujan Model
 * Model untuk operasi CRUD data curah hujan
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujan {
    
    private $db;
    private $table = 'curah_hujan';
    private $logTable = 'curah_hujan_logs';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all data curah hujan dengan filter
     * 
     * @param array $filters
     * @return array
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Filter by date range
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        
        // Filter by location
        if (!empty($filters['lokasi'])) {
            $sql .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }
        
        // Filter by year
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        
        // Filter by month
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        
        // Filter by data source (LIKE pattern)
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $sql .= " ORDER BY tanggal DESC";
        
        // Pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $limit = (int) $filters['limit'];
            $offset = (int) $filters['offset'];
            $sql .= " LIMIT $limit OFFSET $offset";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total records dengan filter
     * 
     * @param array $filters
     * @return int
     */
    public function countAll($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['lokasi'])) {
            $sql .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        // Filter by data source
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['total'];
    }
    
    /**
     * Get data by date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDateRange($startDate, $endDate) {
        return $this->getAll([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }
    
    /**
     * Get statistics (avg, min, max, total)
     * 
     * @param array $filters
     * @return array
     */
    public function getStatistics($filters = []) {
        $where = " WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $where .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['lokasi'])) {
            $where .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }
        if (!empty($filters['year'])) {
            $where .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        if (!empty($filters['month'])) {
            $where .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        if (!empty($filters['sumber_data_like'])) {
            $where .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        // Regional rainfall is the daily mean across available locations. Summing
        // every station would inflate monthly totals by the number of locations.
        $sql = "SELECT
                    COALESCE(SUM(jumlah_record), 0) as total_records,
                    COUNT(*) as jumlah_hari,
                    ROUND(AVG(curah_hujan_harian), 2) as rata_rata,
                    MAX(maksimum_harian) as maksimum,
                    MIN(curah_hujan_harian) as minimum,
                    ROUND(SUM(curah_hujan_harian), 2) as total_curah_hujan,
                    SUM(curah_hujan_harian > 0) as hari_hujan,
                    SUM(curah_hujan_harian = 0) as hari_tidak_hujan
                FROM (
                    SELECT tanggal,
                           AVG(curah_hujan) as curah_hujan_harian,
                           MAX(curah_hujan) as maksimum_harian,
                           COUNT(*) as jumlah_record
                    FROM {$this->table}{$where}
                    GROUP BY tanggal
                ) daily";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly average for a year
     * 
     * @param int $year
     * @return array
     */
    public function getMonthlyAverage($year = null, $filters = []) {
        $year = $year ?: date('Y');
        $where = " WHERE YEAR(tanggal) = ?";
        $params = [$year];
        if (!empty($filters['sumber_data_like'])) {
            $where .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        if (!empty($filters['lokasi'])) {
            $where .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }

        $sql = "SELECT
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(curah_hujan_harian), 2) as rata_rata,
                    ROUND(SUM(curah_hujan_harian), 2) as total,
                    COUNT(*) as jumlah_data,
                    MAX(maksimum_harian) as maksimum,
                    MIN(curah_hujan_harian) as minimum
                FROM (
                    SELECT tanggal,
                           AVG(curah_hujan) as curah_hujan_harian,
                           MAX(curah_hujan) as maksimum_harian
                    FROM {$this->table}{$where}
                    GROUP BY tanggal
                ) daily
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get yearly summary
     * 
     * @param int $limit
     * @return array
     */
    public function getYearlySummary($limit = 5, $filters = []) {
        $where = " WHERE 1=1";
        $params = [];
        if (!empty($filters['sumber_data_like'])) {
            $where .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        $sql = "SELECT
                    YEAR(tanggal) as tahun,
                    ROUND(AVG(curah_hujan_harian), 2) as rata_rata,
                    ROUND(SUM(curah_hujan_harian), 2) as total,
                    COUNT(*) as jumlah_data,
                    MAX(maksimum_harian) as maksimum
                FROM (
                    SELECT tanggal,
                           AVG(curah_hujan) as curah_hujan_harian,
                           MAX(curah_hujan) as maksimum_harian
                    FROM {$this->table}{$where}
                    GROUP BY tanggal
                ) daily
                GROUP BY YEAR(tanggal)
                ORDER BY tahun DESC
                LIMIT " . (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get data source breakdown statistics
     * 
     * @param array $filters
     * @return array
     */
    public function getDataSourceBreakdown($filters = []) {
        $sql = "SELECT 
                    sumber_data,
                    COUNT(*) as count,
                    ROUND(AVG(curah_hujan), 2) as avg_rainfall,
                    SUM(curah_hujan) as total_rainfall
                FROM {$this->table}
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['lokasi'])) {
            $sql .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }

        // Apply year/month filters (exclude sumber_data_like)
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        
        $sql .= " GROUP BY sumber_data ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available years
     * 
     * @return array
     */
    public function getAvailableYears() {
        $sql = "SELECT DISTINCT YEAR(tanggal) as tahun FROM {$this->table} ORDER BY tahun DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Insert single record
     * 
     * @param array $data
     * @return bool
     */
    public function insert($data) {
        $sql = "INSERT INTO {$this->table} 
                (tanggal, lokasi, kecamatan_id, kecamatan, latitude, longitude, kode_wilayah,
                 curah_hujan, satuan, sumber_data, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                curah_hujan = VALUES(curah_hujan),
                keterangan = VALUES(keterangan),
                kecamatan_id = VALUES(kecamatan_id),
                kecamatan = VALUES(kecamatan),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                kode_wilayah = VALUES(kode_wilayah),
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['tanggal'],
            $data['lokasi'] ?? 'Jember',
            $data['kecamatan_id'] ?? null,
            $data['kecamatan'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['kode_wilayah'] ?? '35.09',
            $data['curah_hujan'],
            $data['satuan'] ?? 'mm',
            $data['sumber_data'],
            $data['keterangan'] ?? null
        ]);
        
        return $result;
    }
    
    /**
     * Bulk insert records
     * 
     * @param array $records
     * @return array ['success' => int, 'failed' => int]
     */
    public function bulkInsert($records) {
        $success = 0;
        $failed = 0;
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            foreach ($records as $record) {
                if ($this->insert($record)) {
                    $success++;
                } else {
                    $failed++;
                }
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $failed = count($records);
            $success = 0;
            error_log("Bulk insert curah hujan failed: " . $e->getMessage());
        }
        
        return ['success' => $success, 'failed' => $failed];
    }
    
    /**
     * Get single record by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update existing record
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                tanggal = ?,
                lokasi = ?,
                kecamatan_id = ?,
                kode_wilayah = ?,
                curah_hujan = ?,
                sumber_data = ?,
                keterangan = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['lokasi'] ?? 'Jember',
            $data['kecamatan_id'] ?? null,
            $data['kode_wilayah'] ?? '35.09',
            $data['curah_hujan'],
            $data['sumber_data'] ?? 'Manual',
            $data['keterangan'] ?? null,
            (int)$id
        ]);
    }
    
    /**
     * Delete by ID
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Delete by date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return int Number of deleted rows
     */
    public function deleteByDateRange($startDate, $endDate) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE tanggal BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->rowCount();
    }

    /**
     * Delete log by ID
     * 
     * @param int $id
     * @return bool
     */
    public function deleteLog($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->logTable} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Log scraping activity
     * 
     * @param string $action
     * @param string $status
     * @param string $message
     * @param array $stats
     * @return bool
     */
    public function logActivity($action, $status, $message, $stats = []) {
        // Sanitize and truncate status to max 50 characters to prevent truncation warnings
        $statusStr = substr(trim((string)$status), 0, 50);
        if (empty($statusStr)) {
            $statusStr = 'success';
        }

        $sql = "INSERT INTO {$this->logTable} 
                (action, status, message, records_processed, records_success, records_failed, execution_time)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            substr(trim((string)$action), 0, 50),
            $statusStr,
            $message,
            $stats['processed'] ?? 0,
            $stats['success'] ?? 0,
            $stats['failed'] ?? 0,
            $stats['execution_time'] ?? null
        ]);
    }
    
    /**
     * Get recent logs
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentLogs($limit = 10) {
        $sql = "SELECT * FROM {$this->logTable} ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if table exists
     * 
     * @return bool
     */
    public function tableExists() {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create tables if not exist
     * 
     * @return bool
     */
    public function createTablesIfNotExist() {
        try {
            // Create main table
            $this->db->exec("CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `tanggal` DATE NOT NULL,
                `lokasi` VARCHAR(100) DEFAULT 'Jember',
                `kecamatan_id` INT(11) DEFAULT NULL,
                `kecamatan` VARCHAR(100) DEFAULT NULL,
                `latitude` DECIMAL(10,7) DEFAULT NULL,
                `longitude` DECIMAL(10,7) DEFAULT NULL,
                `kode_wilayah` VARCHAR(20) DEFAULT NULL,
                `curah_hujan` DECIMAL(10,2) NOT NULL,
                `satuan` VARCHAR(10) DEFAULT 'mm',
                `sumber_data` VARCHAR(255) NOT NULL,
                `keterangan` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_tanggal_lokasi_sumber` (`tanggal`, `lokasi`, `sumber_data`),
                INDEX `idx_tanggal` (`tanggal`),
                INDEX `idx_curah_hujan_kecamatan` (`kecamatan_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Create log table
            $this->db->exec("CREATE TABLE IF NOT EXISTS `{$this->logTable}` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `action` VARCHAR(50) NOT NULL,
                `status` VARCHAR(50) NOT NULL DEFAULT 'success',
                `message` TEXT,
                `records_processed` INT DEFAULT 0,
                `records_success` INT DEFAULT 0,
                `records_failed` INT DEFAULT 0,
                `execution_time` DECIMAL(10,4) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to create curah_hujan tables: " . $e->getMessage());
            return false;
        }
    }
    
    // ========== DASHBOARD ANALYSIS METHODS ==========
    
    /**
     * Get trend analysis comparing multiple years
     * 
     * @param int $startYear
     * @param int $endYear
     * @return array
     */
    public function getTrendAnalysis($startYear = null, $endYear = null, $filters = []) {
        $endYear = $endYear ?: date('Y');
        $startYear = $startYear ?: ($endYear - 4);

        $where = "YEAR(tanggal) BETWEEN ? AND ?";
        $params = [$startYear, $endYear];
        if (!empty($filters['sumber_data_like'])) {
            $where .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        // One regional value per day prevents totals being multiplied by 31 stations.
        $sql = "SELECT
                    YEAR(tanggal) as tahun,
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(rata_rata_harian), 2) as rata_rata,
                    ROUND(SUM(rata_rata_harian), 2) as total,
                    COUNT(*) as jumlah_data
                FROM (
                    SELECT tanggal, AVG(curah_hujan) as rata_rata_harian
                    FROM {$this->table}
                    WHERE {$where}
                    GROUP BY tanggal
                ) regional_harian
                GROUP BY YEAR(tanggal), MONTH(tanggal)
                ORDER BY tahun, bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get seasonal pattern analysis
     * 
     * @param int $year
     * @return array
     */
    public function getSeasonalPattern($year = null, $filters = []) {
        $year = $year ?: date('Y');

        $where = "YEAR(tanggal) = ?";
        $params = [$year];
        if (!empty($filters['sumber_data_like'])) {
            $where .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        $sql = "SELECT
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(rata_rata_harian), 2) as rata_rata,
                    ROUND(SUM(rata_rata_harian), 2) as total,
                    SUM(rata_rata_harian > 0) as hari_hujan,
                    COUNT(*) as total_hari,
                    CASE
                        WHEN AVG(rata_rata_harian) > 10 THEN 'Musim Hujan'
                        WHEN AVG(rata_rata_harian) > 5 THEN 'Peralihan'
                        ELSE 'Musim Kemarau'
                    END as klasifikasi
                FROM (
                    SELECT tanggal, AVG(curah_hujan) as rata_rata_harian
                    FROM {$this->table}
                    WHERE {$where}
                    GROUP BY tanggal
                ) regional_harian
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Detect rainfall anomalies (values exceeding threshold)
     * 
     * @param int $year
     * @param float $threshold Standard deviation multiplier (default 2)
     * @return array
     */
    public function getAnomalies($year = null, $threshold = 2.0, $filters = []) {
        $year = $year ?: date('Y');
        $threshold = max(0.1, min(10.0, (float) $threshold));

        $sourceSql = '';
        $sourceParams = [];
        if (!empty($filters['sumber_data_like'])) {
            $sourceSql = " AND sumber_data LIKE ?";
            $sourceParams[] = $filters['sumber_data_like'];
        }
        
        // First get statistics for the year
        $statsSQL = "SELECT AVG(curah_hujan) as mean, STDDEV(curah_hujan) as stddev 
                     FROM {$this->table} WHERE YEAR(tanggal) = ?{$sourceSql}";
        $stmt = $this->db->prepare($statsSQL);
        $stmt->execute(array_merge([$year], $sourceParams));
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $mean = floatval($stats['mean'] ?? 0);
        $stddev = floatval($stats['stddev'] ?? 0);
        $upperLimit = $mean + ($threshold * $stddev);
        $lowerLimit = max(0, $mean - ($threshold * $stddev));
        
        // Get anomalous data points
        $sql = "SELECT 
                    id, tanggal, lokasi, curah_hujan, sumber_data,
                    CASE 
                        WHEN curah_hujan > ? THEN 'Tinggi'
                        WHEN curah_hujan < ? AND curah_hujan < ? THEN 'Rendah'
                        ELSE 'Normal'
                    END as tipe_anomali
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?
                  {$sourceSql}
                  AND (curah_hujan > ? OR (curah_hujan < ? AND curah_hujan < ?))
                ORDER BY curah_hujan DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(
            [$upperLimit, $lowerLimit, $mean, $year],
            $sourceParams,
            [$upperLimit, $lowerLimit, $mean]
        ));
        
        return [
            'statistics' => [
                'mean' => round($mean, 2),
                'stddev' => round($stddev, 2),
                'upper_limit' => round($upperLimit, 2),
                'lower_limit' => round($lowerLimit, 2)
            ],
            'anomalies' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    /**
     * Get simple prediction based on moving averages
     * 
     * @param int $months Number of months to predict
     * @return array
     */
    public function getSimplePrediction($months = 3, $filters = []) {
        $months = max(1, min(12, (int) $months));
        $sourceSql = '';
        $params = [];
        if (!empty($filters['sumber_data_like'])) {
            $sourceSql = " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        $sql = "SELECT
                    DATE_FORMAT(tanggal, '%Y-%m') as periode,
                    ROUND(AVG(rata_rata_harian), 2) as rata_rata
                FROM (
                    SELECT tanggal, AVG(curah_hujan) as rata_rata_harian
                    FROM {$this->table}
                    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH){$sourceSql}
                    GROUP BY tanggal
                ) regional_harian
                GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
                ORDER BY periode DESC
                LIMIT 12";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($historicalData) < 3) {
            return ['historical' => $historicalData, 'predictions' => []];
        }
        
        // Calculate 3-month moving average
        $values = array_column($historicalData, 'rata_rata');
        $movingAvg = array_sum(array_slice($values, 0, 3)) / 3;
        
        // Generate predictions
        $predictions = [];
        $currentDate = new DateTime();
        for ($i = 1; $i <= $months; $i++) {
            $currentDate->modify('+1 month');
            $predictions[] = [
                'periode' => $currentDate->format('Y-m'),
                'prediksi' => round($movingAvg, 2),
                'confidence' => 'Low' // Simple model has low confidence
            ];
        }
        
        return [
            'historical' => array_reverse($historicalData),
            'predictions' => $predictions,
            'method' => '3-Month Moving Average'
        ];
    }
    
    /**
     * Get rainfall data grouped by location/kecamatan
     * 
     * @param int $year
     * @param int $month
     * @return array
     */
    public function getRainfallByLocation($year = null, $month = null, $filters = []) {
        $year = $year ?: date('Y');
        
        $sql = "SELECT 
                    lokasi,
                    ROUND(AVG(curah_hujan), 2) as rata_rata,
                    SUM(curah_hujan) as total,
                    MAX(curah_hujan) as maksimum,
                    COUNT(*) as jumlah_data
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?";
        $params = [$year];
        
        if ($month) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $month;
        }

        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $sql .= " GROUP BY lokasi ORDER BY total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get alerts for high rainfall
     * 
     * @param float $threshold Rainfall threshold in mm
     * @param int $days Number of recent days to check
     * @return array
     */
    public function getAlerts($threshold = 50.0, $days = 7, $filters = []) {
        $days = max(1, min(365, (int) $days));
        $sql = "SELECT 
                    id, tanggal, lokasi, curah_hujan, sumber_data,
                    CASE 
                        WHEN curah_hujan > ? * 2 THEN 'Kritis'
                        WHEN curah_hujan > ? * 1.5 THEN 'Tinggi'
                        ELSE 'Waspada'
                    END as level
                FROM {$this->table}
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND curah_hujan > ?";
        $params = [$threshold, $threshold, $days, $threshold];

        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }

        $sql .= " ORDER BY curah_hujan DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily data for a specific month
     * 
     * @param int $year
     * @param int $month
     * @return array
     */
    public function getDailyData($year = null, $month = null, $filters = []) {
        $year = $year ?: date('Y');
        $month = $month ?: date('n');
        
        $sql = "SELECT 
                    DAY(tanggal) as hari,
                    tanggal,
                    ROUND(AVG(curah_hujan), 2) as curah_hujan,
                    MAX(curah_hujan) as maksimum,
                    COUNT(*) as jumlah_lokasi
                FROM {$this->table}
                WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?";
        $params = [$year, $month];

        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        if (!empty($filters['lokasi'])) {
            $sql .= " AND lokasi = ?";
            $params[] = $filters['lokasi'];
        }

        $sql .= "
                GROUP BY tanggal
                ORDER BY tanggal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
