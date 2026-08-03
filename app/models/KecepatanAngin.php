<?php
/**
 * Kecepatan Angin Model
 * Model untuk data kecepatan angin Kabupaten Jember
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class KecepatanAngin {
    
    private $db;
    private $table = 'kecepatan_angin';
    private $logTable = 'kecepatan_angin_logs';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTablesIfNotExist();
    }
    
    /**
     * Get all data with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $sql .= " ORDER BY tanggal DESC, lokasi ASC";
        
        if (isset($filters['limit'])) {
            $limit = (int) $filters['limit'];
            $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count all data with filters
     */
    public function countAll($filters = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert new record with UPSERT (ON DUPLICATE KEY UPDATE)
     * Requires UNIQUE constraint (tanggal, lokasi) on table
     */
    public function insertUpsert($data): bool {
        $sql = "INSERT INTO {$this->table} 
                (tanggal, lokasi, kode_wilayah, kecepatan_angin, kecepatan_max, 
                 arah_angin, arah_angin_desc, satuan, sumber_data, keterangan, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    kode_wilayah = VALUES(kode_wilayah),
                    kecepatan_angin = VALUES(kecepatan_angin),
                    kecepatan_max = IF(VALUES(kecepatan_max) IS NOT NULL, VALUES(kecepatan_max), kecepatan_max),
                    arah_angin = IF(VALUES(arah_angin) IS NOT NULL, VALUES(arah_angin), arah_angin),
                    arah_angin_desc = IF(VALUES(arah_angin_desc) IS NOT NULL, VALUES(arah_angin_desc), arah_angin_desc),
                    satuan = VALUES(satuan),
                    sumber_data = VALUES(sumber_data),
                    keterangan = CONCAT(keterangan, 
                        IF(keterangan IS NULL OR keterangan = '', '', ' | '),
                        VALUES(keterangan)),
                    updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['lokasi'] ?? 'Jember',
            $data['kode_wilayah'] ?? '35.09',
            $data['kecepatan_angin'],
            $data['kecepatan_max'] ?? null,
            $data['arah_angin'] ?? null,
            $data['arah_angin_desc'] ?? null,
            $data['satuan'] ?? 'km/h',
            $data['sumber_data'] ?? 'Manual',
            $data['keterangan'] ?? null
        ]);
    }

    /**
     * Insert new record
     */
    public function insert($data) {
        $sql = "INSERT INTO {$this->table} 
                (tanggal, lokasi, kode_wilayah, kecepatan_angin, kecepatan_max, 
                 arah_angin, arah_angin_desc, satuan, sumber_data, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['lokasi'] ?? 'Jember',
            $data['kode_wilayah'] ?? '35.09',
            $data['kecepatan_angin'],
            $data['kecepatan_max'] ?? null,
            $data['arah_angin'] ?? null,
            $data['arah_angin_desc'] ?? null,
            $data['satuan'] ?? 'km/h',
            $data['sumber_data'] ?? 'Manual',
            $data['keterangan'] ?? null
        ]);
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                tanggal = ?, lokasi = ?, kode_wilayah = ?, 
                kecepatan_angin = ?, kecepatan_max = ?,
                arah_angin = ?, arah_angin_desc = ?,
                sumber_data = ?, keterangan = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['lokasi'] ?? 'Jember',
            $data['kode_wilayah'] ?? '35.09',
            $data['kecepatan_angin'],
            $data['kecepatan_max'] ?? null,
            $data['arah_angin'] ?? null,
            $data['arah_angin_desc'] ?? null,
            $data['sumber_data'] ?? 'Manual',
            $data['keterangan'] ?? null,
            $id
        ]);
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get statistics
     */
    public function getStatistics($filters = []) {
        $sql = "SELECT 
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    MAX(kecepatan_angin) as maksimum,
                    MIN(kecepatan_angin) as minimum,
                    MAX(kecepatan_max) as puncak_maksimum,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN kecepatan_angin > 10 THEN 1 END) as hari_berangin
                FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $filters['month'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly average
     */
    public function getMonthlyAverage($year = null) {
        $year = $year ?: date('Y');
        
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    ROUND(SUM(kecepatan_angin), 2) as total,
                    MAX(kecepatan_max) as maksimum
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get yearly summary
     */
    public function getYearlySummary($years = 5) {
        $years = (int) $years;
        $sql = "SELECT 
                    YEAR(tanggal) as tahun,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    ROUND(SUM(kecepatan_angin), 2) as total,
                    MAX(kecepatan_max) as maksimum,
                    COUNT(*) as jumlah_data
                FROM {$this->table}
                GROUP BY YEAR(tanggal)
                ORDER BY tahun DESC
                LIMIT {$years}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available years
     */
    public function getAvailableYears() {
        $stmt = $this->db->query(
            "SELECT DISTINCT YEAR(tanggal) as tahun 
             FROM {$this->table} 
             ORDER BY tahun DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get data source breakdown
     */
    public function getDataSourceBreakdown($filters = []) {
        $sql = "SELECT 
                    sumber_data,
                    COUNT(*) as jumlah,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata
                FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(tanggal) = ?";
            $params[] = $filters['year'];
        }
        
        $sql .= " GROUP BY sumber_data ORDER BY jumlah DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========== ANALYSIS METHODS ==========
    
    /**
     * Get trend analysis
     */
    public function getTrendAnalysis($startYear = null, $endYear = null) {
        $endYear = $endYear ?: date('Y');
        $startYear = $startYear ?: ($endYear - 4);
        
        $sql = "SELECT 
                    YEAR(tanggal) as tahun,
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    MAX(kecepatan_max) as maksimum,
                    COUNT(*) as jumlah_data
                FROM {$this->table}
                WHERE YEAR(tanggal) BETWEEN ? AND ?
                GROUP BY YEAR(tanggal), MONTH(tanggal)
                ORDER BY tahun, bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startYear, $endYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get seasonal pattern
     */
    public function getSeasonalPattern($year = null) {
        $year = $year ?: date('Y');
        
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    MAX(kecepatan_max) as maksimum,
                    COUNT(*) as total_hari,
                    CASE 
                        WHEN AVG(kecepatan_angin) > 20 THEN 'Angin Kencang'
                        WHEN AVG(kecepatan_angin) > 10 THEN 'Angin Sedang'
                        ELSE 'Angin Lemah'
                    END as klasifikasi
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)
                ORDER BY bulan";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get anomalies
     */
    public function getAnomalies($year = null, $threshold = 2.0) {
        $year = $year ?: date('Y');
        
        $statsSQL = "SELECT AVG(kecepatan_angin) as mean, STDDEV(kecepatan_angin) as stddev 
                     FROM {$this->table} WHERE YEAR(tanggal) = ?";
        $stmt = $this->db->prepare($statsSQL);
        $stmt->execute([$year]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $mean = floatval($stats['mean'] ?? 0);
        $stddev = floatval($stats['stddev'] ?? 0);
        $upperLimit = $mean + ($threshold * $stddev);
        
        $sql = "SELECT 
                    id, tanggal, lokasi, kecepatan_angin, kecepatan_max, sumber_data,
                    'Tinggi' as tipe_anomali
                FROM {$this->table}
                WHERE YEAR(tanggal) = ? AND kecepatan_angin > ?
                ORDER BY kecepatan_angin DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $upperLimit]);
        
        return [
            'statistics' => [
                'mean' => round($mean, 2),
                'stddev' => round($stddev, 2),
                'upper_limit' => round($upperLimit, 2)
            ],
            'anomalies' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    /**
     * Get simple prediction
     */
    public function getSimplePrediction($months = 3) {
        $sql = "SELECT 
                    DATE_FORMAT(tanggal, '%Y-%m') as periode,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata
                FROM {$this->table}
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
                ORDER BY periode DESC
                LIMIT 12";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($historicalData) < 3) {
            return ['historical' => $historicalData, 'predictions' => []];
        }
        
        $values = array_column($historicalData, 'rata_rata');
        $movingAvg = array_sum(array_slice($values, 0, 3)) / 3;
        
        $predictions = [];
        $currentDate = new DateTime();
        for ($i = 1; $i <= $months; $i++) {
            $currentDate->modify('+1 month');
            $predictions[] = [
                'periode' => $currentDate->format('Y-m'),
                'prediksi' => round($movingAvg, 2),
                'confidence' => 'Low'
            ];
        }
        
        return [
            'historical' => array_reverse($historicalData),
            'predictions' => $predictions,
            'method' => '3-Month Moving Average'
        ];
    }
    
    /**
     * Get wind data by location
     */
    public function getWindByLocation($year = null, $month = null) {
        $year = $year ?: date('Y');
        
        $sql = "SELECT 
                    lokasi,
                    ROUND(AVG(kecepatan_angin), 2) as rata_rata,
                    MAX(kecepatan_max) as maksimum,
                    COUNT(*) as jumlah_data
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?";
        $params = [$year];
        
        if ($month) {
            $sql .= " AND MONTH(tanggal) = ?";
            $params[] = $month;
        }
        
        $sql .= " GROUP BY lokasi ORDER BY rata_rata DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get alerts for high wind speed
     */
    public function getAlerts($threshold = 30.0, $days = 7) {
        $sql = "SELECT 
                    id, tanggal, lokasi, kecepatan_angin, kecepatan_max, sumber_data,
                    CASE 
                        WHEN kecepatan_angin > ? * 2 THEN 'Kritis'
                        WHEN kecepatan_angin > ? * 1.5 THEN 'Tinggi'
                        ELSE 'Waspada'
                    END as level
                FROM {$this->table}
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND kecepatan_angin > ?
                ORDER BY kecepatan_angin DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$threshold, $threshold, $days, $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily data
     */
    public function getDailyData($year = null, $month = null) {
        $year = $year ?: date('Y');
        $month = $month ?: date('n');
        
        $sql = "SELECT 
                    DAY(tanggal) as hari,
                    tanggal,
                    kecepatan_angin,
                    kecepatan_max,
                    arah_angin_desc,
                    lokasi,
                    sumber_data
                FROM {$this->table}
                WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                ORDER BY tanggal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========== LOGGING METHODS ==========
    
    /**
     * Log activity
     */
    public function logActivity($action, $status, $message, $details = []) {
        $sql = "INSERT INTO {$this->logTable} (action, status, message, details) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $action,
            $status,
            $message,
            json_encode($details)
        ]);
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 10) {
        $limit = (int) $limit;
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->logTable} ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete log
     */
    public function deleteLog($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->logTable} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ========== TABLE MANAGEMENT ==========
    
    /**
     * Check if tables exist
     */
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create tables if not exist
     */
    public function createTablesIfNotExist() {
        if (!$this->tableExists($this->table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                lokasi VARCHAR(100) NOT NULL,
                kode_wilayah VARCHAR(20),
                kecepatan_angin DECIMAL(5,2) NOT NULL,
                kecepatan_max DECIMAL(5,2),
                arah_angin INT,
                arah_angin_desc VARCHAR(20),
                satuan VARCHAR(10) DEFAULT 'km/h',
                sumber_data VARCHAR(100),
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tanggal (tanggal),
                INDEX idx_lokasi (lokasi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
        
        if (!$this->tableExists($this->logTable)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->logTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50),
                status VARCHAR(20),
                message TEXT,
                details JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
    }
    
    /**
     * Convert wind direction degrees to compass direction
     */
    public static function degreesToDirection($degrees) {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = round($degrees / 45) % 8;
        return $directions[$index];
    }
    
    /**
     * Get Indonesian wind direction name
     */
    public static function getDirectionName($direction) {
        $names = [
            'N' => 'Utara',
            'NE' => 'Timur Laut',
            'E' => 'Timur',
            'SE' => 'Tenggara',
            'S' => 'Selatan',
            'SW' => 'Barat Daya',
            'W' => 'Barat',
            'NW' => 'Barat Laut'
        ];
        return $names[$direction] ?? $direction;
    }
}
