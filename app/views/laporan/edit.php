<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Laporan</h3>
            </div>
            <form action="<?= BASE_URL ?>laporan/edit/<?= $laporan['id'] ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <?= Security::getIdempotencyField() ?>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Kejadian/Pengamatan <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" class="form-control" value="<?= $laporan['tanggal'] ?>" max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>OPT <span class="text-danger">*</span></label>
                                <select name="master_opt_id" class="form-control" required>
                                    <option value="">-- Pilih OPT --</option>
                                    <?php foreach($data_opt as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $laporan['master_opt_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opt['nama_opt'] ?? '') ?> (<?= $opt['jenis'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group"><label>Metode Pengukuran</label><select name="metode_pengukuran" class="form-control"><option value="absolut" <?= ($laporan['metode_pengukuran'] ?? 'absolut') === 'absolut' ? 'selected' : '' ?>>Luas absolut (Ha)</option><option value="persentase" <?= ($laporan['metode_pengukuran'] ?? '') === 'persentase' ? 'selected' : '' ?>>Persentase (%)</option></select></div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <?php 
                                // Cek role user untuk menentukan apakah field wajib
                                $isRequired = ($_SESSION['role'] ?? '') === 'petugas';
                                $requiredAttr = $isRequired ? 'required' : '';
                                $requiredMark = $isRequired ? '<span class="text-danger">*</span>' : '';
                                ?>
                                <label>Kabupaten <?= $requiredMark ?></label>
                                <select name="kabupaten_id" id="kabupatenSelect" class="form-control" <?= $requiredAttr ?>>
                                    <option value="">-- Pilih Kabupaten --</option>
                                    <option value="unknown">Tidak Diketahui</option>
                                </select>
                                <?php if($isRequired): ?>
                                <div class="invalid-feedback">Kabupaten wajib dipilih</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4"><div class="form-group"><label>Persentase Serangan (%)</label><input type="number" name="persentase_serangan" class="form-control" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string) ($laporan['persentase_serangan'] ?? '')) ?>"></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Luas Areal Diamati (Ha)</label><input type="number" name="luas_areal_diamati" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($laporan['luas_areal_diamati'] ?? '')) ?>"></div></div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kecamatan <?= $requiredMark ?></label>
                                <select name="kecamatan_id" id="kecamatanSelect" class="form-control" <?= $requiredAttr ?>>
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <option value="unknown">Tidak Diketahui</option>
                                </select>
                                <?php if($isRequired): ?>
                                <div class="invalid-feedback">Kecamatan wajib dipilih</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Desa <?= $requiredMark ?></label>
                                <select name="desa_id" id="desaSelect" class="form-control" <?= $requiredAttr ?>>
                                    <option value="">-- Pilih Desa --</option>
                                    <option value="unknown">Tidak Diketahui</option>
                                </select>
                                <?php if($isRequired): ?>
                                <div class="invalid-feedback">Desa wajib dipilih</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Alamat Lengkap <?= $requiredMark ?></label>
                                <input type="text" name="alamat_lengkap" class="form-control" value="<?= htmlspecialchars($laporan['alamat_lengkap'] ?? ($laporan['lokasi'] ?? '')) ?>" <?= $requiredAttr ?>>
                                <?php if($isRequired): ?>
                                <div class="invalid-feedback">Alamat lengkap wajib diisi</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Video Pendukung</label><?php if (!empty($laporan['video_url'])): ?><video controls preload="metadata" class="d-block mb-2" style="max-width:320px"><source src="<?= BASE_URL . htmlspecialchars($laporan['video_url']) ?>" type="video/mp4"></video><?php endif; ?><input type="file" name="video" class="form-control-file" accept="video/mp4"><small class="text-muted">MP4 maksimal 50 MB; kosongkan untuk mempertahankan video saat ini.</small></div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Latitude</label>
                                <input type="text" name="latitude" class="form-control" value="<?= $laporan['latitude'] ?>" step="any">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Longitude</label>
                                <input type="text" name="longitude" class="form-control" value="<?= $laporan['longitude'] ?>" step="any">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tingkat Keparahan <span class="text-danger">*</span></label>
                                <select name="tingkat_keparahan" class="form-control" required>
                                    <option value="Ringan" <?= $laporan['tingkat_keparahan'] == 'Ringan' ? 'selected' : '' ?>>Ringan</option>
                                    <option value="Sedang" <?= $laporan['tingkat_keparahan'] == 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                                    <option value="Berat" <?= $laporan['tingkat_keparahan'] == 'Berat' ? 'selected' : '' ?>>Berat</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Populasi/Intensitas</label>
                                <input type="number" name="populasi" class="form-control" value="<?= $laporan['populasi'] ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Luas Serangan (Ha)</label>
                                <input type="number" name="luas_serangan" class="form-control" value="<?= $laporan['luas_serangan'] ?>" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($laporan['catatan'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Foto</label>
                        <?php if(!empty($laporan['foto_url'])): ?>
                        <div class="mb-2">
                            <strong>Foto Saat Ini:</strong><br>
                            <img src="<?= BASE_URL . $laporan['foto_url'] ?>" style="max-width: 300px; max-height: 300px;" class="img-thumbnail mt-2">
                        </div>
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" name="foto" class="custom-file-input" id="fotoInput" accept="image/jpeg,image/png,image/jpg">
                                <label class="custom-file-label" for="fotoInput">
                                    <?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Format: JPG, PNG. Maksimal 2MB. <?= !empty($laporan['foto_url']) ? 'Biarkan kosong jika tidak ingin mengganti foto.' : '' ?></small>
                        
                        <div id="fotoPreview" class="mt-2" style="display: none;">
                            <strong>Preview Foto Baru:</strong><br>
                            <img id="previewImg" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" style="max-width: 300px; max-height: 300px;" class="img-thumbnail mt-2">
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="clearFotoPreview()">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        </div>
                    </div>
                    
                    <input type="hidden" name="status" value="<?= htmlspecialchars($laporan['status'] ?? 'Submitted') ?>">
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Laporan
                    </button>
                    <a href="<?= BASE_URL ?>laporan" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// File input label update
document.getElementById('fotoInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || '<?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>';
    const label = document.querySelector('.custom-file-label');
    label.textContent = fileName;
    
    // Validate file size (2MB)
    const file = e.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 2MB');
            e.target.value = '';
            label.textContent = '<?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>';
            document.getElementById('fotoPreview').style.display = 'none';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('fotoPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

function clearFotoPreview() {
    document.getElementById('fotoInput').value = '';
    document.querySelector('.custom-file-label').textContent = '<?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>';
    document.getElementById('fotoPreview').style.display = 'none';
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const file = document.getElementById('fotoInput').files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        e.preventDefault();
        alert('Ukuran file terlalu besar! Maksimal 2MB');
        return false;
    }
    
    // Role-based validation for petugas
    const userRole = '<?= $_SESSION['role'] ?? '' ?>';
    if (userRole === 'petugas') {
        const kabupaten = document.getElementById('kabupatenSelect').value;
        const kecamatan = document.getElementById('kecamatanSelect').value;
        const desa = document.getElementById('desaSelect').value;
        const alamatLengkap = document.querySelector('input[name="alamat_lengkap"]').value.trim();
        
        let errors = [];
        
        if (!kabupaten || kabupaten === '') {
            errors.push('Kabupaten wajib dipilih');
        }
        
        if (!kecamatan || kecamatan === '') {
            errors.push('Kecamatan wajib dipilih');
        }
        
        if (!desa || desa === '') {
            errors.push('Desa wajib dipilih');
        }
        
        if (!alamatLengkap) {
            errors.push('Alamat lengkap wajib diisi');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Validasi gagal:\n\n' + errors.join('\n'));
            return false;
        }
    }
});

// Status button toggle for petugas
const statusButtons = document.querySelectorAll('.status-btn');
const statusInput = document.getElementById('statusInput');
if (statusButtons.length > 0 && statusInput) {
    statusButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all
            statusButtons.forEach(b => {
                b.classList.remove('active', 'btn-success', 'btn-secondary');
                b.classList.add('btn-outline-secondary');
            });
            // Add active to clicked
            this.classList.remove('btn-outline-secondary', 'btn-outline-success');
            this.classList.add('active');
            if (this.dataset.status === 'Submitted') {
                this.classList.add('btn-success');
            } else {
                this.classList.add('btn-secondary');
            }
            // Update hidden input
            statusInput.value = this.dataset.status;
        });
    });
}
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
<script>
// ============================================================================
// CASCADING DROPDOWN WILAYAH — Edit Laporan
// Perbaikan: gunakan real DB id dari response API (tidak di-hardcode)
// ============================================================================
(function () {
    'use strict';

    const BASE_URL       = '<?= BASE_URL ?>';
    const savedKabId     = <?= json_encode((string)($laporan['kabupaten_id'] ?? '')) ?>;
    const savedKecId     = <?= json_encode((string)($laporan['kecamatan_id'] ?? '')) ?>;
    const savedDesaId    = <?= json_encode((string)($laporan['desa_id'] ?? '')) ?>;

    async function fetchJSON(url) {
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return { status: 'error', data: [] };
            return await r.json();
        } catch (e) {
            return { status: 'error', data: [] };
        }
    }

    async function loadKabupaten(selectedId) {
        const sel = document.getElementById('kabupatenSelect');
        if (!sel) return;
        sel.disabled = true;
        const resp = await fetchJSON(BASE_URL + 'wilayah/kabupaten');
        if (resp.status !== 'success' || !Array.isArray(resp.data)) {
            sel.disabled = false;
            return;
        }
        // Keep existing placeholder, add options with real DB id
        resp.data.forEach(row => {
            const opt = new Option(row.nama_kabupaten, String(row.id ?? ''));
            if (String(row.id) === String(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    async function loadKecamatan(kabupatenId, selectedId) {
        const sel     = document.getElementById('kecamatanSelect');
        const selDesa = document.getElementById('desaSelect');
        if (!sel) return;
        // Reset kecamatan & desa
        sel.innerHTML     = '<option value="">-- Pilih Kecamatan --</option>';
        if (selDesa) selDesa.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (!kabupatenId || kabupatenId === 'unknown') return;
        sel.disabled = true;
        const resp = await fetchJSON(BASE_URL + 'wilayah/kecamatan/' + encodeURIComponent(kabupatenId));
        if (resp.status === 'success' && Array.isArray(resp.data)) {
            resp.data.forEach(row => {
                const opt = new Option(row.nama_kecamatan, String(row.id ?? ''));
                if (String(row.id) === String(selectedId)) opt.selected = true;
                sel.appendChild(opt);
            });
        }
        sel.disabled = false;
    }

    async function loadDesa(kecamatanId, selectedId) {
        const sel = document.getElementById('desaSelect');
        if (!sel) return;
        sel.innerHTML = '<option value="">-- Pilih Desa --</option>';
        if (!kecamatanId || kecamatanId === 'unknown') return;
        sel.disabled = true;
        const resp = await fetchJSON(BASE_URL + 'wilayah/desa/' + encodeURIComponent(kecamatanId));
        if (resp.status === 'success' && Array.isArray(resp.data)) {
            resp.data.forEach(row => {
                const opt = new Option(row.nama_desa, String(row.id ?? ''));
                if (String(row.id) === String(selectedId)) opt.selected = true;
                sel.appendChild(opt);
            });
        }
        sel.disabled = false;
    }

    // Event bindings
    const kabSel = document.getElementById('kabupatenSelect');
    const kecSel = document.getElementById('kecamatanSelect');
    if (kabSel) {
        kabSel.addEventListener('change', function () {
            loadKecamatan(this.value, '');
        });
    }
    if (kecSel) {
        kecSel.addEventListener('change', function () {
            loadDesa(this.value, '');
        });
    }

    // Init: load kabupaten, then pre-select saved values
    (async () => {
        await loadKabupaten(savedKabId);
        if (savedKabId) {
            await loadKecamatan(savedKabId, savedKecId);
        }
        if (savedKecId) {
            await loadDesa(savedKecId, savedDesaId);
        }
    })();

})();
</script>
