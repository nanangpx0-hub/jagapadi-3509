(function() {
  'use strict';

  if (typeof L === 'undefined') return;

  var originalMap = null;
  var currentLayer = 'hama';
  var currentTahun = window.currentTahun || new Date().getFullYear();
  var activeFilters = {
    opt: '',
    status: '',
    kecamatan: '',
    kondisi: ''
  };

  function initMapEnhancements() {
    if (!window.map) return;
    originalMap = window.map;

    createFilterPanel();
    createLegend();
    enhanceMapToggle();
  }

  function buildPopupContent(p, type) {
    var html = '<div style="font-size:13px;line-height:1.5;min-width:190px;">';
    if (p.nomor_laporan) html += '<strong style="font-size:14px;color:#222;">' + escapeHtml(p.nomor_laporan) + '</strong><br>';

    if (p.opt || type === 'hama') {
      if (p.opt) html += '<span style="color:#1a73e8;font-weight:600;">' + escapeHtml(p.opt) + '</span><br>';
      if (p.tingkat_keparahan) {
        var color = '#4caf50';
        if (p.tingkat_keparahan === 'Sedang') color = '#ff9800';
        if (p.tingkat_keparahan === 'Berat') color = '#f44336';
        html += 'Keparahan: <span style="color:' + color + ';font-weight:600;">' + escapeHtml(p.tingkat_keparahan) + '</span><br>';
      }
      if (p.id) {
        html += '<a href="/laporan-hama/' + p.id + '" style="display:inline-block;margin-top:6px;padding:4px 10px;background:#1a73e8;color:#fff;border-radius:4px;text-decoration:none;font-size:12px;">Detail Hama &raquo;</a><br>';
      }
    } else {
      if (p.nama_saluran) html += '<span style="color:#2e7d32;font-weight:600;">' + escapeHtml(p.nama_saluran) + '</span><br>';
      if (p.kondisi_fisik) html += 'Kondisi: <strong>' + escapeHtml(p.kondisi_fisik) + '</strong><br>';
      if (p.debit_air) html += 'Debit Air: ' + escapeHtml(p.debit_air) + '<br>';
      if (p.id) {
        html += '<a href="/laporan-irigasi/' + p.id + '" style="display:inline-block;margin-top:6px;padding:4px 10px;background:#2e7d32;color:#fff;border-radius:4px;text-decoration:none;font-size:12px;">Detail Irigasi &raquo;</a><br>';
      }
    }

    html += '<small style="color:#666;display:inline-block;margin-top:4px;">';
    if (p.tanggal) html += escapeHtml(p.tanggal);
    if (p.kecamatan) html += ' &middot; ' + escapeHtml(p.kecamatan);
    if (p.desa) html += ', ' + escapeHtml(p.desa);
    html += '</small></div>';
    return html;
  }

  window.buildPopupContent = buildPopupContent;

  function escapeHtml(t) {
    if (!t) return '';
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
  }

  function createFilterPanel() {
    var mapEl = document.getElementById('map');
    if (!mapEl) return;

    var panel = document.createElement('div');
    panel.className = 'map-filter-panel';
    panel.innerHTML = '<div class="map-filter-header" onclick="this.parentNode.classList.toggle(\'collapsed\')">Filter Peta &#9660;</div>' +
      '<div class="map-filter-body">' +
      '<div class="map-filter-group" id="groupOpt"><label>OPT</label><select id="filterOpt"><option value="">Semua OPT</option></select></div>' +
      '<div class="map-filter-group" id="groupKondisi" style="display:none;"><label>Kondisi Irigasi</label><select id="filterKondisi"><option value="">Semua Kondisi</option><option value="Baik">Baik</option><option value="Rusak Ringan">Rusak Ringan</option><option value="Rusak Sedang">Rusak Sedang</option><option value="Rusak Berat">Rusak Berat</option></select></div>' +
      '<div class="map-filter-group"><label>Status</label><select id="filterStatus"><option value="">Semua Status</option><option value="Submitted">Submitted</option><option value="Diverifikasi">Diverifikasi</option></select></div>' +
      '<div class="map-filter-group"><label>Kecamatan</label><select id="filterKecamatan"><option value="">Semua Kecamatan</option></select></div>' +
      '<button id="applyFilter" style="width:100%;padding:8px;background:#1a73e8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;margin-top:4px;">Terapkan Filter</button>' +
      '</div>';

    mapEl.parentNode.insertBefore(panel, mapEl.nextSibling);

    document.getElementById('filterOpt').addEventListener('change', function() {
      activeFilters.opt = this.value;
    });
    document.getElementById('filterKondisi').addEventListener('change', function() {
      activeFilters.kondisi = this.value;
    });
    document.getElementById('filterStatus').addEventListener('change', function() {
      activeFilters.status = this.value;
    });
    document.getElementById('filterKecamatan').addEventListener('change', function() {
      activeFilters.kecamatan = this.value;
    });
    document.getElementById('applyFilter').addEventListener('click', function() {
      loadFilteredMap();
    });
  }

  function loadFilteredMap() {
    var params = '?tahun=' + currentTahun + '&limit=1000';
    if (activeFilters.status) params += '&status=' + encodeURIComponent(activeFilters.status);
    if (activeFilters.kecamatan) params += '&kecamatan_id=' + encodeURIComponent(activeFilters.kecamatan);

    if (currentLayer === 'hama' && activeFilters.opt) {
      params += '&master_opt_id=' + encodeURIComponent(activeFilters.opt);
    }
    if (currentLayer === 'irigasi' && activeFilters.kondisi) {
      params += '&kondisi_fisik=' + encodeURIComponent(activeFilters.kondisi);
    }

    var url = currentLayer === 'hama'
      ? '/dashboard/map/hama' + params
      : '/dashboard/map/irigasi' + params;

    if (typeof window.loadGeoJSON === 'function') {
      window.loadGeoJSON(currentLayer, url);
    }
  }

  function enhanceMapToggle() {
    var origSwitch = window.switchMapLayer;
    if (origSwitch) {
      window.switchMapLayer = function(type) {
        currentLayer = type;
        var groupOpt = document.getElementById('groupOpt');
        var groupKondisi = document.getElementById('groupKondisi');
        if (groupOpt) groupOpt.style.display = type === 'hama' ? 'block' : 'none';
        if (groupKondisi) groupKondisi.style.display = type === 'irigasi' ? 'block' : 'none';
        origSwitch(type);
      };
    }
  }

  function createLegend() {
    var mapEl = document.getElementById('map');
    if (!mapEl) return;

    var legend = document.createElement('div');
    legend.className = 'map-legend';
    legend.innerHTML =
      '<div class="map-legend-title">Legenda Keparahan / Kondisi</div>' +
      '<div class="map-legend-item"><span class="legend-dot" style="background:#f44336;"></span>Berat / Rusak Berat</div>' +
      '<div class="map-legend-item"><span class="legend-dot" style="background:#ff9800;"></span>Sedang / Rusak Sedang</div>' +
      '<div class="map-legend-item"><span class="legend-dot" style="background:#4caf50;"></span>Ringan / Baik</div>';

    mapEl.appendChild(legend);
  }

  function loadFilterOpts() {
    fetch('/api/v1/opt')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var sel = document.getElementById('filterOpt');
        if (!sel) return;
        var list = data.data || data;
        list.forEach(function(o) {
          var opt = document.createElement('option');
          opt.value = o.id;
          opt.textContent = o.nama_opt || o.nama;
          sel.appendChild(opt);
        });
      })
      .catch(function() {});

    fetch('/wilayah/kecamatan-json?kabupaten_id=1')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var sel = document.getElementById('filterKecamatan');
        if (!sel) return;
        var list = data.data || data;
        list.forEach(function(k) {
          var opt = document.createElement('option');
          opt.value = k.id;
          opt.textContent = k.nama_kecamatan;
          sel.appendChild(opt);
        });
      })
      .catch(function() {});
  }

  document.addEventListener('DOMContentLoaded', function() {
    initMapEnhancements();
    loadFilterOpts();
  });
})();
