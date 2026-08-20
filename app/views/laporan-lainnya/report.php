<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Rekapitulasi Pelaporan</h3>
                <div class="card-tools">
                    <a href="<?= BASE_URL ?>laporan-lainnya/summary" class="btn btn-success btn-sm mr-1">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                    <a href="<?= BASE_URL ?>laporan-lainnya" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Year Filter -->
                <form method="GET" action="<?= BASE_URL ?>laporan-lainnya/summary" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="year" class="form-label">Tahun Laporan</label>
                            <select name="year" id="year" class="form-control">
                                <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>

                <!-- KPI Cards -->
                <?php
                $totalProcessed = (int)($performanceSummary['verified'] ?? 0) + (int)($performanceSummary['rejected'] ?? 0);
                $verificationRate = $totalProcessed > 0 ? round(((int)$performanceSummary['verified'] / $totalProcessed) * 100, 1) : 0;
                ?>
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Laporan</h5>
                                <h3 class="card-text"><?= number_format($performanceSummary['total_laporan']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Draf</h5>
                                <h3 class="card-text"><?= number_format($performanceSummary['draft']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Submitted</h5>
                                <h3 class="card-text"><?= number_format($performanceSummary['submitted']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Diverifikasi</h5>
                                <h3 class="card-text"><?= number_format($performanceSummary['verified']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Ditolak</h5>
                                <h3 class="card-text"><?= number_format($performanceSummary['rejected']) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white" style="background-color: <?= $verificationRate >= 80 ? '#17a2b8' : ($verificationRate >= 50 ? '#ffc107' : '#dc3545') ?>;">
                            <div class="card-body">
                                <h5 class="card-title">Tingkat Verifikasi</h5>
                                <h3 class="card-text"><?= $verificationRate ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend Chart -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Tren Bulanan Laporan (<?= $year ?>)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyTrendChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jenis Breakdown -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Breakdown per Jenis Laporan</h5>
                            </div>
                            <div class="card-body">
                                <?php if(!empty($jenisBreakdown)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Jenis Laporan</th>
                                                <th>Total</th>
                                                <th>Diverifikasi</th>
                                                <th>Submitted</th>
                                                <th>Draf</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($jenisBreakdown as $jenis): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($jenis['jenis_nama']) ?></td>
                                                <td><?= number_format($jenis['total_laporan']) ?></td>
                                                <td><span class="badge badge-success"><?= number_format($jenis['verified']) ?></span></td>
                                                <td><span class="badge badge-info"><?= number_format($jenis['submitted']) ?></span></td>
                                                <td><span class="badge badge-secondary"><?= number_format($jenis['draft']) ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">Tidak ada data jenis laporan untuk tahun ini.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Form -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Ekspor Data</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= BASE_URL ?>laporan-lainnya/export">
                                    <?= Security::getCsrfField() ?>
                                    <input type="hidden" name="year" value="<?= $year ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label for="exportStatus" class="form-label">Status</label>
                                            <select name="status" id="exportStatus" class="form-control">
                                                <option value="">-- Semua Status --</option>
                                                <option value="draft">Draf</option>
                                                <option value="submitted">Submitted</option>
                                                <option value="verified">Diverifikasi</option>
                                                <option value="rejected">Ditolak</option>
                                                <option value="archived">Diarsipkan</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="exportJenis" class="form-label">Jenis Laporan</label>
                                            <select name="jenis_id" id="exportJenis" class="form-control">
                                                <option value="">-- Semua Jenis --</option>
                                                <?php foreach($jenisBreakdown as $jenis): ?>
                                                <option value="<?= $jenis['jenis_id'] ?>"><?= htmlspecialchars($jenis['jenis_nama']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-file-csv"></i> Ekspor CSV
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Tindak Lanjut Laporan Ditolak -->
                <?php
                $rejectedReports = array_filter($recentReports ?? [], fn($r) => ($r['status'] ?? '') === 'rejected');
                ?>
                <?php if (!empty($rejectedReports) && (int)($performanceSummary['rejected'] ?? 0) > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-exclamation-circle mr-1"></i> Tindak Lanjut Laporan Ditolak (<?= count($rejectedReports) ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kode Laporan</th>
                                                <th>Jenis</th>
                                                <th>Tanggal</th>
                                                <th>Alasan Penolakan</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rejectedReports as $report): ?>
                                            <tr>
                                                <td><code><?= $report['kode_laporan'] ? htmlspecialchars($report['kode_laporan']) : '—' ?></code></td>
                                                <td><?= htmlspecialchars($report['jenis_nama']) ?></td>
                                                <td><?= $report['tanggal_kejadian'] ? htmlspecialchars($report['tanggal_kejadian']) : '—' ?></td>
                                                <td>
                                                    <span class="text-danger" title="<?= htmlspecialchars($report['catatan_verifikasi'] ?? '') ?>">
                                                        <?= htmlspecialchars(mb_strimwidth($report['catatan_verifikasi'] ?? 'Tidak ada catatan', 0, 80, '…')) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="<?= BASE_URL ?>laporan-lainnya/edit/<?= $report['id'] ?>" class="btn btn-warning btn-sm" title="Perbaiki Laporan">
                                                        <i class="fas fa-edit mr-1"></i> Perbaiki
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Reports -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Laporan Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php if(!empty($recentReports)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kode Laporan</th>
                                                <th>Jenis</th>
                                                <th>Tanggal Kejadian</th>
                                                <th>Desa</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recentReports as $report): ?>
                                            <tr>
                                                <td><code><?= $report['kode_laporan'] ? htmlspecialchars($report['kode_laporan']) : '—' ?></code></td>
                                                <td><?= htmlspecialchars($report['jenis_nama']) ?></td>
                                                <td><?= $report['tanggal_kejadian'] ? htmlspecialchars($report['tanggal_kejadian']) : '—' ?></td>
                                                <td><?= htmlspecialchars($report['nama_desa'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'draft' => ['secondary', 'Draf'],
                                                        'submitted' => ['primary', 'Submitted'],
                                                        'verified' => ['success', 'Diverifikasi'],
                                                        'rejected' => ['danger', 'Ditolak'],
                                                        'archived' => ['dark', 'Diarsipkan'],
                                                    ];
                                                    $sts = $statusMap[$report['status']] ?? ['secondary', $report['status']];
                                                    ?>
                                                    <span class="badge badge-<?= $sts[0] ?>"><?= $sts[1] ?></span>
                                                </td>
                                                <td>
                                                    <a href="<?= BASE_URL ?>laporan-lainnya/show/<?= $report['id'] ?>" class="btn btn-info btn-sm" title="Lihat">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">Belum ada laporan yang dibuat.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Trend Chart
const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
const monthlyTrendChart = new Chart(monthlyTrendCtx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Jumlah Laporan',
            data: [<?= implode(',', $monthlyTrend) ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
