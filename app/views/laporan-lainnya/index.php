<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<!-- Disable Fade In/Out and Hover Animations -->
<style>
/* Nonaktifkan semua animasi fade-in, fade-out, bounce, dan transisi lambat
   untuk SELURUH role (hilangkan efek timbul-tenggelam / hover-lift). */
*, *::before, *::after {
    transition: none !important;
    animation: none !important;
}

.fade, .modal.fade, .alert.fade {
    transition: none !important;
    opacity: 1 !important;
}

/* Master & Child Checkbox Styling */
#checkAll, #lainnyaSelectAll {
    cursor: pointer;
    width: 18px;
    height: 18px;
    vertical-align: middle;
    accent-color: #007bff;
}

.checkbox-item, .lainnya-row-check {
    cursor: pointer;
    width: 18px;
    height: 18px;
    vertical-align: middle;
    accent-color: #007bff;
}

/* Row Selected Highlight */
.row-selected, tr.table-warning {
    background-color: #fff3cd !important;
}

/* Tombol Aksi */
.btn-group-sm > .btn, .btn-sm {
    transition: none !important;
}

/* Netralisasi transform dari mobile-enhancements dan tema global untuk admin. */
.card,
.card:hover,
.btn,
.btn:hover,
.btn:focus,
.btn:active,
#laporanTable tbody tr,
#laporanTable tbody tr:hover,
.pagination .page-link,
.form-control,
.badge,
.alert,
#lainnyaSelectAll,
.lainnya-row-check,
#lainnyaBulkDelete,
#btnDeleteAll {
    animation: none !important;
    transition: none !important;
    transform: none !important;
}

