<?php
/**
 * Dashboard Evaluasi Akurasi - Main View
 * Membandingkan estimasi daerah vs rilis BPS
 */

require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line text-primary"></i> Evaluasi Akurasi Data
        </h1>
        <div class="d-flex">
            <button type="button" class="btn btn-primary btn-sm mr-2" data-toggle="modal" data-target="#modalAddData">
                <i class="fas fa-plus"></i> Tambah Data
            </button>
            <button type="button" class="btn btn-info btn-sm mr-2" data-toggle="modal" data-target="#modalImportExcel">
                <i class="fas fa-file-excel"></i> Import Excel
            </button>
            <?php if ($canSnapshot): ?>
            <button type="button" class="btn btn-success btn-sm" id="btnGenerateSnapshot">
                <i class="fas fa-camera"></i> Generate Snapshot
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-secondary btn-sm" disabled title="Snapshot terkunci setelah tanggal 10">
                <i class="fas fa-lock"></i> Snapshot Terkunci
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>Periode:</strong> Bulan <?= date('F Y') ?> | 
        <strong>Tanggal:</strong> <?= $currentDay ?> |
        <strong>Status Snapshot:</strong> 
        <?php if ($canSnapshot): ?>
            <span class="badge badge-success">Dapat di-snapshot (sebelum tgl 10)</span>
        <?php else: ?>
            <span class="badge badge-warning">Terkunci (setelah tgl 10)</span>
        <?php endif; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>

    <!-- Filter Section -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end" id="filterForm">
                <div class="col-md-3 mb-2">
                    <label class="small text-muted">Tahun</label>
                    <select name="tahun" class="form-control form-control-sm" id="filterTahun">
                        <?php 
                        $years = !empty($availableYears) ? $availableYears : [date('Y')];
                        foreach ($years as $y): 
                        ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small text-muted">Bulan</label>
                    <select name="bulan" class="form-control form-control-sm" id="filterBulan">
                        <option value="">Semua Bulan</option>
                        <?php 
                        $namaBulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                                      7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                        foreach ($namaBulan as $key => $label): 
                        ?>
                        <option value="<?= $key ?>" <?= $bulan == $key ? 'selected' : '' ?>><?= $label ?></option>
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
        <!-- Total Records -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Data</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_records'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-database fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sangat Akurat -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sangat Akurat</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['sangat_akurat'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Perlu Perhatian -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Perlu Perhatian</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['perlu_perhatian'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bias Tinggi -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Bias Tinggi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['bias_tinggi'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Perbandingan Estimasi vs Rilis BPS (Tahun <?= $tahun ?>)
                    </h6>
                    <div class="legend-container">
                        <span class="mr-3"><i class="fas fa-square text-primary"></i> Estimasi Daerah</span>
                        <span><i class="fas fa-square text-danger"></i> Rilis BPS</span>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height: 350px;">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-table"></i> Tabel Monitoring Akurasi
            </h6>
            <div>
                <span class="badge badge-success">Sangat Akurat (&lt;5%)</span>
                <span class="badge badge-warning">Perlu Perhatian (5-10%)</span>
                <span class="badge badge-danger">Bias Tinggi (&gt;10%)</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Bulan</th>
                            <th>Wilayah</th>
                            <th class="text-right">Estimasi (Ha)</th>
                            <th class="text-right">Rilis BPS (Ha)</th>
                            <th class="text-right">Deviasi (+/-)</th>
                            <th class="text-right">% Bias</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                Belum ada data evaluasi. Klik "Generate Snapshot" untuk membuat data estimasi.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($data as $row): ?>
                        <?php 
                            $rowClass = '';
                            if ($row['status_akurasi'] === 'Bias Tinggi') {
                                $rowClass = 'table-danger';
                            } elseif ($row['status_akurasi'] === 'Perlu Perhatian') {
                                $rowClass = 'table-warning';
                            } elseif ($row['status_akurasi'] === 'Sangat Akurat') {
                                $rowClass = 'table-success';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>" data-id="<?= $row['id'] ?>">
                            <td>
                                <strong><?= $namaBulan[$row['periode_bulan']] ?? '-' ?></strong>
                                <br><small class="text-muted"><?= $row['periode_tahun'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($row['nama_wilayah'] ?? '-') ?></td>
                            <td class="text-right"><?= number_format($row['luas_estimasi_daerah'], 2, ',', '.') ?></td>
                            <td class="text-right">
                                <?php if ($row['luas_rilis_bps'] !== null): ?>
                                    <?= number_format($row['luas_rilis_bps'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span class="text-muted">Belum diinput</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($row['deviasi_absolut'] !== null): ?>
                                    <span class="<?= $row['deviasi_absolut'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $row['deviasi_absolut'] >= 0 ? '+' : '' ?><?= number_format($row['deviasi_absolut'], 2, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($row['persentase_bias'] !== null): ?>
                                    <strong><?= number_format($row['persentase_bias'], 2, ',', '.') ?>%</strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($row['status_akurasi']): ?>
                                    <?php 
                                    $badgeClass = 'secondary';
                                    if ($row['status_akurasi'] === 'Sangat Akurat') $badgeClass = 'success';
                                    elseif ($row['status_akurasi'] === 'Perlu Perhatian') $badgeClass = 'warning';
                                    elseif ($row['status_akurasi'] === 'Bias Tinggi') $badgeClass = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badgeClass ?>"><?= $row['status_akurasi'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Menunggu Rilis</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                        data-id="<?= $row['id'] ?>"
                                        data-wilayah="<?= htmlspecialchars($row['nama_wilayah']) ?>"
                                        data-wilayah-id="<?= $row['wilayah_id'] ?>"
                                        data-bulan="<?= $row['periode_bulan'] ?>"
                                        data-tahun="<?= $row['periode_tahun'] ?>"
                                        data-estimasi="<?= $row['luas_estimasi_daerah'] ?>"
                                        data-rilis="<?= $row['luas_rilis_bps'] ?>"
                                        data-catatan="<?= htmlspecialchars($row['catatan_analisis'] ?? '') ?>"
                                        title="Edit Data">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete" 
                                        data-id="<?= $row['id'] ?>"
                                        title="Hapus Data">
                                    <i class="fas fa-trash"></i>
                                </button>
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

<!-- Modal Add Data -->
<div class="modal fade" id="modalAddData" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Data Evaluasi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAddData">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Bulan <span class="text-danger">*</span></label>
                            <select class="form-control" name="periode_bulan" required>
                                <option value="">Pilih Bulan</option>
                                <?php foreach ($namaBulan as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Tahun <span class="text-danger">*</span></label>
                            <select class="form-control" name="periode_tahun" required>
                                <?php for($y=date('Y'); $y>=2019; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Wilayah (Kab/Kota) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_wilayah" required placeholder="Contoh: Kab. Jember">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Estimasi Daerah (Ha) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="luas_estimasi_daerah" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Rilis BPS (Ha)</label>
                            <input type="number" step="0.01" class="form-control" name="luas_rilis_bps">
                            <small class="text-muted">Opsional, jika sudah ada rilis</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Catatan Analisis</label>
                        <textarea class="form-control" name="catatan_analisis" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnAddSubmit">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Data -->
<div class="modal fade" id="modalEditData" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Evaluasi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEditData">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Bulan <span class="text-danger">*</span></label>
                            <select class="form-control" name="periode_bulan" id="editBulan" required>
                                <?php foreach ($namaBulan as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Tahun <span class="text-danger">*</span></label>
                            <select class="form-control" name="periode_tahun" id="editTahun" required>
                                <?php for($y=date('Y'); $y>=2019; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Wilayah (Kab/Kota) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_wilayah" id="editWilayah" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Estimasi Daerah (Ha) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="luas_estimasi_daerah" id="editEstimasi" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Rilis BPS (Ha)</label>
                            <input type="number" step="0.01" class="form-control" name="luas_rilis_bps" id="editRilis">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Catatan Analisis</label>
                        <textarea class="form-control" name="catatan_analisis" id="editCatatan" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="btnEditSubmit">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Excel -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-excel"></i> Import Data Excel</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formImportExcel" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Pilih File Excel/CSV</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="excelFile" name="excel_file" accept=".xlsx, .xls, .csv" required>
                            <label class="custom-file-label" for="excelFile">Pilih file...</label>
                        </div>
                        <small class="form-text text-muted">
                            Format yang didukung: .xlsx, .xls, .csv. 
                            <a href="<?= BASE_URL ?>evaluasi/downloadTemplate" class="text-info font-weight-bold">
                                <i class="fas fa-download"></i> Download Template CSV
                            </a>
                        </small>
                    </div>
                    
                    <div class="text-right mb-3">
                        <button type="button" class="btn btn-info" id="btnPreviewImport">
                            <i class="fas fa-eye"></i> Preview Data
                        </button>
                    </div>
                </form>
                
                <!-- Preview Section -->
                <div id="previewContainer" style="display: none;">
                    <h6 class="font-weight-bold border-bottom pb-2">Preview Data Import</h6>
                    <div class="alert alert-info py-2" id="previewInfo"></div>
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-sm table-bordered table-striped" id="previewTable">
                            <thead class="bg-light sticky-top">
                                <tr></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Result Section -->
                <div id="importResult" style="display: none;" class="mt-3">
                    <div class="alert alert-info mb-0">
                        <h5 class="alert-heading" id="resultTitle">Import Selesai</h5>
                        <p class="mb-0">
                            Total: <span id="resultTotal" class="font-weight-bold">0</span> |
                            Sukses: <span id="resultSuccess" class="font-weight-bold text-success">0</span> |
                            Gagal: <span id="resultFailed" class="font-weight-bold text-danger">0</span>
                        </p>
                        <div id="resultErrors" class="mt-2" style="display: none;">
                            <hr>
                            <strong>Daftar Error:</strong>
                            <ul id="resultErrorList" class="mb-0 pl-3 small text-danger" style="max-height: 100px; overflow-y: auto;"></ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnImportSubmit" disabled>
                    <i class="fas fa-upload"></i> Mulai Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Generate Snapshot -->
<div class="modal fade" id="modalSnapshot" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Generate Snapshot Estimasi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formSnapshot">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Snapshot akan mengambil data luas panen saat ini dari sistem dan menyimpannya sebagai <strong>Angka Estimasi Daerah</strong>.
                    </div>
                    
                    <div class="form-group">
                        <label for="snapshotBulan" class="font-weight-bold">Bulan</label>
                        <select class="form-control" id="snapshotBulan" name="bulan" required>
                            <?php foreach ($namaBulan as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $currentMonth == $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="snapshotTahun" class="font-weight-bold">Tahun</label>
                        <select class="form-control" id="snapshotTahun" name="tahun" required>
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $currentYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Perhatian:</strong> Data snapshot akan terkunci setelah tanggal 10 dan tidak dapat diubah.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitSnapshot">
                        <i class="fas fa-camera"></i> Generate Snapshot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom styles for Evaluasi page */
.table-danger {
    background-color: #f8d7da !important;
}
.table-warning {
    background-color: #fff3cd !important;
}
.table-success {
    background-color: #d4edda !important;
}
.legend-container span {
    font-size: 0.85rem;
}
</style>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const csrfToken = '<?= $csrfToken ?>';
const chartData = <?= json_encode($chartData) ?>;

// ========== COMPARISON CHART ==========
const chartCtx = document.getElementById('comparisonChart');
if (chartCtx) {
    const labels = chartData.map(d => d.nama_bulan);
    const estimasiData = chartData.map(d => d.estimasi);
    const rilisData = chartData.map(d => d.rilis);
    
    new Chart(chartCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Estimasi Daerah',
                    data: estimasiData,
                    borderColor: 'rgba(0, 123, 255, 1)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 8
                },
                {
                    label: 'Rilis BPS',
                    data: rilisData,
                    borderColor: 'rgba(220, 53, 69, 1)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        afterBody: function(context) {
                            const estimasi = context[0]?.raw;
                            const rilis = context[1]?.raw;
                            if (estimasi !== null && rilis !== null && rilis !== undefined) {
                                const selisih = estimasi - rilis;
                                const persen = rilis !== 0 ? ((selisih / rilis) * 100).toFixed(2) : '-';
                                return `\nSelisih: ${selisih >= 0 ? '+' : ''}${selisih.toLocaleString('id-ID')} Ha\nBias: ${persen}%`;
                            }
                            return '';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Luas Panen (Ha)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
}

// ========== GENERATE SNAPSHOT ==========
document.getElementById('btnGenerateSnapshot')?.addEventListener('click', function() {
    $('#modalSnapshot').modal('show');
});

document.getElementById('formSnapshot')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btnSubmitSnapshot');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch(BASE_URL + 'evaluasi/generateSnapshot', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        $('#modalSnapshot').modal('hide');
    }
});

// ========== ADD DATA ==========
document.getElementById('formAddData')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAddSubmit');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch(BASE_URL + 'evaluasi/store', {
            method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrfToken}
        });
        const result = await response.json();
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// ========== EDIT DATA ==========
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        const data = this.dataset;
        document.getElementById('editId').value = data.id;
        document.getElementById('editBulan').value = data.bulan;
        document.getElementById('editTahun').value = data.tahun;
        document.getElementById('editWilayah').value = data.wilayah;
        document.getElementById('editEstimasi').value = data.estimasi;
        document.getElementById('editRilis').value = data.rilis || '';
        document.getElementById('editCatatan').value = data.catatan || '';
        $('#modalEditData').modal('show');
    });
});

