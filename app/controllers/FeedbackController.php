<?php
/**
 * Feedback Controller
 * 
 * Controller untuk mengelola masukan dan saran dari semua role user.
 * Fitur: CRUD, voting, status tracking, notifikasi, dan laporan.
 * 
 * @version V.1.3.5
 * @author JAGAPADI Development Team
 */
class FeedbackController extends Controller {
    
    private $feedbackModel;
    
    public function __construct() {
        $this->feedbackModel = $this->model('Feedback');
    }
    
    /**
     * Index page - List all feedback
     * Route: GET /feedback
     */
    public function index() {
        $this->checkAuth();
        
        // Get filter parameters
        $filters = [
            'jenis' => $_GET['jenis'] ?? '',
            'status' => $_GET['status'] ?? '',
            'prioritas' => $_GET['prioritas'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        // Non-admin users can only see their own feedback by default, or all if they choose
        if ($_SESSION['role'] !== 'admin' && isset($_GET['my_feedback'])) {
            $filters['user_id'] = $_SESSION['user_id'];
        }
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 15;
        
        $result = $this->feedbackModel->getAll($filters, $page, $limit);
        
        // Get user vote status for each feedback
        foreach ($result['data'] as &$feedback) {
            $feedback['has_voted'] = $this->feedbackModel->hasUserVoted($feedback['id'], $_SESSION['user_id']);
        }
        
        // Get statistics for dashboard cards
        $stats = $this->feedbackModel->getDashboardStats();
        
        // Get popular feedback
        $popularFeedback = $this->feedbackModel->getPopularFeedback(5);
        
        $this->view('feedback/index', [
            'feedback' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages']
            ],
            'filters' => $filters,
            'stats' => $stats,
            'popularFeedback' => $popularFeedback,
            'pageTitle' => 'Masukan & Saran'
        ]);
    }
    
    /**
     * Create new feedback
     * Route: GET/POST /feedback/create
     */
    public function create() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrfToken();
            
            // Validate required fields
            $errors = [];
            
            if (empty($_POST['jenis_feedback'])) {
                $errors[] = 'Jenis feedback wajib dipilih';
            }
            
            if (empty($_POST['judul']) || strlen($_POST['judul']) < 5) {
                $errors[] = 'Judul minimal 5 karakter';
            }
            
            if (empty($_POST['deskripsi']) || strlen($_POST['deskripsi']) < 20) {
                $errors[] = 'Deskripsi minimal 20 karakter';
            }
            
            if (!in_array($_POST['prioritas'] ?? '', ['rendah', 'medium', 'tinggi'])) {
                $_POST['prioritas'] = 'medium';
            }
            
            // Handle file upload
            $attachmentUrl = null;
            if (!empty($_FILES['attachment']['name'])) {
                $uploadResult = $this->handleFileUpload($_FILES['attachment']);
                if ($uploadResult['success']) {
                    $attachmentUrl = $uploadResult['path'];
                } else {
                    $errors[] = $uploadResult['error'];
                }
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
                $_SESSION['form_data'] = $_POST;
                $this->redirect('feedback/create');
                return;
            }
            
            // Create feedback
            $data = [
                'user_id' => $_SESSION['user_id'],
                'jenis_feedback' => $_POST['jenis_feedback'],
                'judul' => htmlspecialchars(trim($_POST['judul'])),
                'deskripsi' => htmlspecialchars(trim($_POST['deskripsi'])),
                'prioritas' => $_POST['prioritas'],
                'attachment_url' => $attachmentUrl
            ];
            
            $feedbackId = $this->feedbackModel->create($data);
            
            if ($feedbackId) {
                // Notify admins about new feedback
                $this->notifyAdminsNewFeedback($feedbackId, $_SESSION['nama_lengkap']);
                
                $_SESSION['success'] = 'Feedback berhasil dikirim! Terima kasih atas masukan Anda.';
                $this->redirect('feedback');
            } else {
                $_SESSION['error'] = 'Gagal menyimpan feedback. Silakan coba lagi.';
                $this->redirect('feedback/create');
            }
            return;
        }
        
