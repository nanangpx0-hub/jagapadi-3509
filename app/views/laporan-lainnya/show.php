<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt"></i> Detail Laporan Lainnya
                </h3>
                <div class="card-tools">
                    <a href="<?= BASE_URL ?>laporan-lainnya" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <?php if($laporan['status'] === 'draft' && ($laporan['user_id'] == $_SESSION['user_id'] || $_SESSION['role'] === 'admin')): ?>
                    <a href="<?= BASE_URL ?>laporan-lainnya/edit/<?= $laporan['id'] ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr><th>Kode Laporan</th><td><code><?= htmlspecialchars($laporan['kode_laporan']) ?></code></td></tr>
                            <tr><th>Jenis Laporan</th><td><?= htmlspecialchars($laporan['jenis_nama']) ?></td></tr>
                            <tr><th>Tanggal Kejadian</th><td><?= $laporan['tanggal_kejadian'] ? htmlspecialchars($laporan['tanggal_kejadian']) : '—' ?></td></tr>
                            <tr><th>Lokasi</th><td>
                                <?= htmlspecialchars($laporan['nama_kabupaten'] ?? '-') ?>,
                                <?= htmlspecialchars($laporan['nama_kecamatan'] ?? '-') ?>,
                                <?= htmlspecialchars($laporan['nama_desa'] ?? '-') ?>
                            </td></tr>
                            <tr><th>Alamat Lengkap</th><td><?= htmlspecialchars($laporan['alamat_lengkap'] ?? '—') ?></td></tr>
                            <tr><th>Status</th>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'draft' => ['secondary', 'Draf'],
                                        'submitted' => ['primary', 'Submitted'],
                                        'verified' => ['success', 'Diverifikasi'],
                                        'rejected' => ['danger', 'Ditolak'],
                                    ];
                                    $sts = $statusMap[$laporan['status']] ?? ['secondary', $laporan['status']];
                                    ?>
                                    <span class="badge badge-<?= $sts[0] ?>"><?= $sts[1] ?></span>
                                </td>
                            </tr>
                            <tr><th>Pelapor</th><td><?= htmlspecialchars($laporan['pelapor_nama'] ?? '-') ?></td></tr>
                            <?php if(!empty($laporan['foto_url'])): ?>
                            <tr><th>Foto</th><td>
                                <img src="<?= BASE_URL . 'public/' . htmlspecialchars($laporan['foto_url']) ?>" class="img-thumbnail" style="max-width: 300px; max-height: 300px;" alt="Foto Laporan">
                            </td></tr>
                            <?php endif; ?>
                            <tr><th>Dibuat</th><td><?= htmlspecialchars($laporan['created_at'] ?? '-') ?></td></tr>
                            <?php if($laporan['updated_at'] && $laporan['updated_at'] !== $laporan['created_at']): ?>
                            <tr><th>Diperbarui</th><td><?= htmlspecialchars($laporan['updated_at']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if($laporan['latitude'] && $laporan['longitude']): ?>
                        <div class="alert alert-info">
                            <strong>Koordinat:</strong> <?= $laporan['latitude'] ?>, <?= $laporan['longitude'] ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(!empty($dataJson)): ?>
                <h5><i class="fas fa-table"></i> Data Laporan</h5>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>Field</th><th>Nilai</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($dataJson as $key => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($key) ?></td>
                            <td><?= htmlspecialchars($value ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if($laporan['deskripsi']): ?>
                <h5><i class="fas fa-align-left"></i> Deskripsi</h5>
                <p><?= nl2br(htmlspecialchars($laporan['deskripsi'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>