<?php 
$pageTitle = $data['page_title'] ?? 'Data Curah Hujan';
require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="container-fluid py-4">
    <style>
        /* Custom Hover Effects */
        #filterForm .btn {
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        #filterForm .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        #filterForm .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-cloud-rain text-primary"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-muted mb-0">Monitoring curah hujan untuk analisis pertanian</p>
        </div>
        <div class="btn-group">
            <a href="<?= BASE_URL ?>/curahHujan/export?year=<?= $data['currentYear'] ?>" class="btn btn-outline-success">
                <i class="fas fa-download"></i> Export CSV
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#scraperModal">
                <i class="fas fa-sync"></i> Update Data
            </button>
            <a href="<?= BASE_URL ?>/curahHujan/create" class="btn btn-success">
                <i class="fas fa-plus"></i> Input Manual
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <!-- Source Metadata Alert -->
            <?php if(isset($data['lastScrape'])): ?>
            <div class="alert alert-info border-0 shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fas fa-info-circle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading font-weight-bold mb-1">Status Data Curah Hujan</h6>
                        <ul class="mb-0 pl-3 small">
                            <li><strong>Sumber Data:</strong> <?= strpos($data['lastScrape']['message'], 'BMKG') !== false ? 'BMKG (Prakiraan Cuaca)' : 'Simulasi Data (JAGAPADI Internal)' ?> - <a href="https://api.bmkg.go.id/publik/prakiraan-cuaca" target="_blank" class="text-info font-weight-bold"><u>Lihat Sumber Asli</u></a></li>
                            <li><strong>Terakhir Diperbarui:</strong> <?= date('d F Y, H:i', strtotime($data['lastScrape']['created_at'])) ?> WIB</li>
                            <li><strong>Metode Scraping:</strong> <?= ucfirst($data['lastScrape']['action']) ?> (<?= ucfirst($data['lastScrape']['status']) ?>)</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Demo Mode Toggle -->
            <div class="alert alert-success border-0 shadow-sm mb-4 d-flex justify-content-between align-items-center" id="demoModeAlert">
                <div>
                    <i class="fas fa-presentation"></i>
                    <strong>Mode Presentasi</strong>
                    <p class="mb-0 small">
                        Saat aktif, hanya data terverifikasi BMKG yang ditampilkan (data simulasi disembunyikan)
                    </p>
                </div>
                <div class="custom-control custom-switch" style="font-size: 1.2rem;">
                    <input type="checkbox" class="custom-control-input" id="demoModeToggle" checked>
                    <label class="custom-control-label" for="demoModeToggle">
                        <span class="badge badge-success" id="demoModeLabel">Aktif</span>
                    </label>
                </div>
            </div>

            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label" for="filterYear">Tahun</label>
                    <select class="form-control" id="filterYear" name="year">
                        <option value="">Semua Tahun</option>
                        <?php 
                        // Create array of years from 2020 to 2026
                        $fixedYears = range(2020, 2026);
                        // Merge with available years from database (if any)
                        $allYears = array_unique(array_merge($fixedYears, $data['availableYears'] ?? []));
                        // Sort in descending order (newest first)
                        rsort($allYears);
                        foreach ($allYears as $year): ?>
                        <option value="<?= $year ?>" <?= $year == $data['currentYear'] ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterMonth">Bulan</label>
                    <select class="form-control" id="filterMonth" name="month">
                        <option value="">Semua Bulan</option>
                        <option value="1">Januari</option>
                        <option value="2">Februari</option>
                        <option value="3">Maret</option>
                        <option value="4">April</option>
                        <option value="5">Mei</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">Agustus</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Desember</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filterDataSource">Sumber Data</label>
                    <select class="form-control" id="filterDataSource" name="data_source">
                        <option value="all" selected>Semua Sumber</option>
                        <option value="nasa">NASA POWER API</option>
                        <option value="bmkg">BMKG Verified</option>
                        <option value="simulation">Simulasi</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="chartType">Tipe Grafik</label>
                    <select class="form-control" id="chartType" name="chartType">
                        <option value="monthly">Bulanan</option>
                        <option value="yearly">Tahunan</option>
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
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Rata-rata</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statAverage">
                                <?= number_format($data['statistics']['rata_rata'] ?? 0, 2) ?> mm
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tint fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Maksimum</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statMax">
                                <?= number_format($data['statistics']['maksimum'] ?? 0, 2) ?> mm
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cloud-showers-heavy fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Hari Hujan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statRainyDays">
                                <?= $data['statistics']['hari_hujan'] ?? 0 ?> hari
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Data</div>
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
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Grafik Curah Hujan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="rainfallChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Distribusi Bulanan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 320px;">
                        <canvas id="distributionChart"></canvas>
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
                    <a class="nav-link active" id="analysis-tab" data-toggle="tab" href="#analysisPane" role="tab">
                        <i class="fas fa-chart-area"></i> Analisis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="map-tab" data-toggle="tab" href="#mapPane" role="tab">
                        <i class="fas fa-map-marked-alt"></i> Peta
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="alerts-tab" data-toggle="tab" href="#alertsPane" role="tab">
                        <i class="fas fa-bell"></i> Peringatan
                        <span class="badge badge-danger ml-1" id="alertBadge" style="display:none">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="prediction-tab" data-toggle="tab" href="#predictionPane" role="tab">
                        <i class="fas fa-chart-line"></i> Prediksi
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="dashboardTabContent">
                <!-- Analysis Tab -->
                <div class="tab-pane fade show active" id="analysisPane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-info h-100">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-info">
                                        <i class="fas fa-chart-line"></i> Tren Multi-Tahun
                                    </h6>
                                    <div>
                                        <select class="form-control form-control-sm d-inline-block w-auto" id="trendYearRange">
                                            <option value="3">3 Tahun</option>
                                            <option value="5" selected>5 Tahun</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div style="height: 250px;">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-success h-100">
                                <div class="card-header py-2">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-sun"></i> Pola Musiman <?= date('Y') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div style="height: 250px;">
                                        <canvas id="seasonalChart"></canvas>
                                    </div>
                                    <div class="mt-2 text-center small">
                                        <span class="mr-3"><span style="display:inline-block;width:12px;height:12px;background:rgba(54,162,235,0.7);border-radius:2px;"></span> Musim Hujan</span>
                                        <span class="mr-3"><span style="display:inline-block;width:12px;height:12px;background:rgba(255,206,86,0.7);border-radius:2px;"></span> Peralihan</span>
                                        <span><span style="display:inline-block;width:12px;height:12px;background:rgba(255,99,132,0.7);border-radius:2px;"></span> Musim Kemarau</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-left-warning">
                                <div class="card-header py-2">
                                    <h6 class="m-0 font-weight-bold text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Deteksi Anomali <?= date('Y') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="anomalyStats" class="mb-3">
                                        <span class="text-muted">Memuat data anomali...</span>
                                    </div>
                                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered" id="anomalyTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Lokasi</th>
                                                    <th>Curah Hujan</th>
                                                    <th>Tipe</th>
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
                
                <!-- Map Tab -->
                <div class="tab-pane fade" id="mapPane" role="tabpanel">
                    <div id="rainfallMap" style="height: 400px; border-radius: 8px;"></div>
                    <div class="mt-3 text-center small text-muted">
                        <i class="fas fa-info-circle"></i> Peta menampilkan intensitas rata-rata curah hujan per wilayah. Klik marker untuk detail.
                    </div>
                </div>
                
                <!-- Alerts Tab -->
                <div class="tab-pane fade" id="alertsPane" role="tabpanel">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label small">Threshold (mm)</label>
                            <input type="number" class="form-control form-control-sm" id="alertThreshold" value="50" min="1" max="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Periode (hari)</label>
                            <input type="number" class="form-control form-control-sm" id="alertDays" value="7" min="1" max="30">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-info btn-sm w-100" id="btnCheckAlerts">
                                <i class="fas fa-search"></i> Cek Peringatan
                            </button>
                        </div>
                    </div>
                    <div id="alertsContainer">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-bell-slash fa-3x mb-3"></i>
                            <p>Klik "Cek Peringatan" untuk melihat data curah hujan tinggi.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Prediction Tab -->
                <div class="tab-pane fade" id="predictionPane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div style="height: 300px;">
                                <canvas id="predictionChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="font-weight-bold"><i class="fas fa-info-circle text-info"></i> Tentang Prediksi</h6>
                                    <p class="small mb-2">Prediksi menggunakan metode <strong>Moving Average 3 bulan</strong> berdasarkan data historis.</p>
                                    <div id="predictionInfo" class="small text-muted">
                                        Memuat prediksi...
                                    </div>
                                </div>
                            </div>
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
                <h6 class="m-0 font-weight-bold text-primary mr-3">
                    <i class="fas fa-table"></i> Data Curah Hujan
                </h6>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshData" title="Refresh Data">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> <span class="d-none d-sm-inline">Refresh</span>
                </button>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-danger btn-sm mt-2 mt-md-0" id="btnDeleteSelectedData" style="display:none;">
                <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedDataCount">0</span>)
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Pagination Controls Top -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pagination-controls">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <label class="mr-2 mb-0" for="perPageSelect">Tampilkan:</label>
                    <select class="form-control form-control-sm" id="perPageSelect" style="width: auto;">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="all">Semua</option>
                    </select>
                    <span class="ml-2">data per halaman</span>
                </div>
                <div id="paginationInfo" class="text-muted small">
                    Menampilkan <span id="showingFrom">0</span> - <span id="showingTo">0</span> dari <span id="totalRecords">0</span> data
                </div>
            </div>
            
            <!-- Loading Overlay -->
            <div id="tableLoadingOverlay" class="table-loading-overlay" style="display: none;">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2 mb-0">Memuat data...</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="thead-light">
                        <tr>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <th style="width:40px;"><label class="sr-only" for="selectAllData">Pilih Semua Data</label><input type="checkbox" id="selectAllData" name="selectAllData" title="Pilih Semua" aria-label="Pilih semua data curah hujan"></th>
                            <?php endif; ?>
                            <th>Tanggal</th>
                            <th>Bulan</th>
                            <th>Tahun</th>
                            <th>Lokasi</th>
                            <th>Curah Hujan</th>
                            <th>Sumber</th>
                            <th>Keterangan</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="dataTableBody">
                        <?php if (!empty($data['recentData'])): ?>
                        <?php 
                        // Indonesian month names for display
                        $namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        ?>
                        <?php foreach ($data['recentData'] as $row): ?>
                        <?php 
                        // Extract month and year from tanggal
                        $tanggalObj = strtotime($row['tanggal']);
                        $bulan = $namaBulan[date('n', $tanggalObj) - 1]; // date('n') returns 1-12
                        $tahun = date('Y', $tanggalObj);
                        ?>
                        <tr>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <td><label class="sr-only" for="data-checkbox-<?= $row['id'] ?>">Pilih data <?= $row['id'] ?></label><input type="checkbox" class="data-checkbox" id="data-checkbox-<?= $row['id'] ?>" name="data-checkbox-<?= $row['id'] ?>" data-id="<?= $row['id'] ?>" aria-label="Pilih data tanggal <?= date('d/m/Y', $tanggalObj) ?>"></td>
                            <?php endif; ?>
                            <td><?= date('d/m/Y', $tanggalObj) ?></td>
                            <td><?= htmlspecialchars($bulan) ?></td>
                            <td><?= htmlspecialchars($tahun) ?></td>
                            <td><?= htmlspecialchars($row['lokasi']) ?></td>
                            <td>
                                <span class="badge badge-<?= $row['curah_hujan'] > 50 ? 'danger' : ($row['curah_hujan'] > 20 ? 'warning' : ($row['curah_hujan'] > 0 ? 'info' : 'secondary')) ?>">
                                    <?= number_format($row['curah_hujan'], 2) ?> mm
                            </span>
                            </td>
                            <td>
                                <?php if (strpos($row['sumber_data'], 'BMKG') !== false): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($row['sumber_data']) ?>
                                    </span>
                                <?php elseif (strpos($row['sumber_data'], 'Simulasi') !== false): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($row['sumber_data']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">
                                        <?= htmlspecialchars($row['sumber_data']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <td>
                                <button class="btn btn-sm btn-primary btn-edit no-touch-feedback mr-1" onclick="editData(<?= $row['id'] ?>)" title="Edit" aria-label="Edit data tanggal <?= date('d/m/Y', $tanggalObj) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete no-touch-feedback" onclick="deleteData(<?= $row['id'] ?>)" title="Hapus" aria-label="Hapus data tanggal <?= date('d/m/Y', $tanggalObj) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="<?= $_SESSION['role'] === 'admin' ? '9' : '7' ?>" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <nav aria-label="Page navigation" id="paginationNav">
                <ul class="pagination justify-content-center flex-wrap" id="pagination">
                </ul>
            </nav>
            
            <!-- Pagination Info Bottom (mobile) -->
            <div id="paginationInfoBottom" class="text-center text-muted small mt-3 d-md-none">
                Halaman <span id="currentPageDisplay">1</span> dari <span id="totalPagesDisplay">1</span>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'admin' && !empty($data['recentLogs'])): ?>
    <!-- Recent Logs (Admin Only) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-secondary">
                <i class="fas fa-history"></i> Log Aktivitas Scraping
            </h6>
            <div class="ml-auto">
                <small class="text-muted mr-2" id="logsLastUpdated" style="display:none;">Diperbarui: -</small>
                <button type="button" class="btn btn-info btn-sm mr-1" id="btnRefreshLogs" onclick="refreshLogs()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="btnDeleteSelectedLogs" style="display:none;">
                    <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedLogsCount">0</span>)
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="logsTable">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><label class="sr-only" for="selectAllLogs">Pilih Semua Log</label><input type="checkbox" id="selectAllLogs" name="selectAllLogs" title="Pilih Semua" aria-label="Pilih semua log aktivitas"></th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                            <th>Status</th>
                            <th>Pesan</th>
                            <th>Record</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['recentLogs'] as $log): ?>
                        <tr>
                            <td><label class="sr-only" for="log-checkbox-<?= $log['id'] ?>">Pilih log <?= $log['id'] ?></label><input type="checkbox" class="log-checkbox" id="log-checkbox-<?= $log['id'] ?>" name="log-checkbox-<?= $log['id'] ?>" data-id="<?= $log['id'] ?>" aria-label="Pilih log tanggal <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>"></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td>
                                <span class="badge badge-<?= $log['status'] === 'success' ? 'success' : ($log['status'] === 'partial' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($log['message']) ?></td>
                            <td><?= $log['records_success'] ?>/<?= $log['records_processed'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger btn-delete-log" onclick="deleteLog(<?= $log['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Scraper Modal -->
<div class="modal fade" id="scraperModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-sync"></i> Update Data Curah Hujan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="scraperForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    
                    <!-- Year Selection -->
                    <div class="form-group">
                        <label for="scraperYear">Tahun</label>
                        <select class="form-control" name="year" id="scraperYear">
                            <?php 
                            // Generate years from 2020 to 2026 in ascending order
                            for ($y = 2020; $y <= 2026; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <small class="form-text text-muted">Pilih tahun untuk data yang akan diambil (2020-2026)</small>
                    </div>
                    
                    <!-- Scraping Mode Selection -->
                    <div class="form-group">
                        <span class="d-block mb-2 font-weight-normal">Mode Pengambilan Data</span>
                        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                            <label class="btn btn-outline-primary active flex-fill" id="labelModeMonthly">
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
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="yearlyProgressBar" 
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
                    <button type="submit" class="btn btn-primary" id="btnRunScraper">
                        <i class="fas fa-play" id="scraperBtnIcon"></i> <span id="scraperBtnText">Jalankan Scraper</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Data Modal -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="editDataModal" tabindex="-1" role="dialog" aria-labelledby="editDataModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editDataModalLabel"><i class="fas fa-edit"></i> Edit Data Curah Hujan</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editDataForm">
                <div class="modal-body">
                    <input type="hidden" id="editDataId">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    
                    <div class="form-group">
                        <label for="editTanggal">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="editTanggal" name="tanggal" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editLokasi">Lokasi</label>
                        <input type="text" class="form-control" id="editLokasi" name="lokasi" value="Jember">
                    </div>
                    
                    <div class="form-group">
                        <label for="editCurahHujan">Curah Hujan (mm) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="editCurahHujan" name="curah_hujan" step="0.01" min="0" max="500" required>
                        <small class="form-text text-muted">Nilai antara 0 - 500 mm</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSumberData">Sumber Data</label>
                        <select class="form-control" id="editSumberData" name="sumber_data">
                            <option value="Manual">Manual</option>
                            <option value="BMKG API">BMKG API</option>
                            <option value="Simulasi">Simulasi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editKeterangan">Keterangan</label>
                        <textarea class="form-control" id="editKeterangan" name="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveEdit">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Robust DOMContentLoaded wrapper - handles both loading and already-loaded states
(function() {
    'use strict';
    
    function initCurahHujan() {
    const csrfToken = '<?= Security::generateCsrfToken() ?>';
    let rainfallChart = null;
    let distributionChart = null;
    let currentPage = 1;
    let perPage = parseInt(localStorage.getItem('curahHujan_perPage')) || 10;
    let totalRecords = 0;
    
    // Data cache for filter results (avoid redundant API calls)
    // MUST be declared before any function that uses it is called
    const dataCache = new Map();
    const CACHE_TTL = 60000; // Cache valid for 60 seconds
    const REQUEST_TIMEOUT = 30000; // 30 second timeout
    
    // ========== TOAST NOTIFICATION UTILITY ==========
    function showToast(message, type = 'info') {
        // Remove any existing toasts
        const existingToast = document.querySelector('.custom-toast');
        if (existingToast) existingToast.remove();
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `custom-toast alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 250px;';
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'times-circle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>${message}`;
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Make showToast globally available
    window.showToast = showToast;
    
    // Initialize perPage dropdown from localStorage
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.value = localStorage.getItem('curahHujan_perPage') || '10';
        
        // PerPage change handler
        perPageSelect.addEventListener('change', function() {
            const value = this.value;
            if (value === 'all') {
                perPage = 999999; // Large number for "all"
                localStorage.setItem('curahHujan_perPage', 'all');
            } else {
                perPage = parseInt(value);
                localStorage.setItem('curahHujan_perPage', value);
            }
            currentPage = 1; // Reset to first page
            loadData();
        });
    }

    // Initialize charts (wrapped in try-catch to not block other init)
    try {
        initCharts();
    } catch (e) {
        console.error('[Init] Chart initialization failed:', e);
    }
    
    // Load initial data (wrapped in try-catch to not block other init)
    try {
        loadData();
    } catch (e) {
        console.error('[Init] Data loading failed:', e);
    }
    
    // Filter form submit
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1; // Reset to first page on filter
        loadData();
        loadCharts();
    });

    // Reset Button Handler
    const btnResetFilter = document.getElementById('btnResetFilter');
    if (btnResetFilter) {
        btnResetFilter.addEventListener('click', function() {
            // Reset dropdowns to default values
            document.getElementById('filterYear').value = new Date().getFullYear();
            document.getElementById('filterMonth').value = '';
            document.getElementById('filterDataSource').value = 'all';
            document.getElementById('chartType').value = 'monthly';
            
            // Check demo mode toggle if it exists and reset it too if needed
            // But requirement says: "keep system functionality". Assuming defaults.
            // Actually, source default is BMKG.
            
            // Trigger data reload
            currentPage = 1;
            loadData();
            loadCharts();
            
            showToast('Filter telah di-reset', 'info');
        });
    }
    
    // ========== ENHANCED DASHBOARD FEATURES ==========
    
    // Chart instances for dashboard
    let trendChart = null;
    let seasonalChart = null;
    let predictionChart = null;
    let rainfallMap = null;
    
    // Initialize dashboard features when Analysis tab is first shown
    let dashboardInitialized = false;
    
    function initDashboardFeatures() {
        if (dashboardInitialized) return;
        dashboardInitialized = true;
        
        loadTrendChart();
        loadSeasonalChart();
        loadAnomalyData();
        loadPredictionChart();
    }
    
    // Load trend chart
    function loadTrendChart() {
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;
        
        const years = document.getElementById('trendYearRange')?.value || 5;
        const endYear = new Date().getFullYear();
        const startYear = endYear - years + 1;
        
        fetch(`<?= BASE_URL ?>curahHujan/getTrendData?start_year=${startYear}&end_year=${endYear}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Failed to load trend data');
                
                if (trendChart) trendChart.destroy();
                
                trendChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'mm' }
                            }
                        }
                    }
                });
            })
            .catch(err => console.error('Trend chart error:', err));
    }
    
    // Load seasonal chart
    function loadSeasonalChart() {
        const canvas = document.getElementById('seasonalChart');
        if (!canvas) return;
        
        fetch(`<?= BASE_URL ?>curahHujan/getSeasonalData?year=${new Date().getFullYear()}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Failed to load seasonal data');
                
                if (seasonalChart) seasonalChart.destroy();
                
                seasonalChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Rata-rata (mm)',
                            data: data.values,
                            backgroundColor: data.colors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            })
            .catch(err => console.error('Seasonal chart error:', err));
    }
    
    // Load anomaly data
    function loadAnomalyData() {
        const statsContainer = document.getElementById('anomalyStats');
        const tableBody = document.querySelector('#anomalyTable tbody');
        if (!statsContainer || !tableBody) return;
        
        fetch(`<?= BASE_URL ?>curahHujan/getAnomalyData?year=${new Date().getFullYear()}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Failed to load anomaly data');
                
                const stats = data.statistics;
                statsContainer.innerHTML = `
                    <span class="badge badge-secondary mr-2">Rata-rata: ${stats.mean} mm</span>
                    <span class="badge badge-secondary mr-2">Std Dev: ${stats.stddev} mm</span>
                    <span class="badge badge-warning mr-2">Batas Atas: ${stats.upper_limit} mm</span>
                    <span class="badge badge-info">Total Anomali: ${data.total_anomalies}</span>
                `;
                
                if (data.anomalies.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada anomali terdeteksi</td></tr>';
                } else {
                    tableBody.innerHTML = data.anomalies.slice(0, 10).map(a => `
                        <tr>
                            <td>${a.tanggal}</td>
                            <td>${a.lokasi}</td>
                            <td><strong>${parseFloat(a.curah_hujan).toFixed(2)} mm</strong></td>
                            <td><span class="badge badge-${a.tipe_anomali === 'Tinggi' ? 'danger' : 'info'}">${a.tipe_anomali}</span></td>
                        </tr>
                    `).join('');
                }
            })
            .catch(err => {
                console.error('Anomaly data error:', err);
                statsContainer.innerHTML = '<span class="text-danger">Gagal memuat data anomali</span>';
            });
    }
    
    // Load prediction chart
    function loadPredictionChart() {
        const canvas = document.getElementById('predictionChart');
        const infoContainer = document.getElementById('predictionInfo');
        if (!canvas) return;
        
        fetch(`<?= BASE_URL ?>curahHujan/getPredictionData?months=3`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Failed to load prediction data');
                
                const historical = data.historical || [];
                const predictions = data.predictions || [];
                
                const labels = [...historical.map(h => h.periode), ...predictions.map(p => p.periode)];
                const historicalValues = [...historical.map(h => parseFloat(h.rata_rata)), ...predictions.map(() => null)];
                const predictionValues = [...historical.map(() => null), ...predictions.map(p => parseFloat(p.prediksi))];
                
                // Connect the last historical point to prediction
                if (historical.length > 0 && predictions.length > 0) {
                    predictionValues[historical.length - 1] = historicalValues[historical.length - 1];
                }
                
                if (predictionChart) predictionChart.destroy();
                
                predictionChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Historis',
                                data: historicalValues,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: 'Prediksi',
                                data: predictionValues,
                                borderColor: 'rgb(255, 159, 64)',
                                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                                borderDash: [5, 5],
                                fill: true,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'mm' }
                            }
                        }
                    }
                });
                
                if (infoContainer) {
                    infoContainer.innerHTML = `
                        <p class="mb-1"><strong>Metode:</strong> ${data.method}</p>
                        <p class="mb-0"><strong>Prediksi ${predictions.length} bulan ke depan</strong></p>
                        ${predictions.map(p => `<div class="mt-1">${p.periode}: <strong>${p.prediksi} mm</strong></div>`).join('')}
                    `;
                }
            })
            .catch(err => {
                console.error('Prediction data error:', err);
                if (infoContainer) infoContainer.innerHTML = '<span class="text-danger">Gagal memuat prediksi</span>';
            });
    }
    
    // Initialize map when tab is shown
    function initRainfallMap() {
        const mapContainer = document.getElementById('rainfallMap');
        if (!mapContainer || rainfallMap) return;
        
        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            mapContainer.innerHTML = '<div class="alert alert-warning text-center"><i class="fas fa-map-marker-alt"></i> Memuat peta...</div>';
            return;
        }
        
        rainfallMap = L.map('rainfallMap').setView([-8.1706, 113.7003], 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(rainfallMap);
        
        // Load map data
        fetch(`<?= BASE_URL ?>curahHujan/getMapData?year=${new Date().getFullYear()}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) {
                    L.popup()
                        .setLatLng([-8.1706, 113.7003])
                        .setContent('<strong>Data Curah Hujan</strong><br>Tidak ada data lokasi tersedia.')
                        .openOn(rainfallMap);
                    return;
                }
                
                data.data.forEach(item => {
                    const color = item.rata_rata > 15 ? '#dc3545' : 
                                 item.rata_rata > 10 ? '#ffc107' : 
                                 item.rata_rata > 5 ? '#17a2b8' : '#28a745';
                    
                    const marker = L.circleMarker([item.latitude, item.longitude], {
                        radius: 15,
                        fillColor: color,
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(rainfallMap);
                    
                    marker.bindPopup(`
                        <strong>${item.lokasi}</strong><br>
                        Rata-rata: ${item.rata_rata} mm<br>
                        Total: ${item.total} mm<br>
                        Maks: ${item.maksimum} mm<br>
                        Data: ${item.jumlah_data} record
                    `);
                });
            })
            .catch(err => console.error('Map data error:', err));
    }
    
    // Check alerts
    function checkAlerts() {
        const threshold = document.getElementById('alertThreshold')?.value || 50;
        const days = document.getElementById('alertDays')?.value || 7;
        const container = document.getElementById('alertsContainer');
        const badge = document.getElementById('alertBadge');
        
        if (!container) return;
        
        container.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
        
        fetch(`<?= BASE_URL ?>curahHujan/checkAlerts?threshold=${threshold}&days=${days}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Failed to check alerts');
                
                if (badge) {
                    if (data.total > 0) {
                        badge.textContent = data.total;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                if (data.alerts.length === 0) {
                    container.innerHTML = `
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <p class="mb-0">Tidak ada peringatan curah hujan tinggi dalam ${days} hari terakhir.</p>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Ditemukan <strong>${data.total}</strong> data dengan curah hujan > ${threshold} mm
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Lokasi</th>
                                        <th>Curah Hujan</th>
                                        <th>Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.alerts.map(a => `
                                        <tr>
                                            <td>${a.tanggal}</td>
                                            <td>${a.lokasi}</td>
                                            <td><strong>${parseFloat(a.curah_hujan).toFixed(2)} mm</strong></td>
                                            <td><span class="badge badge-${a.level === 'Kritis' ? 'danger' : a.level === 'Tinggi' ? 'warning' : 'info'}">${a.level}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Alerts error:', err);
                container.innerHTML = '<div class="alert alert-danger">Gagal memuat data peringatan</div>';
            });
    }
    
    // Tab event handlers
    document.querySelectorAll('#dashboardTabs a[data-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('href');
            
            if (target === '#analysisPane') {
                initDashboardFeatures();
            } else if (target === '#mapPane') {
                setTimeout(() => initRainfallMap(), 100);
                if (rainfallMap) rainfallMap.invalidateSize();
            } else if (target === '#alertsPane') {
                // Auto-check alerts on tab open
                checkAlerts();
            } else if (target === '#predictionPane') {
                if (!predictionChart) loadPredictionChart();
            }
        });
    });
    
    // Trend year range change
    document.getElementById('trendYearRange')?.addEventListener('change', loadTrendChart);
    
    // Alert check button
    document.getElementById('btnCheckAlerts')?.addEventListener('click', checkAlerts);
    
    // Initialize dashboard features immediately for the default active tab
    initDashboardFeatures();
    

    // Chart type change
    document.getElementById('chartType').addEventListener('change', function() {
        loadCharts();
    });
    
    // Demo Mode Toggle Handler
    const demoModeToggle = document.getElementById('demoModeToggle');
    const filterDataSource = document.getElementById('filterDataSource');
    const demoModeAlert = document.getElementById('demoModeAlert');
    const demoModeLabel = document.getElementById('demoModeLabel');
    
    if (demoModeToggle && filterDataSource) {
        demoModeToggle.addEventListener('change', function() {
            const isDemo = this.checked;
            
            if (isDemo) {
                // Demo mode active: lock to BMKG data only
                filterDataSource.value = 'bmkg';
                filterDataSource.disabled = true;
                demoModeLabel.textContent = 'Aktif';
                demoModeLabel.className = 'badge badge-success';
                demoModeAlert.className = 'alert alert-success border-0 shadow-sm mb-4 d-flex justify-content-between align-items-center';
                showToast('Mode Presentasi Aktif - Hanya data BMKG yang ditampilkan', 'success');
            } else {
                // Demo mode off: allow all sources
                filterDataSource.disabled = false;
                demoModeLabel.textContent = 'Nonaktif';
                demoModeLabel.className = 'badge badge-secondary';
                demoModeAlert.className = 'alert alert-info border-0 shadow-sm mb-4 d-flex justify-content-between align-items-center';
                showToast('Mode Presentasi Nonaktif', 'info');
            }
            
            // Reload data and charts with new filter
            currentPage = 1;
            loadData();
            loadCharts();
        });
    }
    
    // Data Source Filter Change Handler
    if (filterDataSource) {
        filterDataSource.addEventListener('change', function() {
            // Reset to first page
            currentPage = 1;
            loadData();
            loadCharts();
            
            // Show feedback
            const sourceText = this.options[this.selectedIndex].text;
            showToast(`Filter sumber data: ${sourceText}`, 'info');
        });
    }
    
    // Scraper form submit
    document.getElementById('scraperForm').addEventListener('submit', function(e) {
        e.preventDefault();
        runScraper();
    });
    
    // Refresh Data button
    const refreshBtn = document.getElementById('btnRefreshData');
    const refreshIcon = document.getElementById('refreshIcon');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            // Add spinning animation
            refreshIcon.classList.add('fa-spin');
            refreshBtn.disabled = true;
            
            // Clear cache to force fresh data
            clearCache();
            
            // Load data with force refresh
            loadData(true);
            loadCharts();
            
            // Show feedback
            showToast('Data berhasil diperbarui', 'success');
            
            // Remove spinning after a delay
            setTimeout(() => {
                refreshIcon.classList.remove('fa-spin');
                refreshBtn.disabled = false;
            }, 1000);
        });
    }
    
    // Delete buttons
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Yakin ingin menghapus data ini?')) {
                deleteData(this.dataset.id);
            }
        });
    });

    // ================================================================
    // SELECT ALL & DELETE ALL - SIMPLIFIED ROBUST IMPLEMENTATION
    // ================================================================
    
    // Use document-level event delegation for maximum reliability
    (function() {
        console.log('[SelectAll] Initializing Select All system...');
        
        // ========== DATA TABLE SELECT ALL ==========
        const selectAllData = document.getElementById('selectAllData');
        const deleteDataBtn = document.getElementById('btnDeleteSelectedData');
        const dataCountSpan = document.getElementById('selectedDataCount');
        
        if (selectAllData) {
            console.log('[SelectAll] Found selectAllData checkbox, attaching listener...');
            
            // Select All checkbox for Data table
            selectAllData.addEventListener('click', function() {
                const isChecked = this.checked;
                const checkboxes = document.querySelectorAll('#dataTableBody .data-checkbox');
                
                console.log('[DATA] Select All clicked:', isChecked, 'Found', checkboxes.length, 'checkboxes');
                
                if (checkboxes.length === 0) {
                    showToast('Tidak ada data untuk dipilih', 'warning');
                    this.checked = false;
                    return;
                }
                
                checkboxes.forEach(cb => {
                    cb.checked = isChecked;
                    const row = cb.closest('tr');
                    if (row) row.classList.toggle('table-primary', isChecked);
                });
                
                updateDataSelectionUI();
                
                if (isChecked) {
                    showToast(`${checkboxes.length} data terpilih`, 'info');
                }
            });
        } else {
            console.log('[SelectAll] selectAllData not found (user not admin?)');
        }
        
        // Event delegation for individual data checkboxes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('data-checkbox')) {
                const row = e.target.closest('tr');
                if (row) row.classList.toggle('table-primary', e.target.checked);
                updateDataSelectionUI();
            }
        });
        
        function updateDataSelectionUI() {
            const allCheckboxes = document.querySelectorAll('#dataTableBody .data-checkbox');
            const checkedCheckboxes = document.querySelectorAll('#dataTableBody .data-checkbox:checked');
            const count = checkedCheckboxes.length;
            
            console.log('[DATA] Selection updated:', count, 'of', allCheckboxes.length);
            
            // Update count display
            if (dataCountSpan) dataCountSpan.textContent = count;
            
            // Show/hide delete button
            if (deleteDataBtn) {
                deleteDataBtn.style.display = count > 0 ? 'inline-block' : 'none';
            }
            
            // Sync select all checkbox state
            if (selectAllData) {
                if (allCheckboxes.length === 0 || count === 0) {
                    selectAllData.checked = false;
                    selectAllData.indeterminate = false;
                } else if (count === allCheckboxes.length) {
                    selectAllData.checked = true;
                    selectAllData.indeterminate = false;
                } else {
                    selectAllData.checked = false;
                    selectAllData.indeterminate = true;
                }
            }
        }
        
        // Delete Selected Data button handler
        if (deleteDataBtn) {
            deleteDataBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const checked = document.querySelectorAll('#dataTableBody .data-checkbox:checked');
                const ids = Array.from(checked).map(cb => cb.getAttribute('data-id'));
                
                console.log('[DATA] Delete clicked, IDs:', ids);
                
                if (ids.length === 0) {
                    showToast('Pilih minimal satu data untuk dihapus', 'warning');
                    return;
                }
                
                if (confirm(`Yakin ingin menghapus ${ids.length} data terpilih?\n\nAksi ini tidak dapat dibatalkan!`)) {
                    deleteMultipleData(ids);
                }
            });
        }
        
        // Make update function globally available
        window.updateDataSelectionCount = updateDataSelectionUI;
        window.syncDataSelectAllState = updateDataSelectionUI;
        
        // ========== LOGS TABLE SELECT ALL ==========
        const selectAllLogs = document.getElementById('selectAllLogs');
        const deleteLogsBtn = document.getElementById('btnDeleteSelectedLogs');
        const logsCountSpan = document.getElementById('selectedLogsCount');
        
        if (selectAllLogs) {
            console.log('[SelectAll] Found selectAllLogs checkbox, attaching listener...');
            
            selectAllLogs.addEventListener('click', function() {
                const isChecked = this.checked;
                const checkboxes = document.querySelectorAll('#logsTable .log-checkbox');
                
                console.log('[LOGS] Select All clicked:', isChecked, 'Found', checkboxes.length, 'checkboxes');
                
                if (checkboxes.length === 0) {
                    showToast('Tidak ada log untuk dipilih', 'warning');
                    this.checked = false;
                    return;
                }
                
                checkboxes.forEach(cb => {
                    cb.checked = isChecked;
                    const row = cb.closest('tr');
                    if (row) row.classList.toggle('table-primary', isChecked);
                });
                
                updateLogsSelectionUI();
                
                if (isChecked) {
                    showToast(`${checkboxes.length} log terpilih`, 'info');
                }
            });
        }
        
        // Event delegation for individual log checkboxes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('log-checkbox')) {
                const row = e.target.closest('tr');
                if (row) row.classList.toggle('table-primary', e.target.checked);
                updateLogsSelectionUI();
            }
        });
        
        function updateLogsSelectionUI() {
            const allCheckboxes = document.querySelectorAll('#logsTable .log-checkbox');
            const checkedCheckboxes = document.querySelectorAll('#logsTable .log-checkbox:checked');
            const count = checkedCheckboxes.length;
            
            console.log('[LOGS] Selection updated:', count, 'of', allCheckboxes.length);
            
            if (logsCountSpan) logsCountSpan.textContent = count;
            
            if (deleteLogsBtn) {
                deleteLogsBtn.style.display = count > 0 ? 'inline-block' : 'none';
            }
            
            if (selectAllLogs) {
                if (allCheckboxes.length === 0 || count === 0) {
                    selectAllLogs.checked = false;
                    selectAllLogs.indeterminate = false;
                } else if (count === allCheckboxes.length) {
                    selectAllLogs.checked = true;
                    selectAllLogs.indeterminate = false;
                } else {
                    selectAllLogs.checked = false;
                    selectAllLogs.indeterminate = true;
                }
            }
        }
        
        // Delete Selected Logs button handler
        if (deleteLogsBtn) {
            deleteLogsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const checked = document.querySelectorAll('#logsTable .log-checkbox:checked');
                const ids = Array.from(checked).map(cb => cb.getAttribute('data-id'));
                
                console.log('[LOGS] Delete clicked, IDs:', ids);
                
                if (ids.length === 0) {
                    showToast('Pilih minimal satu log untuk dihapus', 'warning');
                    return;
                }
                
                if (confirm(`Yakin ingin menghapus ${ids.length} log terpilih?\n\nAksi ini tidak dapat dibatalkan!`)) {
                    deleteMultipleLogs(ids);
                }
            });
        }
        
        console.log('[SelectAll] Initialization complete!');
    })();

    window.deleteMultipleData = function(ids) {
        console.log('[DATA] Deleting multiple records:', ids);
        
        // Show loading state
        const deleteBtn = document.getElementById('btnDeleteSelectedData');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
        }
        
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('ids', JSON.stringify(ids));

        fetch(`<?= BASE_URL ?>/curahHujan/deleteMultiple`, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Berhasil menghapus ' + data.deleted + ' data', 'success');
                loadData();
                loadCharts();
                // Reset select all
                const selectAll = document.getElementById('selectAllData');
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
            } else {
                showToast(data.error || 'Gagal menghapus data', 'danger');
            }
        })
        .catch(function(err) {
            console.error('[DATA] Delete error:', err);
            showToast('Error: ' + err.message, 'danger');
        })
        .finally(function() {
            // Restore button state
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedDataCount">0</span>)';
                deleteBtn.style.display = 'none';
            }
        });
    };

    window.deleteMultipleLogs = function(ids) {
        console.log('[LOGS] Deleting multiple records:', ids);
        
        // Show loading state
        const deleteBtn = document.getElementById('btnDeleteSelectedLogs');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
        }
        
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('ids', JSON.stringify(ids));

        fetch(`<?= BASE_URL ?>/curahHujan/deleteMultipleLogs`, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Berhasil menghapus ' + data.deleted + ' log', 'success');
                // Remove deleted rows from table instead of full reload
                ids.forEach(function(id) {
                    const checkbox = document.querySelector('#logsTable .log-checkbox[data-id="' + id + '"]');
                    if (checkbox) {
                        const row = checkbox.closest('tr');
                        if (row) row.remove();
                    }
                });
                // Reset select all
                const selectAll = document.getElementById('selectAllLogs');
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
                // Update button visibility
                const remainingLogs = document.querySelectorAll('#logsTable .log-checkbox');
                if (remainingLogs.length === 0) {
                    // No more logs, hide the section or show message
                    const logsCard = document.querySelector('#logsTable').closest('.card');
                    if (logsCard) {
                        logsCard.querySelector('tbody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Tidak ada log</td></tr>';
                    }
                }
            } else {
                showToast(data.error || 'Gagal menghapus log', 'danger');
            }
        })
        .catch(function(err) {
            console.error('[LOGS] Delete error:', err);
            showToast('Error: ' + err.message, 'danger');
        })
        .finally(function() {
            // Restore button state
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedLogsCount">0</span>)';
                deleteBtn.style.display = 'none';
            }
        });
    };

    // Edit Data Functions (Admin only)
    <?php if ($_SESSION['role'] === 'admin'): ?>
    window.editData = function(id) {
        console.log('[EDIT] Opening edit modal for ID:', id);
        
        // Show loading in modal
        const modal = document.getElementById('editDataModal');
        if (!modal) {
            console.error('[EDIT] Edit modal not found');
            return;
        }
        
        // Reset form
        document.getElementById('editDataForm').reset();
        
        // Fetch record data
        fetch(`<?= BASE_URL ?>/curahHujan/getRecord/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const record = data.data;
                    document.getElementById('editDataId').value = record.id;
                    document.getElementById('editTanggal').value = record.tanggal;
                    document.getElementById('editLokasi').value = record.lokasi || 'Jember';
                    document.getElementById('editCurahHujan').value = record.curah_hujan;
                    document.getElementById('editSumberData').value = record.sumber_data || 'Manual';
                    document.getElementById('editKeterangan').value = record.keterangan || '';
                    
                    // Show modal using jQuery (Bootstrap 4)
                    $('#editDataModal').modal('show');
                } else {
                    showToast(data.error || 'Gagal mengambil data', 'danger');
                }
            })
            .catch(err => {
                console.error('[EDIT] Fetch error:', err);
                showToast('Error: ' + err.message, 'danger');
            });
    };
    
    // Handle edit form submission
    document.getElementById('editDataForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const id = document.getElementById('editDataId').value;
        if (!id) {
            showToast('ID data tidak valid', 'danger');
            return;
        }
        
        const saveBtn = document.getElementById('btnSaveEdit');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        
        const formData = new FormData(this);
        
        fetch(`<?= BASE_URL ?>/curahHujan/update/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Data berhasil diperbarui', 'success');
                $('#editDataModal').modal('hide');
                loadData();
                loadCharts();
            } else {
                showToast(data.error || 'Gagal memperbarui data', 'danger');
            }
        })
        .catch(err => {
            console.error('[EDIT] Save error:', err);
            showToast('Error: ' + err.message, 'danger');
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        });
    });
    <?php endif; ?>

    // Delete single data record (Admin only)
    <?php if ($_SESSION['role'] === 'admin'): ?>
    window.deleteData = function(id) {
        if (!confirm('Yakin ingin menghapus data ini?')) return;
        
        console.log('[DELETE] Deleting record ID:', id);
        
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        
        fetch(`<?= BASE_URL ?>/curahHujan/delete/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Data berhasil dihapus', 'success');
                loadData();
                loadCharts();
            } else {
                showToast(data.error || 'Gagal menghapus data', 'danger');
            }
        })
        .catch(err => {
            console.error('[DELETE] Error:', err);
            showToast('Error: ' + err.message, 'danger');
        });
    };
    <?php endif; ?>

    // Toast notification helper
    function showToast(message, type = 'info') {
        // Remove existing toast if any
        const existingToast = document.querySelector('.curah-hujan-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} curah-hujan-toast position-fixed shadow`;
        toast.style.cssText = 'top: 80px; right: 20px; z-index: 1060; min-width: 280px; max-width: 350px;';
        toast.innerHTML = `
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'} mr-2"></i>
            ${message}
        `;
        document.body.appendChild(toast);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 4000);
    }
    
    // Make showToast globally available
    window.showToast = showToast;

    function initCharts() {
        const ctxRainfall = document.getElementById('rainfallChart').getContext('2d');
        rainfallChart = new Chart(ctxRainfall, {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Curah Hujan (mm)'
                        }
                    }
                }
            }
        });

        const ctxDist = document.getElementById('distributionChart').getContext('2d');
        distributionChart = new Chart(ctxDist, {
            type: 'bar',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        loadCharts();
    }

    function loadCharts() {
        const year = document.getElementById('filterYear').value;
        const type = document.getElementById('chartType').value;

        fetch(`<?= BASE_URL ?>/curahHujan/getChartData?type=${type}&year=${year}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    rainfallChart.data.labels = data.labels;
                    rainfallChart.data.datasets = data.datasets;
                    rainfallChart.update();

                    // Update distribution chart with first dataset
                    if (data.datasets.length > 0) {
                        distributionChart.data.labels = data.labels;
                        distributionChart.data.datasets = [{
                            label: 'Curah Hujan (mm)',
                            data: data.datasets[0].data,
                            backgroundColor: data.datasets[0].data.map(v => 
                                v > 50 ? 'rgba(220, 53, 69, 0.7)' : 
                                v > 20 ? 'rgba(255, 193, 7, 0.7)' : 
                                v > 0 ? 'rgba(23, 162, 184, 0.7)' : 
                                'rgba(108, 117, 125, 0.7)'
                            )
                        }];
                        distributionChart.update();
                    }
                }
            })
            .catch(err => console.error('Chart error:', err));
    }

    // Cache helper functions (dataCache is declared at the top of initCurahHujan)
    function getCacheKey() {
        const year = document.getElementById('filterYear').value;
        const month = document.getElementById('filterMonth').value;
        const dataSource = document.getElementById('filterDataSource')?.value || 'bmkg';
        return `${year}-${month}-${dataSource}-${perPage}-${currentPage}`;
    }
    
    function clearCache() {
        dataCache.clear();
    }
    
    function loadData(forceRefresh = false) {
        const year = document.getElementById('filterYear').value;
        const month = document.getElementById('filterMonth').value;
        const dataSource = document.getElementById('filterDataSource')?.value || 'bmkg';
        const loadingOverlay = document.getElementById('tableLoadingOverlay');
        const filterBtn = document.querySelector('#filterForm button[type="submit"]');
        const filterBtnOriginalHtml = filterBtn ? filterBtn.innerHTML : '';
        
        // Check cache first (unless force refresh)
        const cacheKey = getCacheKey();
        if (!forceRefresh && dataCache.has(cacheKey)) {
            const cached = dataCache.get(cacheKey);
            if (Date.now() - cached.timestamp < CACHE_TTL) {
                console.log('Using cached data for:', cacheKey);
                totalRecords = cached.total;
                updateTable(cached.data);
                updateStatistics(cached.statistics);
                updatePagination(cached.total);
                updatePaginationInfo(cached.data.length, cached.total);
                return;
            } else {
                // Cache expired, remove it
                dataCache.delete(cacheKey);
            }
        }
        
        // Show loading overlay and button state
        if (loadingOverlay) loadingOverlay.style.display = 'flex';
        if (filterBtn) {
            filterBtn.disabled = true;
            filterBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }
        
        // Build URL - handle empty year (Semua Tahun) and add data source
        let url = `<?= BASE_URL ?>/curahHujan/getData?limit=${perPage}&offset=${(currentPage-1)*perPage}`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        if (dataSource) url += `&data_source=${dataSource}`; // NEW: Add data source parameter
        
        // Setup timeout with AbortController
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            controller.abort();
            showToast('Waktu habis! Proses memakan waktu terlalu lama (> 30 detik). Silakan coba lagi.', 'warning');
        }, REQUEST_TIMEOUT);

        fetch(url, { signal: controller.signal })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    totalRecords = data.total;
                    updateTable(data.data);
                    updateStatistics(data.statistics);
                    updatePagination(data.total);
                    updatePaginationInfo(data.data.length, data.total);
                    
                    // Cache the result
                    dataCache.set(cacheKey, {
                        data: data.data,
                        total: data.total,
                        statistics: data.statistics,
                        timestamp: Date.now()
                    });
                } else {
                    throw new Error(data.error || 'Gagal memuat data');
                }
            })
            .catch(err => {
                console.error('Data error:', err);
                
                // Handle different error types
                if (err.name === 'AbortError') {
                    showToast('Request dibatalkan karena timeout (30 detik)', 'warning');
                } else {
                    showToast('Gagal memuat data: ' + err.message, 'danger');
                }
                
                // Show error in table
                const tbody = document.getElementById('dataTableBody');
                const isAdmin = <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?>;
                const colspan = isAdmin ? 9 : 7;
                const errorMessage = err.name === 'AbortError' 
                    ? 'Waktu habis. Silakan coba lagi atau gunakan filter yang lebih spesifik.'
                    : 'Gagal memuat data. Silakan coba lagi.';
                tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger"><i class="fas fa-exclamation-circle mr-2"></i>${errorMessage}</td></tr>`;
            })
            .finally(() => {
                clearTimeout(timeoutId);
                // Hide loading overlay and reset button
                if (loadingOverlay) loadingOverlay.style.display = 'none';
                if (filterBtn) {
                    filterBtn.disabled = false;
                    filterBtn.innerHTML = filterBtnOriginalHtml;
                }
            });
    }
    
    function updatePaginationInfo(showingCount, total) {
        const from = total > 0 ? ((currentPage - 1) * perPage) + 1 : 0;
        const to = Math.min(currentPage * perPage, total);
        
        const showingFromEl = document.getElementById('showingFrom');
        const showingToEl = document.getElementById('showingTo');
        const totalRecordsEl = document.getElementById('totalRecords');
        const currentPageEl = document.getElementById('currentPageDisplay');
        const totalPagesEl = document.getElementById('totalPagesDisplay');
        
        if (showingFromEl) showingFromEl.textContent = from;
        if (showingToEl) showingToEl.textContent = to;
        if (totalRecordsEl) totalRecordsEl.textContent = total;
        
        const totalPages = perPage >= 999999 ? 1 : Math.ceil(total / perPage);
        if (currentPageEl) currentPageEl.textContent = currentPage;
        if (totalPagesEl) totalPagesEl.textContent = totalPages || 1;
    }

    function updateTable(rows) {
        const tbody = document.getElementById('dataTableBody');
        const isAdmin = <?= $_SESSION['role'] === 'admin' ? 'true' : 'false' ?>;
        const colspan = isAdmin ? 9 : 7; // Updated for new columns
        const selectAll = document.getElementById('selectAllData');
        const deleteBtn = document.getElementById('btnDeleteSelectedData');
        
        // Indonesian month names
        const namaBulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted">Tidak ada data</td></tr>`;
            // Reset select all state when no data
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            if (deleteBtn) {
                deleteBtn.style.display = 'none';
            }
            return;
        }

        tbody.innerHTML = rows.map((row, index) => {
            const badgeClass = row.curah_hujan > 50 ? 'danger' : row.curah_hujan > 20 ? 'warning' : row.curah_hujan > 0 ? 'info' : 'secondary';
            const date = new Date(row.tanggal);
            const formattedDate = date.toLocaleDateString('id-ID');
            const bulan = namaBulan[date.getMonth()];
            const tahun = date.getFullYear();
            
            // Determine source badge
            let sourceBadge = '';
            if (row.sumber_data.includes('NASA')) {
                sourceBadge = `<span class="badge badge-primary"><i class="fas fa-satellite"></i> ${row.sumber_data}</span>`;
            } else if (row.sumber_data.includes('BMKG')) {
                sourceBadge = `<span class="badge badge-success"><i class="fas fa-check-circle"></i> ${row.sumber_data}</span>`;
            } else if (row.sumber_data.includes('Simulasi')) {
                sourceBadge = `<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> ${row.sumber_data}</span>`;
            } else {
                sourceBadge = `<span class="badge badge-secondary">${row.sumber_data}</span>`;
            }
            
            return `<tr>
                ${isAdmin ? `<td><label class="sr-only" for="data-checkbox-${row.id}">Pilih data ${row.id}</label><input type="checkbox" class="data-checkbox" id="data-checkbox-${row.id}" name="data-checkbox-${row.id}" data-id="${row.id}" aria-label="Pilih data tanggal ${formattedDate}"></td>` : ''}
                <td>${formattedDate}</td>
                <td>${bulan}</td>
                <td>${tahun}</td>
                <td>${row.lokasi}</td>
                <td><span class="badge badge-${badgeClass}">${parseFloat(row.curah_hujan).toFixed(2)} mm</span></td>
                <td>${sourceBadge}</td>
                <td>${row.keterangan || '-'}</td>
                ${isAdmin ? `<td>
                    <button class="btn btn-sm btn-primary btn-edit no-touch-feedback mr-1" onclick="editData(${row.id})" aria-label="Edit data tanggal ${formattedDate}" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-delete no-touch-feedback" onclick="deleteData(${row.id})" aria-label="Hapus data tanggal ${formattedDate}" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>` : ''}
            </tr>`;
        }).join('');
        
        // Reset select all checkbox and update count
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        if (deleteBtn) {
            deleteBtn.style.display = 'none';
        }
        // Update selection count (should be 0 after refresh)
        if (typeof window.updateDataSelectionCount === 'function') {
            window.updateDataSelectionCount();
        }
        if (typeof window.syncDataSelectAllState === 'function') {
            window.syncDataSelectAllState();
        }
    }

    function updateStatistics(stats) {
        document.getElementById('statAverage').textContent = parseFloat(stats.rata_rata || 0).toFixed(2) + ' mm';
        document.getElementById('statMax').textContent = parseFloat(stats.maksimum || 0).toFixed(2) + ' mm';
        document.getElementById('statRainyDays').textContent = (stats.hari_hujan || 0) + ' hari';
        document.getElementById('statTotal').textContent = (stats.total_records || 0) + ' record';
    }

    function updatePagination(total) {
        const totalPages = perPage >= 999999 ? 1 : Math.ceil(total / perPage);
        const pagination = document.getElementById('pagination');
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let html = '';
        
        // Previous button
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;" aria-label="Previous" ${currentPage === 1 ? 'tabindex="-1"' : ''}>
                <i class="fas fa-chevron-left"></i> <span class="d-none d-sm-inline">Prev</span>
            </a>
        </li>`;
        
        // Page numbers with ellipsis for many pages
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // First page
        if (startPage > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(1); return false;">1</a>
            </li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        // Visible pages
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
            </li>`;
        }
        
        // Last page
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${totalPages}); return false;">${totalPages}</a>
            </li>`;
        }
        
        // Next button
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;" aria-label="Next" ${currentPage === totalPages ? 'tabindex="-1"' : ''}>
                <span class="d-none d-sm-inline">Next</span> <i class="fas fa-chevron-right"></i>
            </a>
        </li>`;
        
        pagination.innerHTML = html;
    }

    window.goToPage = function(page) {
        currentPage = page;
        loadData();
    };

    window.deleteData = function(id) {
        if (!confirm('Yakin ingin menghapus data ini?')) return;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        fetch(`<?= BASE_URL ?>/curahHujan/delete/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadData();
                loadCharts();
            } else {
                alert(data.error || 'Gagal menghapus data');
            }
        })
        .catch(err => alert('Error: ' + err.message));
    };



    window.refreshLogs = function() {
        const btn = document.getElementById('btnRefreshLogs');
        const icon = btn.querySelector('i');
        const originalText = btn.innerHTML;
        const note = document.getElementById('logsLastUpdated');
        
        // UI Loading State
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        
        fetch('<?= BASE_URL ?>/curahHujan/getLogs?limit=5')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.querySelector('#logsTable tbody');
                    const rows = data.data.map(log => {
                        const date = new Date(log.created_at);
                        // Simple formatting
                        const dateStr = date.getDate().toString().padStart(2, '0') + '/' + 
                                      (date.getMonth() + 1).toString().padStart(2, '0') + '/' + 
                                      date.getFullYear() + ' ' + 
                                      date.getHours().toString().padStart(2, '0') + ':' + 
                                      date.getMinutes().toString().padStart(2, '0');
                                      
                        const statusBadge = log.status === 'success' ? 'success' : 
                                          (log.status === 'partial' ? 'warning' : 'danger');
                                          
                        const statusText = log.status.charAt(0).toUpperCase() + log.status.slice(1);
                        
                        return `<tr>
                            <td><label class="sr-only" for="log-checkbox-${log.id}">Pilih log ${log.id}</label><input type="checkbox" class="log-checkbox" id="log-checkbox-${log.id}" name="log-checkbox-${log.id}" data-id="${log.id}" aria-label="Pilih log tanggal ${dateStr}"></td>
                            <td>${dateStr}</td>
                            <td>${escapeHtml(log.action)}</td>
                            <td>
                                <span class="badge badge-${statusBadge}">
                                    ${statusText}
                                </span>
                            </td>
                            <td>${escapeHtml(log.message)}</td>
                            <td>${log.records_success}/${log.records_processed}</td>
                            <td>
                                <button class="btn btn-sm btn-danger btn-delete-log" onclick="deleteLog(${log.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    }).join('');
                    
                    tbody.innerHTML = rows;
                    
                    // Update timestamp
                    const now = new Date();
                    note.textContent = 'Diperbarui: ' + now.getHours().toString().padStart(2,'0') + ':' + 
                                     now.getMinutes().toString().padStart(2,'0');
                    note.style.display = 'inline';
                    
                    // Re-initialize checkbox listeners?
                    // The delegation in initCurahHujan (document-level) handles this automatically!
                    if (typeof window.updateLogsSelectionUI === 'function') {
                        window.updateLogsSelectionUI();
                    }
                    
                    showToast('Log aktivitas berhasil diperbarui', 'success');
                } else {
                    showToast('Gagal memuat log: ' + data.error, 'danger');
                }
            })
            .catch(err => {
                console.error('Log refresh error:', err);
                showToast('Error: ' + err.message, 'danger');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    };
    
    // Helper to escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.deleteLog = function(id) {
        if (!confirm('Yakin ingin menghapus log aktivitas ini?')) return;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        // Find and remove the row immediately for instant feedback
        const row = document.querySelector(`#log-checkbox-${id}`)?.closest('tr');
        
        fetch(`<?= BASE_URL ?>/curahHujan/deleteLog/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remove the row from DOM
                if (row) {
                    row.remove();
                }
                
                // Update selection UI if the function exists
                if (typeof window.updateLogsSelectionUI === 'function') {
                    window.updateLogsSelectionUI();
                }
                
                // Show success message
                showToast('Log aktivitas berhasil dihapus', 'success');
                
                // Refresh logs to ensure consistency
                if (typeof refreshLogs === 'function') {
                    setTimeout(() => refreshLogs(), 500);
                }
            } else {
                // Restore row if deletion failed
                if (row && row.parentNode === null) {
                    location.reload(); // Fallback to reload if we can't restore
                }
                showToast(data.error || 'Gagal menghapus log', 'danger');
            }
        })
        .catch(err => {
            // Restore row on error
            if (row && row.parentNode === null) {
                location.reload(); // Fallback to reload on error
            }
            showToast('Error: ' + err.message, 'danger');
        });
    };

    // ================================================================
    // SCRAPER MODE TOGGLE AND YEARLY SCRAPING
    // ================================================================
    
    let scraperCancelled = false;
    const bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    // Mode toggle handler
    const modeMonthly = document.getElementById('modeMonthly');
    const modeYearly = document.getElementById('modeYearly');
    const monthSelectGroup = document.getElementById('monthSelectGroup');
    const yearlyProgressGroup = document.getElementById('yearlyProgressGroup');
    const btnCancelScraper = document.getElementById('btnCancelScraper');
    
    if (modeMonthly && modeYearly) {
        modeMonthly.addEventListener('change', function() {
            if (this.checked) {
                monthSelectGroup.style.display = 'block';
                yearlyProgressGroup.style.display = 'none';
                document.getElementById('scraperBtnText').textContent = 'Jalankan Scraper';
            }
        });
        
        modeYearly.addEventListener('change', function() {
            if (this.checked) {
                monthSelectGroup.style.display = 'none';
                yearlyProgressGroup.style.display = 'block';
                document.getElementById('scraperBtnText').textContent = 'Ambil Data Tahunan';
                resetYearlyProgress();
            }
        });
    }
    
    // Cancel button handler
    if (btnCancelScraper) {
        btnCancelScraper.addEventListener('click', function() {
            scraperCancelled = true;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membatalkan...';
            showToast('Proses dibatalkan oleh pengguna', 'warning');
        });
    }
    
    function resetYearlyProgress() {
        const progressBar = document.getElementById('yearlyProgressBar');
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        
        for (let i = 1; i <= 12; i++) {
            const badge = document.querySelector(`#monthStatus${i} span`);
            if (badge) {
                badge.className = 'badge badge-secondary';
            }
        }
        
        document.getElementById('yearlyStatusText').textContent = 'Siap mengambil data untuk 12 bulan...';
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
        progressBar.style.width = percentage + '%';
        progressBar.textContent = percentage + '%';
        
        const statusText = document.getElementById('yearlyStatusText');
        if (currentMonth) {
            statusText.textContent = `Mengambil data ${bulanNames[currentMonth - 1]}... (${completed}/${total})`;
        }
    }

    function runScraper() {
        const btn = document.getElementById('btnRunScraper');
        const resultDiv = document.getElementById('scraperResult');
        const isYearlyMode = document.getElementById('modeYearly')?.checked;
        
        if (isYearlyMode) {
            runYearlyScraper();
        } else {
            runMonthlyScraper();
        }
    }
    
    function runMonthlyScraper() {
        const btn = document.getElementById('btnRunScraper');
        const resultDiv = document.getElementById('scraperResult');
        const btnIcon = document.getElementById('scraperBtnIcon');
        const btnText = document.getElementById('scraperBtnText');
        
        btn.disabled = true;
        btnIcon.className = 'fas fa-spinner fa-spin';
        btnText.textContent = 'Memproses...';
        resultDiv.style.display = 'none';

        const formData = new FormData(document.getElementById('scraperForm'));

        fetch('<?= BASE_URL ?>/curahHujan/runScraper', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            resultDiv.style.display = 'block';
            if (data.success) {
                resultDiv.className = 'alert alert-success';
                resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Berhasil!</strong><br>
                    Sumber: ${data.source}<br>
                    Record berhasil: ${data.records_success}<br>
                    Waktu: ${data.execution_time}s`;
                loadData();
                loadCharts();
                showToast('Data berhasil diperbarui', 'success');
            } else {
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>${data.error || data.message}`;
            }
        })
        .catch(err => {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Error:</strong> ${err.message}`;
        })
        .finally(() => {
            btn.disabled = false;
            btnIcon.className = 'fas fa-play';
            btnText.textContent = 'Jalankan Scraper';
        });
    }
    
    async function runYearlyScraper() {
        const btn = document.getElementById('btnRunScraper');
        const resultDiv = document.getElementById('scraperResult');
        const btnIcon = document.getElementById('scraperBtnIcon');
        const btnText = document.getElementById('scraperBtnText');
        const cancelBtn = document.getElementById('btnCancelScraper');
        const year = document.getElementById('scraperYear').value;
        
        // Helper function to reset button state
        function resetButtonState() {
            const b = document.getElementById('btnRunScraper');
            const c = document.getElementById('btnCancelScraper');
            if (b) {
                b.disabled = false;
                b.innerHTML = '<i class="fas fa-play" id="scraperBtnIcon"></i> <span id="scraperBtnText">Ambil Data Tahunan</span>';
            }
            if (c) c.style.display = 'none';
            console.log('Button state reset completed');
        }
        
        try {
            scraperCancelled = false;
            
            // Null-safe element access
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
            statusText.textContent = 'Memulai pengambilan data tahunan...';
            
            for (let month = 1; month <= 12; month++) {
                if (scraperCancelled) {
                    statusText.textContent = `Proses dibatalkan pada bulan ${bulanNames[month - 1]}`;
                    for (let i = month; i <= 12; i++) {
                        updateMonthStatus(i, 'skipped');
                    }
                    break;
                }
                
                updateMonthStatus(month, 'loading');
                updateYearlyProgress(month - 1, 12, month);
                
                try {
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('year', year);
                    formData.append('month', month);
                    
                    // Add timeout with AbortController (60 seconds for complex API requests)
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000);
                    
                    const response = await fetch('<?= BASE_URL ?>/curahHujan/runScraper', {
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
                        // Handle "no data available" response (future months)
                        updateMonthStatus(month, 'nodata');
                        results.noData.push(bulanNames[month - 1]);
                    } else {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNames[month - 1]}: ${data.error || data.message || 'Gagal memproses data API/Database'}`);
                    }
                    
                } catch (err) {
                    if (err.name === 'AbortError') {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNames[month - 1]}: Request timeout (60s) - Batas waktu permintaan terlampaui`);
                    } else {
                        updateMonthStatus(month, 'failed');
                        results.failed++;
                        results.errors.push(`${bulanNames[month - 1]}: ${err.message}`);
                    }
                }
                
                updateYearlyProgress(month, 12, month);
                
                // Small delay between requests to avoid overwhelming the server
                if (month < 12 && !scraperCancelled) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }
            
            // Show final results
            resultDiv.style.display = 'block';
            const progressBar = document.getElementById('yearlyProgressBar');
            
            // Build noData message if applicable
            const noDataMsg = results.noData.length > 0 
                ? `<br><span class="text-info"><i class="fas fa-info-circle"></i> Data belum tersedia untuk: ${results.noData.join(', ')}</span>` 
                : '';
            
            if (scraperCancelled) {
                resultDiv.className = 'alert alert-warning';
                resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Dibatalkan</strong><br>
                    Bulan berhasil: ${results.success}<br>
                    Bulan gagal: ${results.failed}<br>
                    Total record: ${results.totalRecords}${noDataMsg}`;
                progressBar.className = 'progress-bar bg-warning';
            } else if (results.failed === 0 && results.noData.length === 0) {
                resultDiv.className = 'alert alert-success';
                resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Berhasil!</strong><br>
                    Semua 12 bulan berhasil diproses<br>
                    Total record: ${results.totalRecords}`;
                progressBar.className = 'progress-bar bg-success';
                showToast('Data tahunan berhasil diambil!', 'success');
            } else if (results.failed === 0 && results.noData.length > 0) {
                // All processed successfully, but some months have no data yet
                resultDiv.className = 'alert alert-info';
                resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Proses Selesai</strong><br>
                    Bulan dengan data: ${results.success}<br>
                    Total record: ${results.totalRecords}${noDataMsg}`;
                progressBar.className = 'progress-bar bg-info';
                showToast(`Data tahunan diambil! ${results.noData.length} bulan belum tersedia`, 'info');
            } else if (results.success > 0) {
                resultDiv.className = 'alert alert-warning';
                resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Sebagian Berhasil</strong><br>
                    Bulan berhasil: ${results.success}<br>
                    Bulan gagal: ${results.failed}<br>
                    Total record: ${results.totalRecords}${noDataMsg}<br>
                    <small class="text-muted">${results.errors.slice(0, 3).join(', ')}</small>`;
                progressBar.className = 'progress-bar bg-warning';
            } else {
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>
                    Tidak ada data yang berhasil diambil<br>
                    <small class="text-muted">${results.errors.slice(0, 3).join(', ')}</small>${noDataMsg}`;
                progressBar.className = 'progress-bar bg-danger';
            }
            
            statusText.textContent = scraperCancelled ? 'Proses dibatalkan' : 'Selesai!';
            
        } catch (err) {
            console.error('Yearly scraper error:', err);
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Error!</strong><br>${err.message}`;
        } finally {
            // ALWAYS reset button state
            resetButtonState();
            
            // Reload data (in background, don't wait)
            try {
                // Sync filter year with scraper year so loadData shows correct data
                const scraperYear = document.getElementById('scraperYear').value;
                const filterYear = document.getElementById('filterYear');
                if (filterYear && scraperYear) {
                    // Add year to filter dropdown if not exists
                    let yearExists = false;
                    for (let i = 0; i < filterYear.options.length; i++) {
                        if (filterYear.options[i].value === scraperYear) {
                            yearExists = true;
                            break;
                        }
                    }
                    if (!yearExists) {
                        const option = document.createElement('option');
                        option.value = scraperYear;
                        option.textContent = scraperYear;
                        filterYear.appendChild(option);
                    }
                    filterYear.value = scraperYear;
                }
                loadData();
                loadCharts();
            } catch (e) {
                console.error('Error reloading data:', e);
            }
        }
    }
    } // End of initCurahHujan function
    
    // Execute initialization based on document ready state
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCurahHujan);
    } else {
        // DOM already loaded, execute immediately
        initCurahHujan();
    }
})();
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }

/* ========================================
   LOADING OVERLAY STYLES
   ======================================== */

.table-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 0.35rem;
}

.loading-spinner {
    text-align: center;
    color: #4e73df;
}

.loading-spinner i {
    color: #4e73df;
}

.loading-spinner p {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Make card body position relative for overlay */
.card-body {
    position: relative;
}

/* ========================================
   PAGINATION CONTROLS STYLES
   ======================================== */

.pagination-controls {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e3e6f0;
    margin-bottom: 1rem;
}

.pagination-controls label {
    font-weight: 500;
    color: #5a5c69;
}

#perPageSelect {
    min-width: 70px;
    border-radius: 0.25rem;
    border: 1px solid #d1d3e2;
}

#perPageSelect:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

/* Pagination styling */
.pagination {
    margin-bottom: 0;
    gap: 0.25rem;
}

.pagination .page-item .page-link {
    border-radius: 0.25rem;
    margin: 0 1px;
    padding: 0.4rem 0.75rem;
    color: #4e73df;
    border: 1px solid #d1d3e2;
    transition: all 0.15s ease-in-out;
}

.pagination .page-item.active .page-link {
    background-color: #4e73df;
    border-color: #4e73df;
    color: white;
    font-weight: 600;
}

.pagination .page-item.disabled .page-link {
    color: #858796;
    background-color: #f8f9fc;
    border-color: #e3e6f0;
    cursor: not-allowed;
}

.pagination .page-item:not(.disabled):not(.active) .page-link:hover {
    background-color: #eaecf4;
    border-color: #4e73df;
    color: #2e59d9;
}

/* Responsive pagination */
@media (max-width: 576px) {
    .pagination-controls {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.5rem;
    }
    
    .pagination-controls > div:first-child {
        width: 100%;
        justify-content: space-between;
    }
    
    .pagination .page-link {
        padding: 0.35rem 0.5rem;
        font-size: 0.85rem;
    }
}

/* ========================================
   COMPLETE ANIMATION/TRANSFORM REMOVAL - CURAH HUJAN PAGE
   Overrides responsive.css global :active scale effects
   ======================================== */

/* ===== GLOBAL OVERRIDES FOR :ACTIVE AND :HOVER STATES ===== */

/* Remove scale effect from ALL cards on this page */
.card,
.card:hover,
.card:active,
.card:focus {
    transform: none !important;
    transition: none !important;
    animation: none !important;
}

/* Remove scale effect from ALL buttons on this page */
.btn,
.btn:hover,
.btn:active,
.btn:focus,
.btn-primary,
.btn-primary:hover,
.btn-primary:active,
.btn-secondary,
.btn-secondary:hover,
.btn-secondary:active,
.btn-success,
.btn-success:hover,
.btn-success:active,
.btn-danger,
.btn-danger:hover,
.btn-danger:active,
.btn-warning,
.btn-warning:hover,
.btn-warning:active,
.btn-info,
.btn-info:hover,
.btn-info:active,
.btn-sm,
.btn-sm:hover,
.btn-sm:active {
    transform: none !important;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out !important;
}

/* Remove scale effect from navigation links */
.nav-link,
.nav-link:hover,
.nav-link:active,
.nav-link:focus {
    transform: none !important;
    transition: none !important;
}

/* ===== TABLE-SPECIFIC OVERRIDES ===== */

/* Disable ALL transitions and transforms on table elements */
#dataTable,
#logsTable,
#dataTableBody,
#dataTable tr,
#logsTable tr,
#dataTable tr:hover,
#logsTable tr:hover,
#dataTable tr:active,
#logsTable tr:active,
#dataTable td,
#logsTable td,
#dataTable th,
#logsTable th,
.table-responsive,
.table-responsive tr,
.table-responsive tr:hover,
.table-responsive tr:active {
    transform: none !important;
    transition: none !important;
    animation: none !important;
}

/* Delete buttons */
.btn-delete,
.btn-delete:hover,
.btn-delete:active,
.btn-delete-log,
.btn-delete-log:hover,
.btn-delete-log:active,
#btnDeleteSelectedData,
#btnDeleteSelectedData:hover,
#btnDeleteSelectedData:active,
#btnDeleteSelectedLogs,
#btnDeleteSelectedLogs:hover,
#btnDeleteSelectedLogs:active {
    transform: none !important;
    transition: none !important;
    animation: none !important;
}

/* ===== CHECKBOX OVERRIDES ===== */

.data-checkbox,
.log-checkbox,
#selectAllData,
#selectAllLogs,
input[type="checkbox"],
input[type="checkbox"]:hover,
input[type="checkbox"]:active {
    transform: none !important;
    transition: none !important;
    animation: none !important;
}

/* ===== INSTALL BUTTON ===== */

.install-app-btn,
.install-app-btn:hover,
.install-app-btn:active {
    animation: none !important;
    transform: none !important;
}

/* ===== REMOVE STATIC SHADOWS (OPTIONAL - uncomment if needed) ===== */
/* 
.card.shadow,
.card.shadow-sm {
    box-shadow: none !important;
}
*/

@media (max-width: 768px) {
    .chart-area, .chart-bar {
        height: 250px !important;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group .btn {
        margin-bottom: 0.25rem;
    }
    
    /* ===== CURAH HUJAN PAGE HEADER MOBILE FIX ===== */
    /* Fix page header crowding on mobile */
    .container-fluid > .d-flex.justify-content-between.align-items-center.mb-4 {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem;
    }
    
    .container-fluid > .d-flex.justify-content-between.align-items-center.mb-4 > .btn-group {
        width: 100%;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .container-fluid > .d-flex.justify-content-between.align-items-center.mb-4 > .btn-group .btn {
        flex: 1 1 auto;
        min-width: 100px;
        margin-bottom: 0;
    }
    
    /* Page title centered on mobile */
    .container-fluid > .d-flex.justify-content-between > div:first-child h1 {
        font-size: 1.25rem;
    }
    
    .container-fluid > .d-flex.justify-content-between > div:first-child p {
        font-size: 0.85rem;
    }
    
    /* Demo mode alert stacking */
    #demoModeAlert {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    #demoModeAlert .custom-control {
        margin-top: 0.5rem;
    }
    
    /* Filter form improvements */
    #filterForm .col-md-3,
    #filterForm .col-md-2 {
        margin-bottom: 0.75rem;
    }
    
    #filterForm .btn {
        width: 100%;
    }
}

/* Tablet breakpoint improvements */
@media (min-width: 769px) and (max-width: 991px) {
    /* Two column layout for header buttons */
    .container-fluid > .d-flex.justify-content-between.align-items-center.mb-4 > .btn-group {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .container-fluid > .d-flex.justify-content-between.align-items-center.mb-4 > .btn-group .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
}

/* ========================================
   TOAST NOTIFICATION ANIMATIONS
   ======================================== */

/* Toast notification styling */
.curah-hujan-toast {
    border-radius: 8px;
    border: none;
    font-size: 0.9rem;
}

.curah-hujan-toast .close {
    opacity: 0.7;
}

.curah-hujan-toast .close:hover {
    opacity: 1;
}

/* ========================================
   ROW SELECTION HIGHLIGHTING
   ======================================== */

/* Selected row highlight */
#dataTable tr.table-primary,
#logsTable tr.table-primary {
    background-color: rgba(0, 123, 255, 0.15) !important;
    border-left: 3px solid #007bff;
}

#dataTable tr.table-primary td,
#logsTable tr.table-primary td {
    background-color: transparent !important;
}

/* Checkbox indeterminate state styling */
#selectAllData:indeterminate,
#selectAllLogs:indeterminate {
    background-color: #6c757d;
}

/* ========================================
   ACCESSIBILITY UTILITIES
   ======================================== */

/* Screen reader only - for accessibility labels */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* No touch feedback class for elements that shouldn't animate */
.no-touch-feedback {
    transform: none !important;
    transition: none !important;
}
</style>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
