<?php

declare(strict_types=1);

/**
 * Fasade Orkestrator Sistem Peringatan Dini Serangan Hama (Early Warning Orchestrator).
 * Mengorkestrasi ke-4 Agen AI:
 * 1. Agen Pengumpul Data (PestDataCollectorService)
 * 2. Agen Deteksi & Klasifikasi (PestVisionAndPredictiveService)
 * 3. Agen Penilaian Risiko (PestRiskAssessmentService)
 * 4. Agen Distribusi Peringatan (EarlyWarningNotificationService)
 */
class EarlyWarningOrchestrator
{
    private PDO $db;
    private PestDataCollectorService $collector;
    private PestVisionAndPredictiveService $detector;
    private PestRiskAssessmentService $assessor;
    private EarlyWarningNotificationService $distributor;

    public function __construct(
        ?PDO $db = null,
        ?PestDataCollectorService $collector = null,
        ?PestVisionAndPredictiveService $detector = null,
        ?PestRiskAssessmentService $assessor = null,
        ?EarlyWarningNotificationService $distributor = null
    ) {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->collector = $collector ?? new PestDataCollectorService($this->db);
        $this->detector = $detector ?? new PestVisionAndPredictiveService($this->db);
        $this->assessor = $assessor ?? new PestRiskAssessmentService();
        $this->distributor = $distributor ?? new EarlyWarningNotificationService($this->db);
    }

    /**
     * Menjalankan evaluasi terintegrasi untuk satu kecamatan dan jenis OPT.
     */
    public function assessKecamatan(
        int $kecamatanId,
        string $pestCode = 'WBC',
        $imageOrFeatures = null,
        bool $autoDispatch = true
    ): array {
        // Langkah 1: Pengumpulan & Pembersihan Data Lingkungan & Riwayat OPT
        $envData = $this->collector->getEnvironmentalProfile($kecamatanId);
        $pestHistory = $this->collector->getPestHistory($kecamatanId);

        // Langkah 2: Deteksi Citra & Prediksi Biometeorologi 72 Jam
        $visionResult = null;
        if ($imageOrFeatures !== null) {
            $visionResult = $this->detector->classifyImage($imageOrFeatures);
            // Log ke pest_detection_logs
            $this->logPestDetection($visionResult, is_string($imageOrFeatures) ? $imageOrFeatures : null);
        }
        $envPrediction = $this->detector->predictEnvironmentalRisk($envData, $pestCode);

        // Langkah 3: Penilaian Risiko Komposit & Lead Time 72 Jam
        $assessment = $this->assessor->assessRisk($envPrediction, $visionResult, $pestHistory);

        // Langkah 4: Distribusi Peringatan Otomatis Jika Risiko Waspada / Bahaya
        $dispatched = null;
        $persistedAlert = null;
        if ($assessment['action_required'] && $autoDispatch) {
            $optId = $visionResult['master_opt_id'] ?? $this->resolveOptIdByCode($pestCode);
            $persistedAlert = $this->distributor->persistAlert($assessment, $kecamatanId, $optId, null, $envData);
            $dispatched = $this->distributor->dispatchAlert($persistedAlert['id']);
        }

        return [
            'kecamatan_id' => $kecamatanId,
            'target_opt' => $pestCode,
            'environmental_data' => $envData,
            'bioclimatic_prediction' => $envPrediction,
            'vision_analysis' => $visionResult,
            'risk_assessment' => $assessment,
            'alert_persisted' => $persistedAlert,
            'dispatch_status' => $dispatched,
        ];
    }

