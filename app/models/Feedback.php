<?php
declare(strict_types=1);

/**
 * Feedback Model
 * 
 * Model untuk mengelola data masukan dan saran dari user.
 * Mendukung CRUD, voting, statistik, dan tracking status.
 * 
 * @version V.1.4.0
 * @author JAGAPADI Development Team
 */
class Feedback {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // ============================================
    // CRUD Operations
    // ============================================
    
    /**
     * Get all feedback with pagination and filters
     * 
     * @param array $filters Filter options (jenis, status, prioritas, search, user_id, year, month)
     * @param int $page Current page
     * @param int $limit Items per page
     * @return array Feedback data with pagination info
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        $where = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['jenis'])) {
            $where[] = "f.jenis_feedback = ?";
            $params[] = $filters['jenis'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['prioritas'])) {
            $where[] = "f.prioritas = ?";
            $params[] = $filters['prioritas'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(f.judul LIKE ? OR f.deskripsi LIKE ?)";
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "f.user_id = ?";
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['year'])) {
            $where[] = "YEAR(f.created_at) = ?";
            $params[] = (int) $filters['year'];
        }

        if (!empty($filters['month'])) {
            $where[] = "MONTH(f.created_at) = ?";
            $params[] = (int) $filters['month'];
        }
        
        $whereClause = implode(" AND ", $where);
        
        try {
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM feedback f WHERE {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } catch (\PDOException $e) {
            error_log('Feedback::getAll(count) - ' . $e->getMessage());
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'totalPages' => 0];
        }

        // Cast to integers for LIMIT/OFFSET (avoid SQL injection by ensuring they are integers)
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        
        try {
            // Get data with user info - embed LIMIT/OFFSET directly as integers
            $sql = "SELECT f.*, 
                           u.nama_lengkap as user_nama,
                           u.username as user_username,
                           u.role as user_role,
                           p.nama_lengkap as processor_nama
                    FROM feedback f
                    LEFT JOIN users u ON f.user_id = u.id
                    LEFT JOIN users p ON f.processed_by = p.id
                    WHERE {$whereClause}
                    ORDER BY f.created_at DESC
                    LIMIT {$limit} OFFSET {$offset}";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('Feedback::getAll(data) - ' . $e->getMessage());
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'totalPages' => 0];
        }
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / $limit)
        ];
    }
    
    /**
     * Get feedback by ID with full details
     * 
     * @param int $id Feedback ID
     * @return array|null Feedback data or null if not found
     */
    public function getById(int $id): ?array {
        $sql = "SELECT f.*, 
                       u.nama_lengkap as user_nama,
                       u.username as user_username,
                       u.role as user_role,
                       u.email as user_email,
                       p.nama_lengkap as processor_nama
                FROM feedback f
                LEFT JOIN users u ON f.user_id = u.id
                LEFT JOIN users p ON f.processed_by = p.id
                WHERE f.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Create new feedback
     * 
     * @param array $data Feedback data
     * @return int|false New feedback ID or false on failure
     */
    public function create(array $data): int|false {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $sql = "INSERT INTO feedback (user_id, jenis_feedback, judul, deskripsi, prioritas, attachment_url)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                $data['user_id'],
                $data['jenis_feedback'],
                $data['judul'],
                $data['deskripsi'],
                $data['prioritas'] ?? 'medium',
                $data['attachment_url'] ?? null
            ]);

