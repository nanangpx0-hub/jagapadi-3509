<?php include ROOT_PATH . '/app/views/layouts/header.php'; ?>

<style>
/* ===== MASTER CHECKBOX STYLING ===== */
#checkAll {
    cursor: pointer;
    width: 20px;
    height: 20px;
    margin: 0;
    vertical-align: middle;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    border: 2px solid #6c757d;
    border-radius: 4px;
    background-color: #fff;
    position: relative;
    outline: none;
}

/* Master checkbox - Checked state (all selected) */
#checkAll:checked {
    background-color: #007bff;
    border-color: #007bff;
}

#checkAll:checked::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Master checkbox - Indeterminate state (partial selection) */
#checkAll:indeterminate {
    background-color: #17a2b8;
    border-color: #17a2b8;
}

#checkAll:indeterminate::after {
    content: '';
    position: absolute;
    left: 3px;
    top: 7px;
    width: 10px;
    height: 2px;
    background-color: white;
    transform: none;
}

/* Master checkbox - Hover effect DISABLED */
#checkAll:hover {
    /* border-color: #007bff; */
    /* box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25); */
}

#checkAll:focus {
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.35);
}

/* ===== CHILD CHECKBOX STYLING ===== */
.checkbox-item {
    cursor: pointer;
    width: 18px;
    height: 18px;
    margin: 0;
    vertical-align: middle;
}

/* ===== BULK DELETE BUTTON ===== */
#btnBulkDelete {
    display: none;
}

#btnBulkDelete.show {
    display: inline-block;
}

#btnBulkDelete:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ===== ROW SELECTION STYLING ===== */
.row-selected {
    background-color: #fff3cd !important;
    border-left: 3px solid #ffc107;
}

/* Row hover effect DISABLED */
tbody tr:hover {
    /* background-color: #f8f9fa !important; */
}

/* Loading state for bulk operations */
.bulk-loading {
    position: relative;
    overflow: hidden;
}

.bulk-loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Highlight rejected reports for petugas */
.rejected-report-row {
    background-color: #f8d7da !important;
    border-left: 4px solid #dc3545;
}

.rejected-report-row:hover {
    /* background-color: #f5c6cb !important; */
}

/* Rejected report actions styling */
.rejected-actions {
    background-color: #fff3cd;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ffeaa7;
}

.rejected-actions .btn {
    margin: 2px;
}

/* Mobile-specific rejected actions */
.rejected-actions-mobile {
    background-color: #fff3cd;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ffeaa7;
    margin-top: 0.5rem;
    text-align: center;
}

.rejected-actions-mobile .btn {
    margin: 2px;
    min-width: 80px;
}

/* Extra small buttons for rejected reports */
.btn-xs {
    padding: 2px 6px;
    font-size: 11px;
    line-height: 1.2;
    border-radius: 3px;
}

/* ===== FOTO THUMBNAIL STYLING ===== */
.photo-thumbnail-container {
    position: relative;
    display: inline-block;
    width: 100px;
    height: 100px;
}

.photo-thumbnail {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
    border: 2px solid #dee2e6;
    cursor: pointer;
    cursor: pointer;
    background-color: #f8f9fa;
    display: block;
}

.photo-thumbnail:hover {
    /* border-color: #007bff; */
    /* box-shadow: 0 4px 8px rgba(0,0,0,0.15); */
}

.photo-thumbnail.no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #e9ecef;
    color: #6c757d;
    font-size: 24px;
}

/* Photo Preview Modal */
.photo-preview-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    cursor: pointer;
    align-items: center;
    justify-content: center;
}

/* Show overlay when active */
.photo-preview-overlay.show {
    display: flex;
}

.photo-preview-image {
    max-width: 90%;
    max-height: 90%;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);

.photo-preview-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: #fff;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    font-weight: bold;
    cursor: pointer;
    z-index: 10000;
}

.photo-preview-close:hover {
    /* color: #ff6b6b; */
}

