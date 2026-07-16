<?php
/**
 * BMKG Service
 * 
 * High-level service for BMKG data integration.
 * Orchestrates API calls, data processing, and database operations.
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

require_once ROOT_PATH . '/app/services/BMKGApiClient.php';
require_once ROOT_PATH . '/app/services/WeatherConditionMapper.php';
require_once ROOT_PATH . '/app/models/CurahHujan.php';

class BMKGService {
    
    /**
     * @var BMKGApiClient
     */
    private $apiClient;
    
    /**
     * @var CurahHujan
     */
    private $model;
    
    /**
     * Representative locations in Jember to monitor
     * Spread across different sub-districts for better coverage
     * 
     * Format: [adm4_code => location_name]
     */
    private $monitoredLocations = [
        '35.09.01.1001' => 'Kaliwates - Mangli',
        '35.09.18.2001' => 'Sumbersari - Wirolegi',
        '35.09.19.1001' => 'Patrang - Patrang',
        '35.09.02.2001' => 'Arjasa - Arjasa',
        '35.09.27.2001' => 'Kencong - Kencong',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->apiClient = new BMKGApiClient();
        $this->model = new CurahHujan();
    }
    
    /**
     * Check if BMKG API is available
     * 
     * @return bool True if API is accessible
     */
    public function isAvailable() {
        return $this->apiClient->healthCheck();
    }
    
    /**
     * Fetch rainfall forecast for Jember
     * 
     * @param string|null $targetDate Target date (YYYY-MM-DD), null for today
     * @return array Results array with success status and data
     */
    public function fetchRainfallForecast($targetDate = null) {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d');
        }
        
        $results = [
            'success' => true,
            'date' => $targetDate,
            'locations_processed' => 0,
            'locations_failed' => 0,
            'data' => []
        ];
        
        foreach ($this->monitoredLocations as $code => $locationName) {
            try {
                $forecast = $this->apiClient->getForecast($code);
                
                if ($forecast && isset($forecast['data']) && !empty($forecast['data'])) {
                    $processed = $this->processForecastData($forecast['data'][0], $targetDate);
                    
                    if ($processed) {
                        $results['data'][] = $processed;
                        $results['locations_processed']++;
                    } else {
                        $results['locations_failed']++;
                    }
                } else {
                    error_log("BMKGService: No forecast data for {$code}");
                    $results['locations_failed']++;
                }
                
                // Small delay to avoid hammering the API
                usleep(100000); // 0.1 second
                
            } catch (Exception $e) {
                error_log("BMKGService: Error fetching {$code}: " . $e->getMessage());
                $results['locations_failed']++;
            }
        }
        
        $results['success'] = $results['locations_processed'] > 0;
        
        return $results;
    }
    
    /**
     * Process forecast data for a single location
     * 
     * @param array $forecastData Raw forecast data from BMKG
     * @param string $targetDate Target date to extract
     * @return array|false Processed data or false on failure
     */
    private function processForecastData($forecastData, $targetDate) {
        if (!isset($forecastData['lokasi']) || !isset($forecastData['cuaca'])) {
            return false;
        }
        
        $lokasi = $forecastData['lokasi'];
        $forecasts = $forecastData['cuaca'];
        
        // Filter forecasts for target date
        $targetForecasts = array_filter($forecasts, function($f) use ($targetDate) {
            if (!isset($f['local_datetime'])) {
                return false;
            }
            $forecastDate = substr($f['local_datetime'], 0, 10); // Extract YYYY-MM-DD
            return $forecastDate === $targetDate;
        });
        
        if (empty($targetForecasts)) {
            return false;
        }
        
        // Calculate daily rainfall estimate
        $totalRainfall = 0;
        $rainyPeriods = 0;
        $weatherConditions = [];
        
        foreach ($targetForecasts as $forecast) {
            if (isset($forecast['weather_desc'])) {
                $rainfall = WeatherConditionMapper::estimateRainfall($forecast['weather_desc']);
                $totalRainfall += $rainfall;
                
                if ($rainfall > 0) {
                    $rainyPeriods++;
                }
                
                $weatherConditions[] = $forecast['weather_desc'];
            }
        }
        
        // Calculate average (per 3-hour period becomes daily average)
        $dailyAverage = count($targetForecasts) > 0 ? 
                       $totalRainfall / count($targetForecasts) : 0;
        
        // Determine primary weather condition (most common)
        $primaryCondition = $this->getMostCommonCondition($weatherConditions);
        
        return [
            'tanggal' => $targetDate,
            'lokasi' => $lokasi['kotkab'] ?? 'Jember',
            'kode_wilayah' => $lokasi['adm4'] ?? null,
            'curah_hujan' => round($dailyAverage, 2),
            'satuan' => 'mm',
            'sumber_data' => 'BMKG Forecast API',
            'keterangan' => "Prakiraan cuaca: {$primaryCondition}. " .
                           "Data dari " . count($targetForecasts) . " periode prakiraan. " .
                           "Lokasi: " . ($lokasi['desa'] ?? '') . ", " . 
                           ($lokasi['kecamatan'] ?? ''),
            'weather_desc' => $primaryCondition,
            'forecast_periods' => count($targetForecasts),
            'rainy_periods' => $rainyPeriods
        ];
    }
    
    /**
     * Get most common weather condition from array
     * 
     * @param array $conditions Array of weather conditions
     * @return string Most common condition
     */
    private function getMostCommonCondition($conditions) {
        if (empty($conditions)) {
            return 'Tidak tersedia';
        }
        
        $counts = array_count_values($conditions);
        arsort($counts);
        return key($counts);
    }
    
    /**
     * Save forecast data to database
     * 
     * @param array $processedData Processed forecast data
     * @return array Results with success/failure counts
     */
    public function saveForecastData($processedData) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($processedData as $data) {
            try {
                // Check if data already exists for this date and location
                $existing = $this->model->getAll([
                    'date' => $data['tanggal'],
                    'lokasi' => $data['lokasi'],
                    'sumber_data' => 'BMKG Forecast API'
                ]);
                
                if (!empty($existing)) {
                    // Skip if already exists
                    continue;
                }
                
                // Insert new record
                $inserted = $this->model->insert([
                    'tanggal' => $data['tanggal'],
                    'lokasi' => $data['lokasi'],
                    'kode_wilayah' => $data['kode_wilayah'],
                    'curah_hujan' => $data['curah_hujan'],
                    'satuan' => $data['satuan'],
                    'sumber_data' => $data['sumber_data'],
                    'keterangan' => $data['keterangan']
                ]);
                
                if ($inserted) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Gagal insert data untuk {$data['lokasi']}";
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                error_log("BMKGService: Error saving data: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Fetch and save rainfall forecast (combined operation)
     * 
     * @param string|null $targetDate Target date
     * @return array Complete results including fetch and save operations
     */
    public function fetchAndSave($targetDate = null) {
        $fetchResults = $this->fetchRainfallForecast($targetDate);
        
        if (!$fetchResults['success'] || empty($fetchResults['data'])) {
            return [
                'success' => false,
                'message' => 'Gagal mengambil data dari BMKG API',
                'fetch_results' => $fetchResults,
                'save_results' => null
            ];
        }
        
        $saveResults = $this->saveForecastData($fetchResults['data']);
        
        // Log activity
        $this->model->logActivity(
            'bmkg_forecast',
            $saveResults['success'] > 0 ? 'success' : 'failed',
            "BMKG Forecast: {$saveResults['success']} berhasil, {$saveResults['failed']} gagal",
            [
                'processed' => $fetchResults['locations_processed'],
                'success' => $saveResults['success'],
                'failed' => $saveResults['failed']
            ]
        );
        
        return [
            'success' => $saveResults['success'] > 0,
            'message' => "Berhasil menyimpan {$saveResults['success']} data prakiraan BMKG",
            'fetch_results' => $fetchResults,
            'save_results' => $saveResults
        ];
    }
    
    /**
     * Get monitored locations
     * 
     * @return array Monitored locations
     */
    public function getMonitoredLocations() {
        return $this->monitoredLocations;
    }
    
    /**
     * Clear API cache
     */
    public function clearCache() {
        $this->apiClient->clearCache();
    }
}
