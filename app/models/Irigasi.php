<?php
/**
 * Irigasi Model
 * Handles irrigation data operations
 */
class Irigasi extends Model {
    protected $table = 'data_irigasi';
    protected $fillable = ['user_id', 'desa_id', 'tanggal', 'status_kondisi', 'debit_air', 'luas_lahan', 'catatan'];
    
    /**
     * Get irrigation by ID
     */
    public function getById($id) {
        try {
            $qb = new QueryBuilder();
            return $qb->table('data_irigasi di')
                ->select([
                    'di.*',
                    'u.nama_lengkap as pelapor',
                    'md.nama_desa',
                    'mk.nama_kecamatan',
                    'mkab.nama_kabupaten'
                ])
                ->leftJoin('users u', 'di.user_id = u.id')
                ->leftJoin('master_desa md', 'di.desa_id = md.id')
                ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
                ->leftJoin('master_kabupaten mkab', 'mk.kabupaten_id = mkab.id')
                ->where('di.id', $id)
                ->first();
        } catch (\PDOException $e) {
            error_log('Irigasi::getById - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all irrigation data with filters
     */
    public function getAllWithFilters($filters = [], $limit = 20, $offset = 0) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi di')
                ->select([
                    'di.*',
                    'u.nama_lengkap as pelapor',
                    'md.nama_desa',
                    'mk.nama_kecamatan',
                    'mkab.nama_kabupaten'
                ])
                ->leftJoin('users u', 'di.user_id = u.id')
                ->leftJoin('master_desa md', 'di.desa_id = md.id')
                ->leftJoin('master_kecamatan mk', 'md.kecamatan_id = mk.id')
                ->leftJoin('master_kabupaten mkab', 'mk.kabupaten_id = mkab.id');
            
            if (!empty($filters['status_kondisi'])) {
                $qb->where('di.status_kondisi', $filters['status_kondisi']);
            }
            
            if (!empty($filters['kabupaten_id'])) {
                $qb->where('di.kabupaten_id', $filters['kabupaten_id']);
            }
            
            if (!empty($filters['kecamatan_id'])) {
                $qb->where('di.kecamatan_id', $filters['kecamatan_id']);
            }
            
            if (!empty($filters['desa_id'])) {
                $qb->where('di.desa_id', $filters['desa_id']);
            }
            
            if (!empty($filters['jenis_irigasi'])) {
                $qb->where('di.jenis_irigasi', $filters['jenis_irigasi']);
            }
            
            if (!empty($filters['user_id'])) {
                $qb->where('di.user_id', $filters['user_id']);
            }
            
            if (!empty($filters['date_from'])) {
                $qb->where('di.created_at', $filters['date_from'], '>=');
            }
            
            if (!empty($filters['date_to'])) {
                $qb->where('di.created_at', $filters['date_to'], '<=');
            }
            
            $qb->orderBy('di.created_at', 'DESC')
                ->limit($limit)
                ->offset($offset);
            
            return $qb->get();
        } catch (\PDOException $e) {
            error_log('Irigasi::getAllWithFilters - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get count with filters
     */
    public function getCountWithFilters($filters = []) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi di');
            
            if (!empty($filters['status_kondisi'])) {
                $qb->where('di.status_kondisi', $filters['status_kondisi']);
            }
            
            if (!empty($filters['kabupaten_id'])) {
                $qb->where('di.kabupaten_id', $filters['kabupaten_id']);
            }
            
            if (!empty($filters['kecamatan_id'])) {
                $qb->where('di.kecamatan_id', $filters['kecamatan_id']);
            }
            
            if (!empty($filters['desa_id'])) {
                $qb->where('di.desa_id', $filters['desa_id']);
            }
            
            if (!empty($filters['jenis_irigasi'])) {
                $qb->where('di.jenis_irigasi', $filters['jenis_irigasi']);
            }
            
            if (!empty($filters['user_id'])) {
                $qb->where('di.user_id', $filters['user_id']);
            }
            
            if (!empty($filters['date_from'])) {
                $qb->where('di.created_at', $filters['date_from'], '>=');
            }
            
            if (!empty($filters['date_to'])) {
                $qb->where('di.created_at', $filters['date_to'], '<=');
            }
            
            return $qb->count();
        } catch (\PDOException $e) {
            error_log('Irigasi::getCountWithFilters - ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats($userId = null) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi');
            
            if ($userId) {
                $qb->where('user_id', $userId);
            }
            
            $total = $qb->count();
            
            $qb2 = new QueryBuilder();
            $qb2->table('data_irigasi')
                ->select(['status_kondisi', 'COUNT(*) as count'])
                ->groupBy(['status_kondisi']);
            
            if ($userId) {
                $qb2->where('user_id', $userId);
            }
            
            $statusBreakdown = $qb2->get();
            
            return [
                'total' => $total,
                'status_breakdown' => $statusBreakdown
            ];
        } catch (\PDOException $e) {
            error_log('Irigasi::getDashboardStats - ' . $e->getMessage());
            return ['total' => 0, 'status_breakdown' => []];
        }
    }

    public function getTotalCount($userId = null) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi');
            if ($userId !== null) {
                $qb->where('user_id', $userId);
            }
            return $qb->count();
        } catch (\PDOException $e) {
            error_log('Irigasi::getTotalCount - ' . $e->getMessage());
            return 0;
        }
    }

