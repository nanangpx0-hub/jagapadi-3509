<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<!-- Health Check -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-heartbeat"></i> Health Check Sistem</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Health Check</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php if ($overall === 'ok'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Semua sistem dalam kondisi sehat pada <?= date('d-m-Y H:i:s') ?>.
            </div>
        <?php elseif ($overall === 'warning'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Terdapat peringatan — periksa detail di bawah. (<?= date('d-m-Y H:i:s') ?>)
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> Terdapat kegagalan sistem! (<?= date('d-m-Y H:i:s') ?>)
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Hasil Pemeriksaan</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="location.reload()" title="Periksa ulang">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <a href="<?= BASE_URL ?>admin/health?format=json" class="btn btn-tool" title="Tampilkan JSON">
                        <i class="fas fa-code"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 220px;">Komponen</th>
                            <th style="width: 110px;">Status</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $name => $check): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($name) ?></code></td>
                                <td>
                                    <?php if ($check['status'] === 'ok'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> OK</span>
                                    <?php elseif ($check['status'] === 'warning'): ?>
                                        <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Warning</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fas fa-times"></i> Fail</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($check['detail']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
