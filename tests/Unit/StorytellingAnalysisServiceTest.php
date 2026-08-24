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

    private function chart(array $production, ?array $rain = null): array
    {
        $rain ??= $production;
        return [
            'labels' => array_map(static fn (int $index): string => 'P' . ($index + 1), array_keys($production)),
            'datasets' => [
                ['data' => $production],
                ['data' => $rain],
                ['data' => array_fill(0, count($production), 1)],
            ],
        ];
    }
}
