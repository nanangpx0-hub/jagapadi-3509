<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StorytellingAnalysisServiceTest extends TestCase
{
    private StorytellingAnalysisService $service;

    protected function setUp(): void
    {
        $this->service = new StorytellingAnalysisService();
    }

    public function testPerfectPositiveCorrelation(): void
    {
        $result = $this->service->analyze('correlation', $this->chart([1, 2, 3, 4, 5], [2, 4, 6, 8, 10]));
        self::assertSame(1.0, $result['metrics']['pearson_r']);
        self::assertSame('sangat_kuat', $result['metrics']['strength']);
    }

    public function testTrendProducesMovingAverage(): void
    {
        $result = $this->service->analyze('trend', $this->chart([10, 20, 30, 40, 50]), ['window' => 3]);
        self::assertSame([null, null, 20.0, 30.0, 40.0], $result['visualization']['series']['moving_average']);
        self::assertSame(400.0, $result['metrics']['change_percent']);
    }

    public function testPredictionUsesLinearBaseline(): void
    {
        $result = $this->service->analyze('predictive', $this->chart([10, 20, 30, 40, 50]), ['horizon' => 2]);
        self::assertSame([60.0, 70.0], $result['visualization']['series']['forecast']);
    }

    public function testOutlierDetectsExtremeValue(): void
    {
        $result = $this->service->analyze('outlier', $this->chart([10, 11, 9, 10, 100, 10, 11]));
        self::assertSame(1, $result['metrics']['outlier_count']);
        self::assertSame(100.0, $result['visualization']['outliers'][0]['value']);
    }

    public function testRejectsUnsupportedMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->analyze('unknown', $this->chart([1, 2, 3]));
    }

    public function testTrendRejectsSeriesWithoutMonthlyProduction(): void
    {
        $this->expectException(DomainException::class);
        $this->service->analyze('trend', $this->chart([null, null, null]));
    }

    public function testSpearmanRankCorrelationMonotonicNonLinear(): void
    {
        // Exponential relationship: strictly monotonic (Spearman = 1.0), but non-linear (Pearson < 1.0)
        $x = [1, 2, 3, 4, 5];
        $y = [1, 10, 100, 1000, 10000];
        $result = $this->service->analyze('correlation', $this->chart($x, $y), [
            'coefficient' => 'spearman',
        ]);

        self::assertSame('spearman', $result['metrics']['coefficient_type']);
        self::assertSame(1.0, $result['metrics']['correlation_coefficient']);
        self::assertSame('sangat_kuat', $result['metrics']['strength']);
        self::assertArrayHasKey('p_value', $result['metrics']);
        self::assertArrayHasKey('is_significant', $result['metrics']);
    }

    public function testCorrelationWithIrrigationVariable(): void
    {
        $prod = [10, 20, 30, 40, 50];
        $irrig = [2, 4, 6, 8, 10];
        $result = $this->service->analyze('correlation', $this->chart($prod, null, $irrig), [
            'variable' => 'irrigation',
            'coefficient' => 'pearson',
        ]);

        self::assertSame('irrigation', $result['metrics']['variable']);
        self::assertSame(1.0, $result['metrics']['correlation_coefficient']);
    }

    public function testCorrelationWithWindVariable(): void
    {
        $prod = [50, 40, 30, 20, 10];
        $wind = [5, 10, 15, 20, 25];
        $result = $this->service->analyze('correlation', $this->chart($prod, null, null, $wind), [
            'variable' => 'wind',
            'coefficient' => 'pearson',
        ]);

        self::assertSame('wind', $result['metrics']['variable']);
        self::assertSame(-1.0, $result['metrics']['correlation_coefficient']);
        self::assertSame('sangat_kuat', $result['metrics']['strength']);
    }

    public function testPredictiveProvidesRmseAndConfidenceBounds(): void
    {
        $result = $this->service->analyze('predictive', $this->chart([10, 20, 30, 40, 50]), [
            'horizon' => 3,
        ]);

        self::assertArrayHasKey('rmse', $result['metrics']);
        self::assertArrayHasKey('confidence_level', $result['metrics']);
        self::assertArrayHasKey('lower_bound', $result['visualization']['series']);
        self::assertArrayHasKey('upper_bound', $result['visualization']['series']);
        self::assertCount(3, $result['visualization']['series']['lower_bound']);
        self::assertCount(3, $result['visualization']['series']['upper_bound']);
    }

    private function chart(array $production, ?array $rain = null, ?array $irrigation = null, ?array $wind = null): array
    {
        $rain ??= $production;
        $irrigation ??= array_fill(0, count($production), 10.0);
        $wind ??= array_fill(0, count($production), 15.0);
        return [
            'labels' => array_map(static fn (int $index): string => 'P' . ($index + 1), array_keys($production)),
            'datasets' => [
                ['data' => $production],
                ['data' => $rain],
                ['data' => array_fill(0, count($production), 1)],
                ['data' => $irrigation],
                ['data' => $wind],
            ],
        ];
    }
}
