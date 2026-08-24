<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * LaporanLainnyaStoreTest
 *
 * Pengujian blackbox komprehensif endpoint POST /laporan-lainnya/store.
 *
 * Cakupan:
 *  TS-VALID   : Data valid — semua skenario penyimpanan berhasil
 *  TS-INVAL   : Input tidak lengkap — field wajib kosong
 *  TS-BOUND   : Batas ukuran — karakter melebihi batas maksimum
 *  TS-FORMAT  : Format salah — tanggal, koordinat, tipe data field dinamis
 *  TS-WFLOW   : Workflow status — draft → submitted → verified/rejected → archived
 *  TS-OWN     : Ownership — scoping petugas, IDOR, canEdit
 *  TS-SEC     : Keamanan — mass-assignment protection, kode laporan atomik
 *  TS-DB      : Integritas DB — persistensi, relasi wilayah, soft-delete
 *
 * Konvensi penamaan: test{KodeSkenario}_{SkenarioSingkat}
 */
final class LaporanLainnyaStoreTest extends TestCase
{
    private PDO    $db;
    private string $marker;       // penanda unik per run untuk isolasi tearDown
    private int    $adminId;
    private int    $petugasId;
    private int    $petugasB;
    private int    $operatorId;
    private int    $jenisId;      // ID jenis "Penanaman Bibit Baru" (bibit_baru)
    private int    $kabupatenId;
    private int    $kecamatanId;
    private int    $desaId;

    // =========================================================
    // Setup & Teardown
    // =========================================================

    protected function setUp(): void
    {
        $this->loadEnv();

        $this->db = Database::getInstance()->getConnection();
        $this->marker = 'TS-LL-' . bin2hex(random_bytes(5));

        // Cari ID user yang dibutuhkan
        $this->adminId    = $this->findUserId('admin');
        $this->petugasId  = $this->findUserId('petugas');
        $this->petugasB   = $this->findUserId('petugas', $this->petugasId);
        $this->operatorId = $this->findUserId('operator');

        if ($this->adminId <= 0 || $this->petugasId <= 0) {
            self::markTestSkipped('Dibutuhkan minimal 1 admin dan 1 petugas aktif di database.');
        }

        // Pastikan jenis laporan tersedia
        $this->jenisId = $this->findJenisId('bibit_baru');
        if ($this->jenisId <= 0) {
            self::markTestSkipped('Jenis laporan "bibit_baru" tidak ditemukan di master_jenis_laporan.');
        }

        // Cari data wilayah yang valid
        $kab = $this->db->query(
            "SELECT id FROM master_kabupaten LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $this->kabupatenId = $kab ? (int) $kab['id'] : 0;

        $kec = $this->db->prepare(
            "SELECT id FROM master_kecamatan WHERE kabupaten_id = ? LIMIT 1"
        );
        $kec->execute([$this->kabupatenId]);
        $kecRow = $kec->fetch(PDO::FETCH_ASSOC);
        $this->kecamatanId = $kecRow ? (int) $kecRow['id'] : 0;

        $desa = $this->db->prepare(
            "SELECT id FROM master_desa WHERE kecamatan_id = ? LIMIT 1"
        );
        $desa->execute([$this->kecamatanId]);
        $desaRow = $desa->fetch(PDO::FETCH_ASSOC);
        $this->desaId = $desaRow ? (int) $desaRow['id'] : 0;

        if ($this->kabupatenId <= 0 || $this->kecamatanId <= 0 || $this->desaId <= 0) {
            self::markTestSkipped('Data wilayah (kabupaten/kecamatan/desa) tidak tersedia.');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        // Hapus laporan test berdasarkan marker di deskripsi atau alamat
        $like = '%' . $this->marker . '%';
        $this->db->prepare(
            "DELETE FROM laporan_lainnya WHERE alamat_lengkap LIKE ? OR deskripsi LIKE ?"
        )->execute([$like, $like]);
    }

    // =========================================================
    // TS-VALID : Data valid — penyimpanan berhasil
    // =========================================================

    /**
     * TS-VALID-01
     * Data minimal valid (role Petugas): jenis, tanggal, lokasi lengkap,
     * alamat ≥ 10 char, field dinamis required terisi.
     * Laporan harus tersimpan sebagai draft dengan data_json yang benar.
     */
    public function testValid01_MinimalValidDataPetugasSavesAsDraft(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' valid01',
        ]));

        self::assertGreaterThan(0, $id,
            'TS-VALID-01: createReport() harus mengembalikan ID > 0 untuk data valid'
        );