/* ===== TOMBOL AKSI STYLING ===== */
.btn-action-group {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-action {
    width: 32px;
    height: 32px;
    padding: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid transparent;
    font-size: 14px;
    line-height: 1;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    background-color: transparent;
    position: relative;
}

.btn-action i {
    font-size: 16px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Enhanced hover effects for action buttons */
.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Specific button styles with improved colors */
.btn-action-info {
    color: #17a2b8;
    background-color: rgba(23, 162, 184, 0.1);
}

.btn-action-info:hover {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-action-success {
    color: #28a745;
    background-color: rgba(40, 167, 69, 0.1);
}

.btn-action-success:hover {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}

.btn-action-warning {
    color: #ffc107;
    background-color: rgba(255, 193, 7, 0.1);
}

.btn-action-warning:hover {
    color: #212529;
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-action-danger {
    color: #dc3545;
    background-color: rgba(220, 53, 69, 0.1);
}

.btn-action-danger:hover {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}

/* Focus state for accessibility */
.btn-action:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* Active state */
.btn-action:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-action:hover {
    /* box-shadow: 0 2px 4px rgba(0,0,0,0.2); */
}



.btn-action:disabled,
.btn-action.disabled {
    opacity: 0.5;
    cursor: not-allowed !important;
    pointer-events: none;
}

.btn-action:disabled:hover,
.btn-action.disabled:hover {
    box-shadow: none;
}

.btn-action-info {
    background-color: #17a2b8;
    color: #fff;
}

.btn-action-info:hover {
    /* background-color: #138496; */
    color: #fff;
}

.btn-action-success {
    background-color: #28a745;
    color: #fff;
}

.btn-action-success:hover {
    /* background-color: #218838; */
    color: #fff;
}

.btn-action-warning {
    background-color: #ffc107;
    color: #212529;
}

.btn-action-warning:hover {
    /* background-color: #e0a800; */
    color: #212529;
}

.btn-action-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-action-danger:hover {
    /* background-color: #c82333; */
    color: #fff;
}

/* Tooltip styling */
.btn-action {
    position: relative;
}

/* Enhanced tooltip with better styling */
.btn-action::after {
    content: attr(title);
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background-color: #2c3e50;
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    pointer-events: none;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    border: 1px solid rgba(255,255,255,0.1);
}

.btn-action:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.btn-action::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 4px);
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #2c3e50;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
}

.btn-action:hover::before {
    opacity: 1;
    visibility: visible;
}


/* Loading optimization */
.photo-thumbnail {
    loading: lazy;
}

/* Mobile button group improvements */
@media (max-width: 575.98px) {
    .btn-group-horizontal-mobile {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        justify-content: center;
        align-items: center;
    }
    
    .btn-group-horizontal-mobile .btn {
        flex: 0 0 auto;
        min-width: 44px;
        min-height: 44px;
        margin: 0;
    }
    
    .rejected-actions-mobile {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .rejected-actions-mobile .btn {
        min-width: 44px;
        min-height: 44px;
        margin: 0.125rem;
    }
    
    /* Table cell content optimization for mobile */
    .table-responsive td[data-label="Lokasi"] div {
        margin-bottom: 0.125rem;
    }
    
    .table-responsive td[data-label="Status"] .badge {
        font-size: 0.75rem;
        padding: 0.25em 0.5em;
    }
    
    .table-responsive td[data-label="Pelapor"] div {
        margin-bottom: 0.125rem;
    }
    
    /* Photo thumbnail responsive */
    .photo-thumbnail-container {
        width: 80px;
        height: 80px;
    }
    
    .photo-thumbnail {
        width: 80px;
        height: 80px;
    }
    
    /* Action buttons responsive - optimized for mobile */
    .btn-action {
        width: 36px;
        height: 36px;
        padding: 8px;
        font-size: 16px;
    }
    
    .btn-action i {
        font-size: 18px;
        width: 24px;
        height: 24px;
    }
    
    /* Mobile tooltip positioning */
    .btn-action::after {
        bottom: calc(100% + 12px);
        font-size: 11px;
        padding: 4px 8px;
    }
    
    .btn-action::before {
        bottom: calc(100% + 4px);
        border-width: 4px;
    }
}

/* Responsive table adjustments */
@media (min-width: 576px) and (max-width: 991px) {
    /* Tablet adjustments */
    .btn-action {
        width: 34px;
        height: 34px;
        padding: 7px;
        font-size: 15px;
    }
    
    .btn-action i {
        font-size: 17px;
        width: 22px;
        height: 22px;
    }
    
    .photo-thumbnail-container {
        width: 75px;
        height: 75px;
    }
    
    .photo-thumbnail {
        width: 75px;
        height: 75px;
    }
}

@media (max-width: 768px) {
    .photo-thumbnail-container {
        width: 70px;
        height: 70px;
    }
    
    .photo-thumbnail {
        width: 70px;
        height: 70px;
    }
}

@media (min-width: 992px) {
    /* Desktop adjustments */
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 6px;
        font-size: 14px;
    }
    
    .btn-action i {
        font-size: 16px;
        width: 20px;
        height: 20px;
    }
    
    .photo-thumbnail-container {
        width: 100px;
        height: 100px;
    }
    
    .photo-thumbnail {
        width: 100px;
        height: 100px;
    }
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-alt"></i> Daftar Laporan Hama & Penyakit</h3>
                <div class="card-tools">
                    <?php if(($_SESSION['role'] ?? '') == 'admin'): ?>
                    <button id="btnBulkDelete" class="btn btn-danger btn-sm mr-2">
                        <i class="fas fa-trash"></i> Hapus Data Terpilih (<span id="selectedCount">0</span>)
                    </button>
                    <?php endif; ?>
                    <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'operator', 'petugas'])): ?>
                    <a href="<?= BASE_URL ?>laporan/create" 
                       id="btnCreateLaporan" 
                       class="btn btn-success btn-sm" 
                       role="button"
                       style="text-decoration: none !important; pointer-events: auto !important; cursor: pointer !important; display: inline-block !important;">
                        <i class="fas fa-plus"></i> Buat Laporan Baru
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- User Info Badge (for petugas) -->
                <?php if(($_SESSION['role'] ?? '') === 'petugas'): ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Mode Petugas:</strong> Anda hanya dapat melihat laporan yang Anda buat sendiri.
                    <span class="badge badge-primary ml-2">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($currentUser['nama_lengkap'] ?? '') ?>
                    </span>
                </div>
                
                <?php endif; ?>

                <!-- Filter Status dengan Efek Timbul-Tenggelam -->
                <div class="filter-status-container mb-3">
                    <div class="btn-group-status" role="group" aria-label="Filter Status">
                        <?php
                        // Hitung jumlah per status
                        $countAll = count($laporan);
                        $countDraft = 0;
                        $countActive = 0;
                        
                        foreach ($laporan as $item) {
                            switch ($item['status']) {
                                case 'Draf':
                                    $countDraft++;
                                    break;
                                case 'Submitted':
                                case 'Diverifikasi':
                                    $countActive++;
                                    break;
                            }
                        }
                        ?>
                        
                        <a href="<?= BASE_URL ?>laporan" 
                           class="btn-filter <?= empty($status) ? 'active' : '' ?>" 
                           data-filter="semua"
                           aria-pressed="<?= empty($status) ? 'true' : 'false' ?>">
                            <i class="fas fa-list"></i> Semua
                            <span class="badge badge-secondary"><?= $countAll ?></span>
                        </a>

                        <a href="<?= BASE_URL ?>laporan?status=Draf" 
                           class="btn-filter <?= ($status === 'Draf') ? 'active' : '' ?>" 
                           data-filter="draft"
                           aria-pressed="<?= ($status === 'Draf') ? 'true' : 'false' ?>">
                            <i class="fas fa-file"></i> Draf
                            <span class="badge badge-warning"><?= $countDraft ?></span>
                        </a>

                        <a href="<?= BASE_URL ?>laporan" 
                           class="btn-filter" 
                           data-filter="aktif"
                           aria-pressed="false">
                            <i class="fas fa-check-circle"></i> Aktif
                            <span class="badge badge-success"><?= $countActive ?></span>
                        </a>
                    </div>
                </div>

