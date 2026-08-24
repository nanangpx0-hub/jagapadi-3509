<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regresi scraper Kecepatan Angin — fallback otomatis ke simulasi.
 *
 * Memastikan bahwa ketika NASA POWER (atau Open-Meteo) tidak mengembalikan
 * data, scraper tidak melempar RuntimeException sehingga endpoint
 * /kecepatanAngin dan /fetch_nasa_kecepatan_angin tetap responsif.
 * Cakupan juga rentang tahun 2020–2026 (loop bulk).
 */
final class KecepatanAnginFallbackDatabaseTest extends TestCase
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
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            $this->dbAvailable = false;
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }
    }

    private function makeProbe(): KecepatanAnginFallbackProbe
    {
        return new KecepatanAnginFallbackProbe();
    }

    public function testNasaEmptyDataFallsBackToSimulation(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        $probe = $this->makeProbe();
        $result = $probe->run([
            'year' => 2024,
            'month' => 6,
            'source' => 'nasa',
            'allow_fallback' => true,
        ]);

        $this->assertTrue($result['success'], 'Scraper harus tetap sukses bila fallback aktif');
        $this->assertTrue($result['fallback_used'], 'fallback_used harus true');
        $this->assertNotEmpty($result['fallback_reason'], 'fallback_reason harus terisi');
        $this->assertStringContainsStringIgnoringCase('Simulasi', $result['source']);
        $this->assertGreaterThan(0, $result['records_success'], 'Harus ada record simulasi');
        $this->assertSame($result['records_success'], $probe->inserted);
    }

    public function testNasaEmptyDataWithFallbackDisabledReturnsFailure(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        $probe = $this->makeProbe();
        $result = $probe->run([
            'year' => 2024,
            'month' => 6,
            'source' => 'nasa',
            'allow_fallback' => false,
        ]);

        $this->assertFalse($result['success'], 'Bila fallback dimatikan & NASA kosong, harus gagal eksplisit');
        $this->assertFalse($result['fallback_used']);
        $this->assertStringContainsStringIgnoringCase('NASA', $result['message']);
    }

    public function testOpenMeteoEmptyDataFallsBackToSimulation(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        $probe = $this->makeProbe();
        $result = $probe->run([
            'year' => 2023,
            'month' => 12,
            'source' => 'openmeteo',
            'allow_fallback' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['fallback_used']);
        $this->assertStringContainsStringIgnoringCase('Simulasi', $result['source']);
        $this->assertGreaterThan(0, $result['records_success']);
    }

    /**
     * @return list<array<int>>
     */
    public function provideYearRange(): array
    {
        $years = range(2020, (int) date('Y'));
        $cases = [];
        foreach ($years as $year) {
            $cases[$year] = [$year];
        }
        return $cases;
    }

    /**
     * @dataProvider provideYearRange
     */
    public function testFullYearRange2020ToCurrentStaysResponsive(int $year): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }
        $probe = $this->makeProbe();
        $result = $probe->run([
            'year' => $year,
            'month' => 1,
            'source' => 'nasa',
            'allow_fallback' => true,
        ]);

        $this->assertTrue(
            $result['success'],
            sprintf('Tahun %d harus tetap sukses via fallback simulasi', $year)
        );
        $this->assertTrue($result['fallback_used'], 'Fallback harus digunakan untuk tahun ' . $year);
        $this->assertGreaterThan(0, $result['records_success']);
    }
}

final class KecepatanAnginFallbackProbe extends KecepatanAnginScraper
{
    public int $inserted = 0;

    public function __construct()
    {
        // Model & koneksi tetap; hanya mencegah penulisan data dummy ke DB.
        parent::__construct();
    }

    /** Simulasi NASA POWER mengembalikan data kosong. */
    public function fetch_nasa_kecepatan_angin(int $year, int $month): array
    {
        $this->log('Probe: NASA POWER mengembalikan data kosong (simulasi network failure)');
        return [];
    }

    /** Simulasi Open-Meteo mengembalikan data kosong. */
    public function fetchFromOpenMeteo(int $year, int $month): array
    {
        $this->log('Probe: Open-Meteo mengembalikan data kosong (simulasi network failure)');
        return [];
    }

    /** No-op UPSERT agar loop tahunan tidak menulis record simulasi ke DB. */
    protected function persistRecords(array $data): array
    {
        $count = count($data);
        $this->inserted += $count;
        return [$count, 0];
    }
}
