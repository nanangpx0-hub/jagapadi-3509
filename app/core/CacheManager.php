<?php

final class CacheManager {
    private static ?self $instance = null;

    private readonly string $driver;
    private readonly string $prefix;
    private readonly int $defaultTtl;
    private ?object $client = null;
    private bool $available = false;
    private int $namespaceVersion = 1;

    public function __construct(?string $driver = null) {
        $this->driver = strtolower($driver ?? $this->envString('CACHE_DRIVER', 'redis'));
        $this->prefix = $this->normalizeKey($this->envString('CACHE_PREFIX', 'jagapadi'));
        $this->defaultTtl = max(1, $this->envInt('CACHE_DEFAULT_TTL', 60));

        if (!$this->envBool('CACHE_ENABLED', true)) {
            return;
        }

        try {
            match ($this->driver) {
                'redis' => $this->connectRedis(),
                'memcached' => $this->connectMemcached(),
                default => throw new RuntimeException("Unsupported cache driver: {$this->driver}"),
            };
        } catch (Throwable $e) {
            $this->markUnavailable($e);
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function isAvailable(): bool {
        return $this->available;
    }

    public function get(string $key): mixed {
        if (!$this->available || $this->client === null) {
            return null;
        }

        try {
            $cacheKey = $this->key($key);

            if ($this->driver === 'redis') {
                $payload = $this->client->get($cacheKey);
                return $payload === false ? null : unserialize($payload);
            }

            $value = $this->client->get($cacheKey);
            if ($this->client->getResultCode() !== Memcached::RES_SUCCESS) {
                return null;
            }

            return $value;
        } catch (Throwable $e) {
            $this->markUnavailable($e);
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        if (!$this->available || $this->client === null) {
            return false;
        }

        $ttl = max(1, $ttl ?? $this->defaultTtl);

        try {
            $cacheKey = $this->key($key);

            if ($this->driver === 'redis') {
                return (bool)$this->client->setex($cacheKey, $ttl, serialize($value));
            }

            return (bool)$this->client->set($cacheKey, $value, $ttl);
        } catch (Throwable $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function has(string $key): bool {
        if (!$this->available || $this->client === null) {
            return false;
        }

        try {
            $cacheKey = $this->key($key);

            if ($this->driver === 'redis') {
                return (int)$this->client->exists($cacheKey) > 0;
            }

            $this->client->get($cacheKey);
            return $this->client->getResultCode() === Memcached::RES_SUCCESS;
        } catch (Throwable $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function delete(string $key): bool {
        if (!$this->available || $this->client === null) {
            return false;
        }

        try {
            $cacheKey = $this->key($key);

            if ($this->driver === 'redis') {
                return (int)$this->client->del($cacheKey) >= 0;
            }

            $this->client->delete($cacheKey);
            return in_array(
                $this->client->getResultCode(),
                [Memcached::RES_SUCCESS, Memcached::RES_NOTFOUND],
                true
            );
        } catch (Throwable $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function clear(): bool {
        if (!$this->available || $this->client === null) {
            return false;
        }

        try {
            if ($this->driver === 'memcached') {
                $this->namespaceVersion++;
                return (bool)$this->client->set($this->versionKey(), $this->namespaceVersion, 0);
            }

            foreach ($this->redisKeysByPrefix() as $key) {
                $this->client->del($key);
            }

            return true;
        } catch (Throwable $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    /**
     * Fail-open cache wrapper. If cache backend is unavailable, callback still reads from DB.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function connectRedis(): void {
        if (!class_exists('Redis')) {
            throw new RuntimeException('PHP Redis extension is not installed');
        }

        $redis = new Redis();
        $redis->connect(
            $this->envString('REDIS_HOST', '127.0.0.1'),
            $this->envInt('REDIS_PORT', 6379),
            (float)$this->envString('REDIS_TIMEOUT', '1.0')
        );

        $password = $this->envString('REDIS_PASSWORD', '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $database = $this->envInt('REDIS_DATABASE', 0);
        if ($database > 0) {
            $redis->select($database);
        }

        $redis->ping();
        $this->client = $redis;
        $this->available = true;
    }

    private function connectMemcached(): void {
        if (!class_exists('Memcached')) {
            throw new RuntimeException('PHP Memcached extension is not installed');
        }

        $memcached = new Memcached();
        $memcached->addServer(
            $this->envString('MEMCACHED_HOST', '127.0.0.1'),
            $this->envInt('MEMCACHED_PORT', 11211)
        );

        $stats = $memcached->getStats();
        if (empty($stats)) {
            throw new RuntimeException('Memcached server is unavailable');
        }

        $this->client = $memcached;
        $this->available = true;
        $this->namespaceVersion = (int)($memcached->get($this->versionKey()) ?: 1);
        $memcached->set($this->versionKey(), $this->namespaceVersion, 0);
    }

    private function redisKeysByPrefix(): array {
        $pattern = "{$this->prefix}:*";
        $keys = [];
        $iterator = null;

        do {
            $batch = $this->client->scan($iterator, $pattern, 1000);
            if (is_array($batch)) {
                $keys = array_merge($keys, $batch);
            }
        } while ($iterator !== 0 && $iterator !== null);

        if (empty($keys) && method_exists($this->client, 'keys')) {
            $keys = $this->client->keys($pattern) ?: [];
        }

        return $keys;
    }

    private function key(string $key): string {
        $key = $this->normalizeKey($key);

        if ($this->driver === 'memcached') {
            return "{$this->prefix}:v{$this->namespaceVersion}:{$key}";
        }

        return "{$this->prefix}:{$key}";
    }

    private function versionKey(): string {
        return "{$this->prefix}:namespace_version";
    }

    private function normalizeKey(string $key): string {
        $normalized = preg_replace('/[^A-Za-z0-9:_\-.]/', '_', $key) ?: sha1($key);

        if (strlen($normalized) > 160) {
            return substr($normalized, 0, 80) . ':' . sha1($key);
        }

        return $normalized;
    }

    private function markUnavailable(Throwable $e): void {
        $this->available = false;
        $this->client = null;
        error_log('[CacheManager] Cache unavailable, falling back to database: ' . $e->getMessage());
    }

    private function envString(string $key, string $default): string {
        $value = function_exists('env') ? env($key, $default) : getenv($key);
        return $value === false || $value === null ? $default : (string)$value;
    }

    private function envInt(string $key, int $default): int {
        return (int)$this->envString($key, (string)$default);
    }

    private function envBool(string $key, bool $default): bool {
        $value = $this->envString($key, $default ? 'true' : 'false');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
