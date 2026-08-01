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
    
    /**
     * Get available map layers
     * GET /api/dashboard/map/layers
     */
    public function layers() {
        try {
            $layers = [
                [
                    'id' => 'hama',
                    'name' => 'Sebaran Hama/OPT',
                    'description' => 'Lokasi laporan serangan hama dan OPT',
                    'icon' => 'bug',
                    'color' => '#dc3545',
                    'enabled' => true
                ],
                [
                    'id' => 'irigasi',
                    'name' => 'Infrastruktur Irigasi',
                    'description' => 'Daerah irigasi dan debit air',
                    'icon' => 'water',
                    'color' => '#0d6efd',
                    'enabled' => true
                ],
                [
                    'id' => 'rainfall',
                    'name' => 'Curah Hujan',
                    'description' => 'Data curah hujan per kecamatan',
                    'icon' => 'cloud-rain',
                    'color' => '#198754',
                    'enabled' => true
                ],
                [
                    'id' => 'wind',
                    'name' => 'Kecepatan Angin',
                    'description' => 'Data kecepatan angin',
                    'icon' => 'wind',
                    'color' => '#6f42c1',
                    'enabled' => true
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $layers,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data layers: ' . $e->getMessage());
        }
    }
    
    /**
     * Get hama/pest distribution data for map
     * GET /api/dashboard/map/hama
     */
    public function hama() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y'),
                'status' => $_GET['status'] ?? ''
            ];
            
            $data = $this->aggregator->getHamaMapData($filters);
            
            // Transform to GeoJSON format
            $geojson = $this->toGeoJSON($data, 'hama');
            
            $this->jsonResponse([
                'success' => true,
                'data' => $geojson,
                'count' => count($data),
                'filters' => $filters,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data hama: ' . $e->getMessage());
        }
    }
    
    /**
     * Get irrigation data for map
     * GET /api/dashboard/map/irigasi
     */
    public function irigasi() {
        try {
            $data = $this->aggregator->getIrrigationByArea();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data irigasi: ' . $e->getMessage());
        }
    }
    
    /**
     * Get weather data for map
     * GET /api/dashboard/map/weather
     */
    public function weather() {
        try {
            $filters = [
                'days' => $_GET['days'] ?? 7
            ];
            
            $rainfallData = $this->aggregator->getWeatherMapData($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'rainfall' => $rainfallData
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data cuaca: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all map data combined
     * GET /api/dashboard/map/all
     */
    public function all() {
        try {
            $filters = [
                'year' => $_GET['year'] ?? date('Y')
            ];
            
            $data = $this->aggregator->getMapLayersData($filters);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat data peta: ' . $e->getMessage());
        }
    }
    
    /**
     * Get hama summary by kecamatan
     * GET /api/dashboard/map/hamaSummary
     */
    public function hamaSummary() {
        try {
            $year = $_GET['year'] ?? date('Y');
            $data = $this->aggregator->getHamaByKecamatan($year);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'year' => $year,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->errorResponse('Gagal memuat summary hama: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert data to GeoJSON format
     */
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
