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
        animation: false,
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
        animation: false,
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  // Map with animations disabled
  function initMap() {
    if (map) return;
    map = L.map('map', {
      zoomAnimation: false,
      fadeAnimation: false,
      markerZoomAnimation: false
    }).setView([-8.17, 113.70], 11);
    window.map = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    loadGeoJSON('hama');
  }

  function loadGeoJSON(type, customUrl) {
    if (!map) return;
    if (geoLayer) { map.removeLayer(geoLayer); geoLayer = null; }

    const url = customUrl || (type === 'hama'
      ? '/dashboard/map/hama?tahun=' + currentTahun + '&limit=500'
      : '/dashboard/map/irigasi?tahun=' + currentTahun + '&limit=500');

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
              fillOpacity: 0.8
            });
          },
          onEachFeature: function(feature, layer) {
            const p = feature.properties || {};
            if (typeof window.buildPopupContent === 'function') {
              layer.bindPopup(window.buildPopupContent(p, type));
            } else {
              let popupHtml = '<div style="font-size:13px;line-height:1.5;">';
              if (p.nomor_laporan) popupHtml += '<strong>' + p.nomor_laporan + '</strong><br>';
              if (type === 'hama') {
                if (p.opt) popupHtml += '<span style="color:#1a73e8;">' + p.opt + '</span> &middot; ';
                if (p.tingkat_keparahan) popupHtml += '<b>' + p.tingkat_keparahan + '</b><br>';
                if (p.id) popupHtml += '<a href="/laporan-hama/' + p.id + '" style="color:#1a73e8;text-decoration:none;font-weight:600;">Lihat Detail &raquo;</a><br>';
              } else {
                if (p.nama_saluran) popupHtml += '<span style="color:#2e7d32;">' + p.nama_saluran + '</span><br>';
                if (p.kondisi_fisik) popupHtml += 'Kondisi: ' + p.kondisi_fisik + '<br>';
                if (p.debit_air) popupHtml += 'Debit: ' + p.debit_air + '<br>';
                if (p.id) popupHtml += '<a href="/laporan-irigasi/' + p.id + '" style="color:#2e7d32;text-decoration:none;font-weight:600;">Lihat Detail &raquo;</a><br>';
              }
              popupHtml += '<small style="color:#666;">' + (p.tanggal || '') + ' &middot; ' + (p.desa || '') + ', ' + (p.kecamatan || '') + '</small></div>';
              layer.bindPopup(popupHtml);
            }
          }
        }).addTo(map);

        if (data.features.length > 0) {
          map.fitBounds(geoLayer.getBounds(), { padding: [30, 30], animate: false });
        }
      })
      .catch(() => {});
  }

  window.loadGeoJSON = loadGeoJSON;

  window.switchMapLayer = function(type) {
    currentLayer = type;
    document.getElementById('toggleHama').className = type === 'hama' ? 'active' : '';
    document.getElementById('toggleIrigasi').className = type === 'irigasi' ? 'active' : '';
    loadGeoJSON(type);
  };

  // Responsiveness handling
  window.addEventListener('resize', function() {
    if (map) {
      map.invalidateSize();
    }
  });

  // Init
  document.addEventListener('DOMContentLoaded', function() {
    loadCharts();
    initMap();
  });
})();