    /**
     * Menjalankan asesmen menyeluruh untuk seluruh 31 kecamatan di Kabupaten Jember.
     */
    public function runFullRegionalAssessment(bool $autoDispatch = true): array
    {
        $kecamatanList = $this->collector->getMasterKecamatanList();
        $targetPests = ['WBC', 'PBPK', 'WALANG_SANGIT'];

        $results = [];
        $alertsGenerated = 0;

        foreach ($kecamatanList as $kec) {
            $kecId = (int)$kec['id'];
            $maxRiskScore = 0.0;
            $dominantAssessment = null;

            foreach ($targetPests as $pestCode) {
                $assessment = $this->assessKecamatan($kecId, $pestCode, null, false);
                $score = (float)$assessment['risk_assessment']['skor_risiko'];
                if ($score > $maxRiskScore) {
                    $maxRiskScore = $score;
                    $dominantAssessment = $assessment;
                }
            }

            if ($dominantAssessment !== null) {
                $riskLevel = $dominantAssessment['risk_assessment']['tingkat_risiko'];
                // Jika autoDispatch dan status Waspada/Bahaya, simpan dan kirim
                if ($autoDispatch && in_array($riskLevel, ['Waspada', 'Bahaya'], true)) {
                    $optId = $this->resolveOptIdByCode($dominantAssessment['target_opt']);
                    $savedAlert = $this->distributor->persistAlert(
                        $dominantAssessment['risk_assessment'],
                        $kecId,
                        $optId,
                        null,
                        $dominantAssessment['environmental_data']
                    );
                    $dominantAssessment['dispatch_status'] = $this->distributor->dispatchAlert($savedAlert['id']);
                    $alertsGenerated++;
                }

                $results[] = [
                    'kecamatan_id' => $kecId,
                    'nama_kecamatan' => $kec['nama_kecamatan'],
                    'highest_risk_opt' => $dominantAssessment['target_opt'],
                    'skor_risiko' => $maxRiskScore,
                    'tingkat_risiko' => $dominantAssessment['risk_assessment']['tingkat_risiko'],
                    'lead_time_jam' => $dominantAssessment['risk_assessment']['lead_time_jam'],
                    'prediksi_outbreak_at' => $dominantAssessment['risk_assessment']['prediksi_outbreak_at'],
                ];
            }
        }

        return [
            'total_kecamatan_assessed' => count($results),
            'alerts_generated' => $alertsGenerated,
            'regional_risk_overview' => $results,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Endpoint analisis citra visual mandiri (upload foto hama).
     */
    public function analyzeImageUpload(string $imagePath, ?int $kecamatanId = null, ?int $laporanHamaId = null): array
    {
        $visionResult = $this->detector->classifyImage($imagePath);
        $this->logPestDetection($visionResult, $imagePath, $laporanHamaId);

        $envPrediction = null;
        $riskAssessment = null;

        if ($kecamatanId !== null) {
            $envData = $this->collector->getEnvironmentalProfile($kecamatanId);
            $envPrediction = $this->detector->predictEnvironmentalRisk($envData, $visionResult['pest_code']);
            $pestHistory = $this->collector->getPestHistory($kecamatanId, $visionResult['master_opt_id']);
            $riskAssessment = $this->assessor->assessRisk($envPrediction, $visionResult, $pestHistory);
        }

        return [
            'vision_result' => $visionResult,
            'environmental_prediction' => $envPrediction,
            'risk_assessment' => $riskAssessment,
        ];
    }

    /**
     * Ringkasan dashboard analitik EWS.
     */
    public function getDashboardSummary(): array
    {
        // 1. Hitung alert aktif per tingkat risiko
        $stmtAlerts = $this->db->query(
            "SELECT tingkat_risiko, COUNT(*) as total 
             FROM early_warning_alerts 
             WHERE status = 'Aktif' 
             GROUP BY tingkat_risiko"
        );
        $alertCounts = ['Aman' => 0, 'Waspada' => 0, 'Bahaya' => 0];
        while ($row = $stmtAlerts->fetch(PDO::FETCH_ASSOC)) {
            $alertCounts[$row['tingkat_risiko']] = (int)$row['total'];
        }

        // 2. Daftar alert aktif terbaru
        $stmtRecent = $this->db->query(
            "SELECT ewa.*, mo.nama_opt, mk.nama_kecamatan 
             FROM early_warning_alerts ewa
             LEFT JOIN master_opt mo ON ewa.master_opt_id = mo.id
             LEFT JOIN master_kecamatan mk ON ewa.kecamatan_id = mk.id
             WHERE ewa.status = 'Aktif'
             ORDER BY ewa.skor_risiko DESC, ewa.created_at DESC
             LIMIT 10"
        );
        $recentAlerts = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        // 3. Log deteksi computer vision terbaru
        $stmtLogs = $this->db->query(
            "SELECT pdl.*, mo.nama_opt 
             FROM pest_detection_logs pdl
             LEFT JOIN master_opt mo ON pdl.master_opt_id = mo.id
             ORDER BY pdl.created_at DESC
             LIMIT 5"
        );
        $recentDetections = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_active_alerts' => array_sum($alertCounts),
            'alert_counts' => $alertCounts,
            'recent_alerts' => $recentAlerts,
            'recent_detections' => $recentDetections,
            'opt_catalog' => $this->collector->getMasterOptList(),
            'kecamatan_list' => $this->collector->getMasterKecamatanList(),
        ];
    }

    /**
     * Mencatat hasil inferensi citra ke tabel audit pest_detection_logs.
     */
    private function logPestDetection(array $visionResult, ?string $imagePath = null, ?int $laporanHamaId = null): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO pest_detection_logs (
                    laporan_hama_id, master_opt_id, predicted_opt_name,
                    confidence, image_path, visual_features, classification_model
                ) VALUES (
                    :laporan_hama_id, :master_opt_id, :predicted_opt_name,
                    :confidence, :image_path, :visual_features, :model
                )"
            );
            $stmt->execute([
                ':laporan_hama_id' => $laporanHamaId,
                ':master_opt_id' => $visionResult['master_opt_id'] ?? null,
                ':predicted_opt_name' => $visionResult['pest_name'] ?? 'OPT',
                ':confidence' => $visionResult['confidence'] ?? 0.0,
                ':image_path' => $imagePath,
                ':visual_features' => json_encode($visionResult['detected_indicators'] ?? [], JSON_UNESCAPED_UNICODE),
                ':model' => $visionResult['model_info']['name'] ?? 'Hybrid-CV-BioKlimatik-v1',
            ]);
        } catch (Throwable) {
            // Non-blocking logger
        }
    }

    /**
     * Helper resolve ID master OPT dari kode singkat.
     */
    private function resolveOptIdByCode(string $code): ?int
    {
        $profile = PestVisionAndPredictiveService::PEST_BIOCLIMATIC_PROFILES[$code] ?? null;
        if (!$profile) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM master_opt WHERE nama_opt LIKE :name LIMIT 1"
        );
        $stmt->execute([':name' => "%{$profile['name']}%"]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}
