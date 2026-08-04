<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i> Edit Laporan Lainnya
                </h3>
            </div>
            <form action="<?= BASE_URL ?>laporan-lainnya/update/<?= $laporan['id'] ?>" method="POST" enctype="multipart/form-data" id="formEditLaporan">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="jenis_id" value="<?= $laporan['jenis_id'] ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jenis Laporan</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($laporan['jenis_nama'] ?? '') ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Kejadian</label>
                                <input type="date" name="tanggal_kejadian" class="form-control" value="<?= htmlspecialchars($laporan['tanggal_kejadian'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div id="dynamicFieldsContainer" class="mb-3">
                        <h5><i class="fas fa-list"></i> Data Laporan</h5>
                        <div class="row">
                            <?php if(!empty($jenisFields)): ?>
                            <?php foreach($jenisFields as $field): ?>
                            <?php
                            $fieldName = $field['name'];
                            $fieldValue = $dataJson[$fieldName] ?? '';
                            $isRequired = !empty($field['required']) ? '<span class="text-danger">*</span>' : '';
                            $requiredAttr = !empty($field['required']) ? 'required' : '';
                            $inputType = $field['type'] === 'number' ? 'number' : 'text';
                            $stepAttr = $field['type'] === 'number' ? 'step="any"' : '';
                            $label = $field['label'] ?? $field['name'];
                            ?>
                            <div class="col-md-6 mb-3">
                                <label><?= htmlspecialchars($label) ?> <?= $isRequired ?></label>
                                <?php if($field['type'] === 'number'): ?>
                                <input type="number" name="<?= htmlspecialchars($fieldName) ?>" class="form-control" value="<?= htmlspecialchars($fieldValue) ?>" <?= $requiredAttr ?> <?= $stepAttr ?>>
                                <?php else: ?>
                                <input type="text" name="<?= htmlspecialchars($fieldName) ?>" class="form-control" value="<?= htmlspecialchars($fieldValue) ?>" <?= $requiredAttr ?>>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">Tidak ada field untuk jenis laporan ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <input type="text" name="alamat_lengkap" class="form-control" value="<?= htmlspecialchars($laporan['alamat_lengkap'] ?? '') ?>">
                    </div>

                    <input type="hidden" name="kabupaten_id" value="<?= htmlspecialchars($laporan['kabupaten_id'] ?? '') ?>">
                    <input type="hidden" name="kecamatan_id" value="<?= htmlspecialchars($laporan['kecamatan_id'] ?? '') ?>">
                    <input type="hidden" name="desa_id" value="<?= htmlspecialchars($laporan['desa_id'] ?? '') ?>">

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi tambahan"><?= htmlspecialchars($laporan['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Latitude</label>
                                <input type="number" name="latitude" class="form-control" value="<?= htmlspecialchars($laporan['latitude'] ?? '') ?>" step="any" min="-90" max="90">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Longitude</label>
                                <input type="number" name="longitude" class="form-control" value="<?= htmlspecialchars($laporan['longitude'] ?? '') ?>" step="any" min="-180" max="180">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Perbarui
                    </button>
                    <a href="<?= BASE_URL ?>laporan-lainnya/show/<?= $laporan['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>