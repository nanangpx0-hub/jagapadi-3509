<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
.stat-card { border-radius: 10px; border: none; transition: transform 0.2s, box-shadow 0.2s; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.stat-card .count { font-size: 1.5rem; font-weight: bold; }
.filter-bar { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
.usulan-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; }
.badge-st-draf { background: #6c757d; color: #fff; }
.badge-st-pending { background: #ffc107; color: #333; }
.badge-st-revision { background: #fd7e14; color: #fff; }
.badge-st-approved { background: #28a745; color: #fff; }
.badge-st-merged { background: #17a2b8; color: #fff; }
.badge-st-rejected { background: #dc3545; color: #fff; }
.table-usulan th { background: #28a745; color: #fff; font-size: 0.85rem; white-space: nowrap; vertical-align: middle; }
.table-usulan td { font-size: 0.88rem; vertical-align: middle; }
.master-chip { background: #e7f5ec; color: #1d7a46; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; display: inline-block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mobile-usulan-card { border-left: 4px solid #28a745; }
.age-days { font-size: 0.72rem; color: #856404; background: #fff3cd; border-radius: 8px; padding: 1px 6px; display: inline-block; }
.age-days.old { color: #721c24; background: #f8d7da; }
.form-filter-label { font-size: 0.78rem; color: #6c757d; margin-bottom: 0.15rem; display: block; }
.revision-note-cell { background: #fff8e6; }
/* Tampilan statis untuk seluruh role dan seluruh filter status. */
*,
*::before,
*::after,
.stat-card,
.stat-card:hover,
.card,
.card:hover,
.btn,
.btn:hover,
.btn:focus,
.btn:active,
.table-usulan tbody tr,
.table-usulan tbody tr:hover,
.nav-link,
.badge,
.alert,
.modal,
.modal-dialog,
.modal-backdrop,
.filter-bar,
.form-control,
.custom-select {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}

.stat-card:hover,
.card:hover,
.btn:hover,
.btn:focus,
.btn:active,
.table-usulan tbody tr:hover {
    box-shadow: none !important;
}
</style>

<div class="row mb-2">
    <div class="col-12 d-flex flex-wrap align-items-center gap-2">
        <?php if (!$is_admin): ?>
            <a href="<?= BASE_URL ?>usulan-opt/create" class="btn btn-success mr-2">
                <i class="fas fa-plus"></i> Buat Usulan OPT
            </a>
            <a href="<?= BASE_URL ?>laporan/create" class="btn btn-outline-primary">
                <i class="fas fa-bug"></i> Buat melalui Laporan Hama
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-success ml-2" data-toggle="modal" data-target="#importExcelModal">
            <i class="fas fa-file-import"></i> Impor Excel
        </button>
        <?php
        $exportParams = $_GET;
        unset($exportParams['page'], $exportParams['per_page']);
        $exportUrl = BASE_URL . 'usulan-opt/export' . ($exportParams ? '?' . http_build_query($exportParams) : '');
        ?>
        <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-primary ml-2">
            <i class="fas fa-file-excel"></i> Ekspor Excel
        </a>
        <?php if ($is_admin): ?>
        <a href="<?= BASE_URL ?>recycle-bin" class="btn btn-outline-secondary ml-2">
            <i class="fas fa-recycle"></i> Recycle Bin
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (is_array($import_summary)): ?>
<div class="card border-<?= (int) $import_summary['failed'] === 0 ? 'success' : 'warning' ?> mb-3">
    <div class="card-header">
        <strong><i class="fas fa-clipboard-check"></i> Ringkasan Impor Excel</strong>
    </div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <div class="col"><strong><?= (int) $import_summary['total'] ?></strong><br><small>Total baris</small></div>
            <div class="col text-success"><strong><?= (int) $import_summary['success'] ?></strong><br><small>Berhasil</small></div>
            <?php if (array_key_exists('skipped', $import_summary)): ?><div class="col text-warning"><strong><?= (int) $import_summary['skipped'] ?></strong><br><small>Dilewati</small></div><?php endif; ?>
            <div class="col text-danger"><strong><?= (int) $import_summary['failed'] ?></strong><br><small>Gagal</small></div>
        </div>
        <?php if (array_key_exists('other_report_success', $import_summary)): ?>
            <div class="alert alert-info py-2 mb-3">
                <strong><?= (int) ($import_summary['opt_success'] ?? 0) ?></strong> disimpan sebagai draf Usulan OPT dan
                <strong><?= (int) ($import_summary['other_report_success'] ?? 0) ?></strong> dialihkan sebagai draf Laporan Lainnya.
            </div>
        <?php endif; ?>
        <?php if (!empty($import_summary['details'])): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th width="100">Baris Excel</th><th>Alasan gagal</th></tr></thead>
                    <tbody>
                    <?php foreach ($import_summary['details'] as $failure): ?>
                        <tr>
                            <td><?= (int) $failure['row'] ?></td>
                            <td><?= htmlspecialchars(implode('; ', $failure['errors']), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($import_summary['truncated'])): ?>
                <small class="text-muted">Hanya 100 kegagalan pertama yang ditampilkan.</small>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="importExcelModal" tabindex="-1" role="dialog" aria-labelledby="importExcelTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>usulan-opt/import" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="importExcelTitle"><i class="fas fa-file-import"></i> Impor Usulan OPT</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="excel_file"><strong>File Excel</strong></label>
                        <input type="file" id="excel_file" name="excel_file" class="form-control-file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">Format .xlsx atau .xls, maksimal 10 MB dan 5.000 baris. Data valid disimpan sebagai Draf milik pengguna yang sedang login.</small>
                    </div>
                    <a href="<?= BASE_URL ?>usulan-opt/template" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download"></i> Unduh Template
                    </a>
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        Header dan urutan kolom wajib mengikuti template. Baris tidak valid dilewati dan ditampilkan pada ringkasan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Mulai Impor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-6 col-md-4 col-xl mb-3">
        <div class="card stat-card bg-primary text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center py-3">
                <div><div class="count"><?= (int) ($stats['total'] ?? 0) ?></div><div>Total Usulan</div></div>
                <i class="fas fa-inbox fa-2x opacity-75"></i>
            </div>
        </div>
    </div>
    <?php
    $statusCards = [
        UsulanOpt::STATUS_DRAFT => ['bg-secondary', 'text-white', 'fa-pencil-ruler'],
        UsulanOpt::STATUS_PENDING => ['bg-warning', 'text-dark', 'fa-hourglass-half'],
        UsulanOpt::STATUS_REVISION => ['bg-orange', 'text-white', 'fa-exclamation-circle'],
        UsulanOpt::STATUS_APPROVED => ['bg-success', 'text-white', 'fa-check-circle'],
        UsulanOpt::STATUS_MERGED => ['bg-info', 'text-white', 'fa-code-branch'],
        UsulanOpt::STATUS_REJECTED => ['bg-danger', 'text-white', 'fa-ban'],
    ];
    foreach ($statusCards as $statusValue => [$cardBg, $cardText, $cardIcon]): ?>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card stat-card <?= $cardBg ?> <?= $cardText ?> h-100">
                <div class="card-body d-flex justify-content-between align-items-center py-3">
                    <div><div class="count"><?= (int) ($stats['by_status'][$statusValue] ?? 0) ?></div><div><?= htmlspecialchars($statusValue) ?></div></div>
                    <i class="fas <?= $cardIcon ?> fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <h3 class="card-title"><i class="fas fa-lightbulb"></i> <?= $is_admin ? 'Antrean Review Usulan OPT' : 'Riwayat Usulan OPT Saya' ?></h3>
                <ul class="nav nav-pills nav-pills-sm mt-2 mb-2" role="tablist">
                    <?php
                    $tabs = ['' => 'Semua'] + array_combine(UsulanOpt::STATUSES, UsulanOpt::STATUSES);
                    $tabQuery = $_GET;
                    unset($tabQuery['status'], $tabQuery['page']);
                    $activeStatus = $filters['status'] ?? '';
                    foreach ($tabs as $statusValue => $statusLabel):
                        $tabUrl = BASE_URL . 'usulan-opt';
                        $tabParams = array_merge($tabQuery, $statusValue !== '' ? ['status' => $statusValue] : []);
                        if (!empty($tabParams)) {
                            $tabUrl .= '?' . http_build_query($tabParams);
                        }
                        $isActive = $activeStatus === $statusValue;
                        $tabCount = $statusValue === '' ? ($stats['total'] ?? 0) : ($stats['by_status'][$statusValue] ?? 0);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= $tabUrl ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                            <span class="badge badge-light ml-1"><?= (int) $tabCount ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>usulan-opt" class="filter-bar" id="usulanOptFilterForm">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-6 col-12">
                            <label class="form-filter-label" for="filter_q">Cari nama/wilayah/pengusul</label>
                            <input type="text" id="filter_q" name="q" class="form-control form-control-sm"
                                   placeholder="Nama, wilayah, pengusul..." maxlength="100"
                                   value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
                        </div>
                        <div class="col-lg-2 col-md-6 col-6">
                            <label class="form-filter-label" for="filter_jenis">Jenis</label>
                            <select id="filter_jenis" name="jenis" class="form-control form-control-sm">
                                <option value="">Semua</option>
                                <?php foreach (MasterOptService::JENIS as $jenisOpt): ?>
                                    <option value="<?= htmlspecialchars($jenisOpt) ?>" <?= ($filters['jenis'] ?? '') === $jenisOpt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($jenisOpt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="form-filter-label" for="filter_date_from">Dari tanggal</label>
                            <input type="date" id="filter_date_from" name="date_from" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="form-filter-label" for="filter_date_to">Sampai tanggal</label>
                            <input type="date" id="filter_date_to" name="date_to" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                        </div>
                        <div class="col-lg-1 col-md-4 col-6">
                            <label class="form-filter-label" for="filter_per_page">Per hal.</label>
                            <select id="filter_per_page" name="per_page" class="form-control form-control-sm">
                                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                                    <option value="<?= $pp ?>" <?= (int) ($pagination['per_page'] ?? 20) === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12 col-6">
                            <button type="submit" class="btn btn-primary btn-sm w-100" aria-label="Terapkan filter usulan">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                </form>

                <?php if ($is_admin && !empty($proposals)): ?>
                <form method="POST" action="<?= BASE_URL ?>usulan-opt/bulk-delete" id="bulk_delete_form" class="mb-2">
                    <?= Security::getCsrfField() ?>
                    <div id="bulk_ids_holder"></div>
                    <div class="d-flex flex-wrap align-items-center gap-2 border rounded p-2 bg-light">
                        <span class="text-muted small" id="bulk_selected_label" aria-live="polite">0 usulan dipilih</span>
                        <button type="button" class="btn btn-success btn-sm ml-auto" id="bulk_approve_selected_btn" disabled>
                            <i class="fas fa-check-double"></i> Setujui Terpilih
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="bulk_approve_all_btn">
                            <i class="fas fa-check-circle"></i> Setujui Semua Hasil Filter
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulk_delete_btn" disabled
                                aria-label="Hapus usulan terpilih secara massal">
                            <i class="fas fa-trash-alt"></i> Hapus Terpilih
                        </button>
                    </div>
                </form>
                <form method="POST" action="<?= BASE_URL ?>usulan-opt/bulk-approve" id="bulk_approve_form" class="d-none">
                    <?= Security::getCsrfField() ?>
                    <input type="hidden" name="approve_all" id="bulk_approve_all" value="0">
                    <input type="hidden" name="filter_q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
                    <input type="hidden" name="filter_jenis" value="<?= htmlspecialchars($filters['jenis'] ?? '') ?>">
                    <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                    <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                    <div id="bulk_approve_ids_holder"></div>
                </form>
                <?php endif; ?>

                <?php if (empty($proposals)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                        <p class="mb-1"><strong>Belum ada usulan OPT.</strong></p>
                        <?php if (!$is_admin): ?>
                            <p class="text-muted">Temukan organisme pengganggu baru di lapangan? Klik
                                <strong>"Buat Usulan OPT"</strong> di atas untuk mengajukan identifikasi ke Admin,
                                atau lampirkan langsung melalui <strong>Laporan Hama</strong>.</p>
                            <a href="<?= BASE_URL ?>usulan-opt/create" class="btn btn-success"><i class="fas fa-plus"></i> Buat Usulan OPT</a>
                        <?php else: ?>
                            <p class="text-muted">Usulan dari Petugas akan muncul di sini untuk direview.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive d-none d-lg-block">
                    <table class="table table-bordered table-hover table-usulan">
                        <thead>
                            <tr>
                                <?php if ($is_admin): ?>
                                <th width="36" class="text-center" title="Pilih semua usulan pada halaman ini">
                                    <input type="checkbox" id="bulk_select_all" aria-label="Pilih semua usulan pada halaman ini">
                                </th>
                                <?php endif; ?>
                                <th width="60">Foto</th>
                                <th>Nama Usulan</th>
                                <th width="80">Jenis</th>
                                <th>Komoditas / Wilayah</th>
                                <th>Pengusul</th>
                                <th width="110">Tanggal</th>
                                <th width="130">Status</th>
                                <th width="150">Review</th>
                                <th width="250" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proposals as $proposal):
                                $name = $proposal['nama_nasional'] ?: $proposal['nama_lokal'];
                                $stClass = [
                                    UsulanOpt::STATUS_DRAFT => 'badge-st-draf',
                                    UsulanOpt::STATUS_PENDING => 'badge-st-pending',
                                    UsulanOpt::STATUS_REVISION => 'badge-st-revision',
                                    UsulanOpt::STATUS_APPROVED => 'badge-st-approved',
                                    UsulanOpt::STATUS_MERGED => 'badge-st-merged',
                                    UsulanOpt::STATUS_REJECTED => 'badge-st-rejected',
                                ][$proposal['status']] ?? 'badge-secondary';
                                $ageBase = $proposal['submitted_at'] ?: $proposal['created_at'];
                                $ageDays = (int) floor((time() - strtotime((string) $ageBase)) / 86400);
                            ?>
                            <tr class="<?= $proposal['status'] === UsulanOpt::STATUS_REVISION && !$is_admin ? 'revision-note-cell' : '' ?>">
                                <?php if ($is_admin): ?>
                                <td class="text-center">
                                    <?php if (!in_array($proposal['status'], UsulanOptService::BULK_DELETE_PROTECTED, true)): ?>
                                        <input type="checkbox" class="js-bulk-id" value="<?= (int) $proposal['id'] ?>"
                                               data-name="<?= htmlspecialchars($name) ?>"
                                               aria-label="Pilih usulan <?= htmlspecialchars($name) ?> untuk dihapus">
                                    <?php else: ?>
                                        <span class="text-muted" title="Status Disetujui/Digabungkan terkait master OPT — tidak dapat dihapus massal">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ((int) ($photo_counts[(int) $proposal['id']] ?? 0) > 0 || !empty($proposal['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($this->photoUrl((string) $proposal['foto_url'])) ?>"
                                             onerror="this.onerror=null;this.src='<?= BASE_URL ?>public/img/no-image.png'"
                                             class="usulan-thumbnail" alt="Foto usulan <?= htmlspecialchars($name) ?>">
                                        <div><small class="text-muted"><?= (int) ($photo_counts[(int) $proposal['id']] ?? 0) ?> foto</small></div>
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($name) ?></strong>
                                    <?php if (!empty($proposal['nama_lokal']) && $proposal['nama_lokal'] !== $name): ?>
                                        <div><small class="text-muted">Lokal: <?= htmlspecialchars($proposal['nama_lokal']) ?></small></div>
                                    <?php endif; ?>
                                    <?php if ($proposal['status'] === UsulanOpt::STATUS_REVISION && !empty($proposal['catatan_review'])): ?>
                                        <div class="small text-warning text-truncate" style="max-width:220px" title="<?= htmlspecialchars($proposal['catatan_review']) ?>">
                                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars(mb_substr((string) $proposal['catatan_review'], 0, 60)) ?>...
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($proposal['master_nama_opt'])): ?>
                                        <div class="mt-1">
                                            <span class="master-chip" title="Master tujuan: <?= htmlspecialchars($proposal['master_nama_opt']) ?>">
                                                <i class="fas fa-bug"></i> <?= htmlspecialchars(($proposal['master_kode_opt'] ? $proposal['master_kode_opt'] . ' · ' : '') . $proposal['master_nama_opt']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $proposal['jenis'] === 'hama' ? 'danger' : ($proposal['jenis'] === 'penyakit' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars(ucfirst($proposal['jenis'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($proposal['komoditas'] ?: '-') ?></div>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width:200px" title="<?= htmlspecialchars($proposal['wilayah'] ?: '') ?>">
                                        <?= htmlspecialchars($proposal['wilayah'] ?: '-') ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($proposal['nama_pengusul']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['created_at']))) ?></small>
                                    <?php if (in_array($proposal['status'], [UsulanOpt::STATUS_PENDING, UsulanOpt::STATUS_REVISION], true)): ?>
                                        <br><span class="age-days <?= $ageDays >= 7 ? 'old' : '' ?>" title="Usia sejak dikirim">
                                            <?= $ageDays === 0 ? 'hari ini' : $ageDays . ' hari'
                                        ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $stClass ?>"><?= htmlspecialchars($proposal['status']) ?></span></td>
                                <td>
                                    <?php if (!empty($proposal['reviewed_at'])): ?>
                                        <small>
                                            <strong><?= htmlspecialchars($proposal['reviewer_nama'] ?? '-') ?></strong><br>
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['reviewed_at']))) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-wrap justify-content-center gap-1">
                                        <?php if (!$is_admin && in_array($proposal['status'], UsulanOpt::OWNER_EDITABLE, true)): ?>
                                            <a href="<?= BASE_URL ?>usulan-opt/edit/<?= (int) $proposal['id'] ?>" class="btn btn-warning btn-sm" title="Edit usulan">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($proposal['status'] === UsulanOpt::STATUS_DRAFT): ?>
                                                <form method="POST" action="<?= BASE_URL ?>usulan-opt/submit" class="d-inline js-owner-action">
                                                    <?= Security::getCsrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Kirim untuk review">
                                                        <i class="fas fa-paper-plane"></i> Kirim
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?= BASE_URL ?>usulan-opt/delete-draft" class="d-inline js-confirm-delete">
                                                    <?= Security::getCsrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus draf">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= BASE_URL ?>usulan-opt/resubmit" class="d-inline js-owner-action">
                                                    <?= Security::getCsrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Kirim ulang setelah perbaikan">
                                                        <i class="fas fa-redo"></i> Resubmit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif ($is_admin && $proposal['status'] === UsulanOpt::STATUS_PENDING): ?>
                                            <a href="<?= BASE_URL ?>usulan-opt/finalize/<?= (int) $proposal['id'] ?>" class="btn btn-success btn-sm" title="Setujui sebagai master baru">
                                                <i class="fas fa-check"></i> Setujui
                                            </a>
                                            <button type="button" class="btn btn-info btn-sm btn-open-merge"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    data-jenis="<?= htmlspecialchars($proposal['jenis']) ?>" aria-label="Gabungkan usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-code-branch"></i> Gabungkan
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm btn-open-revision"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    aria-label="Minta perbaikan usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-exclamation-circle"></i> Minta Perbaikan
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm btn-open-reject"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    aria-label="Tolak permanen usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-ban"></i> Tolak Permanen
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>usulan-opt/detail/<?= (int) $proposal['id'] ?>" class="btn btn-secondary btn-sm" title="Detail usulan">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-lg-none">
                    <?php foreach ($proposals as $proposal):
                        $name = $proposal['nama_nasional'] ?: $proposal['nama_lokal'];
                        $stClass = [
                            UsulanOpt::STATUS_DRAFT => 'badge-st-draf',
                            UsulanOpt::STATUS_PENDING => 'badge-st-pending',
                            UsulanOpt::STATUS_REVISION => 'badge-st-revision',
                            UsulanOpt::STATUS_APPROVED => 'badge-st-approved',
                            UsulanOpt::STATUS_MERGED => 'badge-st-merged',
                            UsulanOpt::STATUS_REJECTED => 'badge-st-rejected',
                        ][$proposal['status']] ?? 'badge-secondary';
                    ?>
                    <div class="card mobile-usulan-card mb-3 <?= $proposal['status'] === UsulanOpt::STATUS_REVISION && !$is_admin ? 'revision-note-cell' : '' ?>">
                        <div class="card-body p-3">
                            <?php if ($proposal['status'] === UsulanOpt::STATUS_REVISION && !empty($proposal['catatan_review']) && !$is_admin): ?>
                                <div class="alert alert-warning py-2 px-3 small mb-2">
                                    <strong>Catatan Admin:</strong> <?= nl2br(htmlspecialchars($proposal['catatan_review'])) ?>
                                </div>
                            <?php endif; ?>
                            <div class="media">
                                <?php if ($is_admin && !in_array($proposal['status'], UsulanOptService::BULK_DELETE_PROTECTED, true)): ?>
                                    <div class="align-self-start mr-2 pt-1">
                                        <input type="checkbox" class="js-bulk-id" value="<?= (int) $proposal['id'] ?>"
                                               data-name="<?= htmlspecialchars($name) ?>"
                                               aria-label="Pilih usulan <?= htmlspecialchars($name) ?> untuk dihapus">
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($proposal['foto_url'])): ?>
                                    <img src="<?= htmlspecialchars($this->photoUrl((string) $proposal['foto_url'])) ?>"
                                         class="usulan-thumbnail mr-3" alt="Foto usulan <?= htmlspecialchars($name) ?>">
                                <?php endif; ?>
                                <div class="media-body">
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars($name) ?>
                                        <span class="badge <?= $stClass ?> float-right"><?= htmlspecialchars($proposal['status']) ?></span>
                                    </h6>
                                    <div class="text-muted small">
                                        <span class="badge badge-<?= $proposal['jenis'] === 'hama' ? 'danger' : ($proposal['jenis'] === 'penyakit' ? 'warning' : 'info') ?>">
                                            <?= htmlspecialchars(ucfirst($proposal['jenis'])) ?>
                                        </span>
                                        Komoditas: <?= htmlspecialchars($proposal['komoditas'] ?: '-') ?><br>
                                        Wilayah: <?= htmlspecialchars($proposal['wilayah'] ?: '-') ?><br>
                                        Pengusul: <?= htmlspecialchars($proposal['nama_pengusul']) ?> ·
                                        <?= htmlspecialchars(date('d/m/Y', strtotime((string) $proposal['created_at']))) ?>
                                        <?php if (!empty($proposal['master_nama_opt'])): ?>
                                            <br>Master: <?= htmlspecialchars($proposal['master_nama_opt']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($proposal['reviewer_nama'])): ?>
                                            <br>Reviewer: <?= htmlspecialchars($proposal['reviewer_nama']) ?>
                                            pada <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $proposal['reviewed_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <a href="<?= BASE_URL ?>usulan-opt/detail/<?= (int) $proposal['id'] ?>" class="btn btn-secondary btn-sm" aria-label="Detail usulan <?= htmlspecialchars($name) ?>">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <?php if (!$is_admin && in_array($proposal['status'], UsulanOpt::OWNER_EDITABLE, true)): ?>
                                            <a href="<?= BASE_URL ?>usulan-opt/edit/<?= (int) $proposal['id'] ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" action="<?= BASE_URL ?>usulan-opt/<?= $proposal['status'] === UsulanOpt::STATUS_DRAFT ? 'submit' : 'resubmit' ?>" class="d-inline js-owner-action">
                                                <?= Security::getCsrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas <?= $proposal['status'] === UsulanOpt::STATUS_DRAFT ? 'fa-paper-plane' : 'fa-redo' ?>"></i>
                                                    <?= $proposal['status'] === UsulanOpt::STATUS_DRAFT ? 'Kirim' : 'Resubmit' ?>
                                                </button>
                                            </form>
                                            <?php if ($proposal['status'] === UsulanOpt::STATUS_DRAFT): ?>
                                                <form method="POST" action="<?= BASE_URL ?>usulan-opt/delete-draft" class="d-inline js-confirm-delete">
                                                    <?= Security::getCsrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $proposal['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Hapus draf">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif ($is_admin && $proposal['status'] === UsulanOpt::STATUS_PENDING): ?>
                                            <a href="<?= BASE_URL ?>usulan-opt/finalize/<?= (int) $proposal['id'] ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Setujui
                                            </a>
                                            <button type="button" class="btn btn-info btn-sm btn-open-merge"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    data-jenis="<?= htmlspecialchars($proposal['jenis']) ?>" aria-label="Gabungkan usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-code-branch"></i> Gabungkan
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm btn-open-revision"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    aria-label="Minta perbaikan usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-exclamation-circle"></i> Minta Perbaikan
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm btn-open-reject"
                                                    data-id="<?= (int) $proposal['id'] ?>" data-name="<?= htmlspecialchars($name) ?>"
                                                    aria-label="Tolak permanen usulan <?= htmlspecialchars($name) ?>">
                                                <i class="fas fa-ban"></i> Tolak
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                        <div class="pagination-info mb-2">
                            Menampilkan <?= (int) $pagination['from'] ?> - <?= (int) $pagination['to'] ?> dari <?= (int) $pagination['total'] ?> usulan
                        </div>
                        <nav aria-label="Navigasi halaman usulan OPT">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $currentPage = (int) $pagination['current_page'];
                                $lastPage = (int) $pagination['last_page'];
                                $queryParams = $_GET;
                                unset($queryParams['page']);
                                $pageBaseUrl = BASE_URL . 'usulan-opt?' . (http_build_query($queryParams) ? http_build_query($queryParams) . '&' : '');
                                ?>
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $pageBaseUrl ?>page=<?= max(1, $currentPage - 1) ?>" aria-label="Halaman sebelumnya">&laquo;</a>
                                </li>
                                <?php
                                $start = max(1, $currentPage - 2);
                                $end = min($lastPage, $currentPage + 2);
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $pageBaseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $pageBaseUrl ?>page=<?= min($lastPage, $currentPage + 1) ?>" aria-label="Halaman berikutnya">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var perPage = document.getElementById('filter_per_page');
        var filterForm = document.getElementById('usulanOptFilterForm');
        if (!perPage || !filterForm || perPage.dataset.autoSubmitBound === '1') {
            return;
        }
        perPage.dataset.autoSubmitBound = '1';
        perPage.addEventListener('change', function () {
            filterForm.setAttribute('aria-busy', 'true');
            filterForm.submit();
        });
    });
})();
</script>

<?php if ($is_admin): ?>
<div class="modal fade" id="mergeModal" tabindex="-1" role="dialog" aria-labelledby="mergeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>usulan-opt/review" id="merge_form">
                <?= Security::getCsrfField() ?>
                <input type="hidden" name="action" value="merge">
                <input type="hidden" name="id" id="merge_proposal_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="mergeModalTitle">Gabungkan ke Master Aktif</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup dialog"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Usulan: <strong id="merge_proposal_name">-</strong></p>
                    <div class="form-group">
                        <label for="merge_search">Cari master OPT aktif (kode/nama nasional/lokal/ilmiah)</label>
                        <input type="text" id="merge_search" name="merge_search_display" class="form-control"
                               placeholder="Ketik minimal 1 karakter..." autocomplete="off" aria-describedby="merge_help">
                        <small id="merge_help" class="form-text text-muted">Hanya master aktif dengan jenis yang sama yang dicari.</small>
                    </div>
                    <div id="merge_results" class="list-group mb-2"></div>
                    <div class="alert alert-info d-none" id="merge_selected_box">
                        Master terpilih: <strong id="merge_selected_name"></strong>
                    </div>
                    <input type="hidden" name="master_opt_id" id="merge_master_id" value="">
                    <div class="form-group">
                        <label for="merge_catatan">Catatan review (opsional)</label>
                        <textarea id="merge_catatan" name="catatan_review" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info" id="merge_submit" disabled>Ya, Gabungkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="revisionModal" tabindex="-1" role="dialog" aria-labelledby="revisionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>usulan-opt/request-revision" id="revision_form">
                <?= Security::getCsrfField() ?>
                <input type="hidden" name="id" id="revision_proposal_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="revisionModalTitle">Minta Perbaikan Usulan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup dialog"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Minta perbaikan untuk: <strong id="revision_proposal_name">-</strong>?</p>
                    <p class="small text-muted">Petugas akan menerima notifikasi dan dapat memperbaiki lalu mengirim ulang usulan.</p>
                    <div class="form-group">
                        <label for="revision_note">Catatan perbaikan (minimal 10 karakter)</label>
                        <textarea id="revision_note" name="catatan_perbaikan" class="form-control" rows="3"
                                  minlength="10" required aria-describedby="revision_counter"></textarea>
                        <small id="revision_counter" class="form-text text-muted">0 / minimal 10 karakter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="revision_submit">Ya, Minta Perbaikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>usulan-opt/review" id="reject_form">
                <?= Security::getCsrfField() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="reject_proposal_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalTitle">Tolak Permanen Usulan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup dialog"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Tolak <strong>permanen</strong> usulan: <strong id="reject_proposal_name">-</strong>? Petugas tidak dapat mengirim ulang usulan ini.</p>
                    <div class="form-group">
                        <label for="reject_alasan">Alasan penolakan (minimal 10 karakter)</label>
                        <textarea id="reject_alasan" name="alasan" class="form-control" rows="3"
                                  minlength="10" required aria-describedby="reject_counter"></textarea>
                        <small id="reject_counter" class="form-text text-muted">0 / minimal 10 karakter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="reject_submit">Ya, Tolak Permanen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkDeleteModal" tabindex="-1" role="dialog" aria-labelledby="bulkDeleteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteModalTitle">Hapus Usulan Terpilih</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup dialog"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Pindahkan <strong id="bulk_modal_count">0</strong> usulan yang dipilih ke recycle bin?</p>
                <p class="small text-muted mb-0">
                    Usulan berstatus <strong>Disetujui</strong> atau <strong>Digabungkan</strong> tidak dapat dihapus
                    karena terikat master OPT dan jejak audit laporan; baris tersebut akan dilewati secara otomatis.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="bulk_confirm_submit">Ya, Pindahkan</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    $(function () {
        var currentJenis = '';

        // ==================== Select All & Hapus Massal ====================
        var bulkForm = document.getElementById('bulk_delete_form');
        if (bulkForm) {
            var selectAll = document.getElementById('bulk_select_all');
            var bulkBtn = document.getElementById('bulk_delete_btn');
            var bulkLabel = document.getElementById('bulk_selected_label');
            var bulkHolder = document.getElementById('bulk_ids_holder');
            var bulkCount = document.getElementById('bulk_modal_count');
            var approveForm = document.getElementById('bulk_approve_form');
            var approveSelectedBtn = document.getElementById('bulk_approve_selected_btn');
            var approveAllBtn = document.getElementById('bulk_approve_all_btn');
            var approveIdsHolder = document.getElementById('bulk_approve_ids_holder');

            function allBulkBoxes() {
                return Array.prototype.slice.call(document.querySelectorAll('.js-bulk-id'));
            }

            function checkedBoxes() {
                var selected = {};
                allBulkBoxes().forEach(function (box) {
                    if (box.checked && !selected[box.value]) {
                        selected[box.value] = box;
                    }
                });
                return Object.keys(selected).map(function (id) { return selected[id]; });
            }

            function refreshBulkState() {
                var boxes = allBulkBoxes();
                var checked = checkedBoxes();
                var uniqueIds = {};
                boxes.forEach(function (box) { uniqueIds[box.value] = true; });
                var total = Object.keys(uniqueIds).length;

                bulkLabel.textContent = checked.length + ' usulan dipilih';
                bulkBtn.disabled = checked.length === 0;
                approveSelectedBtn.disabled = checked.length === 0;
                if (selectAll) {
                    selectAll.checked = total > 0 && checked.length === total;
                    selectAll.indeterminate = checked.length > 0 && checked.length < total;
                    selectAll.disabled = total === 0;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    var target = this.checked;
                    allBulkBoxes().forEach(function (box) {
                        box.checked = target;
                    });
                    refreshBulkState();
                });
            }

            document.addEventListener('change', function (event) {
                if (event.target && event.target.classList && event.target.classList.contains('js-bulk-id')) {
                    var id = event.target.value;
                    allBulkBoxes().forEach(function (box) {
                        if (box.value === id) { box.checked = event.target.checked; }
                    });
                    refreshBulkState();
                }
            });

            bulkBtn.addEventListener('click', function () {
                var checked = checkedBoxes();
                if (checked.length === 0) { return; }
                bulkCount.textContent = String(checked.length);
                $('#bulkDeleteModal').modal('show');
            });

            approveSelectedBtn.addEventListener('click', function () {
                var selected = checkedBoxes();
                if (selected.length === 0 || !window.confirm('Setujui ' + selected.length + ' usulan terpilih dan buat Kode OPT otomatis?')) { return; }
                document.getElementById('bulk_approve_all').value = '0';
                approveIdsHolder.innerHTML = '';
                selected.forEach(function (box) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = box.value;
                    approveIdsHolder.appendChild(input);
                });
                approveSelectedBtn.disabled = true;
                approveForm.submit();
            });

            approveAllBtn.addEventListener('click', function () {
                if (!window.confirm('Setujui semua usulan Menunggu Review sesuai filter saat ini dan buat Kode OPT otomatis?')) { return; }
                document.getElementById('bulk_approve_all').value = '1';
                approveIdsHolder.innerHTML = '';
                approveAllBtn.disabled = true;
                approveForm.submit();
            });

            document.getElementById('bulk_confirm_submit').addEventListener('click', function () {
                var selected = checkedBoxes();
                if (selected.length === 0) { return; }
                bulkHolder.innerHTML = '';
                selected.forEach(function (box) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = box.value;
                    bulkHolder.appendChild(input);
                });
                this.disabled = true;
                bulkForm.submit();
            });

            refreshBulkState();
        }
        // ==================== akhir Select All ====================

        $(document).on('click', '.btn-open-merge', function () {
            currentJenis = this.getAttribute('data-jenis') || '';
            document.getElementById('merge_proposal_id').value = this.getAttribute('data-id');
            document.getElementById('merge_proposal_name').textContent = this.getAttribute('data-name');
            document.getElementById('merge_search').value = '';
            document.getElementById('merge_master_id').value = '';
            document.getElementById('merge_selected_name').textContent = '';
            document.getElementById('merge_selected_box').classList.add('d-none');
            document.getElementById('merge_submit').disabled = true;
            document.getElementById('merge_results').innerHTML = '';
            $('#mergeModal').modal('show');
        });

        $(document).on('click', '.btn-open-revision', function () {
            document.getElementById('revision_proposal_id').value = this.getAttribute('data-id');
            document.getElementById('revision_proposal_name').textContent = this.getAttribute('data-name');
            document.getElementById('revision_note').value = '';
            updateCounter('revision_note', 'revision_counter', 'revision_submit');
            $('#revisionModal').modal('show');
        });

        $(document).on('click', '.btn-open-reject', function () {
            document.getElementById('reject_proposal_id').value = this.getAttribute('data-id');
            document.getElementById('reject_proposal_name').textContent = this.getAttribute('data-name');
            document.getElementById('reject_alasan').value = '';
            updateCounter('reject_alasan', 'reject_counter', 'reject_submit');
            $('#rejectModal').modal('show');
        });

        function updateCounter(areaId, counterId, submitId) {
            var area = document.getElementById(areaId);
            var len = area.value.trim().length;
            document.getElementById(counterId).textContent = len + ' / minimal 10 karakter';
            document.getElementById(submitId).disabled = len < 10;
        }

        ['revision', 'reject'].forEach(function (prefix) {
            var area = document.getElementById(prefix + '_note') || document.getElementById(prefix + '_alasan');
            if (area) {
                area.addEventListener('input', function () {
                    updateCounter(
                        prefix === 'revision' ? 'revision_note' : 'reject_alasan',
                        prefix === 'revision' ? 'revision_counter' : 'reject_counter',
                        prefix === 'revision' ? 'revision_submit' : 'reject_submit'
                    );
                });
            }
        });

        var searchTimer = null;
        $(document).on('input', '#merge_search', function () {
            clearTimeout(searchTimer);
            var q = this.value.trim();
            if (q.length < 1) {
                document.getElementById('merge_results').innerHTML = '';
                return;
            }
            searchTimer = setTimeout(function () {
                var url = '<?= BASE_URL ?>usulan-opt/search-master?q=' + encodeURIComponent(q)
                    + '&match_jenis=1&proposal_jenis=' + encodeURIComponent(currentJenis);
                fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderResults(data.items || []); })
                    .catch(function () {
                        document.getElementById('merge_results').innerHTML =
                            '<div class="list-group-item text-danger small">Gagal memuat daftar master.</div>';
                    });
            }, 300);
        });

        function renderResults(items) {
            var box = document.getElementById('merge_results');
            box.innerHTML = '';
            if (!items.length) {
                box.innerHTML = '<div class="list-group-item text-muted small">Tidak ada master aktif yang cocok.</div>';
                return;
            }
            items.forEach(function (item) {
                var a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action merge-result-item';
                var label = item.nama_opt + (item.kode_opt ? ' [' + item.kode_opt + ']' : '');
                if (item.nama_ilmiah) { label += ' (' + item.nama_ilmiah + ')'; }
                if (item.nama_lokal && item.nama_lokal !== item.nama_opt) { label += ' - lokal: ' + item.nama_lokal; }
                a.textContent = label;
                a.setAttribute('aria-label', 'Pilih master ' + label);
                a.addEventListener('click', function () { selectMaster(item.id, item.nama_opt); });
                box.appendChild(a);
            });
        }

        function selectMaster(id, name) {
            document.getElementById('merge_master_id').value = String(id);
            document.getElementById('merge_selected_name').textContent = '#' + id + ' - ' + name;
            document.getElementById('merge_selected_box').classList.remove('d-none');
            document.getElementById('merge_submit').disabled = false;
        }

        [document.getElementById('merge_form'), document.getElementById('revision_form'), document.getElementById('reject_form')]
            .forEach(function (formEl) {
                formEl.addEventListener('submit', function () {
                    var btn = formEl.querySelector('button[type="submit"]');
                    if (btn) { btn.disabled = true; }
                });
            });

        document.querySelectorAll('.js-confirm-delete form, form.js-confirm-delete').forEach(function (formEl) {
            formEl.addEventListener('submit', function (event) {
                if (!window.confirm('Hapus draf usulan ini?')) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.js-owner-action form, form.js-owner-action').forEach(function (formEl) {
            formEl.addEventListener('submit', function () {
                var btn = formEl.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; }
            });
        });
    });
})();
</script>
<?php else: ?>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-confirm-delete form, form.js-confirm-delete').forEach(function (formEl) {
            formEl.addEventListener('submit', function (event) {
                if (!window.confirm('Hapus draf usulan ini?')) {
                    event.preventDefault();
                }
            });
        });
        document.querySelectorAll('.js-owner-action form, form.js-owner-action').forEach(function (formEl) {
            formEl.addEventListener('submit', function () {
                var btn = formEl.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; }
            });
        });
    });
})();
</script>
<?php endif; ?>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