        // GET - Show form
        $formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);
        
        $this->view('feedback/create', [
            'formData' => $formData,
            'pageTitle' => 'Buat Masukan Baru'
        ]);
    }
    
    /**
     * View feedback detail
     * Route: GET /feedback/detail/{id}
     */
    public function detail($id) {
        $this->checkAuth();
        
        $feedback = $this->feedbackModel->getById($id);
        
        if (!$feedback) {
            $_SESSION['error'] = 'Feedback tidak ditemukan';
            $this->redirect('feedback');
            return;
        }
        
        // Get status history
        $statusHistory = $this->feedbackModel->getStatusHistory($id);
        
        // Check if current user has voted
        $hasVoted = $this->feedbackModel->hasUserVoted($id, $_SESSION['user_id']);
        
        // Get voters list (for admin view)
        $voters = [];
        if ($_SESSION['role'] === 'admin') {
            $voters = $this->feedbackModel->getVoters($id);
        }
        
        $this->view('feedback/detail', [
            'feedback' => $feedback,
            'statusHistory' => $statusHistory,
            'hasVoted' => $hasVoted,
            'voters' => $voters,
            'isOwner' => $feedback['user_id'] == $_SESSION['user_id'],
            'pageTitle' => 'Detail Masukan'
        ]);
    }
    
    /**
     * Toggle vote on feedback
     * Route: POST /feedback/vote/{id}
     */
    public function vote($id) {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
            return;
        }
        
        // AJAX request - verify CSRF
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCsrfToken($token)) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            return;
        }
        
        // Cannot vote on own feedback
        if ($feedback['user_id'] == $_SESSION['user_id']) {
            $this->json(['error' => 'Tidak dapat vote pada feedback sendiri'], 400);
            return;
        }
        
        $result = $this->feedbackModel->toggleVote($id, $_SESSION['user_id']);
        
        $this->json([
            'success' => true,
            'action' => $result['action'],
            'vote_count' => $result['vote_count'],
            'message' => $result['action'] === 'added' ? 'Vote berhasil ditambahkan' : 'Vote berhasil dihapus'
        ]);
    }
    
    /**
     * Update feedback status (Admin only)
     * Route: POST /feedback/updateStatus/{id}
     */
    public function updateStatus($id) {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST', 'PATCH']);
        
        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            } else {
                $_SESSION['error'] = 'Feedback tidak ditemukan';
                $this->redirect('feedback');
            }
            return;
        }
        
        $newStatus = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if (!in_array($newStatus, ['diterima', 'dalam_proses', 'selesai', 'ditolak'])) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Status tidak valid'], 400);
            } else {
                $_SESSION['error'] = 'Status tidak valid';
                $this->redirect('feedback/detail/' . $id);
            }
            return;
        }
        
        $success = $this->feedbackModel->updateStatus($id, $newStatus, $_SESSION['user_id'], $notes);
        
        if ($success) {
            // Notify the feedback creator about status change
            $this->notifyUserStatusChange($feedback, $newStatus, $notes);
            
            if ($this->isAjax()) {
                $this->json([
                    'success' => true,
                    'message' => 'Status berhasil diupdate'
                ]);
            } else {
                $_SESSION['success'] = 'Status feedback berhasil diupdate';
                $this->redirect('feedback/detail/' . $id);
            }
        } else {
            if ($this->isAjax()) {
                $this->json(['error' => 'Gagal mengupdate status'], 500);
            } else {
                $_SESSION['error'] = 'Gagal mengupdate status';
                $this->redirect('feedback/detail/' . $id);
            }
        }
    }
    
    /**
     * Delete feedback (Admin only)
     * Route: POST/DELETE /feedback/delete/{id}
     */
    public function delete($id) {
        $this->checkRole(['admin']);
        $this->requireStateChangingRequest(['POST', 'DELETE']);
        
        $feedback = $this->feedbackModel->getById($id);
        if (!$feedback) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Feedback tidak ditemukan'], 404);
            } else {
                $_SESSION['error'] = 'Feedback tidak ditemukan';
                $this->redirect('feedback');
            }
            return;
        }
        
        // Delete attachment file if exists
        if ($feedback['attachment_url'] && file_exists(ROOT_PATH . '/' . $feedback['attachment_url'])) {
            unlink(ROOT_PATH . '/' . $feedback['attachment_url']);
        }
        
        $success = $this->feedbackModel->delete($id);
        
        if ($success) {
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => 'Feedback berhasil dihapus']);
            } else {
                $_SESSION['success'] = 'Feedback berhasil dihapus';
                $this->redirect('feedback');
            }
        } else {
            if ($this->isAjax()) {
                $this->json(['error' => 'Gagal menghapus feedback'], 500);
            } else {
                $_SESSION['error'] = 'Gagal menghapus feedback';
                $this->redirect('feedback');
            }
        }
    }
    
    /**
     * Monthly report (Admin only)
     * Route: GET /feedback/report
     */
    public function report() {
        $this->checkRole(['admin']);
        
        $year = intval($_GET['year'] ?? date('Y'));
        
        $monthlyStats = $this->feedbackModel->getMonthlyStats($year);
        $statusStats = $this->feedbackModel->getCountByStatus();
        $typeStats = $this->feedbackModel->getCountByType();
        $popularFeedback = $this->feedbackModel->getPopularFeedback(10);
        
        $this->view('feedback/report', [
            'year' => $year,
            'monthlyStats' => $monthlyStats,
            'statusStats' => $statusStats,
            'typeStats' => $typeStats,
            'popularFeedback' => $popularFeedback,
            'pageTitle' => 'Laporan Feedback Bulanan'
        ]);
    }
    
    // ============================================
    // Private Helper Methods
    // ============================================
    
    /**
     * Handle file upload for attachments
     */
    private function handleFileUpload($file): array {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, WEBP, atau PDF.'];
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Ukuran file maksimal 5MB.'];
        }
        
        // Create upload directory if not exists
        $uploadDir = 'public/uploads/feedback/' . date('Y/m');
        if (!is_dir(ROOT_PATH . '/' . $uploadDir)) {
            mkdir(ROOT_PATH . '/' . $uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'feedback_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $targetPath)) {
            return ['success' => true, 'path' => $targetPath];
        }
        
        return ['success' => false, 'error' => 'Gagal mengupload file.'];
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Notify admins about new feedback
     */
    private function notifyAdminsNewFeedback($feedbackId, $creatorName) {
        // Get all admin users
        $userModel = $this->model('User');
        $admins = $userModel->getAllUsers(1, 100, '', 'admin', 'aktif', true);
        
        $feedback = $this->feedbackModel->getById($feedbackId);
        $jenisLabel = $this->getJenisLabel($feedback['jenis_feedback']);
        
        foreach ($admins['data'] ?? $admins as $admin) {
            if (isset($admin['id'])) {
                $this->createNotification(
                    $admin['id'],
                    'Masukan Baru: ' . $jenisLabel,
                    "User {$creatorName} mengirimkan masukan baru: \"{$feedback['judul']}\"",
                    'info'
                );
            }
        }
    }
    
    /**
     * Notify user about status change
     */
    private function notifyUserStatusChange($feedback, $newStatus, $notes) {
        $statusLabel = $this->getStatusLabel($newStatus);
        
        $message = "Status masukan Anda \"{$feedback['judul']}\" telah diupdate menjadi: {$statusLabel}.";
        if (!empty($notes)) {
            $message .= " Catatan admin: {$notes}";
        }
        
        $this->createNotification(
            $feedback['user_id'],
            'Update Status Masukan',
            $message,
            $newStatus === 'selesai' ? 'success' : ($newStatus === 'ditolak' ? 'danger' : 'info')
        );
    }
    
    /**
     * Create notification
     */
    private function createNotification($userId, $title, $message, $type = 'info') {
        $db = Database::getInstance()->getConnection();
        $sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $title, $message, $type]);
    }
    
    /**
     * Get jenis feedback label
     */
    private function getJenisLabel($jenis): string {
        $labels = [
            'bug' => 'Bug Report',
            'fitur_baru' => 'Fitur Baru',
            'peningkatan' => 'Saran Peningkatan'
        ];
        return $labels[$jenis] ?? $jenis;
    }
    
    /**
     * Get status label
     */
    private function getStatusLabel($status): string {
        $labels = [
            'diterima' => 'Diterima',
            'dalam_proses' => 'Dalam Proses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak'
        ];
        return $labels[$status] ?? $status;
    }
}
