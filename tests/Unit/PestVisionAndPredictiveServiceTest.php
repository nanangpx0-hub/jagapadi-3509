<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PestVisionAndPredictiveServiceTest extends TestCase
{
    private PestVisionAndPredictiveService $service;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/services/PestVisionAndPredictiveService.php';
        $this->service = new PestVisionAndPredictiveService();
    }

    public function testComputerVisionClassificationAccuracyMeetsMinimal90Percent(): void
    {
        // 10 Kasus Uji Ekstraksi Fitur Visual Hama Padi
        $testCases = [
            // Kasus 1-2: WBC (Wereng Batang Coklat)
            [
                'expected' => 'WBC',
                'features' => [
                    'brown_index' => 0.65,
                    'yellow_index' => 0.20,
                    'green_index' => 0.15,
                    'matched_patterns' => ['brown_spot', 'hopperburn', 'stem_colony'],
                ],
            ],
            [
                'expected' => 'WBC',
                'features' => [
                    'brown_index' => 0.58,
                    'yellow_index' => 0.25,
                    'green_index' => 0.17,
                    'matched_patterns' => ['oval_body_cluster', 'stem_colony'],
                ],
            ],
            // Kasus 3-4: PBPK (Penggerek Batang Padi Kuning)
            [
                'expected' => 'PBPK',
                'features' => [
                    'yellow_index' => 0.60,
                    'brown_index' => 0.20,
                    'green_index' => 0.20,
                    'matched_patterns' => ['sundep_curled_leaf', 'white_head_beluk'],
                ],
            ],
            [
                'expected' => 'PBPK',
                'features' => [
                    'yellow_index' => 0.55,
                    'brown_index' => 0.25,
                    'green_index' => 0.20,
                    'matched_patterns' => ['yellow_moth_black_dot', 'sundep_curled_leaf'],
                ],
            ],
            // Kasus 5-6: Walang Sangit
            [
                'expected' => 'WALANG_SANGIT',
                'features' => [
                    'aspect_ratio' => 2.4,
                    'brown_index' => 0.35,
                    'green_index' => 0.35,
                    'matched_patterns' => ['slender_body', 'milky_grain_spot'],
                ],
            ],
            [
                'expected' => 'WALANG_SANGIT',
                'features' => [
                    'aspect_ratio' => 2.1,
                    'brown_index' => 0.30,
                    'green_index' => 0.40,
                    'matched_patterns' => ['brownish_green_adult', 'slender_body'],
                ],
            ],
            // Kasus 7-8: Ulat Grayak
            [
                'expected' => 'ULAT_GRAYAK',
                'features' => [
                    'texture_entropy' => 0.85,
                    'green_index' => 0.30,
                    'brown_index' => 0.20,
                    'matched_patterns' => ['chewed_leaf_skeleton', 'striped_caterpillar'],
                ],
            ],
            [
                'expected' => 'ULAT_GRAYAK',
                'features' => [
                    'texture_entropy' => 0.80,
                    'green_index' => 0.35,
                    'brown_index' => 0.25,
                    'matched_patterns' => ['nocturnal_droppings', 'chewed_leaf_skeleton'],
                ],
            ],
            // Kasus 9-10: Wereng Daun Hijau (WDH)
            [
                'expected' => 'WDH',
                'features' => [
                    'green_index' => 0.65,
                    'yellow_index' => 0.20,
                    'brown_index' => 0.15,
                    'matched_patterns' => ['bright_green_leafhopper', 'black_wing_tip'],
                ],
            ],
            [
                'expected' => 'WDH',
                'features' => [
                    'green_index' => 0.70,
                    'yellow_index' => 0.15,
                    'brown_index' => 0.15,
                    'matched_patterns' => ['upper_canopy_colony', 'bright_green_leafhopper'],
                ],
            ],
        ];

        $correct = 0;
        $total = count($testCases);

        foreach ($testCases as $tc) {
            $result = $this->service->classifyImage($tc['features']);
            if ($result['pest_code'] === $tc['expected']) {
                $correct++;
            }
            // Setiap kasus uji wajib memiliki confidence yang meyakinkan
            self::assertGreaterThanOrEqual(0.70, $result['confidence']);
        }

        $accuracy = ($correct / $total) * 100.0;

        // Validasi persyaratan: Akurasi minimal 90%
        self::assertGreaterThanOrEqual(90.0, $accuracy, "Akurasi model computer vision harus >= 90%. Hasil: {$accuracy}% ({$correct}/{$total})");
    }

    public function testEnvironmentalBioclimaticModel72hProjection(): void
    {
        // Kondisi cuaca sangat kondusif untuk Wereng Batang Coklat (Suhu 27°C, RH 90%, Hujan 25mm, Angin 8 km/j)
        $envData = [
            'current' => [
                'suhu' => 27.0,
                'kelembaban' => 90.0,
                'curah_hujan' => 25.0,
                'kecepatan_angin' => 8.0,
            ],
            'averages' => [
                'suhu' => 27.0,
                'kelembaban' => 90.0,
                'curah_hujan' => 25.0,
                'kecepatan_angin' => 8.0,
            ],
        ];

        $prediction = $this->service->predictEnvironmentalRisk($envData, 'WBC');

        self::assertSame('WBC', $prediction['pest_code']);
        self::assertGreaterThanOrEqual(75.0, $prediction['environmental_suitability_index']);
        self::assertTrue($prediction['is_high_risk_environment']);
        self::assertGreaterThan($prediction['projection_24h'], $prediction['projection_72h']);
        self::assertContains($prediction['estimated_lead_time_hours'], [24, 48, 72]);
    }

    public function testLowRiskEnvironmentProducesLowSuitability(): void
    {
        // Kondisi lingkungan dingin dan kering (Suhu 16°C, RH 45%, Hujan 0, Angin kencang 40 km/j)
        $envData = [
            'current' => [
                'suhu' => 16.0,
                'kelembaban' => 45.0,
                'curah_hujan' => 0.0,
                'kecepatan_angin' => 40.0,
            ],
            'averages' => [
                'suhu' => 16.0,
                'kelembaban' => 45.0,
                'curah_hujan' => 0.0,
                'kecepatan_angin' => 40.0,
            ],
        ];

        $prediction = $this->service->predictEnvironmentalRisk($envData, 'WBC');

        self::assertLessThan(50.0, $prediction['environmental_suitability_index']);
        self::assertFalse($prediction['is_high_risk_environment']);
        self::assertSame(72, $prediction['estimated_lead_time_hours']);
    }
}
