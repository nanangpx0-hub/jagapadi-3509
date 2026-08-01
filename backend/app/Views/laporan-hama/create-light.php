<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:#f0f2f5; color:#333; min-height:100vh; }
.light-header { background:#1a73e8; color:#fff; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
.light-header h1 { font-size:17px; font-weight:600; flex:1; }
.light-header .btn-back { color:#fff; text-decoration:none; font-size:22px; line-height:1; padding:4px; }
.form-card { margin:12px; background:#fff; border-radius:10px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:13px; font-weight:500; color:#555; margin-bottom:4px; }
.form-group input,.form-group select,.form-group textarea { width:100%; padding:12px 14px; border:1px solid #d0d0d0; border-radius:8px; font-size:16px; background:#fff; -webkit-appearance:none; appearance:none; }
.form-group input:focus,.form-group select:focus,.form-group textarea:focus { outline:none; border-color:#1a73e8; box-shadow:0 0 0 3px rgba(26,115,232,0.12); }
.form-group .error { color:#c62828; font-size:12px; margin-top:4px; display:none; }
.form-group.has-error input,.form-group.has-error select,.form-group.has-error textarea { border-color:#c62828; background:#fff5f5; }
.form-group.has-error .error { display:block; }
.form-group .char-counter { text-align:right; font-size:11px; color:#999; margin-top:2px; }
.inline-group { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.inline-group .form-group { margin-bottom:0; }
.btn-group { display:flex; gap:10px; margin-top:20px; }
.btn { flex:1; padding:14px; border:none; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; text-align:center; }
.btn-primary { background:#1a73e8; color:#fff; }
.btn-success { background:#2e7d32; color:#fff; }
.btn-secondary { background:#e0e0e0; color:#333; }
.btn:disabled { opacity:0.5; cursor:not-allowed; }
.gps-status { display:flex; align-items:center; gap:6px; font-size:12px; color:#888; padding:4px 0; }
.gps-status.loading { color:#1a73e8; }
.gps-status.success { color:#2e7d32; }
.gps-status.error { color:#c62828; }
.gps-status .spinner { width:14px; height:14px; border:2px solid #ddd; border-top-color:#1a73e8; border-radius:50%; }
.severity-group { display:flex; gap:8px; }
.severity-btn { flex:1; padding:10px; border:2px solid #e0e0e0; border-radius:8px; background:#fff; font-size:14px; font-weight:500; cursor:pointer; text-align:center; transition:all 0.15s; }
.severity-btn.selected- ringan { border-color:#4caf50; background:#e8f5e9; color:#2e7d32; }
.severity-btn.selected-sedang { border-color:#ff9800; background:#fff3e0; color:#e65100; }
.severity-btn.selected-berat { border-color:#f44336; background:#ffebee; color:#c62828; }
.photo-section { margin-top:4px; }
.photo-section input[type=file] { display:none; }
.photo-btn { display:flex; align-items:center; justify-content:center; gap:8px; padding:12px; border:2px dashed #d0d0d0; border-radius:8px; cursor:pointer; font-size:14px; color:#555; }
.photo-btn.has-photo { border-style:solid; border-color:#2e7d32; background:#e8f5e9; }
.photo-preview { margin-top:8px; position:relative; display:none; }
.photo-preview img { width:100%; border-radius:8px; max-height:200px; object-fit:cover; }
.photo-preview .remove-photo { position:absolute; top:6px; right:6px; width:28px; height:28px; border-radius:50%; background:rgba(0,0,0,0.6); color:#fff; border:none; font-size:16px; cursor:pointer; line-height:28px; text-align:center; }
.offline-badge { display:none; background:#ff9800; color:#fff; font-size:11px; padding:2px 8px; border-radius:4px; margin-left:8px; }
@media (min-width:480px) { .form-card { max-width:480px; margin:20px auto; } }
.hidden { display:none !important; }
</style>

<div class="light-header">
  <a href="/laporan-hama" class="btn-back">&larr;</a>
  <h1>Laporan Cepat</h1>
  <span class="offline-badge" id="offlineBadge">Offline</span>
</div>

<form id="lightForm" method="POST" action="/laporan-hama/light/store" enctype="multipart/form-data">
  <?= \App\Core\Security::csrfField() ?>
  <input type="hidden" name="action" id="formAction" value="submit">

  <div class="form-card">
    <div class="inline-group">
      <div class="form-group" id="fg-tanggal">
        <label for="tanggal">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= \App\Core\Security::e($oldInput['tanggal'] ?? date('Y-m-d')) ?>" required>
        <div class="error"></div>
      </div>
      <div class="form-group" id="fg-opt">
        <label for="master_opt_id">OPT</label>
        <select id="master_opt_id" name="master_opt_id" required>
          <option value="">Pilih OPT</option>
          <?php foreach ($optList as $o): ?>
          <option value="<?= (int) $o['id'] ?>" <?= (isset($oldInput['master_opt_id']) && (int) $oldInput['master_opt_id'] === (int) $o['id']) ? 'selected' : '' ?>><?= \App\Core\Security::e($o['nama_opt']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="error"></div>
      </div>
    </div>

    <div class="form-group" id="fg-kecamatan">
      <label for="kecamatan_id">Kecamatan</label>
      <select id="kecamatan_id" name="kecamatan_id" required>
        <option value="">Pilih Kecamatan</option>
        <?php foreach ($kecamatanList as $k): ?>
        <option value="<?= (int) $k['id'] ?>" <?= (isset($oldInput['kecamatan_id']) && (int) $oldInput['kecamatan_id'] === (int) $k['id']) ? 'selected' : '' ?>><?= \App\Core\Security::e($k['nama_kecamatan']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="error"></div>
    </div>

    <div class="form-group" id="fg-desa">
      <label for="desa_id">Desa <span style="color:#999;font-size:11px;">(opsional)</span></label>
      <select id="desa_id" name="desa_id">
        <option value="">Pilih Desa</option>
      </select>
      <div class="error"></div>
    </div>
  </div>

  <div class="form-card">
    <label style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:6px;">Tingkat Keparahan</label>
    <div class="severity-group" id="severityGroup">
      <button type="button" class="severity-btn" data-value="Ringan">Ringan</button>
      <button type="button" class="severity-btn" data-value="Sedang">Sedang</button>
      <button type="button" class="severity-btn" data-value="Berat">Berat</button>
    </div>
    <input type="hidden" name="tingkat_keparahan" id="tingkat_keparahan" value="<?= \App\Core\Security::e($oldInput['tingkat_keparahan'] ?? '') ?>">
    <div class="form-group" id="fg-keparahan" style="margin-top:0;">
      <div class="error" style="display:<?= !empty($errors['tingkat_keparahan']) ? 'block' : 'none' ?>;"><?= \App\Core\Security::e($errors['tingkat_keparahan'] ?? '') ?></div>
    </div>
  </div>

  <div class="form-card">
    <div class="form-group" id="fg-lokasi">
      <label for="catatan">Catatan / Lokasi</label>
      <textarea id="catatan" name="catatan" rows="2" minlength="10" maxlength="5000" placeholder="Contoh: Sawah Pak RT 02, dekat jembatan..." required><?= \App\Core\Security::e($oldInput['catatan'] ?? '') ?></textarea>
      <div class="char-counter"><span id="catatanCount">0</span>/5000</div>
      <div class="error"></div>
    </div>

    <div class="form-group">
      <label style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:6px;">Lokasi GPS</label>
      <div class="gps-status" id="gpsStatus">
        <span class="spinner" id="gpsSpinner"></span>
        <span id="gpsText">Mendeteksi lokasi...</span>
      </div>
      <input type="hidden" name="latitude" id="latitude" value="<?= \App\Core\Security::e($oldInput['latitude'] ?? '') ?>">
      <input type="hidden" name="longitude" id="longitude" value="<?= \App\Core\Security::e($oldInput['longitude'] ?? '') ?>">
      <div class="error" id="gpsError" style="font-size:12px;color:#c62828;margin-top:2px;display:none;"></div>
    </div>
  </div>

  <div class="form-card">
    <div class="photo-section">
      <label style="display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:6px;">Foto</label>
      <label class="photo-btn" id="photoBtn">
        <span id="photoIcon">+</span>
        <span id="photoLabel">Ambil Foto / Pilih File</span>
      </label>
      <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" capture="environment">
      <div class="photo-preview" id="photoPreview">
        <img id="previewImg" src="" alt="Preview">
        <button type="button" class="remove-photo" id="removePhoto">&times;</button>
      </div>
      <div class="error" id="fotoError" style="font-size:12px;color:#c62828;margin-top:4px;display:none;"></div>
    </div>
  </div>

  <div class="form-card">
    <div class="btn-group">
      <button type="button" class="btn btn-secondary" id="btnDraft">Simpan Draf</button>
      <button type="submit" class="btn btn-success" id="btnSubmit">Kirim Laporan</button>
    </div>
  </div>
</form>

<div style="text-align:center;padding:12px;font-size:12px;color:#999;">Mode Cepat &mdash; JAGAPADI</div>

<script>
(function() {
  'use strict';

  var form = document.getElementById('lightForm');
  var btnSubmit = document.getElementById('btnSubmit');
  var btnDraft = document.getElementById('btnDraft');
  var formAction = document.getElementById('formAction');
  var catatan = document.getElementById('catatan');
  var catatanCount = document.getElementById('catatanCount');
  var latitude = document.getElementById('latitude');
  var longitude = document.getElementById('longitude');
  var gpsStatus = document.getElementById('gpsStatus');
  var gpsText = document.getElementById('gpsText');
  var gpsSpinner = document.getElementById('gpsSpinner');
  var gpsError = document.getElementById('gpsError');
  var severityGroup = document.getElementById('severityGroup');
  var tingkatKeparahan = document.getElementById('tingkat_keparahan');
  var foto = document.getElementById('foto');
  var photoBtn = document.getElementById('photoBtn');
  var photoPreview = document.getElementById('photoPreview');
  var previewImg = document.getElementById('previewImg');
  var removePhoto = document.getElementById('removePhoto');
  var photoIcon = document.getElementById('photoIcon');
  var photoLabel = document.getElementById('photoLabel');
  var fotoError = document.getElementById('fotoError');
  var offlineBadge = document.getElementById('offlineBadge');

  // Char counter
  catatan.addEventListener('input', function() {
    catatanCount.textContent = this.value.length;
  });
  catatanCount.textContent = catatan.value.length;

  // Severity toggle
  var severityBtns = severityGroup.querySelectorAll('.severity-btn');
  severityBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      severityBtns.forEach(function(b) { b.className = 'severity-btn'; });
      var val = this.getAttribute('data-value');
      this.classList.add('selected-' + val.toLowerCase());
      tingkatKeparahan.value = val;
      document.getElementById('fg-keparahan').querySelector('.error').style.display = 'none';
    });
  });

  // Pre-select severity if editing
  var savedSeverity = tingkatKeparahan.value;
  if (savedSeverity) {
    severityBtns.forEach(function(b) {
      if (b.getAttribute('data-value') === savedSeverity) {
        b.click();
      }
    });
  }

  // GPS detection
  function getGPS() {
    if (!navigator.geolocation) {
      gpsStatus.className = 'gps-status error';
      gpsText.textContent = 'GPS tidak didukung perangkat';
      return;
    }
    gpsStatus.className = 'gps-status loading';
    gpsSpinner.style.display = 'inline-block';
    gpsText.textContent = 'Mendeteksi lokasi...';

    navigator.geolocation.getCurrentPosition(function(pos) {
      latitude.value = pos.coords.latitude.toFixed(6);
      longitude.value = pos.coords.longitude.toFixed(6);
      gpsStatus.className = 'gps-status success';
      gpsSpinner.style.display = 'none';
      gpsText.textContent = 'Lokasi: ' + latitude.value + ', ' + longitude.value;
      gpsError.style.display = 'none';

      // Validate Jember bounds
      var lat = parseFloat(latitude.value);
      var lng = parseFloat(longitude.value);
      if (lat < -8.5 || lat > -8.0 || lng < 113.3 || lng > 114.0) {
        gpsStatus.className = 'gps-status warning';
        gpsText.textContent = 'Lokasi di luar Jember (cek kembali)';
      }
    }, function(err) {
      gpsStatus.className = 'gps-status error';
      gpsSpinner.style.display = 'none';
      gpsText.textContent = 'Gagal deteksi GPS';
      gpsError.textContent = 'Klik peta untuk menentukan lokasi atau lanjutkan tanpa GPS.';
      gpsError.style.display = 'block';
    }, {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 300000
    });
  }
  getGPS();

  // Photo handling
  foto.addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;

    // Validate
    if (file.size > 10485760) {
      fotoError.textContent = 'Foto maksimal 10MB';
      fotoError.style.display = 'block';
      this.value = '';
      return;
    }
    fotoError.style.display = 'none';

    // Compress & preview
    var reader = new FileReader();
    reader.onload = function(ev) {
      var img = new Image();
      img.onload = function() {
        var canvas = document.createElement('canvas');
        var maxW = 800;
        var scale = Math.min(1, maxW / img.width);
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
          if (blob.size > file.size) {
            // If compression made it bigger (webp case), keep original
            return;
          }
          // Replace file with compressed version
          var newFile = new File([blob], file.name, { type: 'image/jpeg' });
          var dt = new DataTransfer();
          dt.items.add(newFile);
          foto.files = dt.files;
        }, 'image/jpeg', 0.7);

        previewImg.src = ev.target.result;
        photoPreview.style.display = 'block';
        photoBtn.className = 'photo-btn has-photo';
        photoIcon.textContent = '✓';
        photoLabel.textContent = 'Foto siap';
      };
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  removePhoto.addEventListener('click', function() {
    foto.value = '';
    photoPreview.style.display = 'none';
    photoBtn.className = 'photo-btn';
    photoIcon.textContent = '+';
    photoLabel.textContent = 'Ambil Foto / Pilih File';
  });

  // Desa cascade
  var kecSelect = document.getElementById('kecamatan_id');
  var desaSelect = document.getElementById('desa_id');
  kecSelect.addEventListener('change', function() {
    var kecId = this.value;
    desaSelect.innerHTML = '<option value="">Pilih Desa</option>';
    if (!kecId) return;
    fetch('/wilayah/desa-json?kecamatan_id=' + kecId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.data) {
          data.data.forEach(function(d) {
            var opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.nama_desa;
            desaSelect.appendChild(opt);
          });
        }
      })
      .catch(function() {});
  });

  // Trigger cascade if kecamatan pre-selected
  if (kecSelect.value) {
    kecSelect.dispatchEvent(new Event('change'));
  }

  // Real-time validation helpers
  function markError(fieldId, msg) {
    var fg = document.getElementById('fg-' + fieldId);
    if (!fg) return;
    fg.classList.add('has-error');
    var errEl = fg.querySelector('.error');
    if (errEl) errEl.textContent = msg;
  }

  function clearError(fieldId) {
    var fg = document.getElementById('fg-' + fieldId);
    if (!fg) return;
    fg.classList.remove('has-error');
    var errEl = fg.querySelector('.error');
    if (errEl) errEl.textContent = '';
  }

  // Field-level real-time validation
  document.getElementById('tanggal').addEventListener('blur', function() {
    if (!this.value) { markError('tanggal', 'Tanggal wajib diisi'); return; }
    var d = new Date(this.value + 'T00:00:00');
    if (d > new Date()) { markError('tanggal', 'Tanggal tidak boleh di masa depan'); return; }
    clearError('tanggal');
  });

  document.getElementById('master_opt_id').addEventListener('change', function() {
    if (!this.value) { markError('opt', 'OPT wajib diisi'); return; }
    clearError('opt');
  });

  document.getElementById('kecamatan_id').addEventListener('change', function() {
    if (!this.value) { markError('kecamatan', 'Kecamatan wajib diisi'); return; }
    clearError('kecamatan');
  });

  catatan.addEventListener('blur', function() {
    if (this.value.length < 10) { markError('lokasi', 'Minimal 10 karakter'); return; }
    clearError('lokasi');
  });

  // Submit handling
  btnDraft.addEventListener('click', function() {
    formAction.value = 'draft';
    form.submit();
  });

  btnSubmit.addEventListener('click', function(e) {
    formAction.value = 'submit';
    var valid = true;

    // Validate required fields
    if (!document.getElementById('tanggal').value) { markError('tanggal', 'Tanggal wajib diisi'); valid = false; }
    if (!document.getElementById('master_opt_id').value) { markError('opt', 'OPT wajib diisi'); valid = false; }
    if (!document.getElementById('kecamatan_id').value) { markError('kecamatan', 'Kecamatan wajib diisi'); valid = false; }
    if (!tingkatKeparahan.value) {
      document.getElementById('fg-keparahan').querySelector('.error').textContent = 'Tingkat keparahan wajib diisi';
      document.getElementById('fg-keparahan').querySelector('.error').style.display = 'block';
      valid = false;
    }
    if (catatan.value.length < 10) { markError('lokasi', 'Catatan minimal 10 karakter'); valid = false; }

    if (!valid) {
      e.preventDefault();
      // Scroll to first error
      var firstError = document.querySelector('.has-error');
      if (firstError) firstError.scrollIntoView({ behavior: 'instant', block: 'center' });
    }
  });

  // Offline detection
  function updateOnlineStatus() {
    if (!navigator.onLine) {
      offlineBadge.style.display = 'inline';
    } else {
      offlineBadge.style.display = 'none';
    }
  }
  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);
  updateOnlineStatus();

  // Auto-save to localStorage
  function saveDraft() {
    var data = {
      tanggal: document.getElementById('tanggal').value,
      master_opt_id: document.getElementById('master_opt_id').value,
      kecamatan_id: document.getElementById('kecamatan_id').value,
      desa_id: document.getElementById('desa_id').value,
      tingkat_keparahan: tingkatKeparahan.value,
      catatan: catatan.value,
      latitude: latitude.value,
      longitude: longitude.value,
      savedAt: Date.now()
    };
    try {
      localStorage.setItem('jagapadi_light_draft', JSON.stringify(data));
    } catch(e) {}
  }

  var autoSaveTimer = setInterval(saveDraft, 30000);
  window.addEventListener('beforeunload', saveDraft);

  // Restore draft
  try {
    var draftData = localStorage.getItem('jagapadi_light_draft');
    if (draftData) {
      var draft = JSON.parse(draftData);
      var age = Date.now() - (draft.savedAt || 0);
      if (age < 86400000 && draft.tanggal && draft.catatan) {
        if (!document.getElementById('tanggal').value) document.getElementById('tanggal').value = draft.tanggal;
        if (!document.getElementById('catatan').value) document.getElementById('catatan').value = draft.catatan;
        catatanCount.textContent = draft.catatan.length;
        if (draft.master_opt_id) document.getElementById('master_opt_id').value = draft.master_opt_id;
        if (draft.kecamatan_id) {
          document.getElementById('kecamatan_id').value = draft.kecamatan_id;
          kecSelect.dispatchEvent(new Event('change'));
          if (draft.desa_id) {
            setTimeout(function() {
              document.getElementById('desa_id').value = draft.desa_id;
            }, 500);
          }
        }
        if (draft.tingkat_keparahan) {
          tingkatKeparahan.value = draft.tingkat_keparahan;
          severityBtns.forEach(function(b) {
            if (b.getAttribute('data-value') === draft.tingkat_keparahan) {
              b.click();
            }
          });
        }
        if (draft.latitude) { latitude.value = draft.latitude; }
        if (draft.longitude) { longitude.value = draft.longitude; }
      }
    }
  } catch(e) {}

  // Clear draft after successful submission
  form.addEventListener('submit', function() {
    localStorage.removeItem('jagapadi_light_draft');
  });

})();
</script>
