<?php
/**
 * Unit Tests for CurahHujanValidator
 * Tests input validation for rainfall data specific to Kabupaten Jember
 * 
 * Run with: php vendor/bin/phpunit tests/Unit/CurahHujanValidatorTest.php
 * Or without PHPUnit: php tests/Unit/CurahHujanValidatorTest.php
 * 
 * @version 1.0.0
 */

// Simple test runner if PHPUnit is not available
if (!class_exists('PHPUnit\Framework\TestCase')) {
    // Standalone mode - define minimal test framework
    define('ROOT_PATH', dirname(dirname(__DIR__)));
    require_once ROOT_PATH . '/app/helpers/CurahHujanValidator.php';
    
    class SimpleTestRunner {
        private $passed = 0;
        private $failed = 0;
        private $errors = [];
        
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
                        $this->errors[] = $method->getName() . ': ' . $e->getMessage();
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
        if (!$condition) {
            throw new Exception($message ?: 'Expected true, got false');
        }
    }
    
    function assertFalse($condition, $message = '') {
        if ($condition) {
            throw new Exception($message ?: 'Expected false, got true');
        }
    }
    
    function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception($message ?: "Expected {$expected}, got {$actual}");
        }
    }
    
    function assertArrayHasKey($key, $array, $message = '') {
        if (!isset($array[$key])) {
            throw new Exception($message ?: "Key {$key} not found in array");
        }
    }
}

/**
 * Test Cases for CurahHujanValidator
 * Based on testing matrix from architecture document
 */
class CurahHujanValidatorTest {
    
    private CurahHujanValidator $validator;
    
    public function __construct() {
        $this->validator = new CurahHujanValidator();
    }
    
    // =========================================
    // F01-F03: Tanggal Validation Tests
    // =========================================
    
    public function testValidTanggalFormat() {
        $result = $this->validator->validateTanggal('2025-12-28');
        assertTrue($result, 'Valid date format should pass');
    }
    
    public function testInvalidTanggalFormatDDMMYYYY() {
        // F09: Format tanggal salah DD/MM/YYYY
        $result = $this->validator->validateTanggal('28/12/2025');
        assertFalse($result, 'DD/MM/YYYY format should fail');
        assertArrayHasKey('tanggal', $this->validator->getErrors());
    }
    
    public function testEmptyTanggal() {
        $result = $this->validator->validateTanggal('');
        assertFalse($result, 'Empty date should fail');
    }
    
    public function testTanggalTooFarInFuture() {
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $result = $this->validator->validateTanggal($futureDate);
        assertFalse($result, 'Date too far in future should fail');
    }
    
    public function testTanggalTooFarInPast() {
        $pastDate = date('Y-m-d', strtotime('-3 years'));
        $result = $this->validator->validateTanggal($pastDate);
        assertFalse($result, 'Date too far in past should fail');
    }
    
    public function testInvalidDateNonExistent() {
        // February 30 doesn't exist
        $result = $this->validator->validateTanggal('2025-02-30');
        assertFalse($result, 'Non-existent date should fail');
    }
    
    // =========================================
    // F08: Lokasi Validation Tests
    // =========================================
    
    public function testValidLokasiJember() {
        $result = $this->validator->validateLokasi('Jember');
        assertTrue($result, 'Jember should be valid location');
    }
    
    public function testValidLokasiKecamatan() {
        $result = $this->validator->validateLokasi('Kaliwates');
        assertTrue($result, 'Kaliwates (kecamatan) should be valid');
    }
    
    public function testInvalidLokasiOutsideJember() {
        // F08: Lokasi bukan Jember
        $result = $this->validator->validateLokasi('Surabaya');
        assertFalse($result, 'Location outside Jember should fail');
        assertArrayHasKey('lokasi', $this->validator->getErrors());
    }
    
    public function testLokasiWithSQLInjection() {
        // S04: SQL Injection attempt
        $result = $this->validator->validateLokasi("'; DROP TABLE--");
        assertFalse($result, 'SQL injection should fail validation');
    }
    
