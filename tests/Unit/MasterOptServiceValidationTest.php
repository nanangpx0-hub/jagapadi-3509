<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/DuplicateMasterException.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';

final class MasterOptServiceValidationTest extends TestCase
{
    private MasterOptService $service;

    protected function setUp(): void
    {
        $this->service = new MasterOptService();
    }

    public function testNormalizeAppliesDefaultsAndTrims(): void
    {
        $data = $this->service->normalize([
            'kode_opt' => '  OPT-01 ',
            'nama_opt' => ' Wereng Batang Coklat ',
            'jenis' => ' HAMA ',
        ]);

        self::assertSame('OPT-01', $data['kode_opt']);
        self::assertSame('Wereng Batang Coklat', $data['nama_opt']);
        self::assertSame('hama', $data['jenis']);
        self::assertSame('Tidak', $data['status_karantina']);
        self::assertSame('Sedang', $data['tingkat_bahaya']);
        self::assertSame(0, $data['aktif'], 'Checkbox aktif tidak dicentang harus menghasilkan 0');
    }

    public function testNormalizeNullifiesEmptyOptionalFields(): void
    {
        $data = $this->service->normalize([
            'kode_opt' => 'OPT-02',
            'nama_opt' => 'Ulat Grayak',
            'jenis' => 'hama',
            'nama_ilmiah' => '   ',
            'deskripsi' => '',
        ]);

        self::assertNull($data['nama_ilmiah']);
        self::assertNull($data['deskripsi']);
    }

    public function testNormalizeCastsEtlToFloatOrNull(): void
    {
        $withValue = $this->service->normalize(['etl_acuan' => '12.5']);
        $empty = $this->service->normalize(['etl_acuan' => '']);

        self::assertSame(12.5, $withValue['etl_acuan']);
        self::assertNull($empty['etl_acuan']);
    }

    public function testValidateRejectsMissingRequiredFields(): void
    {
        $errors = $this->service->validate($this->service->normalize([
            'kode_opt' => '',
            'nama_opt' => '',
            'jenis' => '',
        ]));

        self::assertContains('Kode OPT wajib diisi', $errors);
        self::assertContains('Nama OPT (nasional) wajib diisi', $errors);
        self::assertContains('Jenis OPT tidak valid', $errors);
    }

    public function testValidateRejectsInvalidEnumValues(): void
    {
        $errors = $this->service->validate([
            'kode_opt' => 'X',
            'nama_opt' => 'Nama',
            'jenis' => 'virus',
            'status_karantina' => 'Mungkin',
            'tingkat_bahaya' => 'Ekstrem',
        ]);

        self::assertContains('Jenis OPT tidak valid', $errors);
        self::assertContains('Status karantina tidak valid', $errors);
        self::assertContains('Tingkat bahaya tidak valid', $errors);
    }

    public function testValidateAcceptsOfficialEnumValues(): void
    {
        $errors = $this->service->validate([
            'kode_opt' => 'OPT-99',
            'nama_opt' => 'Penggerek Batang Padi',
            'jenis' => 'penyakit',
            'status_karantina' => 'OPTK A2',
            'tingkat_bahaya' => 'Sangat Tinggi',
            'etl_acuan' => 5.0,
        ]);

        self::assertSame([], $errors);
    }

    public function testValidateRejectsNegativeEtl(): void
    {
        $errors = $this->service->validate([
            'kode_opt' => 'X',
            'nama_opt' => 'Nama',
            'jenis' => 'gulma',
            'etl_acuan' => -1.5,
        ]);

        self::assertContains('ETL acuan tidak boleh negatif', $errors);
    }

    public function testValidateEnforcesColumnLengths(): void
    {
        $errors = $this->service->validate([
            'kode_opt' => str_repeat('A', 51),
            'nama_opt' => str_repeat('B', 151),
            'jenis' => 'hama',
        ]);

        self::assertContains('Kode opt maksimal 50 karakter', $errors);
        self::assertContains('Nama opt maksimal 150 karakter', $errors);
    }

    public function testNormalizeKeepsExplicitAktifFlag(): void
    {
        self::assertSame(1, $this->service->normalize(['aktif' => '1'])['aktif']);
        self::assertSame(0, $this->service->normalize(['aktif' => ''])['aktif']);
    }
}
