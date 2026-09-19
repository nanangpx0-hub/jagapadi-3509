<?php

declare(strict_types=1);

/**
 * Service Agen Penilaian Risiko (Risk Assessment Agent).
 * Menghitung skor risiko komposit serangan OPT berdasarkan output model computer vision,
 * prediksi biometeorologi 72 jam, populasi terhadap ambang ETL, dan sebaran spasial.
 */
class PestRiskAssessmentService
{
    public const THRESHOLD_AMAN = 40.0;
    public const THRESHOLD_WASPADA = 70.0;

    /**
     * Menghitung penilaian risiko komposit untuk suatu kecamatan dan target OPT.
     */
    public function assessRisk(
        array $envPrediction,
        ?array $visionResult = null,
        array $pestHistory = [],
        float $spatialNeighborIndex = 20.0
    ): array {
        // 1. Faktor Kesesuaian Lingkungan (ESI: 0 - 100)
        $esi = (float)($envPrediction['environmental_suitability_index'] ?? 50.0);

        // 2. Faktor Keparahan Visual Citra (VSI: 0 - 100)
        $hasVision = $visionResult !== null && !empty($visionResult['confidence']);
        $vsi = $hasVision
            ? (float)($visionResult['visual_severity_index'] ?? ($visionResult['confidence'] * 100.0))
            : 0.0;

        // 3. Faktor Populasi Historis vs Ambang Ekonomi (HPI: 0 - 100)
        $hpi = $this->calculatePopulationIndex($pestHistory, $envPrediction['pest_code'] ?? 'WBC');

        // 4. Faktor Sebaran Spasial Kecamatan Tetangga (SSI: 0 - 100)
        $ssi = min(100.0, max(0.0, $spatialNeighborIndex));

        // 5. Perhitungan Skor Komposit Tertimbang
        if ($hasVision) {
            $wEnv = 0.35;
            $wVis = 0.25;
            $wPop = 0.25;
            $wSpat = 0.15;
            $compositeScore = ($esi * $wEnv) + ($vsi * $wVis) + ($hpi * $wPop) + ($ssi * $wSpat);
        } else {
            // Re-weighting jika evaluasi preventif tanpa foto
            $wEnv = 0.45;
            $wPop = 0.35;
            $wSpat = 0.20;
            $compositeScore = ($esi * $wEnv) + ($hpi * $wPop) + ($ssi * $wSpat);
        }

        $finalScore = round(min(100.0, max(0.0, $compositeScore)), 2);

        // 6. Penentuan Kategori Tingkat Risiko
        if ($finalScore < self::THRESHOLD_AMAN) {
            $riskLevel = 'Aman';
            $actionRequired = false;
        } elseif ($finalScore < self::THRESHOLD_WASPADA) {
            $riskLevel = 'Waspada';
            $actionRequired = true;
        } else {
            $riskLevel = 'Bahaya';
            $actionRequired = true;
        }

        // 7. Estimasi Waktu Jeda Sebelum Potensi Outbreak Meluas (Lead Time Minimal 72 Jam)
        $leadTimeHours = 72;
        if ($finalScore >= 85.0) {
            $leadTimeHours = 24; // Peringatan fase kritis darurat
        } elseif ($finalScore >= 70.0) {
            $leadTimeHours = 48; // Peringatan fase eskalasi cepat
        }

        $outbreakTime = date('Y-m-d H:i:s', strtotime("+{$leadTimeHours} hours"));

        // 8. Ringkasan Ancaman & SOP Rekomendasi PHT Spesifik
        $pestCode = $envPrediction['pest_code'] ?? 'WBC';
        $pestName = $envPrediction['pest_name'] ?? 'Wereng Batang Coklat';
        $summary = $this->generateThreatSummary($pestName, $riskLevel, $finalScore, $leadTimeHours);
        $recommendations = $this->generatePhtRecommendations($pestCode, $riskLevel);

        return [
            'tingkat_risiko' => $riskLevel,
            'skor_risiko' => $finalScore,
            'action_required' => $actionRequired,
            'breakdown_skor' => [
                'faktor_cuaca' => round($esi, 2),
                'faktor_citra' => round($vsi, 2),
                'faktor_populasi' => round($hpi, 2),
                'faktor_spasial' => round($ssi, 2),
            ],
            'lead_time_jam' => $leadTimeHours,
            'prediksi_outbreak_at' => $outbreakTime,
            'ringkasan_ancaman' => $summary,
            'rekomendasi_penanganan' => $recommendations,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Menghitung rasio populasi aktual terhadap Ambang Ekonomi (ETL).
     */
    private function calculatePopulationIndex(array $history, string $pestCode): float
    {
        $maxPop = (float)($history['max_populasi'] ?? 0.0);
        $totalReports = (int)($history['total_reports'] ?? 0);
        $totalLuas = (float)($history['total_luas_ha'] ?? 0.0);

        $etlStandard = 10.0; // default WBC
        if ($pestCode === 'PBPK' || $pestCode === 'WALANG_SANGIT' || $pestCode === 'WDH') {
            $etlStandard = 5.0;
        } elseif ($pestCode === 'ULAT_GRAYAK') {
            $etlStandard = 12.5;
        }

        // Skor berdasarkan kelipatan ETL (1x ETL = 60 poin, 2x ETL = 100 poin)
        $ratio = $maxPop / max(1.0, $etlStandard);
        $popScore = min(100.0, $ratio * 50.0);

        // Tambahan bobot jika ada riwayat serangan luas baru-baru ini
        if ($totalReports >= 3) {
            $popScore += 15.0;
        }
        if ($totalLuas >= 5.0) {
            $popScore += 15.0;
        }

        return round(min(100.0, max(5.0, $popScore)), 2);
    }

    /**
     * Menyusun deskripsi ringkas ancaman.
     */
    private function generateThreatSummary(string $pestName, string $riskLevel, float $score, int $leadTime): string
    {
        if ($riskLevel === 'Aman') {
            return "Status Aman: Kondisi lingkungan dan populasi {$pestName} terkendali (Skor {$score}/100).";
        }
        if ($riskLevel === 'Waspada') {
            return "Peringatan Waspada: Terdeteksi potensi kenaikan populasi {$pestName} dalam {$leadTime} jam ke depan (Skor {$score}/100).";
        }
        return "Peringatan BAHAYA: Indikasi ancaman outbreak meluas {$pestName} dalam {$leadTime} jam ke depan (Skor {$score}/100). Diperlukan tindakan segera!";
    }

    /**
     * Rekomendasi Pengendalian Hama Terpadu (PHT) standar agronomis.
     */
    private function generatePhtRecommendations(string $pestCode, string $riskLevel): array
    {
        $base = [];
        if ($riskLevel === 'Aman') {
            return [
                '1. Lakukan pengamatan rutin mingguan pada petak sawah sampel.',
                '2. Jaga kebersihan pematang sawah dan saluran irigasi dari gulma sekunder.',
                '3. Pertahankan populasi musuh alami (laba-laba pemburu, kumbang Coccinellidae).',
            ];
        }

        switch ($pestCode) {
            case 'WBC':
                $base = [
                    '1. Pengaturan Irigasi: Lakukan pengeringan berkala (intermittent) selama 3-5 hari untuk menurunkan kelembaban di pangkal rumpun padi.',
                    '2. Pengendalian Hayati: Semprotkan jamur entomopatogen Beauveria bassiana atau Metarhizium anisopliae dengan dosis 1-2 kg/ha pada sore hari.',
                    '3. Pengendalian Kimia Terarah: Jika populasi melampaui ETL (>20 ekor/rumpun), gunakan insektisida berbahan aktif pimetrozin, buprofezin, atau triflumezopirim. Hindari piretroid sintetis yang memicu resurgensi.',
                    '4. Larangan Pemupukan Nitrogen Berlebih: Hentikan penambahan pupuk Urea yang membuat tanaman sukulen dan rentan.',
                ];
                break;
            case 'PBPK':
                $base = [
                    '1. Pemotongan & Pengumpulan: Potong dan kumpulkan pucuk tanaman yang menunjukkan gejala sundep lalu musnahkan.',
                    '2. Perangkap Cahaya: Pasang lampu perangkap (light trap) di dekat petak sawah untuk menangkap ngengat dewasa.',
                    '3. Konservasi Parasitoid: Lepaskan parasitoid telur Trichogramma japonicum sebanyak 20.000-50.000 ekor/ha.',
                    '4. Aplikasi Insektisida: Gunakan insektisida sistemik berbahan aktif klorantraniliprol atau dimehipo pada fase instar awal.',
                ];
                break;
            case 'WALANG_SANGIT':
                $base = [
                    '1. Umpan Bangkai: Pasang perangkap bau berumpan bangkai keong mas, kepiting, atau kotoran ayam di pinggir petak sawah.',
                    '2. Sanitasi Gulma: Bersihkan rumput teki dan Echinochloa di pematang yang menjadi inang alternatif sebelum fase bunting.',
                    '3. Penyemprotan Hayati: Gunakan Beauveria bassiana saat pagi hari (pukul 06.00-08.00) saat serangga aktif berkumpul di malai.',
                ];
                break;
            case 'ULAT_GRAYAK':
                $base = [
                    '1. Pemasangan Feromon: Pasang feromonoid perangkap seks Spodoptera untuk memantau populasi ngengat jantan.',
                    '2. Pengendalian Biologis: Semprotkan insektisida biologi Bacillus thuringiensis (Bt) atau Nuclear Polyhedrosis Virus (NPV).',
                    '3. Penggenangan Sawah: Genangi sawah setinggi 5-10 cm selama beberapa jam agar larva ulat merayap ke atas dan mudah dimangsa predator.',
                ];
                break;
            case 'WDH':
            default:
                $base = [
                    '1. Eradikasi Sumber Infeksi: Cabut dan benamkan tanaman yang menunjukkan gejala kerdil/Tungro agar tidak menjadi inang penularan.',
                    '2. Penggunaan Varietas Tahan: Tanam varietas padi yang tahan terhadap vektor wereng daun hijau (misal Inpari 36, Ciherang).',
                    '3. Pengendalian Vektor: Semprotkan agens hayati Beauveria bassiana atau insektisida berbahan aktif tiametoksam sesuai anjuran POPT.',
                ];
                break;
        }

        return $base;
    }
}
