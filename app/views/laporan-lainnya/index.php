<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Laporan Lainnya</h3>
                <div class="card-tools">
                    <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'operator', 'petugas'])): ?>
                    <a href="<?= BASE_URL ?>laporan-lainnya/create" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Buat Laporan Baru
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if($_SESSION['role'] ?? '' === 'petugas'): ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i>
                    <strong>Mode Petugas:</strong> Anda hanya dapat melihat laporan yang Anda buat sendiri.
                </div>
                <?php endif; ?>

                <!-- Filter Form -->
                <form method="GET" action="<?= BASE_URL ?>laporan-lainnya" class="mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <select name="jenis_id" class="form-control">
                                <option value="">-- Semua Jenis --</option>
                                <?php foreach($jenisList as $jl): ?>
                                <option value="<?= $jl['id'] ?>" <?= ($jenisId ?? '') == $jl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jl['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">-- Semua Status --</option>
                                <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>>Draf</option>
                                <option value="submitted" <?= ($status ?? '') === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="verified" <?= ($status ?? '') === 'verified' ? 'selected' : '' ?>>Diverifikasi</option>
                                <option value="rejected" <?= ($status ?? '') === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom ?? '') ?>" placeholder="Dari tanggal">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo ?? '') ?>" placeholder="Sampai tanggal">
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Cari laporan...">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="laporanTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Laporan</th>
                                <th>Jenis</th>
                                <th>Tanggal Kejadian</th>
                                <th>Desa</th>
                                <th>Status</th>
                                <th>Pelapor</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($laporan)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Tidak ada data laporan</td>
                            </tr>
                            <?php else: ?>
                            <?php $no = ($page - 1) * $perPage + 1; ?>
                            <?php foreach($laporan as $item): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><code><?= htmlspecialchars($item['kode_laporan']) ?></code></td>
                                <td><?= htmlspecialchars($item['jenis_nama']) ?></td>
                                <td><?= $item['tanggal_kejadian'] ? htmlspecialchars($item['tanggal_kejadian']) : '—' ?></td>
                                <td><?= htmlspecialchars($item['nama_desa'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'draft' => ['secondary', 'Draf'],
                                        'submitted' => ['primary', 'Submitted'],
                                        'verified' => ['success', 'Diverifikasi'],
                                        'rejected' => ['danger', 'Ditolak'],
                                    ];
                                    $sts = $statusMap[$item['status']] ?? ['secondary', $item['status']];
                                    ?>
                                    <span class="badge badge-<?= $sts[0] ?>"><?= $sts[1] ?></span>
                                </td>
                                <td><?= htmlspecialchars($item['pelapor_nama'] ?? '-') ?></td>
                                <td><small><?= htmlspecialchars($item['created_at'] ?? '-') ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>laporan-lainnya/show/<?= $item['id'] ?>" class="btn btn-info btn-sm" title="Lihat">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if($item['status'] === 'draft'): ?>
                                        <a href="<?= BASE_URL ?>laporan-lainnya/edit/<?= $item['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="<?= BASE_URL ?>laporan-lainnya/submit/<?= $item['id'] ?>" method="POST" class="d-inline">
                                            <?= Security::getCsrfField() ?>
                                            <button type="submit" class="btn btn-success btn-sm" title="Submit" onclick="return confirm('Submit laporan ini?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php for($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= BASE_URL ?>laporan-lainnya?page=<?= $p ?>&status=<?= urlencode($status ?? '') ?>&jenis_id=<?= urlencode($jenisId ?? '') ?>&desa_id=<?= urlencode($desaId ?? '') ?>&date_from=<?= urlencode($dateFrom ?? '') ?>&date_to=<?= urlencode($dateTo ?? '') ?>&search=<?= urlencode($search ?? '') ?>">
                                <?= $p ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>