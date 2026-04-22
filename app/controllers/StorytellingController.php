<?php
/**
 * Storytelling Controller
 * 
 * Controller untuk Dashboard Data Storytelling yang membantu statistisi
 * menjelaskan "MENGAPA" data produksi naik atau turun dengan menghubungkan
 * data variabel eksogen (Curah Hujan & Serangan Hama) sebagai Lagging Indicators.
 * 
 * @version 1.0.0
 * @author JAGAPADI System - Data Storytelling Module
 */

class StorytellingController extends Controller {
    
    private $dataStoryService;
    private $wilayahModel;
    
    public function __construct() {
        // Load required services and models
        require_once ROOT_PATH . '/app/services/DataStoryService.php';
        require_once ROOT_PATH . '/app/models/MasterKecamatan.php';
        
        $this->dataStoryService = new DataStoryService();
        $this->wilayahModel = new MasterKecamatan();
    }
    
    /**
     * Check authentication
     */
    protected function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }
    
    /**
     * Check if user has access to storytelling features
     * Only statistisi, admin, and operator can access
     */
    protected function checkStorytellingAccess() {
        $allowedRoles = ['admin', 'operator', 'statistisi'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            $_SESSION['error'] = 'Anda tidak memiliki akses ke fitur Data Storytelling';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
    
    /**
     * Dashboard utama Data Storytelling
     */
    public function index() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        $data = [
            'title' => 'Dashboard Data Storytelling - JAGAPADI',
            'page_title' => 'Data Storytelling: Analisis Kausalitas Produksi Padi',
            'user_role' => $_SESSION['role'],
            'user_name' => $_SESSION['nama_lengkap'] ?? 'User',
            
            // Data untuk dropdown filter
            'kecamatan_list' => $this->wilayahModel->getAllOrdered(),
            'current_month' => date('m'),
            'current_year' => date('Y'),
            'available_years' => $this->getAvailableYears(),
            
            // Data untuk cards (akan diupdate via AJAX)
            'initial_stats' => $this->getInitialStats(),
            
            // Recent analyses
            'recent_analyses' => $this->getRecentAnalyses(5)
        ];
        
        $this->view('storytelling/index', $data);
    }
    
    /**
     * AJAX handler untuk generate analisis preview
     * Method: POST
     * Expected params: bulan, tahun, wilayah_id
     */
    public function generateAnalysis() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        header('Content-Type: application/json');
        
        try {
            // Validate input
            $bulan = (int) ($_POST['bulan'] ?? 0);
            $tahun = (int) ($_POST['tahun'] ?? 0);
            $wilayahId = (int) ($_POST['wilayah_id'] ?? 0);
            
            if ($bulan < 1 || $bulan > 12) {
                throw new Exception('Bulan tidak valid (1-12)');
            }
            
            if ($tahun < 2020 || $tahun > (date('Y') + 1)) {
                throw new Exception('Tahun tidak valid');
            }
            
            if ($wilayahId <= 0) {
                throw new Exception('Wilayah harus dipilih');
            }
            
            // Check if analysis for this period already exists
            $existingAnalysis = $this->checkExistingAnalysis($bulan, $tahun, $wilayahId);
            
            // Generate analysis using DataStoryService
            $analysisResult = $this->dataStoryService->analyzeCauses($bulan, $tahun, $wilayahId);
            
            if (!$analysisResult['success']) {
                throw new Exception($analysisResult['error'] ?? 'Gagal melakukan analisis');
            }
            
            // Add existing analysis info if any
            if ($existingAnalysis) {
                $analysisResult['existing_analysis'] = $existingAnalysis;
                $analysisResult['has_existing'] = true;
            } else {
                $analysisResult['has_existing'] = false;
            }
            
            // Add chart data for visualization
            $analysisResult['chart_data'] = $this->getChartData($bulan, $tahun, $wilayahId);
            
            echo json_encode($analysisResult);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Menyimpan hasil analisis final ke database
     * Method: POST
     */
    public function store() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        header('Content-Type: application/json');
        
        try {
            // Validate CSRF token (parent method handles failure internally)
            $this->validateCsrfToken();
            
            // Get and validate input
            $input = $this->getJsonInput();
            
            $requiredFields = ['periode', 'produksi_data', 'lagging_indicators', 'faktor_penyebab_utama', 'skor_risiko', 'narasi_otomatis'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Field {$field} wajib diisi");
                }
            }
            
            // Optional: narasi_final (edited by statistician)
            if (isset($input['narasi_final']) && !empty(trim($input['narasi_final']))) {
                $input['narasi_final'] = trim($input['narasi_final']);
            }
            
            // Optional: faktor_penyebab_utama override
            if (isset($input['faktor_penyebab_override'])) {
                $validFactors = ['Cuaca Ekstrem', 'Serangan OPT', 'Normal', 'Alih Fungsi Lahan', 'Lainnya'];
                if (in_array($input['faktor_penyebab_override'], $validFactors)) {
                    $input['faktor_penyebab_utama'] = $input['faktor_penyebab_override'];
                }
            }
            
            // Save analysis using service
            $result = $this->dataStoryService->saveAnalysis($input, $_SESSION['user_id']);
            
            if ($result['success']) {
                // Log activity
                $this->logActivity('storytelling_save', "Analisis {$input['periode']['tahun']}-{$input['periode']['bulan']} disimpan", $result['id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'analysis_id' => $result['id'],
                    'action' => $result['action']
                ]);
            } else {
                throw new Exception($result['message'] ?? 'Gagal menyimpan analisis');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get chart data for trend visualization (6 months)
     */
    public function getChartData() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        header('Content-Type: application/json');
        
        try {
            $bulan = (int) ($_GET['bulan'] ?? date('m'));
            $tahun = (int) ($_GET['tahun'] ?? date('Y'));
            $wilayahId = (int) ($_GET['wilayah_id'] ?? 0);
            $months = (int) ($_GET['months'] ?? 6);
            
            if ($wilayahId <= 0) {
                throw new Exception('Wilayah harus dipilih');
            }
            
            $chartData = $this->generateChartData($bulan, $tahun, $wilayahId, $months);
            
            echo json_encode([
                'success' => true,
                'data' => $chartData
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get list of saved analyses with pagination
     */
    public function getAnalyses() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        header('Content-Type: application/json');
        
        try {
            // This seems to be using a missing method getAnalysesList
            // We'll replace this with getRecent logic for now or implement getAnalysesList later
            // For the specific route /getRecent used in view, we will add a new method below
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            
            // Placeholder response until getAnalysesList is implemented
             echo json_encode([
                'success' => true,
                'data' => [],
                'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit]
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get list of recent analyses (Public API for AJAX)
     */
    public function getRecent() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        header('Content-Type: application/json');
        
        try {
            $limit = (int) ($_GET['limit'] ?? 5);
            $data = $this->getRecentAnalyses($limit);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Publish analysis as official news/report
     */
    public function publish() {
        $this->checkAuth();
        $this->checkStorytellingAccess();
        
        // Only admin and statistisi can publish
        if (!in_array($_SESSION['role'], ['admin', 'statistisi'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Anda tidak memiliki akses untuk mempublikasi analisis'
            ]);
            return;
        }
        
        header('Content-Type: application/json');
        
        try {
            $analysisId = (int) ($_POST['analysis_id'] ?? 0);
            
            if ($analysisId <= 0) {
                throw new Exception('ID analisis tidak valid');
            }
            
            // Update status to published
            $result = $this->publishAnalysis($analysisId, $_SESSION['user_id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Analisis berhasil dipublikasi sebagai berita resmi'
                ]);
            } else {
                throw new Exception('Gagal mempublikasi analisis');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Helper methods
     */
    
    private function getAvailableYears(): array {
        $currentYear = date('Y');
        $years = [];
        
        // Get years from 3 years ago to next year
        for ($year = $currentYear - 3; $year <= $currentYear + 1; $year++) {
            $years[] = $year;
        }
        
        return $years;
    }
    
    private function getInitialStats(): array {
        try {
            $currentYear = date('Y');
            
            // Get basic stats for current year
            $sql = "
                SELECT 
                    COUNT(*) as total_analyses,
                    COUNT(CASE WHEN is_published = 1 THEN 1 END) as published_count,
                    COUNT(CASE WHEN status_analisis = 'draft' THEN 1 END) as draft_count
                FROM analisis_produksi_bulanan 
                WHERE periode_tahun = ?
            ";
            
            $stmt = Database::getInstance()->getConnection()->prepare($sql);
            $stmt->execute([$currentYear]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_analyses' => (int) ($result['total_analyses'] ?? 0),
                'published_count' => (int) ($result['published_count'] ?? 0),
                'draft_count' => (int) ($result['draft_count'] ?? 0)
            ];
        } catch (Exception $e) {
            // Table might not exist yet, return default values
            error_log("[STORYTELLING] getInitialStats error: " . $e->getMessage());
            return [
                'total_analyses' => 0,
                'published_count' => 0,
                'draft_count' => 0
            ];
        }
    }
    
    private function getRecentAnalyses(int $limit = 5): array {
        try {
            $sql = "
                SELECT 
                    apb.*,
                    mk.nama_kecamatan,
                    u.nama_lengkap as created_by_name
                FROM analisis_produksi_bulanan apb
                LEFT JOIN master_kecamatan mk ON apb.wilayah_id = mk.id
                LEFT JOIN users u ON apb.created_by = u.id
                ORDER BY apb.created_at DESC
                LIMIT ?
            ";
            
            $stmt = Database::getInstance()->getConnection()->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("[STORYTELLING] getRecentAnalyses error: " . $e->getMessage());
            return [];
        }
    }
    
    private function checkExistingAnalysis(int $bulan, int $tahun, int $wilayahId): ?array {
        $sql = "
            SELECT 
                apb.*,
                mk.nama_kecamatan,
                u.nama_lengkap as created_by_name
            FROM analisis_produksi_bulanan apb
            LEFT JOIN master_kecamatan mk ON apb.wilayah_id = mk.id
            LEFT JOIN users u ON apb.created_by = u.id
            WHERE apb.periode_bulan = ? AND apb.periode_tahun = ? AND apb.wilayah_id = ?
        ";
        
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    private function generateChartData(int $bulan, int $tahun, int $wilayahId, int $months): array {
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Luas Panen (Ha)',
                    'type' => 'bar',
                    'data' => [],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Curah Hujan (mm)',
                    'type' => 'line',
                    'data' => [],
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'yAxisID' => 'y1',
                    'fill' => false
                ],
                [
                    'label' => 'Laporan Hama',
                    'type' => 'line',
                    'data' => [],
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    'borderColor' => 'rgba(255, 206, 86, 1)',
                    'yAxisID' => 'y2',
                    'fill' => false
                ]
            ]
        ];
        
        // Generate data for the last N months
        for ($i = $months - 1; $i >= 0; $i--) {
            $targetMonth = $bulan - $i;
            $targetYear = $tahun;
            
            if ($targetMonth <= 0) {
                $targetMonth += 12;
                $targetYear--;
            }
            
            $monthName = $this->getMonthName($targetMonth);
            $chartData['labels'][] = "{$monthName} {$targetYear}";
            
            // Get production data
            $produksiData = $this->getProductionDataForChart($targetMonth, $targetYear, $wilayahId);
            $chartData['datasets'][0]['data'][] = $produksiData['luas_panen'];
            
            // Get weather data (lag -1 month)
            $lagMonth = $targetMonth - 1;
            $lagYear = $targetYear;
            if ($lagMonth <= 0) {
                $lagMonth += 12;
                $lagYear--;
            }
            
            $weatherData = $this->getWeatherDataForChart($lagMonth, $lagYear, $wilayahId);
            $chartData['datasets'][1]['data'][] = $weatherData['curah_hujan'];
            
            $hamaData = $this->getHamaDataForChart($lagMonth, $lagYear, $wilayahId);
            $chartData['datasets'][2]['data'][] = $hamaData['total_laporan'];
        }
        
        return $chartData;
    }
    
    private function getMonthName(int $month): string {
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        
        return $months[$month] ?? 'Unknown';
    }
    
    private function getProductionDataForChart(int $bulan, int $tahun, int $wilayahId): array {
        $sql = "
            SELECT COALESCE(SUM(luas_panen), 0) as luas_panen
            FROM produksi_gabah pg
            LEFT JOIN master_desa md ON pg.desa_id = md.id
            WHERE MONTH(pg.tanggal_panen) = ? 
              AND YEAR(pg.tanggal_panen) = ?
              AND md.kecamatan_id = ?
              AND pg.status_verifikasi = 'verified'
        ";
        
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ['luas_panen' => (float) $result['luas_panen']];
    }
    
    private function getWeatherDataForChart(int $bulan, int $tahun, int $wilayahId): array {
        // Get kecamatan info for pattern matching
        $kecamatanInfo = $this->getKecamatanInfo($wilayahId);
        $lokasiPattern = '%' . $kecamatanInfo['nama_kecamatan'] . '%';
        
        $sql = "
            SELECT COALESCE(AVG(curah_hujan), 0) as curah_hujan
            FROM curah_hujan
            WHERE MONTH(tanggal) = ? 
              AND YEAR(tanggal) = ?
              AND lokasi LIKE ?
        ";
        
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$bulan, $tahun, $lokasiPattern]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ['curah_hujan' => (float) $result['curah_hujan']];
    }
    
    private function getHamaDataForChart(int $bulan, int $tahun, int $wilayahId): array {
        $sql = "
            SELECT COUNT(*) as total_laporan
            FROM laporan_hama lh
            LEFT JOIN master_desa md ON lh.desa_id = md.id
            WHERE MONTH(lh.tanggal_laporan) = ? 
              AND YEAR(lh.tanggal_laporan) = ?
              AND md.kecamatan_id = ?
              AND lh.status_verifikasi = 'verified'
        ";
        
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ['total_laporan' => (int) $result['total_laporan']];
    }
    
    private function getKecamatanInfo(int $kecamatanId): array {
        $sql = "SELECT nama_kecamatan FROM master_kecamatan WHERE id = ?";
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$kecamatanId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: ['nama_kecamatan' => 'Unknown'];
    }
    
    private function getJsonInput(): array {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }
        
        return $data;
    }
    
    // validateCsrfToken() inherited from parent Controller class
    
    private function logActivity(string $action, string $description, ?int $relatedId = null): void {
        // Implementation depends on your logging system
        // This is a placeholder for activity logging
        error_log("[STORYTELLING] User {$_SESSION['user_id']}: {$action} - {$description}" . ($relatedId ? " (ID: {$relatedId})" : ""));
    }
}