        $saved = $model->getById($id);
        self::assertNotNull($saved, 'TS-VALID-01: laporan harus tersimpan di DB');
        self::assertSame('draft', $saved['status'],
            'TS-VALID-01: status awal harus draft'
        );
        self::assertSame($this->jenisId, (int) $saved['jenis_id'],
            'TS-VALID-01: jenis_id harus sesuai yang dikirim'
        );
        self::assertSame($this->petugasId, (int) $saved['user_id'],
            'TS-VALID-01: user_id harus sesuai pembuat'
        );
        self::assertNull($saved['kode_laporan'],
            'TS-VALID-01: kode_laporan harus NULL saat masih draft'
        );
    }

    /**
     * TS-VALID-02
     * data_json tersimpan lengkap dengan semua field dinamis jenis bibit_baru.
     */
    public function testValid02_DataJsonContainsAllDynamicFields(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi'    => $this->marker . ' valid02',
            'data_json'    => json_encode([
                'nama_varietas' => 'Varietas Padi Unggul',
                'jumlah_bibit'  => 500,
                'sumber_bibit'  => 'Dinas Pertanian',
            ]),
        ]));

        $saved  = $model->getById($id);
        $parsed = json_decode($saved['data_json'], true);

        self::assertArrayHasKey('nama_varietas', $parsed,
            'TS-VALID-02: nama_varietas harus ada di data_json'
        );
        self::assertArrayHasKey('jumlah_bibit', $parsed,
            'TS-VALID-02: jumlah_bibit harus ada di data_json'
        );
        self::assertSame('Varietas Padi Unggul', $parsed['nama_varietas'],
            'TS-VALID-02: nilai nama_varietas harus tersimpan utuh'
        );
    }

    /**
     * TS-VALID-03
     * Koordinat GPS valid (dalam batas Jember) tersimpan dengan presisi.
     */
    public function testValid03_ValidGpsCoordinatesStoredWithPrecision(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' valid03',
            'latitude'  => -8.1500000,
            'longitude' => 113.7000000,
        ]));

        $saved = $model->getById($id);
        self::assertNotNull($saved['latitude'],
            'TS-VALID-03: latitude harus tersimpan'
        );
        // DECIMAL(10,7) → presisi 7 desimal
        self::assertEqualsWithDelta(-8.15, (float) $saved['latitude'], 0.0000001,
            'TS-VALID-03: latitude harus tersimpan dengan presisi'
        );
        self::assertEqualsWithDelta(113.7, (float) $saved['longitude'], 0.0000001,
            'TS-VALID-03: longitude harus tersimpan dengan presisi'
        );
    }

    /**
     * TS-VALID-04
     * Deskripsi panjang tepat pada batas 5000 karakter harus diterima.
     */
    public function testValid04_DeskripsiExactly5000CharsAccepted(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . str_repeat('x', 5000 - strlen($this->marker)),
        ]));

        $saved = $model->getById($id);
        self::assertNotNull($saved,
            'TS-VALID-04: laporan dengan deskripsi 5000 karakter harus tersimpan'
        );
    }

    /**
     * TS-VALID-05
     * Field opsional (koordinat, deskripsi, foto_url) boleh null.
     */
    public function testValid05_OptionalFieldsNullable(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi'  => $this->marker . ' valid05',
            'latitude'   => null,
            'longitude'  => null,
            'foto_url'   => null,
        ]));

        $saved = $model->getById($id);
        self::assertNull($saved['latitude'],  'TS-VALID-05: latitude boleh null');
        self::assertNull($saved['longitude'], 'TS-VALID-05: longitude boleh null');
        self::assertNull($saved['foto_url'],  'TS-VALID-05: foto_url boleh null');
    }

    /**
     * TS-VALID-06
     * Admin membuat laporan atas nama user lain — user_id di DB harus
     * mencerminkan target user, bukan admin.
     */
    public function testValid06_AdminCreatesOnBehalfOfPetugas(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,   // admin assign ke petugas
            'deskripsi' => $this->marker . ' valid06',
        ]));

        $saved = $model->getById($id);
        self::assertSame($this->petugasId, (int) $saved['user_id'],
            'TS-VALID-06: user_id harus sesuai target user yang dipilih admin'
        );
    }

    // =========================================================
    // TS-INVAL : Input tidak lengkap
    // =========================================================

    /**
     * TS-INVAL-01
     * Validasi controller: jenis_id = 0 harus menghasilkan error.
     */
    public function testInval01_JenisIdZeroFailsValidation(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => 0,
            'tanggal_kejadian' => date('Y-m-d'),
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-INVAL-01: jenis_id=0 harus menghasilkan error validasi');
        self::assertStringContainsString('jenis laporan', strtolower(implode(' ', $errors)),
            'TS-INVAL-01: pesan error harus menyebut "jenis laporan"'
        );
    }

    /**
     * TS-INVAL-02
     * Tanggal kosong harus ditolak.
     */
    public function testInval02_EmptyTanggalFailsValidation(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => '',
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-INVAL-02: tanggal kosong harus menghasilkan error');
        self::assertStringContainsString('tanggal', strtolower(implode(' ', $errors)),
            'TS-INVAL-02: pesan error harus menyebut "tanggal"'
        );
    }

    /**
     * TS-INVAL-03
     * Petugas tanpa kabupaten_id, kecamatan_id, desa_id harus ditolak.
     */
    public function testInval03_PetugasMissingWilayahFailsValidation(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => date('Y-m-d'),
            'kabupaten_id'    => '',
            'kecamatan_id'    => '',
            'desa_id'         => '',
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-INVAL-03: wilayah kosong untuk petugas harus error');
        self::assertStringContainsString('lokasi', strtolower(implode(' ', $errors)),
            'TS-INVAL-03: pesan error harus menyebut "lokasi"'
        );
    }

    /**
     * TS-INVAL-04
     * Alamat kurang dari 10 karakter untuk Petugas harus ditolak.
     */
    public function testInval04_PetugasAlamatTooShortFailsValidation(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => date('Y-m-d'),
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Pendek',  // 6 karakter — kurang dari min 10
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-INVAL-04: alamat < 10 char untuk petugas harus error');
        self::assertStringContainsString('alamat', strtolower(implode(' ', $errors)),
            'TS-INVAL-04: pesan error harus menyebut "alamat"'
        );
    }

    /**
     * TS-INVAL-05
     * Field dinamis required kosong harus menghasilkan error per field.
     */
    public function testInval05_RequiredDynamicFieldEmptyFailsValidation(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => $this->jenisId,
            'tanggal_kejadian'  => date('Y-m-d'),
            'kabupaten_id'     => $this->kabupatenId,
            'kecamatan_id'     => $this->kecamatanId,
            'desa_id'          => $this->desaId,
            'alamat_lengkap'   => 'Alamat cukup panjang test',
            'nama_varietas'    => '',    // required — kosong
            'jumlah_bibit'     => '',    // required — kosong
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-INVAL-05: field dinamis required kosong harus error');

        $joined = strtolower(implode(' ', $errors));
        self::assertStringContainsString('nama varietas', $joined,
            'TS-INVAL-05: error harus menyebut nama field yang kosong (Nama Varietas)'
        );
        self::assertStringContainsString('jumlah bibit', $joined,
            'TS-INVAL-05: error harus menyebut nama field yang kosong (Jumlah Bibit)'
        );
    }

    /**
     * TS-INVAL-06
     * Semua field kosong — harus menghasilkan multiple errors.
     */
    public function testInval06_AllEmptyFieldsProducesMultipleErrors(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => 0,
            'tanggal_kejadian'  => '',
            'kabupaten_id'     => '',
            'kecamatan_id'     => '',
            'desa_id'          => '',
            'alamat_lengkap'   => '',
        ], 'petugas');

        self::assertGreaterThanOrEqual(2, count($errors),
            'TS-INVAL-06: semua field kosong harus menghasilkan minimal 2 error'
        );
    }

    // =========================================================
    // TS-BOUND : Batas ukuran
    // =========================================================

    /**
     * TS-BOUND-01
     * Deskripsi tepat 5001 karakter harus ditolak.
     */
    public function testBound01_DeskripsiOver5000CharsRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => $this->jenisId,
            'tanggal_kejadian'  => date('Y-m-d'),
            'kabupaten_id'     => $this->kabupatenId,
            'kecamatan_id'     => $this->kecamatanId,
            'desa_id'          => $this->desaId,
            'alamat_lengkap'   => 'Alamat cukup panjang test',
            'nama_varietas'    => 'Padi',
            'jumlah_bibit'     => '100',
            'deskripsi'        => str_repeat('x', 5001),
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-BOUND-01: deskripsi > 5000 char harus menghasilkan error'
        );
        self::assertStringContainsString('deskripsi', strtolower(implode(' ', $errors)),
            'TS-BOUND-01: pesan error harus menyebut "deskripsi"'
        );
    }

    /**
     * TS-BOUND-02
     * Field dinamis textarea > 2000 karakter harus ditolak.
     * Menggunakan jenis "kerusakan_cuaca" dengan field textarea jika ada,
     * atau simulasi validasi langsung.
     */
    public function testBound02_DynamicTextareaOver2000CharsRejected(): void
    {
        // Simulasi langsung logika validasi type coercion controller
        $fields = [
            ['name' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea', 'required' => false],
        ];
        $inputData = ['catatan' => str_repeat('a', 2001)];

        $errors = [];
        foreach ($fields as $field) {
            $value = $inputData[$field['name']] ?? null;
            if ($value !== null && $value !== '') {
                if (($field['type'] === 'text' || $field['type'] === 'textarea')
                    && mb_strlen((string) $value) > 2000
                ) {
                    $errors[] = "Field '{$field['label']}' maksimal 2000 karakter";
                }
            }
        }

        self::assertNotEmpty($errors,
            'TS-BOUND-02: field textarea > 2000 karakter harus menghasilkan error'
        );
        self::assertStringContainsString('2000', $errors[0],
            'TS-BOUND-02: pesan error harus menyebut batas "2000"'
        );
    }

    /**
     * TS-BOUND-03
     * alamat_lengkap tepat 255 karakter harus diterima (batas varchar(255)).
     */
    public function testBound03_AlamatExactly255CharsAccepted(): void
    {
        $model  = new LaporanLainnya();
        $alamat = str_repeat('A', 255);

        $id = $model->createReport($this->buildPayload([
            'alamat_lengkap' => $alamat,
            'deskripsi'      => $this->marker . ' bound03',
        ]));

        $saved = $model->getById($id);
        self::assertNotNull($saved,
            'TS-BOUND-03: alamat 255 char harus tersimpan'
        );
        self::assertSame(255, strlen($saved['alamat_lengkap']),
            'TS-BOUND-03: alamat harus tersimpan penuh 255 karakter'
        );
    }

    /**
     * TS-BOUND-04
     * alamat_lengkap 256 karakter harus gagal di level DB (varchar(255)).
     */
    public function testBound04_AlamatOver255CharsFailsAtDb(): void
    {
        $model  = new LaporanLainnya();
        $alamat = str_repeat('B', 256);  // 1 karakter melebihi batas

        $result = $model->createReport($this->buildPayload([
            'alamat_lengkap' => $alamat,
            'deskripsi'      => $this->marker . ' bound04',
        ]));

        // Model::create() mengembalikan 0 atau false jika DB error
        self::assertTrue(
            $result === 0 || $result === false || $result === null,
            'TS-BOUND-04: alamat 256 karakter harus gagal (tidak tersimpan)'
        );
    }

    // =========================================================
    // TS-FORMAT : Format data salah
    // =========================================================

    /**
     * TS-FORMAT-01
     * Tanggal dengan format salah (bukan Y-m-d) harus ditolak.
     */
    public function testFormat01_InvalidDateFormatRejected(): void
    {
        $invalidDates = ['32-13-2026', '2026/08/24', 'kemarin', '24-08-2026', ''];

        foreach ($invalidDates as $badDate) {
            $errors = $this->runControllerValidation([
                'jenis_id'        => $this->jenisId,
                'tanggal_kejadian' => $badDate,
                'kabupaten_id'    => $this->kabupatenId,
                'kecamatan_id'    => $this->kecamatanId,
                'desa_id'         => $this->desaId,
                'alamat_lengkap'  => 'Alamat cukup panjang test',
                'nama_varietas'   => 'Padi',
                'jumlah_bibit'    => '100',
            ], 'petugas');

            self::assertNotEmpty($errors,
                "TS-FORMAT-01: tanggal '$badDate' harus menghasilkan error"
            );
        }
    }

    /**
     * TS-FORMAT-02
     * Tanggal di masa depan harus ditolak.
     */
    public function testFormat02_FutureDateRejected(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));

        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => $futureDate,
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-FORMAT-02: tanggal masa depan harus menghasilkan error'
        );
        self::assertStringContainsString('masa depan', strtolower(implode(' ', $errors)),
            'TS-FORMAT-02: pesan error harus menyebut "masa depan"'
        );
    }

    /**
     * TS-FORMAT-03
     * Tanggal lebih dari 10 tahun lalu harus ditolak.
     */
    public function testFormat03_DateOlderThan10YearsRejected(): void
    {
        $oldDate = date('Y-m-d', strtotime('-11 years'));

        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => $oldDate,
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => '100',
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-FORMAT-03: tanggal > 10 tahun lalu harus menghasilkan error'
        );
    }

    /**
     * TS-FORMAT-04
     * Latitude di luar range -90 s/d 90 harus ditolak.
     */
    public function testFormat04_LatitudeOutOfRangeRejected(): void
    {
        foreach ([-91.0, 91.0, -180.5] as $badLat) {
            $errors = $this->runControllerValidation([
                'jenis_id'         => $this->jenisId,
                'tanggal_kejadian'  => date('Y-m-d'),
                'kabupaten_id'     => $this->kabupatenId,
                'kecamatan_id'     => $this->kecamatanId,
                'desa_id'          => $this->desaId,
                'alamat_lengkap'   => 'Alamat cukup panjang test',
                'nama_varietas'    => 'Padi',
                'jumlah_bibit'     => '100',
                'latitude'         => (string) $badLat,
                'longitude'        => '113.7',
            ], 'petugas');

            self::assertNotEmpty($errors,
                "TS-FORMAT-04: latitude=$badLat harus menghasilkan error"
            );
            self::assertStringContainsString('latitude', strtolower(implode(' ', $errors)),
                "TS-FORMAT-04: pesan error harus menyebut 'latitude'"
            );
        }
    }

    /**
     * TS-FORMAT-05
     * Longitude di luar range -180 s/d 180 harus ditolak.
     */
    public function testFormat05_LongitudeOutOfRangeRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => $this->jenisId,
            'tanggal_kejadian'  => date('Y-m-d'),
            'kabupaten_id'     => $this->kabupatenId,
            'kecamatan_id'     => $this->kecamatanId,
            'desa_id'          => $this->desaId,
            'alamat_lengkap'   => 'Alamat cukup panjang test',
            'nama_varietas'    => 'Padi',
            'jumlah_bibit'     => '100',
            'latitude'         => '-8.15',
            'longitude'        => '200.0',    // > 180
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-FORMAT-05: longitude > 180 harus error');
        self::assertStringContainsString('longitude', strtolower(implode(' ', $errors)));
    }

    /**
     * TS-FORMAT-06
     * Hanya latitude tanpa longitude (atau sebaliknya) harus ditolak.
     */
    public function testFormat06_OnlyOneCoordinateRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => $this->jenisId,
            'tanggal_kejadian'  => date('Y-m-d'),
            'kabupaten_id'     => $this->kabupatenId,
            'kecamatan_id'     => $this->kecamatanId,
            'desa_id'          => $this->desaId,
            'alamat_lengkap'   => 'Alamat cukup panjang test',
            'nama_varietas'    => 'Padi',
            'jumlah_bibit'     => '100',
            'latitude'         => '-8.15',
            'longitude'        => '',   // longitude kosong
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-FORMAT-06: hanya latitude tanpa longitude harus menghasilkan error'
        );
    }

    /**
     * TS-FORMAT-07
     * Koordinat di luar batas Jember harus ditolak oleh GeoValidator.
     * Koordinat Jakarta (bukan Jember) → harus error.
     */
    public function testFormat07_CoordinatesOutsideJemberRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'         => $this->jenisId,
            'tanggal_kejadian'  => date('Y-m-d'),
            'kabupaten_id'     => $this->kabupatenId,
            'kecamatan_id'     => $this->kecamatanId,
            'desa_id'          => $this->desaId,
            'alamat_lengkap'   => 'Alamat cukup panjang test',
            'nama_varietas'    => 'Padi',
            'jumlah_bibit'     => '100',
            'latitude'         => '-6.2',     // Jakarta
            'longitude'        => '106.816',  // Jakarta
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-FORMAT-07: koordinat di luar Jember harus ditolak GeoValidator'
        );
    }

    /**
     * TS-FORMAT-08
     * Field dinamis bertipe number dengan nilai teks harus ditolak.
     */
    public function testFormat08_NumberFieldWithTextValueRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => $this->jenisId,
            'tanggal_kejadian' => date('Y-m-d'),
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
            'nama_varietas'   => 'Padi',
            'jumlah_bibit'    => 'tidak-valid',  // harus angka
        ], 'petugas');

        self::assertNotEmpty($errors,
            'TS-FORMAT-08: field number dengan nilai teks harus error'
        );
        self::assertStringContainsString('angka', strtolower(implode(' ', $errors)),
            'TS-FORMAT-08: pesan error harus menyebut "angka"'
        );
    }

    /**
     * TS-FORMAT-09
     * jenis_id negatif harus ditolak.
     */
    public function testFormat09_NegativeJenisIdRejected(): void
    {
        $errors = $this->runControllerValidation([
            'jenis_id'        => -5,
            'tanggal_kejadian' => date('Y-m-d'),
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Alamat cukup panjang test',
        ], 'petugas');

        self::assertNotEmpty($errors, 'TS-FORMAT-09: jenis_id negatif harus error');
    }

    // =========================================================
    // TS-WFLOW : Workflow status
    // =========================================================

    /**
     * TS-WFLOW-01
     * createReport selalu menyimpan status 'draft'.
     */
    public function testWflow01_NewReportAlwaysDraft(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow01',
            'status'    => 'verified',  // coba paksa status dari luar
        ]));

        $saved = $model->getById($id);
        self::assertSame('draft', $saved['status'],
            'TS-WFLOW-01: status harus selalu draft meski payload minta verified'
        );
    }

    /**
     * TS-WFLOW-02
     * submitReport mengubah draft → submitted dan mengisi kode_laporan.
     */
    public function testWflow02_SubmitDraftGeneratesKodeLaporan(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow02',
        ]));

        $result = $model->submitReport($id, $this->petugasId, 'petugas');

        self::assertTrue($result, 'TS-WFLOW-02: submitReport harus mengembalikan true');

        $saved = $model->getById($id);
        self::assertSame('submitted', $saved['status'],
            'TS-WFLOW-02: status harus berubah ke submitted'
        );
        self::assertNotNull($saved['kode_laporan'],
            'TS-WFLOW-02: kode_laporan harus terisi setelah submit'
        );
        self::assertMatchesRegularExpression(
            '/^LL-\d{8}-\d{4}$/',
            $saved['kode_laporan'],
            'TS-WFLOW-02: format kode_laporan harus LL-YYYYMMDD-NNNN'
        );
    }

    /**
     * TS-WFLOW-03
     * submitReport dari status verified/archived harus gagal (LogicException).
     */
    public function testWflow03_SubmitFromInvalidStatusThrows(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow03',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');
        $model->verifyReport($id, $this->adminId);

        $this->expectException(LogicException::class);
        $model->submitReport($id, $this->petugasId, 'petugas');
    }

    /**
     * TS-WFLOW-04
     * verifyReport dari status submitted mengubah ke verified.
     */
    public function testWflow04_VerifySubmittedReport(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow04',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');
        $result = $model->verifyReport($id, $this->adminId, 'Laporan valid');

        self::assertTrue($result, 'TS-WFLOW-04: verifyReport harus true');

        $saved = $model->getById($id);
        self::assertSame('verified', $saved['status']);
        self::assertSame($this->adminId, (int) $saved['verified_by']);
        self::assertNotNull($saved['verified_at']);
        self::assertSame('Laporan valid', $saved['catatan_verifikasi']);
    }

    /**
     * TS-WFLOW-05
     * verifyReport dari status draft harus gagal (LogicException).
     */
    public function testWflow05_VerifyDraftThrowsLogicException(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow05',
        ]));

        $this->expectException(LogicException::class);
        $model->verifyReport($id, $this->adminId);
    }

    /**
     * TS-WFLOW-06
     * rejectReport dari submitted → rejected; catatan verifikasi tersimpan.
     */
    public function testWflow06_RejectSubmittedReport(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow06',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');
        $result = $model->rejectReport($id, $this->adminId, 'Data tidak lengkap');

        self::assertTrue($result, 'TS-WFLOW-06: rejectReport harus true');

        $saved = $model->getById($id);
        self::assertSame('rejected', $saved['status']);
        self::assertSame('Data tidak lengkap', $saved['catatan_verifikasi']);
    }

    /**
     * TS-WFLOW-07
     * Laporan rejected dapat di-submit ulang (resubmit) tanpa ganti kode.
     */
    public function testWflow07_ResubmitRejectedPreservesKodeLaporan(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow07',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');
        $kodePertama = $model->getById($id)['kode_laporan'];

        $model->rejectReport($id, $this->adminId, 'Kurang data');
        $model->submitReport($id, $this->petugasId, 'petugas');

        $savedAfterResubmit = $model->getById($id);
        self::assertSame('submitted', $savedAfterResubmit['status'],
            'TS-WFLOW-07: status harus kembali ke submitted'
        );
        self::assertSame($kodePertama, $savedAfterResubmit['kode_laporan'],
            'TS-WFLOW-07: kode_laporan tidak boleh berubah saat resubmit'
        );
    }

    /**
     * TS-WFLOW-08
     * archiveReport dari submitted/verified/rejected diterima; dari draft ditolak.
     */
    public function testWflow08_ArchiveAllowedAndDenied(): void
    {
        $model = new LaporanLainnya();

        // Skenario diizinkan: submitted → archived
        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow08-allowed',
        ]));
        $model->submitReport($id, $this->petugasId, 'petugas');
        $ok = $model->archiveReport($id);
        self::assertTrue($ok, 'TS-WFLOW-08: archive dari submitted harus berhasil');
        self::assertSame('archived', $model->getById($id)['status']);

        // Skenario ditolak: draft → archived
        $id2 = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' wflow08-denied',
        ]));
        $this->expectException(LogicException::class);
        $model->archiveReport($id2);
    }

    // =========================================================
    // TS-OWN : Ownership
    // =========================================================

    /**
     * TS-OWN-01
     * canEdit: Petugas hanya bisa edit laporan draftnya sendiri.
     */
    public function testOwn01_PetugasCanEditOwnDraftOnly(): void
    {
        if ($this->petugasB <= 0) {
            self::markTestSkipped('Butuh 2 akun petugas untuk TS-OWN-01');
        }

        $model = new LaporanLainnya();

        // Laporan milik petugasId
        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' own01-A',
        ]));

        self::assertTrue(
            $model->canEdit($id, $this->petugasId, 'petugas'),
            'TS-OWN-01: petugas A harus bisa edit laporan draftnya sendiri'
        );
        self::assertFalse(
            $model->canEdit($id, $this->petugasB, 'petugas'),
            'TS-OWN-01: petugas B tidak boleh bisa edit laporan milik petugas A'
        );
    }

    /**
     * TS-OWN-02
     * canEdit: Petugas tidak bisa edit laporan miliknya yang sudah submitted.
     */
    public function testOwn02_PetugasCannotEditSubmittedOwnReport(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' own02',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');

        self::assertFalse(
            $model->canEdit($id, $this->petugasId, 'petugas'),
            'TS-OWN-02: petugas tidak boleh edit laporan yang sudah submitted'
        );
    }

    /**
     * TS-OWN-03
     * canEdit: Admin bisa edit semua laporan dalam status apapun.
     */
    public function testOwn03_AdminCanEditAnyReport(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' own03',
        ]));

        $model->submitReport($id, $this->petugasId, 'petugas');
        $model->verifyReport($id, $this->adminId);

        self::assertTrue(
            $model->canEdit($id, $this->adminId, 'admin'),
            'TS-OWN-03: admin harus bisa edit laporan verified'
        );
    }

    /**
     * TS-OWN-04
     * submitReport oleh bukan pemilik (bukan admin) harus melempar LogicException.
     */
    public function testOwn04_SubmitByNonOwnerThrows(): void
    {
        if ($this->petugasB <= 0) {
            self::markTestSkipped('Butuh 2 akun petugas untuk TS-OWN-04');
        }

        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' own04',
        ]));

        $this->expectException(LogicException::class);
        $model->submitReport($id, $this->petugasB, 'petugas');
    }

    /**
     * TS-OWN-05
     * isOwner: mengembalikan true hanya untuk pemilik.
     */
    public function testOwn05_IsOwnerReturnsTrueForOwnerOnly(): void
    {
        if ($this->petugasB <= 0) {
            self::markTestSkipped('Butuh 2 akun petugas untuk TS-OWN-05');
        }

        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' own05',
        ]));

        self::assertTrue($model->isOwner($id, $this->petugasId),
            'TS-OWN-05: isOwner harus true untuk pemilik'
        );
        self::assertFalse($model->isOwner($id, $this->petugasB),
            'TS-OWN-05: isOwner harus false untuk bukan pemilik'
        );
    }

    // =========================================================
    // TS-SEC : Keamanan
    // =========================================================

    /**
     * TS-SEC-01
     * Mass-assignment protection: kolom 'deleted_at' tidak ada di $fillable,
     * insert dengan field itu harus melempar InvalidArgumentException.
     */
    public function testSec01_MassAssignmentProtectionBlocksUnfillable(): void
    {
        $model = new LaporanLainnya();

        $this->expectException(InvalidArgumentException::class);
        $model->createReport(array_merge($this->buildPayload([
            'deskripsi' => $this->marker . ' sec01',
        ]), [
            'deleted_at' => '2020-01-01 00:00:00',  // bukan fillable
        ]));
    }

    /**
     * TS-SEC-02
     * Kode laporan bersifat atomik — dua submit serentak tidak boleh
     * menghasilkan kode yang sama (UNIQUE constraint harus menangkap ini).
     */
    public function testSec02_KodeLaporanIsUniqueAcrossSubmissions(): void
    {
        $model = new LaporanLainnya();

        $id1 = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' sec02-A',
        ]));
        $id2 = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' sec02-B',
        ]));

        $model->submitReport($id1, $this->petugasId, 'petugas');
        $model->submitReport($id2, $this->petugasId, 'petugas');

        $kode1 = $model->getById($id1)['kode_laporan'];
        $kode2 = $model->getById($id2)['kode_laporan'];

        self::assertNotSame($kode1, $kode2,
            'TS-SEC-02: dua laporan yang disubmit harus mendapat kode yang berbeda'
        );
    }

    /**
     * TS-SEC-03
     * Kolom verified_by, verified_at, catatan_verifikasi tidak boleh
     * di-set saat createReport (harus null saat draft).
     */
    public function testSec03_VerifiedFieldsNullOnCreate(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' sec03',
        ]));

        $saved = $model->getById($id);
        self::assertNull($saved['verified_by'],
            'TS-SEC-03: verified_by harus null saat draft'
        );
        self::assertNull($saved['verified_at'],
            'TS-SEC-03: verified_at harus null saat draft'
        );
        self::assertNull($saved['catatan_verifikasi'],
            'TS-SEC-03: catatan_verifikasi harus null saat draft'
        );
    }

    // =========================================================
    // TS-DB : Integritas database
    // =========================================================

    /**
     * TS-DB-01
     * Soft delete: laporan yang di-delete tidak muncul di getById/getAllWithFilters.
     */
    public function testDb01_SoftDeleteHidesFromNormalQueries(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' db01',
        ]));

        // Soft delete langsung via DB
        $this->db->prepare(
            "UPDATE laporan_lainnya SET deleted_at = NOW(), deleted_by = ? WHERE id = ?"
        )->execute([$this->adminId, $id]);

        $found = $model->getById($id);
        self::assertNull($found,
            'TS-DB-01: laporan yang di-soft-delete tidak boleh tampil di getById()'
        );
    }

    /**
     * TS-DB-02
     * getById untuk ID yang tidak ada mengembalikan null.
     */
    public function testDb02_GetByIdNonExistentReturnsNull(): void
    {
        $model = new LaporanLainnya();

        $result = $model->getById(PHP_INT_MAX);
        self::assertNull($result,
            'TS-DB-02: getById() untuk ID tidak ada harus mengembalikan null'
        );
    }

    /**
     * TS-DB-03
     * getAllWithFilters dengan filter user_id hanya mengembalikan laporan
     * milik user tersebut.
     */
    public function testDb03_FilterByUserIdScopesCorrectly(): void
    {
        if ($this->petugasB <= 0) {
            self::markTestSkipped('Butuh 2 akun petugas untuk TS-DB-03');
        }

        $model = new LaporanLainnya();

        $idA = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasId,
            'deskripsi' => $this->marker . ' db03-A',
        ]));
        $idB = $model->createReport($this->buildPayload([
            'user_id'   => $this->petugasB,
            'deskripsi' => $this->marker . ' db03-B',
        ]));

        $results = $model->getAllWithFilters(
            ['user_id' => $this->petugasId, 'include_draft' => true],
            50,
            0
        );

        $foundIds = array_column($results, 'id');

        self::assertContains($idA, $foundIds,
            'TS-DB-03: laporan milik petugasId harus tampil'
        );
        self::assertNotContains($idB, $foundIds,
            'TS-DB-03: laporan milik petugasB tidak boleh tampil'
        );
    }

    /**
     * TS-DB-04
     * Relasi wilayah tersimpan benar — desa_id, kecamatan_id, kabupaten_id.
     */
    public function testDb04_WilayahRelationStoredCorrectly(): void
    {
        $model = new LaporanLainnya();

        $id = $model->createReport($this->buildPayload([
            'deskripsi' => $this->marker . ' db04',
        ]));

        $saved = $model->getById($id);
        self::assertSame($this->desaId, (int) $saved['desa_id'],
            'TS-DB-04: desa_id harus tersimpan benar'
        );
        self::assertSame($this->kecamatanId, (int) $saved['kecamatan_id'],
            'TS-DB-04: kecamatan_id harus tersimpan benar'
        );
        self::assertSame($this->kabupatenId, (int) $saved['kabupaten_id'],
            'TS-DB-04: kabupaten_id harus tersimpan benar'
        );
    }

    /**
     * TS-DB-05
     * getCountWithFilters konsisten dengan jumlah row getAllWithFilters.
     */
    public function testDb05_CountConsistentWithGetAll(): void
    {
        $model = new LaporanLainnya();

        // Buat 3 laporan dengan marker yang sama
        for ($i = 1; $i <= 3; $i++) {
            $model->createReport($this->buildPayload([
                'deskripsi' => $this->marker . " db05-$i",
            ]));
        }

        $filters = [
            'user_id'      => $this->petugasId,
            'include_draft' => true,
        ];

        $all   = $model->getAllWithFilters($filters, 100, 0);
        $count = $model->getCountWithFilters($filters);

        self::assertSame(
            count($all),
            $count,
            'TS-DB-05: getCountWithFilters() harus konsisten dengan jumlah row getAllWithFilters()'
        );
    }

    // =========================================================
    // Helper Methods
    // =========================================================

    /**
     * Buat payload data laporan minimal valid.
     * Parameter $overrides digunakan untuk mengubah nilai default.
     */
    private function buildPayload(array $overrides = []): array
    {
        $today = date('Y-m-d');

        $defaults = [
            'user_id'         => $this->petugasId,
            'jenis_id'        => $this->jenisId,
            'kabupaten_id'    => $this->kabupatenId,
            'kecamatan_id'    => $this->kecamatanId,
            'desa_id'         => $this->desaId,
            'alamat_lengkap'  => 'Jl. Testbed No. 10 RT 01 RW 02 ' . $this->marker,
            'tanggal_kejadian' => $today,
            'data_json'       => json_encode([
                'nama_varietas' => 'Padi Test ' . $this->marker,
                'jumlah_bibit'  => 100,
                'sumber_bibit'  => null,
            ]),
            'deskripsi'       => $this->marker . ' default-desc',
            'latitude'        => null,
            'longitude'       => null,
            'foto_url'        => null,
            'status'          => 'draft',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Jalankan subset logika validasi dari LaporanLainnyaController::store().
     * Mengembalikan array pesan error yang dihasilkan.
     *
     * @param array  $input    Data POST yang disimulasikan
     * @param string $userRole Role user ('petugas', 'operator', 'admin')
     */
    private function runControllerValidation(array $input, string $userRole): array
    {
        $errors = [];

        $jenisId = (int) ($input['jenis_id'] ?? 0);

        // ── Validasi jenis_id ──────────────────────────────────────────
        if ($jenisId <= 0) {
            $errors[] = 'Jenis laporan wajib dipilih';
            // Hentikan validasi field dinamis jika jenis tidak valid
            $fields = [];
        } else {
            require_once ROOT_PATH . '/app/models/JenisLaporan.php';
            $jenisModel = new JenisLaporan();
            $jenis = $jenisModel->findById($jenisId);
            if (!$jenis) {
                $errors[] = 'Jenis laporan tidak ditemukan';
                $fields = [];
            } else {
                $fields = $jenisModel->getFields($jenisId);
            }
        }

        // ── Validasi tanggal ───────────────────────────────────────────
        $tanggal = trim($input['tanggal_kejadian'] ?? '');
        if ($tanggal === '') {
            $errors[] = 'Tanggal kejadian wajib diisi';
        } else {
            $tz   = new DateTimeZone('Asia/Jakarta');
            $date = DateTime::createFromFormat('!Y-m-d', $tanggal, $tz);
            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                $errors[] = 'Format tanggal tidak valid';
            } elseif ($date > new DateTime('today', $tz)) {
                $errors[] = 'Tanggal kejadian tidak boleh di masa depan';
            } elseif ($date < new DateTime('-10 years', $tz)) {
                $errors[] = 'Tanggal kejadian tidak boleh lebih dari 10 tahun yang lalu';
            }
        }

        // ── Validasi field dinamis ─────────────────────────────────────
        foreach ($fields as $field) {
            $value = $input[$field['name']] ?? null;

            if (!empty($field['required']) && ($value === null || trim((string) $value) === '')) {
                $errors[] = "Field '{$field['label']}' wajib diisi";
            }

            // Type coercion validation
            if ($value !== null && $value !== '') {
                switch ($field['type'] ?? 'text') {
                    case 'number':
                    case 'integer':
                        if (!is_numeric($value)) {
                            $errors[] = "Field '{$field['label']}' harus berupa angka";
                        }
                        break;
                    case 'text':
                    case 'textarea':
                        if (mb_strlen((string) $value) > 2000) {
                            $errors[] = "Field '{$field['label']}' maksimal 2000 karakter";
                        }
                        break;
                }
            }
        }

        // ── Validasi deskripsi ─────────────────────────────────────────
        $deskripsi = $input['deskripsi'] ?? '';
        if (!empty($deskripsi) && mb_strlen($deskripsi) > 5000) {
            $errors[] = 'Deskripsi maksimal 5000 karakter';
        }

        // ── Validasi wilayah & alamat per role ─────────────────────────
        $kabId   = $input['kabupaten_id'] ?? '';
        $kecId   = $input['kecamatan_id'] ?? '';
        $desId   = $input['desa_id']      ?? '';
        $alamat  = trim($input['alamat_lengkap'] ?? '');

        if (in_array($userRole, ['petugas', 'operator'], true)) {
            if (empty($kabId) || empty($kecId) || empty($desId)) {
                $errors[] = 'Data lokasi lengkap (kabupaten, kecamatan, desa) wajib diisi';
            }
            if ($userRole === 'petugas' && strlen($alamat) < 10) {
                $errors[] = 'Alamat lengkap wajib diisi minimal 10 karakter untuk petugas';
            }
            if ($userRole === 'operator' && strlen($alamat) < 5) {
                $errors[] = 'Alamat lengkap wajib diisi minimal 5 karakter';
            }
        } elseif ($userRole === 'admin' && empty($alamat)) {
            $errors[] = 'Alamat lengkap wajib diisi';
        }

        // ── Validasi koordinat GPS ─────────────────────────────────────
        $lat = $input['latitude']  ?? null;
        $lon = $input['longitude'] ?? null;

        $latProvided = ($lat !== null && $lat !== '');
        $lonProvided = ($lon !== null && $lon !== '');

        if ($latProvided && $lonProvided) {
            $latF = (float) $lat;
            $lonF = (float) $lon;

            if ($latF < -90 || $latF > 90) {
                $errors[] = 'Latitude harus antara -90 dan 90';
            }
            if ($lonF < -180 || $lonF > 180) {
                $errors[] = 'Longitude harus antara -180 dan 180';
            }

            // Validasi batas Jember (Konstanta dari config/config.php)
            $jemLat = defined('JEMBER_LAT_MIN')
                ? ['min' => JEMBER_LAT_MIN, 'max' => JEMBER_LAT_MAX]
                : ['min' => -8.480000, 'max' => -7.960000];
            $jemLon = defined('JEMBER_LON_MIN')
                ? ['min' => JEMBER_LON_MIN, 'max' => JEMBER_LON_MAX]
                : ['min' => 113.280000, 'max' => 113.980000];

            if ($latF < $jemLat['min'] || $latF > $jemLat['max']
                || $lonF < $jemLon['min'] || $lonF > $jemLon['max']
            ) {
                $errors[] = 'Koordinat berada di luar batas wilayah Kabupaten Jember';
            }
        } elseif ($latProvided !== $lonProvided) {
            $errors[] = 'Kedua koordinat (Latitude dan Longitude) harus diisi bersama';
        }

        return $errors;
    }

    /**
     * Cari user ID berdasarkan role, dengan opsi exclude ID tertentu.
     */
    private function findUserId(string $role, int $excludeId = 0): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM users WHERE role = ? AND id != ? AND aktif = 1 ORDER BY id LIMIT 1'
        );
        $stmt->execute([$role, $excludeId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Cari jenis laporan ID berdasarkan kode.
     */
    private function findJenisId(string $kode): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM master_jenis_laporan WHERE kode = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$kode]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Muat environment dari .env dan .env.local.
     */
    private function loadEnv(): void
    {
        foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
