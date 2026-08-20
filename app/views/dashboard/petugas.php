<?php
$dashboard = $petugasDashboard ?? [];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$count = static fn(array $values, string $canonical, string $legacy = ''): int =>
    (int) ($values[$canonical] ?? ($legacy !== '' ? ($values[$legacy] ?? 0) : 0));
$cards = [
    ['Fenomena Hama', 'bug', 'danger', 'laporan/create', 'Tambah Fenomena Hama', $dashboard['hama_summary'] ?? []],
    ['Fenomena Irigasi', 'water', 'info', 'irigasi/create', 'Tambah Fenomena Irigasi', $dashboard['irigasi_summary'] ?? []],
    ['Fenomena Lainnya', 'clipboard-list', 'success', 'laporan-lainnya/create', 'Tambah Fenomena Lainnya', $dashboard['lainnya_summary'] ?? []],
];
$panels = [
    ['Laporan Hama Terbaru', 'laporan', $dashboard['recent_hama'] ?? [], 'hama'],
    ['Laporan Irigasi Terbaru', 'irigasi', $dashboard['recent_irigasi'] ?? [], 'irigasi'],
    ['Laporan Lainnya Terbaru', 'laporan-lainnya', $dashboard['recent_lainnya'] ?? [], 'lainnya'],
];
?>

<div class="row">
<?php foreach ($cards as [$title, $icon, $color, $path, $label, $summary]): ?>
  <div class="col-lg-4 d-flex"><section class="card card-outline card-<?= $color ?> flex-fill">
    <header class="card-header d-flex align-items-center">
      <h3 class="card-title"><i class="fas fa-<?= $icon ?> mr-1"></i><?= $e($title) ?></h3>
      <a class="btn btn-<?= $color ?> btn-sm ml-auto" href="<?= BASE_URL . $path ?>"><i class="fas fa-plus mr-1"></i><?= $e($label) ?></a>
    </header>
    <div class="card-body">
      <span class="badge badge-secondary mr-1">Draf: <?= $count($summary, 'Draf', 'draft') ?></span>
      <span class="badge badge-primary mr-1">Dikirim: <?= $count($summary, 'Submitted', 'submitted') ?></span>
      <span class="badge badge-success mr-1">Diverifikasi: <?= $count($summary, 'Diverifikasi', 'verified') ?></span>
      <span class="badge badge-danger">Ditolak: <?= $count($summary, 'Ditolak', 'rejected') ?></span>
    </div>
  </section></div>
<?php endforeach; ?>
</div>

<div class="row">
  <div class="col-lg-7"><section class="card"><header class="card-header"><h3 class="card-title">Tren Laporan Lainnya</h3></header><div class="card-body"><canvas id="lainnyaTrendChart" height="130"></canvas></div></section></div>
  <div class="col-lg-5"><section class="card"><header class="card-header"><h3 class="card-title">Komposisi Jenis Laporan Lainnya</h3></header><div class="card-body"><canvas id="lainnyaTypeChart" height="190"></canvas></div></section></div>
</div>

<div class="row">
<?php foreach ($panels as [$title, $path, $items, $type]): ?>
  <div class="col-lg-4 d-flex"><section class="card flex-fill">
    <header class="card-header d-flex align-items-center"><h3 class="card-title"><?= $e($title) ?></h3><a href="<?= BASE_URL . $path ?>" class="btn btn-outline-primary btn-xs ml-auto">Lihat Semua</a></header>
    <div class="list-group list-group-flush">
    <?php if (empty($items)): ?><div class="list-group-item text-muted">Belum ada laporan.</div><?php endif; ?>
    <?php foreach (array_slice($items, 0, 3) as $item):
        $location = $type === 'irigasi' ? ($item['nama_saluran'] ?? '-') : ($type === 'lainnya' ? ($item['alamat_lengkap'] ?? '-') : ($item['lokasi'] ?? '-'));
        $note = $type === 'lainnya' ? ($item['deskripsi'] ?? '-') : ($item['catatan'] ?? '-');
        $date = $type === 'lainnya' ? ($item['tanggal_kejadian'] ?? '') : ($item['tanggal'] ?? '');
    ?>
      <article class="list-group-item">
        <strong><?= $e($item['nama_kecamatan'] ?? '-') ?> · <?= $e($item['nama_desa'] ?? '-') ?></strong>
        <div class="small">Lokasi: <?= $e($location) ?></div>
        <div class="small text-muted text-truncate" title="<?= $e($note) ?>"><?= $e($note) ?></div>
        <time class="small text-muted"><?= $date ? $e(date('d/m/Y', strtotime((string) $date))) : '-' ?></time>
      </article>
    <?php endforeach; ?>
    </div>
  </section></div>
<?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  const payload = <?= json_encode($dashboard['lainnya_chart'] ?? ['trend' => [], 'by_type' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const months = Array(12).fill(0);
  (payload.trend || []).forEach(row => { const i = Number(row.bulan) - 1; if (i >= 0 && i < 12) months[i] = Number(row.total); });
  new Chart(document.getElementById('lainnyaTrendChart'), {type: 'line', data: {labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'], datasets: [{label: 'Laporan', data: months, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,.15)', fill: true}]}, options: {responsive: true, maintainAspectRatio: false}});
  const types = payload.by_type || [];
  new Chart(document.getElementById('lainnyaTypeChart'), {type: 'doughnut', data: {labels: types.map(row => row.label), datasets: [{data: types.map(row => Number(row.total)), backgroundColor: ['#28a745','#17a2b8','#ffc107','#dc3545','#6f42c1','#6c757d']}]}, options: {responsive: true, maintainAspectRatio: false}});
});
</script>
