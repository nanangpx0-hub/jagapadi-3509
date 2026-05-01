<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

<style>
/* Dashboard Map Styles */
.map-container {
    position: relative;
    height: calc(100vh - 200px);
    min-height: 500px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

#dashboardMap {
    height: 100%;
    width: 100%;
    z-index: 1;
}

/* Control Panel */
.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    max-width: 280px;
}

.control-panel-header {
    padding: 12px 15px;
    background: linear-gradient(135deg, #198754 0%, #157347 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.control-panel-header h6 {
    margin: 0;
    font-weight: 600;
}

.control-panel-body {
    padding: 15px;
    max-height: 400px;
    overflow-y: auto;
}

.layer-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.layer-item:hover {
    background: #e9ecef;
}

.layer-item.active {
    background: #d1e7dd;
    border-left: 3px solid #198754;
}

.layer-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 10px;
    font-size: 14px;
}

.layer-info {
    flex: 1;
}

.layer-info .name {
    font-weight: 600;
    font-size: 13px;
    color: #333;
}

.layer-info .count {
    font-size: 11px;
    color: #666;
}

/* Filter Panel */
.filter-panel {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 1000;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    max-width: 250px;
}

.filter-panel-header {
    padding: 12px 15px;
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filter-panel-header h6 {
    margin: 0;
    font-weight: 600;
}

.filter-panel-body {
    padding: 15px;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
    display: block;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
}

/* Legend Panel */
.legend-panel {
    position: absolute;
    bottom: 30px;
    left: 10px;
    z-index: 1000;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    padding: 12px 15px;
    max-width: 200px;
}

.legend-title {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 10px;
    color: #333;
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    font-size: 12px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-right: 8px;
}

/* Info Panel */
.info-panel {
    position: absolute;
    bottom: 30px;
    right: 10px;
    z-index: 1000;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    padding: 15px;
    max-width: 300px;
    display: none;
}

.info-panel.active {
    display: block;
}

.info-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.info-panel-header h6 {
    margin: 0;
    font-weight: 600;
}

.info-panel-close {
    cursor: pointer;
    color: #999;
}

.info-content {
    font-size: 13px;
}

.info-content .info-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.info-content .info-row:last-child {
    border-bottom: none;
}

/* Loading Overlay */
.map-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.map-loading.hidden {
    display: none;
}

/* Marker Styles */
.marker-cluster-small {
    background-color: rgba(181, 226, 140, 0.6);
}
.marker-cluster-small div {
    background-color: rgba(110, 204, 57, 0.6);
}

.marker-cluster-medium {
    background-color: rgba(241, 211, 87, 0.6);
}
.marker-cluster-medium div {
    background-color: rgba(240, 194, 12, 0.6);
}

.marker-cluster-large {
    background-color: rgba(253, 156, 115, 0.6);
}
.marker-cluster-large div {
    background-color: rgba(241, 128, 23, 0.6);
}

.custom-marker {
    background: white;
    border-radius: 50%;
    padding: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .map-controls,
    .filter-panel {
        max-width: 200px;
    }
    
    .map-container {
        height: calc(100vh - 150px);
    }
    
    .control-panel-body,
    .filter-panel-body {
        padding: 10px;
    }
}
</style>

<!-- Page Header -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="fas fa-map-marked-alt text-success"></i> Peta Sebaran Data</h4>
                <p class="text-muted mb-0">Visualisasi data sebaran hama, irigasi, dan cuaca pada peta interaktif</p>
            </div>
            <div>
                <button class="btn btn-outline-primary btn-sm" id="btnRefreshMap">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnResetView">
                    <i class="fas fa-expand"></i> Reset View
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Map Container -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="map-container">
                    <!-- Loading Overlay -->
                    <div class="map-loading" id="mapLoading">
                        <div class="text-center">
                            <div class="spinner-border text-success mb-2" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mb-0">Memuat data peta...</p>
                        </div>
                    </div>
                    
                    <!-- Map -->
                    <div id="dashboardMap"></div>
                    
                    <!-- Filter Panel -->
                    <div class="filter-panel" id="filterPanel">
                        <div class="filter-panel-header" onclick="togglePanel('filterPanel')">
                            <h6><i class="fas fa-filter"></i> Filter</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="filter-panel-body">
                            <div class="filter-group">
                                <label>Tahun</label>
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
                                <label>Status</label>
                                <select id="filterStatus">
                                    <option value="" selected>Semua Aktif</option>
                                    <option value="Submitted">Baru Masuk</option>
                                    <option value="Diverifikasi">Lama (Diverifikasi)</option>
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm btn-block" onclick="applyFilters()">
                                <i class="fas fa-check"></i> Terapkan Filter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Layer Control Panel -->
                    <div class="map-controls" id="layerPanel">
                        <div class="control-panel-header" onclick="togglePanel('layerPanel')">
                            <h6><i class="fas fa-layer-group"></i> Layer Peta</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="control-panel-body" id="layerList">
                            <!-- Layers will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Legend Panel -->
                    <div class="legend-panel" id="legendPanel">
                        <div class="legend-title"><i class="fas fa-info-circle"></i> Legenda</div>
                        <div id="legendContent">
                            <div class="legend-item">
                                <div class="legend-color" style="background: #dc3545;"></div>
                                <span>Serangan Berat</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #ffc107;"></div>
                                <span>Serangan Sedang</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #198754;"></div>
                                <span>Serangan Ringan</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Panel -->
                    <div class="info-panel" id="infoPanel">
                        <div class="info-panel-header">
                            <h6 id="infoPanelTitle">Detail</h6>
                            <span class="info-panel-close" onclick="closeInfoPanel()">
                                <i class="fas fa-times"></i>
                            </span>
                        </div>
                        <div class="info-content" id="infoPanelContent">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mt-3">
    <div class="col-md-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3 id="statHama">0</h3>
                <p>Titik Hama</p>
            </div>
            <div class="icon"><i class="fas fa-bug"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3 id="statIrigasi">0</h3>
                <p>Daerah Irigasi</p>
            </div>
            <div class="icon"><i class="fas fa-water"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3 id="statRainfall">0</h3>
                <p>Stasiun Cuaca</p>
            </div>
            <div class="icon"><i class="fas fa-cloud-rain"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3 id="statKecamatan">0</h3>
                <p>Kecamatan</p>
            </div>
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

<script>
// Map Configuration
const mapConfig = {
    center: [-8.1845, 113.6681], // Jember coordinates
    zoom: 10,
    minZoom: 8,
    maxZoom: 18
};

// Global variables
let map;
let layers = {};
let activeFilters = {
    year: <?= date('Y') ?>,
    status: ''
};

// Layer colors
const layerColors = {
    hama: {
        Berat: '#dc3545',
        Sedang: '#ffc107', 
        Ringan: '#198754'
    },
    irigasi: '#0d6efd',
    rainfall: '#17a2b8',
    wind: '#6f42c1'
};

// Initialize map on page load
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    loadLayers();
    loadMapData();
});

