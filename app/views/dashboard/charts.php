<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Dashboard Charts Styles */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.chart-container.large {
    height: 400px;
}

.chart-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}

.chart-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-card-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.chart-card-header .badge {
    font-size: 11px;
}

.chart-card-body {
    padding: 20px;
}

.chart-card-footer {
    padding: 10px 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    font-size: 12px;
    color: #666;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
}

.stat-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin-right: 15px;
}

.stat-card .content {
    flex: 1;
}

.stat-card .value {
    font-size: 24px;
    font-weight: 700;
    color: #333;
    line-height: 1;
}

.stat-card .label {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.stat-card .trend {
    font-size: 12px;
    margin-top: 5px;
}

.stat-card .trend.up {
    color: #28a745;
}

.stat-card .trend.down {
    color: #dc3545;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.filter-bar .filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-bar label {
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin: 0;
    white-space: nowrap;
}

.filter-bar select,
.filter-bar input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    min-width: 120px;
}

.filter-bar .btn-apply {
    background: #198754;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.2s;
}

.filter-bar .btn-apply:hover {
    background: #157347;
}

/* Export Toolbar */
.export-toolbar {
    display: flex;
    gap: 10px;
}

.export-toolbar .btn {
    font-size: 12px;
    padding: 6px 12px;
}

/* Tabs */
.chart-tabs {
    display: flex;
    border-bottom: 2px solid #eee;
    margin-bottom: 20px;
}

.chart-tab {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.chart-tab:hover {
    color: #198754;
}

.chart-tab.active {
    color: #198754;
    border-bottom-color: #198754;
}

/* Loading */
.chart-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.chart-loading.hidden {
    display: none;
}

/* Data Table */
.data-table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    font-size: 13px;
}

.data-table th {
    background: #f8f9fa;
    padding: 10px 15px;
    font-weight: 600;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.data-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-bar .filter-group {
        width: 100%;
    }
    
    .filter-bar select,
    .filter-bar input {
        flex: 1;
    }
    
    .chart-container {
        height: 250px;
    }
}
</style>

<!-- Page Header -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h4 class="mb-1"><i class="fas fa-chart-bar text-primary"></i> Dashboard Grafik & Statistik</h4>
                <p class="text-muted mb-0">Visualisasi data cuaca, harga komoditas, produksi, dan lebih banyak lagi</p>
            </div>
            <div class="export-toolbar">
                <button class="btn btn-outline-success btn-sm" onclick="exportData('csv')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="exportData('excel')">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="refreshAllCharts()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="filter-group">
        <label><i class="fas fa-calendar"></i> Tahun:</label>
        <select id="filterYear">
            <?php 
            $currentYear = date('Y');
            for ($y = $currentYear; $y >= $currentYear - 5; $y--): 
            ?>
            <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="filter-group">
        <label><i class="fas fa-clock"></i> Periode:</label>
        <select id="filterPeriod">
            <option value="3">3 Bulan Terakhir</option>
            <option value="6" selected>6 Bulan Terakhir</option>
            <option value="12">12 Bulan Terakhir</option>
        </select>
    </div>
    <button class="btn-apply" onclick="applyFilters()">
        <i class="fas fa-check"></i> Terapkan
    </button>
</div>

<!-- Tabs -->
<div class="chart-tabs">
    <div class="chart-tab active" data-tab="weather" onclick="switchTab('weather')">
        <i class="fas fa-cloud-sun"></i> Cuaca
    </div>
    <div class="chart-tab" data-tab="prices" onclick="switchTab('prices')">
        <i class="fas fa-money-bill-wave"></i> Harga Komoditas
    </div>
    <div class="chart-tab" data-tab="production" onclick="switchTab('production')">
        <i class="fas fa-seedling"></i> Produksi
    </div>
    <div class="chart-tab" data-tab="hama" onclick="switchTab('hama')">
        <i class="fas fa-bug"></i> Sebaran Hama
    </div>
    <div class="chart-tab" data-tab="irrigation" onclick="switchTab('irrigation')">
        <i class="fas fa-water"></i> Irigasi
    </div>
</div>

