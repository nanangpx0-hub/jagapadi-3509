<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BpsMonthlyHarvestPaginationDatabaseTest extends TestCase
{
    private DataKsaBulanan $model;
    private int $year;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->model = new DataKsaBulanan();
        $years = $this->model->getAvailableYears();
        if ($years === []) {
            self::markTestSkipped('Dataset KSA bulanan belum tersedia.');
        }
        $this->year = (int) $years[0];
    }

    public function testSupportedPageSizesReturnTheRequestedMaximumRows(): void
    {
        $total = $this->model->getCountWithFilters(['tahun' => $this->year]);
        self::assertGreaterThan(0, $total);

        foreach ([10, 25, 50] as $pageSize) {
            $rows = $this->model->getAll(['tahun' => $this->year], $pageSize, 0);
            self::assertCount(min($pageSize, $total), $rows);
            foreach ($rows as $row) {
                self::assertSame($this->year, (int) $row['tahun']);
                self::assertArrayHasKey('bulan', $row);
                self::assertArrayHasKey('luas_panen', $row);
                self::assertArrayHasKey('sumber_file', $row);
            }
        }
    }

    public function testChangingPageUsesASeparateOffset(): void
    {
        $firstPage = $this->model->getAll(['tahun' => $this->year], 10, 0);
        $secondPage = $this->model->getAll(['tahun' => $this->year], 10, 10);

        if (count($secondPage) === 0) {
            self::markTestSkipped('Dataset hanya memiliki satu halaman.');
        }

        self::assertNotSame((int) $firstPage[0]['id'], (int) $secondPage[0]['id']);
    }

    public function testMonthFilterAndCountUseTheSameDataset(): void
    {
        $sample = $this->model->getAll(['tahun' => $this->year], 1, 0);
        $month = (int) $sample[0]['bulan'];
        $filters = ['tahun' => $this->year, 'bulan' => $month];
        $total = $this->model->getCountWithFilters($filters);
        $rows = $this->model->getAll($filters, 50, 0);

        self::assertGreaterThan(0, $total);
        self::assertCount(min(50, $total), $rows);
        foreach ($rows as $row) {
            self::assertSame($month, (int) $row['bulan']);
        }
    }

    public function testMonthlyChartAggregationIsOrderedAndSupportsRegionalScope(): void
    {
        $province = $this->model->getMonthlyHarvestAreaChart($this->year);
        self::assertNotEmpty($province);
        self::assertLessThanOrEqual(12, count($province));
        $months = array_map(static fn (array $row): int => (int) $row['bulan'], $province);
        $sortedMonths = $months;
        sort($sortedMonths);
        self::assertSame($sortedMonths, $months);
        self::assertSame($months, array_values(array_unique($months)));
        foreach ($province as $row) {
            self::assertGreaterThanOrEqual(1, (int) $row['bulan']);
            self::assertLessThanOrEqual(12, (int) $row['bulan']);
            self::assertGreaterThanOrEqual(0.0, (float) $row['luas_panen']);
        }

        $sample = $this->model->getAll(['tahun' => $this->year], 1, 0);
        $regional = $this->model->getMonthlyHarvestAreaChart(
            $this->year,
            (string) $sample[0]['kabupaten_kota']
        );
        self::assertNotEmpty($regional);
        foreach ($regional as $row) {
            self::assertSame(1, (int) $row['jumlah_wilayah']);
        }
    }

    private function loadEnvironment(): void
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
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
