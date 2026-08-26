<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\LaporanHamaValidator;
use PHPUnit\Framework\TestCase;

class LaporanHamaValidatorTest extends TestCase
{
    public function testEmptyDraftReturnsNoErrors(): void
    {
        $errors = LaporanHamaValidator::validateDraft([]);
        $this->assertCount(0, $errors);
    }

    public function testPartialDraftValidDataAccepted(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'catatan' => 'Draf parsial',
            'lokasi' => 'Sawah utara',
        ]);
        $this->assertCount(0, $errors);
    }

    public function testInvalidEnumRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'tingkat_keparahan' => 'SangatBerat',
        ]);
        $this->assertArrayHasKey('tingkat_keparahan', $errors);
    }

    public function testValidEnumAccepted(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'tingkat_keparahan' => 'Ringan',
        ]);
        $this->assertArrayNotHasKey('tingkat_keparahan', $errors);
    }

    public function testInvalidLatitudeRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['latitude' => 100]);
        $this->assertArrayHasKey('latitude', $errors);
    }

    public function testValidLatitudeAccepted(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['latitude' => -8.1734]);
        $this->assertArrayNotHasKey('latitude', $errors);
    }

    public function testInvalidLongitudeRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['longitude' => 200]);
        $this->assertArrayHasKey('longitude', $errors);
    }

    public function testLuasSeranganNegativeRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['luas_serangan' => -1]);
        $this->assertArrayHasKey('luas_serangan', $errors);
    }

    public function testLuasSeranganExceedsMax(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['luas_serangan' => 10000]);
        $this->assertArrayHasKey('luas_serangan', $errors);
    }

    public function testSubmitRejectsEmpty(): void
    {
        $errors = LaporanHamaValidator::validateSubmit([]);
        $this->assertArrayHasKey('tanggal', $errors);
        $this->assertArrayHasKey('master_opt_id', $errors);
        $this->assertArrayHasKey('kabupaten_id', $errors);
        $this->assertArrayHasKey('kecamatan_id', $errors);
        $this->assertArrayHasKey('desa_id', $errors);
        $this->assertArrayHasKey('tingkat_keparahan', $errors);
        $this->assertArrayHasKey('luas_serangan', $errors);
        $this->assertArrayHasKey('populasi', $errors);
        $this->assertArrayHasKey('foto', $errors);
    }

    public function testSubmitInvalidDateHandledGracefully(): void
    {
        try {
            $errors = LaporanHamaValidator::validateSubmit([
                'tanggal' => 'bukan-tanggal',
                'master_opt_id' => 1,
                'kabupaten_id' => 1,
                'kecamatan_id' => 1,
                'desa_id' => 1,
                'tingkat_keparahan' => 'Ringan',
                'luas_serangan' => 1,
                'populasi' => 10,
                'foto_url' => 'assets/uploads/laporan-hama/test.jpg',
            ]);
            $this->assertArrayHasKey('tanggal', $errors);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database tidak tersedia — test integrasi tidak bisa dijalankan.');
        }
    }

    public function testLokasiMaxLength(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['lokasi' => str_repeat('a', 256)]);
        $this->assertArrayHasKey('lokasi', $errors);
    }

    public function testAlamatMaxLength(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['alamat_lengkap' => str_repeat('a', 301)]);
        $this->assertArrayHasKey('alamat_lengkap', $errors);
    }

    public function testSubmitRejectsMissingPhoto(): void
    {
        $errors = LaporanHamaValidator::validateSubmit([
            'tanggal' => '2026-08-12',
            'master_opt_id' => 1,
            'kabupaten_id' => 1,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'tingkat_keparahan' => 'Ringan',
            'luas_serangan' => 1,
            'populasi' => 10,
        ]);

        $this->assertSame(
            'Foto laporan wajib disertakan sebelum laporan dapat dikirim.',
            $errors['foto'] ?? null
        );
    }

    public function testMetodePengukuranInvalidRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['metode_pengukuran' => 'tebakan']);
        $this->assertArrayHasKey('metode_pengukuran', $errors);
    }

    public function testMetodePersentaseAccepted(): void
    {
        $errors = LaporanHamaValidator::validateDraft([
            'metode_pengukuran' => 'persentase',
            'persentase_serangan' => 45.5,
            'luas_areal_diamati' => 2,
        ]);
        $this->assertArrayNotHasKey('metode_pengukuran', $errors);
        $this->assertArrayNotHasKey('persentase_serangan', $errors);
        $this->assertArrayNotHasKey('luas_areal_diamati', $errors);
    }

    public function testPersentaseSeranganOutOfRangeRejected(): void
    {
        $errors = LaporanHamaValidator::validateDraft(['persentase_serangan' => 120]);
        $this->assertArrayHasKey('persentase_serangan', $errors);
    }

    public function testSubmitPersentaseModeDoesNotRequireLuasSerangan(): void
    {
        $errors = LaporanHamaValidator::validateSubmit([
            'metode_pengukuran' => 'persentase',
            'persentase_serangan' => 30,
        ]);

        // Mode persentase tidak menuntut luas_serangan.
        $this->assertArrayNotHasKey('luas_serangan', $errors);
        // Nilai persentase yang diberikan valid → tidak ada error bidang itu.
        $this->assertArrayNotHasKey('persentase_serangan', $errors);
        // Field wajib lainnya tetap dituntut.
        $this->assertArrayHasKey('tanggal', $errors);
        $this->assertArrayHasKey('populasi', $errors);
    }

    public function testSubmitAbsolutModeStillRequiresLuasSerangan(): void
    {
        $errors = LaporanHamaValidator::validateSubmit([
            'metode_pengukuran' => 'absolut',
            'populasi' => 5,
        ]);
        $this->assertArrayHasKey('luas_serangan', $errors);
    }
}
