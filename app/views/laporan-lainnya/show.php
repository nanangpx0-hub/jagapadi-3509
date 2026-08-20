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
                    <?php if(in_array($laporan['status'], ['draft', 'rejected'], true) && ($laporan['user_id'] == $_SESSION['user_id'] || $_SESSION['role'] === 'admin')): ?>
                    <a href="<?= BASE_URL ?>laporan-lainnya/edit/<?= $laporan['id'] ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] === 'admin' && in_array($laporan['status'], ['submitted', 'verified', 'rejected'], true)): ?>
                    <form method="POST" action="<?= BASE_URL ?>laporan-lainnya/archive/<?= (int)$laporan['id'] ?>" class="d-inline" onsubmit="return confirm('Arsipkan laporan ini?');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-sm btn-dark">
                            <i class="fas fa-archive"></i> Arsipkan
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr><th>Kode Laporan</th><td><code><?= $laporan['kode_laporan'] ? htmlspecialchars($laporan['kode_laporan']) : '(Belum ada — masih Draf)' ?></code></td></tr>
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
                                        'archived' => ['dark', 'Diarsipkan'],
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
                <?php
                // fieldMap: label ramah dari jenisFields (database), fallback ke mapping default
                $fieldMap = [];
                foreach (($jenisFields ?? []) as $field) {
                    $label = $field['label'] ?? $field['name'];
                    if (str_ends_with((string)$label, '_')) {
                        $label = rtrim((string)$label, '_');
                    }
                    $fieldMap[$field['name']] = $label;
                }
                $fallbackMap = [
                    'jumlah_bibit' => 'Jumlah Bibit (unit)',
                    'sumber_bibit' => 'Sumber Bibit',
                    'nama_varietas' => 'Nama Varietas',
                    'jumlah_unit' => 'Jumlah Unit',
                    'luas_m2' => 'Luas (m²)',
                    'komoditas' => 'Komoditas',
                    'luas_ha' => 'Luas Panen (Ha)',
                    'estimasi_ton' => 'Estimasi Panen (Ton)',
                    'nama_alat' => 'Nama Alat',
                    'jumlah' => 'Jumlah Unit',
                    'sumber_bantuan' => 'Sumber Bantuan',
                    'jenis_cuaca' => 'Jenis Cuaca',
                    'luas_terdampak_ha' => 'Luas Terdampak (Ha)',
                ];
                ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>Field</th><th>Nilai</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($dataJson as $key => $value): ?>
                        <tr>
                            <td><?= htmlspecialchars($fieldMap[$key] ?? $fallbackMap[$key] ?? $key) ?></td>
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

                <?php if(in_array($laporan['status'], ['verified', 'rejected'], true)): ?>                <hr>
                <div class="alert alert-<?= $laporan['status'] === 'verified' ? 'success' : 'danger' ?>">
                    <strong>
                        <?= $laporan['status'] === 'verified' ? '<i class="fas fa-check-circle"></i> Diverifikasi' : '<i class="fas fa-times-circle"></i> Ditolak' ?>
                    </strong>
                    oleh <?= htmlspecialchars($laporan['verifikator_nama'] ?? '-') ?>
                    pada <?= htmlspecialchars($laporan['verified_at'] ?? '-') ?>
                    <?php if($laporan['catatan_verifikasi']): ?>
                    <div class="mt-2"><em><?= nl2br(htmlspecialchars($laporan['catatan_verifikasi'])) ?></em></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if(($laporan['status'] === 'submitted') && ($_SESSION['role'] ?? '') === 'admin'): ?>
                <hr>
                <h5><i class="fas fa-clipboard-check"></i> Verifikasi Laporan</h5>
                <div class="row">
                    <div class="col-md-6">
                        <form method="POST" action="<?= BASE_URL ?>laporan-lainnya/verify/<?= (int)$laporan['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <div class="form-group">
                                <label>Catatan Verifikasi (opsional)</label>
                                <textarea name="catatan_verifikasi" class="form-control" rows="2" placeholder="Catatan untuk pelapor"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Verifikasi
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" action="<?= BASE_URL ?>laporan-lainnya/reject/<?= (int)$laporan['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <div class="form-group">
                                <label>Alasan Penolakan <span class="text-danger">*</span></label>
                                <textarea name="catatan_verifikasi" class="form-control" rows="2" required placeholder="Alasan penolakan wajib diisi"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>