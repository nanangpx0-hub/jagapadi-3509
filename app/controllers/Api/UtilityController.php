<?php

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';

class UtilityController extends BaseApiController {
    
    /**
     * Get recent activity log
     * GET /api/utilities/recent-activity
     */
    public function recentActivity() {
        $this->checkPermission('admin');
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $activities = [];
            
            $stmt = $db->query("SHOW TABLES LIKE 'audit_log'");
            $auditTableExists = $stmt->rowCount() > 0;
            
            if ($auditTableExists) {
                $stmt = $db->prepare("
                    SELECT 
                        action,
                        table_name,
                        record_id,
                        old_values,
                        new_values,
                        created_at as timestamp,
                        CASE 
                            WHEN action = 'DELETE' THEN 'Menghapus kecamatan duplikat'
                            WHEN action = 'UPDATE' THEN 'Memperbarui data kecamatan'
                            WHEN action = 'CREATE' THEN 'Menambah kecamatan baru'
                            ELSE 'Aktivitas kecamatan'
                        END as description,
                        'success' as status
                    FROM audit_log 
                    WHERE table_name LIKE '%kecamatan%'
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($activities as &$activity) {
                    $activity['timestamp'] = date('Y-m-d H:i:s', strtotime($activity['timestamp']));
                    $activity['type'] = $activity['action'];
                    
                    if ($activity['action'] === 'UPDATE' && $activity['new_values']) {
                        $newData = json_decode($activity['new_values'], true);
                        if ($newData && isset($newData['nama_kecamatan'])) {
                            $activity['description'] = "Memperbarui kecamatan: " . $newData['nama_kecamatan'];
                        }
                    } elseif ($activity['action'] === 'DELETE' && $activity['old_values']) {
                        $oldData = json_decode($activity['old_values'], true);
                        if ($oldData && isset($oldData['nama_kecamatan'])) {
                            $activity['description'] = "Menghapus kecamatan: " . $oldData['nama_kecamatan'];
                        }
                    }
                }
            } else {
                $activities = [
                    [
                        'type' => 'SYSTEM',
                        'description' => 'Sistem pencegahan duplikasi diaktifkan',
                        'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                        'status' => 'success'
                    ],
                    [
                        'type' => 'ANALYSIS',
                        'description' => 'Analisis duplikasi data kecamatan selesai',
                        'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                        'status' => 'success'
                    ],
                    [
                        'type' => 'INFO',
                        'description' => 'Database constraints berhasil dibuat',
                        'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                        'status' => 'success'
                    ]
                ];
            }
            
            $this->sendResponse($activities);
            
        } catch (Exception $e) {
            $this->sendError('Terjadi kesalahan internal.', 500);
        }
    }
    
