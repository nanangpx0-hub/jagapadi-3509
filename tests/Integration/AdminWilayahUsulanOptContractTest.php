<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regresi kontrak Admin Wilayah (Kecamatan/Desa) dan Usulan OPT runtime root.
 *
 * Cakupan:
 *  - MasterKecamatan: checkNameExists excludeId, updateNameOnly policy rename-only
 *  - MasterDesa: checkNameExists excludeId, validasi hierarki Kabupaten-Kecamatan
 *  - Kontrak status Usulan OPT sinkron DB ENUM vs konstanta model/service
 *  - Route canonical terdaftar & method controller tersedia (fail closed)
 *  - View memakai URL canonical tanpa field palsu (kode_pos) atau nilai lama ('Ditolak')
 */
final class AdminWilayahUsulanOptContractTest extends TestCase
{
    private ?PDO $db = null;
    private bool $dbAvailable = true;

    public static function setUpBeforeClass(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
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
                // File berikutnya (.env.local) menimpa sebelumnya — pola yang sama
                // dengan test integrasi lain agar kredensial lokal menang.
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->db->beginTransaction();
        } catch (Throwable $e) {
            $this->dbAvailable = false;
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function kec(): MasterKecamatan
    {
        return new MasterKecamatan();
    }

    private function desa(): MasterDesa
    {
        return new MasterDesa();
    }

    private function insertKecamatan(int $kabupatenId, string $nama, string $kode): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO master_kecamatan (kabupaten_id, kode, nama_kecamatan) VALUES (?, ?, ?)'
        );
        $stmt->execute([$kabupatenId, $kode, $nama]);

