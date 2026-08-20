<?php
/**
 * Data Irigasi Model
 * Model untuk data debit air dan irigasi di Kabupaten Jember
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class DataIrigasi {
    
    private $db;
    private $table = 'data_irigasi';
    private $logTable = 'irigasi_scraping_logs';
    
    // Major Dams / Irrigation Areas in Jember
    const DAERAH_IRIGASI = [
        'Dam Bedadung', 'Dam Talang', 'Dam Curahmalang', 'Dam Pondok Waluh',
        'Dam Rowo', 'Dam Congapan', 'Dam Sembah', 'Dam Kramat',
        'Dam Cangkring', 'Dam Darjanto', 'Dam Gladak Putih', 'Dam Jubung',
        'Dam Tegal Gede', 'Dam Ajung', 'Dam Kertosari', 'Dam Pecoro',
        'Dam Sempolan', 'Dam Sumberjati', 'Dam Sanen', 'Dam Tanggul'
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
        
        if (!empty($filters['tanggal'])) {
            $sql .= " AND tanggal = ?";
            $params[] = $filters['tanggal'];
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND tanggal BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['daerah_irigasi'])) {
            $sql .= " AND daerah_irigasi = ?";
            $params[] = $filters['daerah_irigasi'];
        }
        
        if (!empty($filters['status_pintu'])) {
            $sql .= " AND status_pintu = ?";
            $params[] = $filters['status_pintu'];
        }

        if (!empty($filters['metode_data'])) {
            $sql .= " AND metode_data = ?";
            $params[] = $filters['metode_data'];
        }
        
        $sql .= " ORDER BY tanggal DESC, daerah_irigasi ASC";
        
        if (isset($filters['limit'])) {
            $limit = max(1, min(500, (int) $filters['limit']));
            $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
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
        
        if (!empty($filters['tanggal'])) {
            $sql .= " AND tanggal = ?";
            $params[] = $filters['tanggal'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND tanggal BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['daerah_irigasi'])) {
            $sql .= " AND daerah_irigasi = ?";
            $params[] = $filters['daerah_irigasi'];
        }

        if (!empty($filters['status_pintu'])) {
            $sql .= " AND status_pintu = ?";
            $params[] = $filters['status_pintu'];
        }

        if (!empty($filters['metode_data'])) {
            $sql .= " AND metode_data = ?";
            $params[] = $filters['metode_data'];
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
     * Get by Date and Location
     */
    public function getByDateAndLocation($tanggal, $daerahIrigasi) {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE tanggal = ? AND daerah_irigasi = ?"
        );
        $stmt->execute([$tanggal, $daerahIrigasi]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLatestDate(): ?string {
        $latestDate = $this->db->query("SELECT MAX(tanggal) FROM {$this->table}")->fetchColumn();
        return $latestDate !== false && $latestDate !== null ? (string) $latestDate : null;
    }
    
    /**
     * Upsert - Insert or update if exists
     */
    public function upsert($data) {
        $existing = $this->getByDateAndLocation($data['tanggal'], $data['daerah_irigasi']);
        
        if ($existing) {
            $sql = "UPDATE {$this->table} SET 
                    kecamatan = ?, luas_sawah = ?, debit_air = ?,
                    status_pintu = ?, metode_data = ?, keterangan = ?
                    WHERE id = ?";
            $params = [
                $data['kecamatan'] ?? null,
                $data['luas_sawah'],
                $data['debit_air'],
                $data['status_pintu'],
                $data['metode_data'] ?? 'manual',
                $data['keterangan'] ?? null,
                $existing['id']
            ];
        } else {
            $sql = "INSERT INTO {$this->table} 
                    (tanggal, daerah_irigasi, kecamatan, luas_sawah, debit_air, 
                     status_pintu, metode_data, keterangan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $data['tanggal'],
                $data['daerah_irigasi'],
                $data['kecamatan'] ?? null,
                $data['luas_sawah'],
                $data['debit_air'],
                $data['status_pintu'],
                $data['metode_data'] ?? 'manual',
                $data['keterangan'] ?? null
            ];
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get statistics for a date
     */
    public function getStatistics($filters = []) {
        if (is_string($filters)) {
            $filters = ['tanggal' => $filters];
        }

        $filters['tanggal'] = $filters['tanggal'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    COUNT(*) as total_lokasi,
                    SUM(luas_sawah) as total_luas_layanan,
                    ROUND(AVG(debit_air), 2) as rata_debit,
                    SUM(CASE WHEN LOWER(status_pintu) LIKE '%kritis%' THEN 1 ELSE 0 END) as jumlah_kritis,
                    SUM(CASE WHEN LOWER(status_pintu) LIKE '%waspada%' THEN 1 ELSE 0 END) as jumlah_waspada
                FROM {$this->table} WHERE tanggal = ?";

        $params = [$filters['tanggal']];

        if (!empty($filters['daerah_irigasi'])) {
            $sql .= " AND daerah_irigasi = ?";
            $params[] = $filters['daerah_irigasi'];
        }

        if (!empty($filters['status_pintu'])) {
            $sql .= " AND status_pintu = ?";
            $params[] = $filters['status_pintu'];
        }

        if (!empty($filters['metode_data'])) {
            $sql .= " AND metode_data = ?";
            $params[] = $filters['metode_data'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get debit trend for charts
     */
    public function getDebitTrend($days = 30) {
        $days = max(1, min(365, (int) $days));
        $endDate = $this->getLatestDate() ?? date('Y-m-d');
        $startDate = date('Y-m-d', strtotime($endDate . ' -' . ($days - 1) . ' days'));
        
        $sql = "SELECT 
                    tanggal,
                    ROUND(AVG(debit_air), 2) as rata_debit,
                    MAX(debit_air) as max_debit,
                    MIN(debit_air) as min_debit
                FROM {$this->table}
                WHERE tanggal BETWEEN ? AND ?
                GROUP BY tanggal
                ORDER BY tanggal ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Daerah Irigasi List
     */
    public function getDaerahIrigasiList() {
        return self::DAERAH_IRIGASI;
    }
    
    /**
     * Delete data by date
     */
    public function deleteByDate($tanggal) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE tanggal = ?");
        return $stmt->execute([$tanggal]);
    }
    
    // ========== LOGGING METHODS ==========
    
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
    
    public function getRecentLogs($limit = 10) {
        $limit = (int) $limit;
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->logTable} ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========== TABLE MANAGEMENT ==========
    
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function createTablesIfNotExist() {
        if (!$this->tableExists($this->table)) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATE NOT NULL,
                daerah_irigasi VARCHAR(100) NOT NULL COMMENT 'Nama DI / DAM',
                kecamatan VARCHAR(100),
                luas_sawah DECIMAL(10,2) COMMENT 'Hektar',
                debit_air DECIMAL(10,2) COMMENT 'Liter/detik',
                status_pintu VARCHAR(20) COMMENT 'Aman / Waspada / Kritis',
                metode_data ENUM('aktual', 'manual', 'simulasi') NOT NULL DEFAULT 'manual',
                keterangan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tanggal (tanggal),
                UNIQUE KEY unique_data (tanggal, daerah_irigasi)
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
    
    public static function formatNumber($number, $decimals = 0) {
        return number_format($number ?? 0, $decimals, ',', '.');
    }
}
