<?php
require_once ROOT_PATH . '/app/core/Cache.php';

class MasterKabupaten extends Model {
    protected $table = 'master_kabupaten';
    
    public function getAllOrdered() {
        // Sort by kode ascending for consistent ordering
        return Cache::remember('master_kabupaten_all_by_kode', function() {
            $stmt = $this->db->query("SELECT * FROM master_kabupaten ORDER BY kode ASC");
            return $stmt->fetchAll();
        }, 3600); // Cache for 1 hour
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find kabupaten by ID or by kode (supports multiple formats)
     * Supports: database ID, BPS kode (3509), short kode (09), or JT-format (JT-09)
     * @param mixed $idOrKode The identifier to search for
     * @return array|false The kabupaten record or false if not found
     */
    public function findByIdOrKode($idOrKode) {
        if (empty($idOrKode) && $idOrKode !== '0' && $idOrKode !== 0) {
            return false;
        }
        
        // Convert to string for consistent comparison
        $idOrKode = (string)$idOrKode;
        
        // Step 1: Try exact ID match (as string - for IDs like '09')
        $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE id = ?");
        $stmt->execute([$idOrKode]);
        $result = $stmt->fetch();
        if ($result) {
            return $result;
        }
        
        // Step 2: Try exact kode match (e.g., "3509")
        $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE kode = ?");
        $stmt->execute([$idOrKode]);
        $result = $stmt->fetch();
        if ($result) {
            return $result;
        }
        
        // Step 3: Try BPS format conversion (e.g., "09" -> "3509", "9" -> "3509")
        if (is_numeric($idOrKode) && strlen(ltrim($idOrKode, '0')) <= 2) {
            // Convert to 2-digit padded then prepend province code
            $numericPart = (int)$idOrKode;
            $bpsKode = '35' . str_pad($numericPart, 2, '0', STR_PAD_LEFT);
            
            $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE kode = ?");
            $stmt->execute([$bpsKode]);
            $result = $stmt->fetch();
            if ($result) {
                return $result;
            }
        }
        
        // Step 4: Try JT-format match (legacy format like "JT-09")
        $jtKode = $idOrKode;
        if (!str_starts_with(strtoupper($idOrKode), 'JT-') && is_numeric($idOrKode)) {
            $jtKode = 'JT-' . str_pad((int)$idOrKode, 2, '0', STR_PAD_LEFT);
            
            // Extract the numeric part and convert to BPS format
            $bpsKode = '35' . str_pad((int)$idOrKode, 2, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE kode = ?");
            $stmt->execute([$bpsKode]);
            $result = $stmt->fetch();
            if ($result) {
                return $result;
            }
        }
        
        return false;
    }
    
    public function search($q, $limit = 50) {
        $limit = (int)$limit;
        $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE nama_kabupaten LIKE ? ORDER BY nama_kabupaten LIMIT $limit");
        $stmt->execute(['%'.$q.'%']);
        return $stmt->fetchAll();
    }

    public function findByName($nama) {
        $stmt = $this->db->prepare("SELECT * FROM master_kabupaten WHERE nama_kabupaten = ? LIMIT 1");
        $stmt->execute([$nama]);
        return $stmt->fetch();
    }
    
    // Admin CRUD Methods
    public function getAllWithPagination($search = '', $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM master_kabupaten WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (nama_kabupaten LIKE ? OR kode LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY nama_kabupaten LIMIT $limit OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function count($search = '') {
        $sql = "SELECT COUNT(*) as total FROM master_kabupaten WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (nama_kabupaten LIKE ? OR kode LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['total'] ?? 0;
    }
    
    public function create($data) {
        $sql = "INSERT INTO master_kabupaten (nama_kabupaten, kode) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$data['nama_kabupaten'], $data['kode_kabupaten']]);
        Cache::delete('master_kabupaten_all');
        Cache::delete('master_kabupaten_dropdown');
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE master_kabupaten SET nama_kabupaten = ?, kode = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$data['nama_kabupaten'], $data['kode_kabupaten'], $id]);
        Cache::delete('master_kabupaten_all');
        Cache::delete('master_kabupaten_dropdown');
        return $stmt->rowCount();
    }
    
    public function softDelete($id, $userId) {
        $sql = "DELETE FROM master_kabupaten WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        Cache::delete('master_kabupaten_all');
        Cache::delete('master_kabupaten_dropdown');
        return $stmt->rowCount();
    }
    
    public function checkKodeExists($kode, $excludeId = null) {
        $sql = "SELECT COUNT(*) as c FROM master_kabupaten WHERE kode = ?";
        $params = [$kode];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return ($stmt->fetch()['c'] ?? 0) > 0;
    }
    
    public function getAllForDropdown() {
        return Cache::remember('master_kabupaten_dropdown', function() {
            $stmt = $this->db->query("
                SELECT m.id, m.nama_kabupaten, m.kode 
                FROM master_kabupaten m
                ORDER BY m.kode ASC
            ");
            return $stmt->fetchAll();
        }, 3600); // Cache for 1 hour
    }
}
