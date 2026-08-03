<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>
<style>
.map-container { position:relative; height:calc(100vh - 200px); min-height:500px; border-radius:8px; overflow:hidden; }
#dashboardMap { height:100%; width:100%; z-index:1; }
.map-controls { position:absolute; top:10px; right:10px; z-index:1000; background:#fff; border-radius:8px; max-width:280px; }
.control-panel-header { padding:12px 15px; background:#198754; color:#fff; border-radius:8px 8px 0 0; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
.control-panel-header h6 { margin:0; font-weight:600; }
.control-panel-body { padding:15px; max-height:400px; overflow-y:auto; }
.layer-item { display:flex; align-items:center; padding:8px 10px; margin-bottom:8px; background:#f8f9fa; border-radius:6px; cursor:pointer; }
.layer-item.active { background:#d1e7dd; border-left:3px solid #198754; }
.layer-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; margin-right:10px; font-size:14px; }
.layer-info { flex:1; }
.layer-info .name { font-weight:600; font-size:13px; color:#333; }
.layer-info .count { font-size:11px; color:#666; }
.filter-panel { position:absolute; top:10px; left:10px; z-index:1000; background:#fff; border-radius:8px; max-width:250px; }
.filter-panel-header { padding:12px 15px; background:#0d6efd; color:#fff; border-radius:8px 8px 0 0; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
.filter-panel-header h6 { margin:0; font-weight:600; }
.filter-panel-body { padding:15px; }
.filter-group { margin-bottom:15px; }
.filter-group label { font-size:12px; font-weight:600; color:#555; margin-bottom:5px; display:block; }
.filter-group select, .filter-group input { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px; font-size:13px; }
.legend-panel { position:absolute; bottom:30px; left:10px; z-index:1000; background:#fff; border-radius:8px; padding:12px 15px; max-width:200px; }
.legend-title { font-weight:600; font-size:13px; margin-bottom:10px; color:#333; }
.legend-item { display:flex; align-items:center; margin-bottom:6px; font-size:12px; }
.legend-color { width:16px; height:16px; border-radius:4px; margin-right:8px; }
.info-panel { position:absolute; bottom:30px; right:10px; z-index:1000; background:#fff; border-radius:8px; padding:15px; max-width:300px; display:none; }
.info-panel.active { display:block; }
.info-panel-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.info-panel-header h6 { margin:0; font-weight:600; }
.info-panel-close { cursor:pointer; color:#999; }
.info-content { font-size:13px; }
.info-content .info-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #eee; }
.info-content .info-row:last-child { border-bottom:none; }
.map-loading { position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); display:flex; align-items:center; justify-content:center; z-index:2000; }
.map-loading.hidden { display:none; }
@media (max-width:768px) { .map-container { height:calc(100vh - 150px); } .map-controls { max-width:200px; right:6px; top:6px; } .filter-panel { max-width:200px; left:6px; top:6px; } .control-panel-body,.filter-panel-body { padding:10px; } .legend-panel { display:none; } }

/* Nonaktifkan animasi timbul tenggelam (pulse/blink/fade/spin) agar seluruh elemen pada peta tampil statis */
*, *::before, *::after {
    animation: none !important;
}
</style>

<div class="row mb-3">
 <div class="col-12">
 <div class="d-flex justify-content-between align-items-center">
 <div>
 <h4 class="mb-1">Peta Sebaran Data</h4>
 <p class="text-muted mb-0">Visualisasi data sebaran hama, irigasi, dan cuaca pada peta interaktif</p>
 </div>
 <div>
 <button class="btn btn-outline-primary btn-sm" id="btnRefreshMap">Refresh</button>
 <button class="btn btn-outline-secondary btn-sm" id="btnResetView">Reset View</button>
 </div>
 </div>
 </div>
</div>

<div class="row">
 <div class="col-12">
 <div class="card">
 <div class="card-body p-0">
 <div class="map-container">
 <div class="map-loading hidden" id="mapLoading">
 <div class="text-center">
 <p class="mb-0">Memuat data peta...</p>
 </div>
 </div>
 <div id="dashboardMap"></div>
 <div class="filter-panel" id="filterPanel">
 <div class="filter-panel-header" onclick="togglePanel('filterPanel')">
 <h6>Filter</h6>
 <i class="fas fa-chevron-down"></i>
 </div>
 <div class="filter-panel-body">
 <div class="filter-group">
 <label>Tahun</label>
 <select id="filterYear">
 <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
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
 <button class="btn btn-primary btn-sm btn-block" onclick="applyFilters()">Terapkan Filter</button>
 </div>
 </div>
 <div class="map-controls" id="layerPanel">
 <div class="control-panel-header" onclick="togglePanel('layerPanel')">
 <h6>Layer Peta</h6>
 <i class="fas fa-chevron-down"></i>
 </div>
 <div class="control-panel-body" id="layerList"></div>
 </div>
 <div class="legend-panel" id="legendPanel">
 <div class="legend-title">Legenda</div>
 <div id="legendContent">
 <div class="legend-item"><div class="legend-color" style="background:#dc3545"></div><span>Serangan Hama Berat</span></div>
 <div class="legend-item"><div class="legend-color" style="background:#ffc107"></div><span>Serangan Hama Sedang</span></div>
 <div class="legend-item"><div class="legend-color" style="background:#198754"></div><span>Serangan Hama Ringan</span></div>
 <div class="legend-item"><div class="legend-color" style="background:#0d6efd"></div><span>Infrastruktur Irigasi</span></div>
 <div class="legend-item"><div class="legend-color" style="background:#17a2b8"></div><span>Stasiun Curah Hujan</span></div>
 </div>
 </div>
 <div class="info-panel" id="infoPanel">
 <div class="info-panel-header">
 <h6 id="infoPanelTitle">Detail</h6>
 <span class="info-panel-close" onclick="closeInfoPanel()">&times;</span>
 </div>
 <div class="info-content" id="infoPanelContent"></div>
 </div>
 </div>
 </div>
 </div>
 </div>
</div>

<div class="row mt-3">
 <div class="col-md-3 col-6">
 <div class="small-box bg-danger">
 <div class="inner"><h3 id="statHama">0</h3><p>Titik Hama</p></div>
 <div class="icon"><i class="fas fa-bug"></i></div>
 </div>
 </div>
 <div class="col-md-3 col-6">
 <div class="small-box bg-info">
 <div class="inner"><h3 id="statIrigasi">0</h3><p>Daerah Irigasi</p></div>
 <div class="icon"><i class="fas fa-water"></i></div>
 </div>
 </div>
 <div class="col-md-3 col-6">
 <div class="small-box bg-success">
 <div class="inner"><h3 id="statRainfall">0</h3><p>Stasiun Cuaca</p></div>
 <div class="icon"><i class="fas fa-cloud-rain"></i></div>
 </div>
 </div>
 <div class="col-md-3 col-6">
 <div class="small-box bg-warning">
 <div class="inner"><h3 id="statKecamatan">0</h3><p>Kecamatan</p></div>
 <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
 </div>
 </div>
</div>

<script>
var mapConfig = { center: [-8.1845, 113.6681], zoom: 10, minZoom: 8, maxZoom: 18 };
var map;
var layers = {};
var activeFilters = { year: <?= date('Y') ?>, status: '' };
var layerColors = {
  hama: { Berat: '#dc3545', Sedang: '#ffc107', Ringan: '#198754' },
  irigasi: '#0d6efd', rainfall: '#17a2b8', wind: '#6f42c1'
};
var loadingTimer = 0;

function initMap() {
  map = L.map('dashboardMap', {
    center: mapConfig.center, zoom: mapConfig.zoom,
    minZoom: mapConfig.minZoom, maxZoom: mapConfig.maxZoom
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  layers.hama = L.markerClusterGroup({ chunkedLoading: true, spiderfyOnMaxZoom: true, maxClusterRadius: 50 });
  layers.irigasi = L.layerGroup();
  layers.rainfall = L.layerGroup();
  layers.wind = L.layerGroup();
  map.addLayer(layers.hama);
}

function loadLayers() {
  fetch('<?= BASE_URL ?>api/dashboard/map/layers')
    .then(function(r) { return r.json(); })
    .then(function(data) { if (data.success) { renderLayerControls(data.data); } })
    .catch(function() {});
}

function renderLayerControls(layersData) {
  var container = document.getElementById('layerList');
  container.innerHTML = '';
  layersData.forEach(function(layer) {
    var isActive = layer.id === 'hama';
    var div = document.createElement('div');
    div.className = 'layer-item' + (isActive ? ' active' : '');
    div.setAttribute('data-layer', layer.id);
    div.onclick = function() { toggleLayer(layer.id); };
    div.innerHTML = '<div class="layer-icon" style="background:' + layer.color + '"><i class="fas fa-' + layer.icon + '"></i></div><div class="layer-info"><div class="name">' + layer.name + '</div><div class="count" id="count-' + layer.id + '">Loading...</div></div>';
    container.appendChild(div);
  });
}

function toggleLayer(layerId) {
  var item = document.querySelector('.layer-item[data-layer="' + layerId + '"]');
  var isActive = item.classList.toggle('active');
  if (isActive) { map.addLayer(layers[layerId]); loadLayerData(layerId); }
  else { map.removeLayer(layers[layerId]); }
}

function loadMapData() {
  showLoading();
  loadLayerData('hama');
  setTimeout(hideLoading, 3000);
}

function loadLayerData(layerId) {
  var year = activeFilters.year;
  var status = activeFilters.status;
  if (layerId === 'hama') { loadHamaData(year, status); }
  else if (layerId === 'irigasi') { loadIrigasiData(); }
  else if (layerId === 'rainfall') { loadWeatherData(); }
  else if (layerId === 'wind') { loadWindData(); }
}

function loadHamaData(year, status) {
  fetch('<?= BASE_URL ?>api/dashboard/map/hama?year=' + year + '&status=' + status)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        renderHamaMarkers(data.data);
        document.getElementById('count-hama').textContent = (data.count || 0) + ' titik';
        document.getElementById('statHama').textContent = data.count || 0;
      }
      hideLoading();
    })
    .catch(function() { hideLoading(); });
}

function renderHamaMarkers(geojson) {
  layers.hama.clearLayers();
  if (!geojson || !geojson.features || geojson.features.length === 0) { return; }
  geojson.features.forEach(function(feature) {
    var coords = feature.geometry.coordinates;
    var props = feature.properties || {};
    var color = layerColors.hama[props.tingkat_keparahan] || layerColors.hama.Ringan;
    var marker = L.circleMarker([coords[1], coords[0]], {
      radius: 8, fillColor: color, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.8
    });
    var opt = props.nama_opt || 'Unknown';
    var tgl = props.tanggal || '-';
    var lok = props.lokasi || '-';
    var kep = props.tingkat_keparahan || '-';
    var luas = props.luas_serangan || '0';
    var pop = props.populasi || '0';
    marker.bindPopup('<div style="min-width:200px;"><h6 class="mb-2">' + opt + '</h6><table class="table table-sm table-borderless mb-0"><tr><td><strong>Tanggal:</strong></td><td>' + tgl + '</td></tr><tr><td><strong>Lokasi:</strong></td><td>' + lok + '</td></tr><tr><td><strong>Keparahan:</strong></td><td>' + kep + '</td></tr><tr><td><strong>Luas:</strong></td><td>' + luas + ' Ha</td></tr><tr><td><strong>Populasi:</strong></td><td>' + pop + '</td></tr></table></div>');
    marker.on('click', function() {
      showInfoPanel('Laporan Hama', { 'OPT': opt, 'Tanggal': tgl, 'Lokasi': lok, 'Keparahan': kep, 'Luas': luas + ' Ha', 'Populasi': pop });
    });
    layers.hama.addLayer(marker);
  });
}

function loadIrigasiData() {
  fetch('<?= BASE_URL ?>api/dashboard/map/irigasi')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        document.getElementById('count-irigasi').textContent = (data.count || 0) + ' daerah';
        document.getElementById('statIrigasi').textContent = data.count || 0;
        renderIrigasiMarkers(data.data);
      }
    })
    .catch(function() {});
}

function renderIrigasiMarkers(data) {
  layers.irigasi.clearLayers();
  if (!data || data.length === 0) { return; }
  var byKec = {};
  data.forEach(function(item) {
    var kec = item.kecamatan || 'Unknown';
    if (!byKec[kec]) { byKec[kec] = { items: [], latitude: item.latitude, longitude: item.longitude }; }
    byKec[kec].items.push(item);
  });
  Object.keys(byKec).forEach(function(kecamatan) {
    var group = byKec[kecamatan];
    if (!group.latitude || !group.longitude) return;
    var totalDebit = 0;
    group.items.forEach(function(i) { totalDebit += parseFloat(i.avg_debit || 0); });
    var avgDebit = group.items.length > 0 ? totalDebit / group.items.length : 0;
    var marker = L.circleMarker([parseFloat(group.latitude), parseFloat(group.longitude)], {
      radius: 14, fillColor: layerColors.irigasi, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.6
    });
    var popupHtml = '<div style="min-width:220px;"><h6 class="mb-2">' + kecamatan + '</h6><table class="table table-sm table-borderless mb-0">';
    var sliced = group.items.slice(0, 5);
    sliced.forEach(function(item) {
      popupHtml += '<tr><td><strong>' + (item.daerah_irigasi || '-') + ':</strong></td><td>' + parseFloat(item.avg_debit || 0).toFixed(1) + ' m\u00B3/s (rata-rata)</td></tr>';
    });
    if (group.items.length > 5) {
      popupHtml += '<tr><td colspan="2"><em>...dan ' + (group.items.length - 5) + ' daerah lainnya</em></td></tr>';
    }
    popupHtml += '<tr><td><strong>Total daerah:</strong></td><td>' + group.items.length + '</td></tr>';
    popupHtml += '<tr><td><strong>Rata-rata debit:</strong></td><td>' + avgDebit.toFixed(1) + ' m\u00B3/s</td></tr>';
    popupHtml += '</table></div>';
    marker.bindPopup(popupHtml);
    marker.on('click', function() {
      showInfoPanel('Irigasi - ' + kecamatan, { 'Kecamatan': kecamatan, 'Jumlah Daerah': group.items.length, 'Rata-rata Debit': avgDebit.toFixed(1) + ' m\u00B3/s', 'Periode': '30 hari terakhir' });
    });
    layers.irigasi.addLayer(marker);
  });
}

function loadWeatherData() {
  fetch('<?= BASE_URL ?>api/dashboard/map/weather')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success && data.data && data.data.rainfall) {
        var count = data.data.rainfall.length;
        document.getElementById('count-rainfall').textContent = count + ' stasiun';
        document.getElementById('statRainfall').textContent = count;
        renderWeatherMarkers(data.data.rainfall);
      }
    })
    .catch(function() {});
}

function renderWeatherMarkers(data) {
  layers.rainfall.clearLayers();
  if (!data) { return; }
  data.forEach(function(item) {
    if (!item || !item.latitude || !item.longitude) return;
    var marker = L.circleMarker([item.latitude, item.longitude], {
      radius: 10, fillColor: layerColors.rainfall, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.7
    });
    var kec = item.kecamatan || 'Unknown';
    var avg = item.avg_rainfall ? parseFloat(item.avg_rainfall).toFixed(1) : '0.0';
    var max = item.max_rainfall ? parseFloat(item.max_rainfall).toFixed(1) : '0.0';
    marker.bindPopup('<div style="min-width:180px;"><h6 class="mb-2">' + kec + '</h6><table class="table table-sm table-borderless mb-0"><tr><td><strong>Rata-rata:</strong></td><td>' + avg + ' mm</td></tr><tr><td><strong>Maksimum:</strong></td><td>' + max + ' mm</td></tr></table></div>');
    layers.rainfall.addLayer(marker);
  });
}

function loadWindData() {
  document.getElementById('count-wind').textContent = '0 stasiun';
}

function loadKecamatanSummary() {
  fetch('<?= BASE_URL ?>api/dashboard/map/hamaSummary?year=' + activeFilters.year)
    .then(function(r) { return r.json(); })
    .then(function(data) { if (data.success) { document.getElementById('statKecamatan').textContent = data.count || 0; } })
    .catch(function() {});
}

function applyFilters() {
  activeFilters.year = document.getElementById('filterYear').value;
  activeFilters.status = document.getElementById('filterStatus').value;
  document.querySelectorAll('.layer-item.active').forEach(function(item) {
    loadLayerData(item.getAttribute('data-layer'));
  });
  loadKecamatanSummary();
}

function togglePanel(panelId) {
  var panel = document.getElementById(panelId);
  var body = panel.querySelector('.filter-panel-body, .control-panel-body');
  body.style.display = body.style.display === 'none' ? 'block' : 'none';
}

function showInfoPanel(title, data) {
  var panel = document.getElementById('infoPanel');
  document.getElementById('infoPanelTitle').textContent = title;
  var html = '';
  if (data) {
    Object.keys(data).forEach(function(key) {
      var val = data[key];
      if (val && key !== 'id') {
        html += '<div class="info-row"><span>' + key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }) + '</span><span>' + val + '</span></div>';
      }
    });
  }
  document.getElementById('infoPanelContent').innerHTML = html;
  panel.classList.add('active');
}

function closeInfoPanel() {
  document.getElementById('infoPanel').classList.remove('active');
}

function showLoading() {
  document.getElementById('mapLoading').classList.remove('hidden');
}

function hideLoading() {
  document.getElementById('mapLoading').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  initMap();
  loadLayers();
  loadMapData();
  loadKecamatanSummary();
});

document.getElementById('btnRefreshMap').addEventListener('click', function() { loadMapData(); });
document.getElementById('btnResetView').addEventListener('click', function() { map.setView(mapConfig.center, mapConfig.zoom); });
</script>
<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
