<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvironmentalAnalysisLogicTest extends TestCase
{
    public function testBeaufortClassificationHandlesDecimalsAndNegativeInput(): void
    {
        $analytics = (new ReflectionClass(WindAnalyticsService::class))->newInstanceWithoutConstructor();

        self::assertSame(2, $analytics->convertToBeaufortScale(5.5)['scale']);
        self::assertSame(0, $analytics->convertToBeaufortScale(-3)['scale']);
        self::assertSame(12, $analytics->convertToBeaufortScale(130)['scale']);
    }

    public function testWindSimulationIsDeterministicAndStopsAtToday(): void
    {
        $reflection = new ReflectionClass(KecepatanAnginScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('locations')->setValue($scraper, [[
            'nama_kecamatan' => 'Fixture',
            'kode_bmkg_adm4' => '35.09.99',
        ]]);

        $method = $reflection->getMethod('generateSimulatedData');
        $first = $method->invoke($scraper, (int) date('Y'), (int) date('n'));
        $second = $method->invoke($scraper, (int) date('Y'), (int) date('n'));

        self::assertSame($first, $second);
        self::assertCount((int) date('j'), $first);
        self::assertSame(date('Y-m-d'), end($first)['tanggal']);
        self::assertSame('Simulasi', $first[0]['sumber_data']);
    }

    public function testRainSimulationIsDeterministicAndExplicit(): void
    {
        $reflection = new ReflectionClass(CurahHujanScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('logFile')->setValue($scraper, 'php://memory');
        $method = $reflection->getMethod('generateSimulationData');

        $first = $method->invoke($scraper, 2025, 2);
        $second = $method->invoke($scraper, 2025, 2);

        self::assertSame($first, $second);
        self::assertCount(28, $first);
        self::assertSame('Simulasi', $first[0]['sumber_data']);
        self::assertStringContainsString('bukan observasi', $first[0]['keterangan']);
    }

    public function testIrrigationSimulationIsStableAndRejectsFutureDates(): void
    {
        $fakeModel = new class {
            public array $records = [];
            public function getByDateAndLocation(): false { return false; }
            public function upsert(array $data): bool { $this->records[] = $data; return true; }
            public function logActivity(): bool { return true; }
        };

        $reflection = new ReflectionClass(IrigasiScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('model')->setValue($scraper, $fakeModel);
        $reflection->getProperty('logFile')->setValue($scraper, 'php://memory');

        $first = $scraper->run([
            'tanggal' => '2025-07-15',
            'daerah_irigasi' => 'Dam Bedadung',
            'force_refresh' => true,
        ]);
        $second = $scraper->run([
            'tanggal' => '2025-07-15',
            'daerah_irigasi' => 'Dam Bedadung',
            'force_refresh' => true,
        ]);

        self::assertTrue($first['success']);
        self::assertTrue($second['success']);
        self::assertSame($fakeModel->records[0]['debit_air'], $fakeModel->records[1]['debit_air']);
        self::assertSame('simulasi', $fakeModel->records[0]['metode_data']);
        self::assertStringContainsString('bukan hasil observasi', $fakeModel->records[0]['keterangan']);

        $future = $scraper->run(['tanggal' => date('Y-m-d', strtotime('+1 day'))]);
        self::assertFalse($future['success']);
        self::assertStringContainsString('masa depan', $future['message']);
    }
}
