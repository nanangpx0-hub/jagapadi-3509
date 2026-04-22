<?php
/**
 * BMKG API Client
 * 
 * Low-level HTTP client for BMKG Open Data API.
 * Handles API requests, caching, rate limiting, and error handling.
 * 
 * API Documentation: https://data.bmkg.go.id/prakiraan-cuaca
 * Rate Limit: 60 requests per minute per IP
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class BMKGApiClient {
    
    /**
     * BMKG API base URL
     */
    private const API_BASE_URL = 'https://api.bmkg.go.id/publik';
    
    /**
     * Rate limit (requests per minute)
     */
    private const RATE_LIMIT = 60;
    
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Request timeout in seconds
     */
    private const TIMEOUT = 15;
    
    /**
     * Cache directory
     */
    private $cacheDir;
    
    /**
     * Rate limiter storage file
     */
    private $rateLimiterFile;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cacheDir = ROOT_PATH . '/storage/cache/bmkg';
        $this->rateLimiterFile = ROOT_PATH . '/storage/cache/bmkg_rate_limit.json';
        
        // Ensure cache directory exists
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get weather forecast for specific location
     * 
     * @param string $adm4Code Kode wilayah tingkat IV (format: xx.xx.xx.xxxx)
     * @return array|false Forecast data or false on failure
     */
    public function getForecast($adm4Code) {
        // Validate code format
        if (!$this->isValidAdm4Code($adm4Code)) {
            error_log("BMKGApiClient: Invalid adm4 code format: {$adm4Code}");
            return false;
        }
        
        // Check cache first
        $cached = $this->getFromCache($adm4Code);
        if ($cached !== false) {
            return $cached;
        }
        
        // Check rate limit
        if (!$this->checkRateLimit()) {
            error_log("BMKGApiClient: Rate limit exceeded");
            return false;
        }
        
        // Make API request
        try {
            $url = self::API_BASE_URL . '/prakiraan-cuaca?adm4=' . urlencode($adm4Code);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => self::TIMEOUT,
                    'header' => "User-Agent: JAGAPADI/1.0\r\n" .
                               "Accept: application/json\r\n"
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("BMKGApiClient: Failed to fetch data from BMKG API for {$adm4Code}");
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("BMKGApiClient: Invalid JSON response: " . json_last_error_msg());
                return false;
            }
            
            // Cache the result
            $this->saveToCache($adm4Code, $data);
            
            // Update rate limiter
            $this->recordRequest();
            
            return $data;
            
        } catch (Exception $e) {
            error_log("BMKGApiClient: Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test API connectivity
     * 
     * @return bool True if API is accessible
     */
    public function healthCheck() {
        try {
            // Test with a known valid code (Kaliwates, Jember)
            $testCode = '35.09.01.1001';
            $url = self::API_BASE_URL . '/prakiraan-cuaca?adm4=' . $testCode;
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'header' => "User-Agent: JAGAPADI/1.0\r\n"
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            return $response !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate adm4 code format
     * 
     * @param string $code Code to validate
     * @return bool True if valid
     */
    private function isValidAdm4Code($code) {
        // Format: xx.xx.xx.xxxx (e.g., 35.09.01.1001)
        return preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $code) === 1;
    }
    
    /**
     * Check if request is within rate limit
     * 
     * @return bool True if allowed
     */
    private function checkRateLimit() {
        if (!file_exists($this->rateLimiterFile)) {
            return true;
        }
        
        $data = json_decode(file_get_contents($this->rateLimiterFile), true);
        
        if (!$data) {
            return true;
        }
        
        $now = time();
        $oneMinuteAgo = $now - 60;
        
        // Filter requests in last minute
        $recentRequests = array_filter($data['requests'], function($timestamp) use ($oneMinuteAgo) {
            return $timestamp > $oneMinuteAgo;
        });
        
        return count($recentRequests) < self::RATE_LIMIT;
    }
    
    /**
     * Record a request for rate limiting
     */
    private function recordRequest() {
        $data = ['requests' => []];
        
        if (file_exists($this->rateLimiterFile)) {
            $existing = json_decode(file_get_contents($this->rateLimiterFile), true);
            if ($existing) {
                $data = $existing;
            }
        }
        
        // Add current request
        $data['requests'][] = time();
        
        // Keep only last minute's requests
        $oneMinuteAgo = time() - 60;
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($oneMinuteAgo) {
            return $timestamp > $oneMinuteAgo;
        });
        
        // Reset array keys
        $data['requests'] = array_values($data['requests']);
        
        file_put_contents($this->rateLimiterFile, json_encode($data));
    }
    
    /**
     * Get data from cache
     * 
     * @param string $adm4Code Location code
     * @return array|false Cached data or false if not found/expired
     */
    private function getFromCache($adm4Code) {
        $cacheFile = $this->getCacheFilename($adm4Code);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        // Check if cache is still valid
        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime > self::CACHE_TTL) {
            // Cache expired
            @unlink($cacheFile);
            return false;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        return $data ?: false;
    }
    
    /**
     * Save data to cache
     * 
     * @param string $adm4Code Location code
     * @param array $data Data to cache
     */
    private function saveToCache($adm4Code, $data) {
        $cacheFile = $this->getCacheFilename($adm4Code);
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Get cache filename for location
     * 
     * @param string $adm4Code Location code
     * @return string Cache file path
     */
    private function getCacheFilename($adm4Code) {
        $safeCode = str_replace('.', '_', $adm4Code);
        return $this->cacheDir . "/forecast_{$safeCode}.json";
    }
    
    /**
     * Clear all cache
     */
    public function clearCache() {
        $files = glob($this->cacheDir . '/forecast_*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
