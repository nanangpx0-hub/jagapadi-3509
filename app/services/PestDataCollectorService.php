<?php

declare(strict_types=1);

/**
 * Service Agen Pengumpul Data (Data Ingestion & Cleansing Agent).
 * Mengintegrasikan data sensor lingkungan, cuaca, historis serangan hama,
 * dan citra pengawasan dengan penanganan kesalahan data yang andal.
 */
class PestDataCollectorService
{
    private PDO $db;

    // Nilai default iklim Kabupaten Jember (fallback saat imputasi)
    public const DEFAULT_TEMP = 27.5;
    public const DEFAULT_HUMIDITY = 82.0;
    public const DEFAULT_RAINFALL = 15.0;
    public const DEFAULT_WIND_SPEED = 9.5;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Sanitasi dan batas aman suhu (°C).
     */
    public function sanitizeTemperature(?float $temp): float
    {
        if ($temp === null || !is_finite($temp)) {
            return self::DEFAULT_TEMP;
        }
        if ($temp < 10.0 || $temp > 50.0) {
            return self::DEFAULT_TEMP; // Outlier recovery
        }
        return round($temp, 2);
    }

    /**
     * Sanitasi dan batas aman kelembaban relatif (RH %).
     */
    public function sanitizeHumidity(?float $humidity): float
    {
        if ($humidity === null || !is_finite($humidity)) {
            return self::DEFAULT_HUMIDITY;
        }
        if ($humidity < 20.0 || $humidity > 100.0) {
            return self::DEFAULT_HUMIDITY; // Outlier recovery
        }
        return round($humidity, 2);
    }

    /**
     * Sanitasi dan batas aman curah hujan harian (mm).
     */
    public function sanitizeRainfall(?float $rainfall): float
    {
        if ($rainfall === null || !is_finite($rainfall) || $rainfall < 0.0) {
            return 0.0;
        }
        if ($rainfall > 600.0) {
            return 600.0; // Puncak curah hujan ekstrem
        }
        return round($rainfall, 2);
    }

    /**
     * Sanitasi dan batas aman kecepatan angin (km/jam).
     */
    public function sanitizeWindSpeed(?float $speed): float
    {
        if ($speed === null || !is_finite($speed) || $speed < 0.0) {
            return self::DEFAULT_WIND_SPEED;
        }
        if ($speed > 120.0) {
            return 120.0;
        }
        return round($speed, 2);
    }

