<?php
/**
 * API Authentication Middleware
 * Handles API Key authentication for external services (e.g., BMKG Scraper)
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class ApiAuthMiddleware {
    private const MIN_API_KEY_LENGTH = 32;
    private const INSECURE_DEFAULT_KEYS = [
        'jagapadi_scraper_default_key_change_me',
        'your_api_key_here',
        'your_mobile_api_key_here',
        'your_external_api_key_here',
    ];
    
    /**
     * Valid API sources (for logging purposes)
     */
    private const VALID_SOURCES = [
        'scraper' => 'BMKG Scraper',
        'mobile' => 'Mobile App',
        'external' => 'External Service'
    ];
    
    /**
     * Validate API request authentication
     * 
     * @param string $requiredSource Which API source is required (scraper, mobile, external)
     * @return array ['valid' => bool, 'source' => string, 'error' => string|null]
     */
    public static function authenticate(string $requiredSource = 'scraper'): array {
        $result = [
            'valid' => false,
            'source' => null,
            'error' => null,
            'ip' => self::getClientIp()
        ];

        if (!isset(self::VALID_SOURCES[$requiredSource])) {
            $result['error'] = 'Invalid API source';
            self::logAuthAttempt('INVALID_SOURCE', $result['ip'], false);
            return $result;
        }
        
        // Check if IP is blocked
        if (self::isIpBlocked($result['ip'])) {
            $result['error'] = 'IP address is blocked';
            self::logAuthAttempt('BLOCKED_IP', $result['ip'], false);
            return $result;
        }
        
        // Get API key from headers
        $apiKey = self::extractApiKey();
        
        if (empty($apiKey)) {
            $result['error'] = 'Missing API key';
            self::recordAuthFailure($result['ip']);
            self::logAuthAttempt('MISSING_KEY', $result['ip'], false);
            return $result;
        }
        
        // Load API configuration
        $config = self::loadConfig();
        
        if ($config === null) {
            $result['error'] = 'API configuration error';
            error_log('[API_AUTH] Failed to load API configuration');
            return $result;
        }
        
        // Validate API key based on source
        $keyConfig = $config[$requiredSource . '_api'] ?? null;
        
        if ($keyConfig === null) {
            $result['error'] = 'Invalid API source configuration';
            return $result;
        }
        
        if (!self::matchesConfiguredKey($apiKey, $keyConfig)) {
            $result['error'] = 'Invalid API key';
            self::recordAuthFailure($result['ip']);
            self::logAuthAttempt('INVALID_KEY', $result['ip'], false);
            return $result;
        }
        
        // Check IP whitelist (if configured)
        $allowedIps = $keyConfig['allowed_ips'] ?? [];
        if (!empty($allowedIps) && !in_array($result['ip'], $allowedIps)) {
            $result['error'] = 'IP address not allowed';
            self::logAuthAttempt('IP_NOT_ALLOWED', $result['ip'], false);
            return $result;
        }
        
        // Authentication successful
        $result['valid'] = true;
        $result['source'] = self::VALID_SOURCES[$requiredSource] ?? $requiredSource;
        self::clearAuthFailures($result['ip']);
        self::logAuthAttempt('SUCCESS', $result['ip'], true, $result['source']);
        
        return $result;
    }
    
    /**
     * Extract API key from request headers.
     * External APIs only accept X-API-Key or Authorization: Bearer.
     * 
     * @return string|null
     */
    private static function extractApiKey(): ?string {
        // Check X-API-Key header first
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($apiKey) {
            return trim($apiKey);
        }

        // Check Authorization header (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private static function matchesConfiguredKey(string $apiKey, array $keyConfig): bool {
        $presentedKey = trim($apiKey);

        if (!self::isAcceptableApiKey($presentedKey)) {
            return false;
        }

        foreach (['api_key', 'api_key_backup'] as $field) {
            $configuredKey = $keyConfig[$field] ?? null;
            if (self::isAcceptableApiKey($configuredKey) && hash_equals(trim($configuredKey), $presentedKey)) {
                return true;
            }
        }

        foreach (['api_key_hash', 'api_key_backup_hash'] as $field) {
            $configuredHash = $keyConfig[$field] ?? null;
            if (self::isAcceptableHash($configuredHash) && hash_equals(strtolower(trim($configuredHash)), hash('sha256', $presentedKey))) {
                return true;
            }
        }

        return false;
    }

    private static function isAcceptableApiKey($apiKey): bool {
        if (!is_string($apiKey)) {
            return false;
        }

        $apiKey = trim($apiKey);
        return strlen($apiKey) >= self::MIN_API_KEY_LENGTH
            && !in_array($apiKey, self::INSECURE_DEFAULT_KEYS, true);
    }

    private static function isAcceptableHash($hash): bool {
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', trim($hash)) === 1;
    }
    
    /**
     * Load API configuration
     * 
     * @return array|null
     */
    private static function loadConfig(): ?array {
        $configFile = ROOT_PATH . '/config/api_config.php';
        
        if (!file_exists($configFile)) {
            // Try to create default config
            self::createDefaultConfig($configFile);
        }
        
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return null;
    }
    
    /**
     * Create default API configuration file
     * 
     * @param string $configFile
     */
    private static function createDefaultConfig(string $configFile): void {
        $defaultConfig = <<<'PHP'
<?php
/**
 * API Configuration
 * 
 * IMPORTANT: Store sensitive keys in environment variables, not in this file!
 * 
 * @version 1.0.0
 */

return [
    'scraper_api' => [
        // Primary API Key (rotate every 90 days)
        // Generate with: bin2hex(random_bytes(32))
        'api_key' => getenv('SCRAPER_API_KEY') ?: null,
        'api_key_hash' => getenv('SCRAPER_API_KEY_HASH') ?: null,
        
        // Backup API Key (for smooth rotation)
        'api_key_backup' => getenv('SCRAPER_API_KEY_BACKUP') ?: null,
        'api_key_backup_hash' => getenv('SCRAPER_API_KEY_BACKUP_HASH') ?: null,
        
        // Token TTL in seconds (24 hours)
        'token_ttl' => 86400,
        
        // Allowed IPs for scraper (empty = allow all)
        'allowed_ips' => array_filter(
            explode(',', getenv('SCRAPER_ALLOWED_IPS') ?: ''),
            fn($ip) => !empty(trim($ip))
        ),
    ],
    
    'mobile_api' => [
        'api_key' => getenv('MOBILE_API_KEY') ?: null,
        'api_key_hash' => getenv('MOBILE_API_KEY_HASH') ?: null,
        'api_key_backup' => null,
        'api_key_backup_hash' => null,
        'token_ttl' => 3600,
        'allowed_ips' => [],
    ],
    
    'external_api' => [
        'api_key' => getenv('EXTERNAL_API_KEY') ?: null,
        'api_key_hash' => getenv('EXTERNAL_API_KEY_HASH') ?: null,
        'api_key_backup' => null,
        'api_key_backup_hash' => null,
        'token_ttl' => 3600,
        'allowed_ips' => [],
    ],
    
    'rate_limits' => [
        'scraper' => ['requests' => 100, 'window' => 3600],  // 100/hour
        'mobile' => ['requests' => 1000, 'window' => 3600],   // 1000/hour
        'external' => ['requests' => 500, 'window' => 3600],  // 500/hour
    ],
    
    'brute_force' => [
        'max_failures' => 10,
        'block_duration' => 3600, // 1 hour
    ],
];
PHP;
        
        try {
            file_put_contents($configFile, $defaultConfig);
            error_log('[API_AUTH] Created default API configuration file');
        } catch (Exception $e) {
            error_log('[API_AUTH] Failed to create default config: ' . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    public static function getClientIp(): string {
        // Note: Do NOT trust X-Forwarded-For in production without proper proxy configuration
        // Only use REMOTE_ADDR for security-critical operations
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Record authentication failure for brute force protection
     * 
     * @param string $ip
     */
    private static function recordAuthFailure(string $ip): void {
        $cacheKey = "auth_failures:{$ip}";
        $config = self::loadConfig();
        $maxFailures = $config['brute_force']['max_failures'] ?? 10;
        $blockDuration = $config['brute_force']['block_duration'] ?? 3600;
        
        $failures = self::cache()->get($cacheKey) ?? 0;
        $failures++;
        
        self::cache()->set($cacheKey, $failures, $blockDuration);
        
        if ($failures >= $maxFailures) {
            self::blockIp($ip, $blockDuration);
        }
    }
    
    /**
     * Clear authentication failures after successful login
     * 
     * @param string $ip
     */
    private static function clearAuthFailures(string $ip): void {
        $cacheKey = "auth_failures:{$ip}";
        self::cache()->delete($cacheKey);
    }
    
    /**
     * Block an IP address
     * 
     * @param string $ip
     * @param int $duration Block duration in seconds
     */
    private static function blockIp(string $ip, int $duration = 3600): void {
        $cacheKey = "blocked_ip:{$ip}";
        self::cache()->set($cacheKey, true, $duration);
        
        // Log security event
        if (class_exists('Security')) {
            Security::logSecurityEvent(
                'IP_BLOCKED',
                "IP {$ip} blocked due to too many auth failures"
            );
        }
        
        error_log("[API_AUTH] IP blocked: {$ip}");
    }
    
    /**
     * Check if IP address is blocked
     * 
     * @param string $ip
     * @return bool
     */
    private static function isIpBlocked(string $ip): bool {
        $cacheKey = "blocked_ip:{$ip}";
        return self::cache()->get($cacheKey) === true;
    }

    private static function cache(): CacheManager {
        return CacheManager::getInstance();
    }
    
    /**
     * Log authentication attempt (without sensitive data)
     * 
     * @param string $result
     * @param string $ip
     * @param bool $success
     * @param string|null $source
     */
    private static function logAuthAttempt(
        string $result, 
        string $ip, 
        bool $success,
        ?string $source = null
    ): void {
        $logMessage = sprintf(
            "[API_AUTH] Result=%s, IP=%s, Success=%s, Source=%s, Endpoint=%s, Time=%s",
            $result,
            $ip,
            $success ? 'true' : 'false',
            $source ?? 'N/A',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            date('Y-m-d H:i:s')
        );
        
        // Log to file (without token values)
        error_log($logMessage);
        
        // Also log to database if Security class is available
        if (!$success && class_exists('Security')) {
            Security::logSecurityEvent('API_AUTH_FAILURE', $result);
        }
    }
    
    /**
     * Middleware wrapper for Controller methods
     * 
     * @param string $requiredSource
     * @return bool Returns true if authenticated, sends JSON error response if not
     */
    public static function requireAuth(string $requiredSource = 'scraper'): bool {
        $auth = self::authenticate($requiredSource);
        
        if (!$auth['valid']) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => $auth['error']
            ]);
            exit;
        }
        
        return true;
    }
    
    /**
     * Generate a new secure API key
     * 
     * @return string
     */
    public static function generateApiKey(): string {
        return bin2hex(random_bytes(32));
    }
}