<!-- Weather Tab Content -->
<div class="tab-content" id="tab-weather">
    <!-- Stats Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <i class="fas fa-cloud-rain"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-avg-rainfall">-</div>
                <div class="label">Rata-rata Curah Hujan</div>
                <div class="trend up" id="trend-rainfall">mm/bulan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #6f42c1, #5a32a3);">
                <i class="fas fa-wind"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-avg-wind">-</div>
                <div class="label">Rata-rata Kecepatan Angin</div>
                <div class="trend" id="trend-wind">km/jam</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-weather-alerts">0</div>
                <div class="label">Peringatan Cuaca</div>
                <div class="trend" id="trend-alerts">7 hari terakhir</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Rainfall Chart -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-cloud-rain text-info"></i> Curah Hujan Bulanan</h5>
                    <span class="badge badge-info" id="year-rainfall"><?= date('Y') ?></span>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="rainfallChart"></canvas>
                    </div>
                </div>
                <div class="chart-card-footer">
                    <i class="fas fa-info-circle"></i> Data dari OpenMeteo & simulasi
                </div>
            </div>
        </div>
        
        <!-- Wind Speed Chart -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-wind text-purple"></i> Kecepatan Angin Bulanan</h5>
                    <span class="badge badge-secondary" id="year-wind"><?= date('Y') ?></span>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="windChart"></canvas>
                    </div>
                </div>
                <div class="chart-card-footer">
                    <i class="fas fa-info-circle"></i> Rata-rata kecepatan angin (km/jam)
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Prices Tab Content -->
<div class="tab-content" id="tab-prices" style="display: none;">
    <!-- Stats Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20883c);">
                <i class="fas fa-rice"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-price-gabah">Rp -</div>
                <div class="label">Harga Gabah Terakhir</div>
                <div class="trend up" id="trend-gabah">per kg</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-price-beras">Rp -</div>
                <div class="label">Harga Beras Terakhir</div>
                <div class="trend" id="trend-beras">per kg</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-price-change">-</div>
                <div class="label">Perubahan Harga</div>
                <div class="trend" id="trend-change">vs bulan lalu</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Price Trend Chart -->
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-chart-line text-success"></i> Tren Harga Komoditas</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container large">
                        <canvas id="priceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Price Comparison -->
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-balance-scale"></i> Perbandingan Harga</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="priceComparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Production Tab Content -->
<div class="tab-content" id="tab-production" style="display: none;">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20883c);">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-prod-gabah">-</div>
                <div class="label">Produksi Gabah</div>
                <div class="trend" id="trend-prod-gabah">ton</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <i class="fas fa-map"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-luas-panen">-</div>
                <div class="label">Luas Panen</div>
                <div class="trend" id="trend-luas-panen">hektar</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-produktivitas">-</div>
                <div class="label">Produktivitas</div>
                <div class="trend" id="trend-produktivitas">ku/ha</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Production Trend -->
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-chart-area text-success"></i> Tren Produksi</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container large">
                        <canvas id="productionTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Producers -->
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-trophy text-warning"></i> Top Produsen</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="topProducersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hama Tab Content -->
<div class="tab-content" id="tab-hama" style="display: none;">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                <i class="fas fa-bug"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-total-hama">-</div>
                <div class="label">Total Laporan Hama</div>
                <div class="trend" id="trend-total-hama">tahun ini</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-verified-hama">-</div>
                <div class="label">Terverifikasi</div>
                <div class="trend" id="trend-verified-hama">laporan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #6c757d, #545b62);">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-luas-serangan">-</div>
                <div class="label">Total Luas Serangan</div>
                <div class="trend" id="trend-luas-serangan">hektar</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Hama Distribution -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-chart-line text-danger"></i> Distribusi Laporan Bulanan</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="hamaDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top OPT -->
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-list-ol text-warning"></i> Top OPT Terbanyak</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="topOPTChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Irrigation Tab Content -->
<div class="tab-content" id="tab-irrigation" style="display: none;">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #007bff, #0056b3);">
                <i class="fas fa-water"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-avg-debit">-</div>
                <div class="label">Rata-rata Debit Air</div>
                <div class="trend" id="trend-avg-debit">m³/s</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-total-irigasi">-</div>
                <div class="label">Daerah Irigasi</div>
                <div class="trend" id="trend-total-irigasi">lokasi</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20883c);">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="content">
                <div class="value" id="stat-max-debit">-</div>
                <div class="label">Debit Maksimum</div>
                <div class="trend" id="trend-max-debit">m³/s</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Irrigation Trend -->
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-chart-area text-primary"></i> Tren Debit Air</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container large">
                        <canvas id="irrigationTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- By Area -->
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5><i class="fas fa-sitemap"></i> Per Daerah Irigasi</h5>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container">
                        <canvas id="irrigationByAreaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let charts = {};