document.getElementById('formEditData')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnEditSubmit');
    const originalText = btn.innerHTML;
    const id = document.getElementById('editId').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Update...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch(BASE_URL + 'evaluasi/update/' + id, {
            method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrfToken}
        });
        const result = await response.json();
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ ' + result.message);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// ========== DELETE DATA ==========
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Apakah Anda yakin ingin menghapus data ini? (Snapshot backup akan dibuat otomatis)')) return;
        
        const id = this.dataset.id;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        
        try {
            const response = await fetch(BASE_URL + 'evaluasi/delete/' + id, {
                method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrfToken}
            });
            const result = await response.json();
            if (result.success) {
                alert('✅ ' + result.message);
                location.reload();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            alert('❌ Error: ' + error.message);
        }
    });
});

// ========== IMPORT EXCEL ==========
document.getElementById('excelFile')?.addEventListener('change', function() {
    const fileName = this.files[0] ? this.files[0].name : 'Pilih file...';
    this.nextElementSibling.innerText = fileName;
});

document.getElementById('btnPreviewImport')?.addEventListener('click', async function() {
    const fileInput = document.getElementById('excelFile');
    if (!fileInput.files.length) {
        alert('Pilih file Excel terlebih dahulu');
        return;
    }
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    
    const formData = new FormData();
    formData.append('excel_file', fileInput.files[0]);
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch(BASE_URL + 'evaluasi/previewImport', {
            method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrfToken}
        });
        const result = await response.json();
        
        if (result.success) {
            const container = document.getElementById('previewContainer');
            const thead = document.querySelector('#previewTable thead tr');
            const tbody = document.querySelector('#previewTable tbody');
            
            thead.innerHTML = result.headers.map(h => `<th>${h}</th>`).join('');
            tbody.innerHTML = result.data.map(row => {
                const cells = result.headers.map(h => `<td>${row[h] || ''}</td>`).join('');
                return `<tr>${cells}</tr>`;
            }).join('');
            
            document.getElementById('previewInfo').textContent = `Menampilkan ${result.previewRows} dari ${result.totalRows} baris`;
            container.style.display = 'block';
            document.getElementById('btnImportSubmit').disabled = false;
        } else {
            alert('Import Error: ' + (result.error || 'Gagal membaca file'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

document.getElementById('btnImportSubmit')?.addEventListener('click', async function() {
    const fileInput = document.getElementById('excelFile');
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengimport...';
    
    document.getElementById('importResult').style.display = 'none';
    
    const formData = new FormData(document.getElementById('formImportExcel'));
    
    try {
        const response = await fetch(BASE_URL + 'evaluasi/importExcel', {
            method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrfToken}
        });
        const result = await response.json();
        
        document.getElementById('importResult').style.display = 'block';
        document.getElementById('resultTotal').innerText = result.totalProcessed || 0;
        document.getElementById('resultSuccess').innerText = result.successCount || 0;
        document.getElementById('resultFailed').innerText = result.failedCount || 0;
        
        const errorsDiv = document.getElementById('resultErrors');
        const errorList = document.getElementById('resultErrorList');
        if (result.errors && result.errors.length > 0) {
            errorsDiv.style.display = 'block';
            errorList.innerHTML = result.errors.slice(0, 5).map(e => `<li>${e}</li>`).join('');
            if (result.errors.length > 5) {
                errorList.innerHTML += `<li>...dan ${result.errors.length - 5} error lainnya</li>`;
            }
        } else {
            errorsDiv.style.display = 'none';
        }
        
        if (result.success && result.successCount > 0) {
            setTimeout(() => {
                alert('Import selesai!');
                location.reload();
            }, 1000);
        }
    } catch (error) {
        alert('Error: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// ========== RESET FILTER ==========
document.getElementById('btnReset')?.addEventListener('click', function() {
    window.location.href = BASE_URL + 'evaluasi';
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
