<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Create Feedback Form Styles */
.jenis-radio-group {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.jenis-radio-item {
    flex: 1;
    min-width: 150px;
}

.jenis-radio-item input[type="radio"] {
    display: none;
}

.jenis-radio-item label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px 15px;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.jenis-radio-item label:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.jenis-radio-item input[type="radio"]:checked + label {
    border-color: #007bff;
    background-color: #e7f1ff;
}

.jenis-radio-item label i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.jenis-radio-item.bug label i {
    color: #dc3545;
}

.jenis-radio-item.fitur label i {
    color: #007bff;
}

.jenis-radio-item.peningkatan label i {
    color: #17a2b8;
}

.prioritas-radio-group {
    display: flex;
    gap: 10px;
}

.prioritas-radio-item {
    flex: 1;
}

.prioritas-radio-item input[type="radio"] {
    display: none;
}

.prioritas-radio-item label {
    display: block;
    text-align: center;
    padding: 10px;
    border: 2px solid #dee2e6;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.prioritas-radio-item label:hover {
    transform: translateY(-2px);
}

.prioritas-radio-item input[type="radio"]:checked + label {
    color: white;
}

.prioritas-radio-item.rendah input[type="radio"]:checked + label {
    background-color: #28a745;
    border-color: #28a745;
}

.prioritas-radio-item.medium input[type="radio"]:checked + label {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.prioritas-radio-item.tinggi input[type="radio"]:checked + label {
    background-color: #dc3545;
    border-color: #dc3545;
}

.file-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-upload-area:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.file-upload-area.dragover {
    border-color: #28a745;
    background-color: #e8f5e9;
}

.file-preview {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.file-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 5px;
}

.char-counter {
    font-size: 0.8rem;
    color: #6c757d;
}

.char-counter.warning {
    color: #ffc107;
}

.char-counter.danger {
    color: #dc3545;
}
</style>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i> Buat Masukan Baru
                </h3>
            </div>
            
            <form action="<?= BASE_URL ?>feedback/create" method="POST" enctype="multipart/form-data" id="feedbackForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div class="card-body">
                    <!-- Jenis Feedback -->
                    <div class="form-group">
                        <label><strong>Jenis Masukan</strong> <span class="text-danger">*</span></label>
                        <div class="jenis-radio-group">
                            <div class="jenis-radio-item bug">
                                <input type="radio" name="jenis_feedback" id="jenisBug" value="bug" 
                                    <?= ($formData['jenis_feedback'] ?? '') === 'bug' ? 'checked' : '' ?>>
                                <label for="jenisBug">
                                    <i class="fas fa-bug"></i>
                                    <strong>Bug Report</strong>
                                    <small class="text-muted">Laporkan error atau masalah</small>
                                </label>
                            </div>
                            <div class="jenis-radio-item fitur">
                                <input type="radio" name="jenis_feedback" id="jenisFitur" value="fitur_baru"
                                    <?= ($formData['jenis_feedback'] ?? '') === 'fitur_baru' ? 'checked' : '' ?>>
                                <label for="jenisFitur">
                                    <i class="fas fa-lightbulb"></i>
                                    <strong>Fitur Baru</strong>
                                    <small class="text-muted">Usulkan fitur yang diinginkan</small>
                                </label>
                            </div>
                            <div class="jenis-radio-item peningkatan">
                                <input type="radio" name="jenis_feedback" id="jenisPeningkatan" value="peningkatan"
                                    <?= ($formData['jenis_feedback'] ?? '') === 'peningkatan' ? 'checked' : '' ?>>
                                <label for="jenisPeningkatan">
                                    <i class="fas fa-arrow-up"></i>
                                    <strong>Peningkatan</strong>
                                    <small class="text-muted">Saran untuk perbaikan</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Judul -->
                    <div class="form-group">
                        <label for="judul"><strong>Judul</strong> <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="judul" 
                               id="judul" 
                               class="form-control" 
                               placeholder="Tulis judul singkat dan jelas..."
                               value="<?= htmlspecialchars($formData['judul'] ?? '') ?>"
                               maxlength="255"
                               required>
                        <small class="char-counter"><span id="judulCount">0</span>/255 karakter (min. 5)</small>
                    </div>
                    
                    <!-- Deskripsi -->
                    <div class="form-group">
                        <label for="deskripsi"><strong>Deskripsi Detail</strong> <span class="text-danger">*</span></label>
                        <textarea name="deskripsi" 
                                  id="deskripsi" 
                                  class="form-control" 
                                  rows="6" 
                                  placeholder="Jelaskan masukan Anda secara detail. Sertakan langkah-langkah untuk mereproduksi bug, atau jelaskan use case untuk fitur baru..."
                                  required><?= htmlspecialchars($formData['deskripsi'] ?? '') ?></textarea>
                        <small class="char-counter"><span id="deskripsiCount">0</span> karakter (min. 20)</small>
                    </div>
                    
                    <!-- Prioritas -->
                    <div class="form-group">
                        <label><strong>Prioritas</strong></label>
                        <div class="prioritas-radio-group">
                            <div class="prioritas-radio-item rendah">
                                <input type="radio" name="prioritas" id="prioritasRendah" value="rendah"
                                    <?= ($formData['prioritas'] ?? '') === 'rendah' ? 'checked' : '' ?>>
                                <label for="prioritasRendah">
                                    <i class="fas fa-arrow-down"></i> Rendah
                                </label>
                            </div>
                            <div class="prioritas-radio-item medium">
                                <input type="radio" name="prioritas" id="prioritasMedium" value="medium"
                                    <?= ($formData['prioritas'] ?? 'medium') === 'medium' ? 'checked' : '' ?>>
                                <label for="prioritasMedium">
                                    <i class="fas fa-minus"></i> Medium
                                </label>
                            </div>
                            <div class="prioritas-radio-item tinggi">
                                <input type="radio" name="prioritas" id="prioritasTinggi" value="tinggi"
                                    <?= ($formData['prioritas'] ?? '') === 'tinggi' ? 'checked' : '' ?>>
                                <label for="prioritasTinggi">
                                    <i class="fas fa-arrow-up"></i> Tinggi
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Pilih tingkat urgensi masukan Anda</small>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="form-group">
                        <label><strong>Lampiran</strong> (Opsional)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-1">Klik atau drag file ke sini</p>
                            <small class="text-muted">Format: JPG, PNG, GIF, WEBP, PDF (Max 5MB)</small>
                            <input type="file" name="attachment" id="attachmentInput" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                        </div>
                        <div class="file-preview" id="filePreview">
                            <div class="d-flex align-items-center">
                                <div id="previewContent"></div>
                                <div class="ml-3">
                                    <p class="mb-1"><strong id="fileName"></strong></p>
                                    <small class="text-muted" id="fileSize"></small>
                                    <button type="button" class="btn btn-sm btn-danger ml-2" onclick="removeFile()">
                                        <i class="fas fa-times"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tips -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb"></i> Tips untuk masukan yang baik:</h6>
                        <ul class="mb-0">
                            <li>Gunakan judul yang singkat dan jelas</li>
                            <li>Jelaskan masalah atau ide secara detail</li>
                            <li>Untuk bug, sertakan langkah-langkah untuk mereproduksi</li>
                            <li>Lampirkan screenshot jika membantu</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Kirim Masukan
                    </button>
                    <a href="<?= BASE_URL ?>feedback" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const judulInput = document.getElementById('judul');
    const deskripsiInput = document.getElementById('deskripsi');
    const judulCount = document.getElementById('judulCount');
    const deskripsiCount = document.getElementById('deskripsiCount');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const attachmentInput = document.getElementById('attachmentInput');
    const filePreview = document.getElementById('filePreview');
    
    // Character counters
    judulInput.addEventListener('input', function() {
        judulCount.textContent = this.value.length;
        updateCharCounterClass(judulCount, this.value.length, 5, 255);
    });
    
    deskripsiInput.addEventListener('input', function() {
        deskripsiCount.textContent = this.value.length;
        updateCharCounterClass(deskripsiCount, this.value.length, 20, 5000);
    });
    
    function updateCharCounterClass(element, length, min, max) {
        element.parentElement.classList.remove('warning', 'danger');
        if (length < min) {
            element.parentElement.classList.add('danger');
        } else if (length > max * 0.9) {
            element.parentElement.classList.add('warning');
        }
    }
    
    // Initialize counts
    judulCount.textContent = judulInput.value.length;
    deskripsiCount.textContent = deskripsiInput.value.length;
    
    // File upload - click
    fileUploadArea.addEventListener('click', function() {
        attachmentInput.click();
    });
    
    // File upload - drag and drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            attachmentInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
    
    attachmentInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });
    
    function handleFileSelect(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        
        if (!allowedTypes.includes(file.type)) {
            toastr.error('Tipe file tidak diizinkan');
            return;
        }
        
        if (file.size > maxSize) {
            toastr.error('Ukuran file maksimal 5MB');
            return;
        }
        
        // Show preview
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        
        const previewContent = document.getElementById('previewContent');
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewContent.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            previewContent.innerHTML = `<i class="fas fa-file-pdf fa-3x text-danger"></i>`;
        }
        
        fileUploadArea.style.display = 'none';
        filePreview.style.display = 'block';
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    // Form validation
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        const jenis = document.querySelector('input[name="jenis_feedback"]:checked');
        const judul = judulInput.value.trim();
        const deskripsi = deskripsiInput.value.trim();
        
        if (!jenis) {
            e.preventDefault();
            toastr.error('Pilih jenis masukan');
            return;
        }
        
        if (judul.length < 5) {
            e.preventDefault();
            toastr.error('Judul minimal 5 karakter');
            judulInput.focus();
            return;
        }
        
        if (deskripsi.length < 20) {
            e.preventDefault();
            toastr.error('Deskripsi minimal 20 karakter');
            deskripsiInput.focus();
            return;
        }
        
        // Show loading
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
    });
});

function removeFile() {
    document.getElementById('attachmentInput').value = '';
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('fileUploadArea').style.display = 'block';
}
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