            if (!$success) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return false;
            }

            $feedbackId = (int) $this->db->lastInsertId();

            // Log status awal — dalam transaksi yang sama agar tidak ada data parsial
            $historySuccess = $this->logStatusChange(
                $feedbackId,
                null,
                'diterima',
                (int) $data['user_id'],
                'Feedback baru dibuat'
            );
            if (!$historySuccess) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return false;
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $feedbackId;
        } catch (\PDOException $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Feedback::create - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update feedback status
     * 
     * @param int $id Feedback ID
     * @param string $status New status
     * @param int $adminId Admin ID who processes
     * @param string $notes Admin notes
     * @return bool Success status
     */
    public function updateStatus(int $id, string $status, int $adminId, string $notes = ''): bool {
        // Get current status for logging
        $current = $this->getById($id);
        if (!$current) return false;

        $oldStatus = $current['status'];
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $sql = "UPDATE feedback 
                    SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW(), updated_at = NOW()
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([$status, $notes, $adminId, $id]);

            if (!$success) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return false;
            }

            // Log status change — transaksi yang sama agar riwayat tidak terputus
            $historySuccess = $this->logStatusChange($id, $oldStatus, $status, $adminId, $notes);
            if (!$historySuccess) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return false;
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (\PDOException $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Feedback::updateStatus - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update feedback data (full update)
     * 
     * @param int $id Feedback ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = ['judul', 'deskripsi', 'jenis_feedback', 'prioritas', 'attachment_url'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE feedback SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete feedback
     * 
     * @param int $id Feedback ID
     * @return bool Success status
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM feedback WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    // ============================================
    // Voting System
    // ============================================
    
    /**
     * Add vote to feedback
     * 
     * @param int $feedbackId Feedback ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function addVote(int $feedbackId, int $userId): bool {
        try {
            $sql = "INSERT INTO feedback_votes (feedback_id, user_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([$feedbackId, $userId]);
            
            if ($success) {
                $this->updateVoteCount($feedbackId);
            }
            
            return $success;
        } catch (\PDOException $e) {
            // Duplicate vote - user already voted
            return false;
        }
    }
    
    /**
     * Remove vote from feedback
     * 
     * @param int $feedbackId Feedback ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function removeVote(int $feedbackId, int $userId): bool {
        $sql = "DELETE FROM feedback_votes WHERE feedback_id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$feedbackId, $userId]);
        
        if ($success) {
            $this->updateVoteCount($feedbackId);
        }
        
        return $success;
    }
    
    /**
     * Check if user has voted on feedback
     * 
     * @param int $feedbackId Feedback ID
     * @param int $userId User ID
     * @return bool True if user has voted
     */
    public function hasUserVoted(int $feedbackId, int $userId): bool {
        $sql = "SELECT COUNT(*) as voted FROM feedback_votes WHERE feedback_id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$feedbackId, $userId]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['voted'] ?? 0) > 0;
    }
    
    /**
     * Toggle vote (add if not voted, remove if voted)
     * 
     * @param int $feedbackId Feedback ID
     * @param int $userId User ID
     * @return array Result with action taken and new vote count
     */
    public function toggleVote(int $feedbackId, int $userId): array {
        if ($this->hasUserVoted($feedbackId, $userId)) {
            $this->removeVote($feedbackId, $userId);
            $action = 'removed';
        } else {
            $this->addVote($feedbackId, $userId);
            $action = 'added';
        }
        
        // Get updated vote count
        $feedback = $this->getById($feedbackId);
        
        return [
            'action' => $action,
            'vote_count' => (int) ($feedback['vote_count'] ?? 0)
        ];
    }
    
    /**
     * Update vote count for feedback (sync from votes table)
     * 
     * @param int $feedbackId Feedback ID
     * @return void
     */
    private function updateVoteCount(int $feedbackId): void {
        $sql = "UPDATE feedback f 
                SET vote_count = (SELECT COUNT(*) FROM feedback_votes WHERE feedback_id = f.id)
                WHERE f.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$feedbackId]);
    }
    
    // ============================================
    // Statistics
    // ============================================
    
    /**
     * Get count by status
     * 
     * @return array Count per status
     */
    public function getCountByStatus(): array {
        $sql = "SELECT status, COUNT(*) as count FROM feedback GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get count by type
     * 
     * @return array Count per jenis_feedback
     */
    public function getCountByType(): array {
        $sql = "SELECT jenis_feedback, COUNT(*) as count FROM feedback GROUP BY jenis_feedback";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly statistics
     * 
     * @param int $year Year
     * @return array Monthly stats
     */
    public function getMonthlyStats(int $year): array {
        $sql = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'dalam_proses' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as rejected
                FROM feedback
                WHERE YEAR(created_at) = ?
                GROUP BY MONTH(created_at)
                ORDER BY month";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get popular/most voted feedback
     * 
     * @param int $limit Number of items
     * @return array Top voted feedback
     */
    public function getPopularFeedback(int $limit = 10): array {
        $limit = (int) $limit;
        $sql = "SELECT f.*, 
                       u.nama_lengkap as user_nama,
                       u.role as user_role
                FROM feedback f
                LEFT JOIN users u ON f.user_id = u.id
                WHERE f.status != 'ditolak'
                ORDER BY f.vote_count DESC, f.created_at DESC
                LIMIT {$limit}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get dashboard statistics
     * 
     * @return array Dashboard stats
     */
    public function getDashboardStats(): array {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'diterima' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'dalam_proses' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN jenis_feedback = 'bug' THEN 1 ELSE 0 END) as bugs,
                        SUM(CASE WHEN jenis_feedback = 'fitur_baru' THEN 1 ELSE 0 END) as features,
                        SUM(CASE WHEN jenis_feedback = 'peningkatan' THEN 1 ELSE 0 END) as improvements
                    FROM feedback";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total' => 0, 'pending' => 0, 'in_progress' => 0,
                'completed' => 0, 'rejected' => 0,
                'bugs' => 0, 'features' => 0, 'improvements' => 0,
            ];
        } catch (\PDOException $e) {
            error_log('Feedback::getDashboardStats - ' . $e->getMessage());
            return [
                'total' => 0, 'pending' => 0, 'in_progress' => 0,
                'completed' => 0, 'rejected' => 0,
                'bugs' => 0, 'features' => 0, 'improvements' => 0,
            ];
        }
    }

    public function getDashboardStatsByUser(int $userId): array {
        return $this->getSummaryStats(['user_id' => $userId]);
    }

    public function getAdminSummaryStats(array $filters = []): array {
        return $this->getSummaryStats($filters);
    }

    public function getRekapPerPetugas(array $filters = []): array {
        [$whereClause, $params] = $this->buildSummaryWhere($filters);
        $sql = "SELECT u.id AS user_id, u.nama_lengkap, u.username,
                       COUNT(f.id) AS total,
                       SUM(f.status = 'diterima') AS pending,
                       SUM(f.status = 'dalam_proses') AS in_progress,
                       SUM(f.status = 'selesai') AS completed,
                       SUM(f.status = 'ditolak') AS rejected
                FROM feedback f
                INNER JOIN users u ON u.id = f.user_id
                WHERE u.role = 'petugas' AND {$whereClause}
                GROUP BY u.id, u.nama_lengkap, u.username
                ORDER BY total DESC, u.nama_lengkap ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSummaryStats(array $filters): array {
        [$whereClause, $params] = $this->buildSummaryWhere($filters);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(status = 'diterima') AS pending,
                       SUM(status = 'dalam_proses') AS in_progress,
                       SUM(status = 'selesai') AS completed,
                       SUM(status = 'ditolak') AS rejected,
                       SUM(jenis_feedback = 'bug') AS bugs,
                       SUM(jenis_feedback = 'fitur_baru') AS features,
                       SUM(jenis_feedback = 'peningkatan') AS improvements
                FROM feedback f WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['total', 'pending', 'in_progress', 'completed', 'rejected', 'bugs', 'features', 'improvements'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        return $row;
    }

    private function buildSummaryWhere(array $filters): array {
        $where = ['1=1'];
        $params = [];
        $map = ['user_id' => 'f.user_id', 'jenis' => 'f.jenis_feedback', 'status' => 'f.status'];
        foreach ($map as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $where[] = "{$column} = ?";
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(f.created_at) = ?';
            $params[] = (int) $filters['year'];
        }
        if (!empty($filters['month'])) {
            $where[] = 'MONTH(f.created_at) = ?';
            $params[] = (int) $filters['month'];
        }
        return [implode(' AND ', $where), $params];
    }
    
    // ============================================
    // Status History
    // ============================================
    
    /**
     * Log status change
     * 
     * @param int $feedbackId Feedback ID
     * @param string|null $oldStatus Old status
     * @param string $newStatus New status
     * @param int $changedBy User ID who changed
     * @param string $notes Notes
     * @return bool Success status
     */
    public function logStatusChange(int $feedbackId, ?string $oldStatus, string $newStatus, int $changedBy, string $notes = ''): bool {
        $sql = "INSERT INTO feedback_status_history (feedback_id, old_status, new_status, changed_by, notes)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$feedbackId, $oldStatus, $newStatus, $changedBy, $notes]);
    }
    
    /**
     * Get status history for feedback
     * 
     * @param int $feedbackId Feedback ID
     * @return array Status history
     */
    public function getStatusHistory(int $feedbackId): array {
        $sql = "SELECT h.*, u.nama_lengkap as changed_by_nama
                FROM feedback_status_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.feedback_id = ?
                ORDER BY h.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get voters list for a feedback
     * 
     * @param int $feedbackId Feedback ID
     * @return array Voters list
     */
    public function getVoters(int $feedbackId): array {
        $sql = "SELECT u.id, u.nama_lengkap, u.username, u.role, fv.created_at as voted_at
                FROM feedback_votes fv
                JOIN users u ON fv.user_id = u.id
                WHERE fv.feedback_id = ?
                ORDER BY fv.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
