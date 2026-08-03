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
                        <?php for ($y = date('Y'); $y >= 2019; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
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
                    <label class="form-label small" for="scraperSource"><strong>Sumber Data</strong></label>
                    <select class="form-control form-control-sm" id="scraperSource" name="source">
                        <option value="simulasi">Simulasi (Data BPS Jatim)</option>
                        <option value="resmi_webapi">BPS WebAPI (Resmi)</option>
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
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="forceRefresh" name="force_refresh" value="true">
                        <label class="form-check-label small" for="forceRefresh">Timpa data lama</label>
                    </div>
                </div>
                
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
            
            <!-- Scraping Progress -->
            <div id="scrapingProgress" class="mt-3" style="display: none;">
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm mr-3"></div>
                        <span id="scrapingMessage">Sedang mengambil data dari BPS...</span>
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
                    <div style="height: 300px;">
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
                    <div style="height: 300px;">
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
            </h6>
            <div class="d-flex align-items-center">
                <label class="mb-0 mr-2 small">Tampilkan:</label>
                <select class="form-control form-control-sm" id="perPageSelect" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="38" selected>38</option>
                    <option value="100">100</option>
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
                            <td colspan="<?= $_SESSION['role'] === 'admin' ? '10' : '9' ?>" class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Klik "Muat Data" atau "Jalankan Scraper" untuk menampilkan data
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
                            <input type="file" class="custom-file-input" id="excelFile" name="excel_file" accept=".xlsx,.xls,.csv" required>
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
                                <tbody></tbody>
                            </table>
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
    let perPage = 38;
    let trendChart = null;
    let topChart = null;
    
    const BASE_URL = '<?= BASE_URL ?>';
    const isAdmin = <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?>;

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Format number
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Toast notification
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.custom-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `custom-toast alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px;';
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : 'info-circle'} mr-2"></i>${escapeHtml(message)}`;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 4000);
    }
    
    // Load data
    function loadData() {
        const tahun = document.getElementById('scraperYear').value;
        const kabupaten = document.getElementById('scraperKabupaten').value;
        const source = document.getElementById('scraperSource').value;
        const skenario = document.getElementById('scraperScenario').value;
        
        const params = new URLSearchParams({
            tahun: tahun,
            kabupaten: kabupaten,
            source: source,
            skenario: skenario,
            limit: perPage,
            offset: (currentPage - 1) * perPage
        });
        
        // Show loading state for table
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary"></div><br>Memuat data...</td></tr>';
        
        fetch(`${BASE_URL}bpsScraper/getData?${params}`)
            .then(r => r.json())
            .then(response => {
                if (!response.success) throw new Error(response.error);
                
                renderTable(response.data);
                renderPagination(response.total);
                updateStatistics(response.statistics);
            })
            .catch(err => {
                console.error('Load error:', err);
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${escapeHtml(err.message)}</td></tr>`;
                showToast('Gagal memuat data', 'danger');
            });
    }
    
    // Render table
    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        const colSpan = isAdmin ? 10 : 9;
        
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
                <td class="text-center">
                    ${escapeHtml(row.produktivitas)}
                    <small class="d-block text-muted text-xs">${validationIcon}</small>
                </td>
                <td>
                    <span class="badge badge-${row.sumber_data_type === 'resmi_webapi' ? 'success' : 'info'}">
                        ${escapeHtml(row.sumber_data_type === 'resmi_webapi' ? 'WebAPI' : 'Simulasi')}
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
                    <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>
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
        if (!stats) return;
        document.getElementById('statLuas').textContent = formatNumber(stats.total_luas_panen || 0) + ' Ha';
        document.getElementById('statGabah').textContent = formatNumber(stats.total_produksi_gabah || 0) + ' Ton';
        document.getElementById('statBeras').textContent = formatNumber(stats.total_produksi_beras || 0) + ' Ton';
        document.getElementById('statProduktivitas').textContent = (stats.rata_produktivitas || 0) + ' Ku/Ha';
    }
    
    // Show Anomalies Modal
    window.showAnomalies = function() {
        $('#anomalyModal').modal('show');
        const tbody = document.getElementById('anomalyBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Memuat data anomali...</td></tr>';
        
        const tahun = document.getElementById('scraperYear').value;
        
        fetch(`${BASE_URL}bpsScraper/getAnomalies?limit=50&tahun=${tahun}`)
            .then(r => r.json())
            .then(response => {
                if (!response.success || response.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-success"><i class="fas fa-check-circle"></i> Tidak ditemukan anomali data untuk tahun ini.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = response.data.map(item => `
                    <tr>
                        <td>${escapeHtml(item.kabupaten_kota)}<br><small class="text-muted">Tahun ${escapeHtml(item.tahun)}</small></td>
                        <td><span class="badge badge-secondary">${escapeHtml(item.field_name)}</span></td>
                        <td>${escapeHtml(item.value_actual)}</td>
                        <td class="text-danger small">${escapeHtml(item.notes)}</td>
                    </tr>
                `).join('');
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
        
        // Trend chart
        fetch(`${BASE_URL}bpsScraper/getChartData?type=yearly`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.labels.length) return;
                
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
            });
        
        // Top producers chart
        fetch(`${BASE_URL}bpsScraper/getChartData?type=top&tahun=${tahun}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.labels.length) return;
                
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
            });
    }
    
    // Run scraper
    <?php if($_SESSION['role'] === 'admin'): ?>
    document.getElementById('scraperForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnScrape');
        const progress = document.getElementById('scrapingProgress');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        progress.style.display = 'block';
        
        const formData = new FormData(this);
        
        fetch(`${BASE_URL}bpsScraper/runScraper`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
            progress.style.display = 'none';
            
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
            } else {
                showToast(data.error || data.message, 'danger');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Jalankan Scraper';
            progress.style.display = 'none';
            showToast('Gagal menjalankan scraper', 'danger');
            console.error(err);
        });
    });
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
        loadData();
        loadCharts();
    });

    // Kabupaten/Source/Scenario change -> reload data
    ['scraperKabupaten', 'scraperSource', 'scraperScenario'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            currentPage = 1;
            loadData();
        });
    });
    
    // Update export link
    document.getElementById('btnExport').addEventListener('click', function(e) {
        e.preventDefault();
        const tahun = document.getElementById('scraperYear').value;
        const kabupaten = document.getElementById('scraperKabupaten').value;
        window.location.href = `${BASE_URL}bpsScraper/export?tahun=${tahun}&kabupaten=${kabupaten}`;
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
                const tbody = document.querySelector('#previewTable tbody');
                
                thead.innerHTML = data.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
                tbody.innerHTML = data.data.map(row => {
                    // Escape all cell values to prevent XSS
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
    
    // Initialize
    loadData();
    loadCharts();
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
