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
    private static $tablesChecked = false;

    private const SOURCE_PRIORITY_SQL = "CASE sumber_data_type
        WHEN 'ksa' THEN 1
        WHEN 'resmi_webapi' THEN 2
        WHEN 'manual' THEN 3
        WHEN 'simulasi' THEN 4
        ELSE 5 END";
    
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
        if (!self::$tablesChecked) {
            $this->createTablesIfNotExist();
            self::$tablesChecked = true;
        }
    }
    
    /**
     * Build filter WHERE clause and params (shared by getAll and countAll)
     *
     * @param array $filters
     * @return array ['sql' => string, 'params' => array]
     */
    private function buildFilterClause($filters, $alias = '') {
        $sql = '';
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND {$prefix}tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['kabupaten_kota'])) {
            $sql .= " AND {$prefix}kabupaten_kota LIKE ?";
            $params[] = '%' . $filters['kabupaten_kota'] . '%';
        }
        
        if (!empty($filters['sumber_data_like'])) {
            $sql .= " AND {$prefix}sumber_data LIKE ?";
            $params[] = '%' . $filters['sumber_data_like'] . '%';
        }
        
        if (!empty($filters['sumber_data_type'])) {
            $sql .= " AND {$prefix}sumber_data_type = ?";
            $params[] = $filters['sumber_data_type'];
        }
        
        if (!empty($filters['tipe_skenario'])) {
            $sql .= " AND {$prefix}tipe_skenario = ?";
            $params[] = $filters['tipe_skenario'];
        }
        
        if (isset($filters['is_validated']) && $filters['is_validated'] !== '') {
            $sql .= " AND {$prefix}is_validated = ?";
            $params[] = (int) $filters['is_validated'];
        }

        if (!empty($filters['kode_provinsi'])) {
            $sql .= " AND {$prefix}kode_provinsi = ?";
            $params[] = $filters['kode_provinsi'];
        }
        
        return [$sql, $params];
    }

    /**
     * Pilih satu sumber terbaik untuk setiap kabupaten/tahun/skenario.
     */
    private function preferredSourceClause($alias = 'd') {
        $currentPriority = str_replace('sumber_data_type', "{$alias}.sumber_data_type", self::SOURCE_PRIORITY_SQL);
        $higherPriority = str_replace('sumber_data_type', 'higher.sumber_data_type', self::SOURCE_PRIORITY_SQL);

        return " AND NOT EXISTS (
            SELECT 1 FROM {$this->table} higher
            WHERE higher.tahun = {$alias}.tahun
              AND higher.kode_provinsi = {$alias}.kode_provinsi
              AND higher.kabupaten_kota = {$alias}.kabupaten_kota
              AND higher.tipe_skenario = {$alias}.tipe_skenario
              AND higher.is_validated = 1
              AND ({$higherPriority}) < ({$currentPriority})
        )";
    }
    
    /**
     * Get all data with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT d.* FROM {$this->table} d WHERE 1=1";
        $params = [];
        
        [$filterSql, $filterParams] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);

        if (!empty($filters['preferred_only']) && empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }
        
        $sql .= " ORDER BY d.tahun DESC, d.kabupaten_kota ASC";
        
        if (isset($filters['limit'])) {
            $limit = max(1, min(500, (int) $filters['limit']));
            $offset = max(0, (int) ($filters['offset'] ?? 0));
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
        $sql = "SELECT COUNT(*) FROM {$this->table} d WHERE 1=1";
        $params = [];
        
        [$filterSql, $filterParams] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);

        if (!empty($filters['preferred_only']) && empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countDistinctKabupaten($filters = []) {
        $sql = "SELECT COUNT(DISTINCT d.kabupaten_kota) FROM {$this->table} d WHERE 1=1";
        [$filterSql, $params] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;

        if (!empty($filters['preferred_only']) && empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
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
    public function getByYearAndKabupaten(
        $tahun,
        $kabupaten,
        $sourceType = null,
        $scenario = 'baseline',
        $kodeProvinsi = '35'
    ) {
        $sql = "SELECT * FROM {$this->table}
                WHERE tahun = ? AND kode_provinsi = ? AND kabupaten_kota = ?
                  AND tipe_skenario = ?";
        $params = [$tahun, $kodeProvinsi, $kabupaten, $scenario];

        if ($sourceType !== null && $sourceType !== '') {
            $sql .= ' AND sumber_data_type = ?';
            $params[] = $sourceType;
        }

        $sql .= ' ORDER BY ' . self::SOURCE_PRIORITY_SQL . ' ASC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert new record
     */
    public function insert($data) {
        $sql = "INSERT INTO {$this->table} 
                (tahun, kode_provinsi, kabupaten_kota, kode_wilayah, luas_panen, produksi_gabah, 
                 produksi_beras, produktivitas, sumber_data, sumber_data_type, 
                 tipe_skenario, is_validated, validation_notes, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tahun'],
            $data['kode_provinsi'] ?? '35',
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
                tahun = ?, kode_provinsi = ?, kabupaten_kota = ?, kode_wilayah = ?,
                luas_panen = ?, produksi_gabah = ?, produksi_beras = ?,
                produktivitas = ?, sumber_data = ?, sumber_data_type = ?,
                tipe_skenario = ?, is_validated = ?, validation_notes = ?, 
                keterangan = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['tahun'],
            $data['kode_provinsi'] ?? '35',
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
        $existing = $this->getByYearAndKabupaten(
            $data['tahun'],
            $data['kabupaten_kota'],
            $data['sumber_data_type'] ?? 'manual',
            $data['tipe_skenario'] ?? 'baseline',
            $data['kode_provinsi'] ?? '35'
        );
        
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
    public function getStatistics($tahun = null, $filters = []) {
        $tahun = $tahun ?: date('Y');
        $filters['tahun'] = $tahun;
        unset($filters['limit'], $filters['offset']);

        $sql = "SELECT
                    COUNT(DISTINCT d.kabupaten_kota) AS jumlah_kabupaten,
                    SUM(d.luas_panen) AS total_luas_panen,
                    SUM(d.produksi_gabah) AS total_produksi_gabah,
                    SUM(d.produksi_beras) AS total_produksi_beras,
                    ROUND(SUM(d.produksi_gabah) / NULLIF(SUM(d.luas_panen), 0) * 10, 2)
                        AS rata_produktivitas
                FROM {$this->table} d WHERE 1=1";
        [$filterSql, $params] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;
        if (!empty($filters['preferred_only']) && empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get yearly trend
     */
    public function getYearlyTrend($startYear = null, $endYear = null, $filters = []) {
        $endYear = $endYear ?: date('Y');
        $startYear = $startYear ?: ($endYear - 4);
        
        $sql = "SELECT
                    d.tahun,
                    SUM(d.luas_panen) AS total_luas_panen,
                    SUM(d.produksi_gabah) AS total_produksi_gabah,
                    SUM(d.produksi_beras) AS total_produksi_beras,
                    ROUND(SUM(d.produksi_gabah) / NULLIF(SUM(d.luas_panen), 0) * 10, 2)
                        AS rata_produktivitas,
                    COUNT(DISTINCT d.kabupaten_kota) AS jumlah_kabupaten
                FROM {$this->table} d
                WHERE d.tahun BETWEEN ? AND ?";
        $params = [$startYear, $endYear];
        $filters['tipe_skenario'] = $filters['tipe_skenario'] ?? 'baseline';
        $filters['is_validated'] = 1;
        unset($filters['tahun'], $filters['limit'], $filters['offset']);
        [$filterSql, $filterParams] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        if (empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }
        $sql .= ' GROUP BY d.tahun ORDER BY d.tahun';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top producers
     */
    public function getTopProducers($tahun = null, $limit = 10, $filters = []) {
        $tahun = $tahun ?: date('Y');
        $limit = max(1, min(100, (int) $limit));
        
        $sql = "SELECT d.* FROM {$this->table} d WHERE d.tahun = ?";
        $params = [$tahun];
        $filters['tipe_skenario'] = $filters['tipe_skenario'] ?? 'baseline';
        $filters['is_validated'] = 1;
        unset($filters['tahun'], $filters['limit'], $filters['offset']);
        [$filterSql, $filterParams] = $this->buildFilterClause($filters, 'd');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        if (empty($filters['sumber_data_type'])) {
            $sql .= $this->preferredSourceClause('d');
        }
        $sql .= " ORDER BY d.produksi_gabah DESC
                LIMIT {$limit}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
                kode_provinsi VARCHAR(10) NOT NULL DEFAULT '35',
                kabupaten_kota VARCHAR(100) NOT NULL,
                kode_wilayah VARCHAR(20),
                luas_panen DECIMAL(15,2) COMMENT 'dalam hektar',
                produksi_gabah DECIMAL(15,2) COMMENT 'dalam ton',
                produksi_beras DECIMAL(15,2) COMMENT 'dalam ton',
                produktivitas DECIMAL(10,2) COMMENT 'kuintal/ha',
                sumber_data VARCHAR(100),
                sumber_data_type ENUM('ksa', 'resmi_webapi', 'manual', 'simulasi') DEFAULT 'simulasi',
                tipe_skenario ENUM('baseline', 'optimis', 'pesimis') DEFAULT 'baseline',
                is_validated TINYINT(1) DEFAULT 0,
                validation_notes TEXT,
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tahun (tahun),
                INDEX idx_kabupaten (kabupaten_kota),
                UNIQUE KEY uk_bps_source_scenario
                    (tahun, kode_provinsi, kabupaten_kota, sumber_data_type, tipe_skenario)
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
