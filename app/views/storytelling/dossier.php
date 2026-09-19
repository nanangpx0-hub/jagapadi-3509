<?php
/**
 * Berkas Dossier Eksekutif Storytelling Pertanian Kab. Jember
 * 
 * View mandiri untuk pratinjau & cetak berkas dossier resmi (Print/PDF).
 */

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$periodeText = ($namaBulan[$bulan] ?? 'Bulan ' . $bulan) . ' ' . $tahun;
$badgeColor = match($status_doc) {
    'PUBLISHED' => '#28a745',
    'ARCHIVED' => '#6c757d',
    default => '#ffc107',
};
$badgeTextColor = $status_doc === 'PUBLISHED' ? '#ffffff' : ($status_doc === 'ARCHIVED' ? '#ffffff' : '#212529');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dossier Storytelling Pertanian', ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($periodeText, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #2c3e50;
            background-color: #f4f6f9;
            line-height: 1.6;
            font-size: 13px;
        }
        .container {
            max-width: 960px;
            margin: 20px auto;
            background: #ffffff;
            padding: 40px 48px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .toolbar {
            position: fixed;
            top: 15px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 9999;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }
        .btn-primary { background: #2b6cb0; color: #fff; }
        .btn-primary:hover { background: #2c5282; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }

        /* Kop Surat Resmi */
        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px double #2b6cb0;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .kop-left {
            text-align: left;
        }
        .kop-left h1 {
            font-size: 20px;
            color: #1a365d;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .kop-left h2 {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 2px;
        }
        .kop-left p {
            font-size: 11px;
            color: #718096;
        }
        .kop-badge {
            display: inline-block;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: <?= $badgeColor ?>;
            color: <?= $badgeTextColor ?>;
        }

        /* Metadata Box */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
            margin-bottom: 24px;
        }
        .meta-item .label {
            font-size: 10px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .meta-item .value {
            font-size: 13px;
            font-weight: 700;
            color: #1a202c;
        }

        /* Section Styling */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #2b6cb0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 4px solid #2b6cb0;
            padding-left: 10px;
            margin-top: 24px;
            margin-bottom: 12px;
        }

        /* Narrative Box */
        .narrative-box {
            background: #ebf8ff;
            border-left: 4px solid #3182ce;
            padding: 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #2a4365;
            line-height: 1.7;
            margin-bottom: 20px;
            white-space: pre-line;
        }

        /* KPI / Risk Cards */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .kpi-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
            text-align: center;
        }
        .kpi-box.risk-low { border-top: 4px solid #38a169; }
        .kpi-box.risk-medium { border-top: 4px solid #ecc94b; }
        .kpi-box.risk-high { border-top: 4px solid #e53e3e; }
        .kpi-box .score {
            font-size: 24px;
            font-weight: 800;
            margin: 4px 0;
        }
        .kpi-box .score-label {
            font-size: 11px;
            color: #718096;
            font-weight: 600;
        }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e0;
            padding: 6px 10px;
            text-align: left;
        }
        table.data-table th {
            background: #edf2f7;
            font-weight: 700;
            color: #2d3748;
        }
        table.data-table tr:nth-child(even) {
            background: #f7fafc;
        }
        table.data-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* Signatures */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
        }
        .signature-box .role-title {
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 60px;
        }
        .signature-box .signer-name {
            font-size: 13px;
            font-weight: 700;
            color: #1a202c;
            text-decoration: underline;
        }
        .signature-box .signer-nip {
            font-size: 11px;
            color: #718096;
        }

        .footer-note {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #a0aec0;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: #ffffff;
                font-size: 11pt;
            }
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .toolbar {
                display: none !important;
            }
            .kop-surat {
                border-bottom: 2pt solid #2b6cb0;
            }
            @page {
                size: A4;
                margin: 1.5cm 1.5cm 1.5cm 1.5cm;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak / Simpan PDF
    </button>
    <a href="<?= BASE_URL ?>storytelling" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
    </a>
</div>

<div class="container">
    <!-- Kop Surat -->
    <div class="kop-surat">
        <div class="kop-left">
            <h1>Pemerintah Kabupaten Jember</h1>
            <h2>Dinas Tanaman Pangan, Hortikultura dan Perkebunan • BPS Kabupaten Jember</h2>
            <p>Sistem Pemantauan Terpadu Produksi Pertanian JAGAPADI — Berkas Dossier Eksekutif</p>
        </div>
        <div>
            <span class="kop-badge"><?= htmlspecialchars($status_doc, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="meta-grid">
        <div class="meta-item">
            <div class="label">Periode Analisis</div>
            <div class="value"><?= htmlspecialchars($periodeText, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="meta-item">
            <div class="label">Cakupan Wilayah</div>
            <div class="value"><?= htmlspecialchars($kecamatan_name, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="meta-item">
            <div class="label">Faktor Utama Terkait</div>
            <div class="value"><?= htmlspecialchars($faktor_penyebab, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="meta-item">
            <div class="label">Waktu Pemutakhiran</div>
            <div class="value"><?= date('d/m/Y H:i', strtotime($updated_at)) ?> WIB</div>
        </div>
    </div>

    <!-- Ringkasan Eksekutif / Narasi -->
    <div class="section-title">I. Ringkasan Eksekutif & Narasi Kausalitas</div>
    <div class="narrative-box">
        <?= nl2br(htmlspecialchars($narasi, ENT_QUOTES, 'UTF-8')) ?>
    </div>

    <!-- Skor Risiko Produksi -->
    <div class="section-title">II. Matriks Indikator & Skor Risiko</div>
    <div class="kpi-row">
        <?php
        $scCuaca = $skor_risiko['skor_risiko_cuaca'] ?? null;
        $classCuaca = $scCuaca !== null ? ($scCuaca > 70 ? 'risk-high' : ($scCuaca > 40 ? 'risk-medium' : 'risk-low')) : '';
        ?>
        <div class="kpi-box <?= $classCuaca ?>">
            <div class="score-label">Risiko Anomali Iklim & Cuaca</div>
            <div class="score" style="color: <?= $scCuaca > 70 ? '#e53e3e' : ($scCuaca > 40 ? '#d69e2e' : '#38a169') ?>">
                <?= $scCuaca !== null ? (int)$scCuaca : '-' ?>
            </div>
            <div class="score-label">Curah Hujan & Irigasi (Lag-1)</div>
        </div>

        <?php
        $scHama = $skor_risiko['skor_risiko_hama'] ?? null;
        $classHama = $scHama !== null ? ($scHama > 70 ? 'risk-high' : ($scHama > 40 ? 'risk-medium' : 'risk-low')) : '';
        ?>
        <div class="kpi-box <?= $classHama ?>">
            <div class="score-label">Risiko Serangan OPT / Hama</div>
            <div class="score" style="color: <?= $scHama > 70 ? '#e53e3e' : ($scHama > 40 ? '#d69e2e' : '#38a169') ?>">
                <?= $scHama !== null ? (int)$scHama : '-' ?>
            </div>
            <div class="score-label">Insidensi & Keparahan OPT (Lag-1)</div>
        </div>

        <?php
        $scTotal = $skor_risiko['skor_risiko_total'] ?? null;
        $classTotal = $scTotal !== null ? ($scTotal > 70 ? 'risk-high' : ($scTotal > 40 ? 'risk-medium' : 'risk-low')) : '';
        ?>
        <div class="kpi-box <?= $classTotal ?>">
            <div class="score-label">Indeks Risiko Gabungan</div>
            <div class="score" style="color: <?= $scTotal > 70 ? '#e53e3e' : ($scTotal > 40 ? '#d69e2e' : '#38a169') ?>">
                <?= $scTotal !== null ? (int)$scTotal : '-' ?>
            </div>
            <div class="score-label">Tingkat Ancaman Produksi</div>
        </div>
    </div>

    <!-- Data Multi-Sektor Terintegrasi -->
    <div class="section-title">III. Runtun Waktu Multi-Sektor (12 Bulan Terakhir)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Periode</th>
                <th style="text-align: right;">Luas Panen (Ha)</th>
                <th style="text-align: right;">Curah Hujan (mm)</th>
                <th style="text-align: right;">Laporan OPT</th>
                <th style="text-align: right;">Debit Irigasi (m³/s)</th>
                <th style="text-align: right;">Kecepatan Angin (km/j)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $labels = $chart_data['labels'] ?? [];
            $datasets = $chart_data['datasets'] ?? [];
            $harvestSeries = $datasets[0]['data'] ?? [];
            $rainSeries = $datasets[1]['data'] ?? [];
            $pestSeries = $datasets[2]['data'] ?? [];
            $irrigationSeries = $datasets[3]['data'] ?? [];
            $windSeries = $datasets[4]['data'] ?? [];
            $rowCount = count($labels);

            if ($rowCount === 0):
            ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #718096;">Tidak ada riwayat data bulanan yang tersedia.</td>
            </tr>
            <?php else: ?>
                <?php for ($i = 0; $i < $rowCount; $i++): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($labels[$i] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td class="num"><?= number_format((float)($harvestSeries[$i] ?? 0), 2, ',', '.') ?></td>
                    <td class="num"><?= number_format((float)($rainSeries[$i] ?? 0), 1, ',', '.') ?></td>
                    <td class="num"><?= number_format((float)($pestSeries[$i] ?? 0), 0, ',', '.') ?></td>
                    <td class="num"><?= number_format((float)($irrigationSeries[$i] ?? 0), 2, ',', '.') ?></td>
                    <td class="num"><?= number_format((float)($windSeries[$i] ?? 0), 1, ',', '.') ?></td>
                </tr>
                <?php endfor; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Hasil Analisis Lanjutan jika ada -->
    <?php if (!empty($advanced_analysis)): ?>
    <div class="section-title">IV. Hasil Pemodelan Analitik Lanjutan</div>
    <div style="background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 20px;">
        <p style="font-weight: 700; color: #2d3748; margin-bottom: 4px;">
            Metode: <?= htmlspecialchars(strtoupper((string)($advanced_analysis['method'] ?? 'STATISTIK')), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p style="margin-bottom: 8px; color: #4a5568;">
            <?= htmlspecialchars((string)($advanced_analysis['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if (!empty($advanced_analysis['metrics'])): ?>
        <table class="data-table" style="max-width: 500px; margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Indikator Metrik</th>
                    <th style="text-align: right;">Nilai Terhitung</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advanced_analysis['metrics'] as $mKey => $mVal): ?>
                <tr>
                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$mKey)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num">
                        <?= is_numeric($mVal) ? number_format((float)$mVal, 4, ',', '.') : htmlspecialchars((string)$mVal, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Rekomendasi Aksi Mitigasi -->
    <div class="section-title"><?= !empty($advanced_analysis) ? 'V' : 'IV' ?>. Rekomendasi Kebijakan & Mitigasi Lapangan</div>
    <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #fff;">
        <ul style="padding-left: 20px; color: #4a5568; line-height: 1.8;">
            <li><strong>Mitigasi Pengairan:</strong> Pemeliharaan berkala pintu air saluran sekunder dan tersier untuk menjamin pasokan debit teknis saat presipitasi rendah.</li>
            <li><strong>Pengawasan OPT Intensif:</strong> Peningkatan frekuensi pengamatan mingguan oleh Petugas POPT di wilayah kantong serangan hama.</li>
            <li><strong>Sinkronisasi Kalender Tanam:</strong> Menyelaraskan masa persemaian dan tebar benih dengan proyeksi pola hujan dan prakiraan BMKG/BPS.</li>
            <li><strong>Pelaporan Cepat (Early Warning):</strong> Pemanfaatan modul pelaporan mobile JAGAPADI secara teratur untuk mencegah lonjakan eskalasi risiko.</li>
        </ul>
    </div>

    <!-- Lembar Pengesahan -->
    <div class="signature-grid">
        <div class="signature-box">
            <div class="role-title">Penyusun Analisis & Data Story,</div>
            <div class="signer-name"><?= htmlspecialchars($created_by, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="signer-nip">Statistisi / Analis Data Pertanian</div>
        </div>
        <div class="signature-box">
            <div class="role-title">Mengetahui & Menyetujui,</div>
            <div class="signer-name">Kepala Seksi / Administrator Wilayah</div>
            <div class="signer-nip">Dinas Tanaman Pangan, Hortikultura & Perkebunan</div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="footer-note">
        <span>Dokumen Resmi JAGAPADI — Jember Agrikultur Gapai Prestasi Digital</span>
        <span>Dicetak pada <?= date('d F Y, H:i:s') ?> WIB</span>
    </div>
</div>

</body>
</html>
