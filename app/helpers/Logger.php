<?php
/**
 * Structured Logger Class
 * Provides consistent, structured logging for the application
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */
class Logger {
    private static $logFile;
    private static $initialized = false;

    /**
     * Override log destination for tests or isolated runtime contexts.
     */
    public static function setLogFile(string $path): void {
        self::$logFile = $path;
        $logDir = dirname(self::$logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        self::$initialized = true;
    }

    /**
     * Reset logger to the default application log file.
     */
    public static function reset(): void {
        self::$logFile = null;
        self::$initialized = false;
    }
    
    /**
     * Initialize logger
     */
    private static function init() {
        if (!self::$initialized) {
            self::$logFile = ROOT_PATH . '/storage/logs/app.log';
            $logDir = dirname(self::$logFile);
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            self::$initialized = true;
        }
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }
    
    /**
     * Log security event
     */
    public static function security($event, $description, $context = []) {
        $context['event'] = $event;
        $context['description'] = $description;
        self::log('SECURITY', "Security Event: {$event}", $context);
    }
    
    /**
     * Log API request
     */
    public static function apiRequest($method, $endpoint, $statusCode, $duration = null) {
        self::log('API', "{$method} {$endpoint}", [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => $duration
        ]);
    }
    
    /**
     * Generic log method
     */
    private static function log($level, $message, $context = []) {
        self::init();
        
        $logEntry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null
        ];
        
        // Write to file
        $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also use PHP's error_log for critical errors
        if ($level === 'ERROR' || $level === 'SECURITY') {
            error_log("[$level] $message: " . json_encode($context));
        }
    }
    
    /**
     * Get recent logs
     */
    public static function getRecent($count = 100, $level = null) {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($lines, -$count);
        
        $logs = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && (!$level || $entry['level'] === $level)) {
                $logs[] = $entry;
            }
        }
        
        return $logs;
    }
    
    /**
     * Clear log file
     */
    public static function clear() {
        self::init();
        
        if (file_exists(self::$logFile)) {
            return file_put_contents(self::$logFile, '') !== false;
        }
        
        return true;
    }
}
