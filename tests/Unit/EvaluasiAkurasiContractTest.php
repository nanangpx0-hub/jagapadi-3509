<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Kontrak perbaikan modul Evaluasi Akurasi (runtime root/integrated).
 * Sebagian besar asersi berbasis file agar tidak butuh DB; logika murni
 * diuji via SQLite in-memory dengan PDO injection.
 */
final class EvaluasiAkurasiContractTest extends TestCase
{
    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, "function {$method}(");
        self::assertNotFalse($start, "Method {$method} wajib tersedia");
        $next = strpos($source, 'function ', $start + 12);

        return substr($source, $start, ($next === false ? strlen($source) : $next) - $start);
    }

    public function testDetermineStatusThresholds(): void
    {
        require_once ROOT_PATH . '/app/models/EvaluasiAkurasi.php';
        $pdo = new PDO('sqlite::memory:');
        $model = new EvaluasiAkurasi($pdo);

        self::assertSame('Sangat Akurat', $model->determineStatus(0));
        self::assertSame('Sangat Akurat', $model->determineStatus(4.99));
        self::assertSame('Perlu Perhatian', $model->determineStatus(5));
        self::assertSame('Perlu Perhatian', $model->determineStatus(10));
        self::assertSame('Bias Tinggi', $model->determineStatus(10.01));
        self::assertSame('Bias Tinggi', $model->determineStatus(22));
    }

    public function testResolveWilayahMemakaiKodeBpsBukanCrc32(): void
    {
        require_once ROOT_PATH . '/app/models/EvaluasiAkurasi.php';
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE data_ksa_bulanan (kode_wilayah VARCHAR(10), kabupaten_kota VARCHAR(100))');
        $pdo->exec("INSERT INTO data_ksa_bulanan VALUES ('3509', 'Jember'), ('3510', 'Banyuwangi')");
        $model = new EvaluasiAkurasi($pdo);

        $byKode = $model->resolveWilayah(3509, 'Nama Asing');
        self::assertSame(['wilayah_id' => 3509, 'nama_wilayah' => 'Jember'], $byKode);

        $byNama = $model->resolveWilayah(null, 'Kab. Banyuwangi');
        self::assertSame(['wilayah_id' => 3510, 'nama_wilayah' => 'Banyuwangi'], $byNama);

        self::assertNull($model->resolveWilayah(null, 'Wilayah Fiktif XYZ'));
        self::assertNull($model->resolveWilayah(999999, 'Wilayah Fiktif XYZ'));

        $src = (string) file_get_contents(ROOT_PATH . '/app/models/EvaluasiAkurasi.php');
        self::assertStringNotContainsString('crc32(strtolower', $src, 'Hash CRC32 untuk wilayah_id baru wajib dihapus');
    }

    public function testChartQueryTidakMenyembunyikanEstimasi(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/models/EvaluasiAkurasi.php');
        $chart = $this->methodBody($src, 'getChartData');
        self::assertStringContainsString('SUM(luas_estimasi_daerah)', $chart);
        self::assertStringNotContainsString(
            'CASE WHEN luas_rilis_bps IS NOT NULL THEN luas_estimasi_daerah',
            $chart,
            'Agregasi estimasi tidak boleh digate oleh rilis BPS'
        );
    }

    public function testSnapshotMemakaiBulkUpsertBukanNPlusSatu(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/models/EvaluasiAkurasi.php');
        $snap = $this->methodBody($src, 'snapshotEstimasi');
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $snap);
        self::assertStringContainsString('SELECT wilayah_id, snapshot_locked', $snap);
        self::assertStringNotContainsString('getByPeriodeWilayah($bulan', $snap, 'Snapshot tidak boleh query per-wilayah');
    }

    public function testConstructorTanpaAutoDdlPerRequest(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/models/EvaluasiAkurasi.php');
        $ctor = $this->methodBody($src, '__construct');
        self::assertStringNotContainsString('$this->createTablesIfNotExist()', $ctor);
        self::assertStringContainsString('2026_09_12_create_evaluasi_akurasi_tables', $ctor);
    }

    public function testAuditTrailDitulisDariSession(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/models/EvaluasiAkurasi.php');
        foreach (['function insert(', 'function update(', 'function updateRilisResmi('] as $fn) {
            $body = $this->methodBody($src, trim(substr($fn, 9), '('));
            self::assertStringContainsString("\$_SESSION['user_id']", $body, "{$fn} wajib mengisi created_by/updated_by");
        }
    }

    public function testCsrfWhitelistMencakupSnapshotDanPreview(): void
    {
        $front = (string) file_get_contents(ROOT_PATH . '/index.php');
        self::assertStringContainsString("'generatesnapshot'", $front);
        self::assertStringContainsString("'previewimport'", $front);
    }

    public function testPreviewImportWajibCsrf(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/controllers/EvaluasiController.php');
        $body = $this->methodBody($src, 'previewImport');
        self::assertStringContainsString('checkAdmin();', $body);
        self::assertStringContainsString('validateCsrfToken()', $body);
    }

    public function testUploadDibatasiDanDivalidasiMime(): void
    {
        $src = (string) file_get_contents(ROOT_PATH . '/app/controllers/EvaluasiController.php');
        self::assertStringContainsString('5 * 1024 * 1024', $src);
        self::assertStringContainsString('FILEINFO_MIME_TYPE', $src);
        self::assertStringContainsString('is_uploaded_file', $src);
        self::assertStringContainsString('random_bytes', $src);
    }

    public function testViewStatusSelaluTampilAksiHanyaAdmin(): void
    {
        $view = (string) file_get_contents(ROOT_PATH . '/app/views/evaluasi/index.php');
        // Header aksi tetap kondisional admin.
        self::assertStringContainsString('if ($canManageEvaluation): ?><th class="text-center">Aksi</th>', $view);
        // Status menunggu rilis harus tampil untuk semua role (statistisi).
        $posStatus = strpos($view, 'Menunggu Rilis');
        self::assertNotFalse($posStatus);
        $posAksiGuard = strpos($view, '<?php if ($canManageEvaluation): ?>', $posStatus);
        self::assertNotFalse($posAksiGuard, 'Blok aksi admin wajib berada setelah kolom status');
        // Tombol mutasi tidak boleh tampil sebelum guard admin.
        $posEditBtn = strpos($view, 'btn-edit');
        self::assertNotFalse($posEditBtn);
        self::assertGreaterThan($posAksiGuard, $posEditBtn, 'Tombol edit wajib di dalam guard admin');
    }

    public function testViewMemakaiDropdownWilayahResmi(): void
    {
        $view = (string) file_get_contents(ROOT_PATH . '/app/views/evaluasi/index.php');
        self::assertStringContainsString('name="wilayah_id"', $view);
        self::assertStringContainsString('$wilayahOptions', $view);
    }
}
