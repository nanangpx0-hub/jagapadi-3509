<?php

declare(strict_types=1);

/**
 * Service analisis data storytelling produksi padi.
 *
 * Versi 2 tidak mengklaim kausalitas. Service menghasilkan indikasi hubungan
 * berbasis outcome produksi bulanan terverifikasi dan indikator lag satu bulan.
 */
class DataStoryService
{
    private const ALGORITHM_VERSION = '2.0.0';
    private const MIN_RAIN_COVERAGE = 0.70;
    private const RAIN_DRY_CRITICAL = 50.0;
    private const RAIN_IDEAL = 150.0;
    private const RAIN_WET_CRITICAL = 300.0;
    private const WEIGHT_CUACA = 0.60;
    private const WEIGHT_HAMA = 0.40;
    private const MAX_EXECUTION_TIME = 30;

    private PDO $db;
    private string $logFile;

    public function __construct(?PDO $db = null, ?string $logFile = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->logFile = $logFile ?? ROOT_PATH . '/logs/data_story_service.log';

        if (defined('PDO::MYSQL_ATTR_READ_TIMEOUT')) {
            try {
                $this->db->setAttribute(PDO::MYSQL_ATTR_READ_TIMEOUT, 10);
            } catch (Throwable) {
                // Driver may not support changing the timeout after connection.
            }
        }
        if (defined('PDO::MYSQL_ATTR_WRITE_TIMEOUT')) {
            try {
                $this->db->setAttribute(PDO::MYSQL_ATTR_WRITE_TIMEOUT, 10);
            } catch (Throwable) {
                // Driver may not support changing the timeout after connection.
            }
        }
    }

