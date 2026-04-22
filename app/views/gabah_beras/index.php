<?php
/**
 * Dashboard Gabah & Beras - Main View
 * Menampilkan KPI, charts, dan peta produksi
 */

require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-seedling text-success"></i> Dashboard Gabah & Beras
        </h1>
        <div>
            <a href="<?= BASE_URL ?>gabahBeras/create" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Input Data Produksi
            </a>
            <a href="<?= BASE_URL ?>gabahBeras/analytics" class="btn btn-info btn-sm">
                <i class="fas fa-chart-pie"></i> Analytics
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end" id="filterForm">
                <div class="col-md-3 mb-2">
                    <label class="small text-muted">Tahun</label>
                    <select name="tahun" class="form-control form-control-sm" id="filterTahun">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small text-muted">Musim Tanam</label>
                    <select name="musim" class="form-control form-control-sm" id="filterMusim">
                        <option value="">Semua Musim</option>
                        <?php foreach ($musim_list as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $musim == $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnReset">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <!-- Total Produksi -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Produksi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_produksi'] ?? 0, 0, ',', '.') ?> <small>ton GKG</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Luas Panen -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Luas Panen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_luas_panen'] ?? 0, 0, ',', '.') ?> <small>Ha</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Produktivitas Rata-rata -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Produktivitas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['avg_produktivitas'] ?? 0, 2, ',', '.') ?> <small>ton/ha</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Record -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Data</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_records'] ?? 0) ?> <small>record</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-database fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Map Row -->
    <div class="row mb-4">
        <!-- Production Trend Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-area"></i> Tren Produksi per Musim
                    </h6>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="productionTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-chart-pie"></i> Distribusi Kualitas
                    </h6>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="gradeDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map & Comparison Row -->
    <div class="row mb-4">
        <!-- Productivity Map -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-map-marked-alt"></i> Peta Produktivitas per Wilayah
                    </h6>
                </div>
                <div class="card-body">
                    <div id="productivityMap" style="height: 350px; border-radius: 8px;"></div>
                    <div class="mt-2 text-center small">
                        <span class="badge badge-success mr-2">Tinggi (&gt;7 ton/ha)</span>
                        <span class="badge badge-warning mr-2">Sedang (5-7 ton/ha)</span>
                        <span class="badge badge-danger">Rendah (&lt;5 ton/ha)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regional Comparison -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-chart-bar"></i> Perbandingan Produktivitas Wilayah
                    </h6>
                </div>
                <div class="card-body">
                    <div style="height: 350px;">
                        <canvas id="regionalComparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-table"></i> Data Produksi Terbaru
            </h6>
            <a href="<?= BASE_URL ?>gabahBeras/create" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Tambah Data
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Lokasi</th>
                            <th>Musim</th>
                            <th>Tahun</th>
                            <th>Luas Panen</th>
                            <th>Produksi</th>
                            <th>Produktivitas</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_data)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Belum ada data produksi</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_data as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($row['unique_id']) ?></code></td>
                            <td><?= htmlspecialchars($row['nama_lokasi']) ?></td>
                            <td><span class="badge badge-info"><?= $row['musim_tanam'] ?></span></td>
                            <td><?= $row['tahun'] ?></td>
                            <td><?= number_format($row['luas_panen'], 2) ?> Ha</td>
                            <td><?= number_format($row['produksi_total'], 2) ?> ton</td>
                            <td>
                                <strong class="<?= $row['produktivitas'] >= 7 ? 'text-success' : ($row['produktivitas'] >= 5 ? 'text-warning' : 'text-danger') ?>">
                                    <?= number_format($row['produktivitas'], 2) ?> ton/ha
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-<?= $row['grade_kualitas'] === 'A' ? 'success' : ($row['grade_kualitas'] === 'B' ? 'primary' : 'secondary') ?>">
                                    <?= $row['grade_kualitas'] ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusColors = ['draft' => 'secondary', 'pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'];
                                ?>
                                <span class="badge badge-<?= $statusColors[$row['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>gabahBeras/detail/<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($_SESSION['role'] !== 'petugas' || $row['user_id'] == $_SESSION['user_id']): ?>
                                <a href="<?= BASE_URL ?>gabahBeras/edit/<?= $row['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const currentTahun = '<?= $tahun ?>';
const currentMusim = '<?= $musim ?>';

// Production Trend Data
const trendData = <?= json_encode($production_trend ?? []) ?>;
const productivityMapData = <?= json_encode($productivity_map ?? []) ?>;

