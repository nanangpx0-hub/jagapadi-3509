<?php require_once ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
    .status-badge { font-size: 0.85rem; padding: 0.3em 0.8em; border-radius: 20px; }
    .status-aman { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-waspada { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .status-kritis { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .water-level { height: 100%; border-radius: 0 0 10px 10px; transition: height 1s ease-in-out; }
    .bg-water-blue { background-color: #36b9cc; }
    .card-hover:hover { transform: translateY(-5px); transition: all 0.3s ease; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-water text-info"></i> <?= $data['page_title'] ?>
            </h1>
            <small class="text-muted">Monitoring debit air dan irigasi sawah Kabupaten Jember</small>
        </div>
        <div class="btn-group">
            <a href="#" class="btn btn-success" id="btnExport">
                <i class="fas fa-file-excel"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="alert alert-warning border-left-warning shadow-sm" role="alert">
        <strong>Sumber data:</strong> tombol “Ambil Data” menghasilkan <strong>simulasi internal</strong>
        berbasis norma debit dan pola musim. Nilai tersebut bukan observasi sensor atau rilis instansi.
    </div>

    <!-- Control Panel -->
    <div class="card shadow mb-4 border-left-info">
        <div class="card-body">
            <form id="filterForm" class="row align-items-end">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                
                <div class="col-md-3">
                    <label class="font-weight-bold small">Tanggal Observasi</label>
                    <input type="date" class="form-control" id="filterDate" name="tanggal"
                           max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($data['currentDate']) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="font-weight-bold small">Lokasi (Dam / Daerah Irigasi)</label>
                    <select class="form-control" id="filterLokasi" name="lokasi">
                        <option value="">Semua Lokasi</option>
                        <?php foreach ($data['daerahIrigasi'] as $dam => $details): ?>
                            <option value="<?= htmlspecialchars($dam) ?>"><?= htmlspecialchars($dam) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="font-weight-bold small">Status Debit</label>
                    <select class="form-control" id="filterStatus" name="status">
                        <option value="">Semua Status</option>
                        <option value="Aman">Aman (Normal)</option>
                        <option value="Waspada">Waspada (< 60%)</option>
                        <option value="Kritis">Kritis (< 30%)</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex">
                    <button type="button" class="btn btn-info flex-fill mr-2" id="btnFilter">
                        <i class="fas fa-search"></i> Tampilkan
                    </button>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <button type="button" class="btn btn-primary flex-fill" id="btnScrape">
                        <i class="fas fa-sync-alt"></i> Ambil Data
                    </button>
                    <!-- Simulation Toggle -->
                    <div class="custom-control custom-switch mt-2 ml-2" style="display:none;">
                         <input type="checkbox" class="custom-control-input" id="forceRefresh">
                         <label class="custom-control-label" for="forceRefresh">Force</label>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <!-- Rata-rata Debit -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 card-hover">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Rata-rata Debit</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statAvgDebit">
                                <?= DataIrigasi::formatNumber($data['statistics']['rata_debit']) ?> L/det
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tachometer-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Lokasi -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2 card-hover">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Lokasi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statTotalLoc">
                                <?= $data['statistics']['total_lokasi'] ?> Dam
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Waspada -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2 card-hover">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Status Waspada</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statWaspada">
                                <?= $data['statistics']['jumlah_waspada'] ?> Lokasi
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Kritis -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2 card-hover">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Status Kritis</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="statKritis">
                                <?= $data['statistics']['jumlah_kritis'] ?> Lokasi
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

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-12 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Tren Debit Air (30 Hari Terakhir)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="debitTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Data Debit Per Lokasi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Dam / DI</th>
                            <th>Kecamatan</th>
                            <th>Luas Layanan</th>
                            <th>Debit Air</th>
                            <th class="text-center">Status</th>
                            <th>Metode</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="7" class="text-center text-muted">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Code -->
            <div class="d-flex justify-content-between align-items-center mt-3" id="paginationSection">
                <div id="pageInfo" class="small text-muted"></div>
                <ul class="pagination pagination-sm m-0" id="pagination"></ul>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    let debitChart = null;
    let currentPage = 1;
    const limit = 20;

    // Initialize Chart
    function initChart(labels, data) {
        if (debitChart) debitChart.destroy();
        
        const ctx = document.getElementById("debitTrendChart");
        debitChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: "Rata-rata Debit (L/det)",
                    lineTension: 0.3,
                    backgroundColor: "rgba(54, 185, 204, 0.05)",
                    borderColor: "rgba(54, 185, 204, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(54, 185, 204, 1)",
                    pointBorderColor: "rgba(54, 185, 204, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(54, 185, 204, 1)",
                    pointHoverBorderColor: "rgba(54, 185, 204, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: data,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                scales: {
                    x: { grid: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 7 } },
                    y: { ticks: { maxTicksLimit: 5, padding: 10 }, grid: { color: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2] } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleMarginBottom: 10,
                        titleColor: '#6e707e',
                        titleFont: { size: 14 },
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                    }
                }
            }
        });
    }

    // Load Data
    function loadData(page = 1) {
        const tanggal = document.getElementById('filterDate').value;
        const lokasi = document.getElementById('filterLokasi').value;
        const status = document.getElementById('filterStatus').value;
        
        const params = new URLSearchParams({
            tanggal: tanggal,
            lokasi: lokasi,
            status: status,
            limit: limit,
            offset: (page - 1) * limit
        });
        
        fetch(`${BASE_URL}irigasiScraper/getData?${params}`)
            .then(response => response.json())
            .then(res => {
                if(res.success) {
                    renderTable(res.data, (page - 1) * limit);
                    updateStats(res.statistics);
                    renderPagination(res.total, page);
                }
            })
            .catch(err => console.error(err));
    }
    
    // Render Table
    function renderTable(data, startNum) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-4">Tidak ada data untuk tanggal ini. Silakan klik "Ambil Data".</td></tr>';
            return;
        }
        
        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[char]);

        data.forEach((row, index) => {
            let badgeClass = 'status-aman';
            if (row.status_pintu === 'Waspada') badgeClass = 'status-waspada';
            if (row.status_pintu === 'Kritis') badgeClass = 'status-kritis';
            
            const tr = `
                <tr>
                    <td>${startNum + index + 1}</td>
                    <td class="font-weight-bold">${escapeHtml(row.daerah_irigasi)}</td>
                    <td>${escapeHtml(row.kecamatan || '-')}</td>
                    <td class="text-right">${escapeHtml(row.luas_sawah)}</td>
                    <td class="text-right">${escapeHtml(row.debit_air)}</td>
                    <td class="text-center">
                        <span class="status-badge ${badgeClass}">${escapeHtml(row.status_pintu)}</span>
                    </td>
                    <td><span class="badge badge-warning">${escapeHtml(row.metode_data || 'manual')}</span></td>
                    <td class="small text-muted">${escapeHtml(row.keterangan || '-')}</td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }
    
    // Update Stats
    function updateStats(stats) {
        if (!stats) return;
        document.getElementById('statAvgDebit').innerText = stats.rata_debit || '0 L/det';
        document.getElementById('statTotalLoc').innerText = (stats.total_lokasi || 0) + ' Dam';
        document.getElementById('statWaspada').innerText = (stats.jumlah_waspada || 0) + ' Lokasi';
        document.getElementById('statKritis').innerText = (stats.jumlah_kritis || 0) + ' Lokasi';
    }
    
    // Render Pagination
    function renderPagination(total, page) {
        const totalPages = Math.ceil(total / limit);
        const ul = document.getElementById('pagination');
        const info = document.getElementById('pageInfo');
        
        ul.innerHTML = '';
        info.innerText = `Menampilkan ${(page-1)*limit + 1} sampai ${Math.min(page*limit, total)} dari ${total} data`;
        
        if(totalPages <= 1) return;
        
        // Prev
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
        prevLi.onclick = (e) => { e.preventDefault(); if(page > 1) loadData(page - 1); };
        ul.appendChild(prevLi);
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i > page + 2 || i < page - 2) continue; // simplistic window
            
            const li = document.createElement('li');
            li.className = `page-item ${i === page ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.onclick = (e) => { e.preventDefault(); loadData(i); };
            ul.appendChild(li);
        }
        
        // Next
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${page === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
        nextLi.onclick = (e) => { e.preventDefault(); if(page < totalPages) loadData(page + 1); };
        ul.appendChild(nextLi);
    }
    
    // Run Scraper
    const btnScrape = document.getElementById('btnScrape');
    if (btnScrape) {
        btnScrape.onclick = function() {
            const btn = this;
            const originalText = btn.innerHTML;
            const force = document.getElementById('forceRefresh')?.checked ? 'on' : 'off';
            const tanggal = document.getElementById('filterDate').value;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('tanggal', tanggal);
            if(force === 'on') formData.append('force_refresh', 'on');
            
            fetch(`${BASE_URL}irigasiScraper/runScraper`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if(res.success) {
                    alert('Berhasil: ' + res.message);
                    loadData(1);
                    loadChartData(); // refresh chart
                } else {
                    alert('Gagal: ' + (res.error || res.message));
                }
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Terjadi kesalahan koneksi');
                console.error(err);
            });
        };
    }
    
    // Load Chart Data
    function loadChartData() {
        const days = 30;
        fetch(`${BASE_URL}irigasiScraper/getChartData?days=${days}`)
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    initChart(res.labels, res.datasets[0].data);
                }
            });
    }

    // Event Listeners
    document.getElementById('btnFilter').onclick = () => loadData(1);
    document.getElementById('btnExport').onclick = (e) => {
        e.preventDefault();
        const tanggal = document.getElementById('filterDate').value;
        const lokasi = document.getElementById('filterLokasi').value;
        window.location.href = `${BASE_URL}irigasiScraper/export?tanggal=${tanggal}&lokasi=${lokasi}`;
    };

    // Initial Load
    document.addEventListener('DOMContentLoaded', () => {
        loadData(1);
        loadChartData();
    });

</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
