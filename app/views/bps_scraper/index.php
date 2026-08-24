<?php require_once ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
    .btn-primary:hover, .btn-secondary:hover, .btn-success:hover, .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.2s ease;
    }
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .info-banner { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .scraper-status { position: relative; }
    .scraper-status.running::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 60px;
        height: 60px;
        border: 3px solid transparent;
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 100% { transform: translate(-50%, -50%) rotate(360deg); } }
    .table-pagination { gap: .75rem; }
    .table-pagination .pagination { flex-wrap: wrap; }
    .table-pagination .page-link { min-width: 38px; text-align: center; }
    .table-pagination-info { white-space: nowrap; }
    .monthly-chart-container { height: 320px; position: relative; }
    @media (max-width: 767.98px) {
        .table-pagination { align-items: stretch !important; flex-direction: column; }
        .table-pagination nav { overflow-x: auto; }
        .table-pagination .pagination { flex-wrap: nowrap; }
        .table-pagination-info { white-space: normal; }
        .card-header.flex-responsive { align-items: flex-start !important; flex-direction: column; gap: .75rem; }
        .monthly-chart-container { height: 260px; }
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-database text-primary"></i> <?= $data['page_title'] ?>
            </h1>
            <small class="text-muted">Data luas panen dan produksi padi menurut kabupaten/kota</small>
        </div>
        <div class="btn-group flex-wrap">
            <a href="<?= BASE_URL ?>bpsScraper/export" class="btn btn-success" id="btnExport">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDataModal">
                <i class="fas fa-plus"></i> Tambah Data
            </button>
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#importExcelModal">
                <i class="fas fa-file-excel"></i> Import Excel
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="card info-banner text-white mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5><i class="fas fa-info-circle"></i> Tentang Data Pertanian BPS</h5>
                    <p class="mb-0 small">
                        Halaman ini menyediakan data statistik pertanian dari Badan Pusat Statistik (BPS) 
                        untuk seluruh kabupaten/kota di Provinsi Jawa Timur. Data mencakup luas panen, 
                        produksi gabah (GKG), produksi beras, dan produktivitas per wilayah.
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <img src="https://www.bps.go.id/images/logo-bps-header.png" alt="BPS Logo" 
                         style="height: 50px; filter: brightness(0) invert(1);" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </div>

    <!-- Scraping Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-cog"></i> Konfigurasi & Manajemen Data
            </h6>
            <button class="btn btn-sm btn-light text-primary" onclick="showAnomalies()">
                <i class="fas fa-exclamation-triangle"></i> Cek Anomali
            </button>
        </div>
        <div class="card-body">
            <form id="scraperForm" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                
                <div class="col-md-2">
                    <label class="form-label small" for="scraperYear"><strong>Tahun</strong></label>
                    <select class="form-control form-control-sm" id="scraperYear" name="tahun">
                        <?php
                        $availableYears = $data['availableYears'] ?? [];
                        $defaultYear = $data['defaultYear'] ?? (date('Y'));
                        // Data KSA angka tetap tersedia mulai 2018.
                        for ($y = date('Y'); $y >= 2018; $y--):
                            $hasData = in_array((string)$y, $availableYears) || in_array((int)$y, $availableYears);
                            $isDefault = (string)$y === (string)$defaultYear;
                        ?>
                            <option value="<?= $y ?>"
                                <?= $isDefault ? 'selected' : '' ?>>
                                <?= $y ?><?= !$hasData ? ' (belum ada data)' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small" for="scraperKabupaten"><strong>Kabupaten/Kota</strong></label>
                    <select class="form-control form-control-sm" id="scraperKabupaten" name="kabupaten">
                        <option value="">Semua Kabupaten/Kota (38)</option>
                        <?php foreach ($data['kabupatenList'] as $kab): ?>
                            <option value="<?= $kab ?>"><?= $kab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small" for="filterSource"><strong>Filter Sumber</strong></label>
                    <select class="form-control form-control-sm" id="filterSource">
                        <option value="">Otomatis (sumber terbaik)</option>
                        <option value="ksa">KSA BPS (Angka Tetap)</option>
                        <option value="resmi_webapi">BPS WebAPI</option>
                        <option value="manual">Input Manual</option>
                        <option value="simulasi">Simulasi</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small" for="scraperScenario"><strong>Skenario</strong></label>
                    <select class="form-control form-control-sm" id="scraperScenario" name="skenario">
                        <option value="baseline">Baseline (Normal)</option>
                        <option value="optimis">Optimis (+5-8%)</option>
                        <option value="pesimis">Pesimis (-5-8%)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small" for="scraperSumber"><strong>Dataset</strong></label>
                    <select class="form-control form-control-sm" id="scraperSumber" name="sumber">
                        <option value="">Semua Sumber</option>
                        <option value="KSA BPS">KSA BPS</option>
                        <option value="Input Manual">Input Manual</option>
                    </select>
                </div>

                <?php if($_SESSION['role'] === 'admin'): ?>
                <div class="col-md-2">
                    <label class="form-label small" for="runSource"><strong>Sumber Eksekusi</strong></label>
                    <select class="form-control form-control-sm" id="runSource" name="source">
                        <option value="">— Pilih saat scraping —</option>
                        <option value="resmi_webapi" <?= empty($data['bpsApiConfigured']) ? 'disabled' : '' ?>>
                            BPS WebAPI<?= empty($data['bpsApiConfigured']) ? ' (belum dikonfigurasi)' : '' ?>
                        </option>
                        <option value="simulasi">Simulasi (uji/skenario)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="forceRefresh" name="force_refresh" value="true">
                        <label class="form-check-label small" for="forceRefresh">Timpa data lama</label>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-12 mt-3 d-flex">
                    <?php if($_SESSION['role'] === 'admin'): ?>
                    <button type="submit" class="btn btn-primary btn-sm mr-2" id="btnScrape">
                        <i class="fas fa-play"></i> Jalankan Scraper
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-info btn-sm mr-2" id="btnLoadData">
                        <i class="fas fa-sync-alt"></i> Muat Data
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="showYearlySummary()">
                        <i class="fas fa-chart-pie"></i> Ringkasan Tahunan
                    </button>
                </div>
            </form>

            <div class="alert alert-light border mt-3 mb-0 py-2 small">
                <i class="fas fa-info-circle text-primary"></i>
                Tampilan otomatis memprioritaskan <strong>KSA BPS Angka Tetap</strong>, lalu WebAPI,
                input manual, dan terakhir simulasi. Data simulasi hanya digunakan jika dipilih secara eksplisit.
            </div>
            
            <!-- Scraping Progress -->
            <div id="scrapingProgress" class="mt-3" style="display: none;">
                <div class="alert alert-info">
                    <div class="d-flex align-items-center mb-2">
                        <div class="spinner-border spinner-border-sm mr-3"></div>
                        <span id="scrapingMessage">Menyiapakan proses scraping...</span>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div id="scrapeProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted" id="scrapingEta">Estimasi: 10-30 detik</small>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancelScrape">Batalkan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Luas Panen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statLuas">
                                <?= isset($data['statistics']['total_luas_panen']) ? 
                                    DataPertanianBps::formatNumber($data['statistics']['total_luas_panen']) : '0' ?> Ha
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Produksi Gabah</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statGabah">
                                <?= isset($data['statistics']['total_produksi_gabah']) ? 
                                    DataPertanianBps::formatNumber($data['statistics']['total_produksi_gabah']) : '0' ?> Ton
                            </div>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Produksi Beras</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statBeras">
                                <?= isset($data['statistics']['total_produksi_beras']) ? 
                                    DataPertanianBps::formatNumber($data['statistics']['total_produksi_beras']) : '0' ?> Ton
                            </div>
                            <small class="text-muted" id="statKonversi">Konversi: -</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bowl-rice fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Produktivitas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statProduktivitas">
                                <?= isset($data['statistics']['rata_produktivitas']) ? 
                                    $data['statistics']['rata_produktivitas'] : '0' ?> Ku/Ha
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sorotan Kabupaten Jember -->
    <div class="card shadow mb-4 border-left-primary" id="jemberHighlight">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-map-marker-alt"></i> Sorotan Kabupaten Jember
            </h6>
            <span class="badge badge-primary" id="jemberYearBadge">
                Tahun <?= (int)($data['defaultYear'] ?? date('Y')) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-lg-3 col-6 mb-3 mb-lg-0">
                    <div class="text-xs font-weight-bold text-uppercase text-success mb-1">Luas Panen</div>
                    <div class="h5 mb-0 font-weight-bold" id="jemberLuas">
                        <?= DataPertanianBps::formatNumber($data['jemberStatistics']['total_luas_panen'] ?? 0) ?> Ha
                    </div>
                </div>
                <div class="col-lg-3 col-6 mb-3 mb-lg-0">
                    <div class="text-xs font-weight-bold text-uppercase text-warning mb-1">Produksi Gabah</div>
                    <div class="h5 mb-0 font-weight-bold" id="jemberGabah">
                        <?= DataPertanianBps::formatNumber($data['jemberStatistics']['total_produksi_gabah'] ?? 0) ?> Ton
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="text-xs font-weight-bold text-uppercase text-info mb-1">Produksi Beras</div>
                    <div class="h5 mb-0 font-weight-bold" id="jemberBeras">
                        <?= DataPertanianBps::formatNumber($data['jemberStatistics']['total_produksi_beras'] ?? 0) ?> Ton
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="text-xs font-weight-bold text-uppercase text-primary mb-1">Produktivitas</div>
                    <div class="h5 mb-0 font-weight-bold" id="jemberProduktivitas">
                        <?= (float)($data['jemberStatistics']['rata_produktivitas'] ?? 0) ?> Ku/Ha
                    </div>
                </div>
            </div>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnShowJember">
                    <i class="fas fa-filter"></i> Tampilkan Jember di tabel
                </button>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Tren Produksi Tahunan
                    </h6>
                </div>
                <div class="card-body">
                    <div id="trendChartContainer" style="height: 300px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-trophy"></i> Top 10 Produsen
                    </h6>
                </div>
                <div class="card-body">
                    <div id="topChartContainer" style="height: 300px;">
                        <canvas id="topChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table"></i> Data Pertanian per Kabupaten/Kota
                <span class="badge badge-success ml-2" id="completenessBadge"
                      title="Jumlah kabupaten/kota yang terisi data pada tahun terpilih">
                    <?= (int)($data['kabupatenTerisi'] ?? 0) ?>/<?= (int)($data['totalKabupaten'] ?? 38) ?> kabupaten terisi
                </span>
            </h6>
            <div class="d-flex align-items-center">
                <label class="mb-0 mr-2 small">Tampilkan:</label>
                <select class="form-control form-control-sm" id="perPageSelect" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Kabupaten/Kota</th>
                            <th>Tahun</th>
                            <th>Luas Panen (Ha)</th>
                            <th>Produksi Gabah (Ton)</th>
                            <th>Produksi Beras (Ton)</th>
                            <th>Konversi (Beras/Gabah)</th>
                            <th>Produktivitas (Ku/Ha)</th>
                            <th>Sumber</th>
                            <th>Skenario</th>
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <th style="width: 80px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="<?= $_SESSION['role'] === 'admin' ? '11' : '10' ?>" class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Klik "Muat Data" atau "Jalankan Scraper" untuk menampilkan data
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="pagination" class="table-pagination d-flex justify-content-between align-items-center mt-3">
                <div id="paginationInfo"></div>
                <nav aria-label="Navigasi halaman data pertanian"><ul class="pagination mb-0" id="paginationButtons"></ul></nav>
            </div>
        </div>
    </div>
    
    <!-- Anomaly Modal -->
    <div class="modal fade" id="anomalyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Data Anomali</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="anomalyTable">
                            <thead>
                                <tr>
                                    <th>Kabupaten</th>
                                    <th>Field</th>
                                    <th>Nilai</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody id="anomalyBody">
                                <tr><td colspan="4" class="text-center">Memuat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="anomalyPagination" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Instructions -->
    <div class="card shadow mb-4">
        <div class="card-header py-3" data-toggle="collapse" data-target="#usageCollapse" style="cursor: pointer;">
            <h6 class="m-0 font-weight-bold text-secondary">
                <i class="fas fa-question-circle"></i> Petunjuk Penggunaan
                <i class="fas fa-chevron-down float-right"></i>
            </h6>
        </div>
        <div id="usageCollapse" class="collapse">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold"><i class="fas fa-cog text-primary"></i> Konfigurasi</h6>
                        <ol class="pl-3">
                            <li>Pilih <strong>Tahun</strong> data yang ingin diambil</li>
                            <li>Pilih <strong>Kabupaten/Kota</strong> atau pilih "Semua" untuk seluruh wilayah</li>
                            <li>Centang <strong>Timpa data lama</strong> jika ingin memperbarui data yang sudah ada</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-weight-bold"><i class="fas fa-play text-success"></i> Menjalankan</h6>
                        <ol class="pl-3">
                            <li>Klik <strong>Jalankan Scraper</strong> untuk mengambil data baru</li>
                            <li>Klik <strong>Muat Data</strong> untuk menampilkan data yang sudah tersimpan</li>
                            <li>Gunakan <strong>Export CSV</strong> untuk mengunduh data</li>
                        </ol>
                    </div>
                </div>
                <hr>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-database"></i> <strong>Sumber Data:</strong> 
                    Data diambil berdasarkan publikasi resmi BPS Provinsi Jawa Timur. 
                    Konversi gabah ke beras menggunakan rasio 57,7% sesuai standar BPS.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Harvest Area Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-responsive">
        <div>
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-calendar-alt"></i> Luas Panen Bulanan
            </h6>
            <small class="text-muted">Data KSA per bulan untuk kabupaten/kota di Jawa Timur</small>
        </div>
        <div class="d-flex flex-wrap align-items-end" style="gap: .5rem;">
            <div>
                <label class="small mb-1" for="monthlyMonth">Bulan</label>
                <select class="form-control form-control-sm" id="monthlyMonth">
                    <option value="">Semua bulan</option>
                    <?php foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $monthNumber => $monthName): ?>
                        <option value="<?= $monthNumber + 1 ?>"><?= $monthName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="small mb-1" for="monthlyPerPage">Baris</label>
                <select class="form-control form-control-sm" id="monthlyPerPage">
                    <option value="10">10</option><option value="25">25</option><option value="38" selected>38 (Lengkap)</option><option value="50">50</option><option value="100">100</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="btnLoadMonthly">
                <i class="fas fa-sync-alt"></i> Muat
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                <div>
                    <h6 class="font-weight-bold mb-0">Tren Luas Panen per Bulan</h6>
                    <small class="text-muted" id="monthlyChartSubtitle">Memuat cakupan grafik...</small>
                </div>
                <span class="badge badge-primary">Satuan: Ha</span>
            </div>
            <div class="monthly-chart-container" id="monthlyHarvestChartContainer">
                <canvas id="monthlyHarvestChart" role="img" aria-label="Grafik garis luas panen bulanan"></canvas>
            </div>
            <p class="small text-muted mt-2 mb-0" id="monthlyChartSummary" aria-live="polite"></p>
        </div>
        <hr>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm" id="monthlyHarvestTable">
                <caption class="sr-only">Data luas panen bulanan kabupaten dan kota di Jawa Timur</caption>
                <thead class="thead-light"><tr>
                    <th>No</th><th>Kabupaten/Kota</th><th>Bulan</th><th>Tahun</th>
                    <th class="text-right">Luas Panen</th><th>Satuan</th><th>Status</th><th>Sumber Data</th>
                </tr></thead>
                <tbody id="monthlyHarvestBody"><tr><td colspan="8" class="text-center text-muted">Memuat data...</td></tr></tbody>
            </table>
        </div>
        <div id="monthlyPagination" class="mt-3"></div>
    </div>