<!-- Table Toolbar -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" id="tableToolbar">
                    <!-- Per-page dropdown -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted small mb-0" for="perPageSelect">Tampilkan:</label>
                        <select id="perPageSelect" class="form-select form-select-sm" style="width:auto; min-width:90px;">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="all">Semua</option>
                        </select>
                        <span class="text-muted small" id="tableInfo">—</span>
                    </div>
                    <!-- Search -->
                    <div class="input-group input-group-sm" style="max-width:280px;">
                        <input type="text" id="tableSearch" class="form-control" placeholder="Cari laporan..." value="">
                        <button class="btn btn-outline-secondary" type="button" id="searchBtn" title="Cari">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Hapus filter" style="display:none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Table Scroll Hint for Mobile -->
                <div class="table-scroll-hint d-md-none">
                    <i class="fas fa-arrows-alt-h"></i>
                    <span>Geser ke kiri/kanan untuk melihat semua kolom</span>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="laporanTable">
                        <thead>
                            <tr>
                                <?php if(($_SESSION['role'] ?? '') == 'admin'): ?>
                                <th width="40">
                                    <input type="checkbox" id="checkAll" title="Pilih Semua">
                                </th>
                                <?php endif; ?>
                                <th data-sort="id" data-dir="desc" class="sortable">
                                    ID <i class="fas fa-sort fa-sm text-muted"></i>
                                </th>
                                <th>Foto</th>
                                <th data-sort="tanggal" data-dir="desc" class="sortable">
                                    Tanggal <i class="fas fa-sort fa-sm text-muted"></i>
                                </th>
                                <th>OPT</th>
                                <th>Lokasi</th>
                                <th data-sort="tingkat_keparahan" data-dir="asc" class="sortable">
                                    Keparahan <i class="fas fa-sort fa-sm text-muted"></i>
                                </th>
                                <th>Populasi</th>
                                <th data-sort="status" data-dir="asc" class="sortable">
                                    Status <i class="fas fa-sort fa-sm text-muted"></i>
                                </th>
                                <th>Pelapor</th>
                                <th data-sort="created_at" data-dir="desc" class="sortable">
                                    Dibuat <i class="fas fa-sort fa-sm text-muted"></i>
                                </th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"><!-- populated by AJAX --></tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" id="paginationControls">
                    <div class="text-muted small" id="paginationInfo"></div>
                    <nav id="paginationNav" aria-label="Table pagination"><!-- JS --></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(($_SESSION['role'] ?? '') == 'admin'): ?>
