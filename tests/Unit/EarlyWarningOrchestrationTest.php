<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EarlyWarningOrchestrationTest extends TestCase
{
    private EarlyWarningOrchestrator $orchestrator;
    private PDO $db;

    protected function setUp(): void
    {
        require_once ROOT_PATH . '/app/services/PestDataCollectorService.php';
        require_once ROOT_PATH . '/app/services/PestVisionAndPredictiveService.php';
        require_once ROOT_PATH . '/app/services/PestRiskAssessmentService.php';
        require_once ROOT_PATH . '/app/services/EarlyWarningNotificationService.php';
        require_once ROOT_PATH . '/app/services/EarlyWarningOrchestrator.php';
        require_once ROOT_PATH . '/app/models/EarlyWarningAlert.php';

        $this->db = Database::getInstance()->getConnection();
        $this->orchestrator = new EarlyWarningOrchestrator($this->db);
    }

    public function testEndToEndAssessmentForSingleKecamatan(): void
    {
        // Jalankan asesmen pada kecamatan Kaliwates (atau kecamatan valid pertama)
        $kecStmt = $this->db->query("SELECT id FROM master_kecamatan LIMIT 1");
        $kecId = (int)$kecStmt->fetchColumn();
        if ($kecId <= 0) {
            $kecId = 1;
        }

        $result = $this->orchestrator->assessKecamatan($kecId, 'WBC', null, false);

        self::assertArrayHasKey('environmental_data', $result);
        self::assertArrayHasKey('bioclimatic_prediction', $result);
        self::assertArrayHasKey('risk_assessment', $result);
        self::assertContains($result['risk_assessment']['tingkat_risiko'], ['Aman', 'Waspada', 'Bahaya']);
        self::assertGreaterThanOrEqual(0.0, $result['risk_assessment']['skor_risiko']);
        self::assertLessThanOrEqual(100.0, $result['risk_assessment']['skor_risiko']);
    }

    public function testEndToEndAssessmentWithVisualFeaturesAndDispatch(): void
    {
        $kecStmt = $this->db->query("SELECT id FROM master_kecamatan LIMIT 1");
        $kecId = (int)$kecStmt->fetchColumn() ?: 1;

        // Simulasi input citra dengan fitur wereng coklat kuat
        $visualFeatures = [
            'brown_index' => 0.70,
            'yellow_index' => 0.15,
            'green_index' => 0.15,
            'matched_patterns' => ['brown_spot', 'hopperburn', 'stem_colony'],
        ];

        $result = $this->orchestrator->assessKecamatan($kecId, 'WBC', $visualFeatures, true);

        self::assertNotNull($result['vision_analysis']);
        self::assertSame('WBC', $result['vision_analysis']['pest_code']);
        self::assertGreaterThanOrEqual(0.80, $result['vision_analysis']['confidence']);

        // Pastikan alert tersimpan jika action_required
        if ($result['risk_assessment']['action_required']) {
            self::assertNotNull($result['alert_persisted']);
            self::assertGreaterThan(0, $result['alert_persisted']['id']);
            self::assertNotNull($result['dispatch_status']);
            self::assertTrue($result['dispatch_status']['success']);
        }
    }

    public function testDashboardSummaryReturnsExpectedStructure(): void
    {
        $summary = $this->orchestrator->getDashboardSummary();

        self::assertArrayHasKey('total_active_alerts', $summary);
        self::assertArrayHasKey('alert_counts', $summary);
        self::assertArrayHasKey('recent_alerts', $summary);
        self::assertArrayHasKey('opt_catalog', $summary);
        self::assertArrayHasKey('kecamatan_list', $summary);
    }
}
