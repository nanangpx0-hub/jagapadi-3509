<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\LaporanIrigasiValidator;
use PHPUnit\Framework\TestCase;

class LaporanIrigasiValidatorTest extends TestCase
{
    public function testEmptyDraftReturnsNoErrors(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([]);
        $this->assertCount(0, $errors);
    }

    public function testPartialDraftValidDataAccepted(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([
            'catatan' => 'Draf parsial irigasi',
            'nama_saluran' => 'Saluran utama',
        ]);
        $this->assertCount(0, $errors);
    }

    public function testInvalidKondisiFisikRejected(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([
            'kondisi_fisik' => 'SangatRusak',
        ]);
        $this->assertArrayHasKey('kondisi_fisik', $errors);
    }

    public function testValidKondisiFisikAccepted(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['kondisi_fisik' => 'Bagus']);
        $this->assertArrayNotHasKey('kondisi_fisik', $errors);

        $errors = LaporanIrigasiValidator::validateDraft(['kondisi_fisik' => 'Sedang']);
        $this->assertArrayNotHasKey('kondisi_fisik', $errors);

        $errors = LaporanIrigasiValidator::validateDraft(['kondisi_fisik' => 'Tidak Bagus']);
        $this->assertArrayNotHasKey('kondisi_fisik', $errors);

        $errors = LaporanIrigasiValidator::validateDraft(['kondisi_fisik' => 'Rusak']);
        $this->assertArrayNotHasKey('kondisi_fisik', $errors);
    }

    public function testInvalidDebitAirRejected(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft([
            'debit_air' => 'Meluap',
        ]);
        $this->assertArrayHasKey('debit_air', $errors);
    }

    public function testValidDebitAirAccepted(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['debit_air' => 'Cukup']);
        $this->assertArrayNotHasKey('debit_air', $errors);

        $errors = LaporanIrigasiValidator::validateDraft(['debit_air' => 'Kurang']);
        $this->assertArrayNotHasKey('debit_air', $errors);

        $errors = LaporanIrigasiValidator::validateDraft(['debit_air' => 'Kering']);
        $this->assertArrayNotHasKey('debit_air', $errors);
    }

    public function testInvalidLatitudeRejected(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['latitude' => 100]);
        $this->assertArrayHasKey('latitude', $errors);
    }

    public function testInvalidLongitudeRejected(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['longitude' => 200]);
        $this->assertArrayHasKey('longitude', $errors);
    }

    public function testSubmitRejectsEmpty(): void
    {
        $errors = LaporanIrigasiValidator::validateSubmit([]);
        $this->assertArrayHasKey('tanggal', $errors);
        $this->assertArrayHasKey('kabupaten_id', $errors);
        $this->assertArrayHasKey('kecamatan_id', $errors);
        $this->assertArrayHasKey('desa_id', $errors);
        $this->assertArrayHasKey('nama_saluran', $errors);
        $this->assertArrayHasKey('kondisi_fisik', $errors);
        $this->assertArrayHasKey('debit_air', $errors);
    }

    public function testNamaSaluranMaxLength(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['nama_saluran' => str_repeat('a', 201)]);
        $this->assertArrayHasKey('nama_saluran', $errors);
    }

    public function testDaerahIrigasiMaxLength(): void
    {
        $errors = LaporanIrigasiValidator::validateDraft(['daerah_irigasi' => str_repeat('a', 201)]);
        $this->assertArrayHasKey('daerah_irigasi', $errors);
    }
}
