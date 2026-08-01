<div class="table-responsive">
    <table class="table table-bordered table-striped table-hover" id="laporanTable">
        <thead>
            <tr>
                <?php if(($_SESSION['role'] ?? '') == 'admin'): ?>
                <th width="40">
                    <input type="checkbox" id="checkAll" title="Pilih Semua">
                </th>
                <?php endif; ?>
                <th width="50">No</th>
                <th>ID</th>
                <th>Foto</th>
                <th>Tanggal</th>
                <th>OPT</th>
                <th>Lokasi</th>
                <th>Keparahan</th>
                <th>Populasi</th>
                <th>Status</th>
                <th>Pelapor</th>
                <th>Dibuat</th>
                <th width="120">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($laporan)): ?>
            <tr>
                <td colspan="<?= ($_SESSION['role'] ?? '') == 'admin' ? '13' : '12' ?>" class="text-center">
                    <?php if(($_SESSION['role'] ?? '') === 'petugas'): ?>
                        Anda belum memiliki laporan<?= !empty($status) ? ' dengan status ' . $status : '' ?>
                    <?php else: ?>
                        Tidak ada data laporan<?= !empty($status) ? ' dengan status ' . $status : '' ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
                <?php $no = 1; foreach($laporan as $row): ?>
                <tr>
                    <?php if(($_SESSION['role'] ?? '') == 'admin'): ?>
                    <td data-label="Pilih">
                        <input type="checkbox" class="checkbox-item" value="<?= $row['id'] ?>">
                    </td>
                    <?php endif; ?>
                    <td data-label="No"><?= $no++ ?></td>
                    <td data-label="ID">
                        <span class="badge badge-light">#<?= $row['id'] ?></span>
                    </td>
                    <td data-label="Foto">
                        <?php if(!empty($row['foto_url'])): ?>
                        <div class="photo-thumbnail-container">
                            <img src="<?= BASE_URL . $row['foto_url'] ?>" 
                                 alt="Foto Laporan #<?= $row['id'] ?>"
                                 class="photo-thumbnail"
                                 data-full-image="<?= BASE_URL . $row['foto_url'] ?>"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.classList.add('no-image'); this.alt='Foto tidak ditemukan'; this.style.display='none'; this.parentElement.innerHTML='<div class=\'photo-thumbnail no-image\' title=\'Foto tidak ditemukan\'><i class=\'fas fa-image\'></i></div>';">
                        </div>
                        <?php else: ?>
                        <div class="photo-thumbnail-container">
                            <div class="photo-thumbnail no-image" title="Tidak ada foto">
                                <i class="fas fa-image"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tanggal"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td data-label="OPT">
                        <strong><?= htmlspecialchars($row['nama_opt'] ?? 'N/A') ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($row['jenis'] ?? '-') ?></small>
                    </td>
                    <td data-label="Lokasi">
                        <div><strong>Kab. <?= htmlspecialchars($row['kabupaten'] ?? 'Jember') ?></strong></div>
                        <div>Kec. <?= htmlspecialchars($row['kecamatan'] ?? '-') ?></div>
                        <div>Desa <?= htmlspecialchars($row['desa'] ?? '-') ?></div>
                        <div class="text-muted small"><?= htmlspecialchars(substr($row['alamat_lengkap'] ?? ($row['lokasi'] ?? '-'), 0, 30)) ?><?= strlen($row['alamat_lengkap'] ?? ($row['lokasi'] ?? '')) > 30 ? '...' : '' ?></div>
                    </td>
                    <td data-label="Keparahan">
                        <span class="badge badge-<?= 
                            $row['tingkat_keparahan'] == 'Berat' ? 'danger' : 
                            ($row['tingkat_keparahan'] == 'Sedang' ? 'warning' : 'info') 
                        ?>">
                            <?= $row['tingkat_keparahan'] ?>
                        </span>
                    </td>
                    <td data-label="Populasi">
                        <?= $row['populasi'] ?? 0 ?>
                        <?php if(isset($row['etl_acuan']) && $row['etl_acuan'] > 0 && ($row['populasi'] ?? 0) > $row['etl_acuan']): ?>
                        <i class="fas fa-exclamation-triangle text-danger" title="Melampaui ETL"></i>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <?php
                        $isActiveReport = in_array($row['status'], ['Submitted', 'Diverifikasi']);
                        $statusLabel = $isActiveReport ? 'Aktif' : $row['status'];
                        $statusClass = $isActiveReport ? 'success' : ($row['status'] == 'Diarsipkan' ? 'dark' : ($row['status'] == 'Ditolak' ? 'danger' : 'secondary'));
                        $statusIcon = $isActiveReport ? 'check-circle' : ($row['status'] == 'Diarsipkan' ? 'archive' : ($row['status'] == 'Ditolak' ? 'exclamation-circle' : 'file'));
                        $statusTitle = $isActiveReport ? 'Laporan aktif/masuk' : ($row['status'] == 'Diarsipkan' ? 'Laporan diarsipkan' : ($row['status'] == 'Ditolak' ? 'Status lama: ditolak' : 'Draf belum resmi'));
                        ?>
                        <span class="badge badge-<?= 
                            $statusClass
                        ?>" title="<?= $statusTitle ?>">
                            <i class="fas fa-<?= $statusIcon ?>"></i>
                            <?= $statusLabel ?>
                        </span>
                        
                        <?php if($row['status'] === 'Ditolak' && !empty($row['catatan_verifikasi'])): ?>
                        <br>
                        <small class="text-danger">
                            <i class="fas fa-comment"></i> 
                            <strong>Alasan:</strong> <?= htmlspecialchars(substr($row['catatan_verifikasi'], 0, 30) ?? '') ?><?= strlen($row['catatan_verifikasi']) > 30 ? '...' : '' ?>
                        </small>
                        <?php endif; ?>
                        
                        <?php if($row['status'] === 'Ditolak' && !empty($row['verified_at'])): ?>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> 
                            <?= date('d/m H:i', strtotime($row['verified_at'])) ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td data-label="Pelapor">
                        <div><strong><?= htmlspecialchars(substr($row['pelapor_nama'] ?? '-', 0, 15)) ?><?= strlen($row['pelapor_nama'] ?? '') > 15 ? '...' : '' ?></strong></div>
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($row['pelapor_username'] ?? '-') ?>
                        </small>
                        <br>
                        <span class="badge badge-<?= 
                            ($row['pelapor_role'] ?? '') == 'admin' ? 'danger' : 
                            (($row['pelapor_role'] ?? '') == 'operator' ? 'primary' : 'secondary')
                        ?> badge-sm">
                            <?= ucfirst($row['pelapor_role'] ?? '-') ?>
                        </span>
                    </td>
                    <td data-label="Dibuat">
                        <small class="text-muted">
                            <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                            <br><?= date('H:i', strtotime($row['created_at'])) ?>
                        </small>
                    </td>
                    <td data-label="Aksi">
                        <div class="btn-action-group" data-row-id="<?= $row['id'] ?>">
                            <!-- View button - always available -->
                            <a href="<?= BASE_URL ?>laporan/detail/<?= $row['id'] ?>" 
                               class="btn-action btn-action-info btn-action-view" 
                               data-action="view"
                               title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php 
                            // LOGIKA HAK AKSES EDIT
                            // 1. Admin/Operator: Bisa edit semua (kecuali dibatasi logic lain)
                            // 2. Petugas: bisa edit laporan miliknya sendiri
                            $canEdit = false;
                            if (in_array($_SESSION['role'] ?? '', ['admin', 'operator'])) {
                                $canEdit = true;
                            } elseif (($_SESSION['role'] ?? '') === 'petugas') {
                                if ($row['user_id'] == $_SESSION['user_id']) {
                                    $canEdit = true;
                                }
                            }
                            ?>

                            <?php if($canEdit): ?>
                            <!-- Regular edit button -->
                            <a href="<?= BASE_URL ?>laporan/edit/<?= $row['id'] ?>" 
                               class="btn-action btn-action-warning btn-action-edit" 
                               data-action="edit"
                               title="Edit Laporan">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>

                            <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'operator']) && $row['status'] !== 'Diarsipkan'): ?>
                            <form action="<?= BASE_URL ?>laporan/archive/<?= $row['id'] ?>" method="POST" class="d-inline">
                                <?= Security::getCsrfField() ?>
                                <button type="submit"
                                        class="btn-action btn-action-secondary"
                                        data-action="archive"
                                        onclick="return confirm('Arsipkan laporan ini? Laporan tidak lagi dihitung sebagai laporan aktif.')"
                                        title="Arsipkan Laporan">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php 
                            // LOGIKA HAK AKSES DELETE
                            // 1. Admin: Bisa delete semua
                            // 2. Petugas: HANYA bisa delete jika status 'Draf' atau 'Ditolak' DAN milik sendiri
                            $canDelete = false;
                            if (($_SESSION['role'] ?? '') == 'admin') {
                                $canDelete = true;
                            } elseif (($_SESSION['role'] ?? '') == 'petugas') {
                                if ($row['user_id'] == $_SESSION['user_id'] && in_array($row['status'], ['Draf', 'Ditolak'])) {
                                    $canDelete = true;
                                }
                            }
                            ?>

                            <?php if($canDelete): ?>
                            <!-- Regular delete button -->
                            <form action="<?= BASE_URL ?>laporan/delete/<?= $row['id'] ?>" method="POST" class="d-inline">
                                <?= Security::getCsrfField() ?>
                                <button type="submit"
                                        class="btn-action btn-action-danger btn-action-delete"
                                        data-action="delete"
                                        onclick="return confirm('Yakin ingin menghapus laporan ini?')"
                                        title="Hapus Laporan">
                                    <i class="fas fa-trash"></i>
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