<script>
/**
 * Master Checkbox & Bulk Delete - Consolidated Implementation
 * Features:
 * - Check all / uncheck all functionality
 * - Indeterminate state for partial selection
 * - Row highlighting on selection
 * - Bulk delete with proper AJAX handling
 * - Event delegation for dynamic content support
 */
(function() {
    'use strict';
    
    // DOM Elements
    let checkAllElement = null;
    let bulkDeleteButton = null;
    let selectedCountElement = null;
    let tbody = null;
    tbody = document.querySelector('#tableBody');

    /**
     * Initialize bulk select functionality
     */
    function initBulkSelect() {
        // Get DOM elements
        checkAllElement = document.getElementById('checkAll');
        bulkDeleteButton = document.getElementById('btnBulkDelete');
        selectedCountElement = document.getElementById('selectedCount');
        tbody = document.querySelector('#laporanTable tbody');
        
        if (!checkAllElement) {
            console.log('[Checkbox] Master checkbox not found');
            return;
        }
        
        // Setup master checkbox handler
        setupMasterCheckbox();
        
        // Setup child checkbox handlers via event delegation
        setupChildCheckboxes();
        
        // Setup bulk delete handler
        setupBulkDeleteHandler();
        
        // Set initial state
        updateUI();
        
    console.log('[Checkbox] Bulk select initialized successfully');
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX Pagination Table
    // ─────────────────────────────────────────────────────────────────────
    const BASE_URL = '<?= rtrim(BASE_URL, '/') ?>/';
    let state = {
        page: 1,
        perPage: 10,
        search: '',
        status: '<?= $status ?>',
        sortCol: 'tanggal',
        sortDir: 'desc',
        total: 0,
        totalPages: 1,
        loading: false,
        abortController: null,
    };

    function qs(sel) { return document.querySelector(sel); }
    function qsa(sel) { return document.querySelectorAll(sel); }

    function buildURL() {
        const params = new URLSearchParams({
            page: state.page,
            per_page: state.perPage,
            search: state.search,
            status: state.status,
            sort_col: state.sortCol,
            sort_dir: state.sortDir,
        });
        return BASE_URL + 'laporan/fetch?' + params;
    }

    function showLoader() {
        const tbody = qs('#tableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-5"><div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem;"><span class="visually-hidden">Loading...</span></div><div class="mt-2 text-muted small">Memuat data...</div></td></tr>';
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function formatDate(d) {
        if (!d) return '—';
        try { return new Date(d).toLocaleDateString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric'}); }
        catch { return d; }
    }

    function severityBadge(k) {
        const map = {Berat:'danger', Sedang:'warning'};
        return `<span class="badge badge-${map[k]||'info'}">${escapeHtml(k)}</span>`;
    }

    function statusBadge(s) {
        if (s === 'Submitted' || s === 'Diverifikasi') {
            return `<span class="badge badge-success" title="Laporan aktif/masuk"><i class="fas fa-check-circle"></i> Aktif</span>`;
        }
        const map = {
            Diarsipkan:   { cls:'dark',     icon:'archive' },
            Ditolak:      { cls:'danger',   icon:'times-circle' },
            Draf:         { cls:'secondary',icon:'file' },
        };
        const cfg = map[s] || { cls:'secondary', icon:'file' };
        return `<span class="badge badge-${cfg.cls}" title="${escapeHtml(s)}"><i class="fas fa-${cfg.icon}"></i> ${escapeHtml(s)}</span>`;
    }

    function roleBadge(r) {
        const map = {admin:'danger', operator:'primary'};
        return `<span class="badge badge-${map[r]||'secondary'} badge-sm">${escapeHtml(r||'-')}</span>`;
    }

    function sortIcon(col) {
        if (state.sortCol !== col) return '<i class="fas fa-sort fa-sm text-muted"></i>';
        return state.sortDir === 'asc' ? '<i class="fas fa-sort-up fa-sm text-primary"></i>' : '<i class="fas fa-sort-down fa-sm text-primary"></i>';
    }

    function buildTableRow(r, idx) {
        const isAdmin = '<?= $_SESSION['role'] ?? '' ?>' === 'admin';
        const isOperator = '<?= $_SESSION['role'] ?? '' ?>' === 'operator';
        const isPetugas = '<?= $_SESSION['role'] ?? '' ?>' === 'petugas';
        const canEdit = isAdmin || isOperator || isPetugas;
        const canDelete = isAdmin || (isPetugas && (r.status === 'Draf' || r.status === 'Ditolak'));
        const foto = r.foto_url
            ? `<div class="photo-thumbnail-container"><img src="${BASE_URL}${r.foto_url}" alt="Foto" class="photo-thumbnail" data-full-image="${BASE_URL}${r.foto_url}" loading="lazy" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='<div class=\\'photo-thumbnail no-image\\'><i class=\\'fas fa-image\\'></i></div>';"></div>`
            : `<div class="photo-thumbnail-container"><div class="photo-thumbnail no-image"><i class="fas fa-image"></i></div></div>`;
        const etlWarn = (r.etl_acuan > 0 && r.populasi > r.etl_acuan) ? '<i class="fas fa-exclamation-triangle text-danger ms-1" title="Melampaui ETL"></i>' : '';

        const editBtn = canEdit ? `<a href="${BASE_URL}laporan/edit/${r.id}" class="btn-action btn-action-warning" title="Edit"><i class="fas fa-edit"></i></a>` : '';
        const archiveBtn = (isAdmin || isOperator) && r.status !== 'Diarsipkan' ? `
            <form action="${BASE_URL}laporan/archive/${r.id}" method="POST" class="d-inline">
                <?= Security::getCsrfField() ?>
                <button type="submit" class="btn-action btn-action-secondary" onclick="return confirm('Arsipkan laporan ini? Laporan tidak lagi dihitung sebagai laporan aktif.')" title="Arsipkan">
                    <i class="fas fa-archive"></i>
                </button>
            </form>` : '';

        const deleteBtn = canDelete ? `
            <form action="${BASE_URL}laporan/delete/${r.id}" method="POST" class="d-inline">
                <?= Security::getCsrfField() ?>
                <button type="submit" class="btn-action btn-action-danger" onclick="return confirm('Yakin ingin menghapus laporan ini?')" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </form>` : '';

        const checkbox = isAdmin ? `<td><input type="checkbox" class="checkbox-item" value="${r.id}"></td>` : '';

        const rejectNote = r.status === 'Ditolak' && r.catatan_verifikasi
            ? `<br><small class="text-danger"><i class="fas fa-comment"></i> ${escapeHtml(r.catatan_verifikasi.substring(0,30))}${r.catatan_verifikasi.length > 30 ? '...' : ''}</small>` : '';

        return `<tr>
            ${checkbox}
            <td><span class="badge badge-light">#${r.id}</span></td>
            <td>${foto}</td>
            <td>${formatDate(r.tanggal)}</td>
            <td><strong>${escapeHtml(r.nama_opt||'N/A')}</strong><br><small class="text-muted">${escapeHtml(r.jenis||'-')}</small></td>
            <td>Kab. Jember<br>Kec. ${escapeHtml(r.kecamatan||'-')}<br>Desa ${escapeHtml(r.desa||'-')}</td>
            <td>${severityBadge(r.tingkat_keparahan)}</td>
            <td>${r.populasi||0}${etlWarn}</td>
            <td>${statusBadge(r.status)}${rejectNote}</td>
            <td><strong>${escapeHtml((r.pelapor||'-').substring(0,15))}</strong><br><small class="text-muted">${roleBadge(r.pelapor_role)}</small></td>
            <td><small class="text-muted">${formatDate(r.created_at)}</small></td>
            <td>
                <div class="btn-action-group">
                    <a href="${BASE_URL}laporan/detail/${r.id}" class="btn-action btn-action-info" title="Lihat"><i class="fas fa-eye"></i></a>
                    ${editBtn}
                    ${archiveBtn}
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
    }

    function buildPaginationHTML() {
        const nav = qs('#paginationNav');
        if (!nav) return;
        if (state.totalPages <= 1) {
            nav.innerHTML = '';
            return;
        }
        const maxBtns = 5;
        let start = Math.max(1, state.page - 2);
        let end = Math.min(state.totalPages, start + maxBtns - 1);
        if (end - start < maxBtns - 1) start = Math.max(1, end - maxBtns + 1);

        let html = '<ul class="pagination mb-0">';
        html += `<li class="page-item ${state.page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${state.page - 1}" tabindex="-1"><i class="fas fa-chevron-left"></i></a>
        </li>`;
        for (let p = start; p <= end; p++) {
            html += `<li class="page-item ${p === state.page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${p}">${p}</a>
            </li>`;
        }
        html += `<li class="page-item ${state.page >= state.totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${state.page + 1}"><i class="fas fa-chevron-right"></i></a>
        </li>`;
        html += '</ul>';
        nav.innerHTML = html;
    }

    function updateStatusFilterCounts(statusCounts) {
        if (!statusCounts) return;
        const labels = ['Semua','Draf','Submitted','Diverifikasi','Ditolak'];
        const vals   = [null,'Draf','Submitted','Diverifikasi','Ditolak'];
        vals.forEach((v, i) => {
            const badge = document.querySelector(`.btn-filter[data-filter="${labels[i].toLowerCase()}"] .badge`);
            if (badge) badge.textContent = statusCounts[v] ?? 0;
        });
    }

    function updateSortHeaders() {
        qsa('#laporanTable thead th.sortable').forEach(th => {
            const col = th.dataset.sort;
            const dir = th.dataset.dir;
            const icon = sortIcon(col);
            th.innerHTML = th.textContent.trim().split('(')[0].trim() + ' ' + icon;
        });
    }

    function updateInfo() {
        const pp = qs('#perPageSelect');
        const ppLabel = pp && pp.value === 'all' ? 'semua' : (pp ? pp.value : state.perPage);
        qs('#tableInfo').textContent = `${state.total} total`;
        const from = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        const to = state.perPage < 0 ? state.total : Math.min(state.page * state.perPage, state.total);
        qs('#paginationInfo').textContent = state.total === 0
            ? 'Tidak ada data'
            : `Menampilkan ${from}–${to} dari ${state.total}`;
    }

    // ========== AJAX DATA LOADING ==========
    async function loadTable() {
        if (state.loading && state.abortController) {
            state.abortController.abort();
        }
        state.abortController = new AbortController();
        state.loading = true;
        showLoader();

        try {
            console.log('[LaporanTable] Fetching URL:', buildURL());
            const resp = await fetch(buildURL(), { signal: state.abortController.signal });
            console.log('[LaporanTable] Response status:', resp.status);
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const json = await resp.json();
            console.log('[LaporanTable] Response data:', json);
            if (!json.success) throw new Error(json.message || 'Gagal mengambil data');

            const d = json.data;
            state.total = d.total;
            state.totalPages = d.totalPages;
            state.page = d.page;

            const tbody = qs('#tableBody');
            if (!tbody) {
                console.error('[LaporanTable] tbody element not found!');
                return;
            }

            if (d.rows.length === 0) {
                // Determine correct colspan from table header
                const headerRow = qs('#laporanTable thead tr');
                const colCount = headerRow ? headerRow.children.length : 12;
                tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center text-muted py-4">Tidak ada data laporan</td></tr>`;
            } else {
                tbody.innerHTML = d.rows.map((r, i) => buildTableRow(r, i)).join('');
            }

            updateSortHeaders();
            updateInfo();
            buildPaginationHTML();
            updateStatusFilterCounts(d.statusCounts);

            if (typeof initBulkSelect === 'function') initBulkSelect();

        } catch (err) {
            console.error('[LaporanTable] Error:', err);
            if (err.name === 'AbortError') return;
            const tbody = qs('#tableBody');
            if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle"></i> Gagal memuat: ${escapeHtml(err.message)}</td></tr>`;
        } finally {
            state.loading = false;
        }
    }

    function setPage(p) {
        state.page = p;
        loadTable();
    }

    function setPerPage(v) {
        state.perPage = v;
        state.page = 1;
        loadTable();
    }

    function setSearch(q) {
        state.search = q;
        state.page = 1;
        loadTable();
    }

    function setStatus(s) {
        state.status = s;
        state.page = 1;
        loadTable();
    }

    function setSort(col) {
        if (state.sortCol === col) {
            state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            state.sortCol = col;
            state.sortDir = 'desc';
        }
        state.page = 1;
        loadTable();
    }

    // ========== BULK SELECT HELPER FUNCTIONS ==========
    function setupMasterCheckbox() {
        console.log('[BulkSelect] setupMasterCheckbox called');
        const checkAll = document.getElementById('checkAll');
        if (!checkAll) return;
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#laporanTable tbody input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateUI();
        });
    }

    function setupChildCheckboxes() {
        console.log('[BulkSelect] setupChildCheckboxes called');
        // Handled via event delegation on tbody
    }

    function setupBulkDeleteHandler() {
        console.log('[BulkSelect] setupBulkDeleteHandler called');
        // Handled via inline onclick in button
    }

    function updateUI() {
        const checkAll = document.getElementById('checkAll');
        const checkboxes = document.querySelectorAll('#laporanTable tbody input[type="checkbox"]');
        const selected = document.querySelectorAll('#laporanTable tbody input[type="checkbox"]:checked');
        const count = selected.length;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('btnBulkDelete').disabled = count === 0;
        // Indeterminate state
        if (checkAll) {
            checkAll.indeterminate = count > 0 && count < checkboxes.length;
            checkAll.checked = count === checkboxes.length && checkboxes.length > 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // INITIALIZATION
    // ─────────────────────────────────────────────────────────────────────

    // Start initialization after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadTable);
    } else {
        loadTable();
    }

    // Support for dynamic content updates
    window.addEventListener('laporanContentUpdated', function() {
        console.log('[Checkbox] Content updated, re-initializing...');
        initBulkSelect();
    });

})();
</script>
<?php endif; ?>

<script>
// Simple navigation fix for "Buat Laporan Baru" button
(function() {
    'use strict';
    
    var targetUrl = '<?= BASE_URL ?>laporan/create';
    
    function setupButton() {
        var btn = document.getElementById('btnCreateLaporan');
        if (!btn) return;
        
        // Ensure href is set correctly
        btn.setAttribute('href', targetUrl);
        
        // Remove any interfering attributes
        btn.removeAttribute('onclick');
        btn.removeAttribute('data-ajax');
        
        console.log('[Navigation] Button setup complete');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupButton);
    } else {
        setupButton();
    }
})();
</script>

