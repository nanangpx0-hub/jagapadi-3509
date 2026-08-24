<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/DuplicateMasterException.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';
require_once ROOT_PATH . '/app/services/UsulanOptReviewService.php';
require_once ROOT_PATH . '/app/services/UsulanOptService.php';

final class UsulanOptServicePayloadTest extends TestCase
{
    private UsulanOptService $service;

    protected function setUp(): void
    {
        $this->service = new UsulanOptService();
    }

    public function testNormalizeTrimsNullifiesAndCasts(): void
    {
        $data = $this->service->normalize([
            'nama_lokal' => '  Ulat daun  ',
            'nama_nasional' => '',
            'jenis' => ' HAMA ',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Daun berlubang',
            'tanggal_ditemukan' => '2026-08-24',
            'kabupaten_id' => '1',
            'kecamatan_id' => '2',
            'desa_id' => 'abc',
            'latitude' => '-8.1',
            'longitude' => '',
            'estimasi_terdampak' => '2.5',
            'tingkat_keyakinan' => ' Tinggi ',
        ]);

        self::assertSame('Ulat daun', $data['nama_lokal']);
        self::assertNull($data['nama_nasional']);
        self::assertSame('hama', $data['jenis']);
        self::assertSame('2026-08-24', $data['tanggal_ditemukan']);
        self::assertSame(1, $data['kabupaten_id']);
        self::assertSame(2, $data['kecamatan_id']);
        self::assertNull($data['desa_id'], 'ID non-numerik harus menjadi null');
        self::assertSame(-8.1, $data['latitude']);
        self::assertNull($data['longitude']);
        self::assertSame(2.5, $data['estimasi_terdampak']);
        self::assertSame('Tinggi', $data['tingkat_keyakinan']);
    }

    public function testNormalizeDropsAdministrativeFieldsEntirely(): void
    {
        $data = $this->service->normalize([
            'user_id' => '999',
            'status' => 'Disetujui',
            'reviewed_by' => '42',
            'reviewed_at' => '2020-01-01 00:00:00',
            'master_opt_id' => '7',
            'nama_lokal' => 'Uji',
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Ciri',
        ]);

        self::assertArrayNotHasKey('user_id', $data);
        self::assertArrayNotHasKey('status', $data);
        self::assertArrayNotHasKey('reviewed_by', $data);
        self::assertArrayNotHasKey('reviewed_at', $data);
        self::assertArrayNotHasKey('master_opt_id', $data);
    }

    public function testNormalizeRejectsInvalidDateAndKeyakinan(): void
    {
        $data = $this->service->normalize([
            'tanggal_ditemukan' => '31/12/2026',
            'tingkat_keyakinan' => 'Pasti',
            'nama_lokal' => 'x',
            'jenis' => 'hama',
            'komoditas' => 'y',
            'ciri_ciri' => 'z',
        ]);

        self::assertNull($data['tanggal_ditemukan']);
        self::assertNull($data['tingkat_keyakinan']);
    }

    public function testValidateRequiresCoreFieldsAndEnum(): void
    {
        $errors = $this->service->validate($this->service->normalize([]));

        self::assertContains('Nama lokal/daerah wajib diisi', $errors);
        self::assertContains('Jenis usulan wajib dipilih', $errors);
        self::assertContains('Komoditas yang diserang wajib diisi', $errors);
        self::assertContains('Ciri-ciri/gejala wajib diisi', $errors);
        self::assertContains('Tanggal ditemukan wajib diisi (format YYYY-MM-DD)', $errors);
    }

    public function testValidateDistinguishesMissingAndUnsupportedJenis(): void
    {
        $errors = $this->service->validate($this->service->normalize([
            'nama_lokal' => 'Cekaman kekeringan',
            'jenis' => 'faktor_abiotik',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Daun menggulung dan mengering',
            'tanggal_ditemukan' => date('Y-m-d'),
        ]));

        self::assertContains(
            'Jenis usulan "faktor_abiotik" tidak valid. Gunakan: hama, penyakit, atau gulma',
            $errors
        );
        self::assertNotContains('Jenis usulan wajib dipilih', $errors);
    }

