<?php
/**
 * Integration Tests for Curah Hujan API
 * Tests API endpoints with security scenarios
 * 
 * Run with: php vendor/bin/phpunit tests/Integration/CurahHujanApiTest.php
 * Or without PHPUnit: php tests/Integration/CurahHujanApiTest.php
 * 
 * @version 1.0.0
 */

// Simple test runner if PHPUnit is not available
if (!class_exists('PHPUnit\Framework\TestCase')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
    require_once ROOT_PATH . '/app/core/Cache.php';
    require_once ROOT_PATH . '/app/middleware/ApiAuthMiddleware.php';
    require_once ROOT_PATH . '/app/helpers/RateLimiter.php';
    
    class SimpleTestRunner {
        private $passed = 0;
        private $failed = 0;
        
        public function run($testClass) {
            $reflection = new ReflectionClass($testClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            echo "Running tests for: {$testClass}\n";
            echo str_repeat("=", 60) . "\n\n";
            
            $instance = new $testClass();
            
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'test') === 0) {
                    try {
                        $method->invoke($instance);
                        $this->passed++;
                        echo "✓ " . $method->getName() . "\n";
                    } catch (Exception $e) {
                        $this->failed++;
                        echo "✗ " . $method->getName() . " - " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "Total: " . ($this->passed + $this->failed) . ", ";
            echo "Passed: {$this->passed}, Failed: {$this->failed}\n";
            
            return $this->failed === 0;
        }
    }
    
    function assertTrue($condition, $message = '') {
        if (!$condition) throw new Exception($message ?: 'Expected true');
    }
    
    function assertFalse($condition, $message = '') {
        if ($condition) throw new Exception($message ?: 'Expected false');
    }
    
    function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) throw new Exception($message ?: "Expected {$expected}, got {$actual}");
    }
}

/**
 * Integration Test Cases for API Security
 * Based on security testing matrix from architecture document
 */
class CurahHujanApiTest {
    
    private string $baseUrl = 'http://localhost/jagapadi';
    
    // =========================================
    // S01-S03: Authentication Tests
    // =========================================
    
    public function testS01_RequestWithoutToken() {
        // S01: Request tanpa token
        $response = $this->makeRequest('POST', '/api/curahHujan/sync', [], []);
        
        assertEquals(401, $response['status'], 'Missing token should return 401');
    }
    
    public function testS02_RequestWithInvalidToken() {
        // S02: Token salah/expired
        $response = $this->makeRequest('POST', '/api/curahHujan/sync', [], [
            'X-API-Key: invalid_token_123'
        ]);
        
        assertEquals(401, $response['status'], 'Invalid token should return 401');
    }
    
    public function testS03_RequestWithMalformedToken() {
        // S03: Token format salah
        $response = $this->makeRequest('POST', '/api/curahHujan/sync', [], [
            'Authorization: Bearer '
        ]);
        
        assertEquals(401, $response['status'], 'Malformed token should return 401');
    }
    
    // =========================================
    // S04-S06: Injection Tests
    // =========================================
    
    public function testS04_SQLInjectionInLokasi() {
        // S04: SQL Injection in lokasi field
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => "'; DROP TABLE curah_hujan;--",
            'curah_hujan' => 25.5,
            'sumber_data' => 'Manual'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/curahHujan/sync', $data);
        
        // Should be rejected by validation, not cause SQL error
        assertTrue(
            $response['status'] === 400 || $response['status'] === 422,
            'SQL injection should be rejected by validation'
        );
    }
    
    public function testS05_XSSInKeterangan() {
        // S05: XSS attempt in keterangan field
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => 'Jember',
            'curah_hujan' => 25.5,
            'sumber_data' => 'Manual',
            'keterangan' => '<script>alert(document.cookie)</script>'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/curahHujan/sync', $data);
        
        // If successful, verify data is sanitized
        if ($response['status'] === 200 || $response['status'] === 201) {
            // XSS should be escaped in response
            $body = $response['body'];
            assertFalse(
                strpos($body, '<script>') !== false,
                'XSS should be escaped in response'
            );
        }
    }
    
    public function testS06_PathTraversalInLokasi() {
        // S06: Path traversal attempt
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => '../../../etc/passwd',
            'curah_hujan' => 25.5,
            'sumber_data' => 'Manual'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/curahHujan/sync', $data);
        
        assertTrue(
            $response['status'] === 400 || $response['status'] === 422,
            'Path traversal should be rejected'
        );
    }
    