    public function getCountByStatus($status, $userId = null) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi')->where('status_kondisi', $status);
            if ($userId !== null) {
                $qb->where('user_id', $userId);
            }
            return $qb->count();
        } catch (\PDOException $e) {
            error_log('Irigasi::getCountByStatus - ' . $e->getMessage());
            return 0;
        }
    }

    public function getStatusDistribution($userId = null) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi')
                ->select(['status_kondisi', 'COUNT(*) as total'])
                ->groupBy(['status_kondisi']);
            if ($userId !== null) {
                $qb->where('user_id', $userId);
            }
            return $qb->get();
        } catch (\PDOException $e) {
            error_log('Irigasi::getStatusDistribution - ' . $e->getMessage());
            return [];
        }
    }

    public function getRecentActivities($limit = 10, $userId = null) {
        try {
            $sql = "
                SELECT di.*, u.nama_lengkap as user_name
                FROM data_irigasi di
                LEFT JOIN users u ON di.user_id = u.id
                WHERE 1=1
            ";
            $params = [];
            if ($userId !== null) {
                $sql .= " AND di.user_id = ?";
                $params[] = $userId;
            }
            $sql .= " ORDER BY di.updated_at DESC, di.created_at DESC LIMIT ?";
            $params[] = (int)$limit;

            $stmt = $this->db->prepare($sql);
            foreach ($params as $idx => $param) {
                $stmt->bindValue($idx + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('Irigasi::getRecentActivities - ' . $e->getMessage());
            return [];
        }
    }

    public function getAlerts($userId = null) {
        try {
            $sql = "
                SELECT di.id, di.nama_irigasi, di.status_kondisi, di.updated_at
                FROM data_irigasi di
                WHERE di.status_kondisi IN ('Rusak', 'Kritis', 'Butuh Perbaikan')
            ";
            $params = [];
            if ($userId !== null) {
                $sql .= " AND di.user_id = ?";
                $params[] = $userId;
            }
            $sql .= " ORDER BY di.updated_at DESC LIMIT 20";
            return $this->query($sql, $params);
        } catch (\PDOException $e) {
            error_log('Irigasi::getAlerts - ' . $e->getMessage());
            return [];
        }
    }

    public function getStatistics() {
        try {
            return [
                'total' => (int)$this->getTotalCount(),
                'status_distribution' => $this->getStatusDistribution()
            ];
        } catch (\PDOException $e) {
            error_log('Irigasi::getStatistics - ' . $e->getMessage());
            return ['total' => 0, 'status_distribution' => []];
        }
    }
}
