<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\LaporanHamaValidator;
use App\Helpers\LaporanIrigasiValidator;
use PHPUnit\Framework\TestCase;

final class JemberBoundsValidatorTest extends TestCase
{
    private const PESAN = 'Koordinat lokasi berada di luar wilayah Kabupaten Jember.';

    public function testHamaKoordinatJemberDiterima(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'latitude' => -8.1734,
            'longitude' => 113.7012,
        ]);
        $this->assertArrayNotHasKey('koordinat', $errors);
        $this->assertArrayNotHasKey('latitude', $errors);
        $this->assertArrayNotHasKey('longitude', $errors);
    }

    public function testHamaKoordinatLuarJemberDitolak(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);
        $this->assertArrayHasKey('koordinat', $errors);
        $this->assertSame(self::PESAN, $errors['koordinat']);
    }

    public function testHamaKoordinatTepiBatas(): void
    {
        $this->assertArrayNotHasKey(
            'koordinat',
            LaporanHamaValidator::validateDraft(['latitude' => -8.5500, 'longitude' => 113.3000])
        );
        $this->assertArrayNotHasKey(
            'koordinat',
            LaporanHamaValidator::validateDraft(['latitude' => -7.9000, 'longitude' => 114.1000])
        );
        $this->assertArrayHasKey(
            'koordinat',
            LaporanHamaValidator::validateDraft(['latitude' => -8.5501, 'longitude' => 113.7000])
        );
        $this->assertArrayHasKey(
            'koordinat',
            LaporanHamaValidator::validateDraft(['latitude' => -8.1706, 'longitude' => 114.1001])
        );
    }

    public function testHamaKoordinatParsialTidakMemicuBounds(): void
    {
        $this->assertArrayNotHasKey(
            'koordinat',
            LaporanHamaValidator::validateDraft(['latitude' => -8.1734])
        );
    }

    public function testHamaRangeGlobalTetapDominan(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['latitude' => 100, 'longitude' => 113.7012]);
        $this->assertArrayHasKey('latitude', $errors);
        $this->assertArrayNotHasKey('koordinat', $errors);
    }

    public function testIrigasiKoordinatJemberDiterima(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([
            'latitude' => -8.1706,
            'longitude' => 113.7003,
        ]);
        $this->assertArrayNotHasKey('koordinat', $errors);
    }

    public function testIrigasiKoordinatLuarJemberDitolak(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([
            'latitude' => -7.2575,
            'longitude' => 112.7521,
        ]);
        $this->assertArrayHasKey('koordinat', $errors);
        $this->assertSame(self::PESAN, $errors['koordinat']);
    }

    public function testSubmitMewarisiBoundsCheck(): void
    {
        // Tanpa DB: required dikosongkan agar blok query dilewati,
        // merge validateDraft tetap membawa error koordinat.
        $errors = LaporanHamaValidator::validateSubmit([
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);
        $this->assertArrayHasKey('koordinat', $errors);
        $this->assertArrayHasKey('tanggal', $errors);
    }
}
