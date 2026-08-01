<?php
/**
 * Analytics Gabah & Beras
 * Dashboard analitik dengan korelasi irigasi, cuaca, dan hama
 */

require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-pie text-info"></i> Analytics Gabah & Beras
        </h1>
        <a href="<?= BASE_URL ?>gabahBeras" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row align-items-center">
                <div class="col-md-3">
                    <select name="tahun" class="form-control form-control-sm">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Summary -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Produksi</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= number_format($summary['kpi']['total_produksi']['value'] ?? 0, 0, ',', '.') ?> ton
                    </div>
                    <?php if (($summary['kpi']['total_produksi']['change'] ?? null) !== null): ?>
                    <small class="text-<?= $summary['kpi']['total_produksi']['trend'] === 'up' ? 'success' : 'danger' ?>">
                        <?= $summary['kpi']['total_produksi']['change'] > 0 ? '+' : '' ?><?= $summary['kpi']['total_produksi']['change'] ?>% dari tahun lalu
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Produktivitas</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= number_format($summary['kpi']['avg_produktivitas']['value'] ?? 0, 2, ',', '.') ?> ton/ha
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Luas Panen</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= number_format($summary['kpi']['total_luas_panen']['value'] ?? 0, 0, ',', '.') ?> Ha
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Wilayah</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?= $summary['kpi']['total_wilayah']['value'] ?? 0 ?> kecamatan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Correlation Analysis Row -->
    <div class="row mb-4">
        <!-- Irrigation Correlation -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-water"></i> Korelasi dengan Irigasi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6 text-center">
                            <h4 class="text-info"><?= $irrigation_correlation['correlation'] ?? 0 ?></h4>
                            <small class="text-muted">Koefisien Korelasi</small>
                        </div>
                        <div class="col-6">
                            <p class="small"><?= $irrigation_correlation['interpretation'] ?? 'Tidak ada data' ?></p>
                        </div>
                    </div>
                    <div class="alert alert-light small">
                        <i class="fas fa-lightbulb text-warning"></i>
                        <?= $irrigation_correlation['recommendation'] ?? 'Tidak ada rekomendasi' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weather Correlation -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-warning">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-cloud-sun"></i> Korelasi dengan Cuaca
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Dampak Cuaca:</strong>
                        <ul class="small mb-0">
                            <?php foreach (($weather_correlation['weather_impact'] ?? ['Tidak ada data']) as $impact): ?>
                            <li><?= htmlspecialchars($impact ?? '') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="alert alert-light small">
                        <i class="fas fa-lightbulb text-warning"></i>
                        <?= $weather_correlation['recommendation'] ?? 'Tidak ada rekomendasi' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pest Correlation & By Irigasi -->
    <div class="row mb-4">
        <!-- Pest Correlation -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-bug"></i> Korelasi dengan Hama
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6 text-center">
                            <h4 class="text-danger"><?= $pest_correlation['correlation'] ?? 0 ?></h4>
                            <small class="text-muted">Koefisien Korelasi</small>
                        </div>
                        <div class="col-6">
                            <p class="small"><?= $pest_correlation['interpretation'] ?? 'Tidak ada data' ?></p>
                        </div>
                    </div>
                    <?php if (!empty($pest_correlation['risk_areas'])): ?>
                    <div class="alert alert-danger small">
                        <strong>Wilayah Berisiko Tinggi:</strong>
                        <?= count($pest_correlation['risk_areas']) ?> wilayah teridentifikasi
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success small">
                        <i class="fas fa-check"></i> Tidak ada wilayah berisiko tinggi teridentifikasi
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- By Irigasi -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Produktivitas per Daerah Irigasi
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($by_irigasi)): ?>
                    <p class="text-muted text-center">Belum ada data terkait irigasi</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Irigasi</th>
                                    <th>Produksi</th>
                                    <th>Produktivitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($by_irigasi, 0, 5) as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nama_irigasi'] ?? 'N/A') ?></td>
                                    <td><?= number_format($item['total_produksi'] ?? 0, 0) ?> ton</td>
                                    <td><?= number_format($item['avg_produktivitas'] ?? 0, 2) ?> ton/ha</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Distribution -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-star"></i> Distribusi Kualitas Gabah
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $gradeColors = ['A' => 'success', 'B' => 'primary', 'C' => 'warning', 'D' => 'danger'];
                        foreach (($summary['grade_distribution'] ?? []) as $grade): 
                        ?>
                        <div class="col-md-3 text-center mb-3">
                            <div class="card border-<?= $gradeColors[$grade['grade_kualitas']] ?? 'secondary' ?>">
                                <div class="card-body">
                                    <h3 class="text-<?= $gradeColors[$grade['grade_kualitas']] ?? 'secondary' ?>">
                                        Grade <?= $grade['grade_kualitas'] ?>
                                    </h3>
                                    <p class="mb-0">
                                        <span class="h4"><?= $grade['count'] ?></span> record
                                        <br><small>(<?= $grade['percentage'] ?>%)</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($summary['grade_distribution'])): ?>
                        <div class="col-12 text-center text-muted">
                            <p>Belum ada data distribusi kualitas</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
