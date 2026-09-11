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

<div class="row jenis-no-motion">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list"></i> Master Jenis Laporan
                </h3>
                <div class="mt-2 mt-sm-0">
                    <a href="<?= BASE_URL ?>recycle-bin?module=jenis-laporan" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-recycle"></i> Recycle Bin
                    </a>
                    <a href="<?= BASE_URL ?>jenis-laporan/create" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Tambah Jenis
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Jenis <strong>aktif</strong> tampil di dropdown form Laporan Lainnya.
                    Jenis yang sudah dipakai laporan tidak dapat dihapus (FK RESTRICT) — nonaktifkan saja agar histori tetap utuh.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width:5%">#</th>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Field Dinamis</th>
                                <th style="width:10%">Dipakai</th>
                                <th style="width:10%">Status</th>
                                <th style="width:22%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Belum ada jenis laporan.</td>
                            </tr>
                            <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($rows as $row): ?>
                            <?php
                                $fields = json_decode((string) ($row['fields_json'] ?? ''), true);
                                $fieldCount = is_array($fields) ? count($fields) : 0;
                                $used = (int) ($row['dipakai'] ?? 0);
                                $active = ((int) ($row['is_active'] ?? 0)) === 1;
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><code><?= htmlspecialchars((string) ($row['kode'] ?? '')) ?></code></td>
                                <td><?= htmlspecialchars((string) ($row['nama'] ?? '')) ?></td>
                                <td class="text-center"><?= $fieldCount ?> field</td>
                                <td class="text-center"><?= $used ?> laporan</td>
                                <td class="text-center">
                                    <?php if ($active): ?>
                                    <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>jenis-laporan/edit/<?= (int) $row['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form action="<?= BASE_URL ?>jenis-laporan/toggle/<?= (int) $row['id'] ?>" method="POST" class="d-inline">
                                        <?= Security::getCsrfField() ?>
                                        <button type="submit" class="btn btn-sm <?= $active ? 'btn-warning' : 'btn-success' ?> mb-1">
                                            <i class="fas fa-<?= $active ? 'eye-slash' : 'eye' ?>"></i>
                                            <?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <form action="<?= BASE_URL ?>jenis-laporan/delete/<?= (int) $row['id'] ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('Pindahkan jenis <?= htmlspecialchars((string) ($row['kode'] ?? ''), ENT_QUOTES) ?> ke recycle bin? Data dapat dipulihkan kembali.')">
                                        <?= Security::getCsrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-danger mb-1" <?= $used > 0 ? 'disabled title="Dipakai laporan, tidak dapat dihapus"' : '' ?>>
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
