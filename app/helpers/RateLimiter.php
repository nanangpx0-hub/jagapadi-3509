<?php
/**
 * Rate Limiter Helper
 * Provides advanced rate limiting for API endpoints using file-based cache
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class RateLimiter {
    
    /**
     * Default rate limits per endpoint pattern
     */
    private const DEFAULT_LIMITS = [
        '/api/curahHujan/sync' => ['requests' => 100, 'window' => 3600],    // 100/hour
        '/api/curahHujan/latest' => ['requests' => 500, 'window' => 3600],  // 500/hour
        '/api/' => ['requests' => 300, 'window' => 3600],                    // 300/hour (default)
    ];
    
    /**
     * Check rate limit for current request
     * 
     * @param string|null $endpoint Endpoint to check (defaults to current URI)
     * @param string|null $identifier Client identifier (defaults to IP)
     * @return RateLimitResult
     */
    public static function check(?string $endpoint = null, ?string $identifier = null): RateLimitResult {
        $endpoint = $endpoint ?? self::getCurrentEndpoint();
        $identifier = $identifier ?? self::getClientIdentifier();
        
        $config = self::getLimitConfig($endpoint);
        $cacheKey = self::getCacheKey($endpoint, $identifier);
        
        Cache::init();
        
        // Get current request count
        $data = Cache::get($cacheKey);
        
        if ($data === null) {
            $data = [
                'count' => 0,
                'window_start' => time()
            ];
        }
        
        // Check if window has expired
        $windowExpired = (time() - $data['window_start']) >= $config['window'];
        
        if ($windowExpired) {
            // Reset window
            $data = [
                'count' => 0,
                'window_start' => time()
            ];
        }
        
        // Increment request count
        $data['count']++;
        
        // Calculate remaining requests and reset time
        $remaining = max(0, $config['requests'] - $data['count']);
        $resetTime = $data['window_start'] + $config['window'];
        $resetIn = max(0, $resetTime - time());
        
        // Save updated count
        Cache::set($cacheKey, $data, $config['window']);
        
        // Check if limit exceeded
        $exceeded = $data['count'] > $config['requests'];
        
        if ($exceeded) {
            self::logRateLimitExceeded($endpoint, $identifier, $data['count'], $config['requests']);
        }
        
        return new RateLimitResult(
            !$exceeded,
            $remaining,
            $resetIn,
            $config['requests'],
            $exceeded ? "Rate limit exceeded. Try again in {$resetIn} seconds." : null
        );
    }
    
    /**
     * Apply rate limit and send appropriate response if exceeded
     * 
     * @param string|null $endpoint
     * @param string|null $identifier
     * @return bool True if within limit, exits with 429 if exceeded
     */
    public static function apply(?string $endpoint = null, ?string $identifier = null): bool {
        $result = self::check($endpoint, $identifier);
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $result->getLimit());
        header('X-RateLimit-Remaining: ' . $result->getRemaining());
        header('X-RateLimit-Reset: ' . (time() + $result->getResetIn()));
        
        if (!$result->isAllowed()) {
            http_response_code(429);
            header('Retry-After: ' . $result->getResetIn());
            header('Content-Type: application/json');
            
            echo json_encode([
                'success' => false,
                'error' => 'Too Many Requests',
                'message' => $result->getMessage(),
                'retry_after' => $result->getResetIn()
            ]);
            exit;
        }
        
        return true;
    }
    
    /**
     * Get current endpoint path
     * 
     * @return string
     */
    private static function getCurrentEndpoint(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Normalize path
        return rtrim($uri, '/');
    }
    
    /**
     * Get client identifier (IP address)
     * 
     * @return string
     */
    private static function getClientIdentifier(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get rate limit configuration for endpoint
     * 
     * @param string $endpoint
     * @return array ['requests' => int, 'window' => int]
     */
    private static function getLimitConfig(string $endpoint): array {
        // Try to load from api_config.php first
        $configFile = ROOT_PATH . '/config/api_config.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            
            if (isset($config['rate_limits'])) {
                foreach ($config['rate_limits'] as $pattern => $limits) {
                    if (is_array($limits) && isset($limits['requests'])) {
                        // Direct endpoint match
                        if ($pattern === $endpoint) {
                            return $limits;
                        }
                    }
                }
            }
        }
        
        // Fall back to default limits
        foreach (self::DEFAULT_LIMITS as $pattern => $limits) {
            if ($pattern === $endpoint) {
                return $limits;
            }
            
            // Check if endpoint starts with pattern
            if (str_starts_with($endpoint, $pattern)) {
                return $limits;
            }
        }
        
        // Ultimate fallback
        return ['requests' => 100, 'window' => 3600];
    }
    
    /**
     * Generate cache key for rate limiting
     * 
     * @param string $endpoint
     * @param string $identifier
     * @return string
     */
    private static function getCacheKey(string $endpoint, string $identifier): string {
        // Normalize endpoint to avoid cache key issues
        $normalizedEndpoint = preg_replace('/[^a-zA-Z0-9_]/', '_', $endpoint);
        return "rate_limit:{$normalizedEndpoint}:{$identifier}";
    }
    
    /**
     * Log rate limit exceeded event
     * 
     * @param string $endpoint
     * @param string $identifier
     * @param int $count
     * @param int $limit
     */
    private static function logRateLimitExceeded(
        string $endpoint, 
        string $identifier, 
        int $count, 
        int $limit
    ): void {
        $logMessage = sprintf(
            "[RATE_LIMIT] Exceeded: Endpoint=%s, IP=%s, Count=%d, Limit=%d, Time=%s",
            $endpoint,
            $identifier,
            $count,
            $limit,
            date('Y-m-d H:i:s')
        );
        
        error_log($logMessage);
        
        // Log to database if Security class available
        if (class_exists('Security')) {
            Security::logSecurityEvent(
                'RATE_LIMIT_EXCEEDED',
                "Endpoint: {$endpoint}, IP: {$identifier}"
            );
        }
    }
    
    /**
     * Reset rate limit for a specific identifier
     * 
     * @param string $endpoint
     * @param string $identifier
     * @return bool
     */
    public static function reset(string $endpoint, string $identifier): bool {
        $cacheKey = self::getCacheKey($endpoint, $identifier);
        return Cache::delete($cacheKey);
    }
    
    /**
     * Get current usage stats for an identifier
     * 
     * @param string $endpoint
     * @param string $identifier
     * @return array|null
     */
    public static function getUsage(string $endpoint, string $identifier): ?array {
        $cacheKey = self::getCacheKey($endpoint, $identifier);
        Cache::init();
        
        $data = Cache::get($cacheKey);
        
        if ($data === null) {
            return null;
        }
        
        $config = self::getLimitConfig($endpoint);
        
        return [
            'current_count' => $data['count'],
            'limit' => $config['requests'],
            'remaining' => max(0, $config['requests'] - $data['count']),
            'window_start' => $data['window_start'],
            'window_end' => $data['window_start'] + $config['window'],
            'reset_in' => max(0, $data['window_start'] + $config['window'] - time())
        ];
    }
}

/**
 * Rate Limit Result Object
 */
class RateLimitResult {
    private bool $allowed;
    private int $remaining;
    private int $resetIn;
    private int $limit;
    private ?string $message;
    
    public function __construct(
        bool $allowed, 
        int $remaining, 
        int $resetIn, 
        int $limit, 
        ?string $message = null
    ) {
        $this->allowed = $allowed;
        $this->remaining = $remaining;
        $this->resetIn = $resetIn;
        $this->limit = $limit;
        $this->message = $message;
    }
    
    public function isAllowed(): bool {
        return $this->allowed;
    }
    
    public function getRemaining(): int {
        return $this->remaining;
    }
    
    public function getResetIn(): int {
        return $this->resetIn;
    }
    
    public function getLimit(): int {
        return $this->limit;
    }
    
    public function getMessage(): ?string {
        return $this->message;
    }
}
