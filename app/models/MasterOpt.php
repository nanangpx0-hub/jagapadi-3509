<?php
require_once ROOT_PATH . '/app/core/Cache.php';

class MasterOpt extends Model {
    protected $table = 'master_opt';
    
    /**
     * Get OPT by type (jenis)
     */
    public function getByJenis($jenis) {
        return Cache::remember('master_opt_jenis_' . $jenis, function() use ($jenis) {
            $stmt = $this->db->prepare("SELECT * FROM master_opt WHERE jenis = ? ORDER BY nama_opt");
            $stmt->execute([$jenis]);
            return $stmt->fetchAll();
        }, 3600);
    }
    
    /**
     * Basic search by code or name
     */
    public function search($keyword, $jenis = null) {
        $sql = "SELECT * FROM master_opt WHERE (kode_opt LIKE ? OR nama_opt LIKE ? OR nama_ilmiah LIKE ? OR nama_lokal LIKE ?)";
        $params = ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"];
        
        if ($jenis) {
            $sql .= " AND jenis = ?";
            $params[] = $jenis;
        }
        
        $sql .= " ORDER BY nama_opt";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Advanced search with multiple filters
     */
    public function searchAdvanced($keyword = null, $filters = []) {
        $sql = "SELECT * FROM master_opt WHERE 1=1";
        $params = [];
        
        if (!empty($keyword)) {
            $sql .= " AND (kode_opt LIKE ? OR nama_opt LIKE ? OR nama_ilmiah LIKE ? OR nama_lokal LIKE ?)";
            $params = array_merge($params, ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"]);
        }
        
        if (!empty($filters['jenis'])) {
            $sql .= " AND jenis = ?";
            $params[] = $filters['jenis'];
        }
        
        if (!empty($filters['status_karantina'])) {
            $sql .= " AND status_karantina = ?";
            $params[] = $filters['status_karantina'];
        }
        
        if (!empty($filters['tingkat_bahaya'])) {
            $sql .= " AND tingkat_bahaya = ?";
            $params[] = $filters['tingkat_bahaya'];
        }
        
        if (!empty($filters['kingdom'])) {
            $sql .= " AND kingdom = ?";
            $params[] = $filters['kingdom'];
        }
        
        $sql .= " ORDER BY nama_opt";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Paginated results with filters
     */
    public function paginate($page = 1, $perPage = 10, $filters = [], $keyword = null) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM master_opt WHERE 1=1";
        $countSql = "SELECT COUNT(*) as total FROM master_opt WHERE 1=1";
        $params = [];
        
        if (!empty($keyword)) {
            $searchClause = " AND (kode_opt LIKE ? OR nama_opt LIKE ? OR nama_ilmiah LIKE ? OR nama_lokal LIKE ?)";
            $sql .= $searchClause;
            $countSql .= $searchClause;
            $params = array_merge($params, ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"]);
        }
        
        if (!empty($filters['jenis'])) {
            $sql .= " AND jenis = ?";
            $countSql .= " AND jenis = ?";
            $params[] = $filters['jenis'];
        }
        
        if (!empty($filters['status_karantina'])) {
            $sql .= " AND status_karantina = ?";
            $countSql .= " AND status_karantina = ?";
            $params[] = $filters['status_karantina'];
        }
        
        if (!empty($filters['tingkat_bahaya'])) {
            $sql .= " AND tingkat_bahaya = ?";
            $countSql .= " AND tingkat_bahaya = ?";
            $params[] = $filters['tingkat_bahaya'];
        }
        
        if (!empty($filters['kingdom'])) {
            $sql .= " AND kingdom = ?";
            $countSql .= " AND kingdom = ?";
            $params[] = $filters['kingdom'];
        }
        
        // Get total count
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $total = $stmtCount->fetch()['total'];
        
        // Get paginated data - LIMIT/OFFSET must be integers (not parameterized)
        $perPage = (int) $perPage;
        $offset = (int) $offset;
        $sql .= " ORDER BY nama_opt LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total)
        ];
    }
    
    /**
     * Get by quarantine status
     */
    public function getByKarantina($status) {
        $stmt = $this->db->prepare("SELECT * FROM master_opt WHERE status_karantina = ? ORDER BY nama_opt");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get by danger level
     */
    public function getByBahaya($level) {
        $stmt = $this->db->prepare("SELECT * FROM master_opt WHERE tingkat_bahaya = ? ORDER BY nama_opt");
        $stmt->execute([$level]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get data for export with filters
     */
    public function getForExport($filters = [], $keyword = null) {
        return $this->searchAdvanced($keyword, $filters);
    }
    
    /**
     * Get statistics by type, quarantine status, and danger level
     */
    public function getStats() {
        return Cache::remember('master_opt_stats', function() {
            $stats = [];
            
            // By jenis
            $stmt = $this->db->query("
                SELECT jenis, COUNT(*) as total
                FROM master_opt
                GROUP BY jenis
            ");
            $stats['by_jenis'] = $stmt->fetchAll();
            
            // By karantina
            $stmt = $this->db->query("
                SELECT status_karantina, COUNT(*) as total
                FROM master_opt
                GROUP BY status_karantina
            ");
            $stats['by_karantina'] = $stmt->fetchAll();
            
            // By bahaya
            $stmt = $this->db->query("
                SELECT tingkat_bahaya, COUNT(*) as total
                FROM master_opt
                GROUP BY tingkat_bahaya
            ");
            $stats['by_bahaya'] = $stmt->fetchAll();
            
            // Total
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM master_opt");
            $stats['total'] = $stmt->fetch()['total'];
            
            return $stats;
        }, 3600);
    }
    
    /**
     * Get unique values for filter dropdowns
     */
    public function getFilterOptions() {
        return Cache::remember('master_opt_filter_options', function() {
            $options = [];
            
            // Jenis
            $options['jenis'] = ['Hama', 'Penyakit', 'Gulma'];
            
            // Status Karantina
            $options['status_karantina'] = ['Tidak', 'OPTK A1', 'OPTK A2', 'OPTK B'];
            
            // Tingkat Bahaya
            $options['tingkat_bahaya'] = ['Rendah', 'Sedang', 'Tinggi', 'Sangat Tinggi'];
            
            // Kingdom (static options — column not present in table)
            $options['kingdom'] = [];
            
            return $options;
        }, 3600);
    }
    
    /**
     * Clear cache when OPT is created/updated/deleted
     */
    public function clearCache() {
        $files = glob(ROOT_PATH . '/storage/cache/*.cache');
        foreach ($files as $file) {
            if (strpos($file, 'master_opt') !== false) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Override create to clear cache
     */
    public function create($data) {
        $this->clearCache();
        return parent::create($data);
    }
    
    /**
     * Override update to clear cache
     */
    public function update($id, $data) {
        $this->clearCache();
        return parent::update($id, $data);
    }
    
    /**
     * Override delete to clear cache
     */
    public function delete($id) {
        $this->clearCache();
        return parent::delete($id);
    }

    public function getById($id) {
        return $this->find($id);
    }

    public function getByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM master_opt WHERE nama_opt = ? LIMIT 1");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllWithFilters($filters = [], $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM master_opt WHERE 1=1";
        $params = [];

        if (isset($filters['jenis']) && $filters['jenis'] !== '') {
            $sql .= " AND jenis = ?";
            $params[] = $filters['jenis'];
        }
        if (isset($filters['kategori']) && $filters['kategori'] !== '') {
            $sql .= " AND kategori = ?";
            $params[] = $filters['kategori'];
        }
        if (isset($filters['aktif']) && $filters['aktif'] !== '') {
            $sql .= " AND aktif = ?";
            $params[] = (int)$filters['aktif'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (nama_opt LIKE ? OR nama_latin LIKE ? OR nama_ilmiah LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY nama_opt ASC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $idx => $param) {
            $stmt->bindValue($idx + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCountWithFilters($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM master_opt WHERE 1=1";
        $params = [];

        if (isset($filters['jenis']) && $filters['jenis'] !== '') {
            $sql .= " AND jenis = ?";
            $params[] = $filters['jenis'];
        }
        if (isset($filters['kategori']) && $filters['kategori'] !== '') {
            $sql .= " AND kategori = ?";
            $params[] = $filters['kategori'];
        }
        if (isset($filters['aktif']) && $filters['aktif'] !== '') {
            $sql .= " AND aktif = ?";
            $params[] = (int)$filters['aktif'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (nama_opt LIKE ? OR nama_latin LIKE ? OR nama_ilmiah LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function isUsedInReports($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM laporan_hama WHERE master_opt_id = ?");
        $stmt->execute([$id]);
        return ((int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0)) > 0;
    }

    public function toggleStatus($id) {
        $sql = "UPDATE master_opt SET aktif = CASE WHEN aktif = 1 THEN 0 ELSE 1 END WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function getStatistics() {
        return $this->getStats();
    }

    public function getByCategory($category) {
        $stmt = $this->db->prepare("SELECT * FROM master_opt WHERE kategori = ? ORDER BY nama_opt");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByType($type) {
        return $this->getByJenis($type);
    }
}
