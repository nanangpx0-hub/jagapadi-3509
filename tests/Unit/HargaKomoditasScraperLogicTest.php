<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HargaKomoditasScraperLogicTest extends TestCase
{
    public function testSimulationIsDeterministicAndExplicitlyLabelled(): void
    {
        $reflection = new ReflectionClass(HargaKomoditasScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $locations = $reflection->getProperty('locations');
        $locations->setValue($scraper, [
            ['nama_kecamatan' => 'Fixture', 'kode' => '35.09.99'],
        ]);
        $method = $reflection->getMethod('generateSimulatedData');

        $first = $method->invoke($scraper, 2025, 2);
        $second = $method->invoke($scraper, 2025, 2);

        self::assertSame($first, $second);
        self::assertCount(28 * 4, $first);
        self::assertSame('simulasi', $first[0]['metode_data']);
        self::assertSame('Simulasi', $first[0]['sumber_data']);
        self::assertSame('35.09.99', $first[0]['kode_wilayah']);
        self::assertGreaterThan(0, $first[0]['harga']);
    }

    public function testFuturePeriodAndMisleadingSourceAreRejected(): void
    {
        $reflection = new ReflectionClass(HargaKomoditasScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('validateOptions');

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($scraper, (int) date('Y'), (int) date('n'), 'bps');
    }

    public function testTargetDatesNeverSubstituteTodayForFutureMonth(): void
    {
        if ((int) date('n') === 12) {
            self::markTestSkipped('Tidak ada bulan berikutnya pada tahun berjalan.');
        }
        $reflection = new ReflectionClass(HargaKomoditasScraper::class);
        $scraper = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildTargetDates');

        self::assertSame([], $method->invoke($scraper, (int) date('Y'), (int) date('n') + 1));
    }
}
