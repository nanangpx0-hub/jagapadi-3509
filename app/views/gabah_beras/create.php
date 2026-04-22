<?php
/**
 * Form Input Data Produksi Gabah (Wizard)
 * Form 4 langkah untuk input data mobile-friendly
 */

require_once ROOT_PATH . '/app/views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus-circle text-success"></i> Input Data Produksi Gabah
        </h1>
        <a href="<?= BASE_URL ?>gabahBeras" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
        <?= $_SESSION['flash_message'] ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

    <!-- Wizard Progress -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-3">
                    <div class="wizard-step active" data-step="1">
                        <div class="wizard-icon bg-primary text-white rounded-circle mx-auto mb-2" style="width:40px;height:40px;line-height:40px;">1</div>
                        <small>Musim & Tahun</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="wizard-step" data-step="2">
                        <div class="wizard-icon bg-secondary text-white rounded-circle mx-auto mb-2" style="width:40px;height:40px;line-height:40px;">2</div>
                        <small>Lokasi</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="wizard-step" data-step="3">
                        <div class="wizard-icon bg-secondary text-white rounded-circle mx-auto mb-2" style="width:40px;height:40px;line-height:40px;">3</div>
                        <small>Produksi</small>
                    </div>
                </div>
                <div class="col-3">
                    <div class="wizard-step" data-step="4">
                        <div class="wizard-icon bg-secondary text-white rounded-circle mx-auto mb-2" style="width:40px;height:40px;line-height:40px;">4</div>
                        <small>Kualitas & Foto</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="gabahForm">
        <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
        
        <!-- Step 1: Musim & Tahun -->
        <div class="card shadow mb-4 step-content" id="step1">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-calendar"></i> Langkah 1: Pilih Musim & Tahun</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Musim Tanam <span class="text-danger">*</span></label>
                        <select name="musim_tanam" class="form-control form-control-lg" required id="musimTanam">
                            <option value="">-- Pilih Musim --</option>
                            <?php foreach ($musim_list as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">MT1: Okt-Mar, MT2: Apr-Jul, MT3: Aug-Sep</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Tahun <span class="text-danger">*</span></label>
                        <select name="tahun" class="form-control form-control-lg" required id="tahun">
                            <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>"><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="text-right">
                    <button type="button" class="btn btn-primary btn-lg btn-next" data-next="2">
                        Lanjut <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Lokasi -->
        <div class="card shadow mb-4 step-content d-none" id="step2">
            <div class="card-header bg-success text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-map-marker-alt"></i> Langkah 2: Pilih Lokasi</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Kabupaten <span class="text-danger">*</span></label>
                        <select name="kabupaten_id" class="form-control" required id="kabupaten">
                            <?php foreach ($kabupaten_list as $kab): ?>
                            <option value="<?= $kab['id'] ?>"><?= htmlspecialchars($kab['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Kecamatan <span class="text-danger">*</span></label>
                        <select name="kecamatan_id" class="form-control" required id="kecamatan">
                            <option value="">-- Pilih Kecamatan --</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Desa</label>
                        <select name="desa_id" class="form-control" id="desa">
                            <option value="">-- Pilih Desa (Opsional) --</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Nama Lokasi/Sawah <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lokasi" class="form-control" required placeholder="Contoh: Sawah Blok A Desa Sukamaju">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Daerah Irigasi (Jika ada)</label>
                        <select name="irigasi_id" class="form-control" id="irigasi">
                            <option value="">-- Tidak Terkait Irigasi --</option>
                            <?php foreach ($irigasi_list as $ir): ?>
                            <option value="<?= $ir['id'] ?>"><?= htmlspecialchars($ir['nama_irigasi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="font-weight-bold">Varietas Padi</label>
                    <input type="text" name="varietas" class="form-control" placeholder="Contoh: Ciherang, IR64, Mekongga">
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-lg btn-prev" data-prev="1">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="button" class="btn btn-success btn-lg btn-next" data-next="3">
                        Lanjut <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Produksi -->
        <div class="card shadow mb-4 step-content d-none" id="step3">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-weight-hanging"></i> Langkah 3: Data Produksi</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Luas Tanam (Ha) <span class="text-danger">*</span></label>
                        <input type="number" name="luas_tanam" class="form-control form-control-lg" required 
                               step="0.01" min="0.01" placeholder="0.00" id="luasTanam">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Luas Panen (Ha) <span class="text-danger">*</span></label>
                        <input type="number" name="luas_panen" class="form-control form-control-lg" required 
                               step="0.01" min="0.01" placeholder="0.00" id="luasPanen">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Produksi Total (ton GKG) <span class="text-danger">*</span></label>
                        <input type="number" name="produksi_total" class="form-control form-control-lg" required 
                               step="0.01" min="0" placeholder="0.00" id="produksiTotal">
                        <small class="text-muted">GKG = Gabah Kering Giling</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Produktivitas (Otomatis)</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" id="produktivitasDisplay" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">ton/ha</span>
                            </div>
                        </div>
                        <div id="produktivitasWarning" class="text-warning small mt-1" style="display:none;"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-lg btn-prev" data-prev="2">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="button" class="btn btn-info btn-lg btn-next" data-next="4">
                        Lanjut <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 4: Kualitas & Foto -->
        <div class="card shadow mb-4 step-content d-none" id="step4">
            <div class="card-header bg-warning">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-star"></i> Langkah 4: Kualitas & Dokumentasi</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Kadar Air (%)</label>
                        <input type="number" name="kadar_air" class="form-control" 
                               step="0.1" min="10" max="30" placeholder="14.0">
                        <small class="text-muted">Standar optimal: 14%</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Grade Kualitas <span class="text-danger">*</span></label>
                        <select name="grade_kualitas" class="form-control" required>
                            <?php foreach ($grade_list as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $key === 'B' ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Harga Gabah (Rp/kg)</label>
                        <input type="number" name="harga_gabah" class="form-control" 
                               step="100" min="0" placeholder="5500">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="font-weight-bold">Foto Dokumentasi</label>
                    <div class="custom-file">
                        <input type="file" name="foto" class="custom-file-input" id="fotoInput" accept="image/*">
                        <label class="custom-file-label" for="fotoInput">Pilih foto...</label>
                    </div>
                    <small class="text-muted">Format: JPG, PNG, GIF. Maks 5MB</small>
                    <div id="fotoPreview" class="mt-2"></div>
                </div>
                <div class="mb-3">
                    <label class="font-weight-bold">Keterangan/Catatan</label>
                    <textarea name="keterangan" class="form-control" rows="3" 
                              placeholder="Catatan tambahan tentang kondisi panen..."></textarea>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-lg btn-prev" data-prev="3">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.wizard-step.active .wizard-icon {
    background-color: #007bff !important;
    transform: scale(1.1);
}
.wizard-step.completed .wizard-icon {
    background-color: #28a745 !important;
}
.step-content {
    transition: all 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wizard navigation
    document.querySelectorAll('.btn-next').forEach(btn => {
        btn.addEventListener('click', function() {
            const nextStep = this.dataset.next;
            goToStep(nextStep);
        });
    });
    
    document.querySelectorAll('.btn-prev').forEach(btn => {
        btn.addEventListener('click', function() {
            const prevStep = this.dataset.prev;
            goToStep(prevStep);
        });
    });
    
    function goToStep(stepNum) {
        document.querySelectorAll('.step-content').forEach(el => el.classList.add('d-none'));
        document.getElementById('step' + stepNum).classList.remove('d-none');
        
        document.querySelectorAll('.wizard-step').forEach((el, idx) => {
            el.classList.remove('active', 'completed');
            if (idx + 1 < stepNum) el.classList.add('completed');
            if (idx + 1 == stepNum) el.classList.add('active');
            
            const icon = el.querySelector('.wizard-icon');
            if (idx + 1 < stepNum) icon.classList.replace('bg-secondary', 'bg-success');
            else if (idx + 1 == stepNum) icon.classList.replace('bg-secondary', 'bg-primary');
        });
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Productivity calculation
    const luasPanen = document.getElementById('luasPanen');
    const produksiTotal = document.getElementById('produksiTotal');
    const produktivitasDisplay = document.getElementById('produktivitasDisplay');
    const produktivitasWarning = document.getElementById('produktivitasWarning');
    
    function calculateProductivity() {
        const luas = parseFloat(luasPanen.value) || 0;
        const produksi = parseFloat(produksiTotal.value) || 0;
        
        if (luas > 0) {
            const produktivitas = produksi / luas;
            produktivitasDisplay.value = produktivitas.toFixed(2);
            
            // Validation
            if (produktivitas < 1 || produktivitas > 15) {
                produktivitasWarning.style.display = 'block';
                produktivitasWarning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Nilai ${produktivitas.toFixed(2)} ton/ha tampak tidak wajar. Rentang normal: 1-15 ton/ha`;
                produktivitasDisplay.classList.add('is-invalid');
            } else {
                produktivitasWarning.style.display = 'none';
                produktivitasDisplay.classList.remove('is-invalid');
            }
        } else {
            produktivitasDisplay.value = '';
        }
    }
    
    luasPanen?.addEventListener('input', calculateProductivity);
    produksiTotal?.addEventListener('input', calculateProductivity);
    
    // File input label update
    document.getElementById('fotoInput')?.addEventListener('change', function() {
        const fileName = this.files[0]?.name || 'Pilih foto...';
        this.nextElementSibling.textContent = fileName;
        
        // Preview
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('fotoPreview').innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height:150px;">`;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Load kecamatan based on kabupaten
    document.getElementById('kabupaten')?.addEventListener('change', function() {
        const kabId = this.value;
        fetch(`<?= BASE_URL ?>api/location/kecamatan?kabupaten_id=${kabId}`)
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('kecamatan');
                select.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
                (data.data || []).forEach(kec => {
                    select.innerHTML += `<option value="${kec.id}">${kec.nama}</option>`;
                });
            }).catch(() => {
                // Fallback: manual input kecamatan
            });
    });
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
