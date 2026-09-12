<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration DB modul Evaluasi Akurasi (runtime root/integrated).
 * Memakai periode_tahun 1999 + marker UNITTEST agar tidak menyentuh data nyata.
 */
final class EvaluasiAkurasiDatabaseTest extends TestCase
{
    private PDO $db;
    private EvaluasiAkurasi $model;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/models/EvaluasiAkurasi.php';
        $this->db = Database::getInstance()->getConnection();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->model = new EvaluasiAkurasi($this->db);
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        unset($_SESSION['user_id']);
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM evaluasi_akurasi_panen WHERE periode_tahun = 1999");
        $this->db->exec("DELETE FROM evaluasi_akurasi_panen WHERE nama_wilayah LIKE 'UNITTEST%'");
    }

    public function testWilayahOptionsMemuatKodeBpsJember(): void
    {
        $options = $this->model->getWilayahOptions();
        self::assertGreaterThanOrEqual(38, count($options), 'Minimal 38 kab/kota Jatim');

        $found = null;
        foreach ($options as $opt) {
            if ((int) $opt['kode'] === 3509) {
                $found = $opt;
            }
        }
        self::assertNotNull($found, 'Kode 3509 Jember wajib ada');
        self::assertSame('Jember', $found['nama']);
    }

    public function testInsertMenolakWilayahTakDikenal(): void
    {
        $result = $this->model->insert([
            'periode_bulan' => 1,
            'periode_tahun' => 1999,
            'wilayah_id' => 999999,
            'nama_wilayah' => 'Wilayah Fiktif XYZ',
            'luas_estimasi_daerah' => 100,
            'luas_rilis_bps' => null,
        ]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Wilayah tidak dikenal', $result['message']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM evaluasi_akurasi_panen WHERE periode_tahun = 1999');
        $stmt->execute();
        self::assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testInsertMenghitungDeviasiDanAuditTrail(): void
    {
        $_SESSION['user_id'] = 1;
        $result = $this->model->insert([
            'periode_bulan' => 1,
            'periode_tahun' => 1999,
            'wilayah_id' => 3509,
            'nama_wilayah' => 'Kab. Jember',
            'luas_estimasi_daerah' => 1000,
            'luas_rilis_bps' => 900,
            'catatan_analisis' => 'uji audit',
        ]);
        self::assertTrue($result['success']);

        $row = $this->model->getById($result['id']);
        self::assertEqualsWithDelta(100, (float) $row['deviasi_absolut'], 0.001);
        self::assertEqualsWithDelta(11.11, (float) $row['persentase_bias'], 0.01);
        self::assertSame('Bias Tinggi', $row['status_akurasi']);
        self::assertSame('Jember', $row['nama_wilayah'], 'Nama dinormalisasi ke master BPS');
        self::assertEquals(1, (int) $row['created_by']);
    }

    public function testUpdateMenghitungUlangDanMengisiUpdatedBy(): void
    {
        $_SESSION['user_id'] = 1;
        $created = $this->model->insert([
            'periode_bulan' => 2,
            'periode_tahun' => 1999,
            'wilayah_id' => 3509,
            'nama_wilayah' => 'Jember',
            'luas_estimasi_daerah' => 1000,
            'luas_rilis_bps' => 900,
        ]);
        self::assertTrue($created['success']);

        $_SESSION['user_id'] = 2;
        $updated = $this->model->update((int) $created['id'], ['luas_rilis_bps' => 980]);
        self::assertTrue($updated['success']);

        $row = $this->model->getById($created['id']);
        self::assertEqualsWithDelta(20, (float) $row['deviasi_absolut'], 0.001);
        self::assertEqualsWithDelta(2.04, (float) $row['persentase_bias'], 0.01);
        self::assertSame('Sangat Akurat', $row['status_akurasi']);
        self::assertEquals(2, (int) $row['updated_by']);
    }

    public function testChartTetapMenampilkanEstimasiSaatRilisNull(): void
    {
        $_SESSION['user_id'] = 1;
        $result = $this->model->insert([
            'periode_bulan' => 3,
            'periode_tahun' => 1999,
            'wilayah_id' => 3510,
            'nama_wilayah' => 'Banyuwangi',
            'luas_estimasi_daerah' => 500,
            'luas_rilis_bps' => null,
        ]);
        self::assertTrue($result['success']);

        $chart = $this->model->getChartData(1999);
        $maret = null;
        foreach ($chart as $row) {
            if ((int) $row['bulan'] === 3) {
                $maret = $row;
            }
        }
        self::assertNotNull($maret);
        self::assertEqualsWithDelta(500, (float) $maret['estimasi'], 0.001, 'Estimasi wajib tampil walau rilis NULL');
        self::assertNull($maret['rilis']);
    }

    public function testSnapshotBatchMenghormatiLockDalamTransaksi(): void
    {
        // Pakai data nyata 2026/1 dalam transaksi yang di-rollback agar aman.
        $this->db->beginTransaction();
        try {
            $before = (int) $this->db->query(
                'SELECT COUNT(*) FROM evaluasi_akurasi_panen WHERE periode_bulan = 1 AND periode_tahun = 2026'
            )->fetchColumn();
            self::assertGreaterThan(0, $before, 'Fixture snapshot 2026/1 wajib ada');

            $this->db->exec(
                'UPDATE evaluasi_akurasi_panen SET snapshot_locked = 1 '
                . 'WHERE periode_bulan = 1 AND periode_tahun = 2026 AND wilayah_id = 3509'
            );

            $_SESSION['user_id'] = 1;
            $result = $this->model->snapshotEstimasi(1, 2026);
            self::assertTrue($result['success']);
            self::assertGreaterThanOrEqual(1, (int) ($result['data']['skipped'] ?? 0), 'Baris terkunci wajib dilewati');

            $after = (int) $this->db->query(
                'SELECT COUNT(*) FROM evaluasi_akurasi_panen WHERE periode_bulan = 1 AND periode_tahun = 2026'
            )->fetchColumn();
            self::assertSame($before, $after, 'Bulk upsert tidak boleh duplikat');
        } finally {
            $this->db->rollBack();
        }
    }
}
