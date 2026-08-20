<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Report Styles */
.stat-card-report {
    border-radius: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    margin-bottom: 20px;
}

.stat-card-report.bugs {
    background: linear-gradient(135deg, #f5365c 0%, #f56036 100%);
}

.stat-card-report.features {
    background: linear-gradient(135deg, #2dce89 0%, #2dcecc 100%);
}

.stat-card-report.improvements {
    background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
}

.chart-container {
    position: relative;
    height: 300px;
}

.report-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.month-name {
    font-weight: 600;
}

.progress-thin {
    height: 8px;
    border-radius: 4px;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-chart-bar"></i> Laporan Feedback Bulanan</h4>
            <div>
                <a href="<?= BASE_URL ?>feedback" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <div class="btn-group ml-2">
                    <a href="?year=<?= $year - 1 ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i> <?= $year - 1 ?>
                    </a>
                    <button class="btn btn-primary" disabled><?= $year ?></button>
                    <a href="?year=<?= $year + 1 ?>" class="btn btn-outline-primary">
                        <?= $year + 1 ?> <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card-report">
            <h2 class="mb-0">
                <?php 
                $totalYear = array_sum(array_column($monthlyStats, 'total'));
                echo $totalYear;
                ?>
            </h2>
            <p class="mb-0">Total Feedback <?= $year ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-report bugs">
            <h2 class="mb-0">
                <?php 
                $bugsCount = 0;
                foreach ($typeStats as $ts) {
                    if ($ts['jenis_feedback'] === 'bug') $bugsCount = $ts['count'];
                }
                echo $bugsCount;
                ?>
            </h2>
            <p class="mb-0">Bug Reports</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-report features">
            <h2 class="mb-0">
                <?php 
                $featuresCount = 0;
                foreach ($typeStats as $ts) {
                    if ($ts['jenis_feedback'] === 'fitur_baru') $featuresCount = $ts['count'];
                }
                echo $featuresCount;
                ?>
            </h2>
            <p class="mb-0">Fitur Baru</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-report improvements">
            <h2 class="mb-0">
                <?php 
                $improvementsCount = 0;
                foreach ($typeStats as $ts) {
                    if ($ts['jenis_feedback'] === 'peningkatan') $improvementsCount = $ts['count'];
                }
                echo $improvementsCount;
                ?>
            </h2>
            <p class="mb-0">Peningkatan</p>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Trend Bulanan <?= $year ?></h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Status Distribution</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Table -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-table"></i> Detail Bulanan</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 report-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Selesai</th>
                            <th class="text-center">Dalam Proses</th>
                            <th class="text-center">Ditolak</th>
                            <th style="width: 200px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                       'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        
                        // Create indexed array by month
                        $monthData = [];
                        foreach ($monthlyStats as $stat) {
                            $monthData[$stat['month']] = $stat;
                        }
                        
                        for ($m = 1; $m <= 12; $m++):
                            $data = $monthData[$m] ?? ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'rejected' => 0];
                            $completionRate = $data['total'] > 0 ? ($data['completed'] / $data['total']) * 100 : 0;
                        ?>
                        <tr>
                            <td class="month-name"><?= $monthNames[$m] ?></td>
                            <td class="text-center"><?= $data['total'] ?></td>
                            <td class="text-center text-success"><?= $data['completed'] ?></td>
                            <td class="text-center text-warning"><?= $data['in_progress'] ?></td>
                            <td class="text-center text-danger"><?= $data['rejected'] ?></td>
                            <td>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-success" style="width: <?= $completionRate ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($completionRate, 0) ?>% selesai</small>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Popular Feedback -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-fire"></i> Top 10 Saran Populer</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Judul</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th class="text-center">Votes</th>
                            <th>Pengusul</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($popularFeedback)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($popularFeedback as $idx => $pop): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>feedback/detail/<?= $pop['id'] ?>">
                                    <?= htmlspecialchars($pop['judul'] ?? '') ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-jenis-<?= $pop['jenis_feedback'] ?>">
                                    <?php
                                    $jenisLabels = ['bug' => 'Bug', 'fitur_baru' => 'Fitur', 'peningkatan' => 'Improve'];
                                    echo $jenisLabels[$pop['jenis_feedback']] ?? $pop['jenis_feedback'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $pop['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $pop['status'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-info">
                                    <i class="fas fa-thumbs-up"></i> <?= $pop['vote_count'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($pop['user_nama'] ?? 'Unknown') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Chart Data
    const monthlyData = <?= json_encode($monthlyStats) ?>;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    // Prepare data arrays
    const totals = new Array(12).fill(0);
    const completed = new Array(12).fill(0);
    const inProgress = new Array(12).fill(0);
    
    monthlyData.forEach(item => {
        const monthIndex = item.month - 1;
        totals[monthIndex] = item.total;
        completed[monthIndex] = item.completed;
        inProgress[monthIndex] = item.in_progress;
    });
    
    // Monthly Line Chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Total',
                data: totals,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Selesai',
                data: completed,
                borderColor: '#2dce89',
                backgroundColor: 'transparent',
                tension: 0.4
            }, {
                label: 'Dalam Proses',
                data: inProgress,
                borderColor: '#ffc107',
                backgroundColor: 'transparent',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Status Pie Chart
    const statusData = <?= json_encode($statusStats) ?>;
    const statusLabels = [];
    const statusValues = [];
    const statusColors = {
        'diterima': '#6c757d',
        'dalam_proses': '#ffc107',
        'selesai': '#28a745',
        'ditolak': '#dc3545'
    };
    const chartColors = [];
    
    statusData.forEach(item => {
        statusLabels.push(item.status.charAt(0).toUpperCase() + item.status.slice(1).replace('_', ' '));
        statusValues.push(item.count);
        chartColors.push(statusColors[item.status] || '#6c757d');
    });
    
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: chartColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
