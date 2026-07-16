<style>
    .header-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px; }
    .btn-add { display:inline-block; padding:8px 16px; background:#1a73e8; color:#fff; border-radius:6px; text-decoration:none; font-size:14px; font-weight:500; }
    .btn-sm { padding:4px 12px; border-radius:4px; font-size:12px; text-decoration:none; display:inline-block; margin-right:4px; }
    .btn-edit { background:#fff3e0; color:#e65100; }
    .btn-view { background:#e8f5e9; color:#2e7d32; }
    .btn-delete { background:#fce4ec; color:#c62828; border:none; cursor:pointer; font-size:12px; padding:4px 12px; border-radius:4px; }
    .filter-bar { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; align-items:flex-end; }
    .filter-bar input, .filter-bar select { padding:8px 12px; border:1px solid #d0d0d0; border-radius:6px; font-size:14px; }
    .filter-bar button { padding:8px 16px; background:#f5f5f5; border:1px solid #d0d0d0; border-radius:6px; cursor:pointer; font-size:14px; }
    .filter-bar button:hover { background:#e0e0e0; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #e0e0e0; font-size:14px; }
    th { background:#f5f5f5; font-weight:600; color:#555; }
    tr:hover td { background:#fafafa; }
    .badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500; }
    .badge-draf { background:#fff3e0; color:#e65100; }
    .badge-submitted { background:#e3f2fd; color:#1565c0; }
    .badge-verified { background:#e8f5e9; color:#2e7d32; }
    .badge-rejected { background:#fce4ec; color:#c62828; }
    .badge-archived { background:#f5f5f5; color:#888; }
    .pagination { display:flex; gap:8px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
    .pagination a, .pagination span { padding:6px 12px; border:1px solid #d0d0d0; border-radius:4px; font-size:13px; text-decoration:none; color:#333; }
    .pagination a:hover { background:#f0f0f0; }
    .pagination .active { background:#1a73e8; color:#fff; border-color:#1a73e8; }
    .text-muted { color:#999; font-size:13px; }
</style>

<div class="header-bar">
    <h2 style="margin:0;">Laporan Irigasi</h2>
    <?php if ($currentUser['role'] === 'petugas'): ?>
        <a href="/laporan-irigasi/create" class="btn-add">+ Buat Laporan</a>
    <?php endif; ?>
</div>

<form method="GET" action="/laporan-irigasi" class="filter-bar">
    <div>
        <label style="font-size:12px;color:#888;display:block;">Status</label>
        <select name="status">
            <option value="">Semua Status</option>
            <option value="Draf" <?= ($filters['status'] ?? '') === 'Draf' ? 'selected' : '' ?>>Draf</option>
            <option value="Submitted" <?= ($filters['status'] ?? '') === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
        </select>
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">Tanggal Dari</label>
        <input type="date" name="tanggal_from" value="<?= \App\Core\Security::e($filters['tanggal_from'] ?? '') ?>">
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">Tanggal Sampai</label>
        <input type="date" name="tanggal_to" value="<?= \App\Core\Security::e($filters['tanggal_to'] ?? '') ?>">
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">Kabupaten</label>
        <select name="kabupaten_id">
            <option value="">Semua Kabupaten</option>
            <?php foreach ($kabupaten as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= ($filters['kabupaten_id'] ?? '') == $k['id'] ? 'selected' : '' ?>><?= \App\Core\Security::e($k['nama_kabupaten']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">Kondisi Fisik</label>
        <select name="kondisi_fisik">
            <option value="">Semua</option>
            <option value="Bagus" <?= ($filters['kondisi_fisik'] ?? '') === 'Bagus' ? 'selected' : '' ?>>Bagus</option>
            <option value="Sedang" <?= ($filters['kondisi_fisik'] ?? '') === 'Sedang' ? 'selected' : '' ?>>Sedang</option>
            <option value="Tidak Bagus" <?= ($filters['kondisi_fisik'] ?? '') === 'Tidak Bagus' ? 'selected' : '' ?>>Tidak Bagus</option>
            <option value="Rusak" <?= ($filters['kondisi_fisik'] ?? '') === 'Rusak' ? 'selected' : '' ?>>Rusak</option>
        </select>
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">Cari</label>
        <input type="text" name="q" placeholder="Cari nomor/saluran/catatan..." value="<?= \App\Core\Security::e($filters['q'] ?? '') ?>">
    </div>
    <div>
        <label style="font-size:12px;color:#888;display:block;">&nbsp;</label>
        <button type="submit">Filter</button>
    </div>
</form>

<table>
    <thead>
        <tr>
            <th>No. Laporan</th>
            <th>Tanggal</th>
            <th>Saluran</th>
            <th>Kecamatan</th>
            <th>Desa</th>
            <th>Kondisi</th>
            <th>Debit</th>
            <th>Status</th>
            <th>Diperbarui</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($data as $li): ?>
        <tr>
            <td><?= $li['nomor_laporan'] !== null ? \App\Core\Security::e($li['nomor_laporan']) : '<span class="text-muted">Belum dibuat</span>' ?></td>
            <td><?= \App\Core\Security::e($li['tanggal'] ?? '-') ?></td>
            <td><?= \App\Core\Security::e($li['nama_saluran'] ?? '-') ?></td>
            <td><?= \App\Core\Security::e($li['nama_kecamatan'] ?? '-') ?></td>
            <td><?= \App\Core\Security::e($li['nama_desa'] ?? '-') ?></td>
            <td><?= \App\Core\Security::e($li['kondisi_fisik'] ?? '-') ?></td>
            <td><?= \App\Core\Security::e($li['debit_air'] ?? '-') ?></td>
            <td><span class="badge badge-<?= strtolower($li['status']) ?>"><?= \App\Core\Security::e($li['status']) ?></span></td>
            <td><?= date('d/m/Y H:i', strtotime($li['updated_at'])) ?></td>
            <td>
                <a href="/laporan-irigasi/<?= (int) $li['id'] ?>" class="btn-sm btn-view">Detail</a>
                <?php if ($li['status'] === 'Draf' && $currentUser['role'] === 'petugas'): ?>
                    <a href="/laporan-irigasi/<?= (int) $li['id'] ?>/edit" class="btn-sm btn-edit">Edit</a>
                    <form method="POST" action="/laporan-irigasi/<?= (int) $li['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Hapus draf ini?')">
                        <?= \App\Core\Security::csrfField() ?>
                        <button type="submit" class="btn-delete">Hapus</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (count($data) === 0): ?>
        <tr><td colspan="10" style="text-align:center;color:#999;padding:24px;">Belum ada laporan irigasi.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($meta['last_page'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
        <?php if ($i == $meta['page']): ?>
            <span class="active"><?= $i ?></span>
        <?php else: ?>
            <?php
                $query = $_GET;
                $query['page'] = $i;
                $qs = http_build_query($query);
            ?>
            <a href="/laporan-irigasi?<?= \App\Core\Security::e($qs) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>
