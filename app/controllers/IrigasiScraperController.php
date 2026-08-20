<?php
/**
 * Irigasi Scraper Controller
 * Controller untuk halaman monitoring irigasi
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class IrigasiScraperController extends Controller {
    
    private $model;
    
    public function __construct() {
        require_once ROOT_PATH . '/app/models/DataIrigasi.php';
        $this->model = new DataIrigasi();
    }
    
    protected function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }
    }
    
    protected function checkAdmin() {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Anda tidak memiliki akses';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
    
    /**
     * Dashboard View
     */
    public function index() {
        $this->checkAuth();

        $displayDate = $this->model->getLatestDate() ?? date('Y-m-d');
        $data = [
            'title' => 'Monitoring Irigasi - JAGAPADI',
            'page_title' => 'Monitoring Debit Air & Irigasi Jember',
            'daerahIrigasi' => $this->model->getDaerahIrigasiList(),
            'currentDate' => $displayDate
        ];
        
        $data['statistics'] = $this->model->getStatistics(['tanggal' => $displayDate]);
        
        // Initial charts data (last 30 days)
        $data['trendData'] = $this->model->getDebitTrend();
        
        $this->view('irigasi_scraper/index', $data);
    }
    
    /**
     * AJAX: Get Data Table
     */
    public function getData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $displayDate = $_GET['tanggal'] ?? $this->model->getLatestDate() ?? date('Y-m-d');
            $filters = [
                'tanggal' => $displayDate,
                'daerah_irigasi' => $_GET['lokasi'] ?? null,
                'status_pintu' => $_GET['status'] ?? null,
                'metode_data' => $_GET['metode'] ?? null,
                'limit' => $_GET['limit'] ?? 50,
                'offset' => $_GET['offset'] ?? 0
            ];
            
            // Remove empty filters
            $filters = array_filter($filters, function($v) { return $v !== null && $v !== ''; });
            
            $data = $this->model->getAll($filters);
            $total = $this->model->countAll($filters);
            $statistics = $this->model->getStatistics($filters);
            
            // Format for datatable
            $formattedData = array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'daerah_irigasi' => $row['daerah_irigasi'],
                    'kecamatan' => $row['kecamatan'],
                    'luas_sawah' => DataIrigasi::formatNumber($row['luas_sawah']) . ' Ha',
                    'debit_air' => DataIrigasi::formatNumber($row['debit_air']) . ' L/det',
                    'status_pintu' => $row['status_pintu'],
                    'metode_data' => $row['metode_data'] ?? 'manual',
                    'keterangan' => $row['keterangan']
                ];
            }, $data);
            
            echo json_encode([
                'success' => true,
                'data' => $formattedData,
                'total' => $total,
                'tanggal' => $displayDate,
                'statistics' => [
                    'total_lokasi' => $statistics['total_lokasi'],
                    'rata_debit' => DataIrigasi::formatNumber($statistics['rata_debit']) . ' L/det',
                    'jumlah_kritis' => $statistics['jumlah_kritis'],
                    'jumlah_waspada' => $statistics['jumlah_waspada'],
                    'status_aman' => $statistics['total_lokasi'] - $statistics['jumlah_kritis'] - $statistics['jumlah_waspada']
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * AJAX: Get Chart Data
     */
    public function getChartData() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        try {
            $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
            $data = $this->model->getDebitTrend($days);
            
            $labels = [];
            $avgDebit = [];
            
            foreach ($data as $row) {
                $labels[] = date('d M', strtotime($row['tanggal']));
                $avgDebit[] = $row['rata_debit'];
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Rata-rata Debit (L/det)',
                        'data' => $avgDebit,
                        'borderColor' => '#36b9cc',
                        'backgroundColor' => 'rgba(54, 185, 204, 0.1)',
                        'fill' => true
                    ]
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Trigger Scraper (Admin)
     */
    public function runScraper() {
        $this->checkAuth();
        $this->checkAdmin(); // Only admin can trigger scraping
        $this->requireRequestMethod(['POST']);
        
        header('Content-Type: application/json');
        
        if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid Token']);
            exit;
        }
        
        try {
            require_once ROOT_PATH . '/app/services/IrigasiScraper.php';
            $scraper = new IrigasiScraper();
            
            $options = [
                'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
                'daerah_irigasi' => !empty($_POST['lokasi']) ? $_POST['lokasi'] : null,
                'force_refresh' => isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'on'
            ];
            
            $result = $scraper->run($options);
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Export CSV
     */
    public function export() {
        $this->checkAuth();
        
        $filters = [
            'tanggal' => $_GET['tanggal'] ?? date('Y-m-d'),
            'daerah_irigasi' => $_GET['lokasi'] ?? null,
            'status_pintu' => $_GET['status'] ?? null,
            'metode_data' => $_GET['metode'] ?? null
        ];
        
        $filters = array_filter($filters);
        $data = $this->model->getAll($filters);
        
        $filename = 'data_irigasi_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Tanggal', 'Daerah Irigasi', 'Kecamatan', 'Luas Layanan (Ha)', 'Debit (L/det)', 'Status', 'Metode Data', 'Keterangan']);
        
        foreach ($data as $row) {
            $csvRow = $this->sanitizeCsvRow([
                $row['tanggal'],
                $row['daerah_irigasi'],
                $row['kecamatan'],
                $row['luas_sawah'],
                $row['debit_air'],
                $row['status_pintu'],
                $row['metode_data'] ?? 'manual',
                $row['keterangan']
            ]);
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
}
