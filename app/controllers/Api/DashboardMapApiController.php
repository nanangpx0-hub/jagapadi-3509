<?php
/**
 * Dashboard Map API Controller
 * API endpoints untuk data peta dashboard
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

require_once ROOT_PATH . '/app/controllers/Api/BaseApiController.php';
require_once ROOT_PATH . '/app/services/DashboardDataAggregator.php';

class DashboardMapApiController extends BaseApiController {
    private $aggregator;
    
    public function __construct() {
        $this->aggregator = new DashboardDataAggregator();
    }
    
    // =========================================
    // DEFENSE-IN-DEPTH AUTHENTICATION
    // =========================================
    
    private function assertAuthenticated(): void {
        if (empty($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }
    }
    
    private function getPetugasUserId(): ?int {
        $role   = $_SESSION['role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
 
        if ($role !== 'petugas') {
            return null;
        }
 
        $id = filter_var($userId, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return null;
        }
 
        return $id;
    }
    
    // =========================================
    // LAYER ENDPOINT
    // =========================================
    
    /**
     * Get available map layers
     * GET /api/dashboard/map/layers
     */
    public function layers() {
        $this->assertAuthenticated();
        
        try {
            $layers = [
                [
                    'id' => 'hama',
                    'name' => 'Sebaran Hama/OPT',
                    'description' => 'Lokasi laporan serangan hama dan OPT',
                    'icon' => 'bug',
                    'color' => '#dc3545',
                    'enabled' => true,
                    'scope' => 'user'
                ],
                [
                    'id' => 'irigasi',
                    'name' => 'Infrastruktur Irigasi',
                    'description' => 'Daerah irigasi dan debit air',
                    'icon' => 'water',
                    'color' => '#0d6efd',
                    'enabled' => true,
                    'scope' => 'kabupaten'
                ],
                [
                    'id' => 'rainfall',
                    'name' => 'Curah Hujan',
                    'description' => 'Data curah hujan per kecamatan',
                    'icon' => 'cloud-rain',
                    'color' => '#198754',
                    'enabled' => true,
                    'scope' => 'kabupaten'
                ],
                [
                    'id' => 'wind',
                    'name' => 'Kecepatan Angin',
                    'description' => 'Data kecepatan angin',
                    'icon' => 'wind',
                    'color' => '#6f42c1',
                    'enabled' => true,
                    'scope' => 'kabupaten'
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $layers,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data layers');
        }
    }
    
    // =========================================
    // HAMA MAP ENDPOINT
    // =========================================
    
    /**
     * Get hama/pest distribution data for map
     * GET /api/dashboard/map/hama
     */
    public function hama() {
        $this->assertAuthenticated();
        
        try {
            $rawYear   = $_GET['year'] ?? date('Y');
            $rawStatus = $_GET['status'] ?? '';
            $year      = filter_var($rawYear, FILTER_VALIDATE_INT);
            $year      = ($year !== false && $year >= 2000 && $year <= (int)date('Y') + 1)
                         ? $year : (int)date('Y');
            $allowedStatuses = ['', 'Submitted', 'Diverifikasi'];
            $status    = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : '';
            $userId    = $this->getPetugasUserId();
            $filters   = ['year' => $year, 'status' => $status, 'user_id' => $userId];
            
            $data = $this->aggregator->getHamaMapData($filters);
            
            // Transform to GeoJSON format
            $geojson = $this->toGeoJSON($data, 'hama');
            
            $responseScope = ($userId !== null) ? 'user' : 'kabupaten';
            
            $this->jsonResponse([
                'success' => true,
                'data' => $geojson,
                'count' => count($data),
                'scope' => $responseScope,
                'filters' => ['year' => $year, 'status' => $status],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data hama');
        }
    }
    
    // =========================================
    // IRIGASI ENDPOINT
    // =========================================
    
    /**
     * Get irrigation data for map
     * GET /api/dashboard/map/irigasi
     */
    public function irigasi() {
        $this->assertAuthenticated();
        
        try {
            $data = $this->aggregator->getIrrigationByArea();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'scope' => 'kabupaten',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data irigasi');
        }
    }
    
    // =========================================
    // WEATHER ENDPOINT
    // =========================================
    
    /**
     * Get weather data for map (rainfall + wind)
     * GET /api/dashboard/map/weather
     */
    public function weather() {
        $this->assertAuthenticated();
        
        try {
            $rawDays = $_GET['days'] ?? 30;
            $days    = filter_var($rawDays, FILTER_VALIDATE_INT);
            $days    = ($days !== false && $days >= 1 && $days <= 365) ? $days : 30;
            $filters = ['days' => $days];
            
            $rainfallData = $this->aggregator->getWeatherMapData($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'rainfall' => $rainfallData
                ],
                'scope' => 'kabupaten',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data cuaca');
        }
    }
    
    // =========================================
    // WIND ENDPOINT
    // =========================================
    
    /**
     * Get wind speed data for map
     * GET /api/dashboard/map/wind
     */
    public function wind() {
        $this->assertAuthenticated();
        
        try {
            $rawDays = $_GET['days'] ?? 30;
            $days    = filter_var($rawDays, FILTER_VALIDATE_INT);
            $days    = ($days !== false && $days >= 1 && $days <= 365) ? $days : 30;
            $filters = ['days' => $days];
            
            $windData = $this->aggregator->getWindMapData($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'wind' => $windData
                ],
                'scope' => 'kabupaten',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data kecepatan angin');
        }
    }
    
    // =========================================
    // ALL MAP DATA ENDPOINT
    // =========================================
    
    /**
     * Get all map data combined
     * GET /api/dashboard/map/all
     */
    public function all() {
        $this->assertAuthenticated();
        
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y')
            ];
            $userId = $this->getPetugasUserId();
            if ($userId !== null) {
                $filters['user_id'] = $userId;
            }
            
            $data = $this->aggregator->getMapLayersData($filters);
            
            $responseScope = ($userId !== null) ? 'user' : 'kabupaten';
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s'),
                'scope' => $responseScope
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data peta');
        }
    }
    
    // =========================================
    // HAMA SUMMARY ENDPOINT
    // =========================================
    
    /**
     * Get hama summary by kecamatan
     * GET /api/dashboard/map/hamaSummary
     */
    public function hamaSummary() {
        $this->assertAuthenticated();
        
        try {
            $rawYear = $_GET['year'] ?? date('Y');
            $year    = filter_var($rawYear, FILTER_VALIDATE_INT);
            $year    = ($year !== false && $year >= 2000 && $year <= (int)date('Y') + 1)
                       ? $year : (int)date('Y');
            
            $userId = $this->getPetugasUserId();
            $data = $this->aggregator->getHamaByKecamatan($year, $userId);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'year' => $year,
                'scope' => ($userId !== null) ? 'user' : 'kabupaten',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat summary hama');
        }
    }
    
    // =========================================
    // TO GEOJSON HELPER
    // =========================================
    
    private function toGeoJSON($data, $type = 'point') {
        $features = [];
        
        foreach ($data as $item) {
            if (empty($item['latitude']) || empty($item['longitude'])) {
                continue;
            }
            
            $properties = $item;
            unset($properties['latitude'], $properties['longitude']);
            
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float)$item['longitude'],
                        (float)$item['latitude']
                    ]
                ],
                'properties' => $properties
            ];
        }
        
        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }
}