.card:hover,
.btn:hover,
.btn:focus,
.btn:active,
#laporanTable tbody tr:hover {
    box-shadow: none !important;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Laporan Lainnya</h3>
                <div class="card-tools">
                    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                    <button type="button" id="lainnyaBulkDelete" class="btn btn-danger btn-sm mr-1" disabled style="display:none;">
                        <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedCount">0</span>)
                    </button>
                    <button type="button" id="btnDeleteAll" class="btn btn-outline-danger btn-sm mr-1" title="Hapus semua laporan lainnya ke recycle bin">
                        <i class="fas fa-trash-alt"></i> Hapus Semua
                    </button>
                    <a href="<?= BASE_URL ?>recycle-bin?module=laporan-lainnya" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-recycle"></i> Recycle Bin</a>
                    <?php endif; ?>
                    <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'operator', 'petugas'])): ?>
                    <a href="<?= BASE_URL ?>laporan-lainnya/create" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Tambah Fenomena Lainnya
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
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
                                <option value="archived" <?= ($status ?? '') === 'archived' ? 'selected' : '' ?>>Diarsipkan</option>
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
                    <div class="mt-2">
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="include_draft" value="false">
                            <input type="checkbox" class="custom-control-input" id="includeDraftCheck" name="include_draft" value="true" <?= !empty($includeDraft) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="includeDraftCheck">Sertakan laporan Draf</label>
                        </div>
                    </div>
                </form>

                <!-- Hidden Fallback Form for Bulk Operations -->
                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                <form method="POST" action="<?= BASE_URL ?>laporan-lainnya/bulk-delete" id="lainnyaBulkForm" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <div id="lainnyaBulkIds"></div>
                </form>
                <?php endif; ?>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="laporanTable">
                        <thead>
                            <tr>
                                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="lainnyaSelectAll" aria-label="Pilih semua laporan lainnya pada halaman ini" title="Pilih Semua">
                                </th>
                                <?php endif; ?>

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
                                <td colspan="<?= (($_SESSION['role'] ?? '') === 'admin') ? 10 : 9 ?>" class="text-center text-muted py-4">Tidak ada data laporan</td>
                            </tr>
                            <?php else: ?>
                            <?php $no = ($page - 1) * $perPage + 1; ?>
                            <?php foreach($laporan as $item): ?>
                            <tr>
                                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                                <td class="text-center">
                                    <input type="checkbox" class="checkbox-item lainnya-row-check" value="<?= (int) $item['id'] ?>" aria-label="Pilih laporan #<?= (int) $item['id'] ?>">
                                </td>
                                <?php endif; ?>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><code><?= $item['kode_laporan'] ? htmlspecialchars($item['kode_laporan']) : '—' ?></code></td>
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
                                        'archived' => ['dark', 'Diarsipkan'],
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
                                        <?php if(in_array($item['status'], ['draft', 'rejected'], true)): ?>
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
                <?php
                // Pagination dengan ellipsis: selalu tampilkan halaman 1 & terakhir,
                // serta jendela ±2 halaman di sekitar halaman aktif
                $windowStart = max(2, $page - 2);
                $windowEnd = min($totalPages - 1, $page + 2);
                $showLeftEllipsis = $windowStart > 2;
                $showRightEllipsis = $windowEnd < $totalPages - 1;
                $buildPageUrl = function (int $p) use ($status, $jenisId, $desaId, $dateFrom, $dateTo, $search, $includeDraft) {
                    return BASE_URL . 'laporan-lainnya?page=' . $p
                        . '&status=' . urlencode($status ?? '')
                        . '&jenis_id=' . urlencode($jenisId ?? '')
                        . '&desa_id=' . urlencode($desaId ?? '')
                        . '&date_from=' . urlencode($dateFrom ?? '')
                        . '&date_to=' . urlencode($dateTo ?? '')
                        . '&search=' . urlencode($search ?? '')
                        . '&include_draft=' . (!empty($includeDraft) ? 'true' : 'false');
                };
                ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page > 1 ? $buildPageUrl($page - 1) : '#' ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?= $page == 1 ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl(1) ?>">1</a>
                        </li>
                        <?php if($showLeftEllipsis): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php endif; ?>
                        <?php for($p = $windowStart; $p <= $windowEnd; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($p) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if($showRightEllipsis): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php endif; ?>
                        <?php if($totalPages > 1): ?>
                        <li class="page-item <?= $page == $totalPages ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildPageUrl($totalPages) ?>"><?= $totalPages ?></a>
                        </li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page < $totalPages ? $buildPageUrl($page + 1) : '#' ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
<script>
(function () {
    'use strict';

    var checkAll = document.getElementById('checkAll') || document.getElementById('lainnyaSelectAll');
    var btnBulkDelete = document.getElementById('btnBulkDelete');
    var btnDeleteAll = document.getElementById('btnDeleteAll');
    var selectedCountSpan = document.getElementById('selectedCount');
    var csrfToken = '<?= htmlspecialchars(Security::getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>';

    function getCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('#laporanTable tbody .checkbox-item, #laporanTable tbody .lainnya-row-check'));
    }

    function getSelectedCheckboxes() {
        return getCheckboxes().filter(function (cb) { return cb.checked; });
    }

    function updateBulkUI() {
        var allBoxes = getCheckboxes();
        var selectedBoxes = getSelectedCheckboxes();
        var count = selectedBoxes.length;

        // Update counter & tombol Hapus Terpilih
        if (selectedCountSpan) {
            selectedCountSpan.textContent = String(count);
        }
        if (btnBulkDelete) {
            btnBulkDelete.disabled = (count === 0);
            btnBulkDelete.style.display = (count > 0) ? 'inline-block' : 'none';
        }

        // Update Master Checkbox state
        if (checkAll) {
            checkAll.disabled = (allBoxes.length === 0);
            checkAll.checked = (allBoxes.length > 0 && count === allBoxes.length);
            checkAll.indeterminate = (count > 0 && count < allBoxes.length);
        }

        // Update row highlight
        allBoxes.forEach(function (box) {
            var row = box.closest ? box.closest('tr') : null;
            if (row) {
                if (box.checked) {
                    row.classList.add('table-warning', 'row-selected');
                } else {
                    row.classList.remove('table-warning', 'row-selected');
                }
            }
        });
    }

    // Event: Master Checkbox Change (Select All)
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            var checked = this.checked;
            getCheckboxes().forEach(function (cb) {
                cb.checked = checked;
            });
            updateBulkUI();
        });
    }

    // Event: Child Checkbox Change
    document.addEventListener('change', function (event) {
        if (event.target && (event.target.classList.contains('checkbox-item') || event.target.classList.contains('lainnya-row-check'))) {
            updateBulkUI();
        }
    });

    // Event: Tombol Hapus Terpilih (Bulk Delete by Selection)
    if (btnBulkDelete) {
        btnBulkDelete.addEventListener('click', async function () {
            var selectedBoxes = getSelectedCheckboxes();
            var ids = selectedBoxes.map(function (cb) { return cb.value; }).filter(Boolean);

            if (ids.length === 0) {
                window.alert('Pilih minimal satu laporan untuk dihapus.');
                return;
            }

            if (!window.confirm('Pindahkan ' + ids.length + ' laporan lainnya terpilih ke recycle bin?')) {
                return;
            }

            btnBulkDelete.disabled = true;
            btnBulkDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

            var formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);
            ids.forEach(function (id) {
                formData.append('ids[]', id);
            });

            try {
                var response = await fetch('<?= BASE_URL ?>laporan-lainnya/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData.toString()
                });

                var result = await response.json();
                if (response.ok && result.success) {
                    window.location.reload();
                } else {
                    window.alert(result.message || 'Gagal memindahkan laporan ke recycle bin');
                    btnBulkDelete.disabled = false;
                    btnBulkDelete.innerHTML = '<i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedCount">' + ids.length + '</span>)';
                }
            } catch (err) {
                // Fallback direct POST form submission
                var form = document.getElementById('lainnyaBulkForm');
                var holder = document.getElementById('lainnyaBulkIds');
                if (form && holder) {
                    holder.innerHTML = '';
                    ids.forEach(function (id) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        holder.appendChild(input);
                    });
                    form.submit();
                } else {
                    window.alert('Terjadi kesalahan saat memproses penghapusan.');
                    btnBulkDelete.disabled = false;
                }
            }
        });
    }

    // Event: Tombol Hapus Semua (Delete All)
    if (btnDeleteAll) {
        btnDeleteAll.addEventListener('click', async function () {
            if (!window.confirm('PERINGATAN: Apakah Anda yakin ingin memindahkan SEMUA laporan lainnya ke recycle bin?')) {
                return;
            }

            btnDeleteAll.disabled = true;
            btnDeleteAll.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

            var formData = new URLSearchParams();
            formData.append('csrf_token', csrfToken);

            try {
                var response = await fetch('<?= BASE_URL ?>laporan-lainnya/delete-all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData.toString()
                });

                var result = await response.json();
                if (response.ok && result.success) {
                    window.location.reload();
                } else {
                    window.alert(result.message || 'Gagal memindahkan semua data ke recycle bin');
                    btnDeleteAll.disabled = false;
                    btnDeleteAll.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Semua';
                }
            } catch (err) {
                // Fallback direct form submission
                var form = document.getElementById('lainnyaBulkForm');
                if (form) {
                    form.action = '<?= BASE_URL ?>laporan-lainnya/delete-all';
                    form.submit();
                } else {
                    window.alert('Terjadi kesalahan saat memproses penghapusan semua data.');
                    btnDeleteAll.disabled = false;
                }
            }
        });
    }

    // Initial sync
    updateBulkUI();
})();
</script>
<?php endif; ?>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
