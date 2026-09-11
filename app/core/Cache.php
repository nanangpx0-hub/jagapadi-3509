<?php
/**
 * File-based Cache Class
 * Provides simple file-based caching for master data and frequently accessed data
 */
class Cache {
    private static $cacheDir;
    private static $defaultTTL = 3600; // 1 hour default
    
    /**
     * Initialize cache directory
     */
    public static function init() {
        self::$cacheDir = ROOT_PATH . '/storage/cache/';
        
        // Create cache directory if it doesn't exist
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache file path
     */
    private static function getCachePath($key) {
        self::init();
        $hash = md5($key);
        return self::$cacheDir . $hash . '.cache';
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public static function get($key) {
        $file = self::getCachePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = self::decodePayload($raw, $file);

        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return null;
        }

        // Check if expired
        if (time() > (int) $data['expires']) {
            self::delete($key);
            return null;
        }

        return $data['value'];
    }
    
    /**
     * Set cache data
     * 
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $ttl Time to live in seconds (default: 1 hour)
     * @return bool Success status
     */
    public static function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = self::$defaultTTL;
        }
        
        $file = self::getCachePath($key);
        $data = [
            'v' => 2,
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }
        return file_put_contents($file, $encoded, LOCK_EX) !== false;
    }
    
    /**
     * Delete cache entry
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public static function delete($key) {
        $file = self::getCachePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     * 
     * @return int Number of files deleted
     */
    public static function clear() {
        self::init();
        $count = 0;
        
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Clear expired cache entries
     * 
     * @return int Number of files deleted
     */
     public static function clearExpired() {
        self::init();
        $count = 0;

        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $data = self::decodePayload($raw, $file);

            if (!is_array($data) || !isset($data['expires'])) {
                if (unlink($file)) {
                    $count++;
                }
                continue;
            }

            if (time() > (int) $data['expires']) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }
    
    /**
     * Check if cache exists and is valid
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function has($key) {
        $file = self::getCachePath($key);

        if (!file_exists($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $data = self::decodePayload($raw, $file);

        if (!is_array($data) || !isset($data['expires'])) {
            self::delete($key);
            return false;
        }

        if (time() > (int) $data['expires']) {
            self::delete($key);
            return false;
        }

        return true;
    }
    
    /**
     * Get cache info
     * 
     * @param string $key Cache key
     * @return array|null Cache info or null if not found
     */
    public static function info($key) {
        $file = self::getCachePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = self::decodePayload($raw, $file);
        if (!is_array($data) || !isset($data['expires'])) {
            return null;
        }
        
        return [
            'created' => $data['created'],
            'expires' => $data['expires'],
            'ttl' => $data['expires'] - $data['created'],
            'is_expired' => time() > $data['expires'],
            'size' => filesize($file)
        ];
    }
    
    /**
     * Decode payload with JSON-first, legacy serialize fallback (allowed_classes=>false).
     * Invalid legacy payloads are deleted to prevent object injection.
     */
    private static function decodePayload(string $raw, string $file): ?array {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        // Legacy fallback: safely attempt unserialize without object instantiation
        $legacy = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($legacy) && isset($legacy['value'], $legacy['expires'])) {
            // Migrate to JSON on next read path is lazy; invalid payloads deleted elsewhere
            return $legacy;
        }
        // Corrupted or injected object payload — delete file
        @unlink($file);
        if (is_string($raw) && str_contains($raw, 'O:')) {
            error_log('[Cache] rejected serialized object payload: ' . substr($raw, 0, 200));
        }
        return null;
    }

    /**
     * Remember pattern: Get from cache or execute callback and cache result
     * 
     * @param string $key Cache key
     * @param callable $callback Function to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed
     */
    public static function remember($key, callable $callback, $ttl = null) {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }
}

