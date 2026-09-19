<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PestRiskAssessmentTest extends TestCase
{
    private PestRiskAssessmentService $service;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/services/PestRiskAssessmentService.php';
        $this->service = new PestRiskAssessmentService();
    }

    public function testAssessRiskCategorizationAndLeadTime(): void
    {
        // 1. Kondisi Risiko Rendah (Aman)
        $envLow = [
            'pest_code' => 'WBC',
            'pest_name' => 'Wereng Batang Coklat',
            'environmental_suitability_index' => 25.0,
        ];
        $resultLow = $this->service->assessRisk($envLow, null, ['max_populasi' => 2.0]);
        self::assertSame('Aman', $resultLow['tingkat_risiko']);
        self::assertFalse($resultLow['action_required']);
        self::assertLessThan(40.0, $resultLow['skor_risiko']);
        self::assertSame(72, $resultLow['lead_time_jam']);

        // 2. Kondisi Risiko Sedang (Waspada)
        $envMed = [
            'pest_code' => 'WBC',
            'pest_name' => 'Wereng Batang Coklat',
            'environmental_suitability_index' => 60.0,
        ];
        $resultMed = $this->service->assessRisk($envMed, null, ['max_populasi' => 8.0, 'total_reports' => 2]);
        self::assertSame('Waspada', $resultMed['tingkat_risiko']);
        self::assertTrue($resultMed['action_required']);
        self::assertGreaterThanOrEqual(40.0, $resultMed['skor_risiko']);
        self::assertLessThan(70.0, $resultMed['skor_risiko']);
        self::assertSame(72, $resultMed['lead_time_jam']);

        // 3. Kondisi Risiko Tinggi (Bahaya)
        $envHigh = [
            'pest_code' => 'WBC',
            'pest_name' => 'Wereng Batang Coklat',
            'environmental_suitability_index' => 88.0,
        ];
        $visionHigh = [
            'confidence' => 0.95,
            'visual_severity_index' => 95.0,
        ];
        $resultHigh = $this->service->assessRisk($envHigh, $visionHigh, ['max_populasi' => 25.0, 'total_reports' => 5, 'total_luas_ha' => 8.0], 80.0);
        self::assertSame('Bahaya', $resultHigh['tingkat_risiko']);
        self::assertTrue($resultHigh['action_required']);
        self::assertGreaterThanOrEqual(70.0, $resultHigh['skor_risiko']);
        self::assertLessThanOrEqual(48, $resultHigh['lead_time_jam']);
    }

    public function testPhtRecommendationsContent(): void
    {
        $env = [
            'pest_code' => 'WBC',
            'pest_name' => 'Wereng Batang Coklat',
            'environmental_suitability_index' => 75.0,
        ];
        $result = $this->service->assessRisk($env, null, ['max_populasi' => 15.0]);

        self::assertNotEmpty($result['rekomendasi_penanganan']);
        self::assertStringContainsString('Beauveria bassiana', implode(' ', $result['rekomendasi_penanganan']));
    }
}