    public function testLokasiWithXSS() {
        // S05: XSS attempt
        $result = $this->validator->validateLokasi('<script>alert(1)</script>');
        assertFalse($result, 'XSS attempt should fail validation');
    }
    
    public function testLokasiCaseInsensitive() {
        $result = $this->validator->validateLokasi('jember');
        assertTrue($result, 'Case insensitive should work');
    }
    
    public function testLokasiTooLong() {
        $longLokasi = str_repeat('a', 150);
        $result = $this->validator->validateLokasi($longLokasi);
        assertFalse($result, 'Location too long should fail');
    }
    
    // =========================================
    // F05-F07: Curah Hujan Validation Tests
    // =========================================
    
    public function testValidCurahHujanNormal() {
        $result = $this->validator->validateCurahHujan(25.5);
        assertTrue($result, 'Normal rainfall value should pass');
    }
    
    public function testValidCurahHujanZero() {
        $result = $this->validator->validateCurahHujan(0);
        assertTrue($result, 'Zero rainfall should be valid');
    }
    
    public function testValidCurahHujanExtreme() {
        // F05: Nilai ekstrem valid (300mm)
        $result = $this->validator->validateCurahHujan(300);
        assertTrue($result, 'Extreme but valid value (300mm) should pass');
    }
    
    public function testInvalidCurahHujanNegative() {
        // F06: Nilai negatif (-5mm)
        $result = $this->validator->validateCurahHujan(-5);
        assertFalse($result, 'Negative rainfall should fail');
        assertArrayHasKey('curah_hujan', $this->validator->getErrors());
    }
    
    public function testInvalidCurahHujanTooHigh() {
        // F07: Nilai > 500mm
        $result = $this->validator->validateCurahHujan(600);
        assertFalse($result, 'Value > 500mm should fail');
    }
    
    public function testInvalidCurahHujanNotNumeric() {
        $result = $this->validator->validateCurahHujan('abc');
        assertFalse($result, 'Non-numeric value should fail');
    }
    
    public function testCurahHujanPrecision() {
        $result = $this->validator->validateCurahHujan(25.12);
        assertTrue($result, '2 decimal places should be valid');
    }
    
    // =========================================
    // Sumber Data Validation Tests
    // =========================================
    
    public function testValidSumberData() {
        $result = $this->validator->validateSumberData('BMKG API');
        assertTrue($result, 'BMKG API should be valid source');
    }
    
    public function testInvalidSumberData() {
        $result = $this->validator->validateSumberData('Unknown Source');
        assertFalse($result, 'Unknown source should fail');
    }
    
    // =========================================
    // Kode Wilayah Validation Tests
    // =========================================
    
    public function testValidKodeWilayahJember() {
        $result = $this->validator->validateKodeWilayah('35.09');
        assertTrue($result, 'Jember kabupaten code should be valid');
    }
    
    public function testValidKodeWilayahKecamatan() {
        $result = $this->validator->validateKodeWilayah('35.09.29');
        assertTrue($result, 'Jember kecamatan code should be valid');
    }
    
    public function testInvalidKodeWilayahOutsideJember() {
        $result = $this->validator->validateKodeWilayah('35.10.01');
        assertFalse($result, 'Non-Jember code should fail');
    }
    
    // =========================================
    // Full Record Validation Tests
    // =========================================
    
    public function testValidateCompleteRecord() {
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => 'Jember',
            'curah_hujan' => 25.5,
            'sumber_data' => 'BMKG API'
        ];
        
