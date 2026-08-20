<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DataStoryServiceDatabaseTest extends TestCase
{
    private static ?PDO $sharedDb = null;
    private static ?string $connectionError = null;
    private PDO $db;
    private DataStoryService $service;

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
            sys_get_temp_dir() . '/jagapadi-storytelling-test.log'
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testIncompleteProductionReturnsExplicitInsufficientData(): void
    {
        $regionId = (int) $this->db->query(
            'SELECT id FROM master_kecamatan ORDER BY id LIMIT 1'
        )->fetchColumn();

        self::assertGreaterThan(0, $regionId);
        $result = $this->service->analyzeCauses(1, 2000, $regionId);

        self::assertFalse($result['success']);
        self::assertSame('InsufficientData', $result['error_code']);
        self::assertSame('tidak_cukup', $result['data_quality']['level']);
    }

    public function testNormalDataCanBeAnalyzedSavedUpdatedAndPublished(): void
    {
        $fixture = $this->findRainFixture();
        if ($fixture === null) {
            self::markTestSkipped('Tidak ada periode hujan dengan coverage minimal 70%.');
        }

        $lagPeriod = DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            $fixture['tahun'] . '-' . $fixture['bulan'] . '-1'
        );
        self::assertInstanceOf(DateTimeImmutable::class, $lagPeriod);
        $targetPeriod = $lagPeriod->modify('+1 month');
        $bulan = (int) $targetPeriod->format('n');
        $tahun = (int) $targetPeriod->format('Y');
        $regionId = (int) $fixture['kecamatan_id'];

        $insert = $this->db->prepare(
            "INSERT INTO produksi_gabah
                (kecamatan_id, tahun, bulan, luas_panen, produksi_total, status)
             VALUES (?, ?, ?, ?, ?, 'verified')"
        );
        $insert->execute([$regionId, $tahun, $bulan, 100.0, 620.0]);

        $startedAt = microtime(true);
        $analysis = $this->service->analyzeCauses($bulan, $tahun, $regionId);
        $elapsed = microtime(true) - $startedAt;

        self::assertTrue($analysis['success']);
        self::assertSame('indikasi_hubungan', $analysis['analysis_type']);
        self::assertNotNull($analysis['skor_risiko']['skor_risiko_cuaca']);
        self::assertNotSame('tidak_cukup', $analysis['data_quality']['level']);
        self::assertLessThan(5.0, $elapsed);

        $chart = $this->service->getChartData($bulan, $tahun, $regionId, 6);
        self::assertCount(6, $chart['labels']);
        self::assertCount(6, $chart['datasets'][0]['data']);

        $userId = (int) $this->db->query('SELECT id FROM users ORDER BY id LIMIT 1')
            ->fetchColumn();
        if ($userId <= 0) {
            self::markTestSkipped('Tidak ada user untuk menguji persistensi analisis.');
        }

        $payload = [
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'wilayah_id' => $regionId,
            ],
            'narasi_final' => 'Narasi final pengujian integrasi.',
        ];
        $created = $this->service->saveAnalysis($payload, $userId);
        self::assertSame('created', $created['action']);

        $payload['narasi_final'] = 'Narasi final setelah pembaruan.';
        $updated = $this->service->saveAnalysis($payload, $userId);
        self::assertSame('updated', $updated['action']);
        self::assertSame($created['id'], $updated['id']);

        self::assertTrue($this->service->publishAnalysis($created['id'], $userId));
        $saved = $this->service->getAnalysisById($created['id']);

        self::assertNotNull($saved);
        self::assertSame('published', $saved['status_analisis']);
        self::assertSame('2.0.0', $saved['algorithm_version']);
        self::assertSame('Narasi final setelah pembaruan.', $saved['narasi_final']);
        self::assertNotEmpty($saved['source_snapshot']);
    }

    private function findRainFixture(): ?array
    {
        $stmt = $this->db->query(
            "SELECT kecamatan_id, YEAR(tanggal) AS tahun, MONTH(tanggal) AS bulan,
                    COUNT(DISTINCT tanggal) AS covered_days,
                    DAY(LAST_DAY(MIN(tanggal))) AS expected_days
             FROM curah_hujan
             WHERE kecamatan_id IS NOT NULL
               AND (satuan IS NULL OR LOWER(satuan) = 'mm')
             GROUP BY kecamatan_id, YEAR(tanggal), MONTH(tanggal)
             HAVING covered_days / expected_days >= 0.70
             ORDER BY tahun DESC, bulan DESC, kecamatan_id
             LIMIT 1"
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private static function loadEnvironment(): void
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
                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                putenv("{$key}={$value}");
            }
        }
    }
}