</div>

<!-- KSA Data Panel -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-dark text-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-chart-bar"></i> Data KSA Bulanan (Survei Kerangka Sampel Area)
        </h6>
        <button type="button" class="btn btn-sm btn-light text-dark" onclick="loadKsaStatus()">
            <i class="fas fa-sync-alt"></i> Refresh Status
        </button>
    </div>
    <div class="card-body">
        <!-- Status Ringkasan -->
        <div class="row mb-3">
            <div class="col-xl-3 col-md-6 mb-2">
                <div class="border-left-primary rounded p-2 bg-light">
                    <small class="text-muted text-uppercase font-weight-bold">Total Records</small>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="ksaTotalRecords">-</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-2">
                <div class="border-left-success rounded p-2 bg-light">
                    <small class="text-muted text-uppercase font-weight-bold">Kabupaten Tercakup</small>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="ksaJumlahKabupaten">-</div>
                </div>
            </div>
            <div class="col-xl-6 col-md-12 mb-2">
                <div class="rounded p-2 bg-light">
                    <small class="text-muted text-uppercase font-weight-bold">Records per Tahun</small>
                    <div class="h6 mb-0" id="ksaPerTahun">
                        <span class="text-muted">Memuat...</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if($_SESSION['role'] === 'admin'): ?>
        <!-- Aksi Admin -->
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small" for="ksaServerFile"><strong>Import dari File Server</strong></label>
                <div class="input-group input-group-sm">
                    <select class="form-control" id="ksaServerFile">
                        <option value="">— Pilih file XLSX di data/ksa —</option>
                    </select>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-primary" onclick="importKsaPath()">
                            <i class="fas fa-file-import"></i> Import
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label small" for="ksaUploadFile"><strong>Upload File XLSX</strong></label>
                <div class="input-group input-group-sm">
                    <input type="file" class="form-control" id="ksaUploadFile" accept=".xlsx">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info" onclick="importKsaUpload()">
                            <i class="fas fa-upload"></i> Upload & Import
                        </button>
                    </div>
                </div>
                <small class="text-muted">Nama file harus mengandung "Angka Tetap" atau pola "2026.XX KSA Jatim"</small>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="ksaSyncTahun"><strong>Sync ke Tahunan</strong></label>
                <div class="input-group input-group-sm">
                    <select class="form-control" id="ksaSyncTahun">
                        <?php for ($y = 2026; $y >= 2018; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === 2025 ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-success" onclick="syncKsaAnnual()">
                            <i class="fas fa-arrow-right"></i> Sync
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <?php endif; ?>

        <!-- Riwayat Import -->
        <h6 class="font-weight-bold text-muted"><i class="fas fa-history"></i> Riwayat Import Terakhir</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0" id="ksaHistoryTable">
                <thead>
                    <tr>
                        <th>Aksi</th>
                        <th>Status</th>
                        <th>Pesan</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody id="ksaRecentImports">
                    <tr><td colspan="4" class="text-center text-muted">Memuat...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="ksaHistoryPagination" class="mt-3"></div>
    </div>
</div>

<!-- Add Data Modal -->
<?php if($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="addDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Data Pertanian</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="addDataForm">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun <span class="text-danger">*</span></label>
                                <select class="form-control" name="tahun" required>
                                    <?php for ($y = date('Y'); $y >= 2019; $y--): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kabupaten/Kota <span class="text-danger">*</span></label>
                                <select class="form-control" name="kabupaten_kota" required>
                                    <?php foreach ($data['kabupatenList'] as $kab): ?>
                                        <option value="<?= $kab ?>"><?= $kab ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Luas Panen (Ha) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="luas_panen" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produksi Gabah (Ton) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="produksi_gabah" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produksi Beras (Ton)</label>
                                <input type="number" class="form-control" name="produksi_beras" step="0.01" min="0">
                                <small class="text-muted">Kosongkan untuk auto-calculate (57.7%)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produktivitas (Ku/Ha)</label>
                                <input type="number" class="form-control" name="produktivitas" step="0.01" min="0">
                                <small class="text-muted">Kosongkan untuk auto-calculate</small>
                            </div>
                        </div>
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

<!-- Edit Data Modal -->
<div class="modal fade" id="editDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Pertanian</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editDataForm">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="tahun" id="editTahun" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kabupaten/Kota <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kabupaten_kota" id="editKabupaten" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Luas Panen (Ha) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="luas_panen" id="editLuas" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produksi Gabah (Ton) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="produksi_gabah" id="editGabah" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produksi Beras (Ton)</label>
                                <input type="number" class="form-control" name="produksi_beras" id="editBeras" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Produktivitas (Ku/Ha)</label>
                                <input type="number" class="form-control" name="produktivitas" id="editProduktivitas" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="editKeterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
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
                    <strong>Format file:</strong> xlsx, xls, csv<br>
                    <strong>Kolom wajib:</strong> tahun, kabupaten_kota, luas_panen, produksi_gabah<br>
                    <strong>Kolom opsional:</strong> produksi_beras, produktivitas, keterangan
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
                        <a href="<?= BASE_URL ?>bpsScraper/downloadTemplate" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-download"></i> Download Template CSV
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary ml-2" id="btnPreviewImport">
                            <i class="fas fa-eye"></i> Preview Data
                        </button>
                    </div>
                    
                    <div id="previewContainer" style="display: none;">
                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-table"></i> Preview Data</h6>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="previewTable">
                                <thead class="thead-light"><tr></tr></thead>
                                <tbody id="previewTableBody"></tbody>
                            </table>
                            <div id="previewPagination" class="mt-3"></div>
                        </div>
                        <small class="text-muted" id="previewInfo"></small>
                    </div>
                    
                    <div id="importProgress" style="display: none;">
                        <hr>
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted small">Mengimpor data...</p>
                    </div>
                    
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
                                <ul id="resultErrorList" class="small mb-0 text-danger"></ul>
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

<script>
    // Global variables
    let currentPage = 1;
    let perPage = 10;
    let monthlyPage = 1;
    let monthlyPerPage = 38;
    let monthlyTotalPages = 1;
    let monthlyHarvestChart = null;
    let trendChart = null;
    let topChart = null;
    
    const BASE_URL = '<?= BASE_URL ?>';
    const isAdmin = <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?>;
    const csrfToken = '<?= Security::generateCsrfToken() ?>';

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Format number
    function formatNumber(num) {
        const value = Number(num);
        if (!Number.isFinite(value)) return '-';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
    }

    function pageNumbers(current, total) {
        const pages = [];
        const start = Math.max(1, current - 2);
        const end = Math.min(total, current + 2);
        if (start > 1) pages.push(1);
        if (start > 2) pages.push(null);
        for (let page = start; page <= end; page++) pages.push(page);
        if (end < total - 1) pages.push(null);
        if (end < total) pages.push(total);
        return pages;
    }

    function renderPager(containerId, options) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const total = Math.max(0, Number(options.total) || 0);
        const perPageValue = Number(options.perPage) || 10;
        const totalPages = Math.max(1, Math.ceil(total / perPageValue));
        const page = Math.min(Math.max(1, Number(options.page) || 1), totalPages);
        const start = total === 0 ? 0 : ((page - 1) * perPageValue) + 1;
        const end = Math.min(page * perPageValue, total);
        const pageButtons = pageNumbers(page, totalPages).map(item => item === null
            ? '<li class="page-item disabled"><span class="page-link">…</span></li>'
            : `<li class="page-item ${item === page ? 'active' : ''}"><button type="button" class="page-link" data-page="${item}" ${item === page ? 'aria-current="page"' : ''}>${item}</button></li>`
        ).join('');

        container.innerHTML = `
            <div class="table-pagination d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
                    <span class="table-pagination-info">Menampilkan ${start}-${end} dari ${total} data</span>
                    <label class="mb-0 small">Baris
                        <select class="form-control form-control-sm d-inline-block ml-1 pager-size" style="width:70px">
                            ${[10, 25, 50].map(size => `<option value="${size}" ${size === perPageValue ? 'selected' : ''}>${size}</option>`).join('')}
                        </select>
                    </label>
                </div>
                <nav aria-label="Navigasi halaman ${escapeHtml(options.label || 'tabel')}">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item ${page <= 1 ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Sebelumnya</button></li>
                        ${pageButtons}
                        <li class="page-item ${page >= totalPages ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>Selanjutnya</button></li>
                    </ul>
                </nav>
            </div>`;

        container.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => {
            const target = Number(button.dataset.page);
            if (target >= 1 && target <= totalPages && target !== page) options.onPage(target);
        }));
        container.querySelector('.pager-size')?.addEventListener('change', event => options.onSize(Number(event.target.value)));
    }

    function createClientTablePager(containerId, tbodyId, columns, label) {
        const state = { rows: [], page: 1, perPage: 10 };
        const draw = () => {
            const totalPages = Math.max(1, Math.ceil(state.rows.length / state.perPage));
            state.page = Math.min(state.page, totalPages);
            const start = (state.page - 1) * state.perPage;
            document.getElementById(tbodyId).innerHTML = state.rows.length
                ? state.rows.slice(start, start + state.perPage).join('')
                : `<tr><td colspan="${columns}" class="text-center text-muted">Tidak ada data</td></tr>`;
            renderPager(containerId, {
                total: state.rows.length, page: state.page, perPage: state.perPage, label,
                onPage: page => { state.page = page; draw(); },
                onSize: size => { state.perPage = size; state.page = 1; draw(); },
            });
        };
        return {
            setRows(rows) { state.rows = Array.isArray(rows) ? rows : []; state.page = 1; draw(); },
            clear() { state.rows = []; state.page = 1; draw(); },
        };
    }

    const anomalyPager = createClientTablePager('anomalyPagination', 'anomalyBody', 4, 'data anomali');
    const ksaHistoryPager = createClientTablePager('ksaHistoryPagination', 'ksaRecentImports', 4, 'riwayat import KSA');
    const previewPager = createClientTablePager('previewPagination', 'previewTableBody', 1, 'preview import');
    
    // Toast notification
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.custom-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `custom-toast alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px;';
        const iconClass = type === 'success' ? 'check-circle' :
                         type === 'danger' ? 'times-circle' :
                         type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        toast.innerHTML = `<i class="fas fa-${iconClass} mr-2"></i>${escapeHtml(message)}`;
        document.body.appendChild(toast);
        
        const duration = type === 'warning' ? 6000 : 4000;
        setTimeout(() => toast.remove(), duration);
    }
    
    // Load data
    function loadData() {
        const tahun = document.getElementById('scraperYear').value;
        const kabupaten = document.getElementById('scraperKabupaten').value;
        const source = document.getElementById('filterSource').value;
        const skenario = document.getElementById('scraperScenario').value;
        const sumber = document.getElementById('scraperSumber').value;
        
        const params = new URLSearchParams({
            tahun: tahun,
            kabupaten: kabupaten,
            source: source,
            skenario: skenario,
            sumber: sumber,
            limit: perPage,
            offset: (currentPage - 1) * perPage
        });
        
        // Show loading state for table
        const colSpan = isAdmin ? 11 : 10;
        document.getElementById('tableBody').innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary"></div><br>Memuat data...</td></tr>`;
        
        fetch(`${BASE_URL}bpsScraper/getData?${params}`)
            .then(r => r.json())
            .then(response => {
                if (!response.success) throw new Error(response.error);
                
                renderTable(response.data);
                renderPagination(response.total);
                updateStatistics(response.statistics);
                updateJemberStatistics(response.jember_statistics, response.current_year);
                updateCompleteness(response.kabupatenTerisi);
            })
            .catch(err => {
                console.error('Load error:', err);
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${escapeHtml(err.message)}</td></tr>`;
                showToast('Gagal memuat data', 'danger');
            });
    }
    
    // Update completeness badge (X/38 kabupaten terisi)
    function updateCompleteness(terisi) {
        const badge = document.getElementById('completenessBadge');
        if (!badge) return;
        const totalKab = <?= (int)($data['totalKabupaten'] ?? 38) ?>;
        const n = parseInt(terisi || 0);
        badge.textContent = `${n}/${totalKab} kabupaten terisi`;
        badge.className = 'badge ' + (n >= totalKab ? 'badge-success' : n > 0 ? 'badge-warning' : 'badge-secondary') + ' ml-2';
    }
    
    // Render table
    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        const colSpan = isAdmin ? 11 : 10;
        
        if (!data || data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colSpan}" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i><br>
                        Tidak ada data yang ditemukan.
                    </td>
                </tr>`;
            return;
        }
        
        let no = ((currentPage - 1) * perPage) + 1;
        tbody.innerHTML = data.map(row => {
            const isValid = row.is_validated;
            const validationIcon = isValid ? '<i class="fas fa-check-circle text-success" title="Valid"></i>' : '<i class="fas fa-exclamation-circle text-warning" title="Perlu Review"></i>';
            
            // Konversi rasio beras/gabah per baris
            let konversi = '-';
            if (parseFloat(row.produksi_gabah) > 0 && row.produksi_beras !== null) {
                konversi = ((parseFloat(row.produksi_beras) / parseFloat(row.produksi_gabah)) * 100).toFixed(1) + '%';
            }
            
            // Badge sumber data + tooltip detail
            let sumberBadge = 'Simulasi';
            let sumberClass = 'info';
            if (row.sumber_data_type === 'ksa') { sumberBadge = 'KSA BPS'; sumberClass = 'primary'; }
            else if (row.sumber_data_type === 'resmi_webapi') { sumberBadge = 'WebAPI'; sumberClass = 'success'; }
            else if (row.sumber_data_type === 'manual') { sumberBadge = 'Manual'; sumberClass = 'secondary'; }
            const tooltipText = (row.sumber_data || '') + (row.keterangan ? ' - ' + row.keterangan : '');
            
            let actionColumn = '';
            if (isAdmin) {
                actionColumn = `
                <td class="text-center">
                    <button class="btn btn-sm btn-warning btn-edit" data-id="${escapeHtml(row.id)}" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-delete" data-id="${escapeHtml(row.id)}" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>`;
            }
            
            return `
            <tr class="${!isValid ? 'table-warning' : ''}" data-id="${escapeHtml(row.id)}">
                <td class="text-center">${no++}</td>
                <td>
                    <strong>${escapeHtml(row.kabupaten_kota)}</strong>
                    ${!isValid ? '<span class="badge badge-warning ml-1">Anomali</span>' : ''}
                </td>
                <td class="text-center">${escapeHtml(row.tahun)}</td>
                <td class="text-right">${escapeHtml(row.luas_panen_formatted)}</td>
                <td class="text-right">${escapeHtml(row.produksi_gabah_formatted)}</td>
                <td class="text-right">${escapeHtml(row.produksi_beras_formatted)}</td>
                <td class="text-center">${konversi}</td>
                <td class="text-center">
                    ${escapeHtml(row.produktivitas)}
                    <small class="d-block text-muted text-xs">${validationIcon}</small>
                </td>
                <td>
                    <span class="badge badge-${sumberClass}" data-toggle="tooltip" title="${escapeHtml(tooltipText)}">
                        ${escapeHtml(sumberBadge)}
                    </span>
                </td>
                 <td>
                    <span class="badge badge-light border">
                        ${escapeHtml(row.tipe_skenario || '-')}
                    </span>
                </td>
                ${actionColumn}
            </tr>
        `}).join('');
        
        // Attach event listeners for admin actions
        if (isAdmin) {
            tbody.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', () => openEditModal(btn.dataset.id));
            });
            tbody.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', () => deleteRecord(btn.dataset.id));
            });
        }
        
        // Re-enable tooltips (Bootstrap)
        if (window.$ && $.fn.tooltip) {
            $(tbody).find('[data-toggle="tooltip"]').tooltip();
        }
    }
    
    // Render pagination
    function renderPagination(total) {
        const totalPages = Math.ceil(total / perPage);
        const info = document.getElementById('paginationInfo');
        const buttons = document.getElementById('paginationButtons');
        
        const start = ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, total);
        info.textContent = total > 0 ? `Menampilkan ${start}-${end} dari ${total} data` : 'Tidak ada data';
        
        if (totalPages <= 1) {
            buttons.innerHTML = '';
            return;
        }
        
        let html = '';
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}">Sebelumnya</a>
                 </li>`;
        
        // Show limited page numbers
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
             html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
             if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                     </li>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }
        
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}">Selanjutnya</a>
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
        if (!stats) {
            document.getElementById('statLuas').textContent = '- Ha';
            document.getElementById('statGabah').textContent = '- Ton';
            document.getElementById('statBeras').textContent = '- Ton';
            document.getElementById('statProduktivitas').textContent = '- Ku/Ha';
            return;
        }
        document.getElementById('statLuas').textContent = formatNumber(stats.total_luas_panen || 0) + ' Ha';
        document.getElementById('statGabah').textContent = formatNumber(stats.total_produksi_gabah || 0) + ' Ton';
        document.getElementById('statBeras').textContent = formatNumber(stats.total_produksi_beras || 0) + ' Ton';
        document.getElementById('statProduktivitas').textContent = (stats.rata_produktivitas || 0) + ' Ku/Ha';
        
        // Konversi rasio beras/gabah agregat
        const gabah = parseFloat(stats.total_produksi_gabah || 0);
        const beras = parseFloat(stats.total_produksi_beras || 0);
        const ratioEl = document.getElementById('statKonversi');
        if (ratioEl) {
            ratioEl.textContent = gabah > 0 ? `Konversi: ${((beras / gabah) * 100).toFixed(1)}% dari gabah` : 'Konversi: -';
        }
    }

    function updateJemberStatistics(stats, tahun) {
        document.getElementById('jemberYearBadge').textContent = `Tahun ${tahun}`;
        document.getElementById('jemberLuas').textContent = formatNumber(stats?.total_luas_panen || 0) + ' Ha';
        document.getElementById('jemberGabah').textContent = formatNumber(stats?.total_produksi_gabah || 0) + ' Ton';
        document.getElementById('jemberBeras').textContent = formatNumber(stats?.total_produksi_beras || 0) + ' Ton';
        document.getElementById('jemberProduktivitas').textContent = (stats?.rata_produktivitas || 0) + ' Ku/Ha';
    }

    document.getElementById('btnShowJember').addEventListener('click', function() {
        document.getElementById('scraperKabupaten').value = 'Jember';
        currentPage = 1;
        loadData();
        document.getElementById('dataTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    
    // Show Anomalies Modal
    window.showAnomalies = function() {
        $('#anomalyModal').modal('show');
        const tbody = document.getElementById('anomalyBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Memuat data anomali...</td></tr>';
        document.getElementById('anomalyPagination').innerHTML = '';
        
        const tahun = document.getElementById('scraperYear').value;
        
        fetch(`${BASE_URL}bpsScraper/getAnomalies?limit=50&tahun=${tahun}`)
            .then(r => r.json())
            .then(response => {
                if (!response.success || response.data.length === 0) {
                    anomalyPager.clear();
                    return;
                }

                anomalyPager.setRows(response.data.map(item => `
                    <tr>
                        <td>${escapeHtml(item.kabupaten_kota)}<br><small class="text-muted">Tahun ${escapeHtml(item.tahun)}</small></td>
                        <td><span class="badge badge-secondary">${escapeHtml(item.field_name)}</span></td>
                        <td>${escapeHtml(item.value_actual)}</td>
                        <td class="text-danger small">${escapeHtml(item.notes)}</td>
                    </tr>
                `));
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error: ${escapeHtml(err.message)}</td></tr>`;
            });
    };
    
    // Show Yearly Summary
    window.showYearlySummary = function() {
        const tahun = document.getElementById('scraperYear').value;
        fetch(`${BASE_URL}bpsScraper/getYearlySummary?tahun=${tahun}`)
            .then(r => r.json())
            .then(response => {
                if (response.success && response.data) {
                    const data = response.data;
                    alert(`Ringkasan Tahun ${data.tahun}:\n\n` +
                          `Total Kabupaten: ${data.total_kabupaten}\n` +
                          `Total Luas Panen: ${formatNumber(data.total_luas_panen)} Ha\n` +
                          `Total Produksi Gabah: ${formatNumber(data.total_produksi_gabah)} Ton\n` +
                          `Rata-rata Produktivitas: ${data.rata_produktivitas} Ku/Ha`);
                } else {
                    alert('Data ringkasan belum tersedia. Jalankan scraper terlebih dahulu.');
                }
            });
    };
    
    // Load charts
    function loadCharts() {
        const tahun = document.getElementById('scraperYear').value;
        const source = document.getElementById('filterSource').value;
        const skenario = document.getElementById('scraperScenario').value;
        const chartParams = new URLSearchParams({ source, skenario });

        function ensureCanvas(containerId, canvasId) {
            const container = document.getElementById(containerId);
            if (!document.getElementById(canvasId)) {
                container.innerHTML = `<canvas id="${canvasId}"></canvas>`;
            }
            return container;
        }
        
        // Trend chart
        fetch(`${BASE_URL}bpsScraper/getChartData?type=yearly&${chartParams}`)
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('trendChartContainer');
                if (!data.success || !data.labels.length) {
                    if (trendChart) { trendChart.destroy(); trendChart = null; }
                    container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-line fa-2x mb-2"></i><br>Data tren tidak tersedia</div>';
                    return;
                }

                ensureCanvas('trendChartContainer', 'trendChart');
                
                if (trendChart) trendChart.destroy();
                
                trendChart = new Chart(document.getElementById('trendChart'), {
                    type: 'line',
                    data: { labels: data.labels, datasets: data.datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Luas (ribu Ha)' } },
                            y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Produksi (juta Ton)' } }
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                    }
                });
            })
            .catch(err => {
                console.error('Trend chart error:', err);
                document.getElementById('trendChartContainer').innerHTML = '<div class="text-center text-danger py-4"><i class="fas fa-exclamation-circle"></i><br>Gagal memuat grafik tren</div>';
            });
        
        // Top producers chart
        fetch(`${BASE_URL}bpsScraper/getChartData?type=top&tahun=${tahun}&${chartParams}`)
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('topChartContainer');
                if (!data.success || !data.labels.length) {
                    if (topChart) { topChart.destroy(); topChart = null; }
                    container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-trophy fa-2x mb-2"></i><br>Data Top 10 belum tersedia untuk tahun ini</div>';
                    return;
                }

                ensureCanvas('topChartContainer', 'topChart');
                
                if (topChart) topChart.destroy();
                
                topChart = new Chart(document.getElementById('topChart'), {
                    type: 'bar',
                    data: { labels: data.labels, datasets: data.datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } }
                    }
                });
            })
            .catch(err => {
                console.error('Top producers chart error:', err);
                document.getElementById('topChartContainer').innerHTML = '<div class="text-center text-danger py-4"><i class="fas fa-exclamation-circle"></i><br>Gagal memuat grafik Top 10</div>';
            });
    }
    
    // Run scraper
    <?php if($_SESSION['role'] === 'admin'): ?>
     document.getElementById('scraperForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnScrape');
        const progress = document.getElementById('scrapingProgress');
        const progressBar = document.getElementById('scrapeProgressBar');
        const messageEl = document.getElementById('scrapingMessage');
        const etaEl = document.getElementById('scrapingEta');
        const cancelBtn = document.getElementById('btnCancelScrape');
        
        const source = document.getElementById('runSource').value;
        const kabupaten = document.getElementById('scraperKabupaten').value;

        if (!source) {
            showToast('Pilih sumber eksekusi WebAPI atau simulasi terlebih dahulu', 'warning');
            return;
        }
        
        // Use background mode for WebAPI sources (to avoid HTTP timeout)
        const useBackground = source === 'resmi_webapi';
        
        // Set appropriate message based on source and mode
        const sourceLabel = source === 'resmi_webapi' ? 'BPS WebAPI' : 'Simulasi';
        const scopeLabel = kabupaten ? `kabupaten ${kabupaten}` : 'seluruh Jawa Timur (38 kabupaten)';
        const etaText = useBackground 
            ? 'Estimasi: 30-120 detik (akan diproses di background)' 
            : 'Estimasi: < 10 detik';
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        progress.style.display = 'block';
        progressBar.style.width = '10%';
        progressBar.textContent = '10%';
        messageEl.textContent = useBackground 
            ? `Mengantregrasikan scraping ke ${sourceLabel} (${scopeLabel})...` 
            : `Mengambil data dari ${sourceLabel} (${scopeLabel})...`;
        etaEl.textContent = etaText;
        
        const formData = new FormData(this);
        if (useBackground) formData.append('background', 'true');
        const startTime = Date.now();
        
        const controller = new AbortController();
        const signal = controller.signal;
        
        cancelBtn.onclick = function() {
            controller.abort();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
            progress.style.display = 'none';
            showToast('Scraping dibatalkan oleh pengguna', 'warning');
        };
        
        fetch(`${BASE_URL}bpsScraper/runScraper`, {
            method: 'POST',
            body: formData,
            signal: signal
        })
        .then(r => r.json())
        .then(data => {
            if (data.background && data.job_id) {
                // Background mode: poll for status
                const jobId = data.job_id;
                messageEl.textContent = `Job #${jobId} sedang diproses...`;
                progressBar.style.width = '30%';
                progressBar.textContent = '30%';
                
                pollJobStatus(jobId, progressBar, messageEl, btn, progress);
            } else {
                // Synchronous mode: handle response directly
                const elapsed = Math.round((Date.now() - startTime) / 1000);
                
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
                progress.style.display = 'none';
                
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                
                if (data.success) {
                    showToast(data.message, 'success');
                    loadData(); // Reload table
                    loadCharts(); // Reload charts
                    
                    // Show summary if returned
                    if (data.records_success > 0) {
                        setTimeout(() => {
                            let msg = `Hasil Eksekusi:\nSukses: ${data.records_success}\nGagal: ${data.records_failed}\nDilewati: ${data.records_skipped}`;
                            if(data.execution_time) msg += `\nWaktu: ${data.execution_time}s`;
                            alert(msg);
                        }, 500);
                    }
                    
                    // Show any errors that occurred during processing
                    if (data.errors && data.errors.length > 0) {
                        console.error('Scraping errors:', data.errors);
                        setTimeout(() => {
                            showToast(` ${data.errors.length} error${data.errors.length > 1 ? 's' : ''} - lihat console untuk detail`, 'warning');
                        }, 1000);
                    }
                } else {
                    showToast(data.error || data.message, 'danger');
                }
            }
        })
        .catch(err => {
            if (err.name === 'AbortError') {
                return; // Cancelled by user
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
            progress.style.display = 'none';
            showToast('Gagal menjalankan scraper: ' + err.message, 'danger');
            console.error(err);
        });
    });
    
    // Polling background job status
    function pollJobStatus(jobId, progressBar, messageEl, btn, progress) {
        const poll = setInterval(() => {
            fetch(`${BASE_URL}bpsScraper/getScraperStatus/${jobId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    clearInterval(poll);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
                    progress.style.display = 'none';
                    showToast('Gagal memantau job: ' + resp.error, 'danger');
                    return;
                }
                
                const job = resp.job;
                progressBar.style.width = `${job.progress}%`;
                progressBar.textContent = `${job.progress}%`;
                messageEl.textContent = `Job #${jobId}: ${job.status}`;
                
                if (job.status === 'completed' || job.status === 'failed') {
                    clearInterval(poll);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
                    progress.style.display = 'none';
                    
                    if (job.status === 'completed' && job.result) {
                        showToast('Scraping selesai!', 'success');
                        loadData();
                        loadCharts();
                        
                        if (job.result.records_success > 0) {
                            setTimeout(() => {
                                let msg = `Hasil Eksekusi:\nSukses: ${job.result.records_success}\nGagal: ${job.result.records_failed}\nDilewati: ${job.result.records_skipped}`;
                                if(job.result.execution_time) msg += `\nWaktu: ${job.result.execution_time}s`;
                                alert(msg);
                            }, 500);
                        }
                        
                        if (job.result.errors && job.result.errors.length > 0) {
                            console.error('Scraping errors:', job.result.errors);
                            setTimeout(() => {
                                showToast(` ${job.result.errors.length} error - lihat console`, 'warning');
                            }, 1000);
                        }
                    } else {
                        const errMsg = job.error_message || 'Scraping gagal';
                        showToast('Scraping gagal: ' + errMsg, 'danger');
                        console.error('Job failed:', job);
                    }
                }
            })
            .catch(err => {
                clearInterval(poll);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
                progress.style.display = 'none';
                showToast('Gagal memantau job', 'danger');
                console.error(err);
            });
        }, 5000); // Poll every 5 seconds
    }
    <?php endif; ?>
    
    // Load data button
    document.getElementById('btnLoadData').addEventListener('click', function() {
        currentPage = 1;
        loadData();
        loadCharts();
        showToast('Data dimuat', 'info');
    });
    
    // Per page change
    document.getElementById('perPageSelect').addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        loadData();
    });
    
    // Year change -> reload data & charts
    document.getElementById('scraperYear').addEventListener('change', function() {
        currentPage = 1;
        monthlyPage = 1;
        loadData();
        loadCharts();
        loadMonthlyHarvestArea();
        loadMonthlyHarvestChart();
    });

    // Kabupaten/Source/Scenario/Dataset change -> reload data
    ['scraperKabupaten', 'filterSource', 'scraperScenario', 'scraperSumber'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            currentPage = 1;
            loadData();
            if (id === 'scraperKabupaten') {
                monthlyPage = 1;
                loadMonthlyHarvestArea();
                loadMonthlyHarvestChart();
            }
            loadCharts();
        });
    });
    
    // Update export link
    document.getElementById('btnExport').addEventListener('click', function(e) {
        e.preventDefault();
        const tahun = document.getElementById('scraperYear').value;
        const kabupaten = document.getElementById('scraperKabupaten').value;
        const source = document.getElementById('filterSource').value;
        const skenario = document.getElementById('scraperScenario').value;
        const sumber = document.getElementById('scraperSumber').value;
        const params = new URLSearchParams({ tahun, kabupaten, source, skenario, sumber });
        window.location.href = `${BASE_URL}bpsScraper/export?${params}`;
    });
    
    // ==================== CRUD & Import Functions ====================
    <?php if($_SESSION['role'] === 'admin'): ?>
    
    // Add Data Form Submit
    document.getElementById('addDataForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(`${BASE_URL}bpsScraper/store`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                $('#addDataModal').modal('hide');
                this.reset();
                loadData();
            } else {
                showToast(data.error, 'danger');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
    });
    
    // Open Edit Modal
    function openEditModal(id) {
        fetch(`${BASE_URL}bpsScraper/getRecord/${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const record = data.data;
                    document.getElementById('editId').value = record.id;
                    document.getElementById('editTahun').value = record.tahun;
                    document.getElementById('editKabupaten').value = record.kabupaten_kota;
                    document.getElementById('editLuas').value = record.luas_panen;
                    document.getElementById('editGabah').value = record.produksi_gabah;
                    document.getElementById('editBeras').value = record.produksi_beras;
                    document.getElementById('editProduktivitas').value = record.produktivitas;
                    document.getElementById('editKeterangan').value = record.keterangan || '';
                    $('#editDataModal').modal('show');
                } else {
                    showToast(data.error, 'danger');
                }
            });
    }
    
    // Edit Data Form Submit
    document.getElementById('editDataForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('editId').value;
        const formData = new FormData(this);
        
        fetch(`${BASE_URL}bpsScraper/update/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                $('#editDataModal').modal('hide');
                loadData();
            } else {
                showToast(data.error, 'danger');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
    });
    
    // Delete Record
    function deleteRecord(id) {
        if (!confirm('Hapus data ini? Tindakan ini tidak dapat dibatalkan.')) return;
        
        const formData = new FormData();
        formData.append('csrf_token', '<?= Security::generateCsrfToken() ?>');
        
        fetch(`${BASE_URL}bpsScraper/delete/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                loadData();
            } else {
                showToast(data.error, 'danger');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'danger'));
    }
    
    // File input label update
    document.getElementById('excelFile')?.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'Pilih file...';
        this.nextElementSibling.innerText = fileName;
        document.getElementById('previewContainer').style.display = 'none';
        document.getElementById('importResult').style.display = 'none';
    });
    
    // Preview Import
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
        
        fetch(`${BASE_URL}bpsScraper/previewImport`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('previewContainer');
                const thead = document.querySelector('#previewTable thead tr');
                const tbody = document.getElementById('previewTableBody');
                
                thead.innerHTML = data.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
                const previewRows = data.data.map(row => {
                    // Escape all cell values to prevent XSS
                    const cells = data.headers.map(h => `<td>${escapeHtml(row[h] || '')}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                });
                previewPager.setRows(previewRows);
                
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
    
    // Import Excel
    document.getElementById('btnImportExcel')?.addEventListener('click', function() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files.length) {
            alert('Pilih file Excel terlebih dahulu');
            return;
        }

        const formData = new FormData(document.getElementById('importExcelForm'));
        const progress = document.getElementById('importProgress');
        const result = document.getElementById('importResult');
        
        progress.style.display = 'block';
        result.style.display = 'none';
        this.disabled = true;

        fetch(`${BASE_URL}bpsScraper/importExcel`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
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
    
    <?php endif; ?>
    
    // ===== KSA (Survei Kerangka Sampel Area) =====

    function loadMonthlyHarvestArea() {
        const tbody = document.getElementById('monthlyHarvestBody');
        const params = new URLSearchParams({
            tahun: document.getElementById('scraperYear').value,
            bulan: document.getElementById('monthlyMonth').value,
            kabupaten: document.getElementById('scraperKabupaten').value,
            page: monthlyPage,
            per_page: monthlyPerPage,
        });
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Memuat data bulanan...</td></tr>';

        fetch(`${BASE_URL}bpsScraper/getMonthlyHarvestArea?${params}`)
            .then(response => response.json())
            .then(response => {
                if (!response.success) throw new Error(response.error || 'Gagal memuat data bulanan');
                const meta = response.meta || {};
                monthlyPage = Number(meta.page) || 1;
                monthlyTotalPages = Number(meta.total_pages) || 1;
                tbody.innerHTML = response.data.length ? response.data.map((row, index) => `
                    <tr>
                        <td class="text-center">${((monthlyPage - 1) * monthlyPerPage) + index + 1}</td>
                        <td><strong>${escapeHtml(row.kabupaten_kota)}</strong></td>
                        <td>${escapeHtml(row.nama_bulan)}</td>
                        <td class="text-center">${escapeHtml(row.tahun)}</td>
                        <td class="text-right">${row.luas_panen === null ? '-' : formatNumber(row.luas_panen)}</td>
                        <td class="text-center">${escapeHtml(row.satuan)}</td>
                        <td><span class="badge badge-light border">${escapeHtml(row.status_data)}</span></td>
                        <td><small>${escapeHtml(row.sumber_data)}</small></td>
                    </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data luas panen bulanan untuk filter ini.</td></tr>';

                renderPager('monthlyPagination', {
                    total: Number(meta.total) || 0,
                    page: monthlyPage,
                    perPage: monthlyPerPage,
                    label: 'luas panen bulanan',
                    onPage: page => { monthlyPage = page; loadMonthlyHarvestArea(); },
                    onSize: size => {
                        monthlyPerPage = size;
                        monthlyPage = 1;
                        document.getElementById('monthlyPerPage').value = String(size);
                        loadMonthlyHarvestArea();
                    },
                });
            })
            .catch(error => {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
                document.getElementById('monthlyPagination').innerHTML = '';
            });
    }

    function loadMonthlyHarvestChart() {
        const container = document.getElementById('monthlyHarvestChartContainer');
        const subtitle = document.getElementById('monthlyChartSubtitle');
        const summary = document.getElementById('monthlyChartSummary');
        const params = new URLSearchParams({
            tahun: document.getElementById('scraperYear').value,
            kabupaten: document.getElementById('scraperKabupaten').value,
        });
        subtitle.textContent = 'Memuat grafik...';
        summary.textContent = '';

        fetch(`${BASE_URL}bpsScraper/getMonthlyHarvestChart?${params}`)
            .then(response => response.json())
            .then(response => {
                if (!response.success) throw new Error(response.error || 'Gagal memuat grafik bulanan');
                const perKab = response.datasets_per_kabupaten || [];
                const values = response.values || [];
                if (perKab.length === 0) {
                    if (monthlyHarvestChart) {
                        monthlyHarvestChart.destroy();
                        monthlyHarvestChart = null;
                    }
                    container.innerHTML = '<div class="h-100 d-flex align-items-center justify-content-center text-muted">Data grafik belum tersedia untuk filter ini.</div>';
                    subtitle.textContent = `${response.meta.scope} · ${response.meta.tahun}`;
                    summary.textContent = 'Tidak ada nilai luas panen bulanan yang dapat divisualisasikan.';
                    return;
                }

                if (!document.getElementById('monthlyHarvestChart')) {
                    container.innerHTML = '<canvas id="monthlyHarvestChart" role="img" aria-label="Grafik garis luas panen bulanan per kabupaten"></canvas>';
                }
                if (monthlyHarvestChart) monthlyHarvestChart.destroy();
                monthlyHarvestChart = new Chart(document.getElementById('monthlyHarvestChart'), {
                    type: 'line',
                    data: {
                        labels: response.labels,
                        datasets: perKab,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'nearest', intersect: false },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'right',
                                labels: { boxWidth: 12, font: { size: 10 } },
                            },
                            tooltip: {
                                callbacks: {
                                    label: context => `${context.dataset.label}: ${formatNumber(context.parsed.y)} Ha`,
                                },
                            },
                        },
                        scales: {
                            x: { title: { display: true, text: 'Bulan' } },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Luas Panen (Ha)' },
                                ticks: { callback: value => formatNumber(value) },
                            },
                        },
                    },
                });

                // Cari kabupaten dengan luas panen tertinggi (agregat tahunan)
                const totals = perKab.map(ds => ({
                    label: ds.label,
                    total: ds.data.reduce((s, v) => s + (Number(v) || 0), 0),
                })).sort((a, b) => b.total - a.total);
                subtitle.textContent = `Per Kabupaten/Kota · ${response.meta.scope} · Tahun ${response.meta.tahun} · ${perKab.length} wilayah`;
                summary.textContent = totals.length
                    ? `Tertinggi: ${totals[0].label} (${formatNumber(totals[0].total)} Ha/tahun). Klik legenda untuk menyorot satu kabupaten.`
                    : '';
            })
            .catch(error => {
                if (monthlyHarvestChart) {
                    monthlyHarvestChart.destroy();
                    monthlyHarvestChart = null;
                }
                container.innerHTML = `<div class="h-100 d-flex align-items-center justify-content-center text-danger">${escapeHtml(error.message)}</div>`;
                subtitle.textContent = 'Grafik gagal dimuat';
                summary.textContent = 'Coba muat ulang data.';
            });
    }

    document.getElementById('btnLoadMonthly').addEventListener('click', () => {
        monthlyPage = 1;
        loadMonthlyHarvestArea();
        loadMonthlyHarvestChart();
    });
    document.getElementById('monthlyMonth').addEventListener('change', () => {
        monthlyPage = 1;
        loadMonthlyHarvestArea();
    });
    document.getElementById('monthlyPerPage').addEventListener('change', event => {
        monthlyPerPage = Number(event.target.value);
        monthlyPage = 1;
        loadMonthlyHarvestArea();
    });
    
    function loadKsaStatus() {
        fetch(`${BASE_URL}bpsScraper/getKsaStatus`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Gagal memuat status KSA');
                
                document.getElementById('ksaTotalRecords').textContent = formatNumber(data.total_records || 0);
                document.getElementById('ksaJumlahKabupaten').textContent = data.jumlah_kabupaten || 0;
                
                const perTahun = document.getElementById('ksaPerTahun');
                if (data.per_tahun && data.per_tahun.length > 0) {
                    perTahun.innerHTML = data.per_tahun.map(t => {
                        const tetap = parseInt(t.tetap || 0, 10);
                        const sementara = parseInt(t.sementara || 0, 10);
                        const potensi = parseInt(t.potensi || 0, 10);
                        return `<span class="badge badge-secondary mr-2 mb-1">${t.tahun}: ${t.total}</span>` +
                            `<span class="badge badge-success mr-2 mb-1">tetap ${tetap}</span>` +
                            `<span class="badge badge-warning mr-2 mb-1">sementara ${sementara}</span>` +
                            `<span class="badge badge-info mr-2 mb-1">potensi ${potensi}</span>`;
                    }).join('');
                } else {
                    perTahun.innerHTML = '<span class="text-muted">Belum ada data. Silakan import file KSA.</span>';
                }
                
                const serverFile = document.getElementById('ksaServerFile');
                if (serverFile) {
                    const options = [];
                    (data.files && data.files.angka_tetap || []).forEach(f =>
                        options.push(`<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`));
                    (data.files && data.files.bulanan || []).forEach(f =>
                        options.push(`<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`));
                    serverFile.innerHTML = '<option value="">— Pilih file XLSX di data/ksa —</option>' + options.join('');
                }
                
                if (data.recent_imports && data.recent_imports.length > 0) {
                    ksaHistoryPager.setRows(data.recent_imports.map(l => {
                        const statusClass = l.status === 'success'
                            ? 'badge-success'
                            : (l.status === 'error' ? 'badge-danger' : 'badge-warning');
                        return `<tr>` +
                            `<td><code>${escapeHtml(l.action)}</code></td>` +
                            `<td><span class="badge ${statusClass}">${escapeHtml(l.status)}</span></td>` +
                            `<td>${escapeHtml(l.message)}</td>` +
                            `<td class="text-nowrap">${escapeHtml(l.created_at)}</td>` +
                            `</tr>`;
                    }));
                } else {
                    ksaHistoryPager.clear();
                }
            })
            .catch(error => {
                showToast(error.message, 'danger');
                document.getElementById('ksaPerTahun').innerHTML = '<span class="text-danger">Gagal memuat status</span>';
            });
    }
    
    function importKsaPath() {
        const file = document.getElementById('ksaServerFile').value;
        if (!file) {
            showToast('Pilih file dari server terlebih dahulu', 'warning');
            return;
        }
        
        const csrf = document.querySelector('input[name="csrf_token"]') ?
            document.querySelector('input[name="csrf_token"]').value : '';
        const formData = new FormData();
        formData.append('csrf_token', csrf);
        formData.append('path', file);
        
        showToast('Memulai import KSA...', 'info');
        fetch(`${BASE_URL}bpsScraper/importKsa`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Import gagal');
                showToast(`Import selesai: ${data.inserted} baru, ${data.updated} update, ${data.skipped} skip`, 'success');
                loadKsaStatus();
                loadData();
                loadCharts();
                loadMonthlyHarvestArea();
                loadMonthlyHarvestChart();
            })
            .catch(error => showToast(error.message, 'danger'));
    }
    
    function importKsaUpload() {
        const fileInput = document.getElementById('ksaUploadFile');
        if (!fileInput.files || fileInput.files.length === 0) {
            showToast('Pilih file XLSX terlebih dahulu', 'warning');
            return;
        }
        
        const csrf = document.querySelector('input[name="csrf_token"]') ?
            document.querySelector('input[name="csrf_token"]').value : '';
        const formData = new FormData();
        formData.append('csrf_token', csrf);
        formData.append('file', fileInput.files[0]);
        
        showToast('Mengupload & memproses file KSA...', 'info');
        fetch(`${BASE_URL}bpsScraper/importKsa`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Import gagal');
                showToast(`Import selesai: ${data.inserted} baru, ${data.updated} update, ${data.skipped} skip`, 'success');
                loadKsaStatus();
                loadData();
                loadCharts();
                loadMonthlyHarvestArea();
                loadMonthlyHarvestChart();
            })
            .catch(error => showToast(error.message, 'danger'));
    }
    
    function syncKsaAnnual() {
        const tahun = document.getElementById('ksaSyncTahun').value;
        const csrf = document.querySelector('input[name="csrf_token"]') ?
            document.querySelector('input[name="csrf_token"]').value : '';
        
        const formData = new FormData();
        formData.append('csrf_token', csrf);
        formData.append('tahun', tahun);
        
        showToast(`Sinkronisasi tahun ${tahun} ke data tahunan...`, 'info');
        fetch(`${BASE_URL}bpsScraper/syncKsaToAnnual`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    const details = Array.isArray(data.errors) ? data.errors.join('; ') : '';
                    throw new Error(data.error || details || 'Sinkronisasi gagal');
                }
                showToast(`Sync tahun ${tahun}: ${data.inserted} baru, ${data.updated} update, ${data.skipped} skip`, 'success');
                loadKsaStatus();
                loadData();
                loadCharts();
                loadMonthlyHarvestArea();
                loadMonthlyHarvestChart();
            })
            .catch(error => showToast(error.message, 'danger'));
    }
    
    // Initialize
    loadData();
    loadCharts();
    loadMonthlyHarvestArea();
    loadMonthlyHarvestChart();
    loadKsaStatus();
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
