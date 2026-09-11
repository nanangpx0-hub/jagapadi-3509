<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Tampilan statis sehalaman: matikan total efek timbul-tenggelam. */
.jenis-no-motion,
.jenis-no-motion *,
*::before,
*::after {
    animation: none !important;
    transition: none !important;
}
.jenis-no-motion *:hover,
.jenis-no-motion *:focus,
.jenis-no-motion *:active {
    transform: none !important;
    box-shadow: none !important;
}
</style>
<?php
$row = $row ?? null;
$oldInput = (isset($oldInput) && is_array($oldInput)) ? $oldInput : [];
$formAction = isset($formAction) ? (string) $formAction : 'jenis-laporan/store';
$isEdit = !empty($row);
$val = static function (string $key, $default = '') use ($oldInput, $row, $isEdit): string {
    if (array_key_exists($key, $oldInput ?? []) && $oldInput[$key] !== null && $oldInput[$key] !== '') {
        return htmlspecialchars((string) $oldInput[$key], ENT_QUOTES, 'UTF-8');
    }
    if ($isEdit && isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
        return htmlspecialchars((string) $row[$key], ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string) $default, ENT_QUOTES, 'UTF-8');
};
$checked = static function () use ($oldInput, $row, $isEdit): string {
    if (array_key_exists('is_active', $oldInput ?? [])) {
        return !empty($oldInput['is_active']) ? ' checked' : '';
    }
    if ($isEdit) {
        return ((int) ($row['is_active'] ?? 0)) === 1 ? ' checked' : '';
    }
    return ' checked';
};
$exampleJson = json_encode([
    ['name' => 'komoditas', 'label' => 'Komoditas', 'type' => 'text', 'required' => true],
    ['name' => 'luas_ha', 'label' => 'Luas (Ha)', 'type' => 'number', 'required' => false],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>

<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-<?= $isEdit ? 'edit' : 'plus' ?>"></i>
                    <?= $isEdit ? 'Edit' : 'Tambah' ?> Jenis Laporan
                </h3>
            </div>
            <form action="<?= BASE_URL . htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" method="POST" id="formJenisLaporan">
                <?= Security::getCsrfField() ?>
                <div class="card-body">
<div class="row jenis-no-motion">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kode">Kode <span class="text-danger">*</span></label>
                                <input type="text" name="kode" id="kode" class="form-control"
                                       value="<?= $val('kode') ?>" required maxlength="60"
                                       pattern="[a-z0-9_]{2,60}" autocomplete="off"
                                       placeholder="contoh: panen" readonly data-autokode="1">
                                <small class="text-muted">Terisi otomatis dari Nama (huruf kecil, underscore). Unik; dipakai sebagai identitas stabil.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nama">Nama <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="nama" class="form-control"
                                       value="<?= $val('nama') ?>" required maxlength="150"
                                       placeholder="contoh: Panen">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="2" maxlength="2000"
                                  placeholder="Keterangan singkat jenis laporan"><?= $val('deskripsi') ?></textarea>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"<?= $checked() ?>>
                        <label for="is_active" class="form-check-label">Aktif (tampil di dropdown form Laporan Lainnya)</label>
                    </div>
                    <div class="form-group">
                        <label for="fields_json">Field Dinamis (JSON)</label>
                        <textarea name="fields_json" id="fields_json" class="form-control" rows="8" spellcheck="false"
                                  placeholder='[]'><?= $val('fields_json', '[]') ?></textarea>
                        <small class="text-muted d-block mt-1">
                            Array object <code>name</code> (snake_case, unik), <code>label</code>,
                            <code>type</code> (<code>text</code>, <code>textarea</code>, <code>number</code>, <code>integer</code>, <code>date</code>),
                            <code>required</code> (true/false). Maksimal 50 field. Contoh:
                        </small>
                        <pre class="bg-light p-2 mt-1 mb-0"><code><?= htmlspecialchars($exampleJson, ENT_QUOTES, 'UTF-8') ?></code></pre>
                        <div id="fieldsJsonError" class="invalid-feedback"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnPrettyJson">
                            <i class="fas fa-check"></i> Rapikan &amp; Periksa JSON
                        </button>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="<?= BASE_URL ?>jenis-laporan" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Kode otomatis dari Nama (mode tambah): cerminan JenisLaporanService::slugify.
function slugifyNama(text) {
    var slug = String(text || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    if (slug.length > 60) slug = slug.substring(0, 60).replace(/_+$/g, '');
    return slug;
}

function syncKodeFromNama() {
    var namaInput = document.getElementById('nama');
    var kodeInput = document.getElementById('kode');
    if (namaInput && kodeInput && kodeInput.hasAttribute('data-autokode')) {
        kodeInput.value = slugifyNama(namaInput.value);
    }
}

document.getElementById('btnPrettyJson').addEventListener('click', function () {
    var area = document.getElementById('fields_json');
    var errBox = document.getElementById('fieldsJsonError');
    try {
        var parsed = JSON.parse(area.value === '' ? '[]' : area.value);
        if (!Array.isArray(parsed)) throw new Error('JSON harus berupa array.');
        area.value = JSON.stringify(parsed, null, 2);
        area.classList.remove('is-invalid');
        area.classList.add('is-valid');
        errBox.textContent = '';
    } catch (e) {
        area.classList.remove('is-valid');
        area.classList.add('is-invalid');
        errBox.textContent = 'JSON tidak valid: ' + e.message;
    }
});

document.getElementById('formJenisLaporan').addEventListener('submit', function (e) {
    var kodeInput = document.getElementById('kode');
    if (kodeInput && kodeInput.hasAttribute('data-autokode')) {
        syncKodeFromNama();
    }
    var area = document.getElementById('fields_json');
    var raw = area.value.trim();
    if (raw === '') {
        area.value = '[]';
        return;
    }
    try {
        var parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) throw new Error('JSON harus berupa array.');
        area.value = JSON.stringify(parsed);
    } catch (err) {
        e.preventDefault();
        area.classList.add('is-invalid');
        document.getElementById('fieldsJsonError').textContent = 'Perbaiki JSON sebelum menyimpan: ' + err.message;
        area.focus();
    }
});
</script>

<script>
// Inisialisasi kode otomatis dari Nama.
(function () {
    var namaInput = document.getElementById('nama');
    var kodeInput = document.getElementById('kode');
    if (!namaInput || !kodeInput || !kodeInput.hasAttribute('data-autokode')) return;
    namaInput.addEventListener('input', syncKodeFromNama);
    if (namaInput.value && !kodeInput.value) syncKodeFromNama();
})();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
