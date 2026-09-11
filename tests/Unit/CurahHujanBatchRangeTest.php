<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';

/**
 * Scraper batch tumpuan fixture (tanpa DB/network): meniru hasil per tahun.
 */
final class FixtureCurahHujanScraper extends CurahHujanScraper
{
    /** @var array<int, array> */
    public array $yearFixtures = [];

    /** @var array<int, array> */
    public array $progressCalls = [];

    public function __construct()
    {
        // Sengaja tanpa parent::__construct() agar tanpa DB/network.
    }

    public function runSingleYear($year): array
    {
        $year = (int)$year;
        if (!isset($this->yearFixtures[$year])) {
            throw new RuntimeException("Fixture tahun {$year} tidak tersedia");
        }
        return $this->yearFixtures[$year];
    }
}

final class CurahHujanBatchRangeTest extends TestCase
{
    public function testValidateRangeMenerimaRentangValid(): void
    {
        self::assertSame([], CurahHujanScraper::validateRange(2020, 2026));
        self::assertSame([], CurahHujanScraper::validateRange(2026, 2026));
    }

    public function testValidateRangeMenolakInputInvalid(): void
    {
        self::assertNotEmpty(CurahHujanScraper::validateRange(2019, 2026));
        self::assertNotEmpty(CurahHujanScraper::validateRange(2020, (int)date('Y') + 1));
        self::assertNotEmpty(CurahHujanScraper::validateRange(2024, 2020));
    }

    public function testComposeExtractSummarizeRoundtrip(): void
    {
        $failures = [
            ['year' => 2023, 'month' => 4, 'kecamatan' => 'Puger', 'error' => 'cURL timeout 45s', 'http_code' => 0],
        ];
        $message = CurahHujanScraper::composeLogMessage('Scrape tahun 2023: 10 tersimpan', $failures);
        self::assertStringContainsString('Scrape tahun 2023', $message);
        self::assertSame($failures, CurahHujanScraper::extractFailuresFromMessage($message));
        self::assertSame('Scrape tahun 2023: 10 tersimpan', CurahHujanScraper::summarizeLogMessage($message));
        self::assertSame([], CurahHujanScraper::extractFailuresFromMessage('pesan polos tanpa blok'));
    }

    public function testRunBatchRangeAgregasiLaporan(): void
    {
        $scraper = new FixtureCurahHujanScraper();
        $scraper->yearFixtures = [
            2020 => ['year' => 2020, 'status' => 'success', 'records' => 11315, 'failed' => 0, 'skipped' => 0, 'failures' => [], 'execution_time' => 1.0],
            2021 => ['year' => 2021, 'status' => 'partial', 'records' => 7500, 'failed' => 2, 'skipped' => 0, 'failures' => [
                ['year' => 2021, 'month' => null, 'kecamatan' => 'Puger', 'error' => 'HTTP status 500', 'http_code' => 500],
                ['year' => 2021, 'month' => null, 'kecamatan' => 'Kencong', 'error' => 'timeout', 'http_code' => 0],
            ], 'execution_time' => 2.0],
        ];

        $report = $scraper->runBatchRange(2020, 2021, function (int $year, array $result, int $done, int $total) use ($scraper): void {
            $scraper->progressCalls[] = [$year, $done, $total];
        });

        self::assertFalse($report['success']);
        self::assertSame(2020, $report['start_year']);
        self::assertSame(2021, $report['end_year']);
        self::assertSame(11315 + 7500, $report['total_records_saved']);
        self::assertSame(2, $report['total_records_failed']);
        self::assertSame(0, $report['total_skipped_future']);
        self::assertSame(['status' => 'success', 'records' => 11315, 'failed' => 0], $report['years_summary'][2020]);
        self::assertSame(['status' => 'partial', 'records' => 7500, 'failed' => 2], $report['years_summary'][2021]);
        self::assertCount(2, $report['failures']);
        self::assertSame([[2020, 1, 2], [2021, 2, 2]], $scraper->progressCalls);
        self::assertArrayHasKey('execution_time', $report);
    }

    public function testRunBatchRangeTahunGagalTidakMenghentikanLainnya(): void
    {
        $scraper = new FixtureCurahHujanScraper();
        $scraper->yearFixtures = [
            2021 => ['year' => 2021, 'status' => 'success', 'records' => 100, 'failed' => 0, 'skipped' => 0, 'failures' => [], 'execution_time' => 0.1],
        ];

        // 2020 tidak ada fixture -> runSingleYear melempar, wajib ditangkap per tahun.
        $report = $scraper->runBatchRange(2020, 2021);

        self::assertFalse($report['success']);
        self::assertSame('failed', $report['years_summary'][2020]['status']);
        self::assertSame('success', $report['years_summary'][2021]['status']);
        self::assertSame(100, $report['total_records_saved']);
        self::assertCount(1, $report['failures']);
        self::assertSame(2020, $report['failures'][0]['year']);
    }

    public function testRunBatchRangeMenolakRentangInvalid(): void
    {
        $scraper = new FixtureCurahHujanScraper();
        $this->expectException(InvalidArgumentException::class);
        $scraper->runBatchRange(2019, 2026);
    }
}
