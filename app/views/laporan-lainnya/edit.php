<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Tampilan statis tanpa efek timbul-tenggelam untuk seluruh role. */
.petugas-no-motion .card,
.petugas-no-motion .card:hover,
.petugas-no-motion .btn,
.petugas-no-motion .btn:hover,
.petugas-no-motion .btn:focus,
.petugas-no-motion .btn:active,
.petugas-no-motion .form-control,
.petugas-no-motion .custom-select,
.petugas-no-motion .alert,
.petugas-no-motion .badge,
.petugas-no-motion .skeleton-loading,
.petugas-no-motion #autoSaveIndicator {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}

.petugas-no-motion .card:hover,
.petugas-no-motion .btn:hover,
.petugas-no-motion .btn:focus,
.petugas-no-motion .btn:active {
    box-shadow: none !important;
}
</style>

<div class="row petugas-no-motion">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i> Edit Laporan Lainnya
                </h3>
            </div>
            <form action="<?= BASE_URL ?>laporan-lainnya/update/<?= $laporan['id'] ?>" method="POST" enctype="multipart/form-data" id="formEditLaporan">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="jenis_id" value="<?= $laporan['jenis_id'] ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jenis Laporan</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($laporan['jenis_nama'] ?? '') ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Kejadian</label>
                                <input type="date" name="tanggal_kejadian" class="form-control" value="<?= htmlspecialchars($laporan['tanggal_kejadian'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div id="dynamicFieldsContainer" class="mb-3">
                        <h5><i class="fas fa-list"></i> Data Laporan</h5>
                        <div class="row">
                            <?php if(!empty($jenisFields)): ?>
                            <?php foreach($jenisFields as $field): ?>
                            <?php
                            $fieldName = $field['name'];
                            $fieldValue = $dataJson[$fieldName] ?? '';
                            $isRequired = !empty($field['required']) ? '<span class="text-danger">*</span>' : '';
                            $requiredAttr = !empty($field['required']) ? 'required' : '';
                            $inputType = $field['type'] === 'number' ? 'number' : 'text';
                            $stepAttr = $field['type'] === 'number' ? 'step="any"' : '';
                            $label = $field['label'] ?? $field['name'];
                            ?>
                            <div class="col-md-6 mb-3">
                                <label><?= htmlspecialchars($label) ?> <?= $isRequired ?></label>
                                <?php if($field['type'] === 'number'): ?>
                                <input type="number" name="<?= htmlspecialchars($fieldName) ?>" class="form-control" value="<?= htmlspecialchars($fieldValue) ?>" <?= $requiredAttr ?> <?= $stepAttr ?>>
                                <?php else: ?>
                                <input type="text" name="<?= htmlspecialchars($fieldName) ?>" class="form-control" value="<?= htmlspecialchars($fieldValue) ?>" <?= $requiredAttr ?>>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">Tidak ada field untuk jenis laporan ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <input type="text" name="alamat_lengkap" class="form-control" value="<?= htmlspecialchars($laporan['alamat_lengkap'] ?? '') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kabupaten <span class="text-danger">*</span></label>
                                <select name="kabupaten_id" id="kabupatenSelect" class="form-control" required autocomplete="off">
                                    <option value="">-- Pilih Kabupaten --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kecamatan <span class="text-danger">*</span></label>
                                <select name="kecamatan_id" id="kecamatanSelect" class="form-control" required autocomplete="off">
                                    <option value="">-- Pilih Kecamatan --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Desa <span class="text-danger">*</span></label>
                                <select name="desa_id" id="desaSelect" class="form-control" required autocomplete="off">
                                    <option value="">-- Pilih Desa --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Foto</label>
                        <?php if(!empty($laporan['foto_url'])): ?>
                        <div class="mb-2">
                            <img src="<?= BASE_URL . 'public/' . htmlspecialchars($laporan['foto_url']) ?>" class="img-thumbnail" style="max-width: 300px; max-height: 300px;" alt="Foto Laporan">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="hapusFotoCheck" name="hapus_foto" value="1">
                                <label class="custom-control-label text-danger" for="hapusFotoCheck">
                                    <i class="fas fa-trash"></i> Hapus foto ini
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" name="foto" class="custom-file-input" id="fotoInput" accept="image/jpeg,image/png,image/webp">
                                <label class="custom-file-label" for="fotoInput">
                                    <?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Format: JPG, PNG, WEBP. Maksimal 2MB. Biarkan kosong jika tidak ingin mengganti foto.</small>
                        <div id="fotoPreview" class="mt-2" style="display: none;">
                            <strong>Preview Foto Baru:</strong><br>
                            <img id="previewImg" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" style="max-width: 300px; max-height: 300px;" class="img-thumbnail mt-2">
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="clearFotoPreview()">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi tambahan"><?= htmlspecialchars($laporan['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Latitude</label>
                                <input type="number" name="latitude" class="form-control" value="<?= htmlspecialchars($laporan['latitude'] ?? '') ?>" step="any" min="-90" max="90">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Longitude</label>
                                <input type="number" name="longitude" class="form-control" value="<?= htmlspecialchars($laporan['longitude'] ?? '') ?>" step="any" min="-180" max="180">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Perbarui
                    </button>
                    <a href="<?= BASE_URL ?>laporan-lainnya/show/<?= $laporan['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
