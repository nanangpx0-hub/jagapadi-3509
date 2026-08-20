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
            
            // Gunakan cURL (bukan file_get_contents) agar tidak bergantung pada allow_url_fopen
            $response = $this->httpRequest($url, self::TIMEOUT);
            
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
            
            return $this->httpRequest($url, 5) !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * HTTP Request menggunakan cURL (aman dari allow_url_fopen=Off)
     * 
     * @param string $url URL target
     * @param int $timeout Timeout detik
     * @return string|false Response body atau false saat gagal
     */
    private function httpRequest($url, $timeout = self::TIMEOUT) {
        // SSL verification diaktifkan secara default; bisa dimatikan via .env untuk dev
        $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: JAGAPADI/1.0',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("BMKGApiClient: HTTP Error {$httpCode}, cURL: {$error}");
            return false;
        }

        return $response;
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

        // Baca file dengan flock (shared lock) untuk mencegah race condition
        $data = null;
        $fp = fopen($this->rateLimiterFile, 'r');
        if ($fp) {
            if (flock($fp, LOCK_SH)) {
                $content = stream_get_contents($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            $data = json_decode($content ?? '', true);
        }
        
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
        // Baca data existing dengan flock (shared lock)
        $data = ['requests' => []];
        
        if (file_exists($this->rateLimiterFile)) {
            $fp = fopen($this->rateLimiterFile, 'r');
            if ($fp) {
                if (flock($fp, LOCK_SH)) {
                    $content = stream_get_contents($fp);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
                $existing = json_decode($content ?? '', true);
                if ($existing && isset($existing['requests'])) {
                    $data = $existing;
                }
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
        
        // Tulis dengan LOCK_EX untuk mencegah penulisan konkuren
        file_put_contents($this->rateLimiterFile, json_encode($data), LOCK_EX);
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
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);
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
