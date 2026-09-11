<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/JenisLaporanService.php';

/**
 * Validasi murni JenisLaporanService (tanpa DB).
 */
final class JenisLaporanServiceValidationTest extends TestCase
{
    private JenisLaporanService $service;

    protected function setUp(): void
    {
        $this->service = new JenisLaporanService();
    }

    public function testNormalizeTrimsLowercasesKodeAndDefaultsFieldsJson(): void
    {
        $data = $this->service->normalize([
            'kode' => '  Panen ',
            'nama' => '  Panen ',
            'deskripsi' => '',
            'fields_json' => '',
        ]);

        self::assertSame('panen', $data['kode']);
        self::assertSame('Panen', $data['nama']);
        self::assertNull($data['deskripsi']);
        self::assertSame('[]', $data['fields_json']);
        self::assertSame(0, $data['is_active']);
    }

    public function testValidateAcceptsValidPayload(): void
    {
        $data = $this->service->normalize([
            'kode' => 'panen',
            'nama' => 'Panen',
            'deskripsi' => 'Laporan panen',
            'fields_json' => '[{"name":"komoditas","label":"Komoditas","type":"text","required":true}]',
            'is_active' => '1',
        ]);

        self::assertSame([], $this->service->validate($data));
    }

    public function testValidateRejectsMissingNamaAndBadKode(): void
    {
        $errors = $this->service->validate($this->service->normalize([
            'kode' => 'BAD CODE!',
            'nama' => '',
            'fields_json' => '[]',
        ]));

        self::assertContains('Nama jenis laporan wajib diisi', $errors);
        self::assertContains('Kode hanya boleh huruf kecil, angka, dan underscore (2-60 karakter)', $errors);
    }

    public function testValidateRejectsBrokenFieldsJson(): void
    {
        $errors = $this->service->validate($this->service->normalize([
            'kode' => 'panen',
            'nama' => 'Panen',
            'fields_json' => '{broken',
        ]));

        self::assertContains('fields_json harus berupa JSON valid', $errors);
    }

    public function testValidateRejectsDuplicateFieldNamesAndBadType(): void
    {
        $errors = $this->service->validateFieldsJson(json_encode([
            ['name' => 'luas', 'label' => 'Luas', 'type' => 'number', 'required' => false],
            ['name' => 'luas', 'label' => 'Luas Lagi', 'type' => 'select', 'required' => false],
            ['name' => 'Bad Name', 'label' => '', 'type' => 'text'],
        ]));

        self::assertContains("Field #2: name 'luas' duplikat", $errors);
        self::assertContains('Field #2: type harus salah satu dari text, textarea, number, integer, date', $errors);
        self::assertContains('Field #3: label wajib diisi', $errors);
    }

    public function testValidateRejectsTooManyFields(): void
    {
        $fields = [];
        for ($i = 0; $i < 51; $i++) {
            $fields[] = ['name' => "f{$i}", 'label' => "F{$i}", 'type' => 'text'];
        }

        self::assertContains(
            'Maksimal 50 field dinamis per jenis laporan',
            $this->service->validateFieldsJson(json_encode($fields))
        );
    }

    public function testCanonicalizeKeepsWhitelistKeysAndBoolRequired(): void
    {
        $canonical = $this->service->canonicalizeFieldsJson(json_encode([
            ['name' => 'a', 'label' => 'A', 'type' => 'text', 'required' => 'yes', 'extra' => 'drop'],
        ]));

        self::assertSame(
            '[{"name":"a","label":"A","type":"text","required":true}]',
            $canonical
        );
    }

    public function testSlugify(): void
    {
        self::assertSame('panen', JenisLaporanService::slugify('Panen'));
        self::assertSame('bibit_baru', JenisLaporanService::slugify('Bibit Baru!'));
        self::assertSame('a', JenisLaporanService::slugify('  A  '));
        self::assertSame('', JenisLaporanService::slugify('!!!'));
        self::assertLessThanOrEqual(60, strlen(JenisLaporanService::slugify(str_repeat('Ab ', 40))));
    }

    public function testNormalizeAutoFillsEmptyKodeFromNama(): void
    {
        $data = $this->service->normalize(['nama' => 'Rumah Kaca', 'fields_json' => '[]']);
        self::assertSame('rumah_kaca', $data['kode']);
        self::assertSame([], $this->service->validate($data));
    }

    public function testNormalizeKeepsExplicitKode(): void
    {
        $data = $this->service->normalize(['kode' => 'Custom_01', 'nama' => 'Custom', 'fields_json' => '[]']);
        self::assertSame('custom_01', $data['kode']);
    }
}
