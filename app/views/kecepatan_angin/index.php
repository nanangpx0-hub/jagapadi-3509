<?php 
$pageTitle = $data['page_title'] ?? 'Data Kecepatan Angin';
require_once ROOT_PATH . '/app/views/layouts/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>public/css/hover-disabled.css">
<link rel="stylesheet" href="<?= BASE_URL ?>public/vendor/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>public/vendor/css/responsive.bootstrap4.min.css">

<div class="container-fluid py-4">
    <style>
        /* Additional Wind Specific Styles */
        .wind-direction-arrow {
            display: inline-block;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
    </style>
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-wind text-info"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-muted mb-0">Monitoring kecepatan angin untuk analisis pertanian</p>
        </div>
        <div class="btn-group flex-wrap">
            <a href="<?= BASE_URL ?>/kecepatanAngin/export?year=<?= $data['currentYear'] ?>" class="btn btn-outline-success">
                <i class="fas fa-download"></i> Export CSV
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDataModal">
                <i class="fas fa-plus"></i> Tambah Data
            </button>
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#importExcelModal">
                <i class="fas fa-file-excel"></i> Import Excel
            </button>
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#scraperModal">
                <i class="fas fa-sync"></i> Update Data
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <!-- Source Alert -->
            <?php if(isset($data['lastScrape'])): ?>
            <div class="alert alert-info border-0 shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fas fa-info-circle fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading font-weight-bold mb-1">Status Data Angin</h6>
                        <ul class="mb-0 pl-3 small">
                            <li><strong>Sumber tampilan:</strong> <?= htmlspecialchars(strtoupper($data['currentSource'] ?? 'nasa')) ?></li>
                            <li><strong>Terakhir Diperbarui:</strong> <?= date('d F Y, H:i', strtotime($data['lastScrape']['created_at'])) ?> WIB</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form id="filterForm" class="row g-3 align-items-end" method="GET" action="<?= BASE_URL ?>/kecepatanAngin">
                <div class="col-6 col-md-2">
                    <label class="form-label" for="filterYear">Tahun</label>
                    <select class="form-control" id="filterYear" name="year">
                        <?php 
                        $fixedYears = range(2020, 2026);
                        $allYears = array_unique(array_merge($fixedYears, $data['availableYears'] ?? []));
                        rsort($allYears);
                        foreach ($allYears as $year): ?>
                        <option value="<?= $year ?>" <?= $year == $data['currentYear'] ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="filterMonth">Bulan</label>
                    <select class="form-control" id="filterMonth" name="month">
                        <option value="">Semua Bulan</option>
                        <?php
                        $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        foreach ($months as $i => $m): ?>
                        <option value="<?= $i + 1 ?>" <?= ($data['currentMonth'] ?? '') == ($i + 1) ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="chartType">Tipe Grafik</label>
                    <select class="form-control" id="chartType" name="chartType">
                        <option value="monthly">Bulanan</option>
                        <option value="yearly">Tahunan</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="filterDataSource">Sumber Data</label>
                    <select class="form-control" id="filterDataSource" name="data_source">
                        <option value="nasa" <?= ($data['currentSource'] ?? 'nasa') === 'nasa' ? 'selected' : '' ?>>NASA POWER</option>
                        <option value="openmeteo" <?= ($data['currentSource'] ?? '') === 'openmeteo' ? 'selected' : '' ?>>Open-Meteo</option>
                        <option value="simulation" <?= ($data['currentSource'] ?? '') === 'simulation' ? 'selected' : '' ?>>Simulasi</option>
                        <option value="all" <?= ($data['currentSource'] ?? '') === 'all' ? 'selected' : '' ?>>Semua Sumber</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label d-none d-md-block mb-2" style="visibility:hidden">Filler</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label d-none d-md-block mb-2" style="visibility:hidden">Filler</label>
                    <a href="<?= BASE_URL ?>/kecepatanAngin" class="btn btn-secondary w-100">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-6 col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Rata-rata</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($data['statistics']['rata_rata'] ?? 0, 2) ?> km/h
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wind fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Maksimum</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($data['statistics']['maksimum'] ?? 0, 2) ?> km/h
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tachometer-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 col-md-6 mb-3">
            <div class="card border-left-teal shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hari Berangin</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $data['statistics']['hari_berangin'] ?? 0 ?> hari
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Data</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
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

    <!-- Charts Row — perbandingan lintas tahun -->
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Grafik Kecepatan Angin
                        <small class="text-muted font-weight-normal ml-2">Perbandingan Semua Tahun</small>
                    </h6>
                    <span class="badge badge-info"><?= count($data['allYearsList'] ?? []) ?> tahun</span>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="windChart"></canvas>
                    </div>
                    <?php if (!empty($data['yearlySummaryAll'])): ?>
                    <div class="mt-3 table-responsive">
                        <table class="table table-sm table-bordered mb-0 text-center">
                            <thead class="thead-light"><tr><th>Tahun</th><th>Rata-rata (km/h)</th><th>Maksimum (km/h)</th><th>Jumlah Data</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['yearlySummaryAll'] as $ys): ?>
                                <tr><td><span class="badge badge-primary"><?= htmlspecialchars((string)$ys['tahun']) ?></span></td><td><?= number_format((float)$ys['rata_rata'], 2) ?></td><td><?= number_format((float)($ys['maksimum'] ?? 0), 2) ?></td><td><?= (int)$ys['jumlah_data'] ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-compass"></i> Distribusi Arah
                    </h6>
                    <span class="badge badge-success">Semua Tahun</span>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 320px;">
                        <canvas id="windRoseChart"></canvas>
                    </div>
                    <?php if (!empty($data['windRoseFallback'])): ?>
                    <small class="text-muted d-block text-center mt-2"><i class="fas fa-info-circle"></i> Arah tidak tersedia untuk sumber terpilih — menampilkan semua sumber</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Tabs (Analisis, Rekomendasi, Prediksi) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="rekomendasi-tab" data-toggle="tab" href="#rekomendasiPane" role="tab">
                        <i class="fas fa-spray-can"></i> Rekomendasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="analysis-tab" data-toggle="tab" href="#analysisPane" role="tab">
                        <i class="fas fa-tint"></i> Evapotranspirasi
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="dashboardTabContent">
                <!-- Rekomendasi Tab -->
                <div class="tab-pane fade show active" id="rekomendasiPane" role="tabpanel">
                    <div class="row">
                        <!-- Spray Recommendation -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-success h-100">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-spray-can"></i> Rekomendasi Penyemprotan
                                    </h6>
                                </div>
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-spinner fa-spin fa-3x text-muted" id="sprayIcon"></i>
                                    </div>
                                    <h4 id="sprayStatus" class="font-weight-bold mb-1">Menganalisis...</h4>
                                    <p id="sprayReason" class="text-muted small">Mengambil data kondisi angin terkini</p>
                                    
                                    <div class="row text-center border-top pt-3 mt-3">
                                        <div class="col-6 border-right">
                                            <small class="text-muted d-block">Kecepatan</small>
                                            <strong id="spraySpeedDisplay">- km/h</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Skala Beaufort</small>
                                            <strong id="sprayBeaufortDisplay">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recommendation Details -->
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-warning h-100">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-warning">
                                        <i class="fas fa-clock"></i> Waktu Optimal & Perhatian
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong class="text-xs text-uppercase text-muted">Waktu Tersedia:</strong>
                                        <div id="sprayTimesContent" class="small mt-1">-</div>
                                    </div>
                                    <div id="sprayPrecautions" style="display:none;">
                                        <strong class="text-xs text-uppercase text-muted">Perhatian Khusus:</strong>
                                        <ul id="sprayPrecautionsList" class="small mb-0 pl-3 mt-1 text-danger"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analisis (EVT) Tab -->
                <div class="tab-pane fade" id="analysisPane" role="tabpanel">
                     <div class="row">
                        <div class="col-lg-6 offset-lg-3">
                             <div class="card border-left-info shadow-sm">
                                <div class="card-header py-2 bg-white">
                                    <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-calculator"></i> Kalkulator Penyesuaian Irigasi</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="col-md-6 mb-3">
                                            <label class="small">Suhu (°C)</label>
                                            <input type="number" class="form-control" id="etTemperature" value="28">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="small">Kelembaban (%)</label>
                                            <input type="number" class="form-control" id="etHumidity" value="70">
                                        </div>
                                    </div>
                                    <button class="btn btn-info btn-block btn-sm" id="btnCalculateET">Hitung</button>
                                    
                                    <div id="etResultContainer" class="mt-3 text-center" style="display:none;">
                                        <div class="alert alert-light border">
                                            <h5 class="text-info font-weight-bold" id="etAdjustment">0%</h5>
                                            <small class="text-muted">Penyesuaian Volume Air</small>
                                            <hr>
                                            <p class="small mb-0" id="etRecommendationText">-</p>
                                        </div>
                                    </div>
                                </div>
                             </div>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table — lintas tahun (100 terbaru, semua tahun) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table"></i> Data Kecepatan Angin
                <small class="text-muted font-weight-normal ml-2">Semua Tahun — perbandingan (<?= (int)($data['tableAllYearsTotal'] ?? 0) ?> total, 100 terbaru)</small>
            </h6>
            <span class="badge badge-primary">Semua Tahun</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Bulan</th>
                            <th>Tahun</th>
                            <th>Lokasi</th>
                            <th>Kecepatan (km/h)</th>
                            <th>Arah</th>
                            <th>Validasi</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <th width="100">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tableRows = $data['tableAllYears'] ?? $data['recentData'] ?? [];
                        ?>
                        <?php if (!empty($tableRows)): 
                            $namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        ?>
                        <?php foreach ($tableRows as $row): 
                            $date = strtotime($row['tanggal']);
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', $date) ?></td>
                            <td><?= $namaBulan[date('n', $date) - 1] ?></td>
                            <td><span class="badge badge-primary"><?= date('Y', $date) ?></span></td>
                            <td>Jember</td>
                            <td>
                                <span class="font-weight-bold <?= $row['kecepatan_angin'] > 20 ? 'text-danger' : 'text-dark' ?>">
                                    <?= number_format($row['kecepatan_angin'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <?= number_format($row['arah_angin'] ?? 0, 0) ?>° 
                                <span class="small text-muted">(<?= $row['arah_angin_desc'] ?? '-' ?>)</span>
                            </td>
                            <td>
                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Valid</span>
                            </td>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning" onclick="editData(<?= $row['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteData(<?= $row['id'] ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="<?= $_SESSION['role'] === 'admin' ? '8' : '7' ?>" class="text-center text-muted">Belum ada data</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Data Modal -->
<div class="modal fade" id="addDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Data Kecepatan Angin</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="addDataForm" action="<?= BASE_URL ?>/kecepatanAngin/store" method="POST">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="addTanggal">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="addTanggal" name="tanggal" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="addKecepatan">Kecepatan Angin (km/h) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="addKecepatan" name="kecepatan_angin" step="0.1" min="0" max="200" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="addKecepatanMax">Kecepatan Max (km/h)</label>
                            <input type="number" class="form-control" id="addKecepatanMax" name="kecepatan_max" step="0.1" min="0" max="300">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="addArahAngin">Arah Angin (°)</label>
                            <input type="number" class="form-control" id="addArahAngin" name="arah_angin" min="0" max="360">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="addLokasi">Lokasi</label>
                            <input type="text" class="form-control" id="addLokasi" name="lokasi" value="Jember">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="addKeterangan">Keterangan</label>
                        <textarea class="form-control" id="addKeterangan" name="keterangan" rows="2"></textarea>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-excel"></i> Import Data dari Excel</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Format file yang didukung:</strong> xlsx, xls, csv<br>
                    <strong>Kolom wajib:</strong> tanggal, kecepatan_angin<br>
                    <strong>Kolom opsional:</strong> arah_angin, lokasi, keterangan
                </div>
                
                <form id="importExcelForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    <div class="form-group">
                        <label for="excelFile">Pilih File Excel</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="excelFile" name="excel_file" accept=".xlsx,.xls,.csv" required>
                            <label class="custom-file-label" for="excelFile">Pilih file...</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <a href="<?= BASE_URL ?>/kecepatanAngin/downloadTemplate" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-download"></i> Download Template CSV
                        </a>
                    </div>
                    
                    <!-- Progress -->
                    <div id="importProgress" style="display: none;">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted small">Mengimpor data...</p>
                    </div>
                    
                    <!-- Result -->
                    <div id="importResult" style="display: none;">
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
                <button type="button" class="btn btn-success" id="btnImportExcel">
                    <i class="fas fa-upload"></i> Import Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Data Modal -->
<div class="modal fade" id="editDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Kecepatan Angin</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="editDataForm">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                <input type="hidden" id="editId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editTanggal">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="editTanggal" name="tanggal" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editKecepatan">Kecepatan Angin (km/h) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="editKecepatan" name="kecepatan_angin" step="0.1" min="0" max="200" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editKecepatanMax">Kecepatan Max (km/h)</label>
                            <input type="number" class="form-control" id="editKecepatanMax" name="kecepatan_max" step="0.1" min="0" max="300">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editArahAngin">Arah Angin (°)</label>
                            <input type="number" class="form-control" id="editArahAngin" name="arah_angin" min="0" max="360">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editLokasi">Lokasi</label>
                            <input type="text" class="form-control" id="editLokasi" name="lokasi">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editKeterangan">Keterangan</label>
                        <textarea class="form-control" id="editKeterangan" name="keterangan" rows="2"></textarea>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash"></i> Hapus Data</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus data ini?</p>
                <p class="text-muted small mb-0">Data yang dihapus tidak dapat dikembalikan.</p>
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scraper Modal (Simplified) -->
<!-- Scraper Modal -->
<div class="modal fade" id="scraperModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-sync"></i> Pembaruan Data Kecepatan Angin</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="scraperForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                    
                    <!-- Source Selection -->
                    <div class="form-group">
                        <label for="scraperSource">Sumber Data</label>
                        <select class="form-control" id="scraperSource" name="source">
                            <option value="nasa" selected>NASA POWER API (WS10M / WS2M - Terkoreksi)</option>
                            <option value="openmeteo">Open-Meteo API</option>
                            <option value="simulation">Simulasi (Data Sintetis)</option>
                        </select>
                    </div>

                    <!-- Year Selection -->
                    <div class="form-group">
                        <label for="scraperYear">Tahun</label>
                        <select class="form-control" name="year" id="scraperYear">
                            <?php for ($y = 2020; $y <= 2026; $y++): ?>
                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <small class="form-text text-muted">Pilih tahun untuk data yang akan diambil (2020-2026)</small>
                    </div>
                    
                    <!-- Scraping Mode Selection -->
                    <div class="form-group">
                        <span class="d-block mb-2 font-weight-normal">Mode Pengambilan Data</span>
                        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                            <label class="btn btn-outline-info active flex-fill" id="labelModeMonthly">
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
                    <button type="submit" class="btn btn-info" id="btnRunScraper">
                        <i class="fas fa-play" id="scraperBtnIcon"></i> <span id="scraperBtnText">Jalankan Scraper</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>

<script src="<?= BASE_URL ?>public/vendor/js/dataTables-1.13.6.min.js"></script>
<script src="<?= BASE_URL ?>public/vendor/js/dataTables.bootstrap4.min.js"></script>
<script src="<?= BASE_URL ?>public/vendor/js/dataTables.responsive.min.js"></script>
<script src="<?= BASE_URL ?>public/vendor/js/responsive.bootstrap4.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // --- Wind Chart — perbandingan semua tahun (satu garis per tahun) ---
    const ctxWind = document.getElementById('windChart').getContext('2d');
    const allMonthlyByYear = <?= json_encode($data['allMonthlyByYear'] ?? []) ?>;
    const allYearsList = <?= json_encode($data['allYearsList'] ?? []) ?>;
    const labels = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Ags", "Sep", "Okt", "Nov", "Des"];
    const palette = ['#36b9cc','#1cc88a','#4e73df','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9','#17a673','#36b9cc'];
    let windDatasets = [];
    if (allYearsList.length > 0 && Object.keys(allMonthlyByYear).length > 0) {
        windDatasets = allYearsList.map((year, idx) => {
            const yearData = allMonthlyByYear[year] || {};
            const data = [];
            for (let m = 1; m <= 12; m++) {
                const v = yearData[m] ?? yearData[String(m)] ?? 0;
                data.push(parseFloat(v) || 0);
            }
            const color = palette[idx % palette.length];
            return {
                label: String(year),
                data: data,
                borderColor: color,
                backgroundColor: color + '20',
                pointRadius: 3,
                pointBackgroundColor: color,
                pointBorderColor: color,
                pointHoverRadius: 5,
                pointHitRadius: 10,
                pointBorderWidth: 2,
                fill: false,
                tension: 0.35
            };
        });
    } else {
        // fallback single tahun (kompatibilitas)
        const monthlyData = <?= json_encode($data['monthlyData'] ?? []) ?>;
        const speeds = new Array(12).fill(0);
        monthlyData.forEach(item => {
            if(item.bulan >= 1 && item.bulan <= 12) {
                speeds[item.bulan - 1] = parseFloat(item.rata_rata) || 0;
            }
        });
        windDatasets = [{
            label: 'Kecepatan Rata-rata (km/h)',
            data: speeds,
            borderColor: '#36b9cc',
            backgroundColor: 'rgba(54, 185, 204, 0.05)',
            pointRadius: 3,
            pointBackgroundColor: '#36b9cc',
            pointBorderColor: '#36b9cc',
            pointHoverRadius: 3,
            pointHoverBackgroundColor: '#36b9cc',
            pointHoverBorderColor: '#36b9cc',
            pointHitRadius: 10,
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4
        }];
    }

    new Chart(ctxWind, {
        type: 'line',
        data: {
            labels: labels,
            datasets: windDatasets
        },
        options: {
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
            scales: {
                x: { grid: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 12 } },
                y: { ticks: { maxTicksLimit: 5, padding: 10, callback: function(value) { return value + ' km/h'; } }, grid: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] } }
            },
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 12, padding: 16 } },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleMarginBottom: 10,
                    titleColor: '#6e707e',
                    titleFont: { size: 14 },
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: true,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10
                }
            }
        }
    });

    // --- Wind Rose — agregat semua tahun (real data) ---
    const windRoseRaw = <?= json_encode($data['windRoseAll'] ?? []) ?>;
    const roseLabels = ['U', 'TL', 'T', 'TG', 'S', 'BD', 'B', 'BL'];
    let roseCounts = [0,0,0,0,0,0,0,0];
    let roseAvgs = [0,0,0,0,0,0,0,0];
    if (Array.isArray(windRoseRaw) && windRoseRaw.length === 8) {
        roseCounts = windRoseRaw.map(r => parseInt(r.count) || 0);
        roseAvgs = windRoseRaw.map(r => parseFloat(r.avg_speed) || 0);
    }
    const ctxRose = document.getElementById('windRoseChart').getContext('2d');
    new Chart(ctxRose, {
        type: 'radar',
        data: {
            labels: roseLabels,
            datasets: [{
                label: 'Jumlah Observasi (semua tahun)',
                data: roseCounts,
                backgroundColor: 'rgba(28, 200, 138, 0.25)',
                borderColor: '#1cc88a',
                pointBackgroundColor: '#1cc88a',
                borderWidth: 2
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: { r: { beginAtZero: true, ticks: { display: false } } },
            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function(ctx) {
                            const idx = ctx.dataIndex;
                            return 'Rata-rata: ' + (roseAvgs[idx] || 0) + ' km/h';
                        }
                    }
                },
                legend: { display: true, position: 'bottom' }
            }
        }
    });

    // --- Spray Recommendation Logic (pakai dataset tahun terbaru) ---
    setTimeout(() => {
        const curMonthIdx = new Date().getMonth();
        let lastSpeed = 12.5;
        if (typeof windDatasets !== 'undefined' && windDatasets.length) {
            const latest = windDatasets[windDatasets.length - 1];
            if (latest && latest.data && typeof latest.data[curMonthIdx] !== 'undefined') {
                lastSpeed = parseFloat(latest.data[curMonthIdx]) || 12.5;
            }
        }
        const isSafe = lastSpeed < 15;
        
        document.getElementById('sprayIcon').className = isSafe ? 'fas fa-check-circle fa-3x text-success' : 'fas fa-exclamation-circle fa-3x text-danger';
        document.getElementById('sprayStatus').innerText = isSafe ? 'Aman Dilakukan' : 'Tidak Disarankan';
        document.getElementById('sprayStatus').className = isSafe ? 'font-weight-bold mb-1 text-success' : 'font-weight-bold mb-1 text-danger';
        document.getElementById('sprayReason').innerText = isSafe ? 'Kecepatan angin kondusif (< 15 km/h)' : 'Angin terlalu kencang (> 15 km/h)';
        
        document.getElementById('spraySpeedDisplay').innerText = lastSpeed.toFixed(1) + ' km/h';
        document.getElementById('sprayBeaufortDisplay').innerText = lastSpeed < 2 ? '0' : (lastSpeed < 6 ? '1' : '3'); // Simplified
        document.getElementById('sprayTimesContent').innerText = 'Pagi (06:00 - 09:00) atau Sore (16:00 - 18:00)';
    }, 1000);

    // --- EVT Calculation ---
    document.getElementById('btnCalculateET').addEventListener('click', function() {
        const temp = parseFloat(document.getElementById('etTemperature').value);
        const hum = parseFloat(document.getElementById('etHumidity').value);
        
        // Simple mock calculation logic
        let adjustment = 0;
        if (temp > 30) adjustment += 10;
        if (hum < 60) adjustment += 15;
        
        const container = document.getElementById('etResultContainer');
        container.style.display = 'block';
        document.getElementById('etAdjustment').innerText = '+' + adjustment + '%';
        document.getElementById('etRecommendationText').innerText = adjustment > 15 ? 'Perlu peningkatan irigasi signifikan.' : 'Penyesuaian irigasi minor.';
    });

    // --- File Input Label Update ---
    document.getElementById('excelFile').addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'Pilih file...';
        this.nextElementSibling.innerText = fileName;
    });

    // --- Excel Import Handler ---
    document.getElementById('btnImportExcel').addEventListener('click', function() {
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

        fetch('<?= BASE_URL ?>/kecepatanAngin/importExcel', {
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
                errorList.innerHTML = data.errors.slice(0, 5).map(e => `<li>${e}</li>`).join('');
                if (data.errors.length > 5) {
                    errorList.innerHTML += `<li>...dan ${data.errors.length - 5} error lainnya</li>`;
                }
            } else {
                errorsDiv.style.display = 'none';
            }
            
            this.disabled = false;
            
            // Reload page after 2 seconds if successful
            if (data.success && data.successCount > 0) {
                setTimeout(() => location.reload(), 2000);
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

    // --- Scraper Mode & Form Handler (Bulanan & Tahunan) ---
    const csrfToken = '<?= Security::generateCsrfToken() ?>';
    const bulanNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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
                monthSelectGroup.style.display = 'block';
                yearlyProgressGroup.style.display = 'none';
                if (scraperBtnText) scraperBtnText.textContent = 'Jalankan Scraper';
            }
        });

        modeYearly.addEventListener('change', function() {
            if (this.checked) {
                monthSelectGroup.style.display = 'none';
                yearlyProgressGroup.style.display = 'block';
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
            progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
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
            statusText.textContent = `Mengambil data ${bulanNames[currentMonth - 1]}... (${completed}/${total})`;
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

        fetch('<?= BASE_URL ?>/kecepatanAngin/runScraper', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
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
                        Sumber: ${data.source}<br>
                        Record berhasil: ${data.records_success}<br>
                        Waktu: ${data.execution_time}s`;
                    setTimeout(() => location.reload(), 1500);
                } else if (data.no_data) {
                    resultDiv.className = 'alert alert-info';
                    resultDiv.innerHTML = `<strong><i class="fas fa-info-circle"></i> Info:</strong><br>${data.message || 'Data belum tersedia untuk periode tersebut'}`;
                } else {
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>${data.error || data.message}`;
                }
            }
        })
        .catch(err => {
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Error:</strong> ${err.message}`;
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
            
            for (let month = 1; month <= 12; month++) {
                if (scraperCancelled) {
                    if (statusText) statusText.textContent = `Proses dibatalkan pada bulan ${bulanNames[month - 1]}`;
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
                    formData.append('source', source);
                    
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000);
                    
                    const response = await fetch('<?= BASE_URL ?>/kecepatanAngin/runScraper', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        let detail = '';
                        const textResponse = response.clone();
                        try {
                            const errorPayload = await response.json();
                            detail = errorPayload.message || errorPayload.error || '';
                        } catch (_) {
                            detail = await textResponse.text();
                        }
                        const suffix = detail ? `: ${detail}` : '';
                        throw new Error(`HTTP Error ${response.status} (${response.statusText || 'Server Response Error'})${suffix}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        updateMonthStatus(month, 'success');
                        results.success++;
                        results.totalRecords += data.records_success || 0;
                    } else if (data.no_data) {
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
                
                if (month < 12 && !scraperCancelled) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }
            
            if (resultDiv) {
                resultDiv.style.display = 'block';
                const progressBar = document.getElementById('yearlyProgressBar');
                
                const noDataMsg = results.noData.length > 0 
                    ? `<br><span class="text-info"><i class="fas fa-info-circle"></i> Data belum tersedia untuk: ${results.noData.join(', ')}</span>` 
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
                        Semua 12 bulan berhasil diproses<br>
                        Total record: ${results.totalRecords}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-success';
                    setTimeout(() => location.reload(), 2000);
                } else if (results.failed === 0 && results.noData.length > 0) {
                    resultDiv.className = 'alert alert-info';
                    resultDiv.innerHTML = `<strong><i class="fas fa-check-circle"></i> Proses Selesai</strong><br>
                        Bulan dengan data: ${results.success}<br>
                        Total record: ${results.totalRecords}${noDataMsg}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-info';
                    setTimeout(() => location.reload(), 2500);
                } else if (results.success > 0) {
                    resultDiv.className = 'alert alert-warning';
                    resultDiv.innerHTML = `<strong><i class="fas fa-exclamation-triangle"></i> Sebagian Berhasil</strong><br>
                        Bulan berhasil: ${results.success}<br>
                        Bulan gagal: ${results.failed}<br>
                        Total record: ${results.totalRecords}${noDataMsg}<br>
                        <small class="text-muted">${results.errors.slice(0, 3).join(', ')}</small>`;
                    if (progressBar) progressBar.className = 'progress-bar bg-warning';
                } else {
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal!</strong><br>
                        Tidak ada data yang berhasil diambil<br>
                        <small class="text-muted">${results.errors.slice(0, 3).join(', ')}</small>${noDataMsg}`;
                    if (progressBar) progressBar.className = 'progress-bar bg-danger';
                }
            }
            
            if (statusText) statusText.textContent = scraperCancelled ? 'Proses dibatalkan' : 'Selesai!';
            
        } catch (err) {
            console.error('Yearly scraper error:', err);
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<strong><i class="fas fa-times-circle"></i> Error!</strong><br>${err.message}`;
            }
        } finally {
            resetButtonState();
        }
    }

    // --- Edit Form Handler ---
    document.getElementById('editDataForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('editId').value;
        const formData = new FormData(this);
        
        fetch(`<?= BASE_URL ?>/kecepatanAngin/update/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#editDataModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Gagal memperbarui data'));
            }
        })
        .catch(error => alert('Error: ' + error.message));
    });

    // --- Delete Confirmation Handler ---
    document.getElementById('btnConfirmDelete').addEventListener('click', function() {
        const id = document.getElementById('deleteId').value;
        const formData = new FormData();
        formData.append('csrf_token', '<?= Security::generateCsrfToken() ?>');
        
        fetch(`<?= BASE_URL ?>/kecepatanAngin/delete/${id}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#deleteModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Gagal menghapus data'));
            }
        })
        .catch(error => alert('Error: ' + error.message));
    });

    // --- DataTable perbandingan semua tahun ---
    if (window.jQuery && $.fn.DataTable) {
        const dt = $('#dataTable');
        if (dt.length && !$.fn.DataTable.isDataTable('#dataTable')) {
            dt.DataTable({
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'desc'], [0, 'desc']],
                responsive: true,
                autoWidth: false,
                language: { url: '<?= BASE_URL ?>public/vendor/js/id-1.13.6.json' },
                columnDefs: [
                    { targets: 2, type: 'num' },
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 4 }
                ]
            });
        }
    }
});

// Global functions for edit and delete buttons
function editData(id) {
    fetch(`<?= BASE_URL ?>/kecepatanAngin/getRecord/${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const data = result.data;
                document.getElementById('editId').value = data.id;
                document.getElementById('editTanggal').value = data.tanggal;
                document.getElementById('editKecepatan').value = data.kecepatan_angin;
                document.getElementById('editKecepatanMax').value = data.kecepatan_max || '';
                document.getElementById('editArahAngin').value = data.arah_angin || '';
                document.getElementById('editLokasi').value = data.lokasi || 'Jember';
                document.getElementById('editKeterangan').value = data.keterangan || '';
                $('#editDataModal').modal('show');
            } else {
                alert('Error: ' + (result.error || 'Data tidak ditemukan'));
            }
        })
        .catch(error => alert('Error: ' + error.message));
}

function deleteData(id) {
    document.getElementById('deleteId').value = id;
    $('#deleteModal').modal('show');
}
</script>