let activeFilters = {
    year: <?= date('Y') ?>,
    period: 6
};

// Chart colors
const chartColors = {
    primary: 'rgba(13, 110, 253, 0.8)',
    success: 'rgba(25, 135, 84, 0.8)',
    danger: 'rgba(220, 53, 69, 0.8)',
    warning: 'rgba(255, 193, 7, 0.8)',
    info: 'rgba(23, 162, 184, 0.8)',
    purple: 'rgba(111, 66, 193, 0.8)',
    primaryLight: 'rgba(13, 110, 253, 0.2)',
    successLight: 'rgba(25, 135, 84, 0.2)'
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWeatherData();
    loadSummaryData();
});

// Switch tabs
function switchTab(tabId) {
    // Update tab buttons
    document.querySelectorAll('.chart-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelector(`.chart-tab[data-tab="${tabId}"]`).classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    document.getElementById(`tab-${tabId}`).style.display = 'block';
    
    // Load data for the tab
    switch(tabId) {
        case 'weather':
            loadWeatherData();
            break;
        case 'prices':
            loadPricesData();
            break;
        case 'production':
            loadProductionData();
            break;
        case 'hama':
            loadHamaData();
            break;
        case 'irrigation':
            loadIrrigationData();
            break;
    }
}

// Apply filters
function applyFilters() {
    activeFilters.year = document.getElementById('filterYear').value;
    activeFilters.period = document.getElementById('filterPeriod').value;
    
    // Reload current tab
    const activeTab = document.querySelector('.chart-tab.active').dataset.tab;
    switchTab(activeTab);
}

// Load summary data
function loadSummaryData() {
    fetch(`<?= BASE_URL ?>api/dashboard/charts/summary?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update summary stats across all tabs
                console.log('Summary loaded:', data.data);
            }
        })
        .catch(error => console.error('Error loading summary:', error));
}

// Load weather data
function loadWeatherData() {
    // Rainfall
    fetch(`<?= BASE_URL ?>api/dashboard/charts/rainfall?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderRainfallChart(data.data);
                updateWeatherStats(data.statistics);
            }
        })
        .catch(error => console.error('Error loading rainfall:', error));
    
    // Wind
    fetch(`<?= BASE_URL ?>api/dashboard/charts/wind?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderWindChart(data.data);
                updateWindStats(data.statistics);
            }
        })
        .catch(error => console.error('Error loading wind:', error));
}

// Render rainfall chart
function renderRainfallChart(data) {
    const ctx = document.getElementById('rainfallChart').getContext('2d');
    
    if (charts.rainfall) {
        charts.rainfall.destroy();
    }
    
    charts.rainfall = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Curah Hujan (mm)',
                data: data.datasets[0].data,
                backgroundColor: chartColors.info,
                borderColor: chartColors.info,
                borderWidth: 1,
                borderRadius: 4
            }]
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
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'mm'
                    }
                }
            }
        }
    });
}

// Render wind chart
function renderWindChart(data) {
    const ctx = document.getElementById('windChart').getContext('2d');
    
    if (charts.wind) {
        charts.wind.destroy();
    }
    
    charts.wind = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Kecepatan Angin (km/jam)',
                data: data.datasets[0].data,
                borderColor: chartColors.purple,
                backgroundColor: 'rgba(111, 66, 193, 0.1)',
                fill: true,
                tension: 0.4
            }]
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
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'km/jam'
                    }
                }
            }
        }
    });
}

// Update weather stats
function updateWeatherStats(stats) {
    if (stats) {
        document.getElementById('stat-avg-rainfall').textContent = 
            parseFloat(stats.avg_rainfall || 0).toFixed(1);
    }
}

// Update wind stats
function updateWindStats(stats) {
    if (stats) {
        document.getElementById('stat-avg-wind').textContent = 
            parseFloat(stats.avg_speed || 0).toFixed(1);
    }
}

// Load prices data
function loadPricesData() {
    fetch(`<?= BASE_URL ?>api/dashboard/charts/prices?months=${activeFilters.period}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPriceTrendChart(data.data.chart);
                renderPriceComparisonChart(data.data.comparison);
                updatePriceStats(data.data.latest);
            }
        })
        .catch(error => console.error('Error loading prices:', error));
}