<script>
// ============================================================================
// CASCADING DROPDOWN WILAYAH — Edit Laporan Lainnya
// Pola sama dengan laporan/edit.php; gunakan real DB id dari response API
// ============================================================================
(function () {
    'use strict';

    const BASE_URL       = '<?= BASE_URL ?>';
    const savedKabId     = <?= json_encode((string)($laporan['kabupaten_id'] ?? '')) ?>;
    const savedKecId     = <?= json_encode((string)($laporan['kecamatan_id'] ?? '')) ?>;
    const savedDesaId    = <?= json_encode((string)($laporan['desa_id'] ?? '')) ?>;

    // Input dari update() yang gagal divalidasi (controller menyimpannya ke session).
    const OLD_INPUT = <?= json_encode(!empty($oldInput) ? $oldInput : new stdClass()) ?>;

    // Timpa nilai field statis & dinamis dengan input user bila update gagal,
    // agar edit yang belum tersimpan tidak hilang.
    (function repopulateOldInput(oldInput) {
        if (!oldInput || typeof oldInput !== 'object' || Array.isArray(oldInput) || !Object.keys(oldInput).length) {
            return;
        }
        ['alamat_lengkap', 'tanggal_kejadian', 'deskripsi', 'latitude', 'longitude'].forEach(function (name) {
            if (oldInput[name] === undefined || oldInput[name] === null || oldInput[name] === '') {
                return;
            }
            const el = document.querySelector('[name="' + name + '"]');
            if (el) {
                el.value = oldInput[name];
            }
        });
        document.querySelectorAll('#dynamicFieldsContainer input').forEach(function (el) {
            if (el.name && oldInput[el.name] !== undefined && oldInput[el.name] !== null && oldInput[el.name] !== '') {
                el.value = oldInput[el.name];
            }
        });
    })(OLD_INPUT);

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

    (async () => {
        // Prioritaskan input user dari update gagal (OLD_INPUT) di atas nilai tersimpan.
        const effKab = (OLD_INPUT && OLD_INPUT.kabupaten_id) ? String(OLD_INPUT.kabupaten_id) : savedKabId;
        const effKec = (OLD_INPUT && OLD_INPUT.kecamatan_id) ? String(OLD_INPUT.kecamatan_id) : savedKecId;
        const effDesa = (OLD_INPUT && OLD_INPUT.desa_id) ? String(OLD_INPUT.desa_id) : savedDesaId;

        await loadKabupaten(effKab);
        if (effKab) {
            await loadKecamatan(effKab, effKec);
        }
        if (effKec) {
            await loadDesa(effKec, effDesa);
        }
    })();

})();
</script>
<script>
// ============================================================================
// FOTO — preview & logika hapus foto (Edit Laporan Lainnya)
// ============================================================================
(function () {
    'use strict';

    const fotoInput = document.getElementById('fotoInput');
    const hapusFotoCheck = document.getElementById('hapusFotoCheck');

    if (fotoInput) {
        fotoInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            const label = document.querySelector('.custom-file-label');

            if (!file) {
                label.textContent = '<?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>';
                document.getElementById('fotoPreview').style.display = 'none';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file terlalu besar! Maksimal 2MB');
                e.target.value = '';
                label.textContent = '<?= !empty($laporan['foto_url']) ? 'Ganti foto...' : 'Pilih foto...' ?>';
                document.getElementById('fotoPreview').style.display = 'none';
                return;
            }

            label.textContent = file.name;

            // Jika user memilih foto baru, batal otomatis opsi hapus foto
            if (hapusFotoCheck) {
                hapusFotoCheck.checked = false;
            }

            const reader = new FileReader();
            reader.onload = function (ev) {
                document.getElementById('previewImg').src = ev.target.result;
                document.getElementById('fotoPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

    if (hapusFotoCheck) {
        hapusFotoCheck.addEventListener('change', function () {
            if (this.checked && fotoInput && fotoInput.files.length > 0) {
                this.checked = false;
                alert('Hapus foto lama terlebih dahulu, atau biarkan file baru menggantikannya.');
            }
        });
    }
})();
</script>