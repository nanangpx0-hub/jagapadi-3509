<?php

use PHPUnit\Framework\TestCase;

final class GeoValidatorTest extends TestCase {

    public function testValidCoordinatesInsideJember(): void {
        // Alun-alun Jember
        $res1 = GeoValidator::validateJemberCoordinates(-8.172100, 113.700100);
        self::assertTrue($res1['valid']);
        self::assertStringContainsString('valid', strtolower($res1['message']));

        // Tanggul Jember
        $res2 = GeoValidator::validateJemberCoordinates(-8.165000, 113.450000);
        self::assertTrue($res2['valid']);

        // Ambulu (daratan)
        $res3 = GeoValidator::validateJemberCoordinates(-8.350000, 113.600000);
        self::assertTrue($res3['valid']);
    }

    public function testRejectsCoordinatesOutsideBoundingBox(): void {
        // Surabaya
        $surabaya = GeoValidator::validateJemberCoordinates(-7.250445, 112.768845);
        self::assertFalse($surabaya['valid']);
        self::assertNotEmpty($surabaya['message']);

        // Jakarta
        $jakarta = GeoValidator::validateJemberCoordinates(-6.208800, 106.845600);
        self::assertFalse($jakarta['valid']);
        self::assertNotEmpty($jakarta['message']);

        // Terlalu utara (Lat > -7.96)
        $tooNorth = GeoValidator::validateJemberCoordinates(-7.900000, 113.500000);
        self::assertFalse($tooNorth['valid']);
        self::assertStringContainsString('Latitude', $tooNorth['message']);

        // Terlalu barat (Lng < 113.28)
        $tooWest = GeoValidator::validateJemberCoordinates(-8.100000, 113.200000);
        self::assertFalse($tooWest['valid']);
        self::assertStringContainsString('Longitude', $tooWest['message']);

        // Terlalu timur (Lng > 113.98)
        $tooEast = GeoValidator::validateJemberCoordinates(-8.100000, 114.050000);
        self::assertFalse($tooEast['valid']);
        self::assertStringContainsString('Longitude', $tooEast['message']);
    }

    public function testRejectsCoordinatesInSouthernSea(): void {
        // Samudra Hindia di perairan selatan Jember (Lat < -8.39)
        $seaPoint1 = GeoValidator::validateJemberCoordinates(-8.420000, 113.500000);
        self::assertFalse($seaPoint1['valid']);
        self::assertStringContainsString('laut', strtolower($seaPoint1['message']));

        $seaPoint2 = GeoValidator::validateJemberCoordinates(-8.460000, 113.600000);
        self::assertFalse($seaPoint2['valid']);
        self::assertStringContainsString('Samudra Hindia', $seaPoint2['message']);
    }
}
