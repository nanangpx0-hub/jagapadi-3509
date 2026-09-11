<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';

/** Fixture angin tanpa DB/network. */
final class FixtureAnginScraper extends KecepatanAnginScraper
{
    /** @var array<int, array> */
    public array $yearFixtures = [];

    /** @var array<int, array> */
    public array $progressCalls = [];

    public function __construct()
    {
        // Sengaja tanpa parent agar tanpa DB/network.
    }

    public function runSingleYear($year): array
    {
        $year = (int)$year;
        if (!isset($this->yearFixtures[$year])) {
            throw new RuntimeException("Fixture angin {$year} tidak tersedia");
        }
        return $this->yearFixtures[$year];
    }
}

/** Fixture harga tanpa DB/network. */
final class FixtureHargaScraper extends HargaKomoditasScraper
{
    /** @var array<string, array> key "Y-n" */
    public array $monthFixtures = [];

    /** @var array<int, array> */
    public array $progressCalls = [];

    public function __construct()
    {
        // Sengaja tanpa parent agar tanpa DB/network.
    }

    public function runSingleMonth($year, $month, string $source = 'siskaperbapo'): array
    {
        $key = ((int)$year) . '-' . ((int)$month);
        if (!isset($this->monthFixtures[$key])) {
            throw new RuntimeException("Fixture harga {$key} tidak tersedia");
        }
        return $this->monthFixtures[$key];
    }
}

final class AnginHargaBatchRangeTest extends TestCase
{
    public function testValidateRangeAnginDanHarga(): void
    {
        self::assertSame([], KecepatanAnginScraper::validateRange(2020, 2026));
        self::assertSame([], HargaKomoditasScraper::validateRange(2020, 2026));
        self::assertNotEmpty(KecepatanAnginScraper::validateRange(2019, 2026));
        self::assertNotEmpty(HargaKomoditasScraper::validateRange(2026, 2020));
        self::assertNotEmpty(HargaKomoditasScraper::validateRange(2020, (int)date('Y') + 1));
    }

    public function testBatchAnginAgregasiDanCallback(): void
    {
        $scraper = new FixtureAnginScraper();
        $scraper->yearFixtures = [
            2020 => ['year' => 2020, 'status' => 'success', 'records' => 5000, 'failed' => 0, 'skipped' => 0, 'failures' => [], 'execution_time' => 1.0],
            2021 => ['year' => 2021, 'status' => 'partial', 'records' => 3000, 'failed' => 1, 'skipped' => 5, 'failures' => [
                ['year' => 2021, 'month' => null, 'kecamatan' => 'Puger', 'error' => 'HTTP status 500', 'http_code' => 500],
            ], 'execution_time' => 2.0],
        ];

        $report = $scraper->runBatchRange(2020, 2021, function (int $year, array $result, int $done, int $total) use ($scraper): void {
            $scraper->progressCalls[] = [$year, $done, $total];
        });

        self::assertFalse($report['success']);
        self::assertSame(8000, $report['total_records_saved']);
        self::assertSame(1, $report['total_records_failed']);
        self::assertSame(5, $report['total_skipped_future']);
        self::assertSame(['status' => 'success', 'records' => 5000, 'failed' => 0], $report['years_summary'][2020]);
        self::assertSame(['status' => 'partial', 'records' => 3000, 'failed' => 1, 'skipped' => 5], $report['years_summary'][2021]);
        self::assertCount(1, $report['failures']);
        self::assertSame([[2020, 1, 2], [2021, 2, 2]], $scraper->progressCalls);
    }

    public function testBatchAnginTahunGagalTidakMenghentikanLainnya(): void
    {
        $scraper = new FixtureAnginScraper();
        $scraper->yearFixtures = [
            2021 => ['year' => 2021, 'status' => 'success', 'records' => 50, 'failed' => 0, 'skipped' => 0, 'failures' => [], 'execution_time' => 0.1],
        ];

        $report = $scraper->runBatchRange(2020, 2021);

        self::assertFalse($report['success']);
        self::assertSame('failed', $report['years_summary'][2020]['status']);
        self::assertSame('success', $report['years_summary'][2021]['status']);
        self::assertSame(50, $report['total_records_saved']);
    }

    public function testBatchHargaAgregasiPerBulanDanSkippedMasaDepan(): void
    {
        $scraper = new FixtureHargaScraper();
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');

        // Fixture hanya untuk Januari tahun berjalan.
        $scraper->monthFixtures["{$currentYear}-1"] = [
            'year' => $currentYear, 'month' => 1, 'status' => 'success',
            'records' => 8, 'failed' => 0, 'skipped' => 0, 'failures' => [], 'execution_time' => 0.5,
        ];

        $report = $scraper->runBatchRange($currentYear, $currentYear, 'siskaperbapo', function (int $year, int $month, array $result, int $done, int $total) use ($scraper): void {
            $scraper->progressCalls[] = [$year, $month, $done, $total];
        });

        $expectedMonths = $currentMonth; // bulan lalu & berjalan valid; sisanya skipped
        $expectedSkipped = 12 - $currentMonth;

        self::assertSame($currentYear, $report['start_year']);
        self::assertSame($expectedSkipped, $report['total_skipped_future']);
        self::assertArrayHasKey('skipped', $report['years_summary'][$currentYear]);
        self::assertSame($expectedSkipped, $report['years_summary'][$currentYear]['skipped']);
        self::assertCount($expectedMonths, $scraper->progressCalls);
        // Januari sukses 8 records; bulan 2..berjalan gagal (tanpa fixture) tapi tidak menghentikan.
        self::assertSame(8, $report['total_records_saved']);
        self::assertSame($expectedMonths - 1, $report['total_records_failed']);
        self::assertSame('partial', $report['years_summary'][$currentYear]['status']);
    }

    public function testBatchHargaMenolakRentangInvalid(): void
    {
        $scraper = new FixtureHargaScraper();
        $this->expectException(InvalidArgumentException::class);
        $scraper->runBatchRange(2019, 2026, 'siskaperbapo');
    }
}
