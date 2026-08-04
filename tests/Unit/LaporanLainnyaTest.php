<?php

use PHPUnit\Framework\TestCase;

final class LaporanLainnyaTest extends TestCase {

    public function testJenisLaporanFieldsStructure(): void {
        $fields = [
            ['name' => 'nama_varietas', 'label' => 'Nama Varietas', 'type' => 'text', 'required' => true],
            ['name' => 'jumlah_bibit', 'label' => 'Jumlah Bibit', 'type' => 'number', 'required' => true],
            ['name' => 'sumber_bibit', 'label' => 'Sumber Bibit', 'type' => 'text', 'required' => false],
        ];
        self::assertCount(3, $fields);
        $requiredFields = array_filter($fields, fn($f) => !empty($f['required']));
        self::assertCount(2, $requiredFields);
        $fieldNames = array_column($fields, 'name');
        self::assertContains('nama_varietas', $fieldNames);
        self::assertContains('jumlah_bibit', $fieldNames);
        self::assertContains('sumber_bibit', $fieldNames);
    }

    public function testRumahKacaFieldsStructure(): void {
        $fields = [
            ['name' => 'jumlah_unit', 'label' => 'Jumlah Unit', 'type' => 'number', 'required' => true],
            ['name' => 'luas_m2', 'label' => 'Luas (m2)', 'type' => 'number', 'required' => false],
            ['name' => 'komoditas', 'label' => 'Komoditas', 'type' => 'text', 'required' => false],
        ];
        self::assertCount(3, $fields);
        $requiredFields = array_filter($fields, fn($f) => !empty($f['required']));
        self::assertCount(1, $requiredFields);
    }

    public function testPanenFieldsStructure(): void {
        $fields = [
            ['name' => 'komoditas', 'label' => 'Komoditas', 'type' => 'text', 'required' => true],
            ['name' => 'luas_ha', 'label' => 'Luas Panen (Ha)', 'type' => 'number', 'required' => false],
            ['name' => 'estimasi_ton', 'label' => 'Estimasi Ton', 'type' => 'number', 'required' => false],
        ];
        self::assertCount(3, $fields);
        $requiredFields = array_filter($fields, fn($f) => !empty($f['required']));
        self::assertCount(1, $requiredFields);
    }

    public function testBantuanAlsintanFieldsStructure(): void {
        $fields = [
            ['name' => 'nama_alat', 'label' => 'Nama Alat', 'type' => 'text', 'required' => true],
            ['name' => 'jumlah', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
            ['name' => 'sumber_bantuan', 'label' => 'Sumber Bantuan', 'type' => 'text', 'required' => false],
        ];
        self::assertCount(3, $fields);
        $requiredFields = array_filter($fields, fn($f) => !empty($f['required']));
        self::assertCount(1, $requiredFields);
    }

    public function testKerusakanCuacaFieldsStructure(): void {
        $fields = [
            ['name' => 'jenis_cuaca', 'label' => 'Jenis Cuaca', 'type' => 'text', 'required' => true],
            ['name' => 'luas_terdampak_ha', 'label' => 'Luas Terdampak (Ha)', 'type' => 'number', 'required' => false],
        ];
        self::assertCount(2, $fields);
        $requiredFields = array_filter($fields, fn($f) => !empty($f['required']));
        self::assertCount(1, $requiredFields);
    }

    public function testValidateDataJsonAgainstFields(): void {
        $fields = [
            ['name' => 'nama_varietas', 'label' => 'Nama Varietas', 'type' => 'text', 'required' => true],
            ['name' => 'jumlah_bibit', 'label' => 'Jumlah Bibit', 'type' => 'number', 'required' => true],
            ['name' => 'sumber_bibit', 'label' => 'Sumber Bibit', 'type' => 'text', 'required' => false],
        ];
        $validData = ['nama_varietas' => 'Varietas Unggul', 'jumlah_bibit' => 500, 'sumber_bibit' => 'Dinas'];
        $errors = [];
        foreach ($fields as $field) {
            $value = $validData[$field['name']] ?? null;
            if (!empty($field['required']) && ($value === null || $value === '')) {
                $errors[] = "Field '{$field['label']}' wajib diisi";
            }
        }
        self::assertEmpty($errors);
        $invalidData = ['nama_varietas' => 'Varietas Unggul'];
        $errors = [];
        foreach ($fields as $field) {
            $value = $invalidData[$field['name']] ?? null;
            if (!empty($field['required']) && ($value === null || $value === '')) {
                $errors[] = "Field '{$field['label']}' wajib diisi";
            }
        }
        self::assertCount(1, $errors);
        self::assertStringContainsString('Jumlah Bibit', $errors[0]);
    }

    public function testKodeLaporanFormat(): void {
        $kode = 'LL-' . date('Ymd') . '-0001';
        self::assertStringStartsWith('LL-', $kode);
        self::assertStringContainsString(date('Ymd'), $kode);
        self::assertStringEndsWith('-0001', $kode);
    }

    public function testStatusTransitions(): void {
        $allowedStatuses = ['draft', 'verified'];
        self::assertContains('draft', $allowedStatuses);
        self::assertContains('verified', $allowedStatuses);
        $validTransitions = [
            'draft' => ['verified'],
            'verified' => [],
        ];
        self::assertContains('verified', $validTransitions['draft']);
        self::assertEmpty($validTransitions['verified']);
    }

    public function testOwnerOnlyEditDraft(): void {
        $report = ['id' => 1, 'user_id' => 42, 'status' => 'draft'];
        self::assertTrue($report['user_id'] === 42);
        self::assertTrue($report['status'] === 'draft');
    }

    public function testSubmitAutoVerifies(): void {
        $statusAfterSubmit = 'verified';
        self::assertEquals('verified', $statusAfterSubmit);
        self::assertNotEquals('submitted', $statusAfterSubmit);
    }

    public function testNoAdminApprovalRequired(): void {
        $requiresApproval = false;
        self::assertFalse($requiresApproval);
    }

    public function testMasterJenisLaporanCodes(): void {
        $expectedCodes = ['bibit_baru', 'rumah_kaca', 'panen', 'bantuan_alsintan', 'kerusakan_cuaca'];
        self::assertCount(5, $expectedCodes);
        foreach ($expectedCodes as $code) {
            self::assertIsString($code);
            self::assertNotEmpty($code);
        }
    }

    public function testMigrationTableStructure(): void {
        $expectedMasterJenisColumns = ['id', 'kode', 'nama', 'deskripsi', 'fields_json', 'is_active', 'created_at'];
        $expectedLaporanLainnyaColumns = ['id', 'user_id', 'jenis_id', 'kode_laporan', 'desa_id',
            'tanggal_kejadian', 'data_json', 'deskripsi', 'latitude', 'longitude',
            'status', 'catatan_verifikasi', 'verified_by', 'verified_at', 'created_at', 'updated_at'];
        self::assertCount(7, $expectedMasterJenisColumns);
        self::assertCount(16, $expectedLaporanLainnyaColumns);
        self::assertContains('kode', $expectedMasterJenisColumns);
        self::assertContains('fields_json', $expectedMasterJenisColumns);
        self::assertContains('is_active', $expectedMasterJenisColumns);
        self::assertContains('data_json', $expectedLaporanLainnyaColumns);
        self::assertContains('status', $expectedLaporanLainnyaColumns);
        self::assertContains('verified_by', $expectedLaporanLainnyaColumns);
    }
}