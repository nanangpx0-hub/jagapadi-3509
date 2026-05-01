<?php
/**
 * Qwen Editor Token Manager
 * 
 * Manages OAuth2 access tokens for Qwen AI API.
 * Handles token caching, automatic refresh, and error recovery.
 */

class QwenEditorTokenManager {
    private $config;
    private $cacheFile;
    private $cacheDir;

    public function __construct() {
        $this->config = require ROOT_PATH . '/config/api_config.php';
        $this->cacheDir = ROOT_PATH . '/storage/cache';
        $this->cacheFile = $this->cacheDir . '/qwen_token.json';
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get valid access token (cached or fresh)
     * 
     * @return string Access token
     * @throws Exception If unable to get token
     */
    public function getAccessToken() {
        // Check if credentials are configured
        if (!$this->isConfigured()) {
            throw new Exception('Qwen API credentials not configured. Please set QWEN_API_KEY and QWEN_API_SECRET in .env');
        }

        // Try to get cached token
        $tokenData = $this->loadCachedToken();
        
        if ($this->isTokenValid($tokenData)) {
            return $tokenData['access_token'];
        }

        // Token expired or missing - refresh it
        return $this->refreshToken();
    }

    /**
     * Check if Qwen API credentials are configured
     * 
     * @return bool
     */
    public function isConfigured() {
        $qwenConfig = $this->config['qwen_editor_api'] ?? [];
        
        // Support both API key + secret and client_id + client_secret flows
        $hasApiKey = !empty($qwenConfig['api_key']) && !empty($qwenConfig['api_secret']);
        $hasClientCredentials = !empty($qwenConfig['client_id']) && !empty($qwenConfig['client_secret']);
        
        return $hasApiKey || $hasClientCredentials;
    }

    /**
     * Load cached token data from file
     * 
     * @return array|null Token data or null if not found/invalid
     */
    private function loadCachedToken() {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Save token data to cache file
     * 
     * @param array $tokenData
     * @return bool
     */
    private function saveCachedToken(array $tokenData) {
        $tokenData['cached_at'] = time();
        
        $json = json_encode($tokenData, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        return file_put_contents($this->cacheFile, $json) !== false;
    }

    /**
     * Check if cached token is still valid
     * 
     * @param array|null $tokenData
     * @return bool
     */
    private function isTokenValid($tokenData) {
        if (!is_array($tokenData)) {
            return false;
        }

        if (empty($tokenData['access_token'])) {
            return false;
        }

        // Check expiration
        $expiresAt = $tokenData['expires_at'] ?? 0;
        $buffer = 300; // 5 minutes buffer before actual expiry
        
        return (time() + $buffer) < $expiresAt;
    }

    /**
     * Refresh access token from Qwen OAuth endpoint
     * 
     * @return string New access token
     * @throws Exception If refresh fails
     */
    private function refreshToken() {
        $qwenConfig = $this->config['qwen_editor_api'];
        $tokenUrl = $qwenConfig['token_url'] ?? 'https://api.qwen.com/v1/oauth/token';

        // Prepare request parameters
        $params = [
            'grant_type' => 'client_credentials'
        ];

        // Support both authentication methods
        if (!empty($qwenConfig['client_id']) && !empty($qwenConfig['client_secret'])) {
            $params['client_id'] = $qwenConfig['client_id'];
            $params['client_secret'] = $qwenConfig['client_secret'];
        } elseif (!empty($qwenConfig['api_key']) && !empty($qwenConfig['api_secret'])) {
            $params['client_id'] = $qwenConfig['api_key'];
            $params['client_secret'] = $qwenConfig['api_secret'];
        }

        // Add refresh token if available
        if (!empty($qwenConfig['refresh_token'])) {
            $params['refresh_token'] = $qwenConfig['refresh_token'];
            $params['grant_type'] = 'refresh_token';
        }

        // Make HTTP request to token endpoint
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Network error while refreshing token: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("Token refresh failed with HTTP {$httpCode}: " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from token endpoint');
        }

        if (empty($data['access_token'])) {
            $errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            throw new Exception('Token endpoint did not return access_token: ' . $errorMsg);
        }

        // Calculate expiration time
        $expiresIn = $data['expires_in'] ?? $qwenConfig['token_ttl'] ?? 3600;
        $expiresAt = time() + $expiresIn;

        // Cache the token
        $tokenData = [
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt
        ];

        // Save refresh token if provided
        if (!empty($data['refresh_token'])) {
            $tokenData['refresh_token'] = $data['refresh_token'];
        }

        $this->saveCachedToken($tokenData);

        return $data['access_token'];
    }

    /**
     * Clear cached token (useful for logout or credential changes)
     * 
     * @return bool
     */
    public function clearCache() {
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }
        return true;
    }

    /**
     * Get token status for debugging
     * 
     * @return array
     */
    public function getStatus() {
        $tokenData = $this->loadCachedToken();
        
        return [
            'configured' => $this->isConfigured(),
            'cached' => $tokenData !== null,
            'valid' => $this->isTokenValid($tokenData),
            'expires_at' => $tokenData['expires_at'] ?? null,
            'token_preview' => $tokenData ? substr($tokenData['access_token'], 0, 10) . '...' : null
        ];
    }
}
