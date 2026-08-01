<?php
/**
 * Harga Komoditas Model
 * Model untuk data harga gabah dan beras Kabupaten Jember
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class HargaKomoditas {
    
    private $db;
    private $table = 'harga_komoditas';
    private $logTable = 'harga_komoditas_logs';
    private $alertTable = 'harga_alerts';
    
    // Commodity types
    const GABAH_KERING_PANEN = 'gabah_kering_panen';
    const GABAH_KERING_GILING = 'gabah_kering_giling';
    const BERAS_MEDIUM = 'beras_medium';
    const BERAS_PREMIUM = 'beras_premium';
    
    // Alert threshold percentage
    const ALERT_THRESHOLD = 5.0;
    const CRITICAL_THRESHOLD = 10.0;
    
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
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['jenis_komoditas'])) {
            if ($filters['jenis_komoditas'] === 'gabah') {
                $sql .= " AND jenis_komoditas IN ('gabah_kering_panen', 'gabah_kering_giling')";
            } elseif ($filters['jenis_komoditas'] === 'beras') {
                $sql .= " AND jenis_komoditas IN ('beras_medium', 'beras_premium')";
            } else {
                $sql .= " AND jenis_komoditas = ?";
                $params[] = $filters['jenis_komoditas'];
            }
        }
        
        if (!empty($filters['lokasi'])) {
            $sql .= " AND lokasi LIKE ?";
            $params[] = '%' . $filters['lokasi'] . '%';
        }
        
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        $sql .= " ORDER BY tanggal DESC, jenis_komoditas ASC";
        
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
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['jenis_komoditas'])) {
            if ($filters['jenis_komoditas'] === 'gabah') {
                $sql .= " AND jenis_komoditas IN ('gabah_kering_panen', 'gabah_kering_giling')";
            } elseif ($filters['jenis_komoditas'] === 'beras') {
                $sql .= " AND jenis_komoditas IN ('beras_medium', 'beras_premium')";
            } else {
                $sql .= " AND jenis_komoditas = ?";
                $params[] = $filters['jenis_komoditas'];
            }
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
     * Get latest price for each commodity
     */
    public function getLatestPrices() {
        $sql = "SELECT h1.* FROM {$this->table} h1
                INNER JOIN (
                    SELECT jenis_komoditas, MAX(tanggal) as max_tanggal
                    FROM {$this->table}
                    GROUP BY jenis_komoditas
                ) h2 ON h1.jenis_komoditas = h2.jenis_komoditas AND h1.tanggal = h2.max_tanggal
                ORDER BY h1.jenis_komoditas";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert new record
     */
    public function insert($data) {
        $sql = "INSERT INTO {$this->table} 
                (tanggal, jenis_komoditas, harga, satuan, lokasi, kode_wilayah, sumber_data, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['tanggal'],
            $data['jenis_komoditas'],
            $data['harga'],
            $data['satuan'] ?? 'Rp/kg',
            $data['lokasi'] ?? 'Jember',
            $data['kode_wilayah'] ?? '35.09',
            $data['sumber_data'] ?? 'Manual',
            $data['keterangan'] ?? null
        ]);
        
        // Check for price fluctuation after insert
        if ($result) {
            $this->checkAndGenerateAlert($data['jenis_komoditas'], $data['tanggal']);
        }
        
        return $result;
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                tanggal = ?, jenis_komoditas = ?, harga = ?,
                lokasi = ?, kode_wilayah = ?, sumber_data = ?, keterangan = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['jenis_komoditas'],
            $data['harga'],
            $data['lokasi'] ?? 'Jember',
            $data['kode_wilayah'] ?? '35.09',
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
                    jenis_komoditas,
                    ROUND(AVG(harga), 0) as rata_rata,
                    MAX(harga) as tertinggi,
                    MIN(harga) as terendah,
                    COUNT(*) as total_records
                FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tanggal >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND tanggal <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " GROUP BY jenis_komoditas ORDER BY jenis_komoditas";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get overall statistics
     */
    public function getOverallStats($filters = []) {
        $latestPrices = $this->getLatestPrices();
        
        $gabahPrice = 0;
        $berasPrice = 0;
        foreach ($latestPrices as $price) {
            if (strpos($price['jenis_komoditas'], 'gabah') !== false) {
                $gabahPrice = max($gabahPrice, $price['harga']);
            }
            if (strpos($price['jenis_komoditas'], 'beras') !== false) {
                $berasPrice = max($berasPrice, $price['harga']);
            }
        }
        
        // Calculate price change
        $changeGabah = $this->calculatePriceChange(self::GABAH_KERING_GILING);
        $changeBeras = $this->calculatePriceChange(self::BERAS_MEDIUM);
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->db->query($sql);
        $total = $stmt->fetchColumn();
        
        return [
            'harga_gabah' => $gabahPrice,
            'harga_beras' => $berasPrice,
            'perubahan_gabah' => $changeGabah,
            'perubahan_beras' => $changeBeras,
            'total_records' => $total
        ];
    }
    
    /**
     * Calculate price change percentage
     */
    public function calculatePriceChange($komoditas) {
        $sql = "SELECT harga, tanggal FROM {$this->table} 
                WHERE jenis_komoditas = ? 
                ORDER BY tanggal DESC LIMIT 2";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$komoditas]);
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($prices) < 2) return 0;
        
        $current = $prices[0]['harga'];
        $previous = $prices[1]['harga'];
        
        if ($previous == 0) return 0;
        
        return round(($current - $previous) / $previous * 100, 2);
    }
    
    /**
     * Get monthly average
     */
    public function getMonthlyAverage($year = null, $komoditas = null) {
        $year = $year ?: date('Y');
        
        $sql = "SELECT 
                    MONTH(tanggal) as bulan,
                    jenis_komoditas,
                    ROUND(AVG(harga), 0) as rata_rata
                FROM {$this->table}
                WHERE YEAR(tanggal) = ?";
        $params = [$year];
        
        if ($komoditas) {
            $sql .= " AND jenis_komoditas = ?";
            $params[] = $komoditas;
        }
        
        $sql .= " GROUP BY MONTH(tanggal), jenis_komoditas ORDER BY bulan, jenis_komoditas";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get trend analysis
     */
    public function getTrendAnalysis($startDate = null, $endDate = null) {
        $endDate = $endDate ?: date('Y-m-d');
        $startDate = $startDate ?: date('Y-m-d', strtotime('-30 days'));
        
        $sql = "SELECT 
                    tanggal,
                    jenis_komoditas,
                    ROUND(AVG(harga), 0) as harga
                FROM {$this->table}
                WHERE tanggal BETWEEN ? AND ?
                GROUP BY tanggal, jenis_komoditas
                ORDER BY tanggal, jenis_komoditas";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get price comparison (Gabah vs Beras)
     */
    public function getPriceComparison($months = 6) {
        $sql = "SELECT 
                    DATE_FORMAT(tanggal, '%Y-%m') as periode,
                    CASE 
                        WHEN jenis_komoditas LIKE 'gabah%' THEN 'Gabah'
                        ELSE 'Beras'
                    END as kategori,
                    ROUND(AVG(harga), 0) as rata_rata
                FROM {$this->table}
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), 
                    CASE WHEN jenis_komoditas LIKE 'gabah%' THEN 'Gabah' ELSE 'Beras' END
                ORDER BY periode, kategori";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get price by location
     */
    public function getPriceByLocation($komoditas = null) {
        $sql = "SELECT 
                    lokasi,
                    jenis_komoditas,
                    ROUND(AVG(harga), 0) as rata_rata,
                    MAX(harga) as tertinggi,
                    MIN(harga) as terendah,
                    COUNT(*) as jumlah_data
                FROM {$this->table}
                WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $params = [];
        
        if ($komoditas) {
            $sql .= " AND jenis_komoditas = ?";
            $params[] = $komoditas;
        }
        
        $sql .= " GROUP BY lokasi, jenis_komoditas ORDER BY lokasi";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
    
    // ========== ALERT METHODS ==========
    
    /**
     * Check and generate alert for price fluctuation
     */
    public function checkAndGenerateAlert($komoditas, $date) {
        $sql = "SELECT harga FROM {$this->table} 
                WHERE jenis_komoditas = ? AND tanggal < ?
                ORDER BY tanggal DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$komoditas, $date]);
        $previousPrice = $stmt->fetchColumn();
        
        if (!$previousPrice) return;
        
        $sql = "SELECT harga FROM {$this->table} 
                WHERE jenis_komoditas = ? AND tanggal = ?
                ORDER BY id DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$komoditas, $date]);
        $currentPrice = $stmt->fetchColumn();
        
        if (!$currentPrice) return;
        
        $changePercent = abs(($currentPrice - $previousPrice) / $previousPrice * 100);
        
        if ($changePercent >= self::ALERT_THRESHOLD) {
            $type = ($currentPrice > $previousPrice) ? 'naik' : 'turun';
            
            $sql = "INSERT INTO {$this->alertTable} 
                    (jenis_komoditas, tipe_alert, persentase, harga_sebelum, harga_sesudah, tanggal)
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $komoditas,
                $type,
                round($changePercent, 2),
                $previousPrice,
                $currentPrice,
                $date
            ]);
        }
    }
    
    /**
     * Get unread alerts
     */
    public function getAlerts($limit = 20, $unreadOnly = false) {
        $limit = (int) $limit;
        $sql = "SELECT * FROM {$this->alertTable}";
        
        if ($unreadOnly) {
            $sql .= " WHERE is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT {$limit}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count unread alerts
     */
    public function countUnreadAlerts() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->alertTable} WHERE is_read = 0");
        return $stmt->fetchColumn();
    }
    
    /**
     * Mark alert as read
     */
    public function markAlertRead($id) {
        $stmt = $this->db->prepare("UPDATE {$this->alertTable} SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Mark all alerts as read
     */
    public function markAllAlertsRead() {
        return $this->db->exec("UPDATE {$this->alertTable} SET is_read = 1 WHERE is_read = 0");
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
        // Main price table
        if (!$this->tableExists($this->table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                jenis_komoditas ENUM('gabah_kering_panen', 'gabah_kering_giling', 'beras_medium', 'beras_premium') NOT NULL,
                harga DECIMAL(12,2) NOT NULL,
                satuan VARCHAR(20) DEFAULT 'Rp/kg',
                lokasi VARCHAR(100) DEFAULT 'Jember',
                kode_wilayah VARCHAR(20),
                sumber_data VARCHAR(100),
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tanggal (tanggal),
                INDEX idx_komoditas (jenis_komoditas),
                INDEX idx_lokasi (lokasi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
        
        // Logs table
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
        
        // Alerts table
        if (!$this->tableExists($this->alertTable)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->alertTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                jenis_komoditas VARCHAR(50),
                tipe_alert ENUM('naik', 'turun', 'fluktuasi') NOT NULL,
                persentase DECIMAL(5,2),
                harga_sebelum DECIMAL(12,2),
                harga_sesudah DECIMAL(12,2),
                tanggal DATE,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        }
    }
    
    /**
     * Get commodity label
     */
    public static function getKomoditasLabel($kode) {
        $labels = [
            'gabah_kering_panen' => 'Gabah Kering Panen (GKP)',
            'gabah_kering_giling' => 'Gabah Kering Giling (GKG)',
            'beras_medium' => 'Beras Medium',
            'beras_premium' => 'Beras Premium'
        ];
        return $labels[$kode] ?? $kode;
    }
    
    /**
     * Get all commodity types
     */
    public static function getKomoditasTypes() {
        return [
            self::GABAH_KERING_PANEN => 'Gabah Kering Panen (GKP)',
            self::GABAH_KERING_GILING => 'Gabah Kering Giling (GKG)',
            self::BERAS_MEDIUM => 'Beras Medium',
            self::BERAS_PREMIUM => 'Beras Premium'
        ];
    }
    
    /**
     * Format price for display
     */
    public static function formatHarga($harga) {
        return 'Rp ' . number_format($harga, 0, ',', '.');
    }
}