// Initialize Leaflet map
function initMap() {
    map = L.map('dashboardMap', {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        minZoom: mapConfig.minZoom,
        maxZoom: mapConfig.maxZoom
    });
    
    // Add base tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Initialize layer groups
    layers.hama = L.markerClusterGroup({
        chunkedLoading: true,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        maxClusterRadius: 50
    });
    
    layers.irigasi = L.layerGroup();
    layers.rainfall = L.layerGroup();
    layers.wind = L.layerGroup();
    
    // Add hama layer by default
    map.addLayer(layers.hama);
}

// Load available layers
function loadLayers() {
    fetch('<?= BASE_URL ?>api/dashboard/map/layers')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderLayerControls(data.data);
            }
        })
        .catch(error => console.error('Error loading layers:', error));
}

// Render layer controls
function renderLayerControls(layersData) {
    const container = document.getElementById('layerList');
    container.innerHTML = '';
    
    layersData.forEach(layer => {
        const isActive = layer.id === 'hama'; // Hama active by default
        const div = document.createElement('div');
        div.className = 'layer-item' + (isActive ? ' active' : '');
        div.setAttribute('data-layer', layer.id);
        div.onclick = () => toggleLayer(layer.id);
        
        div.innerHTML = `
            <div class="layer-icon" style="background: ${layer.color};">
                <i class="fas fa-${layer.icon}"></i>
            </div>
            <div class="layer-info">
                <div class="name">${layer.name}</div>
                <div class="count" id="count-${layer.id}">Loading...</div>
            </div>
        `;
        
        container.appendChild(div);
    });
}

