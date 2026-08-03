<?php
declare(strict_types=1);

/**
 * GeoValidator Helper
 * Validasi koordinat geografis untuk memastikan titik lokasi berada di daratan Kabupaten Jember.
 */
class GeoValidator {
    /**
     * Batas Bounding Box Kabupaten Jember
     */
    public const MIN_LAT = -8.480000;
    public const MAX_LAT = -7.960000;
    public const MIN_LNG = 113.280000;
    public const MAX_LNG = 113.980000;

    /**
     * Batas Wilayah Lautan Selatan (Samudra Hindia)
     */
    public const SEA_SOUTH_LAT_THRESHOLD = -8.390000;

    /**
     * Memvalidasi apakah koordinat (latitude, longitude) berada di daratan Kabupaten Jember.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array Array berisi ['valid' => bool, 'message' => string]
     */
    public static function validateJemberCoordinates(float $lat, float $lng): array {
        // 1. Cek Bounding Box Kabupaten Jember
        if ($lat < self::MIN_LAT || $lat > self::MAX_LAT) {
            return [
                'valid' => false,
                'message' => sprintf('Latitude (%.6f) berada di luar batas wilayah Kabupaten Jember (%.6f s/d %.6f).', $lat, self::MIN_LAT, self::MAX_LAT)
            ];
        }

        if ($lng < self::MIN_LNG || $lng > self::MAX_LNG) {
            return [
                'valid' => false,
                'message' => sprintf('Longitude (%.6f) berada di luar batas wilayah Kabupaten Jember (%.6f s/d %.6f).', $lng, self::MIN_LNG, self::MAX_LNG)
            ];
        }

        // 2. Cek Area Perairan/Lautan Selatan (Samudra Hindia)
        if ($lat < self::SEA_SOUTH_LAT_THRESHOLD) {
            return [
                'valid' => false,
                'message' => sprintf('Koordinat (%.6f, %.6f) berada di wilayah laut/perairan selatan (Samudra Hindia).', $lat, $lng)
            ];
        }

        return [
            'valid' => true,
            'message' => 'Koordinat valid berada di daratan Kabupaten Jember.'
        ];
    }
}
