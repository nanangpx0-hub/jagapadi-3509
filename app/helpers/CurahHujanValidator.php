<?php
/**
 * Curah Hujan Input Validator
 * Comprehensive validation for rainfall data specific to Kabupaten Jember
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class CurahHujanValidator {
    
    /**
     * Valid locations (kecamatan in Kabupaten Jember)
     */
    private const VALID_LOCATIONS = [
        'Jember',      // Kabupaten level
        'Kencong',     '35.09.01',
        'Gumukmas',    '35.09.02',
        'Puger',       '35.09.03',
        'Wuluhan',     '35.09.04',
        'Ambulu',      '35.09.05',
        'Tempurejo',   '35.09.06',
        'Silo',        '35.09.07',
        'Mayang',      '35.09.08',
        'Mumbulsari',  '35.09.09',
        'Jenggawah',   '35.09.10',
        'Ajung',       '35.09.11',
        'Rambipuji',   '35.09.12',
        'Balung',      '35.09.13',
        'Umbulsari',   '35.09.14',
        'Semboro',     '35.09.15',
        'Jombang',     '35.09.16',
        'Sumberbaru',  '35.09.17',
        'Tanggul',     '35.09.18',
        'Bangsalsari', '35.09.19',
        'Panti',       '35.09.20',
        'Sukorambi',   '35.09.21',
        'Arjasa',      '35.09.22',
        'Pakusari',    '35.09.23',
        'Kalisat',     '35.09.24',
        'Ledokombo',   '35.09.25',
        'Sumberjambe', '35.09.26',
        'Sukowono',    '35.09.27',
        'Jelbuk',      '35.09.28',
        'Kaliwates',   '35.09.29',
        'Sumbersari',  '35.09.30',
        'Patrang',     '35.09.31'
    ];
    
    /**
     * Valid data sources
     */
    private const VALID_SOURCES = [
        'BMKG API',
        'BMKG Data Online',
        'Manual',
        'Simulasi',
        'Simulasi (Fallback)'
    ];
    
    /**
     * Rainfall value constraints
     */
    private const MIN_RAINFALL = 0.0;
    private const MAX_RAINFALL = 500.0;  // mm per day
    
    /**
     * Date constraints
     */
    private const MAX_FUTURE_DAYS = 7;   // Allow forecast up to 7 days
    private const MAX_PAST_YEARS = 2;    // Allow historical data up to 2 years
    
    /**
     * Collected validation errors
     */
    private array $errors = [];
    
    /**
     * Validate complete rainfall data record
     * 
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool {
        $this->errors = [];
        
        // Validate tanggal
        if (!$this->validateTanggal($data['tanggal'] ?? null)) {
            // Error already added in validateTanggal
        }
        
        // Validate lokasi
        if (!$this->validateLokasi($data['lokasi'] ?? null)) {
            // Error already added in validateLokasi
        }
        
        // Validate curah_hujan
        if (!$this->validateCurahHujan($data['curah_hujan'] ?? null)) {
            // Error already added in validateCurahHujan
        }
        
        // Validate sumber_data (optional but must be valid if provided)
        if (isset($data['sumber_data']) && !empty($data['sumber_data'])) {
            if (!$this->validateSumberData($data['sumber_data'])) {
                // Error already added
            }
        }
        
        // Validate kode_wilayah (optional but must match pattern if provided)
        if (isset($data['kode_wilayah']) && !empty($data['kode_wilayah'])) {
            if (!$this->validateKodeWilayah($data['kode_wilayah'])) {
                // Error already added
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate date field
     * 
     * @param mixed $tanggal
     * @return bool
     */
    public function validateTanggal($tanggal): bool {
        if (empty($tanggal)) {
            $this->errors['tanggal'] = 'Tanggal harus diisi';
            return false;
        }
        
        // Check format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $this->errors['tanggal'] = 'Format tanggal harus YYYY-MM-DD';
            return false;
        }
        
        // Parse date components
        $parts = explode('-', $tanggal);
        $year = (int) $parts[0];
        $month = (int) $parts[1];
        $day = (int) $parts[2];
        
        // Validate actual date
        if (!checkdate($month, $day, $year)) {
            $this->errors['tanggal'] = 'Tanggal tidak valid';
            return false;
        }
        
        try {
            $inputDate = new DateTime($tanggal);
            $today = new DateTime();
            
            // Check not too far in future
            $maxFuture = new DateTime('+' . self::MAX_FUTURE_DAYS . ' days');
            if ($inputDate > $maxFuture) {
                $this->errors['tanggal'] = 'Tanggal tidak boleh lebih dari ' . self::MAX_FUTURE_DAYS . ' hari ke depan';
                return false;
            }
            
            // Check not too far in past
            $maxPast = new DateTime('-' . self::MAX_PAST_YEARS . ' years');
            if ($inputDate < $maxPast) {
                $this->errors['tanggal'] = 'Tanggal tidak boleh lebih dari ' . self::MAX_PAST_YEARS . ' tahun yang lalu';
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors['tanggal'] = 'Tanggal tidak valid';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate location field (must be in Kabupaten Jember)
     * 
     * @param mixed $lokasi
     * @return bool
     */
    public function validateLokasi($lokasi): bool {
        if (empty($lokasi)) {
            $this->errors['lokasi'] = 'Lokasi harus diisi';
            return false;
        }
        
        // Sanitize and normalize
        $lokasi = trim($lokasi);
        
        // Check length
        if (strlen($lokasi) > 100) {
            $this->errors['lokasi'] = 'Lokasi maksimal 100 karakter';
            return false;
        }
        
        // Check for dangerous characters
        if (preg_match('/[<>"\'\\\;`]/', $lokasi)) {
            $this->errors['lokasi'] = 'Lokasi mengandung karakter tidak valid';
            return false;
        }
        
        // Check against whitelist (case-insensitive)
        $normalized = ucwords(strtolower(trim($lokasi)));
        $validLocations = array_map(function($loc) {
            // Only include text names, not codes
            if (!preg_match('/^\d/', $loc)) {
                return $loc;
            }
            return null;
        }, self::VALID_LOCATIONS);
        $validLocations = array_filter($validLocations);
        
        if (!in_array($normalized, $validLocations) && !in_array($lokasi, $validLocations)) {
            $this->errors['lokasi'] = 'Lokasi harus wilayah Kabupaten Jember';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate rainfall value
     * 
     * @param mixed $curahHujan
     * @return bool
     */
    public function validateCurahHujan($curahHujan): bool {
        if ($curahHujan === null || $curahHujan === '') {
            $this->errors['curah_hujan'] = 'Curah hujan harus diisi';
            return false;
        }
        
        if (!is_numeric($curahHujan)) {
            $this->errors['curah_hujan'] = 'Curah hujan harus berupa angka';
            return false;
        }
        
        $value = (float) $curahHujan;
        
        // Check range
        if ($value < self::MIN_RAINFALL) {
            $this->errors['curah_hujan'] = 'Curah hujan tidak boleh negatif';
            return false;
        }
        
        if ($value > self::MAX_RAINFALL) {
            $this->errors['curah_hujan'] = 'Curah hujan maksimal ' . self::MAX_RAINFALL . ' mm';
            return false;
        }
        
        // Check precision (max 2 decimal places)
        if (preg_match('/\.\d{3,}/', (string) $curahHujan)) {
            $this->errors['curah_hujan'] = 'Curah hujan maksimal 2 angka desimal';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate data source
     * 
     * @param mixed $sumberData
     * @return bool
     */
    public function validateSumberData($sumberData): bool {
        if (empty($sumberData)) {
            return true; // Optional field
        }
        
        if (!in_array($sumberData, self::VALID_SOURCES)) {
            $this->errors['sumber_data'] = 'Sumber data tidak valid';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate kode wilayah (BMKG code)
     * 
     * @param mixed $kodeWilayah
     * @return bool
     */
    public function validateKodeWilayah($kodeWilayah): bool {
        if (empty($kodeWilayah)) {
            return true; // Optional field
        }
        
        // Must start with 35.09 (Jember)
        if (!preg_match('/^35\.09(\.\d{2})?(\.\d{4})?$/', $kodeWilayah)) {
            $this->errors['kode_wilayah'] = 'Kode wilayah harus wilayah Jember (35.09.xx)';
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize data for safe storage
     * 
     * @param array $data
     * @return array
     */
    public function sanitize(array $data): array {
        return [
            'tanggal' => $data['tanggal'] ?? null,
            'lokasi' => htmlspecialchars(
                trim($data['lokasi'] ?? 'Jember'), 
                ENT_QUOTES, 
                'UTF-8'
            ),
            'kode_wilayah' => isset($data['kode_wilayah']) 
                ? preg_replace('/[^0-9.]/', '', $data['kode_wilayah']) 
                : '35.09',
            'curah_hujan' => round((float) ($data['curah_hujan'] ?? 0), 2),
            'satuan' => 'mm',
            'sumber_data' => htmlspecialchars(
                trim($data['sumber_data'] ?? 'Manual'), 
                ENT_QUOTES, 
                'UTF-8'
            ),
            'keterangan' => isset($data['keterangan']) 
                ? htmlspecialchars(trim($data['keterangan']), ENT_QUOTES, 'UTF-8') 
                : null,
        ];
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function getFirstError(): ?string {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function isValid(): bool {
        return empty($this->errors);
    }
    
    /**
     * Validate and sanitize in one call
     * 
     * @param array $data
     * @return array ['valid' => bool, 'data' => array|null, 'errors' => array]
     */
    public function validateAndSanitize(array $data): array {
        $isValid = $this->validate($data);
        
        return [
            'valid' => $isValid,
            'data' => $isValid ? $this->sanitize($data) : null,
            'errors' => $this->errors
        ];
    }
    
    /**
     * Get list of valid locations
     * 
     * @return array
     */
    public static function getValidLocations(): array {
        return array_filter(self::VALID_LOCATIONS, function($loc) {
            return !preg_match('/^\d/', $loc);
        });
    }
    
    /**
     * Get list of valid sources
     * 
     * @return array
     */
    public static function getValidSources(): array {
        return self::VALID_SOURCES;
    }
    
    /**
     * Detect seasonal anomaly in rainfall data
     * Based on tropical monsoon climate patterns for Jember:
     * - Wet season (Nov-Mar): High rainfall expected
     * - Dry season (Jun-Sep): Low rainfall expected
     * - Transition (Apr-May, Oct): Variable
     * 
     * @param string $tanggal Date in YYYY-MM-DD format
     * @param float $curahHujan Rainfall in mm
     * @return string|null Warning message if anomaly detected, null otherwise
     */
    public static function detectSeasonalAnomaly(string $tanggal, float $curahHujan): ?string {
        $month = (int)date('n', strtotime($tanggal));
        
        // Define season boundaries
        $drySeasonMonths = [6, 7, 8, 9];     // June to September
        $wetSeasonMonths = [11, 12, 1, 2, 3]; // November to March
        
        // Extreme value check (any season)
        if ($curahHujan > 300) {
            return 'WARN: Nilai curah hujan ekstrem (>300mm), perlu verifikasi manual';
        }
        
        // Dry season anomaly: rainfall > 100mm is unusual
        if (in_array($month, $drySeasonMonths) && $curahHujan > 100) {
            return 'SUSPECT: Curah hujan tinggi di musim kemarau (>100mm)';
        }
        
        // Wet season anomaly: very high daily rainfall
        if (in_array($month, $wetSeasonMonths) && $curahHujan > 200) {
            return 'NOTE: Curah hujan sangat tinggi (>200mm), puncak musim hujan';
        }
        
        return null;
    }
    
    /**
     * Check if rainfall value is within normal seasonal range
     * 
     * @param string $tanggal Date in YYYY-MM-DD format
     * @param float $curahHujan Rainfall in mm
     * @return bool True if within normal range
     */
    public static function isNormalSeasonal(string $tanggal, float $curahHujan): bool {
        return self::detectSeasonalAnomaly($tanggal, $curahHujan) === null;
    }
}
