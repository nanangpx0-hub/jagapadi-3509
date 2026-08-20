<?php require_once ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
    .btn-primary:hover, .btn-secondary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.2s ease;
    }
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .border-left-orange { border-left: 4px solid #fd7e14 !important; }
    .price-up { color: #e74a3b; }
    .price-down { color: #1cc88a; }
    .alert-badge { position: absolute; top: -5px; right: -5px; }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-money-bill-wave text-success"></i> <?= $data['page_title'] ?>
            </h1>
            <small class="text-muted">Monitoring harga dengan asal dan metode data yang dapat ditelusuri</small>
        </div>
        <div class="btn-group flex-wrap">
            <a href="<?= BASE_URL ?>hargaKomoditas/export?metode_data=non_simulasi" class="btn btn-success" id="btnExportCsv">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDataModal">
                <i class="fas fa-plus"></i> Tambah Data
            </button>
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#importExcelModal">
                <i class="fas fa-file-excel"></i> Import Excel
            </button>
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#scraperModal">
                <i class="fas fa-download"></i> Ambil Data
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info py-2" role="alert">
        <i class="fas fa-info-circle"></i>
        Analisis secara default tidak memasukkan data simulasi. Data <strong>aktual</strong>,
        <strong>estimasi</strong>, <strong>manual</strong>, dan <strong>simulasi</strong> diberi label terpisah.
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <form id="filterForm" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label" for="filterStartDate">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="filterStartDate" name="start_date">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterEndDate">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="filterEndDate" name="end_date">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterKomoditas">Jenis Komoditas</label>
                    <select class="form-control" id="filterKomoditas" name="jenis_komoditas">
                        <option value="">Semua</option>
                        <option value="gabah">Gabah (Semua)</option>
                        <option value="beras">Beras (Semua)</option>
                        <option value="gabah_kering_panen">Gabah Kering Panen</option>
                        <option value="gabah_kering_giling">Gabah Kering Giling</option>
                        <option value="beras_medium">Beras Medium</option>
                        <option value="beras_premium">Beras Premium</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="chartDays">Periode Grafik</label>
                    <select class="form-control" id="chartDays" name="chart_days">
                        <option value="7">7 Hari</option>
                        <option value="30" selected>30 Hari</option>
                        <option value="90">3 Bulan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterMetode">Metode Data</label>
                    <select class="form-control" id="filterMetode" name="metode_data">
                        <option value="non_simulasi" selected>Non-simulasi</option>
                        <option value="aktual">Aktual</option>
                        <option value="estimasi">Estimasi</option>
                        <option value="manual">Manual/Import</option>
                        <option value="simulasi">Simulasi</option>
                        <option value="semua">Semua metode</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block mb-2" style="visibility:hidden">Filler</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block mb-2" style="visibility:hidden">Filler</label>
                    <button type="button" class="btn btn-secondary w-100" id="btnResetFilter">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-orange shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Harga Gabah</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statGabah">
                                Rp <?= number_format($data['statistics']['harga_gabah'] ?? 0, 0, ',', '.') ?>
                            </div>
                            <small id="changeGabah" class="<?= ($data['statistics']['perubahan_gabah'] ?? 0) >= 0 ? 'price-up' : 'price-down' ?>">
                                <?= ($data['statistics']['perubahan_gabah'] ?? 0) >= 0 ? '↑' : '↓' ?>
                                <?= abs($data['statistics']['perubahan_gabah'] ?? 0) ?>%
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-seedling fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Harga Beras</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statBeras">
                                Rp <?= number_format($data['statistics']['harga_beras'] ?? 0, 0, ',', '.') ?>
                            </div>
                            <small id="changeBeras" class="<?= ($data['statistics']['perubahan_beras'] ?? 0) >= 0 ? 'price-up' : 'price-down' ?>">
                                <?= ($data['statistics']['perubahan_beras'] ?? 0) >= 0 ? '↑' : '↓' ?>
                                <?= abs($data['statistics']['perubahan_beras'] ?? 0) ?>%
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bowl-rice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2" style="position: relative;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Notifikasi Harga</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statAlerts">
                                <?= $data['unreadAlerts'] ?? 0 ?> baru
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bell fa-2x text-gray-300"></i>
                            <?php if(($data['unreadAlerts'] ?? 0) > 0): ?>
                            <span class="badge badge-danger alert-badge"><?= $data['unreadAlerts'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Data</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statTotal">
                                <?= $data['statistics']['total_records'] ?? 0 ?> record
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

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-chart-line"></i> Tren Harga
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-chart-bar"></i> Perbandingan Gabah vs Beras
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 320px;">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Dashboard Tabs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="trend-tab" data-toggle="tab" href="#trendPane" role="tab">
                        <i class="fas fa-chart-area"></i> Tren Harga
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="comparison-tab" data-toggle="tab" href="#comparisonPane" role="tab">
                        <i class="fas fa-balance-scale"></i> Perbandingan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="map-tab" data-toggle="tab" href="#mapPane" role="tab">
                        <i class="fas fa-map-marked-alt"></i> Peta Sebaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="alerts-tab" data-toggle="tab" href="#alertsPane" role="tab">
                        <i class="fas fa-bell"></i> Notifikasi
                        <span class="badge badge-danger ml-1" id="alertBadge" style="display:<?= ($data['unreadAlerts'] ?? 0) > 0 ? 'inline' : 'none' ?>">
                            <?= $data['unreadAlerts'] ?? 0 ?>
                        </span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="dashboardTabContent">
                <!-- Trend Tab -->
                <div class="tab-pane fade show active" id="trendPane" role="tabpanel">
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-left-success">
                                <div class="card-header py-2">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-chart-line"></i> Statistik Harga per Komoditas
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="statsTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Komoditas</th>
                                                    <th>Rata-rata</th>
                                                    <th>Tertinggi</th>
                                                    <th>Terendah</th>
                                                    <th>Jumlah Data</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comparison Tab -->
                <div class="tab-pane fade" id="comparisonPane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div style="height: 350px;">
                                <canvas id="detailComparisonChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="font-weight-bold"><i class="fas fa-info-circle text-info"></i> Keterangan</h6>
                                    <p class="small mb-2">Perbandingan harga rata-rata antara komoditas gabah dan beras selama 6 bulan terakhir.</p>
                                    <div id="comparisonInfo" class="small">
                                        <div class="mb-2">
                                            <span class="text-warning">●</span> <strong>Gabah:</strong> Harga jual petani
                                        </div>
                                        <div>
                                            <span class="text-info">●</span> <strong>Beras:</strong> Harga konsumen
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Map Tab -->
                <div class="tab-pane fade" id="mapPane" role="tabpanel">
                    <div id="priceMap" style="height: 400px; border-radius: 8px;"></div>
                    <div class="mt-3 text-center small text-muted">
                        <i class="fas fa-info-circle"></i> Peta menampilkan sebaran harga rata-rata per wilayah. Klik marker untuk detail.
                    </div>
                </div>
                
                <!-- Alerts Tab -->
                <div class="tab-pane fade" id="alertsPane" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="m-0"><i class="fas fa-bell text-warning"></i> Notifikasi Fluktuasi Harga</h6>
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <button class="btn btn-sm btn-outline-secondary" id="btnMarkAllRead">
                            <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                        </button>
                        <?php endif; ?>
                    </div>
                    <div id="alertsContainer">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-spinner fa-spin"></i> Memuat notifikasi...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-center">
                <h6 class="m-0 font-weight-bold text-success mr-3">
                    <i class="fas fa-table"></i> Data Harga Komoditas
                </h6>
                <select class="form-control form-control-sm" id="perPageSelect" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">Semua</option>
                </select>
            </div>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-danger btn-sm" id="btnDeleteSelected" disabled>
                    <i class="fas fa-trash"></i> Hapus Terpilih
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="thead-light">
                        <tr>
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th>Tanggal</th>
                            <th>Komoditas</th>
                            <th>Harga</th>
                            <th>Lokasi</th>
                            <th>Sumber</th>
                            <th>Metode</th>
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="<?= ($_SESSION['role'] ?? '') === 'admin' ? 8 : 6 ?>" class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="pagination" class="d-flex justify-content-between align-items-center mt-3">
                <div id="paginationInfo"></div>
                <nav><ul class="pagination mb-0" id="paginationButtons"></ul></nav>
            </div>
        </div>
    </div>
</div>

<!-- Scraper Modal -->
<?php if($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="scraperModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-download"></i> Ambil Data Harga Komoditas</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="scraperForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    
                    <!-- Source Selection -->
                    <div class="form-group">
                        <label for="scraperSource">Sumber Data</label>
                        <select class="form-control" id="scraperSource" name="source">
                            <option value="siskaperbapo" selected>SISKAPERBAPO Jatim (Resmi Disperindag Jatim)</option>
                            <option value="simulation">Simulasi eksplisit (Data Sintetis)</option>
                        </select>
                    </div>

                    <!-- Year Selection -->
                    <div class="form-group">
                        <label for="scraperYear">Tahun</label>
                        <select class="form-control" name="year" id="scraperYear">
                            <?php for ($y = 2020; $y <= (int) date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <small class="form-text text-muted">SISKAPERBAPO memuat harga beras aktual. Estimasi gabah ditandai terpisah; kegagalan sumber tidak membuat simulasi otomatis.</small>
                    </div>
                    
                    <!-- Scraping Mode Selection -->
                    <div class="form-group">
                        <span class="d-block mb-2 font-weight-normal">Mode Pengambilan Data</span>
                        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                            <label class="btn btn-outline-warning active flex-fill" id="labelModeMonthly">
                                <input type="radio" name="scrapeMode" id="modeMonthly" value="monthly" checked>
                                <i class="fas fa-calendar-day mr-1"></i> Bulanan
                            </label>
                            <label class="btn btn-outline-success flex-fill" id="labelModeYearly">
                                <input type="radio" name="scrapeMode" id="modeYearly" value="yearly">
                                <i class="fas fa-calendar-alt mr-1"></i> Tahunan (Jan-Des)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Month Selection (only for monthly mode) -->
                    <div class="form-group" id="monthSelectGroup">
                        <label for="scraperMonth">Bulan</label>
                        <select class="form-control" name="month" id="scraperMonth">
                            <?php 
                            $bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $bulanNames[$m-1] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Yearly Progress (only for yearly mode) -->
                    <div id="yearlyProgressGroup" style="display: none;">
                        <span class="d-block mb-2 font-weight-normal">Progress Pengambilan Data Tahunan</span>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" id="yearlyProgressBar" 
                                 role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                0%
                            </div>
                        </div>
                        <div class="d-flex flex-wrap mb-2" id="monthStatusGrid">
                            <?php foreach ($bulanNames as $idx => $nama): ?>
                            <div class="month-status-item mr-2 mb-2" id="monthStatus<?= $idx + 1 ?>">
                                <span class="badge badge-secondary"><?= substr($nama, 0, 3) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="yearlyStatusText" class="text-muted small mb-2">
                            Siap mengambil data untuk 12 bulan...
                        </div>
                    </div>
                    
                    <!-- Result Display -->
                    <div id="scraperResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-danger" id="btnCancelScraper" style="display: none;">
                        <i class="fas fa-stop"></i> Batalkan
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnRunScraper">
                        <i class="fas fa-play" id="scraperBtnIcon"></i> <span id="scraperBtnText">Jalankan Scraper</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    <input type="hidden" name="id" id="editId">
                    <div class="form-group">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="editTanggal" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Komoditas <span class="text-danger">*</span></label>
                        <select class="form-control" id="editKomoditas" name="jenis_komoditas" required>
                            <?php foreach ($data['komoditasTypes'] as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Harga (Rp/kg) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="editHarga" name="harga" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Lokasi</label>
                        <input type="text" class="form-control" id="editLokasi" name="lokasi" value="Jember">
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" id="editKeterangan" name="keterangan" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveEdit">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Data Modal -->
<?php if($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="addDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Data Harga</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="<?= BASE_URL ?>hargaKomoditas/store" method="POST">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Komoditas <span class="text-danger">*</span></label>
                        <select class="form-control" name="jenis_komoditas" required>
                            <?php foreach ($data['komoditasTypes'] as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Harga (Rp/kg) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="harga" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Lokasi</label>
                        <input type="text" class="form-control" name="lokasi" value="Jember">
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-excel"></i> Import Data dari Excel</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Format file yang didukung:</strong> xlsx, csv (maks. 10 MB)<br>
                    <strong>Kolom wajib:</strong> tanggal, jenis_komoditas, harga<br>
                    <strong>Kolom opsional:</strong> satuan, lokasi, keterangan<br>
                    <strong>Jenis komoditas:</strong> gabah_kering_panen, gabah_kering_giling, beras_premium, beras_medium
                </div>
                
                <form id="importExcelForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    <div class="form-group">
                        <label>Pilih File Excel</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="excelFile" name="excel_file" accept=".xlsx,.csv" required>
                            <label class="custom-file-label" for="excelFile">Pilih file...</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <a href="<?= BASE_URL ?>hargaKomoditas/downloadTemplate" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-download"></i> Download Template CSV
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary ml-2" id="btnPreviewImport">
                            <i class="fas fa-eye"></i> Preview Data
                        </button>
                    </div>
                    
                    <!-- Preview Table -->
                    <div id="previewContainer" style="display: none;">
                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-table"></i> Preview Data</h6>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="previewTable">
                                <thead class="thead-light"><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <small class="text-muted" id="previewInfo"></small>
                    </div>
                    
                    <!-- Progress -->
                    <div id="importProgress" style="display: none;">
                        <hr>
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted small">Mengimpor data...</p>
                    </div>
                    
                    <!-- Result -->
                    <div id="importResult" style="display: none;">
                        <hr>
                        <div class="alert mb-0">
                            <h6 class="alert-heading"><i class="fas fa-check-circle"></i> <span id="resultTitle"></span></h6>
                            <hr>
                            <p class="mb-1"><strong>Total Diproses:</strong> <span id="resultTotal">0</span></p>
                            <p class="mb-1 text-success"><strong>Berhasil:</strong> <span id="resultSuccess">0</span></p>
                            <p class="mb-1 text-danger"><strong>Gagal:</strong> <span id="resultFailed">0</span></p>
                            <div id="resultErrors" style="display: none;">
                                <hr>
                                <p class="mb-1 text-danger"><strong>Error:</strong></p>
                                <ul id="resultErrorList" class="small mb-0"></ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-info" id="btnImportExcel">
                    <i class="fas fa-upload"></i> Import Data
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Leaflet JS -->
<script src="<?= BASE_URL ?>public/vendor/js/leaflet.js"></script>

<script>
    // Global variables
    let currentPage = 1;
    let perPage = localStorage.getItem('hargaKomoditas_perPage') || 10;
    if (perPage === 'all') perPage = 999999;
    else perPage = parseInt(perPage);
    
    let trendChart = null;
    let comparisonChart = null;
    let detailComparisonChart = null;
    let priceMap = null;
    
    const BASE_URL = '<?= BASE_URL ?>';
    const isAdmin = <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?>;
    const csrfToken = <?= json_encode(Security::generateCsrfToken()) ?>;
    const tableColumnCount = isAdmin ? 8 : 6;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[char]);
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.custom-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `custom-toast alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px;';
        const icon = document.createElement('i');
        icon.className = `fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : 'info-circle'} mr-2`;
        toast.appendChild(icon);
        toast.appendChild(document.createTextNode(String(message)));
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Format currency
    function formatRupiah(num) {
        const value = Number(num);
        return 'Rp ' + (Number.isFinite(value) ? Math.round(value) : 0).toLocaleString('id-ID');
    }
    
    // Load data
    function loadData() {
        const startDate = document.getElementById('filterStartDate').value;
        const endDate = document.getElementById('filterEndDate').value;
        const komoditas = document.getElementById('filterKomoditas').value;
        const metode = document.getElementById('filterMetode').value;
        
        const params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            jenis_komoditas: komoditas,
            metode_data: metode,
            limit: perPage,
            offset: (currentPage - 1) * perPage
        });

        const exportParams = new URLSearchParams(params);
        exportParams.delete('limit');
        exportParams.delete('offset');
        document.getElementById('btnExportCsv').href = `${BASE_URL}hargaKomoditas/export?${exportParams}`;
        
        fetch(`${BASE_URL}hargaKomoditas/getData?${params}`)
            .then(r => r.json())
            .then(response => {
                if (!response.success) throw new Error(response.error);
                
                renderTable(response.data);
                renderPagination(response.total);
                updateStatistics(response.overall);
                updateStatsTable(response.statistics);
            })
            .catch(err => {
                console.error('Load error:', err);
                showToast('Gagal memuat data', 'danger');
            });
    }
    
    // Render table
    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        
        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${tableColumnCount}" class="text-center text-muted">Tidak ada data</td></tr>`;
            return;
        }
        
        tbody.innerHTML = data.map(row => `
            <tr data-id="${Number(row.id)}">
                ${isAdmin ? `<td><input type="checkbox" class="row-checkbox" value="${Number(row.id)}"></td>` : ''}
                <td>${escapeHtml(row.tanggal)}</td>
                <td><span class="badge badge-${String(row.jenis_komoditas).includes('gabah') ? 'warning' : 'info'}">${escapeHtml(row.komoditas_label)}</span></td>
                <td><strong>${escapeHtml(row.harga_formatted)}</strong></td>
                <td>${escapeHtml(row.lokasi)}</td>
                <td><span class="badge badge-secondary">${escapeHtml(row.sumber_data)}</span></td>
                <td><span class="badge badge-${row.metode_data === 'aktual' ? 'success' : row.metode_data === 'simulasi' ? 'dark' : row.metode_data === 'estimasi' ? 'warning' : 'info'}">${escapeHtml(row.metode_data)}</span></td>
                ${isAdmin ? `
                <td>
                    <button class="btn btn-sm btn-primary btn-edit" data-id="${Number(row.id)}"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger btn-delete" data-id="${Number(row.id)}"><i class="fas fa-trash"></i></button>
                </td>
                ` : ''}
            </tr>
        `).join('');
        
        if (isAdmin) {
            tbody.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', () => openEditModal(btn.dataset.id));
            });
            tbody.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', () => deleteRecord(btn.dataset.id));
            });
            tbody.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.addEventListener('change', updateDeleteButton);
            });
        }
    }
    
    // Render pagination
    function renderPagination(total) {
        const totalPages = Math.ceil(total / perPage);
        const info = document.getElementById('paginationInfo');
        const buttons = document.getElementById('paginationButtons');
        
        const start = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, total);
        info.textContent = `Menampilkan ${start}-${end} dari ${total}`;
        
        let html = '';
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>
                 </li>`;
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                     </li>`;
        }
        
        html += `<li class="page-item ${totalPages === 0 || currentPage >= totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>
                 </li>`;
        
        buttons.innerHTML = html;
        
        buttons.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    loadData();
                }
            });
        });
    }
    
    // Update statistics
    function updateStatistics(stats) {
        document.getElementById('statGabah').textContent = formatRupiah(stats.harga_gabah || 0);
        document.getElementById('statBeras').textContent = formatRupiah(stats.harga_beras || 0);
        document.getElementById('statTotal').textContent = `${stats.total_records || 0} record`;
        
        const changeGabah = stats.perubahan_gabah || 0;
        const changeBeras = stats.perubahan_beras || 0;
        
        document.getElementById('changeGabah').textContent = `${changeGabah >= 0 ? '↑' : '↓'} ${Math.abs(changeGabah)}%`;
        document.getElementById('changeGabah').className = changeGabah >= 0 ? 'price-up' : 'price-down';
        
        document.getElementById('changeBeras').textContent = `${changeBeras >= 0 ? '↑' : '↓'} ${Math.abs(changeBeras)}%`;
        document.getElementById('changeBeras').className = changeBeras >= 0 ? 'price-up' : 'price-down';
    }
    
    // Update stats table
    function updateStatsTable(stats) {
        const tbody = document.querySelector('#statsTable tbody');
        if (!stats || stats.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Tidak ada data</td></tr>';
            return;
        }
        
        const labels = {
            'gabah_kering_panen': 'Gabah Kering Panen (GKP)',
            'gabah_kering_giling': 'Gabah Kering Giling (GKG)',
            'beras_medium': 'Beras Medium',
            'beras_premium': 'Beras Premium'
        };
        
        tbody.innerHTML = stats.map(row => `
            <tr>
                <td>${labels[row.jenis_komoditas] || row.jenis_komoditas}</td>
                <td>${formatRupiah(row.rata_rata)}</td>
                <td>${formatRupiah(row.tertinggi)}</td>
                <td>${formatRupiah(row.terendah)}</td>
                <td>${row.total_records}</td>
            </tr>
        `).join('');
    }
    
    // Load charts
    function loadCharts() {
        const days = document.getElementById('chartDays').value;
        const analysisParams = new URLSearchParams({
            metode_data: document.getElementById('filterMetode').value,
            jenis_komoditas: document.getElementById('filterKomoditas').value
        });
        
        // Trend chart
        analysisParams.set('type', 'trend');
        analysisParams.set('days', days);
        fetch(`${BASE_URL}hargaKomoditas/getChartData?${analysisParams}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                
                if (trendChart) trendChart.destroy();
                trendChart = new Chart(document.getElementById('trendChart'), {
                    type: 'line',
                    data: { labels: data.labels, datasets: data.datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: false } }
                    }
                });
            });
        
        // Comparison chart
        analysisParams.set('type', 'comparison');
        analysisParams.delete('days');
        fetch(`${BASE_URL}hargaKomoditas/getChartData?${analysisParams}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                
                if (comparisonChart) comparisonChart.destroy();
                comparisonChart = new Chart(document.getElementById('comparisonChart'), {
                    type: 'bar',
                    data: { labels: data.labels, datasets: data.datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true } },
                        scales: { y: { beginAtZero: false } }
                    }
                });
            });
    }
    
    // Load alerts
    function loadAlerts() {
        fetch(`${BASE_URL}hargaKomoditas/getAlerts`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                
                const container = document.getElementById('alertsContainer');
                const badge = document.getElementById('alertBadge');
                
                if (badge) {
                    badge.textContent = data.unread_count;
                    badge.style.display = data.unread_count > 0 ? 'inline' : 'none';
                }
                
                document.getElementById('statAlerts').textContent = `${data.unread_count} baru`;
                
                if (!data.alerts || data.alerts.length === 0) {
                    container.innerHTML = `<div class="alert alert-success text-center"><i class="fas fa-check-circle"></i> Tidak ada notifikasi fluktuasi harga.</div>`;
                    return;
                }
                
                container.innerHTML = data.alerts.map(alert => `
                    <div class="alert alert-${alert.level === 'critical' ? 'danger' : 'warning'} ${!alert.is_read ? 'border-left-4' : ''} mb-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${escapeHtml(alert.komoditas)}</strong>
                                <span class="badge badge-${alert.tipe === 'naik' ? 'danger' : 'success'} ml-2">
                                    ${alert.tipe === 'naik' ? '↑' : '↓'} ${escapeHtml(alert.persentase)}%
                                </span>
                            </div>
                            <small>${escapeHtml(alert.tanggal)}</small>
                        </div>
                        <small class="text-muted">${escapeHtml(alert.harga_sebelum)} → ${escapeHtml(alert.harga_sesudah)}</small>
                    </div>
                `).join('');
            });
    }
    
    // Initialize map
    function initPriceMap() {
        if (priceMap) return;
        if (typeof L === 'undefined') return;
        
        priceMap = L.map('priceMap').setView([-8.1706, 113.7003], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(priceMap);
        
        const mapParams = new URLSearchParams({
            metode_data: document.getElementById('filterMetode').value
        });
        fetch(`${BASE_URL}hargaKomoditas/getMapData?${mapParams}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) return;
                
                data.data.forEach(item => {
                    const avgPrice = item.rata_rata_formatted;
                    L.circleMarker([item.latitude, item.longitude], {
                        radius: 12,
                        fillColor: item.komoditas.includes('Gabah') ? '#fd7e14' : '#36b9cc',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(priceMap).bindPopup(`
                        <strong>${escapeHtml(item.lokasi)}</strong><br>
                        <span class="badge badge-secondary">${escapeHtml(item.komoditas)}</span><br>
                        Rata-rata: ${escapeHtml(avgPrice)}<br>
                        Data: ${Number(item.jumlah_data)} record
                    `);
                });
            });
    }
    
    // Tab handlers
    document.querySelectorAll('#dashboardTabs a[data-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('href');
            if (target === '#mapPane') {
                setTimeout(initPriceMap, 100);
                if (priceMap) priceMap.invalidateSize();
            } else if (target === '#alertsPane') {
                loadAlerts();
            } else if (target === '#comparisonPane') {
                if (!detailComparisonChart) {
                    const detailParams = new URLSearchParams({
                        type: 'comparison',
                        metode_data: document.getElementById('filterMetode').value,
                        jenis_komoditas: document.getElementById('filterKomoditas').value
                    });
                    fetch(`${BASE_URL}hargaKomoditas/getChartData?${detailParams}`)
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) return;
                            detailComparisonChart = new Chart(document.getElementById('detailComparisonChart'), {
                                type: 'bar',
                                data: { labels: data.labels, datasets: data.datasets },
                                options: { responsive: true, maintainAspectRatio: false }
                            });
                        });
                }
            }
        });
    });
    
    // Event handlers
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        if (priceMap) { priceMap.remove(); priceMap = null; }
        if (detailComparisonChart) { detailComparisonChart.destroy(); detailComparisonChart = null; }
        loadData();
        loadCharts();
    });
    
    document.getElementById('btnResetFilter').addEventListener('click', function() {
        document.getElementById('filterStartDate').value = '';
        document.getElementById('filterEndDate').value = '';
        document.getElementById('filterKomoditas').value = '';
        document.getElementById('filterMetode').value = 'non_simulasi';
        document.getElementById('chartDays').value = '30';
        currentPage = 1;
        loadData();
        loadCharts();
        showToast('Filter telah di-reset', 'info');
    });
    
    document.getElementById('chartDays').addEventListener('change', loadCharts);
    
    document.getElementById('perPageSelect').addEventListener('change', function() {
        perPage = this.value === 'all' ? 999999 : parseInt(this.value);
        localStorage.setItem('hargaKomoditas_perPage', this.value);
        currentPage = 1;
        loadData();
    });
    
    document.getElementById('btnMarkAllRead')?.addEventListener('click', function() {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        fetch(`${BASE_URL}hargaKomoditas/markAlertRead`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadAlerts();
                    showToast('Semua notifikasi ditandai sudah dibaca', 'success');
                }
            });
    });
    
    <?php if($_SESSION['role'] === 'admin'): ?>
    // Admin functions
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        updateDeleteButton();
    });
    
    function updateDeleteButton() {
        const checked = document.querySelectorAll('.row-checkbox:checked').length;
        document.getElementById('btnDeleteSelected').disabled = checked === 0;
    }
    
    document.getElementById('btnDeleteSelected')?.addEventListener('click', function() {
        const ids = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (ids.length === 0) return;
        if (!confirm(`Hapus ${ids.length} data terpilih?`)) return;
        
        const formData = new FormData();
        formData.append('csrf_token', '<?= Security::generateCsrfToken() ?>');
        formData.append('ids', JSON.stringify(ids));
        
        fetch(`${BASE_URL}hargaKomoditas/deleteMultiple`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadData();
                } else {
                    showToast(data.error, 'danger');
                }
            });
    });
    
    // --- Scraper Mode & Form Handler (Bulanan & Tahunan) ---
    const csrfTokenScraper = '<?= Security::generateCsrfToken() ?>';
    const bulanNamesScraper = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let scraperCancelled = false;

    const modeMonthly = document.getElementById('modeMonthly');
    const modeYearly = document.getElementById('modeYearly');
    const monthSelectGroup = document.getElementById('monthSelectGroup');
    const yearlyProgressGroup = document.getElementById('yearlyProgressGroup');
    const scraperBtnText = document.getElementById('scraperBtnText');
    const btnCancelScraper = document.getElementById('btnCancelScraper');

    if (modeMonthly && modeYearly) {
        modeMonthly.addEventListener('change', function() {
            if (this.checked) {
                if (monthSelectGroup) monthSelectGroup.style.display = 'block';
                if (yearlyProgressGroup) yearlyProgressGroup.style.display = 'none';
                if (scraperBtnText) scraperBtnText.textContent = 'Jalankan Scraper';
            }
        });

        modeYearly.addEventListener('change', function() {
            if (this.checked) {
                if (monthSelectGroup) monthSelectGroup.style.display = 'none';
                if (yearlyProgressGroup) yearlyProgressGroup.style.display = 'block';
                if (scraperBtnText) scraperBtnText.textContent = 'Ambil Data Tahunan';
            }
        });
    }

    if (btnCancelScraper) {
        btnCancelScraper.addEventListener('click', function() {
            scraperCancelled = true;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membatalkan...';
        });
    }

    function resetYearlyProgress() {
        const progressBar = document.getElementById('yearlyProgressBar');
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
        }
        
        for (let i = 1; i <= 12; i++) {
            const badge = document.querySelector(`#monthStatus${i} span`);
            if (badge) {
                badge.className = 'badge badge-secondary';
            }
        }
        
        const statusText = document.getElementById('yearlyStatusText');
        if (statusText) statusText.textContent = 'Siap mengambil data untuk 12 bulan...';
    }

    function updateMonthStatus(month, status) {
        const badge = document.querySelector(`#monthStatus${month} span`);
        if (badge) {
            if (status === 'loading') {
                badge.className = 'badge badge-warning';
            } else if (status === 'success') {
                badge.className = 'badge badge-success';
            } else if (status === 'failed') {
                badge.className = 'badge badge-danger';
            } else if (status === 'skipped') {
                badge.className = 'badge badge-secondary';
            } else if (status === 'nodata') {
                badge.className = 'badge badge-info';
            }
        }
    }

    function updateYearlyProgress(completed, total, currentMonth) {
        const progressBar = document.getElementById('yearlyProgressBar');
        const percentage = Math.round((completed / total) * 100);
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage + '%';
        }
        
        const statusText = document.getElementById('yearlyStatusText');
        if (statusText && currentMonth) {
            statusText.textContent = `Mengambil data ${bulanNamesScraper[currentMonth - 1]}... (${completed}/${total})`;
        }
    }

    const scraperForm = document.getElementById('scraperForm');
    if (scraperForm) {
        scraperForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const isYearlyMode = document.getElementById('modeYearly')?.checked;
            if (isYearlyMode) {
                runYearlyScraper();
            } else {
                runMonthlyScraper();
            }
        });
    }

    function runMonthlyScraper() {
        const btn = document.getElementById('btnRunScraper');
        const resultDiv = document.getElementById('scraperResult');
        const btnIcon = document.getElementById('scraperBtnIcon');
        const btnText = document.getElementById('scraperBtnText');
        
        if (btn) btn.disabled = true;
        if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
        if (btnText) btnText.textContent = 'Memproses...';
        if (resultDiv) resultDiv.style.display = 'none';

        const formData = new FormData(document.getElementById('scraperForm'));

        fetch(`${BASE_URL}hargaKomoditas/runScraper`, {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            if (resultDiv) {
                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Berhasil!</strong><br>
                        Sumber: ${escapeHtml(data.source)}<br>
                        Baru: ${Number(data.records_inserted || 0)}, diperbarui: ${Number(data.records_updated || 0)}, tidak berubah: ${Number(data.records_skipped || 0)}<br>
                        Waktu: ${Number(data.execution_time || 0)}s`;
                    showToast(data.message, 'success');
                    loadData();
                    loadCharts();
                    loadAlerts();
                } else {
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>${escapeHtml(data.error || data.message)}`;
                }
            }
        })
        .catch(err => {
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Error:</strong> ${escapeHtml(err.message)}`;
            }
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (btnIcon) btnIcon.className = 'fas fa-play';
            if (btnText) btnText.textContent = 'Jalankan Scraper';
        });
    }

    async function runYearlyScraper() {
        const btn = document.getElementById('btnRunScraper');
        const resultDiv = document.getElementById('scraperResult');
        const btnIcon = document.getElementById('scraperBtnIcon');
        const btnText = document.getElementById('scraperBtnText');
        const cancelBtn = document.getElementById('btnCancelScraper');
        const year = document.getElementById('scraperYear').value;
        const source = document.getElementById('scraperSource').value;
        
        function resetButtonState() {
            const b = document.getElementById('btnRunScraper');
            const c = document.getElementById('btnCancelScraper');
            if (b) {
                b.disabled = false;
                b.innerHTML = '<i class="fas fa-play" id="scraperBtnIcon"></i> <span id="scraperBtnText">Ambil Data Tahunan</span>';
            }
            if (c) c.style.display = 'none';
        }
        
        try {
            scraperCancelled = false;
            
            if (btn) btn.disabled = true;
            if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
            if (btnText) btnText.textContent = 'Mengambil Data...';
            if (cancelBtn) {
                cancelBtn.style.display = 'inline-block';
                cancelBtn.disabled = false;
                cancelBtn.innerHTML = '<i class="fas fa-stop"></i> Batalkan';
            }
            if (resultDiv) resultDiv.style.display = 'none';
            resetYearlyProgress();
            
            const results = {
                success: 0,
                failed: 0,
                noData: [],
                totalRecords: 0,
                errors: []
            };
            
            const statusText = document.getElementById('yearlyStatusText');
            if (statusText) statusText.textContent = 'Memulai pengambilan data tahunan...';
            const now = new Date();
            const maxMonth = Number(year) === now.getFullYear() ? now.getMonth() + 1 : 12;
            for (let futureMonth = maxMonth + 1; futureMonth <= 12; futureMonth++) {
                updateMonthStatus(futureMonth, 'skipped');
            }
            
            for (let month = 1; month <= maxMonth; month++) {
                if (scraperCancelled) {
                    if (statusText) statusText.textContent = `Proses dibatalkan pada bulan ${bulanNamesScraper[month - 1]}`;
                    for (let i = month; i <= maxMonth; i++) {
                        updateMonthStatus(i, 'skipped');
                    }
                    break;
                }
                
                updateMonthStatus(month, 'loading');
                updateYearlyProgress(month - 1, maxMonth, month);
                
                try {
                    const formData = new FormData();
                    formData.append('csrf_token', csrfTokenScraper);
                    formData.append('year', year);
                    formData.append('month', month);
                    formData.append('source', source);
                    
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000);
                    
                    const response = await fetch(`${BASE_URL}hargaKomoditas/runScraper`, {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) throw new Error(`HTTP Error ${response.status} (${response.statusText || 'Server Response Error'})`);
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        updateMonthStatus(month, 'success');
                        results.success++;
                        results.totalRecords += data.records_success || 0;
                    } else if (data.no_data) {
                        updateMonthStatus(month, 'nodata');
                        results.noData.push(bulanNamesScraper[month - 1]);
                    } else {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNamesScraper[month - 1]}: ${data.error || data.message || 'Gagal memproses data API/Database'}`);
                    }
                    
                } catch (err) {
                    if (err.name === 'AbortError') {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNamesScraper[month - 1]}: Request timeout (60s) - Batas waktu permintaan terlampaui`);
                    } else {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNamesScraper[month - 1]}: ${err.message}`);
                    }
                }
                
                updateYearlyProgress(month, maxMonth, month);
                
                if (month < 12 && !scraperCancelled) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }
            
            if (resultDiv) {
                resultDiv.style.display = 'block';
                const progressBar = document.getElementById('yearlyProgressBar');
                
                const noDataMsg = results.noData.length > 0 
                    ? `<br><span class="text-info"><i class="fas fa-info-circle"></i> Data belum tersedia untuk: ${escapeHtml(results.noData.join(', '))}</span>` 
                    : '';
                
                if (scraperCancelled) {
                    resultDiv.className = 'alert alert-warning';
                    resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Dibatalkan</strong><br>
                        Bulan berhasil: ${results.success}<br>
                        Bulan gagal: ${results.failed}<br>
                        Total record: ${results.totalRecords}${noDataMsg}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-warning';
                } else if (results.failed === 0 && results.noData.length === 0) {
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Berhasil!</strong><br>
                        Semua ${maxMonth} bulan yang tersedia berhasil diproses<br>
                        Total record: ${results.totalRecords}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-success';
                    loadData();
                    loadCharts();
                    loadAlerts();
                } else if (results.failed === 0 && results.noData.length > 0) {
                    resultDiv.className = 'alert alert-info';
                    resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Proses Selesai</strong><br>
                        Bulan dengan data: ${results.success}<br>
                        Total record: ${results.totalRecords}${noDataMsg}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-info';
                    loadData();
                    loadCharts();
                    loadAlerts();
                } else if (results.success > 0) {
                    resultDiv.className = 'alert alert-warning';
                    resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Sebagian Berhasil</strong><br>
                        Bulan berhasil: ${results.success}<br>
                        Bulan gagal: ${results.failed}<br>
                        Total record: ${results.totalRecords}${noDataMsg}<br>
                        <small class="text-muted">${escapeHtml(results.errors.slice(0, 3).join(', '))}</small>`;
                    if (progressBar) progressBar.className = 'progress-bar bg-warning';
                    loadData();
                    loadCharts();
                } else {
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>
                        Tidak ada data yang berhasil diambil<br>
                        <small class="text-muted">${escapeHtml(results.errors.slice(0, 3).join(', '))}</small>${noDataMsg}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-danger';
                }
            }
            
            if (statusText) statusText.textContent = scraperCancelled ? 'Proses dibatalkan' : 'Selesai!';
            
        } catch (err) {
            console.error('Yearly scraper error:', err);
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Error!</strong><br>${escapeHtml(err.message)}`;
            }
        } finally {
            resetButtonState();
        }
    }
    
    function openEditModal(id) {
        fetch(`${BASE_URL}hargaKomoditas/getRecord/${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);
                document.getElementById('editId').value = data.data.id;
                document.getElementById('editTanggal').value = data.data.tanggal;
                document.getElementById('editKomoditas').value = data.data.jenis_komoditas;
                document.getElementById('editHarga').value = data.data.harga;
                document.getElementById('editLokasi').value = data.data.lokasi;
                document.getElementById('editKeterangan').value = data.data.keterangan || '';
                $('#editModal').modal('show');
            });
    }
    
    document.getElementById('btnSaveEdit')?.addEventListener('click', function() {
        const id = document.getElementById('editId').value;
        const formData = new FormData(document.getElementById('editForm'));
        
        fetch(`${BASE_URL}hargaKomoditas/update/${id}`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    $('#editModal').modal('hide');
                    loadData();
                } else {
                    showToast(data.error, 'danger');
                }
            });
    });
    
    function deleteRecord(id) {
        if (!confirm('Hapus data ini?')) return;
        
        const formData = new FormData();
        formData.append('csrf_token', '<?= Security::generateCsrfToken() ?>');
        
        fetch(`${BASE_URL}hargaKomoditas/delete/${id}`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadData();
                } else {
                    showToast(data.error, 'danger');
                }
            });
    }
    <?php endif; ?>
    
    // File input label update
    document.getElementById('excelFile')?.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'Pilih file...';
        this.nextElementSibling.innerText = fileName;
        // Reset preview and result when new file is selected
        document.getElementById('previewContainer').style.display = 'none';
        document.getElementById('importResult').style.display = 'none';
    });
    
    // Preview import data
    document.getElementById('btnPreviewImport')?.addEventListener('click', function() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files.length) {
            alert('Pilih file Excel terlebih dahulu');
            return;
        }
        
        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);
        formData.append('csrf_token', csrfToken);
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        
        fetch(`${BASE_URL}hargaKomoditas/previewImport`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('previewContainer');
                const thead = document.querySelector('#previewTable thead tr');
                const tbody = document.querySelector('#previewTable tbody');
                
                // Build headers
                thead.innerHTML = data.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
                
                // Build rows
                tbody.innerHTML = data.data.map(row => {
                    const cells = data.headers.map(h => `<td>${escapeHtml(row[h] || '')}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                }).join('');
                
                document.getElementById('previewInfo').textContent = 
                    `Menampilkan ${data.previewRows} dari ${data.totalRows} baris`;
                
                container.style.display = 'block';
            } else {
                alert('Error: ' + (data.error || 'Gagal membaca file'));
            }
            
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-eye"></i> Preview Data';
        })
        .catch(error => {
            alert('Error: ' + error.message);
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-eye"></i> Preview Data';
        });
    });
    
    // Excel import handler
    document.getElementById('btnImportExcel')?.addEventListener('click', function() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files.length) {
            alert('Pilih file Excel terlebih dahulu');
            return;
        }

        const formData = new FormData(document.getElementById('importExcelForm'));
        const progress = document.getElementById('importProgress');
        const result = document.getElementById('importResult');
        
        // Show progress
        progress.style.display = 'block';
        result.style.display = 'none';
        this.disabled = true;

        fetch(`${BASE_URL}hargaKomoditas/importExcel`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            progress.style.display = 'none';
            result.style.display = 'block';
            
            const alertDiv = result.querySelector('.alert');
            if (data.success) {
                alertDiv.className = 'alert alert-success mb-0';
                document.getElementById('resultTitle').innerText = 'Import Berhasil';
            } else {
                alertDiv.className = 'alert alert-danger mb-0';
                document.getElementById('resultTitle').innerText = 'Import Gagal';
            }
            
            document.getElementById('resultTotal').innerText = data.totalProcessed || 0;
            document.getElementById('resultSuccess').innerText = data.successCount || 0;
            document.getElementById('resultFailed').innerText = data.failedCount || 0;
            
            // Show errors if any
            const errorsDiv = document.getElementById('resultErrors');
            const errorList = document.getElementById('resultErrorList');
            if (data.errors && data.errors.length > 0) {
                errorsDiv.style.display = 'block';
                errorList.innerHTML = data.errors.slice(0, 5).map(e => `<li>${escapeHtml(e)}</li>`).join('');
                if (data.errors.length > 5) {
                    errorList.innerHTML += `<li>...dan ${data.errors.length - 5} error lainnya</li>`;
                }
            } else {
                errorsDiv.style.display = 'none';
            }
            
            this.disabled = false;
            
            // Reload data if successful
            if (data.success && data.successCount > 0) {
                setTimeout(() => {
                    loadData();
                    loadCharts();
                }, 1500);
            }
        })
        .catch(error => {
            progress.style.display = 'none';
            result.style.display = 'block';
            result.querySelector('.alert').className = 'alert alert-danger mb-0';
            document.getElementById('resultTitle').innerText = 'Error: ' + error.message;
            this.disabled = false;
        });
    });
    
    // Initialize
    loadData();
    loadCharts();
    loadAlerts();
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
