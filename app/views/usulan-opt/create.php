<?php
$isEdit = false;
include ROOT_PATH . '/app/views/layouts/header.php';
?>


<link rel="stylesheet" href="<?= BASE_URL ?>public/css/hover-disabled.css"><div class="row">
    <div class="col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h3 class="card-title mb-2 mb-md-0"><i class="fas fa-plus-circle"></i> Buat Usulan OPT Baru</h3>
                <a href="<?= BASE_URL ?>usulan-opt" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>usulan-opt/store" id="usulan_form"
                      enctype="multipart/form-data">
                    <?= Security::getCsrfField() ?>
                    <input type="hidden" name="intent" id="usulan_intent" value="draft">
                    <?php include ROOT_PATH . '/app/views/usulan-opt/_form.php'; ?>

                    <div class="border-top pt-3 mt-2 d-flex flex-wrap gap-2">
                        <button type="submit" data-intent="submit" class="btn btn-success mr-2 mb-2 mb-md-0">
                            <i class="fas fa-paper-plane"></i> Kirim untuk Review
                        </button>
                        <button type="submit" data-intent="draft" class="btn btn-outline-secondary">
                            <i class="fas fa-save"></i> Simpan Draf
                        </button>
                    </div>
                    <small class="form-text text-muted mt-2">
                        "Simpan Draf" menyimpan sementara tanpa foto wajib. "Kirim untuk Review" memvalidasi kelengkapan termasuk minimal satu foto bukti.
                    </small>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('usulan_form');
    if (!form) { return; }

    var selects = {
        kabupaten: document.getElementById('kabupaten_id'),
        kecamatan: document.getElementById('kecamatan_id'),
        desa: document.getElementById('desa_id')
    };
    var selected = {
        kabupaten: '<?= htmlspecialchars((string) ($old['kabupaten_id'] ?? ''), ENT_QUOTES) ?>',
        kecamatan: '<?= htmlspecialchars((string) ($old['kecamatan_id'] ?? ''), ENT_QUOTES) ?>',
        desa: '<?= htmlspecialchars((string) ($old['desa_id'] ?? ''), ENT_QUOTES) ?>'
    };

    function fill(select, items, placeholder, selectedId) {
        select.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        select.appendChild(opt);
        items.forEach(function (item) {
            var o = document.createElement('option');
            o.value = item.id;
            o.textContent = item.nama_kabupaten || item.nama_kecamatan || item.nama_desa || ('#' + item.id);
            if (String(item.id) === String(selectedId)) { o.selected = true; }
            select.appendChild(o);
        });
        select.disabled = false;
    }

    function load(url, target, placeholder, selectedId, after) {
        target.disabled = true;
        target.innerHTML = '<option value="">Memuat...</option>';
        fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (json) {
                fill(target, json.data || [], placeholder, selectedId);
                if (after) { after(); }
            })
            .catch(function () {
                target.innerHTML = '<option value="">Gagal memuat</option>';
            });
    }

    function loadKecamatan(keepDesa) {
        if (!selects.kabupaten.value) {
            selects.kecamatan.innerHTML = '<option value="">Pilih kabupaten dahulu</option>';
            selects.kecamatan.disabled = true;
            selects.desa.innerHTML = '<option value="">Pilih kecamatan dahulu</option>';
            selects.desa.disabled = true;
            return;
        }
        load('<?= BASE_URL ?>wilayah/kecamatan/' + encodeURIComponent(selects.kabupaten.value), selects.kecamatan,
            'Pilih kecamatan', keepDesa ? selected.kecamatan : '', function () {
                loadDesa(!keepDesa);
            });
    }

    function loadDesa(reset) {
        if (!selects.kecamatan.value) {
            selects.desa.innerHTML = '<option value="">Pilih kecamatan dahulu</option>';
            selects.desa.disabled = true;
            return;
        }
        if (reset) { selects.desa.value = ''; }
        load('<?= BASE_URL ?>wilayah/desa/' + encodeURIComponent(selects.kecamatan.value), selects.desa,
            'Pilih desa', reset ? '' : selected.desa);
    }

    load('<?= BASE_URL ?>wilayah/kabupaten', selects.kabupaten, 'Pilih kabupaten', selected.kabupaten, function () {
        if (selected.kabupaten) { loadKecamatan(true); }
    });

    selects.kabupaten.addEventListener('change', function () {
        selected.kecamatan = ''; selected.desa = '';
        loadKecamatan(false);
    });
    selects.kecamatan.addEventListener('change', function () { loadDesa(true); });

    var photoInput = document.getElementById('photos_input');
    var previewBox = document.getElementById('usulan_photo_preview');
    photoInput.addEventListener('change', function () {
        previewBox.innerHTML = '';
        Array.prototype.slice.call(photoInput.files).forEach(function (file) {
            if (!file.type.match(/^image\//)) { return; }
            var img = document.createElement('img');
            img.alt = 'Pratinjau ' + file.name;
            img.className = 'img-thumbnail mr-1 mb-1';
            img.style.maxWidth = '90px';
            img.style.maxHeight = '90px';
            img.src = URL.createObjectURL(file);
            previewBox.appendChild(img);
        });
    });

    form.addEventListener('submit', function (event) {
        var intentValue = event.submitter ? event.submitter.getAttribute('data-intent') : 'draft';
        if (intentValue === 'submit' && !window.confirm('Kirim usulan ini untuk direview Admin?')) {
            event.preventDefault();
            return;
        }
        document.getElementById('usulan_intent').value = intentValue === 'submit' ? 'submit' : 'draft';
        var buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(function (b) { b.disabled = true; });
    });
})();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
