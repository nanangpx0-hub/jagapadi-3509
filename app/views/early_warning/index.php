<?php
$pageTitle = $title ?? 'Early Warning System — Peringatan Dini Hama';
require_once ROOT_PATH . '/app/views/layouts/header.php';

$alertCounts = $summary['alert_counts'] ?? ['Aman' => 0, 'Waspada' => 0, 'Bahaya' => 0];
$totalActive = $summary['total_active_alerts'] ?? 0;
?>

<!-- Custom Modern Styles for Early Warning System (EWS) -->
<style>
    .ews-hero-card {
        background: linear-gradient(135deg, #134e4a 0%, #065f46 50%, #047857 100%);
        color: #ffffff;
        border-radius: 14px;
        padding: 1.75rem 2rem;
        box-shadow: 0 10px 25px rgba(4, 120, 87, 0.2);
        margin-bottom: 1.75rem;
        position: relative;
        overflow: hidden;
    }
    .ews-hero-card::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 180px;
        height: 180px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 50%;
        pointer-events: none;
    }
    .ews-hero-badge {
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 30px;
        padding: 0.35rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .ews-pulse-dot {
        width: 10px;
        height: 10px;
        background-color: #ef4444;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        animation: ewsPulse 1.8s infinite;
    }
    .ews-pulse-dot.green {
        background-color: #10b981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    @keyframes ewsPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .kpi-ews-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.25rem 1.25rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: all 0.25s ease-in-out;
        height: 100%;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .kpi-ews-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 22px rgba(0, 0, 0, 0.08);
    }
    .kpi-ews-card.danger { border-top: 4px solid #ef4444; }
    .kpi-ews-card.warning { border-top: 4px solid #f59e0b; }
    .kpi-ews-card.success { border-top: 4px solid #10b981; }
    .kpi-ews-card.info { border-top: 4px solid #06b6d4; }
    .kpi-ews-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .kpi-ews-icon.danger { background: #fee2e2; color: #ef4444; }
    .kpi-ews-icon.warning { background: #fef3c7; color: #d97706; }
    .kpi-ews-icon.success { background: #d1fae5; color: #059669; }
    .kpi-ews-icon.info { background: #cffafe; color: #0891b2; }
    .kpi-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        margin: 0.35rem 0;
    }
    .ews-nav-tabs .nav-link {
        font-weight: 600;
        color: #4b5563;
        border-radius: 8px 8px 0 0;
        padding: 0.85rem 1.25rem;
        border: none;
        border-bottom: 3px solid transparent;
        background: transparent;
        transition: all 0.2s ease;
    }
    .ews-nav-tabs .nav-link.active {
        color: #047857;
        background: #ffffff;
        border-bottom: 3px solid #047857;
    }
    .ews-nav-tabs .nav-link:hover:not(.active) {
        color: #111827;
        background: #f3f4f6;
    }
    .dropzone-box {
        border: 2px dashed #9ca3af;
        border-radius: 12px;
        background: #f9fafb;
        padding: 2rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .dropzone-box:hover, .dropzone-box.dragover {
        border-color: #047857;
        background: #ecfdf5;
    }
    .pest-spec-card {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
        overflow: hidden;
    }
    .pest-spec-card:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }
    .pest-spec-header {
        padding: 1rem 1.25rem;
        font-weight: 700;
        color: #ffffff;
    }
    .timeline-ews {
        display: flex;
        justify-content: space-between;
        position: relative;
        margin: 1.5rem 0;
    }
    .timeline-ews::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 4px;
        background: #e5e7eb;
        transform: translateY(-50%);
        z-index: 1;
    }
    .timeline-step {
        position: relative;
        z-index: 2;
        background: #ffffff;
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
        border: 2px solid #e5e7eb;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
    }
    .timeline-step.active {
        border-color: #ef4444;
        background: #fee2e2;
        color: #991b1b;
    }
    .timeline-step.warning {
        border-color: #f59e0b;
        background: #fef3c7;
        color: #92400e;
    }
    .timeline-step.safe {
        border-color: #10b981;
        background: #d1fae5;
        color: #065f46;
    }
    .badge-filter {
        cursor: pointer;
        padding: 0.4rem 0.85rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-right: 0.35rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #4b5563;
        transition: all 0.15s ease;
    }
    .badge-filter.active {
        background: #047857;
        color: #ffffff;
        border-color: #047857;
    }
</style>

<!-- Hero Section Banner -->
<div class="ews-hero-card">
    <div class="row align-items-center">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="d-flex align-items-center mb-2">
                <span class="ews-pulse-dot <?= $alertCounts['Bahaya'] > 0 ? '' : 'green' ?>"></span>
                <span class="font-weight-bold text-uppercase tracking-wider" style="letter-spacing: 1px; font-size: 0.85rem;">
                    <?= $alertCounts['Bahaya'] > 0 ? 'Status Waspada Serangan Hama Aktif' : 'Status Ekosistem Terkendali' ?>
                </span>
            </div>
            <h2 class="font-weight-bold mb-2 text-white">
                <i class="fas fa-shield-virus mr-2 text-warning"></i>
                Early Warning System (EWS) Deteksi Dini Serangan Hama
            </h2>
            <p class="mb-3 text-light opacity-90" style="max-width: 720px; font-size: 0.95rem; line-height: 1.5;">
                Platform intelijen pertanian cerdas Kabupaten Jember yang mengintegrasikan Computer Vision, 
                stasiun cuaca agroklimat real-time, dan model pertumbuhan biometeorologis untuk mengantisipasi serangan hama 
                hingga <strong>72 jam</strong> sebelum ledakan populasi (outbreak) terjadi di lapangan.
            </p>
            <div class="d-flex flex-wrap">
                <span class="ews-hero-badge"><i class="fas fa-map-marker-alt mr-1"></i> 31 Kecamatan Terkoneksi</span>
                <span class="ews-hero-badge"><i class="fas fa-clock mr-1"></i> Lead Time 72 Jam</span>
                <span class="ews-hero-badge"><i class="fas fa-robot mr-1"></i> 4 Agen AI Terpadu</span>
                <span class="ews-hero-badge"><i class="fas fa-microscope mr-1"></i> Computer Vision HSV+Contour</span>
            </div>
        </div>
        <div class="col-lg-4 text-lg-right text-center">
            <button type="button" class="btn btn-warning btn-lg px-4 py-3 font-weight-bold shadow-lg" id="btnScanAll" style="border-radius: 10px;">
                <i class="fas fa-satellite-dish mr-2"></i> Pindai Serentak 31 Kecamatan
            </button>
            <div class="mt-2 text-light" style="font-size: 0.8rem; opacity: 0.85;">
                <i class="fas fa-info-circle mr-1"></i> Otomatis menghitung risiko seluruh kecamatan
            </div>
        </div>
    </div>
</div>

<!-- Summary KPI Cards -->
<div class="row mb-4">
    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="kpi-ews-card danger">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted font-weight-bold" style="font-size: 0.85rem;">STATUS BAHAYA</div>
                    <div class="kpi-number text-danger" id="kpiBahaya"><?= (int)$alertCounts['Bahaya'] ?></div>
                </div>
                <div class="kpi-ews-icon danger">
                    <i class="fas fa-biohazard"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <small class="text-muted">Lead Time: <strong>24-48 Jam</strong></small>
                <span class="badge badge-danger">Perlu Tindakan Cepat</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="kpi-ews-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted font-weight-bold" style="font-size: 0.85rem;">STATUS WASPADA</div>
                    <div class="kpi-number text-warning" id="kpiWaspada"><?= (int)$alertCounts['Waspada'] ?></div>
                </div>
                <div class="kpi-ews-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <small class="text-muted">Lead Time: <strong>48-72 Jam</strong></small>
                <span class="badge badge-warning text-dark font-weight-bold">Tingkatkan Pantauan</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="kpi-ews-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted font-weight-bold" style="font-size: 0.85rem;">STATUS AMAN</div>
                    <div class="kpi-number text-success" id="kpiAman"><?= (int)$alertCounts['Aman'] ?></div>
                </div>
                <div class="kpi-ews-icon success">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <small class="text-muted">Skor Risiko: <strong>&lt; 40.0</strong></small>
                <span class="badge badge-success">Kondisi Normal</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="kpi-ews-card info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted font-weight-bold" style="font-size: 0.85rem;">TOTAL KECAMATAN</div>
                    <div class="kpi-number text-info">31</div>
                </div>
                <div class="kpi-ews-icon info">
                    <i class="fas fa-broadcast-tower"></i>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <small class="text-muted">Target: <strong>Kabupaten Jember</strong></small>
                <span class="badge badge-info">100% Terliput</span>
            </div>
        </div>
    </div>
</div>

<!-- Main EWS Tabs Container -->
<div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-white p-2 border-bottom">
        <ul class="nav nav-tabs ews-nav-tabs border-0" id="ewsTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="radar-tab" data-toggle="tab" href="#radar-pane" role="tab">
                    <i class="fas fa-satellite-dish mr-2 text-danger"></i> Radar & Peringatan Dini Wilayah
                    <?php if ($totalActive > 0): ?>
                        <span class="badge badge-danger ml-1"><?= $totalActive ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="vision-tab" data-toggle="tab" href="#vision-pane" role="tab">
                    <i class="fas fa-camera mr-2 text-info"></i> Laboratorium Visual AI (Computer Vision)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="catalog-tab" data-toggle="tab" href="#catalog-pane" role="tab">
                    <i class="fas fa-bug mr-2 text-warning"></i> Profil Bioklimatik 5 Hama Utama
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="agent-tab" data-toggle="tab" href="#agent-pane" role="tab">
                    <i class="fas fa-robot mr-2 text-success"></i> Arsitektur Multi-Agent Orchestrator
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <div class="tab-content" id="ewsTabPanes">

            <!-- ==========================================
                 PANE 1: RADAR WILAYAH & PERINGATAN DINI
                 ========================================== -->
            <div class="tab-pane fade show active" id="radar-pane" role="tabpanel">
                
                <!-- Quick Assessment Form -->
                <div class="card bg-light border-0 shadow-none mb-4" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-calculator text-primary mr-2 fa-lg"></i>
                            <h6 class="font-weight-bold mb-0 text-dark">Simulasi & Hitung Risiko Cepat per Kecamatan</h6>
                        </div>
                        <form id="formAssess" class="row align-items-end">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="col-md-4 mb-2">
                                <label for="kecamatan_id" class="text-muted font-weight-bold" style="font-size: 0.85rem;">Kecamatan Sasaran:</label>
                                <select name="kecamatan_id" id="kecamatan_id" class="form-control" required style="border-radius: 8px;">
                                    <option value="">-- Pilih Salah Satu Kecamatan --</option>
                                    <?php foreach ($kecamatan_list as $k): ?>
                                        <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kecamatan']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="pest_code" class="text-muted font-weight-bold" style="font-size: 0.85rem;">Target Hama Prioritas:</label>
                                <select name="pest_code" id="pest_code" class="form-control" style="border-radius: 8px;">
                                    <option value="WBC">Wereng Batang Coklat (Nilaparvata lugens)</option>
                                    <option value="PBPK">Penggerek Batang Padi Kuning (Scirpophaga incertulas)</option>
                                    <option value="WALANG_SANGIT">Walang Sangit (Leptocorisa acuta)</option>
                                    <option value="ULAT_GRAYAK">Ulat Grayak (Spodoptera litura)</option>
                                    <option value="WDH">Wereng Daun Hijau (Nephotettix virescens)</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="checkbox" class="custom-control-input" id="auto_dispatch" name="auto_dispatch" value="1" checked>
                                    <label class="custom-control-label" for="auto_dispatch" style="font-size: 0.85rem;">Broadcast Alert</label>
                                </div>
                                <small class="text-muted d-block">Kirim notifikasi in-app/push</small>
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="submit" class="btn btn-primary btn-block font-weight-bold" style="border-radius: 8px; height: 38px;">
                                    <i class="fas fa-play mr-1"></i> Hitung Risiko
                                </button>
                            </div>
                        </form>
                        <div id="assessResultBox" class="mt-3 d-none"></div>
                    </div>
                </div>

                <!-- Table Active Alerts Header & Filters -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div class="mb-2 mb-md-0">
                        <h5 class="font-weight-bold text-dark mb-1">
                            <i class="fas fa-bell text-danger mr-2"></i> Peringatan Dini Aktif Lapangan
                        </h5>
                        <small class="text-muted">Data diurutkan berdasarkan tingkat risiko tertinggi dan waktu proyeksi</small>
                    </div>
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="btn-group mr-2 mb-2 mb-md-0" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary active filter-btn" data-status="all">Semua</button>
                            <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-status="Bahaya">Bahaya</button>
                            <button type="button" class="btn btn-sm btn-outline-warning filter-btn" data-status="Waspada">Waspada</button>
                            <button type="button" class="btn btn-sm btn-outline-success filter-btn" data-status="Aman">Aman</button>
                        </div>
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="text" id="searchAlertsInput" class="form-control" placeholder="Cari kecamatan/hama...">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Alerts Table -->
                <div class="table-responsive" style="border-radius: 10px; border: 1px solid #e5e7eb;">
                    <table class="table table-hover align-middle mb-0" id="tableAlerts">
                        <thead class="bg-light text-dark" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <tr>
                                <th style="width: 130px;">Kode Alert</th>
                                <th>Kecamatan</th>
                                <th>Hama Sasaran</th>
                                <th style="width: 120px;">Tingkat Risiko</th>
                                <th style="width: 140px;">Skor Risiko</th>
                                <th>Proyeksi Puncak (72h)</th>
                                <th>Lead Time</th>
                                <th>Distribusi</th>
                                <th class="text-right" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 0.9rem;">
                            <?php if (empty($recent_alerts)): ?>
                                <tr id="emptyRow">
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-shield-alt fa-3x mb-3 text-success d-block"></i>
                                        <h6 class="font-weight-bold text-dark">Tidak Ada Ancaman Serangan Hama Terkini</h6>
                                        <p class="mb-0 text-muted" style="font-size: 0.85rem;">Seluruh wilayah kecamatan di Kabupaten Jember dalam batas ambang aman.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_alerts as $alert): ?>
                                    <tr class="alert-row" data-status="<?= htmlspecialchars($alert['tingkat_risiko']) ?>" data-search="<?= strtolower(htmlspecialchars(($alert['nama_kecamatan'] ?? '') . ' ' . ($alert['nama_opt'] ?? '') . ' ' . $alert['alert_code'])) ?>">
                                        <td><code class="font-weight-bold text-dark"><?= htmlspecialchars($alert['alert_code']) ?></code></td>
                                        <td>
                                            <i class="fas fa-map-marker-alt text-danger mr-1"></i>
                                            <strong><?= htmlspecialchars($alert['nama_kecamatan'] ?? 'Jember') ?></strong>
                                        </td>
                                        <td>
                                            <span class="font-weight-bold text-dark"><?= htmlspecialchars($alert['nama_opt'] ?? 'OPT Padi') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($alert['tingkat_risiko'] === 'Bahaya'): ?>
                                                <span class="badge badge-danger px-2 py-1"><i class="fas fa-biohazard mr-1"></i> BAHAYA</span>
                                            <?php elseif ($alert['tingkat_risiko'] === 'Waspada'): ?>
                                                <span class="badge badge-warning px-2 py-1 text-dark font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> WASPADA</span>
                                            <?php else: ?>
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> AMAN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <strong class="mr-2"><?= number_format((float)$alert['skor_risiko'], 1) ?></strong>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <?php
                                                    $barColor = $alert['tingkat_risiko'] === 'Bahaya' ? 'bg-danger' : ($alert['tingkat_risiko'] === 'Waspada' ? 'bg-warning' : 'bg-success');
                                                    ?>
                                                    <div class="progress-bar <?= $barColor ?>" style="width: <?= min(100, (float)$alert['skor_risiko']) ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="far fa-calendar-alt text-muted mr-1"></i>
                                            <small><?= date('d M Y, H:i', strtotime($alert['prediksi_outbreak_at'])) ?> WIB</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary px-2 py-1">
                                                <i class="fas fa-hourglass-half mr-1"></i> <?= (int)$alert['lead_time_jam'] ?> Jam
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><i class="fas fa-broadcast-tower mr-1"></i> <?= htmlspecialchars($alert['saluran_distribusi'] ?? 'In-App') ?></small>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-sm btn-info btn-view-detail font-weight-bold" 
                                                    style="border-radius: 6px;"
                                                    data-alert='<?= htmlspecialchars(json_encode($alert), ENT_QUOTES, 'UTF-8') ?>'>
                                                <i class="fas fa-eye mr-1"></i> SOP PHT
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ==========================================
                 PANE 2: LABORATORIUM COMPUTER VISION
                 ========================================== -->
            <div class="tab-pane fade" id="vision-pane" role="tabpanel">
                <div class="row">
                    <div class="col-lg-5 mb-4 mb-lg-0">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #ffffff; border: 1px solid #e5e7eb;">
                            <div class="card-header bg-white border-bottom font-weight-bold text-dark">
                                <i class="fas fa-camera text-info mr-2"></i> Unggah Citra Hama / Gejala Daun
                            </div>
                            <div class="card-body p-4">
                                <form id="formVision" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-muted" style="font-size: 0.85rem;">Lokasi Pengamatan (Kecamatan):</label>
                                        <select name="kecamatan_id" id="visionKecamatanId" class="form-control" style="border-radius: 8px;">
                                            <option value="">-- Lokasi Acuan Cuaca (Opsional) --</option>
                                            <?php foreach ($kecamatan_list as $k): ?>
                                                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kecamatan']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Data cuaca kecamatan akan otomatis diintegrasikan dengan hasil citra</small>
                                    </div>

                                    <!-- Drag & Drop Zone -->
                                    <div class="dropzone-box mb-3" id="dropZone">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                        <div class="font-weight-bold text-dark">Tarik & Lepaskan File Foto di Sini</div>
                                        <small class="text-muted d-block mb-2">atau klik untuk memilih dari perangkat (JPG, PNG, WebP maks 5MB)</small>
                                        <input type="file" name="image" id="imageInput" class="d-none" accept="image/jpeg,image/png,image/webp">
                                        <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="document.getElementById('imageInput').click();">
                                            <i class="fas fa-folder-open mr-1"></i> Pilih Berkas
                                        </button>
                                    </div>

                                    <!-- Quick Sample Generator (1-Click Test) -->
                                    <div class="mb-3">
                                        <label class="font-weight-bold text-muted d-block" style="font-size: 0.82rem;">Atau Coba Contoh Cepat:</label>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-secondary" id="btnSampleWbc">
                                                <i class="fas fa-bug mr-1"></i> Sampel WBC
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btnSamplePbpk">
                                                <i class="fas fa-leaf mr-1"></i> Sampel PBPK
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btnSampleWalang">
                                                <i class="fas fa-spider mr-1"></i> Sampel Walang
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Preview Image -->
                                    <div class="text-center mb-3 d-none" id="previewContainer">
                                        <div class="position-relative d-inline-block">
                                            <img id="imagePreview" src="" alt="Pratinjau" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain;">
                                            <span id="fileNameBadge" class="badge badge-dark position-absolute" style="bottom: 8px; left: 8px; opacity: 0.9;"></span>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-info btn-block py-2 font-weight-bold shadow-sm" id="btnRunVision" style="border-radius: 8px;" disabled>
                                        <i class="fas fa-microscope mr-1"></i> Jalankan Deteksi & Klasifikasi AI
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Inference Results -->
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm" style="border-radius: 12px; background: #ffffff; border: 1px solid #e5e7eb; min-height: 480px;">
                            <div class="card-header bg-white border-bottom font-weight-bold text-dark d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-brain text-success mr-2"></i> Hasil Inferensi & Rekomendasi Terpadu</span>
                                <span class="badge badge-light border">Computer Vision v1.0</span>
                            </div>
                            <div class="card-body p-4" id="visionResultContainer">
                                <div class="text-center py-5 text-muted my-auto">
                                    <div class="mb-3">
                                        <i class="fas fa-robot fa-4x text-muted" style="opacity: 0.35;"></i>
                                    </div>
                                    <h5 class="font-weight-bold text-dark mb-2">Belum Ada Citra yang Dianalisis</h5>
                                    <p class="text-muted mx-auto" style="max-width: 420px; font-size: 0.9rem;">
                                        Pilih foto hama/daun dari perangkat Anda atau klik salah satu tombol sampel cepat di sebelah kiri untuk menjalankan model deteksi computer vision.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 PANE 3: PROFIL BIOKLIMATIK 5 HAMA UTAMA
                 ========================================== -->
            <div class="tab-pane fade" id="catalog-pane" role="tabpanel">
                <div class="mb-3">
                    <h5 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-book-open text-warning mr-2"></i> Ambang Batas Ekonomi & Standar Bioklimatik 5 Hama Utama
                    </h5>
                    <p class="text-muted" style="font-size: 0.9rem;">
                        Parameter ambang batas dan kondisi optimal perkembangbiakan OPT padi di Kabupaten Jember yang menjadi basis kalibrasi model inferensi AI JAGAPADI.
                    </p>
                </div>

                <div class="row">
                    <!-- WBC -->
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="pest-spec-card h-100">
                            <div class="pest-spec-header" style="background: linear-gradient(135deg, #b45309 0%, #d97706 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Wereng Batang Coklat</span>
                                    <span class="badge badge-light text-dark font-weight-bold">WBC</span>
                                </div>
                                <small class="text-light" style="font-style: italic;">Nilaparvata lugens</small>
                            </div>
                            <div class="p-3" style="font-size: 0.88rem;">
                                <div class="p-2 mb-3 bg-light rounded border">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-crosshairs text-danger mr-1"></i> Ambang Batas Ekonomi (EIL):</strong>
                                    <span class="text-muted">10 - 20 ekor / rumpun pada fase bunting hingga berbunga.</span>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Suhu Optimal</small>
                                            <strong class="text-dark">24.0°C - 29.5°C</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Kelembaban (RH)</small>
                                            <strong class="text-dark">&gt; 78% (Opt: 88%)</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted mb-2"><strong>Dampak Serangan:</strong> Menghisap cairan batang padi, menyebabkan tanaman kering terbakar (<em>hopperburn</em>) dan vektor kerdil hampa.</p>
                                <hr class="my-2">
                                <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Tindakan PHT:</small>
                                <small class="text-muted d-block">Keringkan petakan sawah secara berkala (intermitten irrigation), hindari overdosis pupuk urea, gunakan agen hayati <em>Beauveria bassiana</em>.</small>
                            </div>
                        </div>
                    </div>

                    <!-- PBPK -->
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="pest-spec-card h-100">
                            <div class="pest-spec-header" style="background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Penggerek Batang Kuning</span>
                                    <span class="badge badge-light text-dark font-weight-bold">PBPK</span>
                                </div>
                                <small class="text-light" style="font-style: italic;">Scirpophaga incertulas</small>
                            </div>
                            <div class="p-3" style="font-size: 0.88rem;">
                                <div class="p-2 mb-3 bg-light rounded border">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-crosshairs text-danger mr-1"></i> Ambang Batas Ekonomi (EIL):</strong>
                                    <span class="text-muted">1 kelompok telur / m² atau 5% anakan bergejala sundep/beluk.</span>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Suhu Optimal</small>
                                            <strong class="text-dark">22.0°C - 30.0°C</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Kelembaban (RH)</small>
                                            <strong class="text-dark">&gt; 80% (Opt: 90%)</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted mb-2"><strong>Dampak Serangan:</strong> Larva menggerek bagian dalam batang padi menimbulkan 'sundep' (fase vegetatif) dan 'beluk' (fase generatif).</p>
                                <hr class="my-2">
                                <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Tindakan PHT:</small>
                                <small class="text-muted d-block">Pemasangan perangkap lampu (light trap), pelepasan parasitoid telur <em>Trichogramma japonicum</em>, dan pemotongan ujung persemaian.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Walang Sangit -->
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="pest-spec-card h-100">
                            <div class="pest-spec-header" style="background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Walang Sangit</span>
                                    <span class="badge badge-light text-dark font-weight-bold">WALANG</span>
                                </div>
                                <small class="text-light" style="font-style: italic;">Leptocorisa acuta</small>
                            </div>
                            <div class="p-3" style="font-size: 0.88rem;">
                                <div class="p-2 mb-3 bg-light rounded border">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-crosshairs text-danger mr-1"></i> Ambang Batas Ekonomi (EIL):</strong>
                                    <span class="text-muted">&gt; 5 ekor / m² pada fase masak susu (milky stage).</span>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Suhu Optimal</small>
                                            <strong class="text-dark">25.0°C - 31.0°C</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Kelembaban (RH)</small>
                                            <strong class="text-dark">72% - 85%</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted mb-2"><strong>Dampak Serangan:</strong> Menghisap cairan bulir padi matang susu sehingga menyebabkan bulir hampa kempes atau bintik hitam.</p>
                                <hr class="my-2">
                                <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Tindakan PHT:</small>
                                <small class="text-muted d-block">Pemasangan perangkap bangkai kepiting/keong mas, penanaman serentak, dan sanitasi gulma rumput teki di sekitar pematang.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Ulat Grayak -->
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="pest-spec-card h-100">
                            <div class="pest-spec-header" style="background: linear-gradient(135deg, #475569 0%, #334155 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Ulat Grayak</span>
                                    <span class="badge badge-light text-dark font-weight-bold">GRAYAK</span>
                                </div>
                                <small class="text-light" style="font-style: italic;">Spodoptera litura</small>
                            </div>
                            <div class="p-3" style="font-size: 0.88rem;">
                                <div class="p-2 mb-3 bg-light rounded border">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-crosshairs text-danger mr-1"></i> Ambang Batas Ekonomi (EIL):</strong>
                                    <span class="text-muted">&gt; 2 ekor larva / rumpun atau kerusakan daun &gt; 12.5%.</span>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Suhu Optimal</small>
                                            <strong class="text-dark">26.0°C - 32.0°C</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Kelembaban (RH)</small>
                                            <strong class="text-dark">65% - 80%</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted mb-2"><strong>Dampak Serangan:</strong> Memakan helai daun secara massal dalam waktu singkat, menyisakan epidermis transparan.</p>
                                <hr class="my-2">
                                <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Tindakan PHT:</small>
                                <small class="text-muted d-block">Pemasangan feromon seks sintetis (Spodolure), aplikasi bioinsektisida virus SlNPV, serta penggenangan petak sawah sesaat.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Wereng Daun Hijau -->
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="pest-spec-card h-100">
                            <div class="pest-spec-header" style="background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Wereng Daun Hijau</span>
                                    <span class="badge badge-light text-dark font-weight-bold">WDH</span>
                                </div>
                                <small class="text-light" style="font-style: italic;">Nephotettix virescens</small>
                            </div>
                            <div class="p-3" style="font-size: 0.88rem;">
                                <div class="p-2 mb-3 bg-light rounded border">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-crosshairs text-danger mr-1"></i> Ambang Batas Ekonomi (EIL):</strong>
                                    <span class="text-muted">&gt; 5 ekor / rumpun (vektor utama virus Tungro).</span>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Suhu Optimal</small>
                                            <strong class="text-dark">23.0°C - 30.0°C</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block">Kelembaban (RH)</small>
                                            <strong class="text-dark">75% - 90%</strong>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted mb-2"><strong>Dampak Serangan:</strong> Menghisap cairan daun dan menularkan virus tungro yang membuat tanaman kerdil dan daun kuning jingga.</p>
                                <hr class="my-2">
                                <small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Tindakan PHT:</small>
                                <small class="text-muted d-block">Penanaman varietas tahan tungro (Inpari 36/37 Lanrang), tanam serempak, dan eradikasi selektif tanaman sakit.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 PANE 4: ARSITEKTUR 4-AGENT ORCHESTRATOR
                 ========================================== -->
            <div class="tab-pane fade" id="agent-pane" role="tabpanel">
                <div class="mb-4">
                    <h5 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-robot text-success mr-2"></i> Ekosistem Orkestrasi Multi-Agent AI JAGAPADI
                    </h5>
                    <p class="text-muted" style="font-size: 0.9rem;">
                        Seluruh alur kerja pemrosesan data, klasifikasi citra, estimasi lead time 72 jam, dan penyiapan SOP PHT dijalankan secara otonom oleh 4 agen AI terspesialisasi.
                    </p>
                </div>

                <div class="row">
                    <!-- Agent 1 -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="kpi-ews-icon info mr-2" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-0 text-dark">Agen 1: Collector</h6>
                                        <small class="text-muted">Data Aggregation</small>
                                    </div>
                                </div>
                                <p class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                                    Mengintegrasikan data sensor agroklimat (suhu, kelembaban, curah hujan), satelit cuaca, dan data historis 31 kecamatan di Jember secara real-time dengan imputasi spasial otomatis.
                                </p>
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Operasional Aktif</span>
                            </div>
                        </div>
                    </div>

                    <!-- Agent 2 -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="kpi-ews-icon info mr-2" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-0 text-dark">Agen 2: Model AI</h6>
                                        <small class="text-muted">Vision & Predictive</small>
                                    </div>
                                </div>
                                <p class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                                    Menjalankan computer vision berbasis ekstraksi HSV/kontur citra tanaman (akurasi &ge; 90%) serta model biometeorologis pertumbuhan populasi hama 24h, 48h, dan 72h.
                                </p>
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Model Terkalibrasi</span>
                            </div>
                        </div>
                    </div>

                    <!-- Agent 3 -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="kpi-ews-icon warning mr-2" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-0 text-dark">Agen 3: Assessor</h6>
                                        <small class="text-muted">Risk Assessment</small>
                                    </div>
                                </div>
                                <p class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                                    Menghitung skor risiko komposit (0-100), mengkategorikan status (Aman, Waspada, Bahaya), serta merumuskan paket rekomendasi PHT agronomis spesifik untuk petani.
                                </p>
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Standar FAO / Kementan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Agent 4 -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="kpi-ews-icon danger mr-2" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-0 text-dark">Agen 4: Distributor</h6>
                                        <small class="text-muted">Alert Notification</small>
                                    </div>
                                </div>
                                <p class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                                    Mendistribusikan peringatan dini otomatis ke saluran notifikasi in-app, push FCM mobile, dan pesan broadcast sebelum terjadi eskalasi serangan di lapangan.
                                </p>
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Multi-Channel Siaga</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==========================================
     MODAL DETAIL SOP PHT & TINDAKAN
     ========================================== -->
<div class="modal fade" id="modalDetailAlert" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header text-white" id="modalHeaderBg" style="background: #1f2937;">
                <h5 class="modal-title font-weight-bold" id="modalAlertTitle">
                    <i class="fas fa-shield-virus mr-2"></i> Detail Peringatan Dini & Rekomendasi PHT
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="modalAlertContent">
                <!-- Injected via JavaScript -->
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Logic for EWS Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?= BASE_URL ?>';
    const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

    // File input & Drag-and-drop handler
    const dropZone = document.getElementById('dropZone');
    const imageInput = document.getElementById('imageInput');
    const previewContainer = document.getElementById('previewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const fileNameBadge = document.getElementById('fileNameBadge');
    const btnRunVision = document.getElementById('btnRunVision');

    function showImageFile(file) {
        if (!file) return;
        fileNameBadge.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            previewContainer.classList.remove('d-none');
            btnRunVision.disabled = false;
        };
        reader.readAsDataURL(file);
    }

    if (imageInput) {
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                showImageFile(this.files[0]);
            }
        });
    }

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            }, false);
        });
        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                imageInput.files = files;
                showImageFile(files[0]);
            }
        });
    }

    // Helper to generate sample image canvas and set to input
    function createSampleImage(colorBg, textLabel, fileName) {
        const canvas = document.createElement('canvas');
        canvas.width = 400;
        canvas.height = 300;
        const ctx = canvas.getContext('2d');
        
        // Background
        ctx.fillStyle = colorBg;
        ctx.fillRect(0, 0, 400, 300);
        
        // Leaf stem pattern
        ctx.strokeStyle = '#1b4332';
        ctx.lineWidth = 12;
        ctx.beginPath();
        ctx.moveTo(200, 300);
        ctx.lineTo(200, 50);
        ctx.stroke();

        // Dots representing pest
        ctx.fillStyle = '#451a03';
        for (let i = 0; i < 15; i++) {
            ctx.beginPath();
            ctx.arc(180 + Math.random() * 40, 80 + Math.random() * 160, 6, 0, Math.PI * 2);
            ctx.fill();
        }

        // Label
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 18px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(textLabel, 200, 40);

        canvas.toBlob(function(blob) {
            const file = new File([blob], fileName, { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            imageInput.files = dataTransfer.files;
            showImageFile(file);
        }, 'image/jpeg');
    }

    document.getElementById('btnSampleWbc')?.addEventListener('click', function() {
        createSampleImage('#854d0e', 'Sampel Wereng Batang Coklat (WBC)', 'sampel_wereng_batang_coklat.jpg');
    });
    document.getElementById('btnSamplePbpk')?.addEventListener('click', function() {
        createSampleImage('#b91c1c', 'Sampel Penggerek Batang Kuning (PBPK)', 'sampel_penggerek_batang.jpg');
    });
    document.getElementById('btnSampleWalang')?.addEventListener('click', function() {
        createSampleImage('#0891b2', 'Sampel Walang Sangit Lapangan', 'sampel_walang_sangit.jpg');
    });

    // Form assess per kecamatan
    const formAssess = document.getElementById('formAssess');
    const assessResultBox = document.getElementById('assessResultBox');
    if (formAssess) {
        formAssess.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menghitung...';

            const formData = new FormData(this);
            fetch(baseUrl + 'earlyWarning/assess', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (data.success) {
                    const ra = data.data.risk_assessment;
                    const isBahaya = ra.tingkat_risiko === 'Bahaya';
                    const isWaspada = ra.tingkat_risiko === 'Waspada';
                    const badgeClass = isBahaya ? 'badge-danger' : (isWaspada ? 'badge-warning text-dark font-weight-bold' : 'badge-success');
                    const borderClass = isBahaya ? 'border-danger' : (isWaspada ? 'border-warning' : 'border-success');
                    
                    let html = `
                        <div class="card ${borderClass} shadow-sm mt-3" style="border-radius: 10px;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="font-weight-bold mb-0">Hasil Evaluasi Risiko: <span class="badge ${badgeClass} px-3 py-2 ml-1">${ra.tingkat_risiko} (${ra.skor_risiko}/100)</span></h6>
                                    <span class="badge badge-secondary p-2"><i class="fas fa-hourglass-half mr-1"></i> Lead Time: ${ra.lead_time_jam} Jam</span>
                                </div>
                                <p class="mb-2 text-dark font-weight-bold">${ra.ringkasan_ancaman}</p>

                                <div class="timeline-ews">
                                    <div class="timeline-step safe">Saat Ini: Baseline</div>
                                    <div class="timeline-step ${isWaspada || isBahaya ? 'warning' : 'safe'}">+24 Jam: Eskalasi Awal</div>
                                    <div class="timeline-step ${isBahaya ? 'warning' : 'safe'}">+48 Jam: Pembentukan Koloni</div>
                                    <div class="timeline-step ${isBahaya ? 'active' : 'safe'}">+72 Jam: Potensi Outbreak</div>
                                </div>

                                <div class="p-3 bg-light rounded mt-2">
                                    <strong class="text-dark d-block mb-1"><i class="fas fa-clipboard-list text-success mr-1"></i> Paket Rekomendasi PHT Segera:</strong>
                                    <ul class="mb-0 pl-3" style="font-size: 0.9rem;">
                                        ${ra.rekomendasi_penanganan.map(r => `<li class="mb-1">${r}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    assessResultBox.innerHTML = html;
                    assessResultBox.classList.remove('d-none');
                } else {
                    alert(data.message || 'Terjadi kesalahan asesmen.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Gagal menghubungi server.');
            });
        });
    }

    // Submit form vision
    const formVision = document.getElementById('formVision');
    const visionResultContainer = document.getElementById('visionResultContainer');
    if (formVision) {
        formVision.addEventListener('submit', function(e) {
            e.preventDefault();
            btnRunVision.disabled = true;
            btnRunVision.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menjalankan Inferensi AI...';

            const formData = new FormData(this);
            fetch(baseUrl + 'earlyWarning/detect', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btnRunVision.disabled = false;
                btnRunVision.innerHTML = '<i class="fas fa-microscope mr-1"></i> Jalankan Deteksi & Klasifikasi AI';
                if (data.success) {
                    const vr = data.data.vision_result;
                    const ra = data.data.risk_assessment;
                    const conf = parseFloat(vr.confidence_percentage);
                    
                    let html = `
                        <div class="alert alert-success d-flex align-items-center mb-3">
                            <i class="fas fa-check-circle fa-2x mr-3 text-success"></i>
                            <div>
                                <h6 class="font-weight-bold mb-0">Deteksi Visual Berhasil Diidentifikasi</h6>
                                <small>Model Computer Vision menyelesaikan segmentasi citra dalam hitungan detik</small>
                            </div>
                        </div>

                        <div class="card bg-light border-0 mb-3" style="border-radius: 10px;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="font-weight-bold text-dark mb-0">${vr.pest_name}</h5>
                                    <span class="badge badge-primary px-3 py-2 font-weight-bold">${vr.confidence_percentage}% Confidence</span>
                                </div>
                                <p class="text-muted mb-2"><em>Nama Ilmiah: ${vr.scientific_name}</em></p>
                                
                                <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${conf}%;"></div>
                                </div>

                                <div class="row text-center mb-2" style="font-size: 0.85rem;">
                                    <div class="col-4">
                                        <div class="p-2 bg-white rounded border">
                                            <span class="text-muted d-block">Ruang Warna</span>
                                            <strong>${vr.model_info.color_space}</strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-white rounded border">
                                            <span class="text-muted d-block">Filter Deteksi</span>
                                            <strong>${vr.model_info.filter_applied}</strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-white rounded border">
                                            <span class="text-muted d-block">Ambang Batas</span>
                                            <strong>${vr.model_info.threshold}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <strong class="text-dark d-block mb-2" style="font-size: 0.85rem;">Fitur Visual Teridentifikasi:</strong>
                                    <div>
                                        ${vr.detected_indicators.map(ind => `<span class="badge badge-info mr-1 mb-1 px-2 py-1"><i class="fas fa-tag mr-1"></i>${ind}</span>`).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    if (ra) {
                        const isBahaya = ra.tingkat_risiko === 'Bahaya';
                        const badgeRisk = isBahaya ? 'badge-danger' : (ra.tingkat_risiko === 'Waspada' ? 'badge-warning text-dark font-weight-bold' : 'badge-success');
                        html += `
                            <div class="card border-0 shadow-sm" style="border-radius: 10px; background: #fffbeb; border: 1px solid #fef3c7;">
                                <div class="card-body p-3">
                                    <h6 class="font-weight-bold text-dark mb-2">
                                        <i class="fas fa-satellite-dish text-warning mr-1"></i> Asesmen Risiko Terpadu (Fusi Cuaca & Lapangan):
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Status Wilayah: <span class="badge ${badgeRisk} p-2">${ra.tingkat_risiko} (${ra.skor_risiko}/100)</span></span>
                                        <span class="badge badge-secondary p-2">Lead Time: ${ra.lead_time_jam} Jam</span>
                                    </div>
                                    <p class="text-dark mb-2" style="font-size: 0.9rem;">${ra.ringkasan_ancaman}</p>
                                    <strong class="text-dark d-block mb-1" style="font-size: 0.85rem;">Rekomendasi Tindakan Segera:</strong>
                                    <ul class="mb-0 pl-3" style="font-size: 0.85rem;">
                                        ${ra.rekomendasi_penanganan.map(p => `<li>${p}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        `;
                    }

                    visionResultContainer.innerHTML = html;
                } else {
                    alert(data.message || 'Gagal menganalisis citra.');
                }
            })
            .catch(err => {
                btnRunVision.disabled = false;
                btnRunVision.innerHTML = '<i class="fas fa-microscope mr-1"></i> Jalankan Deteksi & Klasifikasi AI';
                alert('Gagal memproses unggahan gambar.');
            });
        });
    }

    // Scan All Kecamatan
    const btnScanAll = document.getElementById('btnScanAll');
    if (btnScanAll) {
        btnScanAll.addEventListener('click', function() {
            if (!confirm('Jalankan pemindaian serentak untuk seluruh 31 kecamatan di Kabupaten Jember? Sistem akan menganalisis sensor cuaca dan data historis secara otomatis.')) {
                return;
            }
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memindai Se-Jember (31 Kecamatan)...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('auto_dispatch', '1');

            fetch(baseUrl + 'earlyWarning/scanAll', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btnScanAll.disabled = false;
                btnScanAll.innerHTML = originalHtml;
                if (data.success) {
                    alert('Pemindaian 31 Kecamatan Berhasil!\n' + data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Gagal menjalankan pemindaian serentak.');
                }
            })
            .catch(err => {
                btnScanAll.disabled = false;
                btnScanAll.innerHTML = originalHtml;
                alert('Gagal menghubungi server.');
            });
        });
    }

    // Modal detail SOP PHT
    document.querySelectorAll('.btn-view-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = JSON.parse(this.getAttribute('data-alert'));
            const modalTitle = document.getElementById('modalAlertTitle');
            const modalContent = document.getElementById('modalAlertContent');
            const modalHeader = document.getElementById('modalHeaderBg');

            const isBahaya = alert.tingkat_risiko === 'Bahaya';
            const isWaspada = alert.tingkat_risiko === 'Waspada';
            modalHeader.style.background = isBahaya ? 'linear-gradient(135deg, #b91c1c 0%, #dc2626 100%)' : (isWaspada ? 'linear-gradient(135deg, #b45309 0%, #d97706 100%)' : 'linear-gradient(135deg, #15803d 0%, #16a34a 100%)');

            modalTitle.innerHTML = `<i class="fas fa-shield-virus mr-2"></i> [${alert.alert_code}] ${alert.nama_opt}`;

            modalContent.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <div class="p-3 bg-light rounded border">
                            <small class="text-muted d-block">Wilayah Kecamatan</small>
                            <h5 class="font-weight-bold text-dark mb-0"><i class="fas fa-map-marker-alt text-danger mr-1"></i> Kec. ${alert.nama_kecamatan}</h5>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="p-3 bg-light rounded border">
                            <small class="text-muted d-block">Status & Skor Risiko</small>
                            <h5 class="font-weight-bold mb-0">
                                <span class="badge ${isBahaya ? 'badge-danger' : (isWaspada ? 'badge-warning text-dark' : 'badge-success')} px-2 py-1">
                                    ${alert.tingkat_risiko} (${parseFloat(alert.skor_risiko).toFixed(1)}/100)
                                </span>
                            </h5>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning border-0 mb-3" style="border-radius: 10px;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong><i class="fas fa-exclamation-triangle mr-1"></i> Estimasi Waktu Outbreak:</strong>
                        <span class="badge badge-danger font-weight-bold">Lead Time: ${alert.lead_time_jam} Jam</span>
                    </div>
                    <div class="text-dark mb-0">${alert.prediksi_outbreak_at} WIB</div>
                    <hr class="my-2">
                    <div class="font-weight-bold text-dark">${alert.ringkasan_ancaman}</div>
                </div>

                <div class="card border-0 shadow-sm" style="border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <div class="card-header bg-transparent border-bottom font-weight-bold text-success">
                        <i class="fas fa-seedling mr-1"></i> SOP Pengendalian Hama Terpadu (PHT):
                    </div>
                    <div class="card-body p-3">
                        <pre style="white-space: pre-wrap; font-family: inherit; font-size: 0.92rem; margin-bottom: 0; color: #1f2937;">${alert.rekomendasi_penanganan}</pre>
                    </div>
                </div>
            `;

            $('#modalDetailAlert').modal('show');
        });
    });

    // Table Filter and Search
    const searchInput = document.getElementById('searchAlertsInput');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('#tableAlerts tbody tr.alert-row');

    function filterTable() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const activeBtn = document.querySelector('.filter-btn.active');
        const statusFilter = activeBtn ? activeBtn.getAttribute('data-status') : 'all';

        let visibleCount = 0;
        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowSearch = row.getAttribute('data-search') || '';

            const matchesStatus = (statusFilter === 'all') || (rowStatus === statusFilter);
            const matchesQuery = !query || rowSearch.includes(query);

            if (matchesStatus && matchesQuery) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterTable();
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/app/views/layouts/footer.php'; ?>