// Grade Distribution
const gradeData = {
    A: <?= $statistics['grade_a'] ?? 0 ?>,
    B: <?= $statistics['grade_b'] ?? 0 ?>,
    C: <?= $statistics['grade_c'] ?? 0 ?>,
    D: <?= $statistics['grade_d'] ?? 0 ?>
};

// ========== PRODUCTION TREND CHART ==========
const trendCtx = document.getElementById('productionTrendChart');
if (trendCtx) {
    // Group data by year
    const years = [...new Set(trendData.map(d => d.tahun))].sort();
    const musimLabels = ['MT1', 'MT2', 'MT3'];
    
    const datasets = musimLabels.map((musim, idx) => {
        const colors = ['rgba(75, 192, 192, 0.7)', 'rgba(255, 159, 64, 0.7)', 'rgba(153, 102, 255, 0.7)'];
        return {
            label: musim,
            data: years.map(year => {
                const item = trendData.find(d => d.tahun == year && d.musim_tanam === musim);
                return item ? parseFloat(item.total_produksi) : 0;
            }),
            backgroundColor: colors[idx],
            borderColor: colors[idx].replace('0.7', '1'),
            borderWidth: 2,
            fill: false,
            tension: 0.3
        };
    });
    
    new Chart(trendCtx, {
        type: 'line',
        data: { labels: years, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${ctx.raw.toLocaleString()} ton`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Produksi (ton)' }
                }
            }
        }
    });
}

// ========== GRADE DISTRIBUTION CHART ==========
const gradeCtx = document.getElementById('gradeDistributionChart');
if (gradeCtx) {
    new Chart(gradeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Grade A', 'Grade B', 'Grade C', 'Grade D'],
            datasets: [{
                data: [gradeData.A, gradeData.B, gradeData.C, gradeData.D],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// ========== REGIONAL COMPARISON CHART ==========
const regionalCtx = document.getElementById('regionalComparisonChart');
if (regionalCtx && productivityMapData.length > 0) {
    const sortedData = productivityMapData.sort((a, b) => b.avg_produktivitas - a.avg_produktivitas).slice(0, 10);
    
    new Chart(regionalCtx, {
        type: 'bar',
        data: {
            labels: sortedData.map(d => d.nama_lokasi.split(',')[0]),
            datasets: [{
                label: 'Produktivitas (ton/ha)',
                data: sortedData.map(d => parseFloat(d.avg_produktivitas)),
                backgroundColor: sortedData.map(d => {
                    const p = parseFloat(d.avg_produktivitas);
                    if (p >= 7) return 'rgba(40, 167, 69, 0.7)';
                    if (p >= 5) return 'rgba(255, 193, 7, 0.7)';
                    return 'rgba(220, 53, 69, 0.7)';
                }),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: { display: true, text: 'ton/ha' }
                }
            }
        }
    });
}

// ========== PRODUCTIVITY MAP ==========
let productivityMapInstance = null;

function initProductivityMap() {
    if (productivityMapInstance) return;
    
    productivityMapInstance = L.map('productivityMap').setView([-8.1845, 113.6681], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(productivityMapInstance);
    
    // Add markers for each location
    if (productivityMapData.length > 0) {
        // Simulated coordinates (in real app, get from database)
        const baseCoords = { lat: -8.1845, lng: 113.6681 };
        
        productivityMapData.forEach((item, idx) => {
            const lat = baseCoords.lat + (Math.random() - 0.5) * 0.3;
            const lng = baseCoords.lng + (Math.random() - 0.5) * 0.3;
            
            const productivity = parseFloat(item.avg_produktivitas);
            let color = '#dc3545'; // red for low
            if (productivity >= 7) color = '#28a745'; // green
            else if (productivity >= 5) color = '#ffc107'; // yellow
            
            L.circleMarker([lat, lng], {
                radius: 12,
                fillColor: color,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(productivityMapInstance)
              .bindPopup(`
                <strong>${item.nama_lokasi}</strong><br>
                Produktivitas: <b>${productivity} ton/ha</b><br>
                Total Produksi: ${parseFloat(item.total_produksi).toLocaleString()} ton<br>
                Luas: ${parseFloat(item.total_luas).toLocaleString()} Ha
              `);
        });
    }
}

// Initialize map when visible
setTimeout(initProductivityMap, 500);

// Event handlers
document.getElementById('btnReset')?.addEventListener('click', function() {
    window.location.href = BASE_URL + 'gabahBeras';
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
