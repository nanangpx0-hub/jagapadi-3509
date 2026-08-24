<?php
/**
 * @var array $old
 * @var array $wilayahNames
 * @var array $keyakinanOptions
 * @var bool $isEdit
 * @var string $actionUrl
 */
$val = static fn (string $key): string => htmlspecialchars((string) ($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$sel = static fn (string $key): string => (string) ($old[$key] ?? '');
?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    Usulan ini <strong>belum menjadi master OPT resmi</strong>. Data akan direview Admin:
    disetujui menjadi master baru, digabungkan ke master yang sudah ada, diminta perbaikan, atau ditolak permanen.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="required" for="nama_lokal">Nama lokal/daerah</label>
            <input type="text" id="nama_lokal" name="nama_lokal" class="form-control" required maxlength="200"
                   value="<?= $val('nama_lokal') ?>" placeholder="cth. Ulat daun jagung">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="nama_nasional">Nama nasional (opsional)</label>
            <input type="text" id="nama_nasional" name="nama_nasional" class="form-control" maxlength="150"
                   value="<?= $val('nama_nasional') ?>" placeholder="Jika diketahui">
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="form-group">
            <label class="required" for="jenis">Jenis</label>
            <select id="jenis" name="jenis" class="form-control" required>
                <?php foreach (['hama', 'penyakit', 'gulma'] as $jenisOpt): ?>
                    <option value="<?= $jenisOpt ?>" <?= $sel('jenis') === $jenisOpt ? 'selected' : '' ?>><?= ucfirst($jenisOpt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-5 col-6">
        <div class="form-group">
            <label class="required" for="komoditas">Komoditas yang diserang</label>
            <input type="text" id="komoditas" name="komoditas" class="form-control" required maxlength="150"
                   value="<?= $val('komoditas') ?>" placeholder="cth. Padi, Jagung">
        </div>
    </div>
    <div class="col-md-3 col-12">
        <div class="form-group">
            <label class="required" for="tanggal_ditemukan">Tanggal ditemukan</label>
            <input type="date" id="tanggal_ditemukan" name="tanggal_ditemukan" class="form-control" required
                   max="<?= date('Y-m-d') ?>" value="<?= $val('tanggal_ditemukan') ?>">
        </div>
    </div>

    <div class="col-12"><h6 class="text-muted mt-2 mb-1">Lokasi temuan</h6></div>

    <div class="col-md-4">
        <div class="form-group">
            <label class="required" for="kabupaten_id">Kabupaten</label>
            <select id="kabupaten_id" name="kabupaten_id" class="form-control" data-usulan-wilayah="kabupaten" required>
                <option value="">Memuat...</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="required" for="kecamatan_id">Kecamatan</label>
            <select id="kecamatan_id" name="kecamatan_id" class="form-control" data-usulan-wilayah="kecamatan" required <?= $sel('kabupaten_id') === '' ? 'disabled' : '' ?>>
                <option value="">Pilih kabupaten dahulu</option>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="required" for="desa_id">Desa</label>
            <select id="desa_id" name="desa_id" class="form-control" data-usulan-wilayah="desa" required <?= $sel('kecamatan_id') === '' ? 'disabled' : '' ?>>
                <option value="">Pilih kecamatan dahulu</option>
            </select>
        </div>
    </div>
    <div class="col-12">
        <div class="form-group">
            <label for="alamat_lokasi">Alamat/keterangan lokasi (opsional)</label>
            <input type="text" id="alamat_lokasi" name="alamat_lokasi" class="form-control" maxlength="300"
                   value="<?= $val('alamat_lokasi') ?>" placeholder="cth. Blok sawah dekat jalan raya">
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="form-group">
            <label for="latitude">Latitude (opsional)</label>
            <input type="number" step="0.0000001" id="latitude" name="latitude"
                   class="form-control" value="<?= $val('latitude') ?>" placeholder="-8.1234567"
                   inputmode="decimal" aria-describedby="coord_help">
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="form-group">
            <label for="longitude">Longitude (opsional)</label>
            <input type="number" step="0.0000001" id="longitude" name="longitude"
                   class="form-control" value="<?= $val('longitude') ?>" placeholder="113.1234567"
                   inputmode="decimal" aria-describedby="coord_help">
            <small id="coord_help" class="form-text text-muted">Rentang: lat -90..90, lng -180..180</small>
        </div>
    </div>

    <div class="col-12"><h6 class="text-muted mt-2 mb-1">Detail serangan</h6></div>

    <div class="col-md-4 col-6">
        <div class="form-group">
            <label for="bagian_terserang">Bagian tanaman terserang (opsional)</label>
            <input type="text" id="bagian_terserang" name="bagian_terserang" class="form-control" maxlength="150"
                   value="<?= $val('bagian_terserang') ?>" placeholder="cth. Daun, buah">
        </div>
    </div>
    <div class="col-md-8 col-12">
        <div class="form-group">
            <label for="pola_gejala">Pola/gejala serangan (opsional)</label>
            <input type="text" id="pola_gejala" name="pola_gejala" class="form-control" maxlength="300"
                   value="<?= $val('pola_gejala') ?>" placeholder="cth. Berlubang memanjang pada daun">
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="form-group">
            <label for="estimasi_terdampak">Perkiraan luas/jumlah terdampak (opsional)</label>
            <input type="number" step="0.01" min="0" id="estimasi_terdampak" name="estimasi_terdampak"
                   class="form-control" value="<?= $val('estimasi_terdampak') ?>" placeholder="cth. 2.5">
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="form-group">
            <label for="satuan_terdampak">Satuan terdampak</label>
            <input type="text" id="satuan_terdampak" name="satuan_terdampak" class="form-control" maxlength="30" list="satuan_terdampak_list"
                   value="<?= $val('satuan_terdampak') ?>" placeholder="cth. hektare">
            <datalist id="satuan_terdampak_list">
                <option value="hektare"></option>
                <option value="tanaman"></option>
                <option value="rumpun"></option>
            </datalist>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="form-group">
            <label for="tingkat_keyakinan">Tingkat keyakinan Anda</label>
            <select id="tingkat_keyakinan" name="tingkat_keyakinan" class="form-control">
                <option value="">-</option>
                <?php foreach ($keyakinan_options as $optKeyakinan): ?>
                    <option value="<?= htmlspecialchars($optKeyakinan) ?>" <?= $sel('tingkat_keyakinan') === $optKeyakinan ? 'selected' : '' ?>><?= htmlspecialchars($optKeyakinan) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-12">
        <div class="form-group">
            <label class="required" for="ciri_ciri">Ciri-ciri/gejala</label>
            <textarea id="ciri_ciri" name="ciri_ciri" class="form-control" rows="3" required maxlength="5000"
                      placeholder="Jelaskan ciri fisik organisme dan gejala pada tanaman"><?= $val('ciri_ciri') ?></textarea>
        </div>
    </div>
    <div class="col-12">
        <div class="form-group">
            <label for="sumber_identifikasi">Sumber identifikasi/catatan (opsional)</label>
            <input type="text" id="sumber_identifikasi" name="sumber_identifikasi" class="form-control" maxlength="255"
                   value="<?= $val('sumber_identifikasi') ?>" placeholder="cth. Ditanyakan ke PPL desa">
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="photos_input">Foto bukti (maksimal <?= UsulanPhotoUploader::MAX_FILES_PER_USULAN ?> file, masing-masing maksimal 5 MB)</label>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="photos_input" name="photos[]"
                       accept="image/jpeg,image/png,image/webp" multiple>
                <label class="custom-file-label" for="photos_input">Pilih foto...</label>
            </div>
            <?php if (!$isEdit): ?>
                <small class="form-text text-muted">Wajib minimal satu foto saat mengirim review. Boleh dikosongkan saat menyimpan draf.</small>
            <?php endif; ?>
            <div id="usulan_photo_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
        </div>
    </div>
</div>