<!-- Photo Preview Overlay -->
<div id="photoPreviewOverlay" class="photo-preview-overlay">
    <span class="photo-preview-close">&times;</span>
    <img id="photoPreviewImage" class="photo-preview-image" src="" alt="Preview Foto">
</div>

<script>
// Photo Preview Functionality
(function() {
    'use strict';
    
    const overlay = document.getElementById('photoPreviewOverlay');
    const previewImage = document.getElementById('photoPreviewImage');
    const closeBtn = document.querySelector('.photo-preview-close');
    const thumbnails = document.querySelectorAll('.photo-thumbnail');
    
    // Open preview
    function openPreview(imageSrc) {
        if (!imageSrc || imageSrc.includes('no-image')) return;
        
        previewImage.src = imageSrc;
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
    
    // Close preview
    function closePreview() {
        overlay.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
        previewImage.src = '';

    }
    
    // Attach click events to thumbnails
    thumbnails.forEach(thumbnail => {
        if (thumbnail.tagName === 'IMG' && thumbnail.dataset.fullImage) {
            thumbnail.addEventListener('click', function(e) {
                e.preventDefault();
                openPreview(this.dataset.fullImage);
            });
        }
    });
    
    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay || e.target === closeBtn) {
            closePreview();
        }
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('show')) {
            closePreview();
        }
    });
    
    // Prevent image click from closing (click on image should not close)
    previewImage.addEventListener('click', function(e) {
        e.stopPropagation();
    });
})();
</script>

<!-- Filter Status Enhancement Script -->
<script src="<?= BASE_URL ?>public/js/filter-status.js"></script>

<!-- CSS untuk Menonaktifkan Efek Hover -->
<link rel="stylesheet" href="<?= BASE_URL ?>public/css/hover-disabled.css">

<?php include ROOT_PATH . '/app/views/layouts/footer.php'; ?>
