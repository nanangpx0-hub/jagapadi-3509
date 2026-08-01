<?php
require_once ROOT_PATH . '/app/views/layouts/header.php';
require_once __DIR__ . '/../../scripts/laporan_hama_analytics.php';

$analytics = new LaporanHamaAnalytics();
$data = $analytics->runAll();
$jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
?>

<style>
.card-chart { margin-bottom: 20px; }
.chart-container { position: relative; height: 280px; }
.stat-card { border-left: 4px solid; min-height: 100px; }
.stat-card.primary { border-left-color: #3498db; }
.stat-card.success { border-left-color: #27ae60; }
.stat-card.warning { border-left-color: #f39c12; }
.stat-card.danger { border-left-color: #e74c3c; }
.rec-badge { font-size: 0.75rem; padding: 3px 8px; }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">Dashboard Analisis Laporan Hama</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item active">Analisis Laporan Hama</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="card card-chart stat-card primary">
                    <div class="card-body">
                        <h5><i class="fas fa-bug"></i> Total Laporan</h5>
                        <h2 class="text-primary"><?= $data['overview']['summary']['total_laporan'] ?? 0 ?></h2>
                        <small class="text-muted"><?= $data['overview']['summary']['total_pelapor'] ?? 0 ?> pelapor</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card card-chart stat-card success">
                    <div class="card-body">
                        <h5><i class="fas fa-map-marker-alt"></i> Kecamatan</h5>
                        <h2 class="text-success"><?= $data['overview']['summary']['total_kecamatan'] ?? 0 ?></h2>
                        <small class="text-muted"><?= $data['overview']['summary']['total_desa'] ?? 0 ?> desa</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card card-chart stat-card warning">
                    <div class="card-body">
                        <h5><i class="fas fa-ruler-combined"></i> Luas Serangan</h5>
                        <h2 class="text-warning"><?= $data['overview']['summary']['total_luas_serangan'] ?? 0 ?> Ha</h2>
                        <small class="text-muted">Rata-rata: <?= $data['overview']['summary']['avg_luas_serangan'] ?? 0 ?> Ha</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card card-chart stat-card danger">
                    <div class="card-body">
                        <h5><i class="fas fa-exclamation-triangle"></i> OPT Terdampak</h5>
                        <h2 class="text-danger"><?= $data['overview']['summary']['total_opt'] ?? 0 ?></h2>
                        <small class="text-muted">Jenis Organisme</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 1: Trend & Status -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Tren Laporan Bulanan</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Status Laporan</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Distribution -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-bar"></i> Tingkat Keparahan</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-leaf"></i> OPT Dominan</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="optChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cloud-sun"></i> Distribusi Musim</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="seasonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Geographic & Role -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-map"></i> Top 10 Kecamatan</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="kecamatanChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users"></i> Kontribusi per Role</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Recommendations -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info">
                        <h3 class="card-title"><i class="fas fa-lightbulb"></i> Rekomendasi Tindakan</h3>
                        <div class="float-right">
                            <a href="/api/laporan-hama/analytics/export" class="btn btn-sm btn-secondary" target="_blank">
                                <i class="fas fa-download"></i> Export JSON
                            </a>
                            <a href="/api/laporan-hama/analytics/export-csv" class="btn btn-sm btn-secondary">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                        </div>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Prioritas</th>
                                    <th>Kategori</th>
                                    <th>Judul</th>
                                    <th>Deskripsi</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['recommendations'] as $rec): ?>
                                <tr>
                                    <td>
                                        <span class="badge rec-badge <?= $rec['priority'] === 'high' ? 'bg-danger' : 'bg-warning' ?>">
                                            <?= strtoupper($rec['priority']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($rec['category'] ?? '') ?></td>
                                    <td><strong><?= htmlspecialchars($rec['title'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($rec['description'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($rec['action'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data['recommendations'])): ?>
                                <tr><td colspan="5" class="text-center text-muted">Tambahkan data lebih banyak untuk analisis.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const analyticsData = <?= $jsonData ?>;

Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.color = '#6c757d';

function getChartConfig(data) {
    return data && data.labels && data.labels.length > 0 ? data : { labels: [], datasets: [] };
}

new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: getChartConfig(analyticsData.chart_data.monthlyTrend),
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Jumlah' }},
            y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'Luas (Ha)' }, grid: { drawOnChartArea: false }}
        }
    }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: getChartConfig(analyticsData.chart_data.statusBreakdown),
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('severityChart'), {
    type: 'bar',
    data: getChartConfig(analyticsData.chart_data.severityDistribution),
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('optChart'), {
    type: 'bar',
    data: getChartConfig(analyticsData.chart_data.topOpt),
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('seasonChart'), {
    type: 'pie',
    data: getChartConfig(analyticsData.chart_data.seasonDistribution),
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('kecamatanChart'), {
    type: 'bar',
    data: getChartConfig(analyticsData.chart_data.topKecamatan),
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('roleChart'), {
    type: 'bar',
    data: getChartConfig(analyticsData.chart_data.roleContribution),
    options: { responsive: true, maintainAspectRatio: false }
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>