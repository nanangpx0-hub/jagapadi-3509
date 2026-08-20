<?php
/**
 * ProduksiGabah Model
 * Model untuk data produksi gabah per lokasi dan musim tanam
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class ProduksiGabah extends Model {
    protected $table = 'produksi_gabah';
    
    // Validation ranges
    const PRODUKTIVITAS_MIN = 1.0;  // ton/ha minimum wajar
    const PRODUKTIVITAS_MAX = 15.0; // ton/ha maksimum wajar
    const KADAR_AIR_MIN = 10.0;     // %
    const KADAR_AIR_MAX = 30.0;     // %
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get all produksi gabah with filters and pagination
     */
    public function getAll($filters = []) {
        $sql = "SELECT pg.*, 
                       u.nama_lengkap as user_nama,
                       v.nama_lengkap as verifier_nama
                FROM {$this->table} pg
                LEFT JOIN users u ON pg.user_id = u.id
                LEFT JOIN users v ON pg.verified_by = v.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND pg.tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['musim_tanam'])) {
            $sql .= " AND pg.musim_tanam = ?";
            $params[] = $filters['musim_tanam'];
        }
        
        if (!empty($filters['kabupaten_id'])) {
            $sql .= " AND pg.kabupaten_id = ?";
            $params[] = $filters['kabupaten_id'];
        }
        
        if (!empty($filters['kecamatan_id'])) {
            $sql .= " AND pg.kecamatan_id = ?";
            $params[] = $filters['kecamatan_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND pg.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND pg.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (pg.nama_lokasi LIKE ? OR pg.varietas LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        $sql .= " ORDER BY pg.created_at DESC";
        
        // Use direct integer interpolation for LIMIT/OFFSET (PDO requirement)
        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $sql .= " LIMIT {$limit}";
            
            if (!empty($filters['offset'])) {
                $offset = (int)$filters['offset'];
                $sql .= " OFFSET {$offset}";
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count all records with filters
     */
    public function countAll($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['musim_tanam'])) {
            $sql .= " AND musim_tanam = ?";
            $params[] = $filters['musim_tanam'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    /**
     * Get single record by ID
     */
    public function getById($id) {
        $sql = "SELECT pg.*, 
                       u.nama_lengkap as user_nama,
                       v.nama_lengkap as verifier_nama
                FROM {$this->table} pg
                LEFT JOIN users u ON pg.user_id = u.id
                LEFT JOIN users v ON pg.verified_by = v.id
                WHERE pg.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate unique ID
     */
    public function generateUniqueId() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} WHERE unique_id LIKE ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["GBH-{$date}-%"]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] + 1;
        return sprintf("GBH-%s-%04d", $date, $count);
    }
    
    /**
     * Create new produksi gabah record
     */
    public function create($data) {
        // Validate productivity
        $validation = $this->validateProductivity($data);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        $uniqueId = $this->generateUniqueId();
        
        $sql = "INSERT INTO {$this->table} 
                (unique_id, musim_tanam, tahun, kabupaten_id, kecamatan_id, desa_id,
                 nama_lokasi, irigasi_id, varietas, luas_tanam, luas_panen,
                 produksi_total, kadar_air, grade_kualitas, harga_gabah,
                 foto, keterangan, user_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $uniqueId,
            $data['musim_tanam'],
            $data['tahun'],
            $data['kabupaten_id'],
            $data['kecamatan_id'],
            $data['desa_id'] ?? null,
            $data['nama_lokasi'],
            $data['irigasi_id'] ?? null,
            $data['varietas'] ?? null,
            $data['luas_tanam'],
            $data['luas_panen'],
            $data['produksi_total'],
            $data['kadar_air'] ?? null,
            $data['grade_kualitas'] ?? 'B',
            $data['harga_gabah'] ?? null,
            $data['foto'] ?? null,
            $data['keterangan'] ?? null,
            $data['user_id'],
            $data['status'] ?? 'pending'
        ]);
        
        if ($success) {
            $this->logActivity('create', 'success', "Data produksi gabah {$uniqueId} berhasil dibuat");
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    /**
     * Update produksi gabah record
     */
    public function update($id, $data) {
        // Validate productivity
        $validation = $this->validateProductivity($data);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        $sql = "UPDATE {$this->table} SET
                musim_tanam = ?,
                tahun = ?,
                kabupaten_id = ?,
                kecamatan_id = ?,
                desa_id = ?,
                nama_lokasi = ?,
                irigasi_id = ?,
                varietas = ?,
                luas_tanam = ?,
                luas_panen = ?,
                produksi_total = ?,
                kadar_air = ?,
                grade_kualitas = ?,
                harga_gabah = ?,
                keterangan = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $data['musim_tanam'],
            $data['tahun'],
            $data['kabupaten_id'],
            $data['kecamatan_id'],
            $data['desa_id'] ?? null,
            $data['nama_lokasi'],
            $data['irigasi_id'] ?? null,
            $data['varietas'] ?? null,
            $data['luas_tanam'],
            $data['luas_panen'],
            $data['produksi_total'],
            $data['kadar_air'] ?? null,
            $data['grade_kualitas'] ?? 'B',
            $data['harga_gabah'] ?? null,
            $data['keterangan'] ?? null,
            $id
        ]);
        
        if ($success) {
            $this->logActivity('update', 'success', "Data produksi gabah ID {$id} diperbarui");
        }
        return $success;
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$id]);
        
        if ($success) {
            $this->logActivity('delete', 'success', "Data produksi gabah ID {$id} dihapus");
        }
        return $success;
    }
    
    /**
     * Verify record
     */
    public function verify($id, $verifierId, $status = 'verified') {
        $sql = "UPDATE {$this->table} SET 
                status = ?, 
                verified_by = ?, 
                verified_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$status, $verifierId, $id]);
        
        if ($success) {
            $this->logActivity('verify', 'success', "Data produksi gabah ID {$id} diverifikasi");
        }
        return $success;
    }
    
    /**
     * Validate productivity values
     */
    public function validateProductivity($data) {
        $luasPanen = floatval($data['luas_panen'] ?? 0);
        $produksi = floatval($data['produksi_total'] ?? 0);
        
        if ($luasPanen <= 0) {
            return ['valid' => false, 'message' => 'Luas panen harus lebih dari 0'];
        }
        
        if ($produksi < 0) {
            return ['valid' => false, 'message' => 'Produksi tidak boleh negatif'];
        }
        
        $produktivitas = $produksi / $luasPanen;
        
        if ($produktivitas < self::PRODUKTIVITAS_MIN || $produktivitas > self::PRODUKTIVITAS_MAX) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Produktivitas %.2f ton/ha tampak tidak wajar. Rentang normal: %.1f - %.1f ton/ha. Mohon periksa kembali luas panen dan produksi.',
                    $produktivitas, self::PRODUKTIVITAS_MIN, self::PRODUKTIVITAS_MAX
                ),
                'warning' => true,
                'produktivitas' => $produktivitas
            ];
        }
        
        // Validate kadar air if provided
        if (!empty($data['kadar_air'])) {
            $kadarAir = floatval($data['kadar_air']);
            if ($kadarAir < self::KADAR_AIR_MIN || $kadarAir > self::KADAR_AIR_MAX) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        'Kadar air %.1f%% di luar rentang wajar (%.0f%% - %.0f%%)',
                        $kadarAir, self::KADAR_AIR_MIN, self::KADAR_AIR_MAX
                    )
                ];
            }
        }
        
        return ['valid' => true, 'produktivitas' => $produktivitas];
    }
    
    /**
     * Get statistics summary
     */
    public function getStatistics($filters = []) {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(luas_tanam) as total_luas_tanam,
                    SUM(luas_panen) as total_luas_panen,
                    SUM(produksi_total) as total_produksi,
                    ROUND(AVG(produktivitas), 2) as avg_produktivitas,
                    ROUND(AVG(kadar_air), 2) as avg_kadar_air,
                    COUNT(CASE WHEN grade_kualitas = 'A' THEN 1 END) as grade_a,
                    COUNT(CASE WHEN grade_kualitas = 'B' THEN 1 END) as grade_b,
                    COUNT(CASE WHEN grade_kualitas = 'C' THEN 1 END) as grade_c,
                    COUNT(CASE WHEN grade_kualitas = 'D' THEN 1 END) as grade_d,
                    COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_count,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                FROM {$this->table}
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tahun'])) {
            $sql .= " AND tahun = ?";
            $params[] = $filters['tahun'];
        }
        
        if (!empty($filters['musim_tanam'])) {
            $sql .= " AND musim_tanam = ?";
            $params[] = $filters['musim_tanam'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get productivity by location for map
     */
    public function getProductivityByLocation($tahun = null, $musim = null) {
        $tahun = $tahun ?: date('Y');
        
        $sql = "SELECT 
                    kecamatan_id,
                    MIN(nama_lokasi) as nama_lokasi,
                    COUNT(*) as jumlah_data,
                    SUM(luas_panen) as total_luas,
                    SUM(produksi_total) as total_produksi,
                    ROUND(AVG(produktivitas), 2) as avg_produktivitas
                FROM {$this->table}
                WHERE tahun = ?";
        $params = [$tahun];
        
        if ($musim) {
            $sql .= " AND musim_tanam = ?";
            $params[] = $musim;
        }
        
        $sql .= " GROUP BY kecamatan_id ORDER BY avg_produktivitas DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get production trend
     */
    public function getProductionTrend($years = 5) {
        $sql = "SELECT 
                    tahun,
                    musim_tanam,
                    SUM(produksi_total) as total_produksi,
                    SUM(luas_panen) as total_luas,
                    ROUND(AVG(produktivitas), 2) as avg_produktivitas
                FROM {$this->table}
                WHERE tahun >= YEAR(CURDATE()) - ?
                GROUP BY tahun, musim_tanam
                ORDER BY tahun, musim_tanam";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$years]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get musim tanam list
     */
    public function getMusimList() {
        return [
            'MT1' => 'Musim Tanam 1 (Okt-Mar)',
            'MT2' => 'Musim Tanam 2 (Apr-Jul)',
            'MT3' => 'Musim Tanam 3 (Aug-Sep)'
        ];
    }
    
    /**
     * Get grade list
     */
    public function getGradeList() {
        return [
            'A' => 'Grade A - Premium',
            'B' => 'Grade B - Baik',
            'C' => 'Grade C - Cukup',
            'D' => 'Grade D - Kurang'
        ];
    }
    
    /**
     * Log activity
     */
    public function logActivity($action, $status, $message, $details = []) {
        try {
            $sql = "INSERT INTO gabah_beras_logs (action, status, message, details, user_id, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $action,
                $status,
                $message,
                json_encode($details),
                $_SESSION['user_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("ProduksiGabah log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 10) {
        $limit = (int)$limit;
        $sql = "SELECT * FROM gabah_beras_logs ORDER BY created_at DESC LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}