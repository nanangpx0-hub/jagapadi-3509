(function() {
  'use strict';

  let chartHama = null;
  let chartIrigasi = null;
  let map = null;
  let currentLayer = 'hama';
  let geoLayer = null;

  // Fetch charts
  function loadCharts() {
    fetch('/dashboard/charts/hama.json?tahun=' + currentTahun)
      .then(r => r.json())
      .then(data => renderChartHama(data))
      .catch(() => {});

    fetch('/dashboard/charts/irigasi.json?tahun=' + currentTahun)
      .then(r => r.json())
      .then(data => renderChartIrigasi(data))
      .catch(() => {});
  }

  function renderChartHama(data) {
    if (!data || !data.labels) return;
    if (chartHama) chartHama.destroy();

    const ctx = document.getElementById('chartHama');
    if (!ctx) return;
    chartHama = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          { label: 'Submitted', data: data.series.submitted, backgroundColor: '#f57c00', borderRadius: 3 },
          { label: 'Diverifikasi', data: data.series.diverifikasi, backgroundColor: '#2e7d32', borderRadius: 3 },
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  function renderChartIrigasi(data) {
    if (!data || !data.labels) return;
    if (chartIrigasi) chartIrigasi.destroy();

    const ctx = document.getElementById('chartIrigasi');
    if (!ctx) return;
    chartIrigasi = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          { label: 'Submitted', data: data.series.submitted, backgroundColor: '#f57c00', borderRadius: 3 },
          { label: 'Diverifikasi', data: data.series.diverifikasi, backgroundColor: '#2e7d32', borderRadius: 3 },
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  // Map
  function initMap() {
    if (map) return;
    map = L.map('map').setView([-8.17, 113.70], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    loadGeoJSON('hama');
  }

  function loadGeoJSON(type) {
    if (geoLayer) { map.removeLayer(geoLayer); geoLayer = null; }

    const url = type === 'hama'
      ? '/dashboard/map/hama.json?tahun=' + currentTahun + '&limit=500'
      : '/dashboard/map/irigasi.json?tahun=' + currentTahun + '&limit=500';

    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (!data || !data.features) return;
        geoLayer = L.geoJSON(data, {
          pointToLayer: function(feature, latlng) {
            return L.circleMarker(latlng, {
              radius: 8,
              fillColor: type === 'hama' ? '#1a73e8' : '#2e7d32',
              color: '#fff',
              weight: 2,
              opacity: 1,
              fillOpacity: 0.7
            });
          },
          onEachFeature: function(feature, layer) {
            const p = feature.properties || {};
            layer.bindPopup('<strong>' + (p.popup || '') + '</strong><br>' +
              (p.tanggal || '') + ' &middot; ' + (p.desa || '') + ', ' + (p.kecamatan || ''));
          }
        }).addTo(map);

        if (data.features.length > 0) {
          map.fitBounds(geoLayer.getBounds(), { padding: [30, 30] });
        }
      })
      .catch(() => {});
  }

  window.switchMapLayer = function(type) {
    currentLayer = type;
    document.getElementById('toggleHama').className = type === 'hama' ? 'active' : '';
    document.getElementById('toggleIrigasi').className = type === 'irigasi' ? 'active' : '';
    loadGeoJSON(type);
  };

  // Init
  document.addEventListener('DOMContentLoaded', function() {
    loadCharts();
    initMap();
  });
})();
