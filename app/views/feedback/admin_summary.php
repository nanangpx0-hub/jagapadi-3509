<?php require_once ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
.stat-box-summary {
    border-radius: 8px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-box-summary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 font-weight-bold text-dark"><i class="fas fa-chart-pie text-primary mr-2"></i>Rekap Masukan &amp; Aduan Petugas</h4>
    <a class="btn btn-outline-info btn-sm" href="<?= BASE_URL ?>feedback/report">
        <i class="fas fa-chart-bar mr-1"></i> Laporan Bulanan
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" class="card card-body mb-4 shadow-sm border-0">
    <div class="form-row align-items-end">
        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
            <label class="small font-weight-bold text-muted mb-1">Tahun</label>
            <input class="form-control form-control-sm" type="number" name="year" min="2020" max="<?= (int) date('Y') + 1 ?>" value="<?= (int) ($filters['year'] ?? date('Y')) ?>" aria-label="Tahun">
        </div>
        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
            <label class="small font-weight-bold text-muted mb-1">Bulan</label>
            <select class="form-control form-control-sm" name="month" aria-label="Bulan">
                <option value="0" <?= ((int) ($filters['month'] ?? 0)) === 0 ? 'selected' : '' ?>>Semua Bulan</option>
                <?php
                $bulanNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                foreach ($bulanNames as $mNum => $mLabel): ?>
                    <option value="<?= $mNum ?>" <?= ((int) ($filters['month'] ?? 0)) === $mNum ? 'selected' : '' ?>><?= $mLabel ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
            <label class="small font-weight-bold text-muted mb-1">Jenis</label>
            <select class="form-control form-control-sm" name="jenis" aria-label="Jenis Feedback">
                <option value="">Semua Jenis</option>
                <?php foreach (['bug' => 'Bug Report', 'fitur_baru' => 'Fitur Baru', 'peningkatan' => 'Peningkatan'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($filters['jenis'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
            <label class="small font-weight-bold text-muted mb-1">Status</label>
            <select class="form-control form-control-sm" name="status" aria-label="Status Feedback">
                <option value="">Semua Status</option>
                <?php foreach (['diterima' => 'Diterima', 'dalam_proses' => 'Dalam Proses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-sm-8 mb-2 mb-md-0">
            <label class="small font-weight-bold text-muted mb-1">Pencarian</label>
            <input class="form-control form-control-sm" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari judul/deskripsi...">
        </div>
        <div class="col-md-1 col-sm-4">
            <button type="submit" class="btn btn-primary btn-sm btn-block">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
        </div>
    </div>
</form>

<!-- KPI Cards -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info stat-box-summary">
            <div class="inner">
                <h3><?= (int) ($totalStats['total'] ?? 0) ?></h3>
                <p>Total Masukan</p>
            </div>
            <div class="icon">
                <i class="fas fa-comments"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary stat-box-summary">
            <div class="inner">
                <h3><?= (int) ($totalStats['pending'] ?? 0) ?></h3>
                <p>Diterima (Menunggu)</p>
            </div>
            <div class="icon">
                <i class="fas fa-inbox"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning stat-box-summary">
            <div class="inner">
                <h3><?= (int) ($totalStats['in_progress'] ?? 0) ?></h3>
                <p>Dalam Proses</p>
            </div>
            <div class="icon">
                <i class="fas fa-spinner"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success stat-box-summary">
            <div class="inner">
                <h3><?= (int) ($totalStats['completed'] ?? 0) ?></h3>
                <p>Selesai</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Rekap per Petugas Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-users text-info mr-2"></i>Rekap Masukan per Petugas</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Petugas</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Diterima</th>
                    <th class="text-center">Diproses</th>
                    <th class="text-center">Selesai</th>
                    <th class="text-center">Ditolak</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rekapPerPetugas as $row): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) $row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <br><small class="text-muted"><i class="fas fa-user mr-1"></i><?= htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                    <td class="text-center font-weight-bold"><?= (int) $row['total'] ?></td>
                    <td class="text-center"><span class="badge badge-secondary"><?= (int) $row['pending'] ?></span></td>
                    <td class="text-center"><span class="badge badge-warning"><?= (int) $row['in_progress'] ?></span></td>
                    <td class="text-center"><span class="badge badge-success"><?= (int) $row['completed'] ?></span></td>
                    <td class="text-center"><span class="badge badge-danger"><?= (int) $row['rejected'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rekapPerPetugas)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-info-circle mr-1"></i> Tidak ada data rekap petugas untuk periode ini.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Daftar Aduan Masuk Table -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-list text-primary mr-2"></i>Daftar Seluruh Aduan Masuk</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width: 140px;">Waktu</th>
                    <th>Petugas</th>
                    <th>Jenis</th>
                    <th>Judul</th>
                    <th>Status</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftarFeedback as $item): ?>
                <tr>
                    <td class="small text-muted"><?= htmlspecialchars(date('d-m-Y H:i', strtotime((string) $item['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($item['user_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge badge-jenis-<?= htmlspecialchars((string) $item['jenis_feedback'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($jenisLabels[$item['jenis_feedback']] ?? $item['jenis_feedback']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars((string) $item['judul'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-status-<?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($statusLabels[$item['status']] ?? $item['status']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>feedback/detail/<?= (int) $item['id'] ?>">
                            <i class="fas fa-eye mr-1"></i> Detail
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($daftarFeedback)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x d-block mb-2 text-muted"></i> Belum ada aduan masuk.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="card-footer bg-white border-top">
        <nav aria-label="Navigasi halaman aduan">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= (int) $pagination['totalPages']; $i++): ?>
                <li class="page-item <?= $i === (int) $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge(array_filter($filters, fn($v) => $v !== '' && $v !== null), ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center text-muted small mt-2">
    <span><i class="fas fa-sync-alt mr-1"></i> Data diperbarui: <?= htmlspecialchars((string) ($generatedAt ?? date('d-m-Y H:i:s')), ENT_QUOTES, 'UTF-8') ?> WIB</span>
</div>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