        $result = $this->validator->validate($data);
        assertTrue($result, 'Complete valid record should pass');
        assertTrue($this->validator->isValid());
    }
    
    public function testValidateIncompleteRecord() {
        $data = [
            'tanggal' => '2025-12-28'
            // Missing lokasi and curah_hujan
        ];
        
        $result = $this->validator->validate($data);
        assertFalse($result, 'Incomplete record should fail');
    }
    
    // =========================================
    // Sanitize Tests
    // =========================================
    
    public function testSanitizeRemovesHtml() {
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => '<b>Jember</b>',
            'curah_hujan' => 25.5,
            'sumber_data' => 'Manual',
            'keterangan' => '<script>alert(1)</script>'
        ];
        
        $sanitized = $this->validator->sanitize($data);
        
        assertEquals('&lt;b&gt;Jember&lt;/b&gt;', $sanitized['lokasi']);
        assertTrue(strpos($sanitized['keterangan'], '<script>') === false);
    }
    
    public function testSanitizeRoundsCurahHujan() {
        $data = [
            'tanggal' => '2025-12-28',
            'lokasi' => 'Jember',
            'curah_hujan' => 25.5678,
            'sumber_data' => 'Manual'
        ];
        
        $sanitized = $this->validator->sanitize($data);
        assertEquals(25.57, $sanitized['curah_hujan']);
    }
    
    // =========================================
    // Static Method Tests
    // =========================================
    
    public function testGetValidLocations() {
        $locations = CurahHujanValidator::getValidLocations();
        assertTrue(in_array('Jember', $locations));
        assertTrue(in_array('Kaliwates', $locations));
        assertTrue(count($locations) >= 31); // At least 31 kecamatan + kabupaten
    }
    
    public function testGetValidSources() {
        $sources = CurahHujanValidator::getValidSources();
        assertTrue(in_array('BMKG API', $sources));
        assertTrue(in_array('Manual', $sources));
    }
    
    // =========================================
    // Seasonal Anomaly Detection Tests
    // =========================================
    
    public function testDetectSeasonalAnomalyDrySeason() {
        // Dry season (Jun-Sep): rainfall > 100mm is suspect
        $anomaly = CurahHujanValidator::detectSeasonalAnomaly('2025-07-15', 150.0);
        assertTrue($anomaly !== null, 'High rainfall in dry season should be detected');
        assertTrue(strpos($anomaly, 'SUSPECT') !== false, 'Should be marked as SUSPECT');
    }
    
    public function testDetectSeasonalAnomalyWetSeason() {
        // Wet season (Nov-Mar): very high rainfall > 200mm is notable
        $anomaly = CurahHujanValidator::detectSeasonalAnomaly('2025-01-15', 250.0);
        assertTrue($anomaly !== null, 'Very high rainfall should be noted');
        assertTrue(strpos($anomaly, 'NOTE') !== false, 'Should be marked as NOTE');
    }
    
    public function testDetectSeasonalAnomalyExtremeValue() {
        // Any season: > 300mm is extreme
        $anomaly = CurahHujanValidator::detectSeasonalAnomaly('2025-04-15', 350.0);
        assertTrue($anomaly !== null, 'Extreme rainfall should be detected');
        assertTrue(strpos($anomaly, 'WARN') !== false, 'Should be marked as WARN');
    }
    
    public function testDetectSeasonalAnomalyNormalValue() {
        // Normal rainfall should not trigger anomaly
        $anomaly = CurahHujanValidator::detectSeasonalAnomaly('2025-01-15', 25.0);
        assertTrue($anomaly === null, 'Normal rainfall should not be flagged');
    }
    
    public function testIsNormalSeasonal() {
        $isNormal = CurahHujanValidator::isNormalSeasonal('2025-03-15', 40.0);
        assertTrue($isNormal, 'Normal wet season rainfall should be normal');
        
        $isNormal = CurahHujanValidator::isNormalSeasonal('2025-07-15', 150.0);
        assertFalse($isNormal, 'High dry season rainfall should not be normal');
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && !class_exists('PHPUnit\Framework\TestCase')) {
    $runner = new SimpleTestRunner();
    $success = $runner->run('CurahHujanValidatorTest');
    exit($success ? 0 : 1);
}
