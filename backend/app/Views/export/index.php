<div class="card">
    <h2>Ekspor Data Laporan</h2>
    <p style="font-size:14px;color:#666;margin-bottom:16px;">
        Pilih jenis laporan, filter, dan format untuk mengunduh data.
        Maksimal rentang tanggal 366 hari dan maksimal 10.000 baris.
    </p>

    <form id="exportForm" method="POST" style="max-width:600px">
        <?= \App\Core\Security::csrfField() ?>

        <div class="form-group">
            <label>Jenis Laporan</label>
            <div style="display:flex;gap:16px;margin-top:4px">
                <label style="font-weight:400;display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="jenis" value="hama" checked onchange="toggleForm()"> Hama (OPT)
                </label>
                <label style="font-weight:400;display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="jenis" value="irigasi" onchange="toggleForm()"> Irigasi
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Format File</label>
            <div style="display:flex;gap:16px;margin-top:4px">
                <label style="font-weight:400;display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="format" value="csv" checked> CSV
                </label>
                <label style="font-weight:400;display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="format" value="xlsx"> XLSX (Excel)
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px">
                <?php
                $statuses = ['Draf', 'Submitted', 'Diverifikasi', 'Ditolak', 'Diarsipkan'];
                foreach ($statuses as $s):
                ?>
                <label style="font-weight:400;display:flex;align-items:center;gap:4px;cursor:pointer">
                    <input type="checkbox" name="status_filter[]" value="<?= $s ?>"
                        <?= in_array($s, ($oldInput['status_filter'] ?? [])) ? 'checked' : '' ?>> <?= $s ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Kabupaten</label>
            <select name="kabupaten_id" id="kabupaten" style="width:100%;padding:10px 14px;border:1px solid #d0d0d0;border-radius:6px;font-size:15px;">
                <option value="">Semua Kabupaten</option>
                <?php foreach ($kabupaten as $k): ?>
                <option value="<?= (int) $k['id'] ?>"
                    <?= ((int) ($oldInput['kabupaten_id'] ?? 0) === (int) $k['id']) ? 'selected' : '' ?>>
                    <?= \App\Core\Security::e($k['nama_kabupaten']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="desaSection" style="display:none">
            <div class="form-group">
                <label>Kecamatan</label>
                <select name="kecamatan_id" id="kecamatan" style="width:100%;padding:10px 14px;border:1px solid #d0d0d0;border-radius:6px;font-size:15px;">
                    <option value="">Semua Kecamatan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Desa</label>
                <select name="desa_id" id="desa" style="width:100%;padding:10px 14px;border:1px solid #d0d0d0;border-radius:6px;font-size:15px;">
                    <option value="">Semua Desa</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:16px">
            <div class="form-group" style="flex:1">
                <label>Tanggal Dari</label>
                <input type="date" name="tanggal_dari" value="<?= \App\Core\Security::e($oldInput['tanggal_dari'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label>Tanggal Sampai</label>
                <input type="date" name="tanggal_sampai" value="<?= \App\Core\Security::e($oldInput['tanggal_sampai'] ?? '') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" id="submitBtn" style="margin-top:8px">
            Unduh
        </button>
    </form>
</div>

<script>
function toggleForm() {
    var jenis = document.querySelector('input[name="jenis"]:checked').value;
    var btn = document.getElementById('submitBtn');
    if (jenis === 'hama') {
        btn.textContent = 'Unduh Laporan Hama';
    } else {
        btn.textContent = 'Unduh Laporan Irigasi';
    }
}
toggleForm();

document.getElementById('exportForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var jenis = document.querySelector('input[name="jenis"]:checked').value;
    var format = document.querySelector('input[name="format"]:checked').value;
    var checkboxes = document.querySelectorAll('input[name="status_filter[]"]:checked');
    var statusValues = [];
    checkboxes.forEach(function(cb) { statusValues.push(cb.value); });

    var form = this;
    var action = '/export/' + jenis;
    form.action = action;

    var hiddenStatus = document.createElement('input');
    hiddenStatus.type = 'hidden';
    hiddenStatus.name = 'status';
    hiddenStatus.value = statusValues.join(',');
    form.appendChild(hiddenStatus);

    checkboxes.forEach(function(cb) { cb.disabled = true; });

    form.submit();
});

document.getElementById('kabupaten').addEventListener('change', function() {
    var kabId = this.value;
    var desaSection = document.getElementById('desaSection');
    var kecamatan = document.getElementById('kecamatan');
    var desa = document.getElementById('desa');

    if (kabId === '') {
        desaSection.style.display = 'none';
        return;
    }

    desaSection.style.display = 'block';
    kecamatan.innerHTML = '<option value="">Memuat...</option>';
    desa.innerHTML = '<option value="">Semua Desa</option>';

    fetch('/wilayah/kecamatan-json?kabupaten_id=' + kabId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            kecamatan.innerHTML = '<option value="">Semua Kecamatan</option>';
            if (data.success && data.data) {
                data.data.forEach(function(k) {
                    var opt = document.createElement('option');
                    opt.value = k.id;
                    opt.textContent = k.nama_kecamatan;
                    kecamatan.appendChild(opt);
                });
            }
        });
});

document.getElementById('kecamatan').addEventListener('change', function() {
    var kecId = this.value;
    var desa = document.getElementById('desa');

    if (kecId === '') {
        desa.innerHTML = '<option value="">Semua Desa</option>';
        return;
    }

    desa.innerHTML = '<option value="">Memuat...</option>';

    fetch('/wilayah/desa-json?kecamatan_id=' + kecId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            desa.innerHTML = '<option value="">Semua Desa</option>';
            if (data.success && data.data) {
                data.data.forEach(function(d) {
                    var opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.nama_desa;
                    desa.appendChild(opt);
                });
            }
        });
});
</script>
