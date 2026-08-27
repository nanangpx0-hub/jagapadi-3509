<?php
$reportType = $petugasReportType ?? '';

// Nonaktifkan efek timbul-tenggelam (hover-lift / transisi / animasi) agar
// tampilan petugas stabil, sejalan dengan halaman laporan lainnya.
?>
<style>
.petugas-laporan-page,
.petugas-laporan-page *,
.petugas-laporan-page *::before,
.petugas-laporan-page *::after {
    animation: none !important;
    transition: none !important;
}
.petugas-laporan-page *:hover,
.petugas-laporan-page *:focus,
.petugas-laporan-page *:active {
    transform: none !important;
}
</style>
<?php
$isHama = $reportType === 'hama';
$basePath = $isHama ? 'laporan' : 'irigasi';
$heading = $isHama ? 'Laporan Hama' : 'Laporan Irigasi';
$createLabel = $isHama ? 'Tambah Fenomena Hama' : 'Tambah Fenomena Irigasi';
$icon = $isHama ? 'bug' : 'water';
$query = [
    'status' => $status ?? '',
    'search' => $search ?? '',
    'date_from' => $dateFrom ?? '',
    'date_to' => $dateTo ?? '',
    'per_page' => $perPage ?? 20,
];
$statusStyles = [
    'Draf' => ['secondary', 'Draf'],
    'Submitted' => ['primary', 'Submitted'],
    'Diverifikasi' => ['success', 'Diverifikasi'],
    'Ditolak' => ['danger', 'Ditolak'],
    'Diarsipkan' => ['dark', 'Diarsipkan'],
];
?>

<div class="row petugas-laporan-page">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-<?= $icon ?>"></i> <?= $heading ?></h3>
        <div class="card-tools">
          <a href="<?= BASE_URL . $basePath ?>/create" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> <?= $createLabel ?>
          </a>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="<?= BASE_URL . $basePath ?>" class="mb-3">
          <div class="row">
            <div class="col-md-2">
              <select name="status" class="form-control">
                <option value="">-- Semua Status --</option>
                <?php foreach (array_keys($statusStyles) as $statusValue): ?>
                  <option value="<?= $statusValue ?>" <?= ($status ?? '') === $statusValue ? 'selected' : '' ?>><?= $statusValue ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars((string) ($dateFrom ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2">
              <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars((string) ($dateTo ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2">
              <select name="per_page" class="form-control" aria-label="Jumlah data per halaman">
                <?php foreach ([10, 20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= (int) ($perPage ?? 20) === $size ? 'selected' : '' ?>><?= $size ?> data</option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <div class="input-group">
                <input type="search" name="search" class="form-control" value="<?= htmlspecialchars((string) ($search ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari laporan...">
                <div class="input-group-append">
                  <button type="submit" class="btn btn-primary" title="Terapkan filter"><i class="fas fa-search"></i></button>
                  <a href="<?= BASE_URL . $basePath ?>" class="btn btn-outline-secondary" title="Reset filter"><i class="fas fa-times"></i></a>
                </div>
              </div>
            </div>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover">
            <thead><tr>
              <th>No</th><th>Nomor Laporan</th><th>Tanggal</th><?php if ($isHama): ?><th>Preview Media</th><?php endif; ?>
              <th><?= $isHama ? 'OPT' : 'Saluran' ?></th><th>Lokasi</th>
              <th><?= $isHama ? 'Keparahan' : 'Kondisi' ?></th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($laporan)): ?>
              <tr><td colspan="<?= $isHama ? 9 : 8 ?>" class="text-center text-muted py-4">Tidak ada data laporan</td></tr>
            <?php else: $number = ((int) ($page ?? 1) - 1) * (int) ($perPage ?? 20) + 1; ?>
              <?php foreach ($laporan as $item): $statusData = $statusStyles[$item['status'] ?? ''] ?? ['secondary', $item['status'] ?? '-']; ?>
              <tr>
                <td class="text-center"><?= $number++ ?></td>
                <td><code><?= htmlspecialchars((string) ($item['nomor_laporan'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= !empty($item['tanggal']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $item['tanggal'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                <?php if ($isHama): ?>
                <td style="min-width:150px">
                  <?php if (!empty($item['foto_url'])): ?>
                    <a href="<?= BASE_URL . htmlspecialchars(ltrim((string) $item['foto_url'], '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Buka foto">
                      <img src="<?= BASE_URL . htmlspecialchars(ltrim((string) $item['foto_url'], '/'), ENT_QUOTES, 'UTF-8') ?>" alt="Preview foto laporan" loading="lazy" style="width:120px;max-height:100px;object-fit:cover;border-radius:6px">
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($item['video_url'])): ?>
                    <video controls preload="metadata" playsinline style="display:block;width:140px;max-height:105px;margin-top:6px;background:#111;border-radius:6px">
                      <source src="<?= BASE_URL . htmlspecialchars(ltrim((string) $item['video_url'], '/'), ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                    </video>
                  <?php endif; ?>
                  <?php if (empty($item['foto_url']) && empty($item['video_url'])): ?><small class="text-muted">Media tidak tersedia</small><?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                  <strong><?= htmlspecialchars((string) ($isHama ? ($item['nama_opt'] ?? '-') : ($item['nama_saluran'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if (!$isHama && !empty($item['daerah_irigasi'])): ?><br><small class="text-muted"><?= htmlspecialchars((string) $item['daerah_irigasi'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) (($item['nama_desa'] ?? $item['desa'] ?? '-') . ' / ' . ($item['nama_kecamatan'] ?? $item['kecamatan'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($isHama ? ($item['tingkat_keparahan'] ?? '-') : ($item['kondisi_fisik'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge badge-<?= $statusData[0] ?>"><?= htmlspecialchars((string) $statusData[1], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><div class="btn-group btn-group-sm">
                  <a href="<?= BASE_URL . $basePath ?>/detail/<?= (int) $item['id'] ?>" class="btn btn-info" title="Lihat"><i class="fas fa-eye"></i></a>
                  <?php if (in_array($item['status'] ?? '', ['Draf', 'Ditolak'], true)): ?>
                    <a href="<?= BASE_URL . $basePath ?>/edit/<?= (int) $item['id'] ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                  <?php endif; ?>
                </div></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
          <small class="text-muted">Menampilkan <?= count($laporan ?? []) ?> dari <?= (int) ($total ?? 0) ?> laporan</small>
          <?php if (($totalPages ?? 0) > 1): ?><nav aria-label="Navigasi <?= $heading ?>"><ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= (int) $totalPages; $i++): $query['page'] = $i; ?>
              <li class="page-item <?= $i === (int) $page ? 'active' : '' ?>"><a class="page-link" href="<?= BASE_URL . $basePath . '?' . htmlspecialchars(http_build_query(array_filter($query, static fn($value) => $value !== '')), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a></li>
            <?php endfor; ?>
          </ul></nav><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
