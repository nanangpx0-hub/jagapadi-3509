<?php
/**
 * Dashboard Data Storytelling - Main View
 * 
 * Interface untuk statistisi menganalisis kausalitas produksi padi
 * dengan faktor eksogen (curah hujan & serangan hama) sebagai Lagging Indicators.
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
            <small class="text-muted">Analisis Kausalitas Produksi Padi</small>
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
        </style>

        <!-- Filter Toolbar -->
        <div class="filter-toolbar">
            <form id="filterForm" class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Bulan Analisis</label>
                    <select class="form-control" name="bulan" id="filter-bulan">
                        <?php
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        $currentMonth = date('n');
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
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 4; $y--):
                        ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label font-weight-bold">Kecamatan</label>
                    <select class="form-control" name="kecamatan_id" id="filter-kecamatan">
                        <option value="">Pilih Kecamatan...</option>
                        <?php if (!empty($data['kecamatan_list'])): ?>
                            <?php foreach ($data['kecamatan_list'] as $kec): ?>
                                <option value="<?= $kec['id'] ?>"><?= $kec['nama_kecamatan'] ?></option>
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
        
        <div class="alert alert-info text-center small mb-4">
            <i class="fas fa-info-circle mr-1"></i> 
            Menggunakan data bulan sebelumnya sebagai <strong>Lagging Indicators</strong> (Ex: Analisis Maret menggunakan data Hujan/Hama Februari)
        </div>

        <!-- Main Content Area (Hidden by default, shown after analysis) -->
        <div id="analysis-result" style="display: none;">
            
            <div id="existing-warning" class="alert alert-warning mb-4" style="display: none;">
                <i class="fas fa-exclamation-triangle mr-1"></i> Analisis untuk periode dan wilayah ini sudah pernah dibuat sebelumnya. Anda dapat memperbarui data atau menyimpannya sebagai analisis baru.
            </div>

            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card primary">
                        <div class="kpi-value" id="kpi-luas-panen">-</div>
                        <div class="kpi-label">Total Luas Panen (Ha)</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card info">
                        <div class="kpi-value" id="kpi-curah-hujan">-</div>
                        <div class="kpi-label">Avg Curah Hujan (mm)</div>
                        <small class="text-muted">Lagging (H-1 Bulan)</small>
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
                        <h5 class="mb-4 font-weight-bold">Korelasi Faktor Produksi (6 Bulan Terakhir)</h5>
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
                                            <th>Penyebab Utama</th>
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
                            <label class="font-weight-bold">Faktor Penyebab Utama</label>
                            <select class="form-control" id="faktor-penyebab">
                                <option value="Cuaca Ekstrem">Cuaca Ekstrem</option>
                                <option value="Serangan OPT">Serangan OPT</option>
                                <option value="Normal">Normal</option>
                                <option value="Alih Fungsi Lahan">Alih Fungsi Lahan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <!-- Auto Generated Story -->
                        <div class="story-section">
                            <h6><i class="fas fa-robot mr-2"></i>AI Generated Insight</h6>
                            <textarea id="narasi-otomatis" class="form-control" rows="4" readonly placeholder="Silakan lakukan analisis untuk mendapatkan insight otomatis..."></textarea>
                        </div>

                        <!-- Manual Editor -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Final Narrative / Official Statement</label>
                            <textarea class="form-control" id="narasi-final" rows="8" 
                                placeholder="Edit narasi final di sini sebelum dipublikasikan..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-block mb-2" id="btn-save-analysis">
                                <i class="fas fa-save mr-2"></i> Simpan Analisis
                            </button>
                            <button class="btn btn-info btn-block text-white" id="btn-preview">
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
<div id="loading-overlay" class="loading-overlay">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin fa-3x mb-3 text-primary"></i>
        <h5>Memproses Data...</h5>
        <p>Sedang menghubungkan variabel eksogen</p>
        <div id="loading-timer" class="mt-3" style="font-size: 1.2rem; font-weight: 500;">
            <i class="fas fa-clock mr-2"></i>Waktu proses: <span id="timer-display">00:00</span>
        </div>
        <div id="loading-warning" class="mt-3 text-warning" style="display: none;">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Proses sudah berlangsung lebih dari 5 menit. 
            Silakan cek koneksi jaringan atau <a href="#" onclick="location.reload()" class="text-warning font-weight-bold">muat ulang halaman</a>.
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>public/js/storytelling-dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Dashboard
    StorytellingDashboard.init({
        baseUrl: '<?= BASE_URL ?>',
        csrfToken: '<?= $_SESSION['csrf_token'] ?>'
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