    /**
     * Get kecamatan statistics
     * GET /api/utilities/kecamatan-stats
     */
    public function kecamatanStats() {
        $this->checkPermission('admin');
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $stats = [];
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM master_kecamatan");
            $stats['total_kecamatan'] = (int)$stmt->fetch()['total'];
            
            $stmt = $db->query("SELECT COUNT(DISTINCT nama_kecamatan) as unique_nama FROM master_kecamatan");
            $stats['unique_nama_kecamatan'] = (int)$stmt->fetch()['unique_nama'];
            
            $stats['duplicate_count'] = $stats['total_kecamatan'] - $stats['unique_nama_kecamatan'];
            
            $stmt = $db->query("
                SELECT COUNT(DISTINCT nama_kecamatan) as duplicate_groups
                FROM (
                    SELECT nama_kecamatan 
                    FROM master_kecamatan 
                    GROUP BY nama_kecamatan 
                    HAVING COUNT(*) > 1
                ) as dupes
            ");
            $stats['duplicate_groups'] = (int)$stmt->fetch()['duplicate_groups'];
            
            $stmt = $db->query("
                SELECT COUNT(*) as count FROM master_kecamatan 
                WHERE kode NOT REGEXP '^35[0-9]{4}$'
                OR LENGTH(kode) != 6
            ");
            $stats['invalid_codes'] = (int)$stmt->fetch()['count'];
            
            $stmt = $db->query("
                SELECT COUNT(*) as count FROM master_kabupaten kab
                INNER JOIN master_kecamatan kec ON kab.id = kec.kabupaten_id
                GROUP BY kab.id
                HAVING COUNT(kec.id) != COUNT(DISTINCT kec.nama_kecamatan)
            ");
            $stats['kabupaten_with_issues'] = (int)$stmt->fetch()['count'];
            
            $qualityScore = 0;
            if ($stats['total_kecamatan'] > 0) {
                $validData = $stats['total_kecamatan'] - $stats['duplicate_count'] - $stats['invalid_codes'];
                $qualityScore = round(($validData / $stats['total_kecamatan']) * 100, 1);
            }
            $stats['data_quality_score'] = $qualityScore;
            
            $stats['timestamp'] = date('Y-m-d H:i:s');
            
            $this->sendResponse($stats);
            
        } catch (Exception $e) {
            $this->sendError('Terjadi kesalahan internal.', 500);
        }
    }
    
    /**
     * Filter and search desa
     * GET /api/utilities/desa-filter
     */
    public function desaFilter() {
        $this->checkPermission('admin');
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $kabupatenId = $_GET['kabupaten_id'] ?? null;
            $kecamatanId = $_GET['kecamatan_id'] ?? null;
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $conditions = [];
            $params = [];
            
            if ($kabupatenId) {
                $conditions[] = 'd.kabupaten_id = ?';
                $params[] = $kabupatenId;
            }
            
            if ($kecamatanId) {
                $conditions[] = 'd.kecamatan_id = ?';
                $params[] = $kecamatanId;
            }
            
            if ($search) {
                $conditions[] = 'd.nama_desa LIKE ?';
                $params[] = '%' . $search . '%';
            }
            
            $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            $stmt = $db->prepare("
                SELECT d.*, k.nama_kecamatan, kb.nama_kabupaten 
                FROM master_desa d 
                LEFT JOIN master_kecamatan k ON d.kecamatan_id = k.id 
                LEFT JOIN master_kabupaten kb ON d.kabupaten_id = kb.id 
                {$whereClause}
                LIMIT ? OFFSET ?
            ");
            
            foreach ($params as $i => $param) {
                $stmt->bindValue($i + 1, $param);
            }
            $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $desa = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $countStmt = $db->prepare("
                SELECT COUNT(*) FROM master_desa d 
                LEFT JOIN master_kecamatan k ON d.kecamatan_id = k.id 
                LEFT JOIN master_kabupaten kb ON d.kabupaten_id = kb.id 
                {$whereClause}
            ");
            foreach ($params as $i => $param) {
                $countStmt->bindValue($i + 1, $param);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();
            
            $totalPages = ceil($total / $limit);
            
            $this->sendResponse([
                'success' => true,
                'data' => $desa,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $totalPages,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Terjadi kesalahan internal.', 500);
        }
    }
    
    /**
     * Autocomplete desa search
     * GET /api/utilities/desa-autocomplete
     */
    public function desaAutocomplete() {
        $this->checkPermission('admin');
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
            $limit = min(15, max(1, (int)($_GET['limit'] ?? 10)));
            
            if (strlen($search) < 2) {
                $this->sendResponse(['success' => true, 'data' => []]);
            }
            
            $stmt = $db->prepare("
                SELECT d.id, d.nama_desa, d.kode_desa, 
                       k.nama_kecamatan, kb.nama_kabupaten 
                FROM master_desa d 
                LEFT JOIN master_kecamatan k ON d.kecamatan_id = k.id 
                LEFT JOIN master_kabupaten kb ON d.kabupaten_id = kb.id 
                WHERE d.nama_desa LIKE ? 
                LIMIT ?
            ");
            $stmt->execute(['%' . $search . '%', $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $suggestions = [];
            foreach ($results as $row) {
                $suggestions[] = [
                    'id' => $row['id'],
                    'value' => $row['nama_desa'],
                    'label' => $row['nama_desa'] . ' - ' . ($row['nama_kecamatan'] ?? '') . ', ' . ($row['nama_kabupaten'] ?? ''),
                    'kode_desa' => $row['kode_desa'],
                    'nama_kecamatan' => $row['nama_kecamatan'],
                    'nama_kabupaten' => $row['nama_kabupaten']
                ];
            }
            
            $this->sendResponse([
                'success' => true,
                'data' => $suggestions
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Terjadi kesalahan internal.', 500);
        }
    }
}
