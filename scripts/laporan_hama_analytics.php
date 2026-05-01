<?php
/**
 * Laporan Hama - Comprehensive Analysis Engine
 * Analisis komprehensif data laporan hama: pola sebaran, tren waktu,
 * tingkat kerusakan, partisipasi role, dan rekomendasi tindakan.
 * 
 * Usage: php scripts/laporan_hama_analytics.php
 * Output: JSON + HTML dashboard data
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class LaporanHamaAnalytics {
    private $db;
    private $results = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // ──────────────────────────────────────────────
    // 1. OVERVIEW & SUMMARY STATS
    // ──────────────────────────────────────────────
    public function getOverview() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_laporan,
                COUNT(DISTINCT user_id) as total_pelapor,
                COUNT(DISTINCT IF(kecamatan IS NOT NULL AND kecamatan != '', kecamatan, NULL)) as total_kecamatan,
                COUNT(DISTINCT IF(desa IS NOT NULL AND desa != '', desa, NULL)) as total_desa,
                COUNT(DISTINCT IF(kabupaten IS NOT NULL AND kabupaten != '', kabupaten, NULL)) as total_kabupaten,
                COUNT(DISTINCT IF(master_opt_id IS NOT NULL, master_opt_id, NULL)) as total_opt,
                MIN(tanggal) as earliest_date,
                MAX(tanggal) as latest_date,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas_serangan,
                AVG(IF(luas_serangan IS NOT NULL, luas_serangan, NULL)) as avg_luas_serangan,
                SUM(IF(populasi IS NOT NULL, populasi, 0)) as total_populasi
            FROM laporan_hama
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Status breakdown
        $statusStmt = $this->db->query("
            SELECT status, COUNT(*) as jumlah
            FROM laporan_hama GROUP BY status
        ");
        $statusBreakdown = [];
        while ($r = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
            $statusBreakdown[$r['status']] = (int)$r['jumlah'];
        }

        // Severity breakdown
        $severityStmt = $this->db->query("
            SELECT tingkat_keparahan, COUNT(*) as jumlah,
                   SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas
            FROM laporan_hama GROUP BY tingkat_keparahan
        ");
        $severityBreakdown = [];
        while ($r = $severityStmt->fetch(PDO::FETCH_ASSOC)) {
            $severityBreakdown[$r['tingkat_keparahan']] = [
                'jumlah' => (int)$r['jumlah'],
                'total_luas' => (float)$r['total_luas']
            ];
        }

        $this->results['overview'] = [
            'summary' => [
                'total_laporan' => (int)$row['total_laporan'],
                'total_pelapor' => (int)$row['total_pelapor'],
                'total_kecamatan' => (int)$row['total_kecamatan'],
                'total_desa' => (int)$row['total_desa'],
                'total_opt' => (int)$row['total_opt'],
                'total_luas_serangan' => round((float)$row['total_luas_serangan'], 2),
                'avg_luas_serangan' => round((float)$row['avg_luas_serangan'], 2),
                'total_populasi' => (int)$row['total_populasi'],
                'date_range' => [
                    'earliest' => $row['earliest_date'],
                    'latest' => $row['latest_date']
                ]
            ],
            'status_breakdown' => $statusBreakdown,
            'severity_breakdown' => $severityBreakdown
        ];
        return $this->results['overview'];
    }

    // ──────────────────────────────────────────────
    // 2. TREN WAKTU (Bulanan & Musiman)
    // ──────────────────────────────────────────────
    public function getTimeTrends() {
        // Monthly trend (last 24 months)
        $monthlyStmt = $this->db->query("
            SELECT 
                DATE_FORMAT(tanggal, '%Y-%m') as bulan,
                COUNT(*) as jumlah_laporan,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas,
                SUM(CASE WHEN tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                SUM(CASE WHEN tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                SUM(CASE WHEN tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan
            FROM laporan_hama
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
            GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
            ORDER BY bulan ASC
        ");
        $monthly = [];
        while ($r = $monthlyStmt->fetch(PDO::FETCH_ASSOC)) {
            $monthly[] = [
                'bulan' => $r['bulan'],
                'jumlah' => (int)$r['jumlah_laporan'],
                'total_luas' => round((float)$r['total_luas'], 2),
                'berat' => (int)$r['berat'],
                'sedang' => (int)$r['sedang'],
                'ringan' => (int)$r['ringan']
            ];
        }

        // Seasonality (month distribution)
        $seasonStmt = $this->db->query("
            SELECT 
                MONTH(tanggal) as bulan_num,
                MONTHNAME(tanggal) as bulan_nama,
                COUNT(*) as jumlah,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas
            FROM laporan_hama
            GROUP BY MONTH(tanggal), MONTHNAME(tanggal)
            ORDER BY MONTH(tanggal)
        ");
        $seasonality = [];
        $bulanNames = [1=>'Januari','Februari','Maret','April','Mei','Juni',
                        'Juli','Agustus','September','Oktober','November','Desember'];
        while ($r = $seasonStmt->fetch(PDO::FETCH_ASSOC)) {
            $seasonality[(int)$r['bulan_num']] = [
                'bulan' => $bulanNames[(int)$r['bulan_num']],
                'jumlah' => (int)$r['jumlah'],
                'total_luas' => round((float)$r['total_luas'], 2)
            ];
        }

        // Musim: bulan 1-3 (Musi
                $musimStmt = $this->db->query("
            SELECT 
                CASE 
                    WHEN MONTH(tanggal) IN (1,2,3,11,12) THEN 'Musim Hujan'
                    WHEN MONTH(tanggal) IN (4,5,6) THEN 'Peralihan Awal'
                    WHEN MONTH(tanggal) IN (7,8,9,10) THEN 'Musim Kemarau'
                    ELSE 'Peralihan Akhir'
                END as musim,
                COUNT(*) as jumlah_laporan,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas
            FROM laporan_hama
            GROUP BY CASE 
                    WHEN MONTH(tanggal) IN (1,2,3,11,12) THEN 'Musim Hujan'
                    WHEN MONTH(tanggal) IN (4,5,6) THEN 'Peralihan Awal'
                    WHEN MONTH(tanggal) IN (7,8,9,10) THEN 'Musim Kemarau'
                    ELSE 'Peralihan Akhir'
                END
        ");
        $season = [];
        while ($r = $musimStmt->fetch(PDO::FETCH_ASSOC)) {
            $season[] = [
                'musim' => $r['musim'],
                'jumlah' => (int)$r['jumlah_laporan'],
                'total_luas' => round((float)$r['total_luas'], 2)
            ];
        }

        $this->results['time_trends'] = [
            'monthly' => $monthly,
            'seasonality' => $seasonality,
            'season' => $season
        ];
        return $this->results['time_trends'];
    }

    // ──────────────────────────────────────────────
    // 3. DISTRIBUSI GEOGRAFIS (Kecamatan & Desa)
    // ──────────────────────────────────────────────
    public function getGeographicDistribution() {
        // Top kecamatan by jumlah laporan
        $kecStmt = $this->db->query("
            SELECT 
                kecamatan as nama,
                COUNT(*) as jumlah,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas,
                SUM(CASE WHEN tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                SUM(CASE WHEN tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                SUM(CASE WHEN tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan,
                ROUND(AVG(IF(luas_serangan IS NOT NULL, luas_serangan, NULL)), 2) as avg_luas
            FROM laporan_hama
            WHERE kecamatan IS NOT NULL AND kecamatan != ''
            GROUP BY kecamatan
            ORDER BY jumlah DESC
            LIMIT 20
        ");
        $top_kecamatan = [];
        while ($r = $kecStmt->fetch(PDO::FETCH_ASSOC)) {
            $top_kecamatan[] = [
                'nama' => $r['nama'],
                'jumlah' => (int)$r['jumlah'],
                'total_luas' => round((float)$r['total_luas'], 2),
                'avg_luas' => round((float)($r['avg_luas'] ?? 0), 2),
                'berat' => (int)$r['berat'],
                'sedang' => (int)$r['sedang'],
                'ringan' => (int)$r['ringan']
            ];
        }

        // Top desa
        $desaStmt = $this->db->query("
            SELECT 
                CONCAT(desa, ' - ', kecamatan) as nama,
                COUNT(*) as jumlah,
                SUM(IF(luas_serangan IS NOT NULL, luas_serangan, 0)) as total_luas
            FROM laporan_hama
            WHERE desa IS NOT NULL AND desa != '' AND kecamatan IS NOT NULL AND kecamatan != ''
            GROUP BY desa, kecamatan
            ORDER BY jumlah DESC
            LIMIT 20
        ");
        $top_desa = [];
        while ($r = $desaStmt->fetch(PDO::FETCH_ASSOC)) {
            $top_desa[] = [
                'nama' => $r['nama'],
                'jumlah' => (int)$r['jumlah'],
                'total_luas' => round((float)$r['total_luas'], 2)
            ];
        }

        $this->results['geographic'] = [
            'top_kecamatan' => $top_kecamatan,
            'top_desa' => $top_desa
        ];
        return $this->results['geographic'];
    }

    // ──────────────────────────────────────────────
    // 4. KATEGORI HAMA DOMINAN (OPT)
    // ──────────────────────────────────────────────
    public function getDominantPestCategories() {
        // Top OPT
        $optStmt = $this->db->query("
            SELECT 
                mo.nama_opt as nama,
                mo.jenis as jenis,
                COUNT(lh.id) as jumlah_laporan,
                SUM(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, 0)) as total_luas,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan,
                ROUND(AVG(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, NULL)), 2) as avg_luas
            FROM laporan_hama lh
            LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
            WHERE lh.master_opt_id IS NOT NULL
            GROUP BY mo.id, mo.nama_opt, mo.jenis
            ORDER BY jumlah_laporan DESC
            LIMIT 15
        ");
        $top_opt = [];
        while ($r = $optStmt->fetch(PDO::FETCH_ASSOC)) {
            $top_opt[] = [
                'nama' => $r['nama'] ?? 'Tidak Diketahui',
                'jenis' => $r['jenis'] ?? '-',
                'jumlah' => (int)$r['jumlah_laporan'],
                'total_luas' => round((float)$r['total_luas'], 2),
                'avg_luas' => round((float)($r['avg_luas'] ?? 0), 2),
                'berat' => (int)$r['berat'],
                'sedang' => (int)$r['sedang'],
                'ringan' => (int)$r['ringan']
            ];
        }

        // Hama type distribution
        $jenisStmt = $this->db->query("
            SELECT 
                mo.jenis as jenis,
                COUNT(*) as jumlah,
                SUM(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, 0)) as total_luas
            FROM laporan_hama lh
            LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
            WHERE lh.master_opt_id IS NOT NULL AND mo.jenis IS NOT NULL
            GROUP BY mo.jenis
            ORDER BY jumlah DESC
        ");
        $jenis_opt = [];
        while ($r = $jenisStmt->fetch(PDO::FETCH_ASSOC)) {
            $jenis_opt[] = [
                'jenis' => $r['jenis'],
                'jumlah' => (int)$r['jumlah'],
                'total_luas' => round((float)$r['total_luas'], 2)
            ];
        }

        $this->results['pest_categories'] = [
            'top_opt' => $top_opt,
            'jenis_opt' => $jenis_opt
        ];
        return $this->results['pest_categories'];
    }

    // ──────────────────────────────────────────────
    // 5. ANALISIS PER ROLE PENGGUNA
    // ──────────────────────────────────────────────
    public function getRoleAnalysis() {
        // Per role: jumlah laporan, luas serangan, rata-rata
        $roleStmt = $this->db->query("
            SELECT 
                u.role as role,
                COUNT(lh.id) as jumlah_laporan,
                SUM(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, 0)) as total_luas,
                ROUND(AVG(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, NULL)), 2) as avg_luas,
                SUM(CASE WHEN lh.status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN lh.status = 'Diverifikasi' THEN 1 ELSE 0 END) as diverifikasi,
                SUM(CASE WHEN lh.status = 'Ditolak' THEN 1 ELSE 0 END) as ditolak,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Berat' THEN 1 ELSE 0 END) as berat,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Sedang' THEN 1 ELSE 0 END) as sedang,
                SUM(CASE WHEN lh.tingkat_keparahan = 'Ringan' THEN 1 ELSE 0 END) as ringan
            FROM users u
            LEFT JOIN laporan_hama lh ON u.id = lh.user_id
            WHERE u.role IS NOT NULL
            GROUP BY u.role
            ORDER BY jumlah_laporan DESC
        ");
        $roleStats = [];
        while ($r = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
            $roleStats[$r['role']] = [
                'jumlah_laporan' => (int)$r['jumlah_laporan'],
                'total_luas' => round((float)$r['total_luas'], 2),
                'avg_luas' => round((float)($r['avg_luas'] ?? 0), 2),
                'submitted' => (int)$r['submitted'],
                'diverifikasi' => (int)$r['diverifikasi'],
                'ditolak' => (int)$r['ditolak'],
                'berat' => (int)$r['berat'],
                'sedang' => (int)$r['sedang'],
                'ringan' => (int)$r['ringan']
            ];
        }

        // Top reporters per role
        $topReportersStmt = $this->db->query("
            SELECT 
                u.id,
                u.nama_lengkap as nama,
                u.role,
                COUNT(lh.id) as jumlah_laporan,
                SUM(IF(lh.luas_serangan IS NOT NULL, lh.luas_serangan, 0)) as total_luas,
                MAX(lh.tanggal) as last_report
            FROM users u
            LEFT JOIN laporan_hama lh ON u.id = lh.user_id
            WHERE u.role IS NOT NULL AND lh.id IS NOT NULL
            GROUP BY u.id, u.nama_lengkap, u.role
            ORDER BY jumlah_laporan DESC
            LIMIT 15
        ");
        $top_reporters = [];
        while ($r = $topReportersStmt->fetch(PDO::FETCH_ASSOC)) {
            $top_reporters[] = [
                'id' => (int)$r['id'],
                'nama' => $r['nama'],
                'role' => $r['role'],
                'jumlah' => (int)$r['jumlah_laporan'],
                'total_luas' => round((float)$r['total_luas'], 2),
                'last_report' => $r['last_report']
            ];
        }

        // Role contribution percentage
        $totalStmt = $this->db->query("SELECT COUNT(DISTINCT user_id) as total FROM laporan_hama WHERE user_id IS NOT NULL");
        $totalUsers = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $totalStmt2 = $this->db->query("SELECT COUNT(*) as total FROM laporan_hama WHERE user_id IS NOT NULL");
        $total = (int)$totalStmt2->fetch(PDO::FETCH_ASSOC)['total'];
        
        $roleContribution = [];
        foreach ($roleStats as $role => $stats) {
            $roleContribution[$role] = [
                'jumlah' => $stats['jumlah_laporan'],
                'percentage' => $total > 0 ? round($stats['jumlah_laporan'] / $total * 100, 1) : 0
            ];
        }

        $this->results['role_analysis'] = [
            'role_stats' => $roleStats,
            'top_reporters' => $top_reporters,
            'role_contribution' => $roleContribution,
            'total_laporan' => $total
        ];
        return $this->results['role_analysis'];
    }

    // ──────────────────────────────────────────────
    // 6. VERIFIKASI & RESPON TIME
    // ──────────────────────────────────────────────
    public function getVerificationAnalysis() {
        // Average verification time
        $verifyStmt = $this->db->query("
            SELECT 
                u.role as verifier_role,
                COUNT(*) as total_verified,
                AVG(TIMESTAMPDIFF(HOUR, lh.created_at, lh.verified_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, lh.created_at, lh.verified_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, lh.created_at, lh.verified_at)) as max_hours
            FROM laporan_hama lh
            LEFT JOIN users u ON lh.verified_by = u.id
            WHERE lh.verified_at IS NOT NULL AND lh.verified_by IS NOT NULL
            GROUP BY u.role
        ");
        $verificationTime = [];
        while ($r = $verifyStmt->fetch(PDO::FETCH_ASSOC)) {
            $verificationTime[$r['verifier_role']] = [
                'total_verified' => (int)$r['total_verified'],
                'avg_hours' => round((float)($r['avg_hours'] ?? 0), 1),
                'min_hours' => (int)($r['min_hours'] ?? 0),
                'max_hours' => (int)($r['max_hours'] ?? 0)
            ];
        }

        // Auto-approve vs manual verify
        $autoApproveStmt = $this->db->query("
            SELECT 
                COUNT(CASE WHEN catatan_verifikasi = 'AutoApprove' OR verified_by IS NULL THEN 1 END) as auto_approve,
                COUNT(CASE WHEN verified_by IS NOT NULL AND catatan_verifikasi != 'AutoApprove' THEN 1 END) as manual_verify,
                COUNT(CASE WHEN verified_by IS NULL AND catatan_verifikasi != 'AutoApprove' THEN 1 END) as belum_diverifikasi
            FROM laporan_hama
        ");
        $r = $autoApproveStmt->fetch(PDO::FETCH_ASSOC);

        $this->results['verification'] = [
            'verification_time' => $verificationTime,
            'auto_vs_manual' => [
                'auto_approve' => (int)$r['auto_approve'],
                'manual_verify' => (int)$r['manual_verify'],
                'belum_diverifikasi' => (int)$r['belum_diverifikasi']
            ]
        ];
        return $this->results['verification'];
    }

    // ──────────────────────────────────────────────
    // 7. DATA UNTUK CHART (siap JSON)
    // ──────────────────────────────────────────────
    public function getChartData() {
        $chart = [];

        // Chart 1: Trend laporan bulanan
        if (isset($this->results['time_trends']['monthly'])) {
            $chart['monthlyTrend'] = [
                'labels' => array_column($this->results['time_trends']['monthly'], 'bulan'),
                'datasets' => [
                    [
                        'label' => 'Jumlah Laporan',
                        'data' => array_column($this->results['time_trends']['monthly'], 'jumlah'),
                        'borderColor' => '#3498db',
                        'backgroundColor' => 'rgba(52,152,219,0.1)'
                    ],
                    [
                        'label' => 'Total Luas Serangan (Ha)',
                        'data' => array_column($this->results['time_trends']['monthly'], 'total_luas'),
                        'borderColor' => '#e74c3c',
                        'backgroundColor' => 'rgba(231,76,60,0.1)',
                        'yAxisID' => 'y1'
                    ]
                ]
            ];
        }

        // Chart 2: Severity distribution
        $sev = $this->results['overview']['severity_breakdown'] ?? [];
        $sevLabels = array_keys($sev);
        $sevJumlah = array_column($sev, 'jumlah');
        $chart['severityDistribution'] = [
            'labels' => $sevLabels,
            'datasets' => [[
                'label' => 'Jumlah Laporan',
                'data' => $sevJumlah,
                'backgroundColor' => ['#27ae60', '#f39c12', '#e74c3c'],
                'borderWidth' => 2
            ]]
        ];

        // Chart 3: Status breakdown (pie)
        $stat = $this->results['overview']['status_breakdown'] ?? [];
        $chart['statusBreakdown'] = [
            'labels' => array_keys($stat),
            'datasets' => [[
                'data' => array_values($stat),
                'backgroundColor' => ['#3498db', '#2ecc71', '#e74c3c', '#95a5a6'],
                'borderWidth' => 2
            ]]
        ];

        // Chart 4: Season distribution
        $seasonData = $this->results['time_trends']['season'] ?? [];
        $chart['seasonDistribution'] = [
            'labels' => array_column($seasonData, 'musim'),
            'datasets' => [[
                'label' => 'Jumlah Laporan',
                'data' => array_column($seasonData, 'jumlah'),
                'backgroundColor' => ['#3498db', '#f39c12', '#e74c3c', '#9b59b6']
            ]]
        ];

        // Chart 5: Top 10 OPT
        $optData = array_slice($this->results['pest_categories']['top_opt'] ?? [], 0, 10);
        $chart['topOpt'] = [
            'labels' => array_column($optData, 'nama'),
            'datasets' => [[
                'label' => 'Jumlah Laporan',
                'data' => array_column($optData, 'jumlah'),
                'backgroundColor' => '#2ecc71'
            ]]
        ];

        // Chart 6: Top 10 Kecamatan
        $kecData = array_slice($this->results['geographic']['top_kecamatan'] ?? [], 0, 10);
        $chart['topKecamatan'] = [
            'labels' => array_column($kecData, 'nama'),
            'datasets' => [[
                'label' => 'Jumlah Laporan',
                'data' => array_column($kecData, 'jumlah'),
                'backgroundColor' => '#9b59b6'
            ]]
        ];

        // Chart 7: Role contribution (bar horizontal)
        $roleStats = $this->results['role_analysis']['role_stats'] ?? [];
        $roleLabels = array_keys($roleStats);
        $roleJumlah = array_column($roleStats, 'jumlah_laporan');
        $chart['roleContribution'] = [
            'labels' => $roleLabels,
            'datasets' => [[
                'label' => 'Jumlah Laporan',
                'data' => $roleJumlah,
                'backgroundColor' => ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6']
            ]]
        ];

        // Chart 8: Verifikasi stat
        $verify = $this->results['verification']['auto_vs_manual'] ?? [];
        $chart['verificationStat'] = [
            'labels' => ['Auto Approve', 'Manual Verifikasi', 'Belum Diverifikasi'],
            'datasets' => [[
                'data' => [$verify['auto_approve'] ?? 0, $verify['manual_verify'] ?? 0, $verify['belum_diverifikasi'] ?? 0],
                'backgroundColor' => ['#27ae60', '#3498db', '#e74c3c']
            ]]
        ];

        $this->results['chart_data'] = $chart;
        return $chart;
    }

    // ──────────────────────────────────────────────
    // 8. REKOMENDASI BERBASIS DATA
    // ──────────────────────────────────────────────
    public function getRecommendations() {
        $recommendations = [];

        // R1: Identifikasi kecamatan dengan laporan tertinggi (prioritas pemantauan)
        if (!empty($this->results['geographic']['top_kecamatan'])) {
            $topKec = $this->results['geographic']['top_kecamatan'][0];
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'Pengawasan Prioritas',
                'title' => 'Kecamatan ' . $topKec['nama'] . ' memiliki laporan tertinggi',
                'description' => 'Dengan ' . $topKec['jumlah'] . ' laporan dan total luas serangan ' . $topKec['total_luas'] . ' Ha, '
                    . 'kecamatan ini perlu mendapatkan perhatian lebih dalam pemantauan OPT. '
                    . 'Disarankan penambahan frekuensi monitoring dan koordinasi dengan POPT lokal.',
                'action' => 'Tingkatkan frekuensi patrolOPT di kecamatan ' . $topKec['nama'] . ' minimal 2x seminggu.',
                'metrics' => [
                    'jumlah_laporan' => $topKec['jumlah'],
                    'total_luas' => $topKec['total_luas'],
                    'avg_luas' => $topKec['avg_luas']
                ]
            ];
        }

        // R2: Musim高峰期
        $seasonData = $this->results['time_trends']['season'] ?? [];
        usort($seasonData, fn($a, $b) => $b['jumlah'] <=> $a['jumlah']);
        if (!empty($seasonData)) {
            $peakSeason = $seasonData[0];
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'Perencanaan Musim',
                'title' => 'Puncak laporan terjadi pada musim ' . $peakSeason['musim'],
                'description' => 'Sebanyak ' . $peakSeason['jumlah'] . ' laporan tercatat pada musim ini dengan total '
                    . 'luas serangan ' . $peakSeason['total_luas'] . ' Ha. '
                    . 'Perlu persiapan logistik pengendalian dini sebelum musim peak.',
                'action' => 'Siapkan stok pestisida dan jadwal koordinasi POPT sebelum memasuki musim ' . $peakSeason['musim'],
                'metrics' => [
                    'musim' => $peakSeason['musim'],
                    'jumlah' => $peakSeason['jumlah']
                ]
            ];
        }

        // R3: OPT dominan
        if (!empty($this->results['pest_categories']['top_opt'])) {
            $topOpt = $this->results['pest_categories']['top_opt'][0];
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'Pengendalian OPT',
                'title' => 'OPT ' . $topOpt['nama'] . ' paling banyak dilaporkan',
                'description' => 'Jenis OPT ' . $topOpt['nama'] . ' (jenis: ' . ($topOpt['jenis'] ?? '-') . ') '
                    . 'muncul dalam ' . $topOpt['jumlah'] . ' laporan. '
                    . 'Disarankan pengembangan strategi pengendalian spesifik untuk OPT ini.',
                'action' => 'Buat SOP pengendalian khusus untuk OPT ' . $topOpt['nama'] . ' dan sosialisasikan ke petugas lapangan.',
                'metrics' => [
                    'opt' => $topOpt['nama'],
                    'jenis' => $topOpt['jenis'] ?? '-',
                    'jumlah' => $topOpt['jumlah']
                ]
            ];
        }

        // R4: Analisis peran role
        $roleStats = $this->results['role_analysis']['role_stats'] ?? [];
        $totalRole = array_sum(array_column($roleStats, 'jumlah_laporan'));
        if (count($roleStats) > 1 && $totalRole > 0) {
            $nonEmptyRoles = array_filter($roleStats, fn($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH);
if (count($nonEmptyRoles) > 1) {
                $jumlahs = array_column($nonEmptyRoles, 'jumlah_laporan');
                $maxJumlah = max($jumlahs);
                $bestIdx = array_search($maxJumlah, $jumlahs);
                $roleKeys = array_keys($nonEmptyRoles);
                $bestRole = $roleKeys[$bestIdx] ?? '';
                $bestStats = $nonEmptyRoles[$bestRole] ?? [];

                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'Peningkatan Partisipasi',
                    'title' => 'Role ' . ucfirst($bestRole) . ' menjadi kontributor utama',
                    'description' => 'Role ' . $bestRole . ' menyumbang ' . ($totalRole > 0 && isset($bestStats['jumlah_laporan']) ? round($bestStats['jumlah_laporan'] / $totalRole * 100, 1) : 0) 
                        . '% dari total ' . $totalRole . ' laporan. '
                        . 'Perlu strategi untuk meningkatkan partisipasi role lain.',
                    'action' => 'Adakan pelatihan dan briefing rutin untuk role non-' . $bestRole . ' untuk meningkatkan awareness.',
                    'metrics' => [
                        'role' => $bestRole,
                        'jumlah' => $bestStats['jumlah_laporan'] ?? 0,
                        'percentage' => $totalRole > 0 ? round(($bestStats['jumlah_laporan'] ?? 0) / $totalRole * 100, 1) : 0
                    ]
                ];
            }
        }

        // R5: Laporan berat yang perlu perhatian khusus
        $beratCount = 0;
        foreach ($this->results['overview']['severity_breakdown'] ?? [] as $level => $data) {
            if ($level === 'Berat') $beratCount = $data['jumlah'];
        }
        if ($beratCount > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'Respons Darurat',
                'title' => $beratCount . ' laporan berkategori Berat perlu respons cepat',
                'description' => 'Terdapat ' . $beratCount . ' laporan dengan tingkat keparahan Berat. '
                    . 'Ini memerlukan koordinasi cepat dengan Dinas Pertanian dan POPT setempat.',
                'action' => 'Buat sistem notifikasi otomatis untuk laporan Berat dan jadwal verifikasi < 24 jam.',
                'metrics' => [
                    'jumlah_berat' => $beratCount
                ]
            ];
        }

        // R6: Trend bulanan - anomali
        $monthly = $this->results['time_trends']['monthly'] ?? [];
        if (count($monthly) >= 3) {
            $last3 = array_slice($monthly, -3);
            $avg = array_sum(array_column($last3, 'jumlah')) / count($last3);
            $recent = end($last3);
            if ($recent['jumlah'] > $avg * 1.5) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'Deteksi Dini',
                    'title' => 'Peningkatan signifikan laporan bulan terakhir',
                    'description' => 'Bulan ' . $recent['bulan'] . ' menunjukkan lonjakan laporan '
                        . '(' . $recent['jumlah'] . '), melebihi rata-rata 3 bulan terakhir (' . round($avg, 1) . '). '
                        . 'Perlu investigasi apakah ini pola musiman atau outbreak.',
                    'action' => 'Lakukan analisis penyebab lonjakan dan siapkan tim respons tambahan.',
                    'metrics' => [
                        'bulan' => $recent['bulan'],
                        'jumlah' => $recent['jumlah'],
                        'avg_3bulan' => round($avg, 1)
                    ]
                ];
            }
        }

        $this->results['recommendations'] = $recommendations;
        return $recommendations;
    }

    // ──────────────────────────────────────────────
    // RUN ALL ANALYSES
    // ──────────────────────────────────────────────
    public function runAll() {
        $this->getOverview();
        $this->getTimeTrends();
        $this->getGeographicDistribution();
        $this->getDominantPestCategories();
        $this->getRoleAnalysis();
        $this->getVerificationAnalysis();
        $this->getChartData();
        $this->getRecommendations();
        return $this->results;
    }

    public function toJSON() {
        return json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// CLI or include usage
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $analytics = new LaporanHamaAnalytics();
    $results = $analytics->runAll();
    
    echo "=== ANALISIS KOMPREHENSIF LAPORAN HAMA JAGAPADI ===\n\n";
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n[DONE] Output saved.\n";
} else {
    // API/controller include path
}