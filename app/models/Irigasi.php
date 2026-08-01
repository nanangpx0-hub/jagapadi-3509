<?php
/**
 * Irigasi Model
 * Handles irrigation data operations
 */
class Irigasi extends Model {
    protected $table = 'data_irigasi';
    protected $fillable = ['tanggal', 'daerah_irigasi', 'kecamatan', 'luas_sawah', 'debit_air', 'status_pintu', 'keterangan'];
    
    /**
     * Get irrigation by ID
     */
    public function getById($id) {
        try {
            $qb = new QueryBuilder();
            return $qb->table('data_irigasi di')
                ->select([
                    'di.*'
                ])
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
                    'di.*'
                ]);
            
            if (!empty($filters['status_pintu'])) {
                $qb->where('di.status_pintu', $filters['status_pintu']);
            }
            
            if (!empty($filters['kecamatan'])) {
                $qb->where('di.kecamatan', $filters['kecamatan']);
            }
            
            if (!empty($filters['daerah_irigasi'])) {
                $qb->where('di.daerah_irigasi', $filters['daerah_irigasi']);
            }
            
            if (!empty($filters['date_from'])) {
                $qb->where('di.tanggal', $filters['date_from'], '>=');
            }
            
            if (!empty($filters['date_to'])) {
                $qb->where('di.tanggal', $filters['date_to'], '<=');
            }
            
            $qb->orderBy('di.tanggal', 'DESC')
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
            
            if (!empty($filters['status_pintu'])) {
                $qb->where('di.status_pintu', $filters['status_pintu']);
            }
            
            if (!empty($filters['kecamatan'])) {
                $qb->where('di.kecamatan', $filters['kecamatan']);
            }
            
            if (!empty($filters['daerah_irigasi'])) {
                $qb->where('di.daerah_irigasi', $filters['daerah_irigasi']);
            }
            
            if (!empty($filters['date_from'])) {
                $qb->where('di.tanggal', $filters['date_from'], '>=');
            }
            
            if (!empty($filters['date_to'])) {
                $qb->where('di.tanggal', $filters['date_to'], '<=');
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
            
            $total = $qb->count();
            
            $qb2 = new QueryBuilder();
            $qb2->table('data_irigasi')
                ->select(['status_pintu', 'COUNT(*) as count'])
                ->groupBy(['status_pintu']);
            
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
            return $qb->count();
        } catch (\PDOException $e) {
            error_log('Irigasi::getTotalCount - ' . $e->getMessage());
            return 0;
        }
    }

    public function getCountByStatus($status, $userId = null) {
        try {
            $qb = new QueryBuilder();
            $qb->table('data_irigasi')->where('status_pintu', $status);
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
                ->select(['status_pintu', 'COUNT(*) as total'])
                ->groupBy(['status_pintu']);
            return $qb->get();
        } catch (\PDOException $e) {
            error_log('Irigasi::getStatusDistribution - ' . $e->getMessage());
            return [];
        }
    }

    public function getRecentActivities($limit = 10, $userId = null) {
        try {
            $sql = "
                SELECT di.*
                FROM data_irigasi di
                WHERE 1=1
            ";
            $params = [];
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
                SELECT di.id, di.daerah_irigasi, di.status_pintu, di.updated_at
                FROM data_irigasi di
                WHERE di.status_pintu IN ('Tertutup', 'Sebagian')
            ";
            $sql .= " ORDER BY di.updated_at DESC LIMIT 20";
            return $this->query($sql, []);
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
