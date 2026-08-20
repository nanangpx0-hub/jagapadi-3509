<?php
declare(strict_types=1);

require_once ROOT_PATH . '/app/models/DataPertanianBps.php';

/**
 * REST API Controller for BPS Data
 *
 * Public API endpoints for external system integration.
 * Auth: X-API-Key header validated via ApiAuthMiddleware (external source).
 * Rate limit: 100 requests/minute via RateLimiter middleware.
 *
 * Endpoints:
 *   GET  /api/v1/bps/data?tahun=2025&kabupaten=Jember&limit=10&offset=0
 *   GET  /api/v1/bps/statistics?tahun=2025
 *   GET  /api/v1/bps/trend?start=2020&end=2025
 *   POST /api/v1/bps/scrape
 *   GET  /api/v1/bps/status/{jobId}
 */
class ApiBpsController {
    
    private $model;
    private $db;
    private $cache;
    
    public function __construct() {
        $this->model = new DataPertanianBps();
        $this->db = Database::getInstance()->getConnection();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Send JSON response and exit
     */
    private function respond(int $code, array $data): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * GET /api/v1/bps/data?tahun=2025&kabupaten=Jember&limit=10&offset=0
     * Get paginated BPS agricultural data
     */
    public function data() {
        $cacheKey = 'api_bps_data_' . md5(serialize($_GET));
        if ($this->cache->isAvailable() && ($cached = $this->cache->get($cacheKey)) !== null) {
            $this->respond(200, $cached);
        }
        
        $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;
        $kabupaten = $_GET['kabupaten'] ?? null;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $filters = [];
        if ($tahun) $filters['tahun'] = $tahun;
        if ($kabupaten) $filters['kabupaten_kota'] = $kabupaten;
        $filters['limit'] = $limit;
        $filters['offset'] = $offset;
        
        $data = $this->model->getAll($filters);
        $total = $this->model->countAll(array_filter($filters, fn($k) => $k !== 'limit' && $k !== 'offset', ARRAY_FILTER_USE_KEY));
        
        $response = [
            'success' => true,
            'meta' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'data' => array_map([$this, 'formatRecordForApi'], $data)
        ];
        
        if ($this->cache->isAvailable()) {
            $this->cache->set($cacheKey, $response, 300);
        }
        
        $this->respond(200, $response);
    }
    
    /**
     * GET /api/v1/bps/statistics?tahun=2025
     * Get statistics for a given year
     */
    public function statistics() {
        $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
        
        $cacheKey = "api_bps_stats_{$tahun}";
        if ($this->cache->isAvailable() && ($cached = $this->cache->get($cacheKey)) !== null) {
            $this->respond(200, $cached);
        }
        
        $statistics = $this->model->getStatistics($tahun);
        
        $response = [
            'success' => true,
            'tahun' => $tahun,
            'statistics' => [
                'jumlah_kabupaten' => (int)($statistics['jumlah_kabupaten'] ?? 0),
                'total_luas_panen' => (float)($statistics['total_luas_panen'] ?? 0),
                'total_produksi_gabah' => (float)($statistics['total_produksi_gabah'] ?? 0),
                'total_produksi_beras' => (float)($statistics['total_produksi_beras'] ?? 0),
                'rata_produktivitas' => (float)($statistics['rata_produktivitas'] ?? 0)
            ]
        ];
        
        if ($this->cache->isAvailable()) {
            $this->cache->set($cacheKey, $response, 300);
        }
        
        $this->respond(200, $response);
    }
    
    /**
     * GET /api/v1/bps/trend?start=2020&end=2025
     * Get production trend data over multiple years
     */
    public function trend() {
        $startYear = isset($_GET['start']) ? (int)$_GET['start'] : (int)date('Y') - 2;
        $endYear = isset($_GET['end']) ? (int)$_GET['end'] : (int)date('Y');
        
        $cacheKey = "api_bps_trend_{$startYear}_{$endYear}";
        if ($this->cache->isAvailable() && ($cached = $this->cache->get($cacheKey)) !== null) {
            $this->respond(200, $cached);
        }
        
        $stmt = $this->db->prepare(
            "SELECT tahun, 
             SUM(luas_panen) as total_luas, 
             SUM(produksi_gabah) as total_produksi,
             AVG(produktivitas) as avg_produktivitas,
             COUNT(*) as jumlah_kabupaten
             FROM data_pertanian_bps 
             WHERE tahun BETWEEN ? AND ?
             GROUP BY tahun 
             ORDER BY tahun ASC"
        );
        $stmt->execute([$startYear, $endYear]);
        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'meta' => [
                'start_year' => $startYear,
                'end_year' => $endYear,
            ],
            'data' => array_map(function($row) {
                return [
                    'tahun' => (int)$row['tahun'],
                    'total_luas_panen' => (float)$row['total_luas'],
                    'total_produksi_gabah' => (float)$row['total_produksi'],
                    'rata_produktivitas' => (float)$row['avg_produktivitas'],
                    'jumlah_kabupaten' => (int)$row['jumlah_kabupaten']
                ];
            }, $trend)
        ];
        
        if ($this->cache->isAvailable()) {
            $this->cache->set($cacheKey, $response, 360);
        }
        
        $this->respond(200, $response);
    }
    