    /**
     * Mengambil profil biometeorologi terkini dan deret 7 hari per kecamatan.
     */
    public function getEnvironmentalProfile(int $kecamatanId, int $days = 7): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                tanggal, 
                AVG(suhu) as avg_suhu, 
                AVG(kelembaban) as avg_kelembaban, 
                AVG(curah_hujan) as avg_curah_hujan, 
                AVG(kecepatan_angin) as avg_angin
             FROM laporan_cuaca
             WHERE kecamatan_id = :kecamatan_id
               AND tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY tanggal
             ORDER BY tanggal DESC"
        );
        $stmt->execute([
            ':kecamatan_id' => $kecamatanId,
            ':days' => $days,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Jika data laporan_cuaca kosong atau minim, fallback ke tabel curah_hujan dan spasial imputasi
        if (empty($rows)) {
            return $this->imputeMissingWeatherData($kecamatanId, $days);
        }

        $dailySeries = [];
        $totalTemp = 0.0;
        $totalHum = 0.0;
        $totalRain = 0.0;
        $totalWind = 0.0;
        $count = count($rows);

        foreach ($rows as $r) {
            $t = $this->sanitizeTemperature($r['avg_suhu'] !== null ? (float)$r['avg_suhu'] : null);
            $h = $this->sanitizeHumidity($r['avg_kelembaban'] !== null ? (float)$r['avg_kelembaban'] : null);
            $rn = $this->sanitizeRainfall($r['avg_curah_hujan'] !== null ? (float)$r['avg_curah_hujan'] : null);
            $w = $this->sanitizeWindSpeed($r['avg_angin'] !== null ? (float)$r['avg_angin'] : null);

            $dailySeries[] = [
                'tanggal' => $r['tanggal'],
                'suhu' => $t,
                'kelembaban' => $h,
                'curah_hujan' => $rn,
                'kecepatan_angin' => $w,
            ];

            $totalTemp += $t;
            $totalHum += $h;
            $totalRain += $rn;
            $totalWind += $w;
        }

        return [
            'kecamatan_id' => $kecamatanId,
            'is_imputed' => false,
            'data_points' => $count,
            'current' => $dailySeries[0] ?? [
                'suhu' => self::DEFAULT_TEMP,
                'kelembaban' => self::DEFAULT_HUMIDITY,
                'curah_hujan' => self::DEFAULT_RAINFALL,
                'kecepatan_angin' => self::DEFAULT_WIND_SPEED,
            ],
            'averages' => [
                'suhu' => round($totalTemp / max(1, $count), 2),
                'kelembaban' => round($totalHum / max(1, $count), 2),
                'curah_hujan' => round($totalRain / max(1, $count), 2),
                'total_curah_hujan' => round($totalRain, 2),
                'kecepatan_angin' => round($totalWind / max(1, $count), 2),
            ],
            'series' => $dailySeries,
        ];
    }

    /**
     * Imputasi spasial data cuaca jika data kecamatan setempat belum tercatat.
     */
    public function imputeMissingWeatherData(int $kecamatanId, int $days = 7): array
    {
        // 1. Coba ambil rata-rata se-Kabupaten Jember dari laporan_cuaca
        $stmt = $this->db->prepare(
            "SELECT 
                tanggal, 
                AVG(suhu) as avg_suhu, 
                AVG(kelembaban) as avg_kelembaban, 
                AVG(curah_hujan) as avg_curah_hujan, 
                AVG(kecepatan_angin) as avg_angin
             FROM laporan_cuaca
             WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY tanggal
             ORDER BY tanggal DESC
             LIMIT :days_limit"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':days_limit', $days, PDO::PARAM_INT);
        $stmt->execute();
        $globalRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dailySeries = [];
        if (!empty($globalRows)) {
            $totalTemp = 0.0;
            $totalHum = 0.0;
            $totalRain = 0.0;
            $totalWind = 0.0;
            $count = count($globalRows);

            foreach ($globalRows as $r) {
                $t = $this->sanitizeTemperature($r['avg_suhu'] !== null ? (float)$r['avg_suhu'] : null);
                $h = $this->sanitizeHumidity($r['avg_kelembaban'] !== null ? (float)$r['avg_kelembaban'] : null);
                $rn = $this->sanitizeRainfall($r['avg_curah_hujan'] !== null ? (float)$r['avg_curah_hujan'] : null);
                $w = $this->sanitizeWindSpeed($r['avg_angin'] !== null ? (float)$r['avg_angin'] : null);

                $dailySeries[] = [
                    'tanggal' => $r['tanggal'],
                    'suhu' => $t,
                    'kelembaban' => $h,
                    'curah_hujan' => $rn,
                    'kecepatan_angin' => $w,
                ];

                $totalTemp += $t;
                $totalHum += $h;
                $totalRain += $rn;
                $totalWind += $w;
            }

            return [
                'kecamatan_id' => $kecamatanId,
                'is_imputed' => true,
                'imputation_source' => 'regional_average',
                'data_points' => $count,
                'current' => $dailySeries[0],
                'averages' => [
                    'suhu' => round($totalTemp / $count, 2),
                    'kelembaban' => round($totalHum / $count, 2),
                    'curah_hujan' => round($totalRain / $count, 2),
                    'total_curah_hujan' => round($totalRain, 2),
                    'kecepatan_angin' => round($totalWind / $count, 2),
                ],
                'series' => $dailySeries,
            ];
        }

        // 2. Fallback parameter klimatologis Jember
        return [
            'kecamatan_id' => $kecamatanId,
            'is_imputed' => true,
            'imputation_source' => 'climatological_baseline',
            'data_points' => 1,
            'current' => [
                'tanggal' => date('Y-m-d'),
                'suhu' => self::DEFAULT_TEMP,
                'kelembaban' => self::DEFAULT_HUMIDITY,
                'curah_hujan' => self::DEFAULT_RAINFALL,
                'kecepatan_angin' => self::DEFAULT_WIND_SPEED,
            ],
            'averages' => [
                'suhu' => self::DEFAULT_TEMP,
                'kelembaban' => self::DEFAULT_HUMIDITY,
                'curah_hujan' => self::DEFAULT_RAINFALL,
                'total_curah_hujan' => self::DEFAULT_RAINFALL * 7,
                'kecepatan_angin' => self::DEFAULT_WIND_SPEED,
            ],
            'series' => [
                [
                    'tanggal' => date('Y-m-d'),
                    'suhu' => self::DEFAULT_TEMP,
                    'kelembaban' => self::DEFAULT_HUMIDITY,
                    'curah_hujan' => self::DEFAULT_RAINFALL,
                    'kecepatan_angin' => self::DEFAULT_WIND_SPEED,
                ],
            ],
        ];
    }

    /**
     * Mengambil riwayat serangan hama terverifikasi di kecamatan dalam N hari terakhir.
     */
    public function getPestHistory(int $kecamatanId, ?int $optId = null, int $days = 30): array
    {
        $sql = "SELECT 
                    lh.id,
                    lh.nomor_laporan,
                    lh.tanggal,
                    lh.master_opt_id,
                    mo.nama_opt,
                    mo.etl_acuan,
                    mo.satuan_etl,
                    lh.tingkat_keparahan,
                    lh.luas_serangan,
                    lh.populasi,
                    lh.foto_url,
                    lh.status
                FROM laporan_hama lh
                LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
                WHERE lh.kecamatan_id = :kecamatan_id
                  AND lh.status IN ('Submitted', 'Diverifikasi')
                  AND lh.tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";

        $params = [
            ':kecamatan_id' => $kecamatanId,
            ':days' => $days,
        ];

        if ($optId !== null) {
            $sql .= " AND lh.master_opt_id = :opt_id";
            $params[':opt_id'] = $optId;
        }

        $sql .= " ORDER BY lh.tanggal DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalLuas = 0.0;
        $maxPopulasi = 0.0;
        $keparahanCount = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];

        foreach ($records as $rec) {
            $luas = (float)($rec['luas_serangan'] ?? 0);
            $pop = (float)($rec['populasi'] ?? 0);
            $totalLuas += $luas;
            if ($pop > $maxPopulasi) {
                $maxPopulasi = $pop;
            }
            $kep = $rec['tingkat_keparahan'] ?? 'Ringan';
            if (isset($keparahanCount[$kep])) {
                $keparahanCount[$kep]++;
            }
        }

        return [
            'total_reports' => count($records),
            'total_luas_ha' => round($totalLuas, 2),
            'max_populasi' => round($maxPopulasi, 2),
            'keparahan_breakdown' => $keparahanCount,
            'recent_reports' => array_slice($records, 0, 10),
        ];
    }

    /**
     * Mengambil daftar master OPT target deteksi dini.
     */
    public function getMasterOptList(): array
    {
        $stmt = $this->db->query(
            "SELECT id, kode_opt, nama_opt, nama_ilmiah, nama_lokal, jenis, 
                    etl_acuan, satuan_etl, tingkat_bahaya, rekomendasi
             FROM master_opt 
             WHERE aktif = 1 AND jenis = 'hama'
             ORDER BY nama_opt ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil daftar seluruh kecamatan di Kabupaten Jember.
     */
    public function getMasterKecamatanList(): array
    {
        $stmt = $this->db->query(
            "SELECT id, kode, nama_kecamatan 
             FROM master_kecamatan 
             ORDER BY nama_kecamatan ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
