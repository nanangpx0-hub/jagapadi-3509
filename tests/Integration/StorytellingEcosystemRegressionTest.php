<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regresi ekosistem storytelling terhadap database aktual:
 * 1. getAnginLag wajib valid di bawah sql_mode ONLY_FULL_GROUP_BY.
 * 2. getLaporanLainnyaLag wajib membaca status lowercase
 *    ('submitted','verified') sesuai enum tabel laporan_lainnya.
 */
final class StorytellingEcosystemRegressionTest extends TestCase
{
    private static ?PDO $sharedDb = null;
    private static ?string $connectionError = null;
    private PDO $db;
    private DataStoryService $service;
    private ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        self::loadEnvironment();

        try {
            $driver = getenv('DB_DRIVER') ?: 'mysql';
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'jagapadi_local';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
            $dsn = "{$driver}:host={$host};port={$port};dbname={$name};charset={$charset}";

            self::$sharedDb = new PDO(
                $dsn,
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $error) {
            self::$connectionError = $error->getMessage();
        }
    }

    private static function loadEnvironment(): void
    {
        // Pola repo: .env.local menimpa .env (tanpa guard) — sama seperti
        // DataStoryServiceDatabaseTest::loadEnvironment().
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
                if ($key === '') {
                    continue;
                }
                putenv("{$key}={$value}");
            }
        }
    }

    protected function setUp(): void
    {
        if (self::$sharedDb === null) {
            self::markTestSkipped(
                'Database integrasi tidak tersedia: ' . (self::$connectionError ?? 'unknown')
            );
        }

        $this->db = self::$sharedDb;
        $this->db->beginTransaction();
        $this->service = new DataStoryService(
            $this->db,
            sys_get_temp_dir() . '/jagapadi-storytelling-eco-test.log'
        );
        $this->reflection = new ReflectionClass(DataStoryService::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->service, ...$args);
    }

    public function testAnginLagSurvivesOnlyFullGroupBy(): void
    {
        $mode = (string) $this->db->query('SELECT @@sql_mode')->fetchColumn();
        if (!str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            self::markTestSkipped('sql_mode tanpa ONLY_FULL_GROUP_BY: ' . $mode);
        }

        // Periode dengan data angin aktual (scraper/simulasi).
        $row = $this->db->query(
            "SELECT YEAR(tanggal) y, MONTH(tanggal) m FROM kecepatan_angin
             GROUP BY y, m ORDER BY y DESC, m DESC LIMIT 1"
        )->fetch();
        if ($row === false) {
            self::markTestSkipped('Tidak ada data kecepatan_angin.');
        }

        $result = $this->callPrivate('getAnginLag', (int) $row['m'], (int) $row['y'], 0);

        self::assertTrue($result['has_data'], 'getAnginLag gagal di bawah ONLY_FULL_GROUP_BY.');
        self::assertNotNull($result['avg_kecepatan']);
    }

    public function testLaporanLainnyaLagReadsLowercaseStatus(): void
    {
        $userId = (int) $this->db->query(
            "SELECT id FROM users WHERE role = 'petugas' ORDER BY id LIMIT 1"
        )->fetchColumn();
        $jenisId = (int) $this->db->query(
            'SELECT id FROM master_jenis_laporan ORDER BY id LIMIT 1'
        )->fetchColumn();
        $kecId = (int) $this->db->query(
            'SELECT id FROM master_kecamatan ORDER BY id LIMIT 1'
        )->fetchColumn();
        if ($userId <= 0 || $jenisId <= 0 || $kecId <= 0) {
            self::markTestSkipped('Fixture master/user belum tersedia.');
        }

        $insert = $this->db->prepare(
            "INSERT INTO laporan_lainnya
                (user_id, jenis_id, kecamatan_id, kode_laporan, tanggal_kejadian,
                 data_json, deskripsi, status)
             VALUES (?, ?, ?, 'REG-eco-test', '2026-06-15', '{}', 'Regresi ekosistem', 'submitted')"
        );
        $insert->execute([$userId, $jenisId, $kecId]);

        $result = $this->callPrivate('getLaporanLainnyaLag', 6, 2026, $kecId);

        self::assertGreaterThanOrEqual(1, $result['total_laporan']);
        self::assertTrue($result['has_data']);
    }
}