// Toggle layer visibility
function toggleLayer(layerId) {
    const item = document.querySelector(`.layer-item[data-layer="${layerId}"]`);
    const isActive = item.classList.toggle('active');
    
    if (isActive) {
        map.addLayer(layers[layerId]);
        loadLayerData(layerId);
    } else {
        map.removeLayer(layers[layerId]);
    }
    
    updateLegend();
}

// Load all map data
function loadMapData() {
    showLoading();
    
    // Load hama data (default layer)
    loadLayerData('hama');
    
    hideLoading();
}

// Load specific layer data
function loadLayerData(layerId) {
    const year = activeFilters.year;
    const status = activeFilters.status;
    
    switch(layerId) {
        case 'hama':
            loadHamaData(year, status);
            break;
        case 'irigasi':
            loadIrigasiData();
            break;
        case 'rainfall':
            loadWeatherData();
            break;
        case 'wind':
            loadWindData();
            break;
    }
}

// Load hama/pest data
function loadHamaData(year, status) {
    fetch(`<?= BASE_URL ?>api/dashboard/map/hama?year=${year}&status=${status}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderHamaMarkers(data.data);
                document.getElementById('count-hama').textContent = data.count + ' titik';
                document.getElementById('statHama').textContent = data.count;
            }
        })
        .catch(error => console.error('Error loading hama data:', error));
}

// Render hama markers
function renderHamaMarkers(geojson) {
    layers.hama.clearLayers();
    
    if (!geojson.features || geojson.features.length === 0) {
        return;
    }
    
    geojson.features.forEach(feature => {
        const coords = feature.geometry.coordinates;
        const props = feature.properties;
        
        // Determine marker color based on severity
        const color = layerColors.hama[props.tingkat_keparahan] || layerColors.hama.Ringan;
        
        const marker = L.circleMarker([coords[1], coords[0]], {
            radius: 8,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        });
        
        // Popup content
        marker.bindPopup(`
            <div style="min-width: 200px;">
                <h6 class="mb-2"><i class="fas fa-bug text-danger"></i> ${props.nama_opt || 'Unknown'}</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td><strong>Tanggal:</strong></td><td>${props.tanggal}</td></tr>
                    <tr><td><strong>Lokasi:</strong></td><td>${props.lokasi}</td></tr>
                    <tr><td><strong>Keparahan:</strong></td><td><span class="badge badge-${props.tingkat_keparahan === 'Berat' ? 'danger' : (props.tingkat_keparahan === 'Sedang' ? 'warning' : 'success')}">${props.tingkat_keparahan}</span></td></tr>
                    <tr><td><strong>Luas:</strong></td><td>${props.luas_serangan} Ha</td></tr>
                    <tr><td><strong>Populasi:</strong></td><td>${props.populasi}</td></tr>
                </table>
            </div>
        `);
        
        // Click event for info panel
        marker.on('click', function() {
            showInfoPanel('Laporan Hama', props);
        });
        
        layers.hama.addLayer(marker);
    });
}

// Load irigasi data
function loadIrigasiData() {
    fetch('<?= BASE_URL ?>api/dashboard/map/irigasi')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('count-irigasi').textContent = data.count + ' daerah';
                document.getElementById('statIrigasi').textContent = data.count;
                // Render irigasi data (would need coordinates in actual implementation)
            }
        })
        .catch(error => console.error('Error loading irigasi data:', error));
}

// Load weather data
function loadWeatherData() {
    fetch('<?= BASE_URL ?>api/dashboard/map/weather')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.rainfall) {
                const count = data.data.rainfall.length;
                document.getElementById('count-rainfall').textContent = count + ' stasiun';
                document.getElementById('statRainfall').textContent = count;
                renderWeatherMarkers(data.data.rainfall);
            }
        })
        .catch(error => console.error('Error loading weather data:', error));
}

// Render weather markers
function renderWeatherMarkers(data) {
    layers.rainfall.clearLayers();
    
    data.forEach(item => {
        if (!item.latitude || !item.longitude) return;
        
        const marker = L.circleMarker([item.latitude, item.longitude], {
            radius: 10,
            fillColor: layerColors.rainfall,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.7
        });
        
        marker.bindPopup(`
            <div style="min-width: 180px;">
                <h6 class="mb-2"><i class="fas fa-cloud-rain text-info"></i> ${item.kecamatan || 'Unknown'}</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td><strong>Rata-rata:</strong></td><td>${parseFloat(item.avg_rainfall).toFixed(1)} mm</td></tr>
                    <tr><td><strong>Maksimum:</strong></td><td>${parseFloat(item.max_rainfall).toFixed(1)} mm</td></tr>
                </table>
            </div>
        `);
        
        layers.rainfall.addLayer(marker);
    });
}

// Load wind data
function loadWindData() {
    // Similar to weather data
    document.getElementById('count-wind').textContent = '0 stasiun';
}

// Load kecamatan summary
function loadKecamatanSummary() {
    fetch(`<?= BASE_URL ?>api/dashboard/map/hamaSummary?year=${activeFilters.year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statKecamatan').textContent = data.count;
            }
        })
        .catch(error => console.error('Error loading kecamatan summary:', error));
    
    loadKecamatanSummary();
}

