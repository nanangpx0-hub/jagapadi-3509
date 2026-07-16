<?php
/**
 * Master Kecamatan Model
 * 
 * Model untuk data master kecamatan yang digunakan dalam
 * Data Storytelling untuk filter wilayah analisis.
 * 
 * @version 1.0.0
 * @author JAGAPADI System - Data Storytelling Module
 */

class MasterKecamatan extends Model {
    
    protected $table = 'master_kecamatan';
    
    /**
     * Get all kecamatan ordered by name
     * 
     * @return array
     */
    public function getAllOrdered(): array {
        $sql = "
            SELECT 
                id,
                nama_kecamatan
            FROM {$this->table} 
            ORDER BY nama_kecamatan ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get kecamatan by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        $sql = "
            SELECT 
                id,
                nama_kecamatan
            FROM {$this->table} 
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Get kecamatan with production statistics
     * 
     * @param int $year
     * @return array
     */
    public function getWithProductionStats(int $year): array {
        $sql = "
            SELECT 
                mk.id,
                mk.nama_kecamatan,
                COALESCE(SUM(pg.luas_panen), 0) as total_luas_panen,
                COALESCE(SUM(pg.produksi), 0) as total_produksi,
                COALESCE(AVG(pg.produktivitas), 0) as avg_produktivitas,
                COUNT(pg.id) as jumlah_laporan
            FROM {$this->table} mk
            LEFT JOIN master_desa md ON mk.id = md.kecamatan_id
            LEFT JOIN produksi_gabah pg ON md.id = pg.desa_id 
                AND YEAR(pg.tanggal_panen) = ?
                AND pg.status_verifikasi = 'verified'
            WHERE 1=1
            GROUP BY mk.id, mk.nama_kecamatan
            ORDER BY mk.nama_kecamatan ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search kecamatan by name
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array {
        $sql = "
            SELECT 
                id,
                nama_kecamatan
            FROM {$this->table} 
            WHERE nama_kecamatan LIKE ?
            ORDER BY nama_kecamatan ASC
            LIMIT 10
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['%' . $query . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get kecamatan by kabupaten ID
     * Used by WilayahController::kecamatan() for cascading dropdown
     * 
     * @param string|int $kabupatenId Kabupaten ID
     * @param string|null $q Search query (optional)
     * @param int $limit Max results (default 100)
     * @return array
     */
    public function getByKabupaten($kabupatenId, $q = null, $limit = 100): array {
        $kabupatenId = $this->normalizeKabupatenId($kabupatenId);
        if ($kabupatenId === null) {
            return [];
        }

        $limit = (int)$limit;
        
        if (!$q) {
            $sql = "SELECT id, kode_kecamatan, nama_kecamatan, kabupaten_id 
                    FROM {$this->table} 
                    WHERE kabupaten_id = ? AND deleted_at IS NULL 
                    ORDER BY nama_kecamatan ASC 
                    LIMIT $limit";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kabupatenId]);
        } else {
            $sql = "SELECT id, kode_kecamatan, nama_kecamatan, kabupaten_id 
                    FROM {$this->table} 
                    WHERE kabupaten_id = ? AND nama_kecamatan LIKE ? AND deleted_at IS NULL 
                    ORDER BY nama_kecamatan ASC 
                    LIMIT $limit";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$kabupatenId, '%'.$q.'%']);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeKabupatenId($kabupatenId): ?string {
        if ($kabupatenId === null) {
            return null;
        }

        $kabupatenId = trim((string)$kabupatenId);
        if ($kabupatenId === '') {
            return null;
        }

        return ctype_digit($kabupatenId) && strlen($kabupatenId) === 1
            ? str_pad($kabupatenId, 2, '0', STR_PAD_LEFT)
            : $kabupatenId;
    }
    
    /**
     * Get all kecamatan with kabupaten filter and pagination
     * Used by AdminWilayahController::kecamatan
     */
    public function getAllWithKabupaten($kabupatenId = null, $search = '', $limit = 20, $offset = 0): array {
        $sql = "
            SELECT 
                k.*,
                kb.nama_kabupaten
            FROM {$this->table} k
            LEFT JOIN master_kabupaten kb ON k.kabupaten_id = kb.id
            WHERE k.deleted_at IS NULL
        ";
        
        $params = [];
        
        if (!empty($kabupatenId)) {
            $sql .= " AND k.kabupaten_id = ?";
            $params[] = $kabupatenId;
        }
        
        if (!empty($search)) {
            $sql .= " AND (k.nama_kecamatan LIKE ? OR k.kode_kecamatan LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY kb.nama_kabupaten ASC, k.nama_kecamatan ASC";
        $sql .= " LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count kecamatan with filters
     * Used by AdminWilayahController::kecamatan
     */
    public function count($kabupatenId = null, $search = ''): int {
        return $this->countWithFilters($search, $kabupatenId);
    }

    /**
     * Get all with pagination and filters for API
     */
    public function getAllWithPaginationAndFilters($search, $limit, $offset, $orderColumn, $orderDir, $kabupatenId = null) {
        $sql = "
            SELECT 
                k.*,
                kb.nama_kabupaten
            FROM {$this->table} k
            LEFT JOIN master_kabupaten kb ON k.kabupaten_id = kb.id
            WHERE k.deleted_at IS NULL
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (k.nama_kecamatan LIKE ? OR k.kode_kecamatan LIKE ? OR kb.nama_kabupaten LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($kabupatenId)) {
            $sql .= " AND k.kabupaten_id = ?";
            $params[] = $kabupatenId;
        }
        
        // Allowed columns for ordering
        $allowedColumns = ['id', 'nama_kecamatan', 'kode_kecamatan', 'kabupaten_id', 'kode_kabupaten'];
        if (!in_array($orderColumn, $allowedColumns)) {
            $orderColumn = 'nama_kecamatan';
        }
        
        // Fix for kode_kabupaten sorting if joined
        if ($orderColumn === 'kode_kabupaten') {
            $orderColumn = 'kb.kode_kabupaten';
        }

        $sql .= " ORDER BY {$orderColumn} " . ($orderDir === 'desc' ? 'DESC' : 'ASC');
        $sql .= " LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count with filters for API
     */
    public function countWithFilters($search, $kabupatenId = null) {
        $sql = "
            SELECT COUNT(*) 
            FROM {$this->table} k
            LEFT JOIN master_kabupaten kb ON k.kabupaten_id = kb.id
            WHERE k.deleted_at IS NULL
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (k.nama_kecamatan LIKE ? OR k.kode_kecamatan LIKE ? OR kb.nama_kabupaten LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($kabupatenId)) {
            $sql .= " AND k.kabupaten_id = ?";
            $params[] = $kabupatenId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find by ID with Kabupaten details
     */
    public function findByIdWithKabupaten($id) {
        $sql = "
            SELECT 
                k.*,
                kb.nama_kabupaten,
                kb.kode_kabupaten
            FROM {$this->table} k
            LEFT JOIN master_kabupaten kb ON k.kabupaten_id = kb.id
            WHERE k.id = ? AND k.deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if name exists in kabupaten
     */
    public function checkNameExists($kabupatenId, $namaKecamatan) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE kabupaten_id = ? AND nama_kecamatan = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kabupatenId, $namaKecamatan]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if kode exists
     */
    public function checkKodeExists($kodeKecamatan) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE kode_kecamatan = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kodeKecamatan]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Find by ID (Alias for parent find but with cache check option if needed)
     */
    public function findById($id) {
        return $this->find($id);
    }

    /**
     * Soft delete
     */
    public function softDelete($id, $userId) {
        $data = [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId
        ];
        return $this->update($id, $data);
    }
    
    /**
     * Clear cache by kabupaten (Placeholder)
     */
    public function clearCacheByKabupaten($kabupatenId) {
        // No caching implemented yet
        return true;
    }
    
    /**
     * Count by kabupaten
     */
    public function countByKabupaten($kabupatenId) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE kabupaten_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kabupatenId]);
        return $stmt->fetchColumn();
    }
}
