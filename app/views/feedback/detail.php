<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* Detail Feedback Styles */
.feedback-detail-card {
    border-left: 5px solid;
}

.feedback-detail-card.prioritas-tinggi {
    border-left-color: #dc3545;
}

.feedback-detail-card.prioritas-medium {
    border-left-color: #ffc107;
}

.feedback-detail-card.prioritas-rendah {
    border-left-color: #28a745;
}

.status-timeline {
    position: relative;
    padding-left: 30px;
}

.status-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    padding: 15px 0;
    border-bottom: 1px dashed #eee;
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-item.status-diterima::before {
    background: #6c757d;
    box-shadow: 0 0 0 2px #6c757d;
}

.timeline-item.status-dalam_proses::before {
    background: #ffc107;
    box-shadow: 0 0 0 2px #ffc107;
}

.timeline-item.status-selesai::before {
    background: #28a745;
    box-shadow: 0 0 0 2px #28a745;
}

.timeline-item.status-ditolak::before {
    background: #dc3545;
    box-shadow: 0 0 0 2px #dc3545;
}

.vote-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.vote-count-large {
    font-size: 3rem;
    font-weight: bold;
}

.attachment-preview {
    max-width: 100%;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.admin-actions {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}

.voters-list {
    max-height: 200px;
    overflow-y: auto;
}

.voter-item {
    display: flex;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.voter-item:last-child {
    border-bottom: none;
}
</style>

<div class="row">
    <!-- Back button -->
    <div class="col-12 mb-3">
        <a href="<?= BASE_URL ?>feedback" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <div class="card feedback-detail-card prioritas-<?= $feedback['prioritas'] ?>">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="mb-1"><?= htmlspecialchars($feedback['judul'] ?? '') ?></h4>
                        <div class="mb-2">
                            <span class="badge badge-jenis-<?= $feedback['jenis_feedback'] ?>">
                                <?php
                                $jenisLabels = ['bug' => 'Bug Report', 'fitur_baru' => 'Fitur Baru', 'peningkatan' => 'Peningkatan'];
                                echo $jenisLabels[$feedback['jenis_feedback']] ?? $feedback['jenis_feedback'];
                                ?>
                            </span>
                            <span class="badge badge-status-<?= $feedback['status'] ?>">
                                <?php
                                $statusLabels = ['diterima' => 'Diterima', 'dalam_proses' => 'Dalam Proses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
                                echo $statusLabels[$feedback['status']] ?? $feedback['status'];
                                ?>
                            </span>
                            <span class="badge badge-<?= $feedback['prioritas'] === 'tinggi' ? 'danger' : ($feedback['prioritas'] === 'medium' ? 'warning' : 'success') ?>">
                                Prioritas: <?= ucfirst($feedback['prioritas']) ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#updateStatusModal">
                                <i class="fas fa-edit"></i> Update Status
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="deleteFeedback(<?= $feedback['id'] ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Meta Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <strong>Dibuat oleh:</strong><br>
                            <?= htmlspecialchars($feedback['user_nama'] ?? 'Unknown') ?> 
                            <span class="badge badge-secondary"><?= ucfirst($feedback['user_role'] ?? '') ?></span>
                        </small>
                    </div>
                    <div class="col-md-6 text-md-right">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> <strong>Tanggal:</strong><br>
                            <?= date('d F Y, H:i', strtotime($feedback['created_at'])) ?>
                        </small>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="mb-4">
                    <h6><i class="fas fa-align-left"></i> Deskripsi</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($feedback['deskripsi'] ?? '')) ?>
                    </div>
                </div>
                
                <!-- Attachment -->
                <?php if ($feedback['attachment_url']): ?>
                <div class="mb-4">
                    <h6><i class="fas fa-paperclip"></i> Lampiran</h6>
                    <?php 
                    $ext = strtolower(pathinfo($feedback['attachment_url'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                    ?>
                    <a href="<?= BASE_URL . $feedback['attachment_url'] ?>" target="_blank">
                        <img src="<?= BASE_URL . $feedback['attachment_url'] ?>" alt="Attachment" class="attachment-preview" style="max-height: 400px;">
                    </a>
                    <?php else: ?>
                    <a href="<?= BASE_URL . $feedback['attachment_url'] ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-download"></i> Download File (<?= strtoupper($ext) ?>)
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Admin Notes -->
                <?php if ($feedback['admin_notes']): ?>
                <div class="mb-4">
                    <h6><i class="fas fa-comment-alt"></i> Catatan Admin</h6>
                    <div class="alert alert-info">
                        <?= nl2br(htmlspecialchars($feedback['admin_notes'] ?? '')) ?>
                        <?php if ($feedback['processor_nama']): ?>
                        <hr>
                        <small>
                            <i class="fas fa-user-check"></i> Diproses oleh: <?= htmlspecialchars($feedback['processor_nama'] ?? '') ?>
                            <?php if ($feedback['processed_at']): ?>
                            pada <?= date('d M Y H:i', strtotime($feedback['processed_at'])) ?>
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status History -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Riwayat Status
                </h5>
            </div>
            <div class="card-body">
                <div class="status-timeline">
                    <?php if (empty($statusHistory)): ?>
                    <p class="text-muted">Belum ada riwayat status</p>
                    <?php else: ?>
                    <?php foreach ($statusHistory as $history): ?>
                    <div class="timeline-item status-<?= $history['new_status'] ?>">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>
                                    <?php if ($history['old_status']): ?>
                                    <?= ucfirst(str_replace('_', ' ', $history['old_status'])) ?> 
                                    <i class="fas fa-arrow-right mx-1"></i>
                                    <?php endif; ?>
                                    <?= ucfirst(str_replace('_', ' ', $history['new_status'])) ?>
                                </strong>
                                <?php if ($history['notes']): ?>
                                <p class="mb-0 mt-1 text-muted"><?= htmlspecialchars($history['notes'] ?? '') ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <small class="text-muted">
                                    <?= date('d M Y H:i', strtotime($history['created_at'])) ?>
                                </small>
                                <?php if ($history['changed_by_nama']): ?>
                                <br><small class="text-muted">oleh <?= htmlspecialchars($history['changed_by_nama'] ?? '') ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Vote Section -->
        <div class="vote-section mb-3">
            <div class="vote-count-large"><?= $feedback['vote_count'] ?></div>
            <p class="mb-3">Dukungan</p>
            
            <?php if ($_SESSION['role'] === 'petugas' && !$isOwner): ?>
            <button type="button" 
                    class="btn btn-lg <?= $hasVoted ? 'btn-light' : 'btn-outline-light' ?>" 
                    id="voteBtn"
                    onclick="toggleVote()">
                <i class="fas fa-thumbs-up"></i>
                <?= $hasVoted ? 'Batalkan Vote' : 'Vote Masukan Ini' ?>
            </button>
            <?php else: ?>
            <small class="d-block mt-2 opacity-75">
                <i class="fas fa-info-circle"></i> Anda tidak dapat vote masukan sendiri
            </small>
            <?php endif; ?>
        </div>
        
        <!-- Voters List (Admin only) -->
        <?php if ($_SESSION['role'] === 'admin' && !empty($voters)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-users"></i> Daftar Voter</h6>
            </div>
            <div class="card-body p-0">
                <div class="voters-list">
                    <?php foreach ($voters as $voter): ?>
                    <div class="voter-item">
                        <i class="fas fa-user-circle fa-2x text-muted mr-2"></i>
                        <div>
                            <strong><?= htmlspecialchars($voter['nama_lengkap'] ?? '') ?></strong>
                            <br><small class="text-muted"><?= date('d M Y H:i', strtotime($voter['voted_at'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Admin Quick Actions -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="admin-actions">
            <h6><i class="fas fa-tools"></i> Aksi Admin</h6>
            <div class="btn-group-vertical w-100">
                <button class="btn btn-outline-warning mb-2" data-toggle="modal" data-target="#updateStatusModal">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                <button class="btn btn-outline-danger" onclick="deleteFeedback(<?= $feedback['id'] ?>)">
                    <i class="fas fa-trash"></i> Hapus Feedback
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal (Admin) -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>feedback/updateStatus/<?= $feedback['id'] ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Update Status</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status Baru</label>
                        <select name="status" class="form-control" required>
                            <option value="diterima" <?= $feedback['status'] === 'diterima' ? 'selected' : '' ?>>Diterima</option>
                            <option value="dalam_proses" <?= $feedback['status'] === 'dalam_proses' ? 'selected' : '' ?>>Dalam Proses</option>
                            <option value="selesai" <?= $feedback['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="ditolak" <?= $feedback['status'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan untuk user..."><?= htmlspecialchars($feedback['admin_notes'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleVote() {
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const feedbackId = <?= $feedback['id'] ?>;
    const btn = document.getElementById('voteBtn');
    
    btn.disabled = true;
    
    fetch('<?= BASE_URL ?>feedback/vote/' + feedbackId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update vote count
            document.querySelector('.vote-count-large').textContent = data.vote_count;
            
            // Update button
            if (data.action === 'added') {
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-light');
                btn.innerHTML = '<i class="fas fa-thumbs-up"></i> Batalkan Vote';
            } else {
                btn.classList.remove('btn-light');
                btn.classList.add('btn-outline-light');
                btn.innerHTML = '<i class="fas fa-thumbs-up"></i> Vote Masukan Ini';
            }
            
            toastr.success(data.message);
        } else {
            toastr.error(data.error || 'Gagal memproses vote');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan');
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function deleteFeedback(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus feedback ini?')) {
        return;
    }
    
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    
    fetch('<?= BASE_URL ?>feedback/delete/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success(data.message);
            setTimeout(() => {
                window.location.href = '<?= BASE_URL ?>feedback';
            }, 1000);
        } else {
            toastr.error(data.error || 'Gagal menghapus feedback');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan');
    });
}
</script>

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
