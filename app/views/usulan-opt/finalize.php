<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
.required::after { content: " *"; color: #dc3545; }
.finalize-photo { max-width: 240px; width: 100%; border-radius: 10px; border: 1px solid #dee2e6; }
.form-filter-label { font-size: 0.78rem; color: #6c757d; margin-bottom: 0.15rem; display: block; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h3 class="card-title mb-2 mb-md-0">
                    <i class="fas fa-check-circle"></i> Finalisasi Master OPT dari Usulan #<?= (int) $proposal['id'] ?>
                </h3>
                <a href="<?= BASE_URL ?>usulan-opt" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    Usulan dari <strong><?= htmlspecialchars($proposal['nama_pengusul']) ?></strong>:
                    nama lokal <strong><?= htmlspecialchars($proposal['nama_lokal']) ?></strong>
                    (<?= htmlspecialchars(ucfirst($proposal['jenis'])) ?>).
                    Lengkapi data master di bawah sebelum menyetujui. Validasi mengikuti form Tambah OPT.
                </div>

                <?php if (!empty($duplicates)): ?>
                <div class="alert alert-warning" role="alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> Kandidat duplikat ditemukan:</strong>
                    <ul class="mb-2 mt-1">
                        <?php foreach ($duplicates as $dup): ?>
                        <li>
                            <?= htmlspecialchars(($dup['kode_opt'] ? '[' . $dup['kode_opt'] . '] ' : '') . $dup['nama_opt']) ?>
                            (<?= htmlspecialchars(ucfirst((string) $dup['jenis'])) ?>, <?= (int) $dup['aktif'] === 1 ? 'aktif' : 'non-aktif' ?>)
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    Jika salah satu adalah master yang sama, batalkan dan gunakan aksi
                    <strong>Gabungkan</strong> pada daftar usulan. Persetujuan tetap dapat dilanjutkan
                    bila Anda yakin ini organisme yang berbeda.
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>usulan-opt/approve-new" id="finalize_form" novalidate>
                    <?= Security::getCsrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                    <input type="hidden" name="foto_url" value="<?= htmlspecialchars($prefill['foto_url'] ?? '') ?>">

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="kode_opt">Kode OPT</label>
                                        <input type="text" id="kode_opt" name="kode_opt" class="form-control"
                                               maxlength="50" value="<?= htmlspecialchars($prefill['kode_opt'] ?? '') ?>"
                                               placeholder="Dibuat otomatis saat disetujui" readonly>
                                        <small class="form-text text-muted">Format otomatis: OPT-H/P/G-000001 sesuai jenis.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="required" for="nama_opt">Nama Nasional</label>
                                        <input type="text" id="nama_opt" name="nama_opt" class="form-control" required
                                               maxlength="150" value="<?= htmlspecialchars($prefill['nama_opt'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nama_lokal">Nama Lokal</label>
                                        <input type="text" id="nama_lokal" name="nama_lokal" class="form-control"
                                               maxlength="200" value="<?= htmlspecialchars($prefill['nama_lokal'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nama_ilmiah">Nama Ilmiah</label>
                                        <input type="text" id="nama_ilmiah" name="nama_ilmiah" class="form-control"
                                               maxlength="200" value="<?= htmlspecialchars($prefill['nama_ilmiah'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="required" for="jenis">Jenis</label>
                                        <select id="jenis" name="jenis" class="form-control" required>
                                            <?php foreach ($filter_options['jenis'] as $jenisOpt): ?>
                                            <option value="<?= htmlspecialchars($jenisOpt) ?>" <?= ($prefill['jenis'] ?? '') === $jenisOpt ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucfirst($jenisOpt)) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="kategori">Kategori</label>
                                        <select id="kategori" name="kategori" class="form-control">
                                            <option value="">-</option>
                                            <?php foreach ($filter_options['kategori'] as $kat): ?>
                                            <option value="<?= htmlspecialchars($kat) ?>" <?= ($prefill['kategori'] ?? '') === $kat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($kat) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status_karantina">Status Karantina</label>
                                        <select id="status_karantina" name="status_karantina" class="form-control">
                                            <?php foreach ($filter_options['status_karantina'] as $sk): ?>
                                            <option value="<?= htmlspecialchars($sk) ?>" <?= ($prefill['status_karantina'] ?? '') === $sk ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sk) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="tingkat_bahaya">Tingkat Bahaya</label>
                                        <select id="tingkat_bahaya" name="tingkat_bahaya" class="form-control">
                                            <?php foreach ($filter_options['tingkat_bahaya'] as $tb): ?>
                                            <option value="<?= htmlspecialchars($tb) ?>" <?= ($prefill['tingkat_bahaya'] ?? '') === $tb ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tb) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="etl_acuan">ETL Acuan</label>
                                        <input type="number" step="0.01" min="0" id="etl_acuan" name="etl_acuan"
                                               class="form-control" value="<?= htmlspecialchars((string) ($prefill['etl_acuan'] ?? '')) ?>"
                                               placeholder="cth. 10">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="satuan_etl">Satuan ETL</label>
                                        <input type="text" id="satuan_etl" name="satuan_etl" class="form-control" list="satuan_etl_list"
                                               maxlength="30" value="<?= htmlspecialchars($prefill['satuan_etl'] ?? '') ?>">
                                        <datalist id="satuan_etl_list">
                                            <option value="%"></option>
                                            <option value="ekor/rumpun"></option>
                                            <option value="ekor/tanaman"></option>
                                            <option value="ekor/m²"></option>
                                        </datalist>
                                    </div>
                                </div>

                                <div class="col-12 mt-1 mb-2"><h6 class="text-muted mb-0">Klasifikasi Ilmiah (opsional)</h6></div>

                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="kingdom">Kingdom</label>
                                        <input type="text" id="kingdom" name="kingdom" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['kingdom'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="filum">Filum/Divisi</label>
                                        <input type="text" id="filum" name="filum" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['filum'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="kelas">Kelas</label>
                                        <input type="text" id="kelas" name="kelas" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['kelas'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="ordo">Ordo</label>
                                        <input type="text" id="ordo" name="ordo" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['ordo'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="famili">Famili</label>
                                        <input type="text" id="famili" name="famili" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['famili'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="genus">Genus</label>
                                        <input type="text" id="genus" name="genus" class="form-control" maxlength="100"
                                               value="<?= htmlspecialchars($prefill['genus'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="deskripsi">Deskripsi / Ciri-ciri</label>
                                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($prefill['deskripsi'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="rekomendasi">Rekomendasi Pengendalian</label>
                                        <textarea id="rekomendasi" name="rekomendasi" class="form-control" rows="2"><?= htmlspecialchars($prefill['rekomendasi'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="referensi">Referensi/Sumber Data</label>
                                        <textarea id="referensi" name="referensi" class="form-control" rows="2"><?= htmlspecialchars($prefill['referensi'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group form-check">
                                        <input type="checkbox" class="form-check-input" id="aktif" name="aktif" value="1"
                                               <?= !empty($prefill['aktif']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aktif">Master aktif (dapat dipilih pada laporan)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="catatan_review">Catatan review (opsional)</label>
                                        <textarea id="catatan_review" name="catatan_review" class="form-control" rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="border-top pt-3 mt-2 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-success" id="finalize_submit">
                                    <i class="fas fa-check"></i> Setujui &amp; Buat Master
                                </button>
                                <a href="<?= BASE_URL ?>usulan-opt" class="btn btn-secondary">Batal</a>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-header py-2"><strong>Foto Usulan Petugas</strong></div>
                                <div class="card-body text-center">
                                    <?php if (!empty($proposal['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($this->photoUrl($proposal['foto_url'])) ?>"
                                             alt="Foto usulan <?= htmlspecialchars($proposal['nama_lokal']) ?>" class="finalize-photo img-fluid">
                                        <p class="small text-muted mt-2 mb-0">Foto ini otomatis dipakai sebagai foto master.</p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0 py-4"><i class="fas fa-image fa-2x"></i><br>Tidak ada foto usulan</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card bg-light mt-3">
                                <div class="card-header py-2"><strong>Ringkasan Usulan</strong></div>
                                <div class="card-body py-2 small">
                                    <div><span class="detail-label">Komoditas:</span> <?= htmlspecialchars($proposal['komoditas'] ?: '-') ?></div>
                                    <div><span class="detail-label">Wilayah:</span> <?= htmlspecialchars($proposal['wilayah'] ?: '-') ?></div>
                                    <div><span class="detail-label">Ciri:</span> <?= nl2br(htmlspecialchars(mb_substr((string) ($proposal['ciri_ciri'] ?: '-'), 0, 300))) ?></div>
                                    <div><span class="detail-label">Pengusul:</span> <?= htmlspecialchars($proposal['nama_pengusul']) ?></div>
                                    <div><span class="detail-label">Tanggal:</span> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['created_at']))) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('finalize_form');
    if (!form) { return; }
    form.addEventListener('submit', function (event) {
        if (!window.confirm('Setujui usulan ini dan buat master OPT baru?')) {
            event.preventDefault();
            return;
        }
        var btn = document.getElementById('finalize_submit');
        btn.disabled = true;
    });
})();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
