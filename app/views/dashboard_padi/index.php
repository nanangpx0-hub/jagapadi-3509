<?php
require_once ROOT_PATH . '/app/views/layouts/header.php';

$formatNumber = static function ($value, int $decimals = 0): string {
    return number_format((float) ($value ?? 0), $decimals, ',', '.');
};

$formatDateTime = static function ($value): string {
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
};

$trendLabels = array_map(static fn($row): string => (string) $row['tahun'], $trend ?? []);
$trendLuasPanen = array_map(static fn($row): float => round((float) ($row['luas_panen'] ?? 0), 2), $trend ?? []);
$trendProduksi = array_map(static fn($row): float => round((float) ($row['produksi'] ?? 0), 2), $trend ?? []);
$trendProduktivitas = array_map(static fn($row): float => round((float) ($row['produktivitas'] ?? 0), 2), $trend ?? []);
$operationalSummary = $summary['operational_summary'] ?? null;
$statusLabels = [
    'verified' => 'Terverifikasi',
    'pending' => 'Menunggu',
    'rejected' => 'Ditolak',
    'draft' => 'Draft',
];
$statusClasses = [
    'verified' => 'success',
    'pending' => 'warning',
    'rejected' => 'danger',
    'draft' => 'secondary',
];
?>

<style>
    .dashboard-padi .small-box {
        border-radius: 8px;
    }

    .dashboard-padi .metric-card {
        border-left-width: 4px;
        border-left-style: solid;
    }

    .dashboard-padi .metric-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .dashboard-padi .metric-label {
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .dashboard-padi .trend-chart-wrap {
        position: relative;
        min-height: 320px;
    }

    .dashboard-padi .table td,
    .dashboard-padi .table th {
        vertical-align: middle;
    }

    .dashboard-padi .number-cell {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
</style>

<div class="dashboard-padi">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label for="tahun" class="mb-1">Tahun</label>
                    <select name="tahun" id="tahun" class="form-control">
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?= (int) $year ?>" <?= (int) $selectedYear === (int) $year ? 'selected' : '' ?>>
                                <?= (int) $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 mb-2">
                    <label for="kecamatan_id" class="mb-1">Kecamatan</label>
                    <select name="kecamatan_id" id="kecamatan_id" class="form-control">
                        <option value="">Semua Kecamatan</option>
                        <?php foreach ($kecamatanList as $kecamatan): ?>
                            <option value="<?= (int) $kecamatan['id'] ?>" <?= (int) ($selectedKecamatanId ?? 0) === (int) $kecamatan['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kecamatan['nama_kecamatan'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2 d-flex">
                    <button type="submit" class="btn btn-success mr-2">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                    <a href="<?= BASE_URL ?>dashboardPadi?tahun=<?= (int) $selectedYear ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset Kecamatan
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-light border mb-3">
        <div class="d-flex flex-column flex-md-row justify-content-between">
            <div>
                <strong>Sumber ringkasan:</strong>
                <?= htmlspecialchars($summary['source_label'] ?? '-') ?>
                <?php if (!empty($selectedKecamatan)): ?>
                    untuk Kecamatan <?= htmlspecialchars($selectedKecamatan['nama_kecamatan'] ?? '') ?>
                <?php else: ?>
                    untuk Kabupaten Jember
                <?php endif; ?>
                <br>
                <small class="text-muted"><?= htmlspecialchars($summary['source_note'] ?? '') ?></small>
            </div>
            <div class="mt-2 mt-md-0 text-md-right">
                <small class="text-muted">Update terakhir</small><br>
                <strong><?= $formatDateTime($summary['last_updated'] ?? null) ?></strong>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card metric-card border-left-primary h-100">
                <div class="card-body">
                    <div class="metric-label text-primary">Luas Panen</div>
                    <div class="metric-value"><?= $formatNumber($summary['luas_panen'] ?? 0, 0) ?></div>
                    <div class="text-muted">hektar</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card metric-card border-left-success h-100">
                <div class="card-body">
                    <div class="metric-label text-success">Produksi</div>
                    <div class="metric-value"><?= $formatNumber($summary['produksi'] ?? 0, 0) ?></div>
                    <div class="text-muted">ton GKG</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card metric-card border-left-warning h-100">
                <div class="card-body">
                    <div class="metric-label text-warning">Produktivitas</div>
                    <div class="metric-value"><?= $formatNumber($summary['produktivitas'] ?? 0, 2) ?></div>
                    <div class="text-muted">kuintal per hektar</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card metric-card border-left-info h-100">
                <div class="card-body">
                    <div class="metric-label text-info">Cakupan Data</div>
                    <div class="metric-value">
                        <?php if (($summary['source'] ?? '') === 'data_pertanian_bps'): ?>
                            1
                        <?php else: ?>
                            <?= $formatNumber($summary['jumlah_kecamatan'] ?? 0, 0) ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted">
                        <?= ($summary['source'] ?? '') === 'data_pertanian_bps' ? 'kabupaten' : 'kecamatan' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (($summary['source'] ?? '') === 'data_pertanian_bps' && $operationalSummary !== null): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="row text-center">
                            <div class="col-md-3 col-6 mb-2 mb-md-0">
                                <small class="text-muted d-block">Input Produksi</small>
                                <strong><?= $formatNumber($operationalSummary['jumlah_data'] ?? 0, 0) ?> data</strong>
                            </div>
                            <div class="col-md-3 col-6 mb-2 mb-md-0">
                                <small class="text-muted d-block">Terverifikasi</small>
                                <strong><?= $formatNumber($operationalSummary['verified_count'] ?? 0, 0) ?> data</strong>
                            </div>
                            <div class="col-md-3 col-6">
                                <small class="text-muted d-block">Menunggu</small>
                                <strong><?= $formatNumber($operationalSummary['pending_count'] ?? 0, 0) ?> data</strong>
                            </div>
                            <div class="col-md-3 col-6">
                                <small class="text-muted d-block">Kecamatan Terisi</small>
                                <strong><?= $formatNumber($operationalSummary['jumlah_kecamatan'] ?? 0, 0) ?> kecamatan</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-chart-line text-success"></i> Tren Tahunan
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($trend)): ?>
                        <div class="text-center text-muted py-5">
                            Belum ada data tren untuk filter ini.
                        </div>
                    <?php else: ?>
                        <div class="trend-chart-wrap">
                            <canvas id="trendPadiChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-check-circle text-info"></i> Status Input
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($statusBreakdown)): ?>
                        <div class="text-muted">Belum ada input produksi gabah untuk filter ini.</div>
                    <?php else: ?>
                        <?php foreach ($statusBreakdown as $status): ?>
                            <?php
                            $statusKey = $status['status'] ?? 'pending';
                            $badgeClass = $statusClasses[$statusKey] ?? 'secondary';
                            ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>
                                    <span class="badge badge-<?= $badgeClass ?>">
                                        <?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                                    </span>
                                </span>
                                <strong><?= $formatNumber($status['total'] ?? 0, 0) ?> data</strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="mt-3 small text-muted">
                        Draft tidak dihitung dalam ringkasan operasional.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="fas fa-table text-primary"></i> Ringkasan Per Kecamatan
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($kecamatanBreakdown)): ?>
                <div class="text-center text-muted py-4">
                    Belum ada input produksi gabah per kecamatan pada tahun <?= (int) $selectedYear ?>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Kecamatan</th>
                                <th class="number-cell">Data</th>
                                <th class="number-cell">Luas Panen (ha)</th>
                                <th class="number-cell">Produksi (ton GKG)</th>
                                <th class="number-cell">Produktivitas (ku/ha)</th>
                                <th class="number-cell">Terverifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($kecamatanBreakdown, 0, 10) as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nama_kecamatan'] ?? '-') ?></td>
                                    <td class="number-cell"><?= $formatNumber($row['jumlah_data'] ?? 0, 0) ?></td>
                                    <td class="number-cell"><?= $formatNumber($row['luas_panen'] ?? 0, 0) ?></td>
                                    <td class="number-cell"><?= $formatNumber($row['produksi'] ?? 0, 0) ?></td>
                                    <td class="number-cell"><?= $formatNumber($row['produktivitas'] ?? 0, 2) ?></td>
                                    <td class="number-cell"><?= $formatNumber($row['verified_count'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($trend)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('trendPadiChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        data: {
            labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            datasets: [
                {
                    type: 'bar',
                    label: 'Luas panen (ha)',
                    data: <?= json_encode($trendLuasPanen, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.35)',
                    borderColor: '#28a745',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    type: 'bar',
                    label: 'Produksi (ton GKG)',
                    data: <?= json_encode($trendProduksi, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.28)',
                    borderColor: '#17a2b8',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'Produktivitas (ku/ha)',
                    data: <?= json_encode($trendProduktivitas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                    borderColor: '#f0ad4e',
                    backgroundColor: 'rgba(240, 173, 78, 0.2)',
                    tension: 0.25,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const value = Number(context.parsed.y || 0).toLocaleString('id-ID');
                            return context.dataset.label + ': ' + value;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return Number(value).toLocaleString('id-ID');
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