// Render price trend chart
function renderPriceTrendChart(data) {
    const ctx = document.getElementById('priceTrendChart').getContext('2d');
    
    if (charts.priceTrend) {
        charts.priceTrend.destroy();
    }
    
    const datasets = data.datasets.map((ds, index) => ({
        ...ds,
        tension: 0.4,
        fill: false
    }));
    
    charts.priceTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Rupiah/kg'
                    }
                }
            }
        }
    });
}

// Render price comparison chart
function renderPriceComparisonChart(data) {
    const ctx = document.getElementById('priceComparisonChart').getContext('2d');
    
    if (charts.priceComparison) {
        charts.priceComparison.destroy();
    }
    
    const labels = data.map(d => d.komoditas);
    const values = data.map(d => parseFloat(d.avg_price));
    
    charts.priceComparison = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [chartColors.success, chartColors.danger, chartColors.warning, chartColors.info]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Update price stats
function updatePriceStats(latest) {
    if (latest && latest.length > 0) {
        const gabah = latest.find(p => p.komoditas && p.komoditas.toLowerCase().includes('gabah'));
        const beras = latest.find(p => p.komoditas && p.komoditas.toLowerCase().includes('beras'));
        
        if (gabah) {
            document.getElementById('stat-price-gabah').textContent = 
                'Rp ' + parseInt(gabah.harga).toLocaleString('id-ID');
        }
        if (beras) {
            document.getElementById('stat-price-beras').textContent = 
                'Rp ' + parseInt(beras.harga).toLocaleString('id-ID');
        }
    }
}

// Load production data
function loadProductionData() {
    fetch(`<?= BASE_URL ?>api/dashboard/charts/production?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderProductionTrendChart(data.data.trendChart);
                renderTopProducersChart(data.data.topProducersChart);
                updateProductionStats(data.data.statistics);
            }
        })
        .catch(error => console.error('Error loading production:', error));
}

// Render production trend chart
function renderProductionTrendChart(data) {
    const ctx = document.getElementById('productionTrendChart').getContext('2d');
    
    if (charts.productionTrend) {
        charts.productionTrend.destroy();
    }
    
    if (!data || !data.datasets) return;
    
    const datasets = data.datasets.map((ds, index) => ({
        ...ds,
        tension: 0.4,
        fill: index === 0
    }));
    
    charts.productionTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
}

// Render top producers chart
function renderTopProducersChart(data) {
    const ctx = document.getElementById('topProducersChart').getContext('2d');
    
    if (charts.topProducers) {
        charts.topProducers.destroy();
    }
    
    if (!data || !data.datasets) return;
    
    charts.topProducers = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels.slice(0, 5),
            datasets: [{
                label: 'Produksi (ton)',
                data: data.datasets[0].data.slice(0, 5),
                backgroundColor: chartColors.success,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Update production stats
function updateProductionStats(stats) {
    if (stats) {
        document.getElementById('stat-prod-gabah').textContent = 
            parseFloat(stats.total_produksi_gabah || 0).toLocaleString('id-ID');
        document.getElementById('stat-luas-panen').textContent = 
            parseFloat(stats.total_luas_panen || 0).toLocaleString('id-ID');
        document.getElementById('stat-produktivitas').textContent = 
            parseFloat(stats.avg_produktivitas || 0).toFixed(2);
    }
}

// Load hama data
function loadHamaData() {
    fetch(`<?= BASE_URL ?>api/dashboard/charts/hama?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderHamaDistributionChart(data.data.distributionChart);
                renderTopOPTChart(data.data.topOPTChart);
                updateHamaStats(data.data.statistics);
            }
        })
        .catch(error => console.error('Error loading hama:', error));
}

