<style>
    .form-card { background:#fff; border-radius:8px; padding:24px; box-shadow:0 1px 6px rgba(0,0,0,0.06); max-width:800px; }
    .form-group { margin-bottom:20px; }
    .form-group label { display:block; font-size:14px; font-weight:500; margin-bottom:6px; color:#555; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:1px solid #d0d0d0; border-radius:6px; font-size:15px; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#1a73e8; box-shadow:0 0 0 3px rgba(26,115,232,0.1); }
    .form-group .error { color:#c62828; font-size:13px; margin-top:4px; }
    .btn { padding:10px 24px; border:none; border-radius:6px; font-size:15px; font-weight:600; cursor:pointer; }
    .btn-primary { background:#1a73e8; color:#fff; }
    .btn-primary:hover { background:#1557b0; }
    .btn-success { background:#2e7d32; color:#fff; }
    .btn-success:hover { background:#1b5e20; }
    .btn-secondary { background:#e0e0e0; color:#333; margin-left:8px; text-decoration:none; display:inline-block; padding:10px 24px; border-radius:6px; font-size:15px; }
    .inline-group { display:flex; gap:12px; flex-wrap:wrap; }
    .inline-group .form-group { flex:1; min-width:180px; }
    .action-group { margin-top:24px; display:flex; gap:12px; flex-wrap:wrap; }
</style>

<div class="form-card">
    <h2 style="margin-bottom:20px;">Edit Laporan Hama</h2>

    <form method="POST" action="/laporan-hama/<?= (int) $data['id'] ?>">
        <?= \App\Core\Security::csrfField() ?>

        <div class="inline-group">
            <div class="form-group">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= \App\Core\Security::e($oldInput['tanggal'] ?? $data['tanggal'] ?? '') ?>">
                <?php if (!empty($errors['tanggal'])): ?><div class="error"><?= \App\Core\Security::e($errors['tanggal']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="master_opt_id">OPT</label>
                <select id="master_opt_id" name="master_opt_id">
                    <option value="">-- Pilih OPT --</option>
                    <?php foreach ($optList as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= (($oldInput['master_opt_id'] ?? $data['master_opt_id'] ?? '') == $o['id']) ? 'selected' : '' ?>><?= \App\Core\Security::e($o['nama_opt']) ?> (<?= \App\Core\Security::e($o['jenis']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['master_opt_id'])): ?><div class="error"><?= \App\Core\Security::e($errors['master_opt_id']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="inline-group">
            <div class="form-group">
                <label for="kabupaten_id">Kabupaten</label>
                <select id="kabupaten_id" name="kabupaten_id" onchange="loadKecamatan(this.value)">
                    <option value="">-- Pilih Kabupaten --</option>
                    <?php foreach ($kabupaten as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= (($oldInput['kabupaten_id'] ?? $data['kabupaten_id'] ?? '') == $k['id']) ? 'selected' : '' ?>><?= \App\Core\Security::e($k['nama_kabupaten']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['kabupaten_id'])): ?><div class="error"><?= \App\Core\Security::e($errors['kabupaten_id']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="kecamatan_id">Kecamatan</label>
                <select id="kecamatan_id" name="kecamatan_id" onchange="loadDesa(this.value)">
                    <option value="">-- Pilih Kabupaten Dulu --</option>
                </select>
                <?php if (!empty($errors['kecamatan_id'])): ?><div class="error"><?= \App\Core\Security::e($errors['kecamatan_id']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="desa_id">Desa</label>
                <select id="desa_id" name="desa_id">
                    <option value="">-- Pilih Kecamatan Dulu --</option>
                </select>
                <?php if (!empty($errors['desa_id'])): ?><div class="error"><?= \App\Core\Security::e($errors['desa_id']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="inline-group">
            <div class="form-group">
                <label for="tingkat_keparahan">Tingkat Keparahan</label>
                <select id="tingkat_keparahan" name="tingkat_keparahan">
                    <option value="">-- Pilih --</option>
                    <option value="Ringan" <?= (($oldInput['tingkat_keparahan'] ?? $data['tingkat_keparahan'] ?? '') === 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                    <option value="Sedang" <?= (($oldInput['tingkat_keparahan'] ?? $data['tingkat_keparahan'] ?? '') === 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                    <option value="Berat" <?= (($oldInput['tingkat_keparahan'] ?? $data['tingkat_keparahan'] ?? '') === 'Berat') ? 'selected' : '' ?>>Berat</option>
                </select>
                <?php if (!empty($errors['tingkat_keparahan'])): ?><div class="error"><?= \App\Core\Security::e($errors['tingkat_keparahan']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="luas_serangan">Luas Serangan (Ha)</label>
                <input type="number" id="luas_serangan" name="luas_serangan" step="0.01" min="0" max="9999.99" value="<?= \App\Core\Security::e($oldInput['luas_serangan'] ?? $data['luas_serangan'] ?? '') ?>">
                <?php if (!empty($errors['luas_serangan'])): ?><div class="error"><?= \App\Core\Security::e($errors['luas_serangan']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="populasi">Populasi</label>
                <input type="number" id="populasi" name="populasi" step="0.01" min="0" value="<?= \App\Core\Security::e($oldInput['populasi'] ?? $data['populasi'] ?? '') ?>">
                <?php if (!empty($errors['populasi'])): ?><div class="error"><?= \App\Core\Security::e($errors['populasi']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="lokasi">Lokasi</label>
            <input type="text" id="lokasi" name="lokasi" maxlength="255" value="<?= \App\Core\Security::e($oldInput['lokasi'] ?? $data['lokasi'] ?? '') ?>">
            <?php if (!empty($errors['lokasi'])): ?><div class="error"><?= \App\Core\Security::e($errors['lokasi']) ?></div><?php endif; ?>
        </div>

        <div class="inline-group">
            <div class="form-group">
                <label for="latitude">Latitude</label>
                <input type="text" id="latitude" name="latitude" placeholder="-8.1734" value="<?= \App\Core\Security::e($oldInput['latitude'] ?? $data['latitude'] ?? '') ?>">
                <?php if (!empty($errors['latitude'])): ?><div class="error"><?= \App\Core\Security::e($errors['latitude']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="longitude">Longitude</label>
                <input type="text" id="longitude" name="longitude" placeholder="113.7012" value="<?= \App\Core\Security::e($oldInput['longitude'] ?? $data['longitude'] ?? '') ?>">
                <?php if (!empty($errors['longitude'])): ?><div class="error"><?= \App\Core\Security::e($errors['longitude']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="alamat_lengkap">Alamat Lengkap</label>
            <textarea id="alamat_lengkap" name="alamat_lengkap" rows="2" maxlength="300"><?= \App\Core\Security::e($oldInput['alamat_lengkap'] ?? $data['alamat_lengkap'] ?? '') ?></textarea>
            <?php if (!empty($errors['alamat_lengkap'])): ?><div class="error"><?= \App\Core\Security::e($errors['alamat_lengkap']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="catatan">Catatan</label>
            <textarea id="catatan" name="catatan" rows="3"><?= \App\Core\Security::e($oldInput['catatan'] ?? $data['catatan'] ?? '') ?></textarea>
            <?php if (!empty($errors['catatan'])): ?><div class="error"><?= \App\Core\Security::e($errors['catatan']) ?></div><?php endif; ?>
        </div>

        <div class="action-group">
            <button type="submit" class="btn btn-primary">Simpan Draf</button>
            <button type="submit" formaction="/laporan-hama/<?= (int) $data['id'] ?>/submit" class="btn btn-success">Kirim Laporan</button>
            <a href="/laporan-hama/<?= (int) $data['id'] ?>" class="btn-secondary">Batal</a>
        </div>
    </form>

    <?php if (isset($data['id'])): ?>
    <hr style="margin:24px 0;">
    <h3 style="margin-bottom:12px;">Foto Laporan</h3>

    <?php if (!empty($data['foto_url'])): ?>
        <div style="margin-bottom:12px;">
            <img src="/<?= \App\Core\Security::e($data['foto_url']) ?>" alt="Foto laporan" style="max-width:300px;max-height:200px;border-radius:6px;border:1px solid #e0e0e0;">
            <form method="POST" action="/laporan-hama/<?= (int) $data['id'] ?>/foto/delete" style="margin-top:8px;">
                <?= \App\Core\Security::csrfField() ?>
                <button type="submit" class="btn btn-danger" style="padding:6px 16px;font-size:13px;" onclick="return confirm('Hapus foto ini?')">Hapus Foto</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="POST" action="/laporan-hama/<?= (int) $data['id'] ?>/foto" enctype="multipart/form-data">
        <?= \App\Core\Security::csrfField() ?>
        <div class="form-group">
            <label for="foto">Upload Foto Baru (maks. 10 MB, JPEG/PNG/WebP)</label>
            <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
    <?php endif; ?>
</div>

<script>
function loadKecamatan(kabupatenId) {
    const kecSelect = document.getElementById('kecamatan_id');
    const desaSelect = document.getElementById('desa_id');
    kecSelect.innerHTML = '<option value="">Memuat...</option>';
    desaSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dulu --</option>';

    if (!kabupatenId) {
        kecSelect.innerHTML = '<option value="">-- Pilih Kabupaten Dulu --</option>';
        return;
    }

    fetch('/api/v1/wilayah/kecamatan?kabupaten_id=' + kabupatenId, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
        const data = res.data || [];
        kecSelect.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        data.forEach(function(k) {
            const sel = '<?= \App\Core\Security::e($oldInput["kecamatan_id"] ?? $data["kecamatan_id"] ?? "") ?>' == k.id ? 'selected' : '';
            kecSelect.innerHTML += '<option value="' + k.id + '" ' + sel + '>' + k.nama_kecamatan + '</option>';
        });
        const kecId = '<?= \App\Core\Security::e($oldInput["kecamatan_id"] ?? $data["kecamatan_id"] ?? "") ?>';
        if (kecId) {
            loadDesa(kecId);
        }
    })
    .catch(() => {
        kecSelect.innerHTML = '<option value="">Gagal memuat data</option>';
    });
}

function loadDesa(kecamatanId) {
    const desaSelect = document.getElementById('desa_id');
    desaSelect.innerHTML = '<option value="">Memuat...</option>';

    if (!kecamatanId) {
        desaSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dulu --</option>';
        return;
    }

    fetch('/api/v1/wilayah/desa?kecamatan_id=' + kecamatanId, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
        const data = res.data || [];
        desaSelect.innerHTML = '<option value="">-- Pilih Desa --</option>';
        data.forEach(function(d) {
            const sel = '<?= \App\Core\Security::e($oldInput["desa_id"] ?? $data["desa_id"] ?? "") ?>' == d.id ? 'selected' : '';
            desaSelect.innerHTML += '<option value="' + d.id + '" ' + sel + '>' + d.nama_desa + '</option>';
        });
    })
    .catch(() => {
        desaSelect.innerHTML = '<option value="">Gagal memuat data</option>';
    });
}

(function() {
    const kabId = '<?= \App\Core\Security::e($oldInput["kabupaten_id"] ?? $data["kabupaten_id"] ?? "") ?>';
    if (kabId) {
        loadKecamatan(kabId);
    }
})();
</script>