    /**
     * Menghasilkan indikasi faktor terkait, bukan kesimpulan kausal.
     */
    public function analyzeCauses(int $bulan, int $tahun, int $wilayahId): array
    {
        $startTime = microtime(true);

        try {
            $kecamatan = $this->getKecamatanInfo($wilayahId);
            if ($kecamatan === null) {
                return $this->failure(
                    'Wilayah tidak ditemukan.',
                    'InvalidRegion',
                    $startTime
                );
            }

            $produksiData = $this->getProductionData($bulan, $tahun, $wilayahId);
            if (!$produksiData['has_data']) {
                return $this->failure(
                    'Data produksi bulanan terverifikasi belum tersedia untuk periode dan wilayah ini.',
                    'InsufficientData',
                    $startTime,
                    $this->buildDataQuality($produksiData, null)
                );
            }

            $this->assertWithinExecutionTime($startTime, 'mengambil data produksi');

            $lagData = $this->getLaggingIndicators($bulan, $tahun, $wilayahId);
            $dataQuality = $this->buildDataQuality($produksiData, $lagData);

            if (!$lagData['curah_hujan']['has_data'] && !$lagData['hama']['has_data']) {
                return $this->failure(
                    'Indikator hujan dan OPT bulan sebelumnya tidak cukup untuk dianalisis.',
                    'InsufficientData',
                    $startTime,
                    $dataQuality
                );
            }

            $this->assertWithinExecutionTime($startTime, 'mengambil indikator lag');

            $skorRisiko = $this->calculateRiskScores($lagData, $produksiData);
            $faktorPenyebab = $this->determinePrimaryFactor($skorRisiko);
            $narasi = $this->generateNarrative(
                $bulan,
                $tahun,
                $kecamatan['nama_kecamatan'],
                $produksiData,
                $lagData,
                $faktorPenyebab,
                $skorRisiko,
                $dataQuality
            );

            return [
                'success' => true,
                'analysis_type' => 'indikasi_hubungan',
                'algorithm_version' => self::ALGORITHM_VERSION,
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'nama_bulan' => $this->getMonthName($bulan),
                    'wilayah_id' => $wilayahId,
                    'nama_kecamatan' => $kecamatan['nama_kecamatan'],
                ],
                'produksi_data' => $produksiData,
                'lagging_indicators' => $lagData,
                'faktor_penyebab_utama' => $faktorPenyebab,
                'skor_risiko' => $skorRisiko,
                'data_quality' => $dataQuality,
                'narasi_otomatis' => $narasi,
                'disclaimer' => 'Hasil merupakan indikasi hubungan berbasis data, bukan bukti kausalitas.',
                'execution_time' => round(microtime(true) - $startTime, 4),
            ];
        } catch (Throwable $e) {
            $this->log('Analysis failed: ' . $e->getMessage(), 'ERROR');

            return $this->failure(
                'Gagal menyelesaikan analisis.',
                'AnalysisError',
                $startTime
            );
        }
    }

    /**
     * Mengambil seluruh seri grafik dalam tiga query set-based.
     */
    public function getChartData(
        int $bulan,
        int $tahun,
        int $wilayahId,
        int $months = 6
    ): array {
        $months = max(1, min(24, $months));
        $periods = $this->buildPeriods($bulan, $tahun, $months);
        $first = $periods[0];
        $last = $periods[count($periods) - 1];

        $productionRows = $this->fetchProductionSeries(
            $first['tahun'],
            $last['tahun'],
            $wilayahId
        );

        $firstLag = $this->previousPeriod($first['bulan'], $first['tahun']);
        $lastLag = $this->previousPeriod($last['bulan'], $last['tahun']);
        $lagStart = $this->periodStart($firstLag['bulan'], $firstLag['tahun']);
        $lagEnd = $this->periodStart($lastLag['bulan'], $lastLag['tahun'])->modify('+1 month');

        $rainRows = $this->fetchRainSeries($lagStart, $lagEnd, $wilayahId);
        $pestRows = $this->fetchPestSeries($lagStart, $lagEnd, $wilayahId);

        $productionMap = $this->indexSeries($productionRows, 'luas_panen');
        $rainMap = $this->indexSeries($rainRows, 'total_curah_hujan');
        $pestMap = $this->indexSeries($pestRows, 'total_laporan');

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Luas Panen Bulanan Terverifikasi (Ha)',
                    'type' => 'bar',
                    'data' => [],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Total Curah Hujan Lag-1 (mm)',
                    'type' => 'line',
                    'data' => [],
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                ],
                [
                    'label' => 'Laporan OPT Lag-1',
                    'type' => 'line',
                    'data' => [],
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    'borderColor' => 'rgba(255, 206, 86, 1)',
                    'yAxisID' => 'y2',
                    'fill' => false,
                ],
            ],
        ];

        foreach ($periods as $period) {
            $targetKey = $this->periodKey($period['bulan'], $period['tahun']);
            $lag = $this->previousPeriod($period['bulan'], $period['tahun']);
            $lagKey = $this->periodKey($lag['bulan'], $lag['tahun']);

            $chartData['labels'][] = $this->getMonthNameShort($period['bulan'])
                . ' ' . $period['tahun'];
            $chartData['datasets'][0]['data'][] = $productionMap[$targetKey] ?? null;
            $chartData['datasets'][1]['data'][] = $rainMap[$lagKey] ?? null;
            $chartData['datasets'][2]['data'][] = $pestMap[$lagKey] ?? null;
        }

        return $chartData;
    }

    /**
     * Client hanya menentukan periode, override, dan narasi final. Seluruh data
     * serta skor dihitung ulang di server untuk menjaga integritas.
     */
    public function saveAnalysis(array $requestData, int $userId): array
    {
        $periode = $requestData['periode'] ?? null;
        if (!is_array($periode)) {
            throw new InvalidArgumentException('Periode analisis wajib diisi.');
        }

        $bulan = filter_var($periode['bulan'] ?? null, FILTER_VALIDATE_INT);
        $tahun = filter_var($periode['tahun'] ?? null, FILTER_VALIDATE_INT);
        $wilayahId = filter_var($periode['wilayah_id'] ?? null, FILTER_VALIDATE_INT);

        if ($bulan === false || $bulan < 1 || $bulan > 12) {
            throw new InvalidArgumentException('Bulan analisis tidak valid.');
        }
        if ($tahun === false || $tahun < 2000 || $tahun > ((int) date('Y') + 1)) {
            throw new InvalidArgumentException('Tahun analisis tidak valid.');
        }
        if ($wilayahId === false || $wilayahId <= 0) {
            throw new InvalidArgumentException('Wilayah analisis tidak valid.');
        }

        $analysisData = $this->analyzeCauses($bulan, $tahun, $wilayahId);
        if (!$analysisData['success']) {
            throw new DomainException($analysisData['error'] ?? 'Data tidak cukup untuk disimpan.');
        }

        $validFactors = [
            'Cuaca Ekstrem',
            'Serangan OPT',
            'Kombinasi Cuaca & OPT',
            'Normal',
            'Alih Fungsi Lahan',
            'Lainnya',
        ];
        $override = trim((string) ($requestData['faktor_penyebab_override'] ?? ''));
        if ($override !== '' && in_array($override, $validFactors, true)) {
            $analysisData['faktor_penyebab_utama'] = $override;
        }

        $narasiFinal = trim((string) ($requestData['narasi_final'] ?? ''));
        if (mb_strlen($narasiFinal) > 10000) {
            throw new InvalidArgumentException('Narasi final maksimal 10.000 karakter.');
        }
        $analysisData['narasi_final'] = $narasiFinal !== ''
            ? $narasiFinal
            : $analysisData['narasi_otomatis'];

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $existing = $this->findExistingAnalysis($bulan, $tahun, $wilayahId, true);
            $result = $existing === null
                ? $this->createAnalysis($analysisData, $userId)
                : $this->updateAnalysis((int) $existing['id'], $analysisData, $userId, $existing);

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->log('Failed to save analysis: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function publishAnalysis(int $analysisId, int $userId): bool
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM analisis_produksi_bulanan WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$analysisId]);
            $analysis = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$analysis) {
                throw new DomainException('Analisis tidak ditemukan.');
            }

            $finalNarrative = trim((string) ($analysis['narasi_final'] ?? ''));
            if ($finalNarrative === '') {
                throw new DomainException('Narasi final wajib diisi sebelum publikasi.');
            }

            $update = $this->db->prepare(
                "UPDATE analisis_produksi_bulanan
                 SET status_analisis = 'published', published_by = ?, published_at = NOW()
                 WHERE id = ?"
            );
            $update->execute([$userId, $analysisId]);

            $this->logAnalysisActivity(
                $analysisId,
                'publish',
                $analysis,
                ['status_analisis' => 'published'],
                'Analisis dipublikasikan',
                $userId
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getAnalysisById(int $analysisId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT apb.*, mk.nama_kecamatan, u.nama_lengkap AS created_by_name
             FROM analisis_produksi_bulanan apb
             LEFT JOIN master_kecamatan mk ON apb.wilayah_id = mk.id
             LEFT JOIN users u ON apb.created_by = u.id
             WHERE apb.id = ?'
        );
        $stmt->execute([$analysisId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        $result['data_quality'] = $this->decodeJsonObject($result['data_quality_json'] ?? null);
        $result['source_snapshot'] = $this->decodeJsonObject($result['source_snapshot_json'] ?? null);

        return $result;
    }

    private function getProductionData(int $bulan, int $tahun, int $wilayahId): array
    {
        $current = $this->fetchProductionPeriod($bulan, $tahun, $wilayahId);
        if (!$current['has_data']) {
            return $current + [
                'trend' => 'Tidak Ada Data',
                'perubahan_produksi_pct' => null,
                'comparison_period' => null,
                'comparison_total_produksi' => null,
            ];
        }

        $yearOverYear = $this->fetchProductionPeriod($bulan, $tahun - 1, $wilayahId);
        $comparison = $yearOverYear;
        $comparisonType = 'year_over_year';

        if (!$comparison['has_data']) {
            $previous = $this->previousPeriod($bulan, $tahun);
            $comparison = $this->fetchProductionPeriod(
                $previous['bulan'],
                $previous['tahun'],
                $wilayahId
            );
            $comparisonType = 'month_over_month';
        }

        $change = null;
        $trend = 'Belum Dapat Dibandingkan';
        if ($comparison['has_data'] && $comparison['total_produksi'] > 0) {
            $change = (($current['total_produksi'] - $comparison['total_produksi'])
                / $comparison['total_produksi']) * 100;
            $trend = $change > 1.0 ? 'Naik' : ($change < -1.0 ? 'Turun' : 'Stabil');
        }

        return $current + [
            'trend' => $trend,
            'perubahan_produksi_pct' => $change !== null ? round($change, 2) : null,
            'comparison_type' => $comparison['has_data'] ? $comparisonType : null,
            'comparison_period' => $comparison['has_data']
                ? ['bulan' => $comparison['bulan'], 'tahun' => $comparison['tahun']]
                : null,
            'comparison_total_produksi' => $comparison['has_data']
                ? $comparison['total_produksi']
                : null,
        ];
    }

    private function fetchProductionPeriod(int $bulan, int $tahun, int $wilayahId): array
    {
        $stmt = $this->db->prepare(
            "SELECT SUM(luas_panen) AS total_luas_panen,
                    SUM(produksi_total) AS total_produksi,
                    CASE
                        WHEN SUM(luas_panen) > 0
                        THEN SUM(produksi_total) / SUM(luas_panen)
                        ELSE NULL
                    END AS avg_produktivitas,
                    COUNT(*) AS jumlah_laporan,
                    MIN(created_at) AS tanggal_panen_awal,
                    MAX(updated_at) AS tanggal_panen_akhir
             FROM produksi_gabah
             WHERE tahun = ? AND bulan = ? AND kecamatan_id = ?
               AND status = 'verified'"
        );
        $stmt->execute([$tahun, $bulan, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $hasData = $result
            && $result['total_luas_panen'] !== null
            && (float) $result['total_luas_panen'] > 0;

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'total_luas_panen' => $hasData ? (float) $result['total_luas_panen'] : null,
            'total_produksi' => $hasData ? (float) $result['total_produksi'] : null,
            'avg_produktivitas' => $hasData ? (float) $result['avg_produktivitas'] : null,
            'jumlah_laporan' => $hasData ? (int) $result['jumlah_laporan'] : 0,
            'tanggal_panen_awal' => $hasData ? $result['tanggal_panen_awal'] : null,
            'tanggal_panen_akhir' => $hasData ? $result['tanggal_panen_akhir'] : null,
            'has_data' => $hasData,
            'source_status' => 'verified',
            'grain' => 'kecamatan_bulanan',
        ];
    }

    private function getLaggingIndicators(int $bulan, int $tahun, int $wilayahId): array
    {
        $lag = $this->previousPeriod($bulan, $tahun);

        return [
            'lag_periode' => [
                'bulan' => $lag['bulan'],
                'tahun' => $lag['tahun'],
                'nama_bulan' => $this->getMonthName($lag['bulan']),
            ],
            'curah_hujan' => $this->getCurahHujanLag(
                $lag['bulan'],
                $lag['tahun'],
                $wilayahId
            ),
            'hama' => $this->getHamaLag(
                $lag['bulan'],
                $lag['tahun'],
                $wilayahId
            ),
        ];
    }

    private function getCurahHujanLag(int $bulan, int $tahun, int $wilayahId): array
    {
        $start = $this->periodStart($bulan, $tahun);
        $end = $start->modify('+1 month');
        $expectedDays = (int) $start->format('t');

        $stmt = $this->db->prepare(
            "SELECT SUM(daily_rain) AS total_curah_hujan,
                    AVG(daily_rain) AS avg_harian,
                    MIN(daily_rain) AS min_harian,
                    MAX(daily_rain) AS max_harian,
                    COUNT(*) AS jumlah_hari,
                    SUM(daily_rain >= 100) AS hari_hujan_ekstrem
             FROM (
                 SELECT tanggal, AVG(curah_hujan) AS daily_rain
                 FROM curah_hujan
                 WHERE tanggal >= ? AND tanggal < ? AND kecamatan_id = ?
                   AND (satuan IS NULL OR LOWER(satuan) = 'mm')
                 GROUP BY tanggal
             ) daily"
        );
        $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d'), $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $days = (int) ($result['jumlah_hari'] ?? 0);
        $coverage = $expectedDays > 0 ? $days / $expectedDays : 0.0;
        $hasValue = $result && $result['total_curah_hujan'] !== null;
        $hasData = $hasValue && $coverage >= self::MIN_RAIN_COVERAGE;
        $totalRain = $hasValue ? (float) $result['total_curah_hujan'] : null;

        return [
            'total_curah_hujan' => $totalRain,
            // Alias sementara untuk kompatibilitas client/persistence lama.
            'avg_curah_hujan' => $totalRain,
            'avg_harian' => $hasValue ? (float) $result['avg_harian'] : null,
            'min_harian' => $hasValue ? (float) $result['min_harian'] : null,
            'max_harian' => $hasValue ? (float) $result['max_harian'] : null,
            'jumlah_hari' => $days,
            'expected_days' => $expectedDays,
            'coverage_ratio' => round($coverage, 4),
            'hari_hujan_ekstrem' => (int) ($result['hari_hujan_ekstrem'] ?? 0),
            'kategori' => $hasData && $totalRain !== null
                ? $this->categorizeCurahHujan($totalRain)
                : 'Data Tidak Lengkap',
            'has_data' => $hasData,
            'unit' => 'mm/bulan',
        ];
    }

    private function getHamaLag(int $bulan, int $tahun, int $wilayahId): array
    {
        $start = $this->periodStart($bulan, $tahun);
        $end = $start->modify('+1 month');

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total_laporan_hama,
                    SUM(lh.tingkat_keparahan = 'Berat') AS laporan_hama_berat,
                    SUM(lh.tingkat_keparahan = 'Sedang') AS laporan_hama_sedang,
                    SUM(lh.tingkat_keparahan = 'Ringan') AS laporan_hama_ringan,
                    SUM(COALESCE(lh.luas_serangan, 0)) AS total_luas_serangan,
                    SUM(COALESCE(lh.luas_serangan, 0) * CASE
                        WHEN lh.tingkat_keparahan = 'Berat' THEN 3
                        WHEN lh.tingkat_keparahan = 'Sedang' THEN 2
                        WHEN lh.tingkat_keparahan = 'Ringan' THEN 1
                        ELSE 0
                    END) AS weighted_luas_serangan,
                    GROUP_CONCAT(DISTINCT mo.nama_opt ORDER BY mo.nama_opt SEPARATOR ', ')
                        AS jenis_hama_list
             FROM laporan_hama lh
             LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
             WHERE lh.tanggal >= ? AND lh.tanggal < ? AND lh.kecamatan_id = ?
               AND lh.status IN ('Submitted', 'Diverifikasi')"
        );
        $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d'), $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int) ($result['total_laporan_hama'] ?? 0);

        return [
            'total_laporan_hama' => $total,
            'laporan_hama_berat' => (int) ($result['laporan_hama_berat'] ?? 0),
            'laporan_hama_sedang' => (int) ($result['laporan_hama_sedang'] ?? 0),
            'laporan_hama_ringan' => (int) ($result['laporan_hama_ringan'] ?? 0),
            'total_luas_serangan' => (float) ($result['total_luas_serangan'] ?? 0),
            'weighted_luas_serangan' => (float) ($result['weighted_luas_serangan'] ?? 0),
            'jenis_hama_list' => (string) ($result['jenis_hama_list'] ?? ''),
            'kategori' => $this->categorizeHamaAttack(
                $total,
                (int) ($result['laporan_hama_berat'] ?? 0)
            ),
            // Nol laporan tidak otomatis berarti nol serangan; coverage pelaporan tidak tersedia.
            'has_data' => $total > 0,
            'coverage_known' => false,
        ];
    }

    private function calculateRiskScores(array $lagData, array $produksiData = []): array
    {
        $rain = $lagData['curah_hujan'] ?? [];
        $pest = $lagData['hama'] ?? [];

        $weatherScore = null;
        if (($rain['has_data'] ?? false) && isset($rain['total_curah_hujan'])) {
            $totalRain = max(0.0, (float) $rain['total_curah_hujan']);
            if ($totalRain <= self::RAIN_IDEAL) {
                $weatherScore = min(
                    100.0,
                    ((self::RAIN_IDEAL - $totalRain)
                        / (self::RAIN_IDEAL - self::RAIN_DRY_CRITICAL)) * 70.0
                );
            } else {
                $weatherScore = min(
                    100.0,
                    (($totalRain - self::RAIN_IDEAL)
                        / (self::RAIN_WET_CRITICAL - self::RAIN_IDEAL)) * 70.0
                );
            }
        }

        $pestScore = null;
        if ($pest['has_data'] ?? false) {
            $harvestArea = (float) ($produksiData['total_luas_panen'] ?? 0);
            $weightedAffectedArea = (float) ($pest['weighted_luas_serangan'] ?? 0);
            if ($harvestArea > 0 && $weightedAffectedArea > 0) {
                $pestScore = min(100.0, ($weightedAffectedArea / $harvestArea) * 100.0);
            } else {
                $pestScore = min(
                    100.0,
                    ((int) ($pest['laporan_hama_berat'] ?? 0) * 15.0)
                    + ((int) ($pest['laporan_hama_sedang'] ?? 0) * 5.0)
                    + ((int) ($pest['laporan_hama_ringan'] ?? 0) * 2.0)
                );
            }
        }

        $weightedTotal = 0.0;
        $availableWeight = 0.0;
        if ($weatherScore !== null) {
            $weightedTotal += $weatherScore * self::WEIGHT_CUACA;
            $availableWeight += self::WEIGHT_CUACA;
        }
        if ($pestScore !== null) {
            $weightedTotal += $pestScore * self::WEIGHT_HAMA;
            $availableWeight += self::WEIGHT_HAMA;
        }

        $totalScore = $availableWeight > 0 ? $weightedTotal / $availableWeight : null;

        return [
            'skor_risiko_cuaca' => $weatherScore !== null ? (int) round($weatherScore) : null,
            'skor_risiko_hama' => $pestScore !== null ? (int) round($pestScore) : null,
            'skor_risiko_total' => $totalScore !== null ? (int) round($totalScore) : null,
            'available_weight' => round($availableWeight, 2),
        ];
    }

    private function determinePrimaryFactor(array $scores): string
    {
        $weather = $scores['skor_risiko_cuaca'] ?? null;
        $pest = $scores['skor_risiko_hama'] ?? null;

        if ($weather === null && $pest === null) {
            return 'Data Tidak Cukup';
        }
        if ($weather !== null && $pest !== null && $weather >= 40 && $pest >= 40
            && abs($weather - $pest) <= 15) {
            return 'Kombinasi Cuaca & OPT';
        }
        if ($weather !== null && $weather >= 50 && ($pest === null || $weather >= $pest)) {
            return 'Cuaca Ekstrem';
        }
        if ($pest !== null && $pest >= 50 && ($weather === null || $pest > $weather)) {
            return 'Serangan OPT';
        }

        return 'Normal';
    }

    private function generateNarrative(
        int $bulan,
        int $tahun,
        string $namaKecamatan,
        array $production,
        array $lagData,
        string $factor,
        array $scores,
        array $quality
    ): string {
        $area = number_format((float) $production['total_luas_panen'], 2, ',', '.');
        $output = number_format((float) $production['total_produksi'], 2, ',', '.');
        $trend = $production['trend'];
        $change = $production['perubahan_produksi_pct'];
        $lagName = $lagData['lag_periode']['nama_bulan'];
        $lagYear = $lagData['lag_periode']['tahun'];

        $narrative = "Pada {$this->getMonthName($bulan)} {$tahun}, produksi terverifikasi "
            . "di Kecamatan {$namaKecamatan} tercatat {$output} ton dari luas panen {$area} Ha.";

        if ($change !== null) {
            $absChange = number_format(abs((float) $change), 2, ',', '.');
            $narrative .= " Dibandingkan periode acuan, produksi {$trend} {$absChange}%.";
        } else {
            $narrative .= ' Data periode pembanding belum tersedia, sehingga perubahan produksi belum dapat dihitung.';
        }

        $rain = $lagData['curah_hujan'];
        if ($rain['has_data']) {
            $rainValue = number_format((float) $rain['total_curah_hujan'], 2, ',', '.');
            $narrative .= " Total curah hujan pada {$lagName} {$lagYear} adalah {$rainValue} mm "
                . "dengan cakupan " . number_format($rain['coverage_ratio'] * 100, 0) . '%. ';
        } else {
            $narrative .= " Data hujan {$lagName} {$lagYear} tidak cukup untuk dinilai. ";
        }

        $pest = $lagData['hama'];
        if ($pest['has_data']) {
            $narrative .= "Terdapat {$pest['total_laporan_hama']} laporan OPT tervalidasi/submitted "
                . "dengan total luas terdampak "
                . number_format((float) $pest['total_luas_serangan'], 2, ',', '.') . ' Ha. ';
        } else {
            $narrative .= 'Tidak ada laporan OPT yang memenuhi filter; kondisi ini tidak membuktikan nihil serangan. ';
        }

        $totalScore = $scores['skor_risiko_total'];
        $scoreLabel = $totalScore === null ? 'tidak tersedia' : $totalScore . '/100';
        $narrative .= "Indikasi faktor terkait: {$factor}, dengan skor risiko {$scoreLabel}. ";
        $narrative .= "Kualitas data: {$quality['level']}. Hasil ini menunjukkan asosiasi dan bukan bukti kausalitas.";

        return $narrative;
    }

    private function buildDataQuality(array $production, ?array $lagData): array
    {
        $issues = [];
        $critical = false;

        if (!($production['has_data'] ?? false)) {
            $issues[] = 'Produksi bulanan terverifikasi tidak tersedia.';
            $critical = true;
        }
        if (($production['has_data'] ?? false)
            && ($production['perubahan_produksi_pct'] ?? null) === null) {
            $issues[] = 'Periode pembanding produksi tidak tersedia.';
        }

        if ($lagData !== null) {
            if (!($lagData['curah_hujan']['has_data'] ?? false)) {
                $issues[] = 'Cakupan data hujan di bawah 70% atau tidak tersedia.';
            }
            if (!($lagData['hama']['has_data'] ?? false)) {
                $issues[] = 'Tidak ada laporan OPT yang memenuhi filter; coverage pelaporan tidak diketahui.';
            }
        }

        $level = $critical ? 'tidak_cukup' : (empty($issues) ? 'tinggi' : 'sedang');

        return [
            'level' => $level,
            'issues' => $issues,
            'minimum_rain_coverage' => self::MIN_RAIN_COVERAGE,
        ];
    }

    private function createAnalysis(array $data, int $userId): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO analisis_produksi_bulanan (
                periode_bulan, periode_tahun, wilayah_id, total_luas_panen,
                total_produksi, avg_produktivitas, perubahan_produksi_pct,
                faktor_penyebab_utama, skor_risiko_cuaca, skor_risiko_hama,
                skor_risiko_total, avg_curah_hujan_lag1, total_laporan_hama_lag1,
                laporan_hama_berat_lag1, narasi_otomatis, narasi_final,
                data_quality_json, source_snapshot_json, algorithm_version, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute($this->analysisStatementValues($data, $userId));
        $analysisId = (int) $this->db->lastInsertId();

        $this->logAnalysisActivity(
            $analysisId,
            'create',
            null,
            $data,
            'Analisis baru dibuat dengan rekalkulasi server',
            $userId
        );

        return [
            'success' => true,
            'id' => $analysisId,
            'action' => 'created',
            'message' => 'Analisis berhasil disimpan.',
        ];
    }

    private function updateAnalysis(
        int $analysisId,
        array $data,
        int $userId,
        array $oldData
    ): array {
        $stmt = $this->db->prepare(
            "UPDATE analisis_produksi_bulanan SET
                total_luas_panen = ?, total_produksi = ?, avg_produktivitas = ?,
                perubahan_produksi_pct = ?, faktor_penyebab_utama = ?,
                skor_risiko_cuaca = ?, skor_risiko_hama = ?, skor_risiko_total = ?,
                avg_curah_hujan_lag1 = ?, total_laporan_hama_lag1 = ?,
                laporan_hama_berat_lag1 = ?, narasi_otomatis = ?, narasi_final = ?,
                data_quality_json = ?, source_snapshot_json = ?, algorithm_version = ?,
                status_analisis = 'draft', published_by = NULL, published_at = NULL
             WHERE id = ?"
        );

        $values = $this->analysisStatementValues($data, $userId);
        // Remove immutable period, region, and created_by values.
        $updateValues = array_slice($values, 3, 16);
        $updateValues[] = $analysisId;
        $stmt->execute($updateValues);

        $this->logAnalysisActivity(
            $analysisId,
            'update',
            $oldData,
            $data,
            'Analisis diperbarui dan dikembalikan ke draft',
            $userId
        );

        return [
            'success' => true,
            'id' => $analysisId,
            'action' => 'updated',
            'message' => 'Analisis berhasil diperbarui.',
        ];
    }

    private function analysisStatementValues(array $data, int $userId): array
    {
        return [
            $data['periode']['bulan'],
            $data['periode']['tahun'],
            $data['periode']['wilayah_id'],
            $data['produksi_data']['total_luas_panen'],
            $data['produksi_data']['total_produksi'],
            $data['produksi_data']['avg_produktivitas'],
            $data['produksi_data']['perubahan_produksi_pct'],
            $data['faktor_penyebab_utama'],
            $data['skor_risiko']['skor_risiko_cuaca'],
            $data['skor_risiko']['skor_risiko_hama'],
            $data['skor_risiko']['skor_risiko_total'],
            $data['lagging_indicators']['curah_hujan']['total_curah_hujan'],
            $data['lagging_indicators']['hama']['total_laporan_hama'],
            $data['lagging_indicators']['hama']['laporan_hama_berat'],
            $data['narasi_otomatis'],
            $data['narasi_final'],
            json_encode($data['data_quality'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode([
                'produksi_data' => $data['produksi_data'],
                'lagging_indicators' => $data['lagging_indicators'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $data['algorithm_version'],
            $userId,
        ];
    }

    private function findExistingAnalysis(
        int $bulan,
        int $tahun,
        int $wilayahId,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM analisis_produksi_bulanan
                WHERE periode_bulan = ? AND periode_tahun = ? AND wilayah_id = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bulan, $tahun, $wilayahId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function fetchProductionSeries(int $startYear, int $endYear, int $wilayahId): array
    {
        $stmt = $this->db->prepare(
            "SELECT tahun, bulan, SUM(luas_panen) AS luas_panen
             FROM produksi_gabah
             WHERE kecamatan_id = ? AND tahun BETWEEN ? AND ?
               AND bulan IS NOT NULL AND status = 'verified'
             GROUP BY tahun, bulan"
        );
        $stmt->execute([$wilayahId, $startYear, $endYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRainSeries(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $wilayahId
    ): array {
        $stmt = $this->db->prepare(
            "SELECT YEAR(tanggal) AS tahun, MONTH(tanggal) AS bulan,
                    SUM(daily_rain) AS total_curah_hujan
             FROM (
                 SELECT tanggal, AVG(curah_hujan) AS daily_rain
                 FROM curah_hujan
                 WHERE tanggal >= ? AND tanggal < ? AND kecamatan_id = ?
                   AND (satuan IS NULL OR LOWER(satuan) = 'mm')
                 GROUP BY tanggal
             ) daily
             GROUP BY YEAR(tanggal), MONTH(tanggal)"
        );
        $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d'), $wilayahId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchPestSeries(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $wilayahId
    ): array {
        $stmt = $this->db->prepare(
            "SELECT YEAR(tanggal) AS tahun, MONTH(tanggal) AS bulan, COUNT(*) AS total_laporan
             FROM laporan_hama
             WHERE tanggal >= ? AND tanggal < ? AND kecamatan_id = ?
               AND status IN ('Submitted', 'Diverifikasi')
             GROUP BY YEAR(tanggal), MONTH(tanggal)"
        );
        $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d'), $wilayahId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function indexSeries(array $rows, string $valueField): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->periodKey((int) $row['bulan'], (int) $row['tahun']);
            $indexed[$key] = (float) $row[$valueField];
        }
        return $indexed;
    }

    private function buildPeriods(int $bulan, int $tahun, int $months): array
    {
        $last = $this->periodStart($bulan, $tahun);
        $first = $last->modify('-' . ($months - 1) . ' months');
        $periods = [];

        for ($cursor = $first; $cursor <= $last; $cursor = $cursor->modify('+1 month')) {
            $periods[] = [
                'bulan' => (int) $cursor->format('n'),
                'tahun' => (int) $cursor->format('Y'),
            ];
        }

        return $periods;
    }

    private function previousPeriod(int $bulan, int $tahun): array
    {
        $date = $this->periodStart($bulan, $tahun)->modify('-1 month');
        return ['bulan' => (int) $date->format('n'), 'tahun' => (int) $date->format('Y')];
    }

    private function periodStart(int $bulan, int $tahun): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%04d-%02d-01', $tahun, $bulan));
    }

    private function periodKey(int $bulan, int $tahun): string
    {
        return sprintf('%04d-%02d', $tahun, $bulan);
    }

    private function getKecamatanInfo(int $kecamatanId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT nama_kecamatan, kode AS kode_wilayah FROM master_kecamatan WHERE id = ?'
        );
        $stmt->execute([$kecamatanId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function categorizeCurahHujan(float $totalRain): string
    {
        if ($totalRain < self::RAIN_DRY_CRITICAL) {
            return 'Sangat Rendah';
        }
        if ($totalRain < 100) {
            return 'Rendah';
        }
        if ($totalRain <= 200) {
            return 'Normal';
        }
        if ($totalRain <= self::RAIN_WET_CRITICAL) {
            return 'Tinggi';
        }
        return 'Sangat Tinggi';
    }

    private function categorizeHamaAttack(int $totalReports, int $heavyReports): string
    {
        if ($heavyReports > 0) {
            return 'Perlu Perhatian';
        }
        if ($totalReports > 10) {
            return 'Laporan Tinggi';
        }
        if ($totalReports > 0) {
            return 'Laporan Sporadis';
        }
        return 'Tidak Ada Laporan';
    }

    private function getMonthName(int $month): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$month] ?? 'Unknown';
    }

    private function getMonthNameShort(int $month): string
    {
        return [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ][$month] ?? 'Unknown';
    }

    private function failure(
        string $message,
        string $code,
        float $startTime,
        ?array $dataQuality = null
    ): array {
        return [
            'success' => false,
            'error_code' => $code,
            'error' => $message,
            'data_quality' => $dataQuality,
            'algorithm_version' => self::ALGORITHM_VERSION,
            'execution_time' => round(microtime(true) - $startTime, 4),
        ];
    }

    private function assertWithinExecutionTime(float $startTime, string $stage): void
    {
        if ((microtime(true) - $startTime) > self::MAX_EXECUTION_TIME) {
            throw new RuntimeException("Analisis melewati batas waktu saat {$stage}.");
        }
    }

    private function decodeJsonObject(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function logAnalysisActivity(
        int $analysisId,
        string $action,
        ?array $oldValues,
        array $newValues,
        string $notes,
        int $userId
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO analisis_produksi_logs
                (analisis_id, action, old_values, new_values, notes, user_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $analysisId,
            $action,
            $oldValues !== null
                ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : null,
            json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $notes,
            $userId,
        ]);
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents(
            $this->logFile,
            sprintf('[%s] [%s] [DataStoryService] %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL),
            FILE_APPEND | LOCK_EX
        );
    }
}
