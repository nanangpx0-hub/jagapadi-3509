<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
.detail-label { font-weight: 600; color: #6c757d; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.4px; }
.detail-photo { max-width: 260px; width: 100%; border-radius: 10px; border: 1px solid #dee2e6; }
.timeline-item { position: relative; padding-left: 24px; padding-bottom: 16px; border-left: 2px solid #dee2e6; margin-left: 8px; }
.timeline-item:last-child { border-left-color: transparent; }
.timeline-item::before { content: ''; position: absolute; left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #28a745; }
.badge-st-revision { background: #fd7e14; color: #fff; }
</style>

<div class="row">
    <div class="col-lg-10 col-xl-9">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h3 class="card-title mb-2 mb-md-0"><i class="fas fa-lightbulb"></i> Detail Usulan OPT #<?= (int) $proposal['id'] ?></h3>
                <a href="<?= BASE_URL ?>usulan-opt" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <?php
                $statusClass = [
                    UsulanOpt::STATUS_DRAFT => 'badge-secondary',
                    UsulanOpt::STATUS_PENDING => 'badge-st-pending',
                    UsulanOpt::STATUS_REVISION => 'badge-st-revision',
                    UsulanOpt::STATUS_APPROVED => 'badge-success',
                    UsulanOpt::STATUS_MERGED => 'badge-info',
                    UsulanOpt::STATUS_REJECTED => 'badge-danger',
                ][$proposal['status']] ?? 'badge-secondary';
                ?>
                <?php if (!$is_admin && $proposal['status'] === UsulanOpt::STATUS_REVISION && !empty($proposal['catatan_review'])): ?>
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Admin meminta perbaikan:</strong>
                        <?= nl2br(htmlspecialchars($proposal['catatan_review'])) ?><br>
                        <a href="<?= BASE_URL ?>usulan-opt/edit/<?= (int) $proposal['id'] ?>" class="btn btn-warning btn-sm mt-2">
                            <i class="fas fa-edit"></i> Perbaiki Usulan
                        </a>
                    </div>
                <?php endif; ?>

                <p>Status:
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($proposal['status']) ?></span>
                </p>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <?php $gallery = array_merge(
                            $photos !== [] ? $photos : [],
                            ($photos === [] && !empty($proposal['foto_url'])) ? [['file_path' => $proposal['foto_url']]] : []
                        ); ?>
                        <?php if ($gallery !== []): ?>
                            <?php foreach ($gallery as $index => $photo):
                                $src = $this->photoUrl((string) $photo['file_path']);
                                if ($index >= 4) break;
                            ?>
                                <img src="<?= htmlspecialchars($src) ?>" alt="Foto bukti usulan <?= (int) $photo['id'] ?? '' ?>"
                                     class="detail-photo img-fluid mb-2">
                            <?php endforeach; ?>
                            <small class="text-muted d-block mb-2"><?= count($photos) > 0 ? count($photos) . ' foto terlampir' : '' ?></small>
                        <?php else: ?>
                            <div class="alert alert-light text-muted text-center py-5" role="img" aria-label="Tidak ada foto usulan">
                                <i class="fas fa-image fa-3x"></i><br>Belum ada foto bukti
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($proposal['master_nama_opt'])): ?>
                            <div class="card bg-light mt-2">
                                <div class="card-body py-2 px-3">
                                    <span class="detail-label">Master tujuan</span><br>
                                    <strong><?= htmlspecialchars($proposal['master_nama_opt']) ?></strong>
                                    <?php if (!empty($proposal['master_kode_opt'])): ?>
                                        (<?= htmlspecialchars($proposal['master_kode_opt']) ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h6 class="mt-3 mb-2">Riwayat status</h6>
                        <?php foreach ($history as $entry): ?>
                            <div class="timeline-item small">
                                <strong><?= htmlspecialchars((string) $entry['to_status']) ?></strong>
                                <span class="text-muted">· <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?></span>
                                <div class="text-muted">oleh <?= htmlspecialchars($entry['actor_nama'] ?? 'sistem') ?></div>
                                <?php if (!empty($entry['catatan'])): ?>
                                    <div class="text-dark"><?= nl2br(htmlspecialchars((string) $entry['catatan'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="col-md-8">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 detail-label">Nama nasional</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['nama_nasional'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Nama lokal</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['nama_lokal']) ?></dd>

                            <dt class="col-sm-4 detail-label">Jenis</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars(ucfirst($proposal['jenis'])) ?></dd>

                            <dt class="col-sm-4 detail-label">Komoditas</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['komoditas'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Tanggal ditemukan</dt>
                            <dd class="col-sm-8"><?= !empty($proposal['tanggal_ditemukan']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $proposal['tanggal_ditemukan']))) : '-' ?></dd>

                            <dt class="col-sm-4 detail-label">Wilayah</dt>
                            <dd class="col-sm-8">
                                <?= htmlspecialchars(trim(($proposal['nama_kabupaten'] ?? '') . ', ' . ($proposal['nama_kecamatan'] ?? '') . ', ' . ($proposal['nama_desa'] ?? ''), ', ') ?: ((string) ($proposal['wilayah'] ?: '-'))) ?>
                            </dd>

                            <dt class="col-sm-4 detail-label">Alamat lokasi</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['alamat_lokasi'] ?: '-') ?></dd>

                            <?php if ($proposal['latitude'] !== null || $proposal['longitude'] !== null): ?>
                                <dt class="col-sm-4 detail-label">Koordinat</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars(($proposal['latitude'] ?? '-') . ', ' . ($proposal['longitude'] ?? '-')) ?></dd>
                            <?php endif; ?>

                            <dt class="col-sm-4 detail-label">Bagian terserang</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['bagian_terserang'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Pola/gejala</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['pola_gejala'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Perkiraan terdampak</dt>
                            <dd class="col-sm-8"><?= $proposal['estimasi_terdampak'] !== null ? htmlspecialchars(rtrim(rtrim((string) $proposal['estimasi_terdampak'], '0'), '.') . ' ' . ($proposal['satuan_terdampak'] ?? '')) : '-' ?></dd>

                            <dt class="col-sm-4 detail-label">Keyakinan Petugas</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['tingkat_keyakinan'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Sumber identifikasi</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['sumber_identifikasi'] ?: '-') ?></dd>

                            <dt class="col-sm-4 detail-label">Ciri-ciri/gejala</dt>
                            <dd class="col-sm-8"><?= nl2br(htmlspecialchars($proposal['ciri_ciri'] ?: '-')) ?></dd>

                            <dt class="col-sm-4 detail-label">Pengusul</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($proposal['nama_pengusul']) ?></dd>

                            <dt class="col-sm-4 detail-label">Dibuat</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['created_at']))) ?></dd>

                            <?php if (!empty($proposal['submitted_at'])): ?>
                                <dt class="col-sm-4 detail-label">Dikirim review</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['submitted_at']))) ?></dd>
                            <?php endif; ?>

                            <dt class="col-sm-4 detail-label">Laporan terkait</dt>
                            <dd class="col-sm-8"><?= (int) $laporan_count ?> laporan hama terhubung ke usulan ini</dd>

                            <?php if (!empty($proposal['reviewed_at'])): ?>
                                <dt class="col-sm-4 detail-label">Keputusan terakhir</dt>
                                <dd class="col-sm-8">
                                    <?= htmlspecialchars($proposal['reviewer_nama'] ?? '-') ?>
                                    pada <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['reviewed_at']))) ?>
                                    <?php if (!empty($proposal['catatan_review'])): ?>
                                        <div class="mt-1"><?= nl2br(htmlspecialchars($proposal['catatan_review'])) ?></div>
                                    <?php endif; ?>
                                </dd>
                            <?php endif; ?>
                        </dl>

                        <?php if ($is_admin && $proposal['status'] === UsulanOpt::STATUS_PENDING && !empty($duplicates)): ?>
                            <div class="alert alert-warning mt-3" role="alert">
                                <strong><i class="fas fa-exclamation-triangle"></i> Kemungkinan duplikat master:</strong>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($duplicates as $dup): ?>
                                        <li>
                                            <?= htmlspecialchars(($dup['kode_opt'] ? '[' . $dup['kode_opt'] . '] ' : '') . $dup['nama_opt']) ?>
                                            (<?= htmlspecialchars(ucfirst((string) $dup['jenis'])) ?>, <?= (int) $dup['aktif'] === 1 ? 'aktif' : 'non-aktif' ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <hr>
                                Gunakan aksi <strong>Gabungkan</strong> pada daftar usulan bila salah satu di atas adalah master yang dimaksud.
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <?php if ($can_edit): ?>
                                <a href="<?= BASE_URL ?>usulan-opt/edit/<?= (int) $proposal['id'] ?>" class="btn btn-warning mr-2">
                                    <i class="fas fa-edit"></i> Edit Usulan
                                </a>
                                <?php if ($proposal['status'] === UsulanOpt::STATUS_DRAFT): ?>
                                    <form method="POST" action="<?= BASE_URL ?>usulan-opt/submit" class="d-inline js-owner-action-detail"
                                          onsubmit="return confirm('Kirim usulan untuk direview Admin? Minimal satu foto bukti harus ada.');">
                                        <?= Security::getCsrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                        <button type="submit" class="btn btn-success mr-2"><i class="fas fa-paper-plane"></i> Kirim untuk Review</button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>usulan-opt/delete-draft"
                                          onsubmit="return confirm('Hapus permanen draf ini?');">
                                        <?= Security::getCsrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Hapus Draf</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>usulan-opt/resubmit" class="d-inline js-owner-action-detail"
                                          onsubmit="return confirm('Kirim ulang usulan yang telah diperbaiki?');">
                                        <?= Security::getCsrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-redo"></i> Kirim Ulang untuk Review</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($is_admin && $proposal['status'] === UsulanOpt::STATUS_PENDING): ?>
                                <a href="<?= BASE_URL ?>usulan-opt/finalize/<?= (int) $proposal['id'] ?>" class="btn btn-success mr-2">
                                    <i class="fas fa-check"></i> Setujui sebagai Master Baru
                                </a>
                                <a href="<?= BASE_URL ?>usulan-opt?status=<?= urlencode(UsulanOpt::STATUS_PENDING) ?>" class="btn btn-info mr-2">
                                    <i class="fas fa-code-branch"></i> Gabungkan dari Daftar
                                </a>
                                <a href="<?= BASE_URL ?>usulan-opt?status=<?= urlencode(UsulanOpt::STATUS_PENDING) ?>" class="btn btn-danger">
                                    <i class="fas fa-ban"></i> Tolak Permanen dari Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-owner-action-detail').forEach(function (formEl) {
            formEl.addEventListener('submit', function () {
                var btn = formEl.querySelector('button[type="submit"]');
                if (btn) { setTimeout(function () { btn.disabled = true; }, 0); }
            });
        });
    });
})();
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
