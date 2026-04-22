<?php
/**
 * Data Pertanian BPS Model
 * Model untuk data luas panen dan produksi padi dari BPS Jawa Timur
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class DataPertanianBps {
    
    private $db;
    private $table = 'data_pertanian_bps';
    private $logTable = 'bps_scraping_logs';
    
    // East Java regencies/cities
    const KABUPATEN_JATIM = [
        'Bangkalan', 'Banyuwangi', 'Blitar', 'Bojonegoro', 'Bondowoso',
        'Gresik', 'Jember', 'Jombang', 'Kediri', 'Kota Batu', 'Kota Blitar',
        'Kota Kediri', 'Kota Madiun', 'Kota Malang', 'Kota Mojokerto',
        'Kota Pasuruan', 'Kota Probolinggo', 'Kota Surabaya', 'Lamongan',
        'Lumajang', 'Madiun', 'Magetan', 'Malang', 'Mojokerto', 'Nganjuk',
        'Ngawi', 'Pacitan', 'Pamekasan', 'Pasuruan', 'Ponorogo',
        'Probolinggo', 'Sampang', 'Sidoarjo', 'Situbondo', 'Sumenep',
        'Trenggalek', 'Tuban', 'Tulungagung'
    ];
    
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
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['kabupaten_kota'])) {
            $sql .= " AND kabupaten_kota LIKE ?";
            $params[] = '%' . $filters['kabupaten_kota'] . '%';
        }
        
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND sumber_data LIKE ?";
            $params[] = $filters['sumber_data_like'];
        }
        
        if (!empty($filters['sumber_data_type'])) {
            $sql .= " AND sumber_data_type = ?";
            $params[] = $filters['sumber_data_type'];
        }
        
        if (!empty($filters['tipe_skenario'])) {
            $sql .= " AND tipe_skenario = ?";
            $params[] = $filters['tipe_skenario'];
        }
        
        if (isset($filters['is_validated']) && $filters['is_validated'] !== '') {
            $sql .= " AND is_validated = ?";
            $params[] = (int) $filters['is_validated'];
        }
        
        $sql .= " ORDER BY tahun DESC, kabupaten_kota ASC";
        
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
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['kabupaten_kota'])) {
            $sql .= " AND kabupaten_kota LIKE ?";
            $params[] = '%' . $filters['kabupaten_kota'] . '%';
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
     * Get by year and kabupaten
     */
    public function getByYearAndKabupaten($tahun, $kabupaten) {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE tahun = ? AND kabupaten_kota = ?"
        );
        $stmt->execute([$tahun, $kabupaten]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert new record
     */
    public function insert($data) {
        $sql = "INSERT INTO {$this->table} 
                (tahun, kabupaten_kota, kode_wilayah, luas_panen, produksi_gabah, 
                 produksi_beras, produktivitas, sumber_data, sumber_data_type, 
                 tipe_skenario, is_validated, validation_notes, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tahun'],
            $data['kabupaten_kota'],
            $data['kode_wilayah'] ?? null,
            $data['luas_panen'],
            $data['produksi_gabah'],
            $data['produksi_beras'] ?? null,
            $data['produktivitas'] ?? null,
            $data['sumber_data'] ?? 'BPS',
            $data['sumber_data_type'] ?? 'manual',
            $data['tipe_skenario'] ?? 'baseline',
            $data['is_validated'] ?? 0,
            $data['validation_notes'] ?? null,
            $data['keterangan'] ?? null
        ]);
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                tahun = ?, kabupaten_kota = ?, kode_wilayah = ?,
                luas_panen = ?, produksi_gabah = ?, produksi_beras = ?,
                produktivitas = ?, sumber_data = ?, sumber_data_type = ?,
                tipe_skenario = ?, is_validated = ?, validation_notes = ?, 
                keterangan = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tahun'],
            $data['kabupaten_kota'],
            $data['kode_wilayah'] ?? null,
            $data['luas_panen'],
            $data['produksi_gabah'],
            $data['produksi_beras'] ?? null,
            $data['produktivitas'] ?? null,
            $data['sumber_data'] ?? 'BPS',
            $data['sumber_data_type'] ?? 'manual',
            $data['tipe_skenario'] ?? 'baseline',
            $data['is_validated'] ?? 0,
            $data['validation_notes'] ?? null,
            $data['keterangan'] ?? null,
            $id
        ]);
    }
    
    /**
     * Upsert - Insert or update if exists
     */
    public function upsert($data) {
        $existing = $this->getByYearAndKabupaten($data['tahun'], $data['kabupaten_kota']);
        
        if ($existing) {
            return $this->update($existing['id'], $data);
        } else {
            return $this->insert($data);
        }
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get statistics for a year
     */
    public function getStatistics($tahun = null) {
        $tahun = $tahun ?: date('Y');
        
        $sql = "SELECT 
                    COUNT(DISTINCT kabupaten_kota) as jumlah_kabupaten,
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_gabah) as total_produksi_gabah,
                    SUM(produksi_beras) as total_produksi_beras,
                    ROUND(AVG(produktivitas), 2) as rata_produktivitas
                FROM {$this->table} WHERE tahun = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get yearly trend
     */
    public function getYearlyTrend($startYear = null, $endYear = null) {
        $endYear = $endYear ?: date('Y');
        $startYear = $startYear ?: ($endYear - 4);
        
        $sql = "SELECT 
                    tahun,
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_gabah) as total_produksi_gabah,
                    SUM(produksi_beras) as total_produksi_beras,
                    ROUND(AVG(produktivitas), 2) as rata_produktivitas,
                    COUNT(DISTINCT kabupaten_kota) as jumlah_kabupaten
                FROM {$this->table}
                WHERE tahun BETWEEN ? AND ?
                GROUP BY tahun
                ORDER BY tahun";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startYear, $endYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top producers
     */
    public function getTopProducers($tahun = null, $limit = 10) {
        $tahun = $tahun ?: date('Y');
        $limit = (int) $limit;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE tahun = ?
                ORDER BY produksi_gabah DESC
                LIMIT {$limit}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get kabupaten list
     */
    public function getKabupatenList() {
        return self::KABUPATEN_JATIM;
    }
    
    /**
     * Get available years
     */
    public function getAvailableYears() {
        $stmt = $this->db->query(
            "SELECT DISTINCT tahun FROM {$this->table} ORDER BY tahun DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Delete by year
     */
    public function deleteByYear($tahun) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE tahun = ?");
        return $stmt->execute([$tahun]);
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
        // Main data table
        if (!$this->tableExists($this->table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tahun INT NOT NULL,
                kabupaten_kota VARCHAR(100) NOT NULL,
                kode_wilayah VARCHAR(20),
                luas_panen DECIMAL(15,2) COMMENT 'dalam hektar',
                produksi_gabah DECIMAL(15,2) COMMENT 'dalam ton',
                produksi_beras DECIMAL(15,2) COMMENT 'dalam ton',
                produktivitas DECIMAL(10,2) COMMENT 'kuintal/ha',
                sumber_data VARCHAR(100),
                sumber_data_type ENUM('simulasi', 'resmi_webapi', 'manual') DEFAULT 'simulasi',
                tipe_skenario ENUM('baseline', 'optimis', 'pesimis') DEFAULT 'baseline',
                is_validated TINYINT(1) DEFAULT 0,
                validation_notes TEXT,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tahun (tahun),
                INDEX idx_kabupaten (kabupaten_kota),
                UNIQUE KEY unique_data (tahun, kabupaten_kota)
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
    }
    
    /**
     * Format number for display
     */
    public static function formatNumber($number, $decimals = 0) {
        return number_format($number, $decimals, ',', '.');
    }
}