// Render hama distribution chart
function renderHamaDistributionChart(data) {
    const ctx = document.getElementById('hamaDistributionChart').getContext('2d');
    
    if (charts.hamaDistribution) {
        charts.hamaDistribution.destroy();
    }
    
    charts.hamaDistribution = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.datasets[0].data,
                borderColor: chartColors.danger,
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.4
            }]
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
}

// Render top OPT chart
function renderTopOPTChart(data) {
    const ctx = document.getElementById('topOPTChart').getContext('2d');
    
    if (charts.topOPT) {
        charts.topOPT.destroy();
    }
    
    const colors = [chartColors.danger, chartColors.warning, chartColors.success, 
                    chartColors.info, chartColors.purple];
    
    charts.topOPT = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels.slice(0, 5),
            datasets: [{
                label: 'Jumlah Laporan',
                data: data.datasets[0].data.slice(0, 5),
                backgroundColor: colors,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Update hama stats
function updateHamaStats(stats) {
    if (stats) {
        document.getElementById('stat-total-hama').textContent = 
            parseInt(stats.total_laporan || 0).toLocaleString('id-ID');
        document.getElementById('stat-verified-hama').textContent = 
            parseInt(stats.terverifikasi || 0).toLocaleString('id-ID');
        document.getElementById('stat-luas-serangan').textContent = 
            parseFloat(stats.total_luas_serangan || 0).toFixed(2);
    }
}

// Load irrigation data
function loadIrrigationData() {
    fetch(`<?= BASE_URL ?>api/dashboard/charts/irrigation`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderIrrigationTrendChart(data.data.trendChart);
                renderIrrigationByAreaChart(data.data.byAreaChart);
                updateIrrigationStats(data.data.statistics);
            }
        })
        .catch(error => console.error('Error loading irrigation:', error));
}

// Render irrigation trend chart
function renderIrrigationTrendChart(data) {
    const ctx = document.getElementById('irrigationTrendChart').getContext('2d');
    
    if (charts.irrigationTrend) {
        charts.irrigationTrend.destroy();
    }
    
    if (!data || !data.datasets) return;
    
    charts.irrigationTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.datasets.map(ds => ({
                ...ds,
                tension: 0.4
            }))
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
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'm³/s'
                    }
                }
            }
        }
    });
}

// Render irrigation by area chart
function renderIrrigationByAreaChart(data) {
    const ctx = document.getElementById('irrigationByAreaChart').getContext('2d');
    
    if (charts.irrigationByArea) {
        charts.irrigationByArea.destroy();
    }
    
    if (!data || !data.datasets) return;
    
    charts.irrigationByArea = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels.slice(0, 5),
            datasets: [{
                label: 'Rata-rata Debit',
                data: data.datasets[0].data.slice(0, 5),
                backgroundColor: chartColors.primary,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Update irrigation stats
function updateIrrigationStats(stats) {
    if (stats) {
        document.getElementById('stat-avg-debit').textContent = 
            parseFloat(stats.avg_debit || 0).toFixed(2);
        document.getElementById('stat-total-irigasi').textContent = 
            parseInt(stats.total_daerah_irigasi || 0).toLocaleString('id-ID');
        document.getElementById('stat-max-debit').textContent = 
            parseFloat(stats.max_debit || 0).toFixed(2);
    }
}

// Refresh all charts
function refreshAllCharts() {
    const activeTab = document.querySelector('.chart-tab.active').dataset.tab;
    switchTab(activeTab);
}

// Export data
function exportData(format) {
    const activeTab = document.querySelector('.chart-tab.active').dataset.tab;
    let type = activeTab;
    
    // Map tab to export type
    const typeMap = {
        'weather': 'rainfall',
        'prices': 'prices',
        'production': 'production',
        'hama': 'hama',
        'irrigation': 'irrigation'
    };
    
    type = typeMap[activeTab] || type;
    
    const url = `<?= BASE_URL ?>api/dashboard/charts/export?type=${type}&format=${format}&year=${activeFilters.year}`;
    
    if (format === 'csv') {
        window.open(url, '_blank');
    } else {
        // For Excel, we'd need a proper library - for now, use CSV
        window.open(url.replace('excel', 'csv'), '_blank');
    }
}
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
