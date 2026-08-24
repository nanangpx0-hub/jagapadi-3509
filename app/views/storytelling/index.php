<?php
/**
 * Dashboard Data Storytelling - Main View
 * 
 * Interface untuk meninjau indikasi hubungan produksi padi dengan curah hujan
 * dan laporan OPT bulan sebelumnya.
 */

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4 mt-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-book-open text-primary"></i> Data Storytelling
            </h1>
            <small class="text-muted">Indikasi Faktor Terkait Produksi Padi</small>
        </div>

        <!-- Custom CSS for Storytelling -->
        <style>
            .kpi-card {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
                border-left: 4px solid;
                height: 100%;
            }
            
            .kpi-card:hover {
                transform: translateY(-5px);
            }
            
            .kpi-card.primary { border-left-color: #007bff; }
            .kpi-card.success { border-left-color: #28a745; }
            .kpi-card.warning { border-left-color: #ffc107; }
            .kpi-card.info { border-left-color: #17a2b8; }
            
            .kpi-value {
                font-size: 2rem;
                font-weight: bold;
                margin-bottom: 0.5rem;
            }
            
            .kpi-label {
                color: #6c757d;
                font-size: 0.9rem;
            }
            
            .story-panel {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 1.5rem;
                height: 100%;
                border: 1px solid #e9ecef;
            }

            .story-section {
                background: white;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #dee2e6;
                margin-bottom: 15px;
            }

            .story-section h6 {
                font-weight: bold;
                color: #4e73df;
                border-bottom: 2px solid #f0f3ff;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            
            .chart-container {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                position: relative;
                height: 400px;
                margin-bottom: 1.5rem;
            }
            
            .filter-toolbar {
                background: white;
                padding: 1.5rem;
                border-radius: 12px;
                margin-bottom: 2rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            /* Fix AdminLTE conflict */
            .content-wrapper {
                background-color: #f4f6f9;
            }

            .risk-unavailable {
                background: #6c757d;
                color: #fff;
            }
        </style>

        <!-- Filter Toolbar -->
        <?php $storyDataAvailability = $data_availability ?? ['available' => false]; ?>
        <?php if (empty($storyDataAvailability['available'])): ?>
            <div class="alert alert-warning shadow-sm" role="alert" id="storytelling-data-warning">
                <h5 class="alert-heading"><i class="fas fa-database mr-2"></i>Data produksi bulanan belum tersedia</h5>
                <p class="mb-2">
                    Storytelling memerlukan data <strong>produksi terverifikasi yang memiliki bulan</strong>.
                    Saat ini terdapat <?= (int) ($storyDataAvailability['verified_total'] ?? 0) ?> data produksi terverifikasi,
                    tetapi <?= (int) ($storyDataAvailability['monthly_total'] ?? 0) ?> di antaranya memiliki periode bulanan.
                </p>
                <small>
                    Impor atau lengkapi kolom bulan pada sumber produksi terlebih dahulu. Sistem tidak membuat estimasi bulanan
                    dari data tahunan karena dapat menghasilkan analisis yang menyesatkan.
                </small>
            </div>
        <?php endif; ?>
        <div class="filter-toolbar">
            <form id="filterForm" class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Bulan Produksi</label>
                    <select class="form-control" name="bulan" id="filter-bulan">
                        <?php
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        $currentMonth = (int) ($current_month ?? date('n'));
                        foreach ($months as $num => $name):
                        ?>
                            <option value="<?= $num ?>" <?= $num == $currentMonth ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Tahun</label>
                    <select class="form-control" name="tahun" id="filter-tahun">
                        <?php
                        $years = $available_years ?? range((int) date('Y'), (int) date('Y') - 4);
                        foreach ($years as $y):
                        ?>
                            <option value="<?= (int) $y ?>"><?= (int) $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label font-weight-bold">Kecamatan</label>
                    <select class="form-control" name="kecamatan_id" id="filter-kecamatan">
                        <option value="">Pilih Kecamatan...</option>
                        <?php if (!empty($kecamatan_list)): ?>
                            <?php foreach ($kecamatan_list as $kec): ?>
                                <option value="<?= (int) $kec['id'] ?>"><?= htmlspecialchars((string) $kec['nama_kecamatan'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> Pilih kecamatan untuk analisis spesifik
                    </small>
                </div>
                
                <div class="col-md-2 mb-3">
                    <button type="button" id="btn-analyze" class="btn btn-primary btn-block">
                        <i class="fas fa-magic mr-2"></i> Analisa
                    </button>
                    <button type="button" id="btn-reset" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <div class="card shadow-sm mb-4" id="advanced-analysis-panel">
            <div class="card-header bg-white">
                <strong><i class="fas fa-microscope text-primary mr-2"></i>Metode Analisis Lanjutan</strong>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="analysis-method" class="font-weight-bold">Metode</label>
                        <select id="analysis-method" class="form-control">
                            <option value="trend">Tren temporal</option>
                            <option value="correlation">Korelasi Pearson</option>
                            <option value="predictive">Prediksi baseline</option>
                            <option value="clustering">Segmentasi data</option>
                            <option value="outlier">Deteksi outlier</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="analysis-months" class="font-weight-bold">Jendela data</label>
                        <select id="analysis-months" class="form-control">
                            <option value="6">6 bulan</option>
                            <option value="12" selected>12 bulan</option>
                            <option value="18">18 bulan</option>
                            <option value="24">24 bulan</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="analysis-parameter" class="font-weight-bold">Parameter</label>
                        <input id="analysis-parameter" type="number" class="form-control" value="3" min="1" max="12" step="0.1">
                        <small id="analysis-parameter-help" class="text-muted">Window moving average</small>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3" id="analysis-variable-wrapper" style="display:none">
                        <label for="analysis-variable" class="font-weight-bold">Variabel pembanding</label>
                        <select id="analysis-variable" class="form-control">
                            <option value="rain">Curah hujan</option>
                            <option value="pest">Laporan OPT</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3 ml-auto">
                        <button type="button" id="btn-run-method" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-play mr-1"></i> Jalankan
                        </button>
                    </div>
                </div>
                <div id="method-analysis-result" class="alert alert-light border mb-0" style="display:none" aria-live="polite">
                    <div class="d-flex justify-content-between flex-wrap">
                        <strong id="method-analysis-title">Hasil analisis</strong>
                        <small id="method-analysis-meta" class="text-muted"></small>
                    </div>
                    <p id="method-analysis-summary" class="mb-2 mt-2"></p>
                    <pre id="method-analysis-metrics" class="small bg-white border rounded p-2 mb-0" style="white-space:pre-wrap"></pre>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info text-center small mb-4">
            <i class="fas fa-info-circle mr-1"></i> 
            Menggunakan produksi bulanan terverifikasi dan indikator bulan sebelumnya.
            Hasil merupakan <strong>indikasi hubungan, bukan bukti kausalitas</strong>.
        </div>

        <!-- Main Content Area (Hidden by default, shown after analysis) -->
        <div id="analysis-result" style="display: none;">
            
            <div id="existing-warning" class="alert alert-warning mb-4" style="display: none;">
                <i class="fas fa-exclamation-triangle mr-1"></i> Analisis periode ini sudah ada. Menyimpan kembali akan memperbarui analisis dan mengembalikan statusnya menjadi draft.
            </div>

            <div id="stale-warning" class="alert alert-danger mb-4" style="display: none;">
                <i class="fas fa-sync-alt mr-1"></i> Filter telah berubah. Jalankan analisis kembali sebelum menyimpan atau membuat preview.
            </div>

            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card primary">
                        <div class="kpi-value" id="kpi-luas-panen">-</div>
                        <div class="kpi-label">Luas Panen Bulanan Terverifikasi (Ha)</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card info">
                        <div class="kpi-value" id="kpi-curah-hujan">-</div>
                        <div class="kpi-label">Total Curah Hujan (mm/bulan)</div>
                        <small class="text-muted">Lag-1 bulan, coverage minimum 70%</small>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card warning">
                        <div class="kpi-value" id="kpi-laporan-hama">-</div>
                        <div class="kpi-label">Laporan Hama</div>
                        <small class="text-muted">Lagging (H-1 Bulan)</small>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card success" data-bs-toggle="tooltip" data-bs-placement="top" title="Skor <40: Aman (Hijau), 40-70: Waspada (Kuning), >70: Bahaya (Merah)">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="kpi-value" id="kpi-skor-risiko">-</div>
                            <i class="fas fa-info-circle text-muted" style="cursor: pointer;"></i>
                        </div>
                        <div class="kpi-label">Skor Risiko Produksi</div>
                        <div class="mt-2 small">
                            <span id="score-cuaca" class="badge">Cuaca: -</span>
                            <span id="score-hama" class="badge">Hama: -</span>
                            <span id="score-total" class="badge">Total: -</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-4 font-weight-bold">Perbandingan Produksi dan Indikator (6 Bulan)</h5>
                        <canvas id="correlationChart"></canvas>
                    </div>
                    
                    <!-- Recent Analysis Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Riwayat Analisis Terakhir</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Periode</th>
                                            <th>Wilayah</th>
                                            <th>Indikasi Faktor</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentAnalysesTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Story Panel -->
                <div class="col-lg-4 mb-4">
                    <div class="story-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0 font-weight-bold"><i class="fas fa-pen-fancy mr-2"></i>Narrative Builder</h5>
                            <span class="badge badge-warning" id="statusBadge">DRAFT</span>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Indikasi Faktor Terkait</label>
                            <select class="form-control" id="faktor-penyebab">
                                <option value="Cuaca Ekstrem">Cuaca Ekstrem</option>
                                <option value="Serangan OPT">Serangan OPT</option>
                                <option value="Kombinasi Cuaca & OPT">Kombinasi Cuaca & OPT</option>
                                <option value="Normal">Normal</option>
                                <option value="Alih Fungsi Lahan">Alih Fungsi Lahan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <!-- Auto Generated Story -->
                        <div class="story-section">
                            <h6><i class="fas fa-calculator mr-2"></i>Insight Berbasis Aturan</h6>
                            <textarea id="narasi-otomatis" class="form-control" rows="4" readonly placeholder="Silakan lakukan analisis untuk mendapatkan insight otomatis..."></textarea>
                        </div>

                        <div class="story-section">
                            <h6><i class="fas fa-database mr-2"></i>Kualitas Data</h6>
                            <p id="data-quality" class="small mb-0">-</p>
                        </div>

                        <!-- Manual Editor -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Final Narrative / Official Statement</label>
                            <textarea class="form-control" id="narasi-final" rows="8" 
                                placeholder="Edit narasi final di sini sebelum dipublikasikan..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-block mb-2" id="btn-save-analysis" disabled>
                                <i class="fas fa-save mr-2"></i> Simpan Analisis
                            </button>
                            <button class="btn btn-info btn-block text-white" id="btn-preview" disabled>
                                <i class="fas fa-print mr-2"></i> Preview & Cetak
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="default-state" class="text-center py-5">
            <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h4 class="text-gray-800">Siap Menganalisis?</h4>
            <p class="text-muted">Pilih filter di atas dan klik tombol "Analisa" untuk memulai data storytelling.</p>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin fa-3x mb-3 text-primary"></i>
        <h5>Memproses Data...</h5>
        <p id="loading-message">Sedang menghubungkan variabel eksogen</p>
        <div class="mt-3 mb-2">
            <div class="progress" style="height: 6px;">
                <div id="loading-progress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
            </div>
        </div>
        <div id="loading-timer" class="mt-2" style="font-size: 1.2rem; font-weight: 500;">
            <i class="fas fa-clock mr-2"></i>Waktu proses: <span id="timer-display">00:00</span>
        </div>
        <div id="loading-warning" class="mt-3 text-warning" style="display: none;">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Proses sudah berlangsung lebih dari 20 detik.
            Silakan cek koneksi jaringan atau <a href="#" onclick="location.reload()" class="text-warning font-weight-bold">muat ulang halaman</a>.
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>public/js/storytelling-dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Dashboard
    StorytellingDashboard.init({
        baseUrl: <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        dataAvailable: <?= !empty($storyDataAvailability['available']) ? 'true' : 'false' ?>
    });

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    } else if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[data-bs-toggle="tooltip"]').tooltip();
    }
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
