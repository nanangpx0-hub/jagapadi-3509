<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$typeLabels = ['bug' => 'Bug', 'fitur_baru' => 'Fitur Baru', 'peningkatan' => 'Peningkatan'];
$statusLabels = ['diterima' => 'Diterima', 'dalam_proses' => 'Dalam Proses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
?>
<div class="row justify-content-center">
  <div class="col-xl-9">
    <div class="card card-outline card-primary">
      <div class="card-header d-flex align-items-center">
        <div><h3 class="card-title"><i class="fas fa-comment-dots mr-1"></i>Saran dan Aduan Saya</h3><br><small class="text-muted">Kirim masukan dan pantau riwayat pribadi Anda.</small></div>
        <a href="<?= BASE_URL ?>feedback/create" class="btn btn-primary btn-sm ml-auto"><i class="fas fa-plus mr-1"></i>Kirim Saran</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($feedback)): ?>
          <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>Belum ada saran atau aduan.</p></div>
        <?php else: ?>
          <div class="list-group list-group-flush">
          <?php foreach ($feedback as $item): ?>
            <a class="list-group-item list-group-item-action" href="<?= BASE_URL ?>feedback/detail/<?= (int) $item['id'] ?>">
              <div class="d-flex justify-content-between"><strong><?= $e($item['judul'] ?? '-') ?></strong><time class="small text-muted"><?= $e(date('d/m/Y H:i', strtotime((string) ($item['created_at'] ?? 'now')))) ?></time></div>
              <p class="small text-muted text-truncate mb-2"><?= $e($item['deskripsi'] ?? '') ?></p>
              <span class="badge badge-info"><?= $e($typeLabels[$item['jenis_feedback'] ?? ''] ?? '-') ?></span>
              <span class="badge badge-secondary"><?= $e($statusLabels[$item['status'] ?? ''] ?? '-') ?></span>
              <span class="badge badge-light">Prioritas <?= $e(ucfirst((string) ($item['prioritas'] ?? '-'))) ?></span>
            </a>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if (($pagination['totalPages'] ?? 0) > 1): ?>
      <div class="card-footer"><nav><ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($pageNumber = 1; $pageNumber <= (int) $pagination['totalPages']; $pageNumber++): ?>
          <li class="page-item <?= $pageNumber === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $pageNumber ?>"><?= $pageNumber ?></a></li>
        <?php endfor; ?>
      </ul></nav></div>
      <?php endif; ?>
    </div>
  </div>
</div>
