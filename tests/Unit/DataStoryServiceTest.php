<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DataStoryServiceTest extends TestCase
{
    private DataStoryService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(DataStoryService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testMissingIndicatorsRemainUnavailable(): void
    {
        $scores = $this->riskScores(
            $this->lagData(null, false, false),
            ['total_luas_panen' => 100.0]
        );

        self::assertNull($scores['skor_risiko_cuaca']);
        self::assertNull($scores['skor_risiko_hama']);
        self::assertNull($scores['skor_risiko_total']);
        self::assertSame(0.0, $scores['available_weight']);
    }

    public function testIdealMonthlyRainfallHasZeroWeatherRisk(): void
    {
        $scores = $this->riskScores(
            $this->lagData(150.0, true, false),
            ['total_luas_panen' => 100.0]
        );

        self::assertSame(0, $scores['skor_risiko_cuaca']);
        self::assertSame(0, $scores['skor_risiko_total']);
    }

    public function testDryBoundaryIsContinuous(): void
    {
        $below = $this->riskScores(
            $this->lagData(49.99, true, false),
            ['total_luas_panen' => 100.0]
        );
        $atBoundary = $this->riskScores(
            $this->lagData(50.0, true, false),
            ['total_luas_panen' => 100.0]
        );

        self::assertLessThanOrEqual(
            1,
            abs($below['skor_risiko_cuaca'] - $atBoundary['skor_risiko_cuaca'])
        );
    }

    public function testWetBoundaryIsContinuous(): void
    {
        $atBoundary = $this->riskScores(
            $this->lagData(300.0, true, false),
            ['total_luas_panen' => 100.0]
        );
        $above = $this->riskScores(
            $this->lagData(300.01, true, false),
            ['total_luas_panen' => 100.0]
        );

        self::assertLessThanOrEqual(
            1,
            abs($atBoundary['skor_risiko_cuaca'] - $above['skor_risiko_cuaca'])
        );
    }

    public function testOutlierScoresAreCappedAtOneHundred(): void
    {
        $lag = $this->lagData(10000.0, true, true);
        $lag['hama']['weighted_luas_serangan'] = 10000.0;

        $scores = $this->riskScores($lag, ['total_luas_panen' => 1.0]);

        self::assertSame(100, $scores['skor_risiko_cuaca']);
        self::assertSame(100, $scores['skor_risiko_hama']);
        self::assertSame(100, $scores['skor_risiko_total']);
    }

    public function testPestRiskUsesWeightedAffectedArea(): void
    {
        $lag = $this->lagData(null, false, true);
        $lag['hama']['weighted_luas_serangan'] = 30.0;

        $scores = $this->riskScores($lag, ['total_luas_panen' => 100.0]);

        self::assertSame(30, $scores['skor_risiko_hama']);
        self::assertSame(30, $scores['skor_risiko_total']);
    }

    public function testBalancedHighScoresProduceCombinedFactor(): void
    {
        $factor = $this->invoke('determinePrimaryFactor', [[
            'skor_risiko_cuaca' => 65,
            'skor_risiko_hama' => 60,
            'skor_risiko_total' => 63,
        ]]);

        self::assertSame('Kombinasi Cuaca & OPT', $factor);
    }

    public function testJanuaryLagRollsBackToPreviousDecember(): void
    {
        $period = $this->invoke('previousPeriod', [1, 2026]);

        self::assertSame(['bulan' => 12, 'tahun' => 2025], $period);
    }

    private function riskScores(array $lagData, array $production): array
    {
        return $this->invoke('calculateRiskScores', [$lagData, $production]);
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflectionMethod = $this->reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($this->service, $arguments);
    }

    private function lagData(?float $rain, bool $rainHasData, bool $pestHasData): array
    {
        return [
            'curah_hujan' => [
                'total_curah_hujan' => $rain,
                'has_data' => $rainHasData,
            ],
            'hama' => [
                'has_data' => $pestHasData,
                'weighted_luas_serangan' => 0.0,
                'laporan_hama_berat' => 0,
                'laporan_hama_sedang' => 0,
                'laporan_hama_ringan' => $pestHasData ? 1 : 0,
            ],
        ];
    }
}
