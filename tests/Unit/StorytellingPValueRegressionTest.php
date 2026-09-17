<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regresi: korelasi signifikansi (p-value) wajib berjalan tanpa ekstensi
 * lgamma() yang tidak tersedia di sebagian build PHP.
 */
final class StorytellingPValueRegressionTest extends TestCase
{
    private function chartData(): array
    {
        $labels = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];
        $production = [100.0, 110.0, 105.0, 120.0, 115.0, 130.0];
        $rain = [150.0, 140.0, 160.0, 120.0, 130.0, 110.0];
        $pest = [1.0, 2.0, 1.0, 3.0, 2.0, 4.0];
        $irrigation = [100.0, 105.0, 98.0, 110.0, 107.0, 112.0];
        $wind = [12.0, 14.0, 13.0, 15.0, 14.5, 16.0];

        return [
            'labels' => $labels,
            'datasets' => [
                ['data' => $production],
                ['data' => $rain],
                ['data' => $pest],
                ['data' => $irrigation],
                ['data' => $wind],
            ],
        ];
    }

    public function testPearsonCorrelationReportsPValue(): void
    {
        $service = new StorytellingAnalysisService();
        $result = $service->analyze('correlation', $this->chartData(), ['variable' => 'rain']);

        self::assertSame('correlation', $result['method']);
        self::assertArrayHasKey('p_value', $result['metrics']);
        self::assertIsFloat($result['metrics']['p_value']);
        self::assertGreaterThanOrEqual(0.0, $result['metrics']['p_value']);
        self::assertLessThanOrEqual(1.0, $result['metrics']['p_value']);
    }

    public function testSpearmanEcosystemVariablesReportPValue(): void
    {
        $service = new StorytellingAnalysisService();

        foreach (['pest', 'irrigation', 'wind'] as $variable) {
            $result = $service->analyze('correlation', $this->chartData(), [
                'variable' => $variable,
                'coefficient' => 'spearman',
            ]);

            self::assertArrayHasKey('p_value', $result['metrics'], "p_value hilang utk {$variable}");
            self::assertArrayHasKey('is_significant', $result['metrics']);
        }
    }
}
