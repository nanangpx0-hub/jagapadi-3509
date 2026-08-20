<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BpsDataLogicTest extends TestCase
{
    public function testSimulationIsReproducibleAndNeverNegativeForExtremeYear(): void
    {
        $service = new BpsSimulationService();

        $first = $service->generateData(2100, 'Jember', 'pesimis');
        $second = $service->generateData(2100, 'Jember', 'pesimis');

        self::assertSame($first, $second);
        self::assertGreaterThan(0, $first['luas_panen']);
        self::assertGreaterThan(0, $first['produksi_gabah']);
        self::assertGreaterThan(0, $first['produktivitas']);
        self::assertSame('simulasi', $first['sumber_data_type']);
    }

    public function testConversionPreservesAuthoritativeRiceProduction(): void
    {
        $service = (new ReflectionClass(BpsDataService::class))->newInstanceWithoutConstructor();
        $record = $service->applyConversions([
            'luas_panen' => 100.0,
            'produksi_gabah' => 600.0,
            'produksi_beras' => 400.0,
            'sumber_data_type' => 'ksa',
        ]);

        self::assertSame(400.0, $record['produksi_beras']);
        self::assertSame(60.0, $record['produktivitas']);
        self::assertSame('KSA BPS', $record['sumber_data']);
    }

    public function testOutlierAndInconsistentProductivityAreRejected(): void
    {
        $service = (new ReflectionClass(BpsDataService::class))->newInstanceWithoutConstructor();
        $validation = $service->validateRecord([
            'luas_panen' => 100.0,
            'produksi_gabah' => 50000.0,
            'produktivitas' => 5000.0,
        ]);

        self::assertFalse($validation['valid']);
        self::assertNotEmpty($validation['issues']);
        self::assertStringContainsString('Produktivitas', implode(' ', $validation['issues']));
    }
}
