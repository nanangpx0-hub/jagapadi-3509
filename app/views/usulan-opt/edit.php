<?php
/** @var array $proposal @var array $photos */
$isEdit = true;
$actionUrl = BASE_URL . 'usulan-opt/update';
include ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="row">
    <div class="col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h3 class="card-title mb-2 mb-md-0"><i class="fas fa-edit"></i> Edit Usulan OPT #<?= (int) $proposal['id'] ?></h3>
                <a href="<?= BASE_URL ?>usulan-opt/detail/<?= (int) $proposal['id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <?php if ($proposal['status'] === UsulanOpt::STATUS_REVISION && !empty($proposal['catatan_review'])): ?>
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Catatan perbaikan Admin:</strong>
                        <?= nl2br(htmlspecialchars($proposal['catatan_review'])) ?>
                        <hr class="my-2">
                        Perbaiki data sesuai catatan, lalu klik "Kirim Ulang untuk Review".
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $actionUrl ?>" id="usulan_form" enctype="multipart/form-data">
                    <?= Security::getCsrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                    <input type="hidden" name="expected_status" value="<?= htmlspecialchars($proposal['status']) ?>">
                    <?php include ROOT_PATH . '/app/views/usulan-opt/_form.php'; ?>

                    <div class="border-top pt-3 mt-2 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary mr-2 mb-2 mb-md-0">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <?php if ($proposal['status'] === UsulanOpt::STATUS_DRAFT): ?>
                            <button type="button" class="btn btn-success" id="btn_submit_review">
                                <i class="fas fa-paper-plane"></i> Kirim untuk Review
                            </button>
                        <?php elseif ($proposal['status'] === UsulanOpt::STATUS_REVISION): ?>
                            <button type="button" class="btn btn-success" id="btn_resubmit_review">
                                <i class="fas fa-redo"></i> Kirim Ulang untuk Review
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($photos !== []): ?>
                    <h6 class="mt-4">Foto terlampir</h6>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($photos as $photo):
                            $src = $this->photoUrl((string) $photo['file_path']);
                        ?>
                            <div class="text-center">
                                <img src="<?= htmlspecialchars($src) ?>" alt="Foto usulan" style="max-width:110px;max-height:110px" class="img-thumbnail">
                                <form method="POST" action="<?= BASE_URL ?>usulan-opt/delete-photo" class="mt-1"
                                      onsubmit="return confirm('Hapus foto ini?');">
                                    <?= Security::getCsrfField() ?>
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Hapus foto usulan">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($proposal['status'] === UsulanOpt::STATUS_DRAFT): ?>
                    <div class="border-top pt-3 mt-3">
                        <form method="POST" action="<?= BASE_URL ?>usulan-opt/delete-draft"
                              onsubmit="return confirm('Hapus permanen draf ini? Tindakan tidak dapat dibatalkan.');">
                            <?= Security::getCsrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash"></i> Hapus Draf
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('usulan_form');
    if (!form) { return; }
    var proposalId = <?= (int) $proposal['id'] ?>;
    var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';

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
        opt.value = ''; opt.textContent = placeholder;
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
            .then(function (json) { fill(target, json.data || [], placeholder, selectedId); if (after) { after(); } })
            .catch(function () { target.innerHTML = '<option value="">Gagal memuat</option>'; });
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
            'Pilih kecamatan', keepDesa ? selected.kecamatan : '', function () { loadDesa(!keepDesa); });
    }

    function loadDesa(reset) {
        if (!selects.kecamatan.value) { return; }
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
            img.style.maxWidth = '90px'; img.style.maxHeight = '90px';
            img.src = URL.createObjectURL(file);
            previewBox.appendChild(img);
        });
    });

    function postAction(actionPath, confirmText) {
        if (!window.confirm(confirmText)) { return; }
        var body = new URLSearchParams();
        body.append('id', String(proposalId));
        body.append('csrf_token', csrfToken);
        fetch(actionPath, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: body,
            redirect: 'follow'
        }).then(function (r) {
            window.location.reload();
        }).catch(function () {
            window.location.reload();
        });
    }

    var btnSubmit = document.getElementById('btn_submit_review');
    if (btnSubmit) {
        btnSubmit.addEventListener('click', function () {
            postAction('<?= BASE_URL ?>usulan-opt/submit', 'Kirim usulan ini untuk direview Admin? Pastikan data dan minimal satu foto sudah lengkap.');
        });
    }
    var btnResubmit = document.getElementById('btn_resubmit_review');
    if (btnResubmit) {
        btnResubmit.addEventListener('click', function () {
            postAction('<?= BASE_URL ?>usulan-opt/resubmit', 'Kirim ulang usulan yang telah diperbaiki untuk review Admin?');
        });
    }

    form.addEventListener('submit', function () {
        var buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(function (b) { b.disabled = true; });
    });
})();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