    /**
     * POST /api/v1/bps/scrape
     * Queue a scraping job (background mode)
     *
     * Body: { "tahun": 2025, "source": "auto", "kabupaten": "Jember", "skenario": "baseline" }
     */
    public function scrape() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(405, [
                'success' => false,
                'error' => 'Method not allowed. Use POST.'
            ]);
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $tahun = (int)($input['tahun'] ?? date('Y'));
        $source = in_array($input['source'] ?? 'auto', ['simulasi', 'resmi_webapi', 'auto']) ? $input['source'] : 'auto';
        $kabupaten = $input['kabupaten'] ?? null;
        $skenario = in_array($input['skenario'] ?? 'baseline', ['baseline', 'optimis', 'pesimis']) ? $input['skenario'] : 'baseline';
        
        $sql = "INSERT INTO bps_scraping_queue (tahun, kabupaten, source, skenario, status, progress)
                VALUES (?, ?, ?, ?, 'pending', 0)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tahun, $kabupaten ?: null, $source, $skenario]);
        $jobId = (int)$this->db->lastInsertId();
        
        $this->respond(202, [
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Scraping job queued. Poll GET /api/v1/bps/status/{job_id} for progress.',
            'meta' => [
                'tahun' => $tahun,
                'source' => $source,
                'kabupaten' => $kabupaten ?: 'all',
                'skenario' => $skenario
            ]
        ]);
    }
    
    /**
     * GET /api/v1/bps/status/{jobId}
     * Get scraping job status
     */
    public function status($jobId = null) {
        if (!$jobId) {
            $this->respond(400, [
                'success' => false,
                'error' => 'Job ID required'
            ]);
        }
        
        $stmt = $this->db->prepare(
            "SELECT id, tahun, kabupaten, source, skenario, status, progress, result, error_message,
                    created_at, started_at, completed_at
             FROM bps_scraping_queue WHERE id = ?"
        );
        $stmt->execute([(int)$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            $this->respond(404, [
                'success' => false,
                'error' => 'Job not found'
            ]);
        }
        
        $result = null;
        if ($job['result']) {
            $result = json_decode($job['result'], true);
        }
        
        $this->respond(200, [
            'success' => true,
            'data' => [
                'job_id' => (int)$job['id'],
                'tahun' => (int)$job['tahun'],
                'kabupaten' => $job['kabupaten'],
                'source' => $job['source'],
                'skenario' => $job['skenario'],
                'status' => $job['status'],
                'progress' => (int)$job['progress'],
                'result' => $result,
                'error_message' => $job['error_message'],
                'created_at' => $job['created_at'],
                'started_at' => $job['started_at'],
                'completed_at' => $job['completed_at']
            ]
        ]);
    }
    
    /**
     * GET /api/v1/bps/provinsi
     * List supported provinces for scraping
     */
    public function provinsi() {
        require_once ROOT_PATH . '/app/services/BpsApiClient.php';
        $this->respond(200, [
            'success' => true,
            'data' => BpsApiClient::getProvinsiList()
        ]);
    }
    
    /**
     * GET /api/v1/bps/kabupaten-list?provCode=35
     * List kabupaten/kota for a specific province
     */
    public function kabupaten() {
        $provCode = $_GET['provCode'] ?? '35';
        require_once ROOT_PATH . '/app/services/BpsApiClient.php';
        $list = BpsApiClient::getKabupatenForProvinsi($provCode);
        
        $this->respond(200, [
            'success' => true,
            'provinsi' => $provCode,
            'data' => $list
        ]);
    }
    
    /**
     * Format a record for API response (excludes internal fields)
     */
    private function formatRecordForApi($row) {
        return [
            'id' => (int)$row['id'],
            'tahun' => (int)$row['tahun'],
            'kabupaten_kota' => $row['kabupaten_kota'],
            'kode_wilayah' => $row['kode_wilayah'],
            'luas_panen' => (float)$row['luas_panen'],
            'produksi_gabah' => (float)$row['produksi_gabah'],
            'produksi_beras' => $row['produksi_beras'] !== null ? (float)$row['produksi_beras'] : null,
            'produktivitas' => (float)$row['produktivitas'],
            'sumber_data' => $row['sumber_data'],
            'sumber_data_type' => $row['sumber_data_type'] ?? '-',
            'tipe_skenario' => $row['tipe_skenario'] ?? '-',
            'is_validated' => (bool)$row['is_validated'],
            'created_at' => $row['created_at']
        ];
    }
}