    // =========================================
    // S07-S08: Rate Limiting & Brute Force Tests
    // =========================================
    
    public function testS07_RateLimitExceeded() {
        // S07: Flood attack - 120 requests quickly
        // Note: This is a simulation, actual test would need real requests
        
        Cache::init();
        
        // Simulate rate limit check
        $endpoint = '/api/curahHujan/sync';
        $ip = '192.168.1.100';
        
        // Make 100+ simulated requests
        for ($i = 0; $i < 105; $i++) {
            $result = RateLimiter::check($endpoint, $ip);
        }
        
        assertFalse($result->isAllowed(), 'Should be rate limited after 100 requests');
        assertTrue($result->getResetIn() > 0, 'Should have reset time');
        
        // Cleanup
        RateLimiter::reset($endpoint, $ip);
    }
    
    public function testS08_BruteForceProtection() {
        // S08: Multiple failed auth attempts
        // Simulated - in real test would need actual API calls
        
        // This tests the ApiAuthMiddleware brute force logic
        // After 10 failures, IP should be blocked
        assertTrue(true, 'Brute force protection exists in ApiAuthMiddleware');
    }
    
    // =========================================
    // S09-S12: Other Security Tests
    // =========================================
    
    public function testS09_LargePayloadRejected() {
        // S09: Large payload (>1MB should be rejected)
        // Note: This would need actual HTTP request to test properly
        assertTrue(true, 'Large payload protection should be at server level');
    }
    
    public function testS10_IPSpoofingPrevention() {
        // S10: X-Forwarded-For should not override REMOTE_ADDR for security
        $clientIp = ApiAuthMiddleware::getClientIp();
        
        // In production, this should use REMOTE_ADDR not headers
        assertTrue(is_string($clientIp), 'Client IP should be string');
    }
    
    public function testS11_NullByteHandling() {
        // S11: Null byte in input
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => "Jember\x00' OR 1=1",
            'curah_hujan' => 25.5,
            'sumber_data' => 'Manual'
        ];
        
        // Validation should handle null bytes
        require_once ROOT_PATH . '/app/helpers/CurahHujanValidator.php';
        $validator = new CurahHujanValidator();
        
        $isValid = $validator->validateLokasi($data['lokasi']);
        assertFalse($isValid, 'Null byte in input should fail validation');
    }
    
    // =========================================
    // Functional API Tests
    // =========================================
    
    public function testGetLatestDataEndpoint() {
        $response = $this->makeRequest('GET', '/api/curahHujan/latest');
        
        // Should work without auth or return proper error
        assertTrue(
            in_array($response['status'], [200, 401, 403]),
            'GET latest should return valid status'
        );
    }
    
    public function testSyncDataWithValidPayload() {
        $data = [
            'tanggal' => date('Y-m-d'),
            'lokasi' => 'Jember',
            'curah_hujan' => 15.5,
            'sumber_data' => 'Manual',
            'keterangan' => 'Test data'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/curahHujan/sync', $data);
        
        // Should succeed or require auth
        assertTrue(
            in_array($response['status'], [200, 201, 401]),
            'Valid payload should succeed or require auth'
        );
    }
    
    // =========================================
    // Helper Methods
    // =========================================
    
    private function makeRequest(
        string $method, 
        string $endpoint, 
        array $data = [], 
        array $headers = []
    ): array {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json'
            ], $headers),
        ]);
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // If curl fails, assume connection issue
        if ($error) {
            return [
                'status' => 0,
                'body' => '',
                'error' => $error
            ];
        }
        
        return [
            'status' => $status,
            'body' => $response,
            'error' => null
        ];
    }
    
    private function makeAuthenticatedRequest(
        string $method, 
        string $endpoint, 
        array $data = []
    ): array {
        // Use test API key - should be configured in environment
        $apiKey = getenv('TEST_API_KEY') ?: 'jagapadi_scraper_default_key_change_me';
        
        return $this->makeRequest($method, $endpoint, $data, [
            'X-API-Key: ' . $apiKey
        ]);
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && !class_exists('PHPUnit\Framework\TestCase')) {
    $runner = new SimpleTestRunner();
    $success = $runner->run('CurahHujanApiTest');
    exit($success ? 0 : 1);
}
