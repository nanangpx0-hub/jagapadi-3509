<style>
.dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.dashboard-header h1 { font-size:22px; color:#1a73e8; }
.dashboard-header select { padding:8px 12px; border:1px solid #d0d0d0; border-radius:6px; font-size:14px; }
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px; }
.kpi-card { background:#fff; border-radius:8px; padding:20px; box-shadow:0 1px 6px rgba(0,0,0,0.06); text-align:center; }
.kpi-card .kpi-value { font-size:32px; font-weight:700; color:#1a73e8; }
.kpi-card .kpi-label { font-size:13px; color:#888; margin-top:4px; }
.kpi-card.kpi-warning .kpi-value { color:#f57c00; }
.kpi-card.kpi-danger .kpi-value { color:#c62828; }
.kpi-card.kpi-success .kpi-value { color:#2e7d32; }
.charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
.map-container { height:400px; border-radius:8px; margin-bottom:24px; }
.map-container .leaflet-container { border-radius:8px; }
.map-toggle { display:flex; gap:8px; margin-bottom:12px; }
.map-toggle button { padding:6px 16px; border:1px solid #d0d0d0; border-radius:6px; background:#fff; cursor:pointer; font-size:13px; }
.map-toggle button.active { background:#1a73e8; color:#fff; border-color:#1a73e8; }
.top-opt-table { width:100%; border-collapse:collapse; font-size:14px; }
.top-opt-table th, .top-opt-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #f0f0f0; }
.top-opt-table th { font-size:12px; color:#888; text-transform:uppercase; }
.quick-links { display:flex; gap:12px; flex-wrap:wrap; }
.quick-links a { padding:10px 20px; background:#1a73e8; color:#fff; border-radius:6px; text-decoration:none; font-size:14px; font-weight:500; }
.quick-links a:hover { background:#1557b0; }
.quick-links a.secondary { background:#fff; color:#1a73e8; border:1px solid #1a73e8; }
.quick-links a.secondary:hover { background:#f0f5ff; }
@media (max-width:768px) { .charts-grid { grid-template-columns:1fr; } .kpi-grid { grid-template-columns:repeat(2, 1fr); }
}
</style> <div class="dashboard-header"> <h1>Dashboard</h1> <div> <label for="tahun" style="font-size:14px;margin-right:8px;">Tahun:</label> <select id="tahun" onchange="refreshDashboard(this.value)"> <?php for ($y = (int) date('Y'); $y >= 2020; $y--): ?> <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option> <?php endfor; ?> </select> </div>
</div> <!-- KPI Cards -->
<div class="kpi-grid"> <div class="kpi-card"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['hama']['total_aktif'] ?? 0)) ?></div> <div class="kpi-label">Laporan Hama Aktif</div> </div> <div class="kpi-card"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['irigasi']['total_aktif'] ?? 0)) ?></div> <div class="kpi-label">Laporan Irigasi Aktif</div> </div> <?php if ($role === 'admin'): ?> <div class="kpi-card kpi-warning"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['hama']['total_submitted'] + $stats['irigasi']['total_submitted'])) ?></div> <div class="kpi-label">Menunggu Verifikasi</div> </div> <?php else: ?> <div class="kpi-card kpi-danger"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['hama']['total_ditolak'] + $stats['irigasi']['total_ditolak'])) ?></div> <div class="kpi-label">Ditolak</div> </div> <div class="kpi-card kpi-warning"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['hama']['total_draf'] + $stats['irigasi']['total_draf'])) ?></div> <div class="kpi-label">Draf Saya</div> </div> <?php endif; ?> <div class="kpi-card kpi-success"> <div class="kpi-value"><?= \App\Core\Security::e((string) ($stats['hama']['luas_serangan_total'] ?? 0)) ?></div> <div class="kpi-label">Luas Serangan (Ha)</div> </div>
</div> <!-- Charts -->
<div class="charts-grid"> <div class="card"><h2>Laporan Hama per Bulan</h2><canvas id="chartHama" height="200"></canvas></div> <div class="card"><h2>Laporan Irigasi per Bulan</h2><canvas id="chartIrigasi" height="200"></canvas></div>
</div> <!-- Map -->
<div class="card"> <h2>Peta Laporan</h2> <div class="map-toggle"> <button id="toggleHama" class="active" onclick="switchMapLayer('hama')">Hama</button> <button id="toggleIrigasi" onclick="switchMapLayer('irigasi')">Irigasi</button> </div> <div id="map" class="map-container"></div>
</div> <!-- Top OPT + Quick Links -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;"> <div class="card"> <h2>Top OPT</h2> <?php $topOpt = $stats['hama']['top_opt'] ?? []; ?> <?php if (count($topOpt) > 0): ?> <table class="top-opt-table"> <thead><tr><th>OPT</th><th>Laporan</th></tr></thead> <tbody> <?php foreach ($topOpt as $opt): ?> <tr> <td><?= \App\Core\Security::e($opt['nama_opt'] ?? '-') ?></td> <td><?= (int) ($opt['jumlah'] ?? 0) ?></td> </tr> <?php endforeach; ?> </tbody> </table> <?php else: ?> <p style="color:#888;font-size:14px;">Belum ada data.</p> <?php endif; ?> </div> <div class="card"> <h2>Status Laporan</h2> <table class="top-opt-table"> <thead><tr><th>Status</th><th>Hama</th><th>Irigasi</th></tr></thead> <tbody> <tr><td>Submitted</td><td><?= (int) ($stats['hama']['total_submitted'] ?? 0) ?></td><td><?= (int) ($stats['irigasi']['total_submitted'] ?? 0) ?></td></tr> <tr><td>Diverifikasi</td><td><?= (int) ($stats['hama']['total_diverifikasi'] ?? 0) ?></td><td><?= (int) ($stats['irigasi']['total_diverifikasi'] ?? 0) ?></td></tr> <tr><td>Ditolak</td><td><?= (int) ($stats['hama']['total_ditolak'] ?? 0) ?></td><td><?= (int) ($stats['irigasi']['total_ditolak'] ?? 0) ?></td></tr> <tr><td>Draf</td><td><?= (int) ($stats['hama']['total_draf'] ?? 0) ?></td><td><?= (int) ($stats['irigasi']['total_draf'] ?? 0) ?></td></tr> <tr><td>Diarsipkan</td><td><?= (int) ($stats['hama']['total_diarsipkan'] ?? 0) ?></td><td><?= (int) ($stats['irigasi']['total_diarsipkan'] ?? 0) ?></td></tr> </tbody> </table> </div>
</div> <!-- Quick Links -->
<div class="quick-links"> <?php if ($role === 'admin'): ?> <a href="/laporan-hama?status=Submitted">Verifikasi Laporan Hama</a> <a href="/laporan-irigasi?status=Submitted">Verifikasi Laporan Irigasi</a> <?php else: ?> <a href="/laporan-hama/create-light" style="background:#2e7d32;">+ Cepat</a> <a href="/laporan-hama/create">+ Buat Laporan Hama</a> <a href="/laporan-irigasi/create">+ Buat Laporan Irigasi</a> <?php endif; ?> <a href="/laporan-hama" class="secondary">Semua Laporan Hama</a> <a href="/laporan-irigasi" class="secondary">Semua Laporan Irigasi</a>
</div> <script>
const currentTahun = <?= (int) $tahun ?>;
const role = '<?= \App\Core\Security::e($role) ?>'; function refreshDashboard(tahun) { window.location.href = '/dashboard?tahun=' + tahun;
}
</script>
<script src="/assets/js/chart.umd.min.js"></script>
<script src="/assets/js/leaflet.js"></script>
<link rel="stylesheet" href="/assets/css/leaflet.css"/>
<link rel="stylesheet" href="/assets/css/map-enhancements.css"/>
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/map-enhancements.js"></script>