// Apply filters
function applyFilters() {
    activeFilters.year = document.getElementById('filterYear').value;
    activeFilters.status = document.getElementById('filterStatus').value;
    
    // Reload active layers
    document.querySelectorAll('.layer-item.active').forEach(item => {
        const layerId = item.getAttribute('data-layer');
        loadLayerData(layerId);
    });
    
    loadKecamatanSummary();
}

// Toggle panel expand/collapse
function togglePanel(panelId) {
    const panel = document.getElementById(panelId);
    const body = panel.querySelector('.filter-panel-body, .control-panel-body');
    body.style.display = body.style.display === 'none' ? 'block' : 'none';
}

// Show info panel
function showInfoPanel(title, data) {
    const panel = document.getElementById('infoPanel');
    const titleEl = document.getElementById('infoPanelTitle');
    const contentEl = document.getElementById('infoPanelContent');
    
    titleEl.textContent = title;
    
    let html = '';
    for (const [key, value] of Object.entries(data)) {
        if (value && key !== 'id') {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            html += `<div class="info-row"><span>${label}</span><span>${value}</span></div>`;
        }
    }
    
    contentEl.innerHTML = html;
    panel.classList.add('active');
}

// Close info panel
function closeInfoPanel() {
    document.getElementById('infoPanel').classList.remove('active');
}

// Update legend based on active layers
function updateLegend() {
    // Legend is already static for hama, could be dynamic based on active layers
}

// Show loading overlay
function showLoading() {
    document.getElementById('mapLoading').classList.remove('hidden');
}

// Hide loading overlay
function hideLoading() {
    document.getElementById('mapLoading').classList.add('hidden');
}

// Refresh map button
document.getElementById('btnRefreshMap').addEventListener('click', function() {
    loadMapData();
});

// Reset view button
document.getElementById('btnResetView').addEventListener('click', function() {
    map.setView(mapConfig.center, mapConfig.zoom);
});

// Also load kecamatan count on init
loadKecamatanSummary();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
