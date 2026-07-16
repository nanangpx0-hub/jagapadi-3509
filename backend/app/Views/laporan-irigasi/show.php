<style>
    .detail-card { background:#fff; border-radius:8px; padding:24px; box-shadow:0 1px 6px rgba(0,0,0,0.06); }
    .detail-card h2 { margin-bottom:16px; }
    .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .detail-item { padding:8px 0; border-bottom:1px solid #f0f0f0; }
    .detail-item .label { font-size:12px; color:#888; text-transform:uppercase; }
    .detail-item .value { font-size:15px; font-weight:500; margin-top:2px; }
    .detail-full { padding:8px 0; border-bottom:1px solid #f0f0f0; grid-column:1/-1; }
    .badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:500; }
    .badge-draf { background:#fff3e0; color:#e65100; }
    .badge-submitted { background:#e3f2fd; color:#1565c0; }
    .badge-verified { background:#e8f5e9; color:#2e7d32; }
    .badge-rejected { background:#fce4ec; color:#c62828; }
    .badge-archived { background:#f5f5f5; color:#888; }
    .action-group { margin-top:24px; display:flex; gap:12px; flex-wrap:wrap; }
    .btn { padding:8px 20px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-primary { background:#1a73e8; color:#fff; }
    .btn-success { background:#2e7d32; color:#fff; }
    .btn-danger { background:#c62828; color:#fff; }
    .btn-secondary { background:#e0e0e0; color:#333; }
    .btn:hover { opacity:0.9; }
    @media (max-width:640px) { .detail-grid { grid-template-columns:1fr; } }
</style>

<div class="detail-card">
    <h2>Detail Laporan Irigasi</h2>

    <div class="detail-grid">
        <div class="detail-item">
            <div class="label">Nomor Laporan</div>
            <div class="value"><?= $laporan['nomor_laporan'] !== null ? \App\Core\Security::e($laporan['nomor_laporan']) : '<span style="color:#999">Belum dibuat</span>' ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Status</div>
            <div class="value"><span class="badge badge-<?= strtolower($laporan['status']) ?>"><?= \App\Core\Security::e($laporan['status']) ?></span></div>
        </div>
        <div class="detail-item">
            <div class="label">Tanggal</div>
            <div class="value"><?= \App\Core\Security::e($laporan['tanggal'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Nama Saluran</div>
            <div class="value"><?= \App\Core\Security::e($laporan['nama_saluran'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Daerah Irigasi</div>
            <div class="value"><?= \App\Core\Security::e($laporan['daerah_irigasi'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Kabupaten</div>
            <div class="value"><?= \App\Core\Security::e($laporan['nama_kabupaten'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Kecamatan</div>
            <div class="value"><?= \App\Core\Security::e($laporan['nama_kecamatan'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Desa</div>
            <div class="value"><?= \App\Core\Security::e($laporan['nama_desa'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Kondisi Fisik</div>
            <div class="value"><?= \App\Core\Security::e($laporan['kondisi_fisik'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Debit Air</div>
            <div class="value"><?= \App\Core\Security::e($laporan['debit_air'] ?? '-') ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Latitude</div>
            <div class="value"><?= $laporan['latitude'] !== null ? \App\Core\Security::e((string) $laporan['latitude']) : '-' ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Longitude</div>
            <div class="value"><?= $laporan['longitude'] !== null ? \App\Core\Security::e((string) $laporan['longitude']) : '-' ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Pelapor</div>
            <div class="value"><?= \App\Core\Security::e($laporan['pelapor_nama'] ?? '-') ?> (@<?= \App\Core\Security::e($laporan['pelapor_username'] ?? '-') ?>)</div>
        </div>
        <div class="detail-item">
            <div class="label">Dibuat</div>
            <div class="value"><?= date('d/m/Y H:i', strtotime($laporan['created_at'])) ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Diperbarui</div>
            <div class="value"><?= date('d/m/Y H:i', strtotime($laporan['updated_at'])) ?></div>
        </div>
        <div class="detail-full">
            <div class="label">Catatan</div>
            <div class="value" style="font-weight:400;white-space:pre-wrap;"><?= \App\Core\Security::e($laporan['catatan'] ?? '-') ?></div>
        </div>
    </div>

    <div class="action-group">
        <a href="/laporan-irigasi" class="btn btn-secondary">Kembali ke Daftar</a>
        <?php if ($laporan['status'] === 'Draf' && $currentUser['role'] === 'petugas'): ?>
            <a href="/laporan-irigasi/<?= (int) $laporan['id'] ?>/edit" class="btn btn-primary">Edit</a>
            <form method="POST" action="/laporan-irigasi/<?= (int) $laporan['id'] ?>/submit" style="display:inline">
                <?= \App\Core\Security::csrfField() ?>
                <button type="submit" class="btn btn-success">Kirim Laporan</button>
            </form>
            <form method="POST" action="/laporan-irigasi/<?= (int) $laporan['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Hapus draf ini?')">
                <?= \App\Core\Security::csrfField() ?>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </form>
        <?php endif; ?>
    </div>
</div>
