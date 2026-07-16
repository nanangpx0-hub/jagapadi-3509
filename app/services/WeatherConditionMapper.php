<?php
/**
 * Weather Condition to Rainfall Mapper
 * 
 * Maps BMKG weather descriptions to estimated rainfall in mm.
 * Based on BMKG's rainfall intensity classification:
 * - Ringan (Light): 1-5 mm/hour
 * - Sedang (Moderate): 5-10 mm/hour  
 * - Lebat (Heavy): 10-20 mm/hour
 * - Sangat Lebat (Very Heavy): > 20 mm/hour
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

class WeatherConditionMapper {
    
    /**
     * Weather description to rainfall mapping (in mm per 3-hour period)
     * Values are conservative estimates for 3-hour forecasts
     * 
     * @var array
     */
    private static $mappingRules = [
        // No rainfall conditions
        'Cerah' => 0,
        'Cerah Berawan' => 0,
        'Berawan' => 0,
        'Berawan Tebal' => 0,
        'Udara Kabur' => 0,
        'Asap' => 0,
        'Kabut' => 0,
        
        // Light rainfall (1-5 mm/hour → 3-15 mm per 3 hours)
        'Hujan Ringan' => 3,
        
        // Moderate rainfall (5-10 mm/hour → 15-30 mm per 3 hours)
        'Hujan Sedang' => 7.5,
        
        // Heavy rainfall (10-20 mm/hour → 30-60 mm per 3 hours)
        'Hujan Lebat' => 15,
        
        // Very heavy rainfall (>20 mm/hour → >60 mm per 3 hours)
        'Hujan Sangat Lebat' => 25,
        
        // Thunderstorm (varies, assume moderate-heavy)
        'Hujan Petir' => 12,
    ];
    
    /**
     * Estimate rainfall amount from weather description
     * 
     * @param string $weatherDesc Weather description from BMKG
     * @return float Estimated rainfall in mm
     */
    public static function estimateRainfall($weatherDesc) {
        if (empty($weatherDesc)) {
            return 0;
        }
        
        // Exact match first
        if (isset(self::$mappingRules[$weatherDesc])) {
            return self::$mappingRules[$weatherDesc];
        }
        
        // Pattern matching for partial matches
        $weatherDescLower = strtolower($weatherDesc);
        
        // Check for rain keywords
        if (strpos($weatherDescLower, 'hujan sangat lebat') !== false) {
            return self::$mappingRules['Hujan Sangat Lebat'];
        } elseif (strpos($weatherDescLower, 'hujan lebat') !== false) {
            return self::$mappingRules['Hujan Lebat'];
        } elseif (strpos($weatherDescLower, 'hujan sedang') !== false) {
            return self::$mappingRules['Hujan Sedang'];
        } elseif (strpos($weatherDescLower, 'hujan ringan') !== false) {
            return self::$mappingRules['Hujan Ringan'];
        } elseif (strpos($weatherDescLower, 'hujan petir') !== false || 
                  strpos($weatherDescLower, 'petir') !== false) {
            return self::$mappingRules['Hujan Petir'];
        } elseif (strpos($weatherDescLower, 'hujan') !== false) {
            // Generic "hujan" - assume light
            return self::$mappingRules['Hujan Ringan'];
        }
        
        // No rain detected
        return 0;
    }
    
    /**
     * Get rainfall category based on amount
     * 
     * @param float $rainfall Rainfall amount in mm
     * @return string Category name
     */
    public static function getRainfallCategory($rainfall) {
        if ($rainfall == 0) {
            return 'Tidak Hujan';
        } elseif ($rainfall <= 5) {
            return 'Hujan Ringan';
        } elseif ($rainfall <= 10) {
            return 'Hujan Sedang';
        } elseif ($rainfall <= 20) {
            return 'Hujan Lebat';
        } else {
            return 'Hujan Sangat Lebat';
        }
    }
    
    /**
     * Get all supported weather conditions
     * 
     * @return array List of weather conditions
     */
    public static function getSupportedConditions() {
        return array_keys(self::$mappingRules);
    }
    
    /**
     * Check if weather condition indicates rain
     * 
     * @param string $weatherDesc Weather description
     * @return bool True if rain is expected
     */
    public static function isRainy($weatherDesc) {
        return self::estimateRainfall($weatherDesc) > 0;
    }
}