        return (int) $this->db->lastInsertId();
    }

    private function insertDesa(int $kecamatanId, string $nama, string $kode): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO master_desa (kecamatan_id, kode, nama_desa) VALUES (?, ?, ?)'
        );
        $stmt->execute([$kecamatanId, $kode, $nama]);

        return (int) $this->db->lastInsertId();
    }

    // ==================== KECAMATAN ====================

    public function testKecamatanRenameToSameNameIsNotDuplicate(): void
    {
        $id = $this->insertKecamatan(1, 'Sukamaju Regresi', '9900010');

        self::assertFalse(
            $this->kec()->checkNameExists(1, 'Sukamaju Regresi', $id),
            'Simpan tanpa perubahan tidak boleh dianggap duplikat'
        );
        self::assertTrue(
            $this->kec()->checkNameExists(1, 'Sukamaju Regresi'),
            'Tanpa excludeId, nama sendiri tetap terhitung (perilaku lama)'
        );
    }

    public function testKecamatanDuplicateNameInSameKabupatenRejected(): void
    {
        $first = $this->insertKecamatan(1, 'Duplikat Kec Regresi', '9900020');
        $second = $this->insertKecamatan(1, 'Lainnya Kec Regresi', '9900030');

        self::assertTrue($this->kec()->checkNameExists(1, 'Duplikat Kec Regresi', $second));
    }

    public function testUpdateNameOnlyRenamesAndNormalizesWhitespace(): void
    {
        $id = $this->insertKecamatan(1, 'Sumber Rejo   Baru', '9900040');

        self::assertTrue($this->kec()->updateNameOnly($id, '  Sumber   Rejo Baru  ', 1));

        $row = $this->db->prepare('SELECT nama_kecamatan FROM master_kecamatan WHERE id = ?');
        $row->execute([$id]);
        self::assertSame('Sumber Rejo Baru', (string) $row->fetchColumn());
    }

    public function testUpdateNameOnlyRejectsEmptyName(): void
    {
        $id = $this->insertKecamatan(1, 'Tetap Regresi', '9900050');

        self::assertFalse($this->kec()->updateNameOnly($id, '   ', 1));
    }

    // ==================== DESA ====================

    public function testDesaDuplicateNameExcludesCurrentId(): void
    {
        $kecId = $this->insertKecamatan(1, 'Induk Desa Regresi', '9900060');
        $desaA = $this->insertDesa($kecId, 'Desa A Regresi', '9900060001');
        $desaB = $this->insertDesa($kecId, 'Desa B Regresi', '9900060002');

        self::assertFalse($this->desa()->checkNameExists($kecId, 'Desa A Regresi', $desaA));
        self::assertTrue($this->desa()->checkNameExists($kecId, 'Desa A Regresi', $desaB));
    }

    public function testKecamatanOutsideKabupatenIsRejected(): void
    {
        // Buat dua kabupaten sementara di dalam transaksi agar test mandiri.
        $this->db->prepare('INSERT INTO master_kabupaten (kode, nama_kabupaten) VALUES (?, ?)')
            ->execute(['9980', 'Kab Regresi A']);
        $kabA = (int) $this->db->lastInsertId();
        $this->db->prepare('INSERT INTO master_kabupaten (kode, nama_kabupaten) VALUES (?, ?)')
            ->execute(['9990', 'Kab Regresi B']);
        $kabB = (int) $this->db->lastInsertId();

        $foreignKec = $this->insertKecamatan($kabB, 'Kec Kab Lain Regresi', '9900070');

        self::assertFalse(
            $this->desa()->validateKecamatanInKabupaten($foreignKec, $kabA),
            'Hierarki silang kabupaten harus ditolak'
        );
        self::assertTrue($this->desa()->validateKecamatanInKabupaten($foreignKec, $kabB));
    }
    // ==================== STATUS & ROUTE CONTRACT ====================

    public function testUsulanOptStatusesMatchDatabaseEnum(): void
    {
        $cols = $this->db->query('SHOW COLUMNS FROM usulan_opt LIKE "status"')->fetch(PDO::FETCH_ASSOC);
        self::assertNotFalse($cols);

        preg_match("/^enum\((.*)\)$/", (string) $cols['Type'], $m);
        $enumValues = array_map(
            static fn(string $v): string => trim($v, "'"),
            explode(',', (string) $m[1])
        );

        foreach (UsulanOpt::STATUSES as $status) {
            self::assertContains($status, $enumValues, "Status '$status' harus ada di ENUM database");
        }
        self::assertContains('Ditolak Permanen', $enumValues);
    }

    public function testRejectStatusIsDitolakPermanenEverywhere(): void
    {
        self::assertSame('Ditolak Permanen', UsulanOpt::STATUS_REJECTED);
        self::assertSame(UsulanOpt::STATUS_REJECTED, UsulanOptReviewService::STATUS_REJECTED);
        self::assertContains(
            UsulanOpt::STATUS_PENDING,
            UsulanOpt::OWNER_EDITABLE === [UsulanOpt::STATUS_DRAFT, UsulanOpt::STATUS_REVISION]
                ? UsulanOpt::STATUSES
                : [],
            'sanity check konstanta'
        );
        self::assertSame([UsulanOpt::STATUS_DRAFT, UsulanOpt::STATUS_REVISION], UsulanOpt::OWNER_EDITABLE);
    }

    public function testCanonicalRoutesAreRegisteredAndResolvable(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        $routes = array_change_key_case($routes, CASE_LOWER);

        $required = [
            'usulan-opt',
            'usulan-opt/create',
            'usulan-opt/store',
            'usulan-opt/update',
            'usulan-opt/submit',
            'usulan-opt/resubmit',
            'usulan-opt/delete-draft',
            'usulan-opt/request-revision',
            'adminwilayah/kecamatan',
            'adminwilayah/desa',
        ];

        foreach ($required as $path) {
            self::assertArrayHasKey($path, $routes, "Route '$path' wajib terdaftar eksplisit");
        }

        // Semua handler UsulanOpt/AdminWilayah harus punya method nyata.
        foreach ($routes as $handler) {
            [$ctrl, $method] = explode('@', (string) $handler);
            if (!in_array($ctrl, ['UsulanOpt', 'AdminWilayah'], true)) {
                continue;
            }
            self::assertTrue(
                method_exists($ctrl . 'Controller', $method),
                "$ctrl@$method harus tersedia"
            );
        }
    }

    public function testViewsUseCanonicalContracts(): void
    {
        $kecEdit = file_get_contents(ROOT_PATH . '/app/views/admin/wilayah/kecamatan/edit.php') ?: '';
        $desaEdit = file_get_contents(ROOT_PATH . '/app/views/admin/wilayah/desa/edit.php') ?: '';
        $desaCreate = file_get_contents(ROOT_PATH . '/app/views/admin/wilayah/desa/create.php') ?: '';
        $usulanIndex = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php') ?: '';

        // Kecamatan: satu kontrak update, tanpa redirect/link jalur rusak.
        self::assertStringContainsString('adminWilayah/kecamatan_update/', $kecEdit);
        self::assertStringNotContainsString('admin/wilayah', $kecEdit);

        // Desa: form ke desa_update; kode_pos tidak lagi menjadi field UI.
        self::assertStringContainsString('adminWilayah/desa_update/', $desaEdit);
        self::assertStringNotContainsString('name="kode_pos"', $desaEdit);
        self::assertStringNotContainsString('name="kode_pos"', $desaCreate);
        self::assertStringNotContainsString('admin/wilayah', $desaEdit . $desaCreate);

        // Usulan OPT: tidak ada nilai status lama 'Ditolak' sebagai kunci map/badge.
        self::assertStringNotContainsString("'Ditolak' =>", $usulanIndex);
        self::assertStringNotContainsString("\$stats['by_status']['Ditolak']", $usulanIndex);
        self::assertStringContainsString('usulan-opt/create', $usulanIndex);
    }
}