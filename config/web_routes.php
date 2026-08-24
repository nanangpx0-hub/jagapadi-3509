<?php
/**
 * JAGAPADI â€” Explicit Web Route Map
 *
 * Route web eksplisit (URL â†’ Controller@method). Route yang terdaftar di sini
 * dipakai persis seperti didefinisikan (tanpa konversi str_replace), sehingga
 * meminimalkan risiko collision nama route.
 *
 * Route yang TIDAK terdaftar di sini tetap dilayani oleh konvensi default
 * (Controller@method) di index.php sebagai fallback backward-compatible.
 *
 * Format: 'path' => 'ControllerName@method'  (ControllerName tanpa suffix "Controller")
 */

return [
    // Auth
    'login' => 'Auth@login',
    'logout' => 'Auth@logout',
    'auth/login' => 'Auth@login',
    'auth/do-login' => 'Auth@doLogin',
    'auth/logout' => 'Auth@logout',
    'auth/change-password' => 'Auth@changePassword',
    'auth/update-password' => 'Auth@updatePassword',
    'auth/forgot-password' => 'Auth@forgotPassword',

// Dashboard
'dashboard' => 'Dashboard@index',
    'dashboard/charts-lainnya' => 'Dashboard@chartsLainnya',
    'dashboard/map' => 'Dashboard@map',
    'dashboard/charts' => 'Dashboard@charts',
    'admin/health' => 'Health@index',
    // Alias Dashboard Padi: gunakan path bertanda hubung sebagai URL utama.
    // 'dashboardPadi' dipertahankan sebagai alias lama (case-insensitive) untuk
    // kompatibilitas & link lama/simpanan sebelumnya.
    'dashboard-padi' => 'DashboardPadi@index',
    'dashboardPadi' => 'DashboardPadi@index',

    // Laporan
    'laporan' => 'Laporan@index',
    'laporan/create' => 'Laporan@create',
    'laporan/store' => 'Laporan@store',
    'laporan/detail' => 'Laporan@detail',
    'laporan/fetch' => 'Laporan@fetch',
    'laporan/bulk-delete' => 'Laporan@bulkDelete',
    'laporan/hama' => 'LaporanHama@index',

    // Laporan Lainnya (web)
    'laporan-lainnya' => 'LaporanLainnya@index',
    'laporan-lainnya/create' => 'LaporanLainnya@create',
    'laporan-lainnya/store' => 'LaporanLainnya@store',
    'laporan-lainnya/summary' => 'LaporanLainnya@summary',
    'laporan-lainnya/report' => 'LaporanLainnya@report',
    'laporan-lainnya/export' => 'LaporanLainnya@export',
    'laporan-lainnya/bulk-delete' => 'LaporanLainnya@bulkDelete',
    'laporan-lainnya/delete-all' => 'LaporanLainnya@deleteAll',

    // Irigasi
    'irigasi' => 'Irigasi@index',
    'irigasi/create' => 'Irigasi@create',
    'irigasi/store' => 'Irigasi@store',
    'irigasi/monitoring' => 'Irigasi@monitoring',
    'irigasi/bulk-delete' => 'Irigasi@bulkDelete',

    // Recycle bin khusus Admin
    'recycle-bin' => 'RecycleBin@index',
    'recycle-bin/restore' => 'RecycleBin@restore',
    'recycle-bin/bulk-restore' => 'RecycleBin@bulkRestore',
    'recycle-bin/bulk-delete' => 'RecycleBin@bulkDelete',

    // Irigasi Scraper
    'irigasiScraper' => 'IrigasiScraper@index',
    'irigasiScraper/runScraper' => 'IrigasiScraper@runScraper',
    'irigasiScraper/export' => 'IrigasiScraper@export',

    // Cuaca / Lingkungan
    'curahHujan' => 'CurahHujan@index',
    'curahHujan/runScraper' => 'CurahHujan@runScraper',
    'curahHujan/getChartData' => 'CurahHujan@getChartData',
    'curahHujan/getStatistics' => 'CurahHujan@getStatistics',
    'curahHujan/export' => 'CurahHujan@export',
    'kecepatanAngin' => 'KecepatanAngin@index',
    'kecepatanAngin/runScraper' => 'KecepatanAngin@runScraper',
    'kecepatanAngin/getChartData' => 'KecepatanAngin@getChartData',
    'kecepatanAngin/getStatistics' => 'KecepatanAngin@getStatistics',
    'kecepatanAngin/export' => 'KecepatanAngin@export',
    'hargaKomoditas' => 'HargaKomoditas@index',
    'hargaKomoditas/runScraper' => 'HargaKomoditas@runScraper',
    'hargaKomoditas/getChartData' => 'HargaKomoditas@getChartData',
    'hargaKomoditas/getStatistics' => 'HargaKomoditas@getStatistics',
    'hargaKomoditas/export' => 'HargaKomoditas@export',

    // BPS
    'bpsScraper' => 'BpsScraper@index',
    'bpsScraper/runScraper' => 'BpsScraper@runScraper',
    'bpsScraper/runScraperBackground' => 'BpsScraper@runScraperBackground',
    'bpsScraper/getScraperStatus' => 'BpsScraper@getScraperStatus',
    'bpsScraper/getChartData' => 'BpsScraper@getChartData',
    'bpsScraper/getStatistics' => 'BpsScraper@getStatistics',
    'bpsScraper/getMonthlyHarvestArea' => 'BpsScraper@getMonthlyHarvestArea',
    'bpsScraper/getMonthlyHarvestChart' => 'BpsScraper@getMonthlyHarvestChart',
    'bpsScraper/export' => 'BpsScraper@export',

    // Analisis & Evaluasi
    'evaluasi' => 'Evaluasi@index',
    'storytelling' => 'Storytelling@index',
    'storytelling/generateAnalysis' => 'Storytelling@generateAnalysis',
    'storytelling/store' => 'Storytelling@store',
    'storytelling/getChartData' => 'Storytelling@getChartData',
    'storytelling/runMethod' => 'Storytelling@runMethod',
    'storytelling/getRecent' => 'Storytelling@getRecent',
    'storytelling/getAnalysis' => 'Storytelling@getAnalysis',
    'storytelling/publish' => 'Storytelling@publish',

    // Feedback
    'feedback' => 'Feedback@index',
    'feedback/create' => 'Feedback@create',
    'feedback/admin-summary' => 'Feedback@adminSummary',
    'feedback/report' => 'Feedback@report',

    // Master
    'opt' => 'Opt@index',
    'opttambahkan' => 'Opt@create',
    'opt/bulk-delete' => 'Opt@bulkDelete',
    'opt/delete-all' => 'Opt@deleteAll',
    'opt/auto-fill-photos' => 'Opt@autoFillPhotos',
    'usulan-opt' => 'UsulanOpt@index',
    'usulan-opt/create' => 'UsulanOpt@create',
    'usulan-opt/store' => 'UsulanOpt@store',
    'usulan-opt/update' => 'UsulanOpt@update',
    'usulan-opt/submit' => 'UsulanOpt@submit',
    'usulan-opt/resubmit' => 'UsulanOpt@resubmit',
    'usulan-opt/delete-draft' => 'UsulanOpt@deleteDraft',
    'usulan-opt/delete-photo' => 'UsulanOpt@deletePhoto',
    'usulan-opt/request-revision' => 'UsulanOpt@requestRevision',
    'usulan-opt/review' => 'UsulanOpt@review',
    'usulan-opt/approve-new' => 'UsulanOpt@approveNew',
    'usulan-opt/bulk-approve' => 'UsulanOpt@bulkApprove',
    'usulan-opt/search-master' => 'UsulanOpt@searchMaster',
    'usulan-opt/bulk-delete' => 'UsulanOpt@bulkDelete',
    'usulan-opt/import' => 'UsulanOpt@importExcel',
    'usulan-opt/export' => 'UsulanOpt@exportExcel',
    'usulan-opt/template' => 'UsulanOpt@downloadTemplate',
    // Alias kompatibilitas URL "OPT Saya".
    'optsaya' => 'UsulanOpt@index',
    'optsaya/import' => 'UsulanOpt@importExcel',
    'optsaya/export' => 'UsulanOpt@exportExcel',
    'optsaya/template' => 'UsulanOpt@downloadTemplate',
    'user' => 'User@index',
    'user/exportCsv' => 'User@exportCsv',
    'user/exportExcel' => 'User@exportExcel',
    'adminWilayah/kabupaten' => 'AdminWilayah@kabupaten',
    'adminWilayah/kecamatan' => 'AdminWilayah@kecamatan',
    'adminWilayah/desa' => 'AdminWilayah@desa',

    // Export
    'export/csv' => 'Export@csv',
    'export/excel' => 'Export@excel',
    'export/pdf' => 'Export@pdf',

    // Analisis hama
    'laporan-hama/analytics' => 'LaporanHama@analytics',

    // Usulan OPT â€” Petugas (form mandiri) & Admin (request revision)
    'usulan-opt/create' => 'UsulanOpt@create',
    'usulan-opt/store' => 'UsulanOpt@store',
    'usulan-opt/submit' => 'UsulanOpt@submit',
    'usulan-opt/resubmit' => 'UsulanOpt@resubmit',
    'usulan-opt/delete-draft' => 'UsulanOpt@deleteDraft',
    'usulan-opt/request-revision' => 'UsulanOpt@requestRevision',
    'usulan-opt/update' => 'UsulanOpt@update',
    // Konvensi parameterized (2 segmen): /usulan-opt/edit/{id} dan
    // /update/{id} dipetakan otomatis oleh fallback index.php;
    // submit/resubmit/delete-draft juga menerima {id} pada segmen URL.
];