    public function testValidateRejectsFutureDateAndBadCoordinates(): void
    {
        $base = [
            'nama_lokal' => 'Ulat uji',
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Gejala uji panjang agar lolos batas minimum.',
        ];

        $future = $this->service->validate(array_merge(
            $this->service->normalize($base + ['tanggal_ditemukan' => date('Y-m-d', strtotime('+3 days'))]),
            []
        ));
        self::assertContains('Tanggal ditemukan tidak boleh di masa depan', $future);

        $latOnly = $this->service->validate($this->service->normalize($base + [
            'tanggal_ditemukan' => date('Y-m-d'),
            'latitude' => '-8.1',
        ]));
        self::assertContains('Latitude dan longitude harus diisi keduanya atau dikosongkan keduanya', $latOnly);

        $outOfRange = $this->service->validate($this->service->normalize($base + [
            'tanggal_ditemukan' => date('Y-m-d'),
            'latitude' => '-95',
            'longitude' => '999',
        ]));
        self::assertContains('Latitude harus di antara -90 dan 90', $outOfRange);
        self::assertContains('Longitude harus di antara -180 dan 180', $outOfRange);
    }

    public function testValidateEnforcesTextLengthLimits(): void
    {
        $errors = $this->service->validate($this->service->normalize([
            'nama_lokal' => str_repeat('A', 201),
            'nama_nasional' => str_repeat('B', 151),
            'jenis' => 'hama',
            'komoditas' => str_repeat('C', 151),
            'ciri_ciri' => str_repeat('D', 5001),
        ]));

        self::assertContains('Nama lokal maksimal 200 karakter', $errors);
        self::assertContains('Nama nasional maksimal 150 karakter', $errors);
        self::assertContains('Komoditas maksimal 150 karakter', $errors);
        self::assertContains('Ciri-ciri maksimal 5000 karakter', $errors);
    }

    public function testValidateForSubmitDemandsWilayahBeforeDatabaseLookup(): void
    {
        $complete = $this->service->normalize([
            'nama_lokal' => 'Ulat uji',
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Gejala uji.',
            'tanggal_ditemukan' => date('Y-m-d'),
        ]);

        $errors = $this->service->validate($complete, true);

        self::assertContains('Kabupaten wajib dipilih saat mengirim review', $errors);
        self::assertContains('Kecamatan wajib dipilih saat mengirim review', $errors);
        self::assertContains('Desa wajib dipilih saat mengirim review', $errors);
    }

    public function testValidateNegativeEstimateAndSatuanPairing(): void
    {
        $base = [
            'nama_lokal' => 'Ulat uji',
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Gejala.',
            'tanggal_ditemukan' => date('Y-m-d'),
        ];

        $negative = $this->service->validate($this->service->normalize($base + ['estimasi_terdampak' => '-1']));
        self::assertContains('Perkiraan luas/jumlah terdampak tidak boleh negatif', $negative);

        $satuanOnly = $this->service->validate($this->service->normalize($base + ['satuan_terdampak' => 'ha']));
        self::assertContains(
            'Satuan terdampak wajib diisi bila perkiraan terdampak diisi, dan sebaliknya',
            $satuanOnly
        );
    }

    public function testStateMachineConstantsMatchDatabaseEnum(): void
    {
        $expected = ['Draf', 'Menunggu Review', 'Perlu Perbaikan', 'Disetujui', 'Digabungkan', 'Ditolak Permanen'];
        self::assertSame($expected, UsulanOpt::STATUSES, 'Konstanta status harus sinkron dengan ENUM migration');
        self::assertSame(['Draf', 'Perlu Perbaikan'], UsulanOpt::OWNER_EDITABLE);
    }

    public function testAdminImportHasDedicatedPendingCreationPath(): void
    {
        $source = file_get_contents(ROOT_PATH . '/app/services/UsulanOptService.php');
        self::assertStringContainsString('public function createPendingAdminImport(', $source);
        self::assertStringContainsString("'admin_excel_import_pending'", $source);

        $controller = file_get_contents(ROOT_PATH . '/app/controllers/UsulanOptController.php');
        self::assertStringContainsString("(\$_SESSION['role'] ?? '') === 'admin'", $controller);
        self::assertStringContainsString('createPendingAdminImport($ownerId, $data, $ownerId)', $controller);
    }
}
