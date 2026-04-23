<?php
/**
 * Security class for handling CSRF protection, input validation, and other security measures
 */
class Security {
    /**
     * Generate CSRF token and store in session
     */
    public static function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        } elseif (self::isTokenExpired()) {
            // Regenerate expired token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken(?string $token): bool {
        if (!$token) {
            return false;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $tokenTime = $_SESSION['csrf_token_time'] ?? 0;

        // Check if token exists and is not expired (1 hour expiry)
        if (!$sessionToken || (time() - $tokenTime) > 3600) {
            return false;
        }

        // Use secure comparison to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate CSRF token (call this after successful form submission)
     */
    public static function regenerateCsrfToken(): void {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }

    /**
     * Get hidden input field with CSRF token
     */
    public static function getCsrfField(): string {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get the current CSRF token value.
     */
    public static function getCsrfToken(): string {
        return self::generateCsrfToken();
    }

    /**
     * Extract CSRF token from standard web form, AJAX header, or JSON body.
     */
    public static function getRequestCsrfToken(): string {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_CSRFTOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            return trim($_POST['csrf_token']);
        }

        if (isset($_POST['_token']) && is_string($_POST['_token'])) {
            return trim($_POST['_token']);
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $payload = $rawBody ? json_decode($rawBody, true) : null;

            if (is_array($payload)) {
                foreach (['csrf_token', '_token'] as $field) {
                    if (isset($payload[$field]) && is_string($payload[$field])) {
                        return trim($payload[$field]);
                    }
                }
            }
        }

        return '';
    }

    /**
     * Destroy the active session and expire the session cookie.
     */
    public static function destroySession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $cookieOptions = [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ];

            setcookie(session_name(), '', $cookieOptions);
        }

        session_destroy();
    }

    /**
     * Validate user input for XSS prevention
     */
    public static function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email format
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate secure random string
     */
    public static function generateRandomString(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Check if CSRF token is expired
     */
    private static function isTokenExpired(): bool {
        $tokenTime = $_SESSION['csrf_token_time'] ?? 0;
        return (time() - $tokenTime) > 3600; // 1 hour
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, string $description, ?int $userId = null): void {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at)
                VALUES (?, 'SECURITY_EVENT', 'security', NULL, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $event . ': ' . $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }

    /**
     * Check for suspicious activity (brute force protection)
     */
    public static function checkBruteForce(string $action, int $maxAttempts = 5, int $timeWindow = 900): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "brute_force:{$action}:{$ip}";

        // For now, implement simple session-based tracking
        // In production, this should use Redis or database
        $attempts = $_SESSION[$key]['count'] ?? 0;
        $lastAttempt = $_SESSION[$key]['time'] ?? 0;

        // Reset counter if time window has passed
        if (time() - $lastAttempt > $timeWindow) {
            $attempts = 0;
        }

        $attempts++;
        $_SESSION[$key] = ['count' => $attempts, 'time' => time()];

        if ($attempts > $maxAttempts) {
            self::logSecurityEvent('BRUTE_FORCE_ATTEMPT', "Too many {$action} attempts from IP: {$ip}");
            return true; // Block the attempt
        }

        return false; // Allow the attempt
    }

    /**
     * Rate limiting for API endpoints
     */
    public static function checkRateLimit(string $key, int $maxRequests = 100, int $timeWindow = 60): bool {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cacheKey = "rate_limit:{$key}:{$identifier}";

        // Simple implementation using session
        // In production, should use Redis
        $requests = $_SESSION[$cacheKey]['count'] ?? 0;
        $lastRequest = $_SESSION[$cacheKey]['time'] ?? 0;

        // Reset counter if time window has passed
        if (time() - $lastRequest > $timeWindow) {
            $requests = 0;
        }

        $requests++;

        if ($requests > $maxRequests) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', "{$key} rate limit exceeded for {$identifier}");
            return true; // Rate limit exceeded
        }

        $_SESSION[$cacheKey] = ['count' => $requests, 'time' => time()];

        return false; // Within rate limit
    }
}
