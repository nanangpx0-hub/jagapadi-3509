<style>
    .form-card { background:#fff; border-radius:8px; padding:24px; box-shadow:0 1px 6px rgba(0,0,0,0.06); max-width:600px; }
    .form-group { margin-bottom:20px; }
    .form-group label { display:block; font-size:14px; font-weight:500; margin-bottom:6px; color:#555; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:1px solid #d0d0d0; border-radius:6px; font-size:15px; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#1a73e8; box-shadow:0 0 0 3px rgba(26,115,232,0.1); }
    .form-group .error { color:#c62828; font-size:13px; margin-top:4px; }
    .btn { padding:10px 24px; border:none; border-radius:6px; font-size:15px; font-weight:600; cursor:pointer; }
    .btn-primary { background:#1a73e8; color:#fff; }
    .btn-primary:hover { background:#1557b0; }
    .btn-secondary { background:#e0e0e0; color:#333; margin-left:8px; text-decoration:none; display:inline-block; padding:10px 24px; border-radius:6px; font-size:15px; }
    .inline-group { display:flex; gap:12px; }
    .inline-group .form-group { flex:1; }
</style>

<div class="form-card">
    <h2 style="margin-bottom:20px;"><?= isset($data['id']) ? 'Edit' : 'Tambah' ?> OPT</h2>

    <form method="POST" action="/opt/<?= isset($data['id']) ? 'update/'.$data['id'] : 'store' ?>">
        <?= \App\Core\Security::csrfField() ?>

        <div class="form-group">
            <label for="nama_opt">Nama OPT</label>
            <input type="text" id="nama_opt" name="nama_opt" maxlength="150" required
                   value="<?= \App\Core\Security::e($data['nama_opt'] ?? '') ?>">
            <?php if (!empty($errors['nama_opt'])): ?><div class="error"><?= \App\Core\Security::e($errors['nama_opt']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="jenis">Jenis</label>
            <select id="jenis" name="jenis" required>
                <option value="">-- Pilih Jenis --</option>
                <option value="hama" <?= ($data['jenis'] ?? '') === 'hama' ? 'selected' : '' ?>>Hama</option>
                <option value="penyakit" <?= ($data['jenis'] ?? '') === 'penyakit' ? 'selected' : '' ?>>Penyakit</option>
                <option value="gulma" <?= ($data['jenis'] ?? '') === 'gulma' ? 'selected' : '' ?>>Gulma</option>
            </select>
            <?php if (!empty($errors['jenis'])): ?><div class="error"><?= \App\Core\Security::e($errors['jenis']) ?></div><?php endif; ?>
        </div>

        <div class="inline-group">
            <div class="form-group">
                <label for="etl_acuan">ETL Acuan</label>
                <input type="number" id="etl_acuan" name="etl_acuan" step="0.01" min="0"
                       value="<?= \App\Core\Security::e((string) ($data['etl_acuan'] ?? '')) ?>">
                <?php if (!empty($errors['etl_acuan'])): ?><div class="error"><?= \App\Core\Security::e($errors['etl_acuan']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="satuan_etl">Satuan ETL</label>
                <input type="text" id="satuan_etl" name="satuan_etl" maxlength="30"
                       value="<?= \App\Core\Security::e($data['satuan_etl'] ?? '') ?>">
                <?php if (!empty($errors['satuan_etl'])): ?><div class="error"><?= \App\Core\Security::e($errors['satuan_etl']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi" rows="3"><?= \App\Core\Security::e($data['deskripsi'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="aktif" value="1" <?= (($data['aktif'] ?? 1) == 1) ? 'checked' : '' ?>>
                Aktif
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="/opt" class="btn-secondary">Batal</a>
        </div>
    </form>
</div>
