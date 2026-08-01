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
                    <select class="form-control" name="bulan" id="bulanSelect">
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
                    <select class="form-control" name="tahun" id="tahunSelect">
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
                    <select class="form-control" name="kecamatan_id" id="kecamatanSelect">
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
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-magic mr-2"></i> Analisa
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-block mt-2" onclick="location.reload()">
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
        <div id="analysisContent" style="display: none;">
            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card primary">
                        <div class="kpi-value" id="kpiLuasPanen">-</div>
                        <div class="kpi-label">Total Luas Panen (Ha)</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card info">
                        <div class="kpi-value" id="kpiCurahHujan">-</div>
                        <div class="kpi-label">Avg Curah Hujan (mm)</div>
                        <small class="text-muted">Lagging (H-1 Bulan)</small>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card warning">
                        <div class="kpi-value" id="kpiHama">-</div>
                        <div class="kpi-label">Laporan Hama</div>
                        <small class="text-muted">Lagging (H-1 Bulan)</small>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="kpi-card success">
                        <div class="kpi-value" id="kpiRiskScore">-</div>
                        <div class="kpi-label">Skor Risiko Produksi</div>
                        <small class="text-muted">0 (Rendah) - 100 (Tinggi)</small>
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

                        <!-- Auto Generated Story -->
                        <div class="story-section">
                            <h6><i class="fas fa-robot mr-2"></i>AI Generated Insight</h6>
                            <p id="autoNarrative" class="text-muted small font-italic">
                                Silakan lakukan analisis untuk mendapatkan insight otomatis...
                            </p>
                        </div>

                        <!-- Manual Editor -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Final Narrative / Official Statement</label>
                            <textarea class="form-control" id="finalNarrative" rows="8" 
                                placeholder="Edit narasi final di sini sebelum dipublikasikan..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-block" id="btnSaveAnalysis">
                                <i class="fas fa-save mr-2"></i> Simpan Analisis
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5">
            <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h4 class="text-gray-800">Siap Menganalisis?</h4>
            <p class="text-muted">Pilih filter di atas dan klik tombol "Analisa" untuk memulai data storytelling.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('correlationChart').getContext('2d');
    let chartInstance = null;

    // Load recent analyses on start
    loadRecentAnalyses();

    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;

        // Collect form data
        const formData = new FormData(this);

        // AJAX Call
        fetch('<?= BASE_URL ?>storytelling/generateAnalysis', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                updateDashboard(data.data);
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('analysisContent').style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memproses data.');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    document.getElementById('btnSaveAnalysis').addEventListener('click', function() {
        const autoNarrative = document.getElementById('autoNarrative').innerText;
        const finalNarrative = document.getElementById('finalNarrative').value;
        const form = document.getElementById('filterForm');
        
        if(!finalNarrative.trim()) {
            alert('Harap isi narasi final sebelum menyimpan.');
            return;
        }

        const payload = {
            periode_bulan: form.bulan.value,
            periode_tahun: form.tahun.value,
            wilayah_id: form.kecamatan_id.value,
            narasi_otomatis: autoNarrative,
            narasi_final: finalNarrative,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
        };

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        fetch('<?= BASE_URL ?>storytelling/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                alert('Analisis berhasil disimpan!');
                loadRecentAnalyses(); // Reload table
            } else {
                alert('Gagal menyimpan: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan koneksi.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-2"></i> Simpan Analisis';
        });
    });

    function updateDashboard(data) {
        // Update KPI Cards
        document.getElementById('kpiLuasPanen').innerText = parseFloat(data.metrics.luas_panen).toLocaleString();
        document.getElementById('kpiCurahHujan').innerText = data.metrics.rainfall;
        document.getElementById('kpiHama').innerText = data.metrics.pest_reports;
        
        const riskScore = data.risk_score.total;
        const riskEl = document.getElementById('kpiRiskScore');
        riskEl.innerText = riskScore;
        riskEl.parentElement.className = `kpi-card ${riskScore > 70 ? 'danger' : (riskScore > 40 ? 'warning' : 'success')}`;

        // Update Narratives
        document.getElementById('autoNarrative').innerText = data.narrative.generated;
        // Don't overwrite final narrative if user has typed something, unless it's empty
        if(!document.getElementById('finalNarrative').value) {
            document.getElementById('finalNarrative').value = data.narrative.generated;
        }

        // Update Chart
        updateChart(data.chart_data);
    }

    function updateChart(chartData) {
        if(chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Produksi (Ton)',
                        data: chartData.produksi,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Curah Hujan (mm)',
                        data: chartData.rainfall, // Lagging data usually shown aligned
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Produksi (Ton)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Curah Hujan (mm)' },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    }

    function loadRecentAnalyses() {
        const tbody = document.getElementById('recentAnalysesTable');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';

        // Fetch recent data 
        fetch('<?= BASE_URL ?>storytelling/getRecent')
            .then(response => response.json())
            .then(data => {
                renderRecentTable(data.data || []);
            })
            .catch(err => {
                console.warn('Could not load recent analyses', err);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat analisis.</td></tr>';
            });
    }

    function renderRecentTable(data) {
        const tbody = document.getElementById('recentAnalysesTable');
        tbody.innerHTML = '';

        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat analisis.</td></tr>';
            return;
        }

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.periode_bulan}/${row.periode_tahun}</td>
                <td>${row.nama_kecamatan || '-'}</td>
                <td>${row.faktor_penyebab_utama}</td>
                <td><span class="badge badge-${row.status_analisis === 'published' ? 'success' : 'secondary'}">${row.status_analisis}</span></td>
                <td>
                    <button class="btn btn-sm btn-info" title="Lihat"><i class="fas fa-eye"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>