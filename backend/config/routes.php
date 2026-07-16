<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController as ApiAuthController;
use App\Controllers\Api\DeviceTokenController as ApiDeviceTokenController;
use App\Controllers\Api\DashboardController as ApiDashboardController;
use App\Controllers\Api\ExportController as ApiExportController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\MeController;
use App\Controllers\Api\NotificationController as ApiNotificationController;
use App\Controllers\Api\LaporanHamaController as ApiLaporanHamaController;
use App\Controllers\Api\LaporanIrigasiController as ApiLaporanIrigasiController;
use App\Controllers\Api\OptController as ApiOptController;
use App\Controllers\Api\WilayahController as ApiWilayahController;
use App\Controllers\Web\AuthController as WebAuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\ExportController as WebExportController;
use App\Controllers\Web\LaporanHamaController as WebLaporanHamaController;
use App\Controllers\Web\LaporanIrigasiController as WebLaporanIrigasiController;
use App\Controllers\Web\NotificationController as WebNotificationController;
use App\Controllers\Web\OptController as WebOptController;
use App\Controllers\Web\PasswordController;
use App\Controllers\Web\WilayahController as WebWilayahController;
use App\Middleware\AdminMiddleware;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\WebAuthMiddleware;

/** @var \App\Core\Router $router */

// Global middleware
$router->addGlobalMiddleware(CsrfMiddleware::class);
$router->addGlobalMiddleware(RateLimitMiddleware::class);

// ====================
// Web — Auth (public)
// ====================
$router->get('/login', [WebAuthController::class, 'showLoginForm']);
$router->post('/login', [WebAuthController::class, 'login']);
$router->post('/logout', [WebAuthController::class, 'logout']);

// ========================
// Web — Protected (session)
// ========================
$router->get('/dashboard', [DashboardController::class, 'index'], [WebAuthMiddleware::class]);
$router->get('/dashboard/stats.json', [DashboardController::class, 'statsJson'], [WebAuthMiddleware::class]);
$router->get('/dashboard/charts/hama.json', [DashboardController::class, 'chartsHamaJson'], [WebAuthMiddleware::class]);
$router->get('/dashboard/charts/irigasi.json', [DashboardController::class, 'chartsIrigasiJson'], [WebAuthMiddleware::class]);
$router->get('/dashboard/map/hama.json', [DashboardController::class, 'mapHamaJson'], [WebAuthMiddleware::class]);
$router->get('/dashboard/map/irigasi.json', [DashboardController::class, 'mapIrigasiJson'], [WebAuthMiddleware::class]);
$router->get('/password/change', [PasswordController::class, 'showChangeForm'], [WebAuthMiddleware::class]);
$router->post('/password/change', [PasswordController::class, 'change'], [WebAuthMiddleware::class]);

// ===========================
// =====================
// Web — Export (auth)
// =====================
$router->get('/export', [WebExportController::class, 'index'], [WebAuthMiddleware::class]);
$router->post('/export/hama', [WebExportController::class, 'exportHama'], [WebAuthMiddleware::class]);
$router->post('/export/irigasi', [WebExportController::class, 'exportIrigasi'], [WebAuthMiddleware::class]);

// ===========================
// Web — Admin only (wilayah)
// ===========================
$router->get('/admin', [DashboardController::class, 'index'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/wilayah', [WebWilayahController::class, 'index'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// Kabupaten
$router->get('/wilayah/kabupaten/create', [WebWilayahController::class, 'kabupatenCreate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kabupaten/store', [WebWilayahController::class, 'kabupatenStore'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/wilayah/kabupaten/edit/{id}', [WebWilayahController::class, 'kabupatenEdit'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kabupaten/update/{id}', [WebWilayahController::class, 'kabupatenUpdate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kabupaten/{id}/delete', [WebWilayahController::class, 'kabupatenDelete'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// Kecamatan
$router->get('/wilayah/kecamatan/create', [WebWilayahController::class, 'kecamatanCreate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kecamatan/store', [WebWilayahController::class, 'kecamatanStore'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/wilayah/kecamatan/edit/{id}', [WebWilayahController::class, 'kecamatanEdit'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kecamatan/update/{id}', [WebWilayahController::class, 'kecamatanUpdate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/kecamatan/{id}/delete', [WebWilayahController::class, 'kecamatanDelete'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// Desa
$router->get('/wilayah/desa/create', [WebWilayahController::class, 'desaCreate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/desa/store', [WebWilayahController::class, 'desaStore'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/wilayah/desa/edit/{id}', [WebWilayahController::class, 'desaEdit'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/desa/update/{id}', [WebWilayahController::class, 'desaUpdate'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/wilayah/desa/{id}/delete', [WebWilayahController::class, 'desaDelete'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// ======================
// Web — Admin only (OPT)
// ======================
$router->get('/opt', [WebOptController::class, 'index'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/opt/create', [WebOptController::class, 'create'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/opt/store', [WebOptController::class, 'store'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->get('/opt/{id}/edit', [WebOptController::class, 'edit'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/opt/update/{id}', [WebOptController::class, 'update'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/opt/{id}/delete', [WebOptController::class, 'delete'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/opt/{id}/foto/delete', [WebOptController::class, 'deleteFoto'], [WebAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/opt/{id}/foto', [WebOptController::class, 'uploadFoto'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// ===========================
// Web — Notifications (auth)
// ===========================
$router->get('/notifications', [WebNotificationController::class, 'index'], [WebAuthMiddleware::class]);
$router->get('/notifications/unread-count.json', [WebNotificationController::class, 'unreadCountJson'], [WebAuthMiddleware::class]);
$router->get('/notifications/recent.json', [WebNotificationController::class, 'recentJson'], [WebAuthMiddleware::class]);
$router->post('/notifications/{id}/read', [WebNotificationController::class, 'markRead'], [WebAuthMiddleware::class]);
$router->post('/notifications/read-all', [WebNotificationController::class, 'markAllRead'], [WebAuthMiddleware::class]);
$router->post('/notifications/{id}/delete', [WebNotificationController::class, 'delete'], [WebAuthMiddleware::class]);

// ====================
// API v1 — Public
// ====================
$router->get('/api/v1/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [ApiAuthController::class, 'login']);

// ============================
// API v1 — Protected (JWT)
// ============================
$router->get('/api/v1/export/hama', [ApiExportController::class, 'exportHama'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/export/irigasi', [ApiExportController::class, 'exportIrigasi'], [ApiAuthMiddleware::class]);

$router->get('/api/v1/me', [MeController::class, 'index'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/dashboard/stats', [ApiDashboardController::class, 'stats'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/dashboard/charts/hama', [ApiDashboardController::class, 'chartsHama'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/dashboard/charts/irigasi', [ApiDashboardController::class, 'chartsIrigasi'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/dashboard/map/hama', [ApiDashboardController::class, 'mapHama'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/dashboard/map/irigasi', [ApiDashboardController::class, 'mapIrigasi'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/refresh', [ApiAuthController::class, 'refresh'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/logout', [ApiAuthController::class, 'logout'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/change-password', [ApiAuthController::class, 'changePassword'], [ApiAuthMiddleware::class]);

// ================================
// API v1 — Notifications
// ================================
$router->get('/api/v1/notifications', [ApiNotificationController::class, 'index'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/notifications/unread-count', [ApiNotificationController::class, 'unreadCount'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/notifications/{id}/read', [ApiNotificationController::class, 'markRead'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/notifications/read-all', [ApiNotificationController::class, 'markAllRead'], [ApiAuthMiddleware::class]);
$router->delete('/api/v1/notifications/{id}', [ApiNotificationController::class, 'delete'], [ApiAuthMiddleware::class]);

// ================================
// API v1 — Wilayah (read: auth, write: admin)
// ================================
// Read — any authenticated user
$router->get('/api/v1/wilayah/kabupaten', [ApiWilayahController::class, 'listKabupaten'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/wilayah/kabupaten/{id}', [ApiWilayahController::class, 'getKabupaten'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/wilayah/kecamatan', [ApiWilayahController::class, 'listKecamatan'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/wilayah/kecamatan/{id}', [ApiWilayahController::class, 'getKecamatan'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/wilayah/desa', [ApiWilayahController::class, 'listDesa'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/wilayah/desa/{id}', [ApiWilayahController::class, 'getDesa'], [ApiAuthMiddleware::class]);

// Write — admin only
$router->post('/api/v1/wilayah/kabupaten', [ApiWilayahController::class, 'createKabupaten'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->put('/api/v1/wilayah/kabupaten/{id}', [ApiWilayahController::class, 'updateKabupaten'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->delete('/api/v1/wilayah/kabupaten/{id}', [ApiWilayahController::class, 'deleteKabupaten'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/api/v1/wilayah/kecamatan', [ApiWilayahController::class, 'createKecamatan'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->put('/api/v1/wilayah/kecamatan/{id}', [ApiWilayahController::class, 'updateKecamatan'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->delete('/api/v1/wilayah/kecamatan/{id}', [ApiWilayahController::class, 'deleteKecamatan'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/api/v1/wilayah/desa', [ApiWilayahController::class, 'createDesa'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->put('/api/v1/wilayah/desa/{id}', [ApiWilayahController::class, 'updateDesa'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->delete('/api/v1/wilayah/desa/{id}', [ApiWilayahController::class, 'deleteDesa'], [ApiAuthMiddleware::class, AdminMiddleware::class]);

// ================================
// API v1 — OPT (read: auth, write: admin)
// ================================
$router->get('/api/v1/opt', [ApiOptController::class, 'index'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/opt/{id}', [ApiOptController::class, 'show'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/opt', [ApiOptController::class, 'store'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->put('/api/v1/opt/{id}', [ApiOptController::class, 'update'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->delete('/api/v1/opt/{id}', [ApiOptController::class, 'destroy'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/api/v1/opt/{id}/foto/delete', [ApiOptController::class, 'deleteFoto'], [ApiAuthMiddleware::class, AdminMiddleware::class]);
$router->post('/api/v1/opt/{id}/foto', [ApiOptController::class, 'uploadFoto'], [ApiAuthMiddleware::class, AdminMiddleware::class]);

// ================================
// Web — Laporan Hama (petugas + admin)
// ================================
$router->get('/laporan-hama', [WebLaporanHamaController::class, 'index'], [WebAuthMiddleware::class]);
$router->get('/laporan-hama/create', [WebLaporanHamaController::class, 'create'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama', [WebLaporanHamaController::class, 'store'], [WebAuthMiddleware::class]);
$router->get('/laporan-hama/{id}', [WebLaporanHamaController::class, 'show'], [WebAuthMiddleware::class]);
$router->get('/laporan-hama/{id}/edit', [WebLaporanHamaController::class, 'edit'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}', [WebLaporanHamaController::class, 'update'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/submit', [WebLaporanHamaController::class, 'submit'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/delete', [WebLaporanHamaController::class, 'delete'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/verifikasi', [WebLaporanHamaController::class, 'verify'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/tolak', [WebLaporanHamaController::class, 'reject'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/archive', [WebLaporanHamaController::class, 'archive'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/resubmit', [WebLaporanHamaController::class, 'resubmit'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/foto/delete', [WebLaporanHamaController::class, 'deleteFoto'], [WebAuthMiddleware::class]);
$router->post('/laporan-hama/{id}/foto', [WebLaporanHamaController::class, 'uploadFoto'], [WebAuthMiddleware::class]);

// ================================
// API v1 — Laporan Hama
// ================================
$router->get('/api/v1/laporan-hama', [ApiLaporanHamaController::class, 'index'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama', [ApiLaporanHamaController::class, 'store'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/laporan-hama/{id}', [ApiLaporanHamaController::class, 'show'], [ApiAuthMiddleware::class]);
$router->put('/api/v1/laporan-hama/{id}', [ApiLaporanHamaController::class, 'update'], [ApiAuthMiddleware::class]);
$router->delete('/api/v1/laporan-hama/{id}', [ApiLaporanHamaController::class, 'destroy'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/submit', [ApiLaporanHamaController::class, 'submit'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/verifikasi', [ApiLaporanHamaController::class, 'verify'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/tolak', [ApiLaporanHamaController::class, 'reject'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/archive', [ApiLaporanHamaController::class, 'archive'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/resubmit', [ApiLaporanHamaController::class, 'resubmit'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/foto/delete', [ApiLaporanHamaController::class, 'deleteFoto'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-hama/{id}/foto', [ApiLaporanHamaController::class, 'uploadFoto'], [ApiAuthMiddleware::class]);

// ================================
// Web — Laporan Irigasi (petugas + admin)
// ================================
$router->get('/laporan-irigasi', [WebLaporanIrigasiController::class, 'index'], [WebAuthMiddleware::class]);
$router->get('/laporan-irigasi/create', [WebLaporanIrigasiController::class, 'create'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi', [WebLaporanIrigasiController::class, 'store'], [WebAuthMiddleware::class]);
$router->get('/laporan-irigasi/{id}', [WebLaporanIrigasiController::class, 'show'], [WebAuthMiddleware::class]);
$router->get('/laporan-irigasi/{id}/edit', [WebLaporanIrigasiController::class, 'edit'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}', [WebLaporanIrigasiController::class, 'update'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/submit', [WebLaporanIrigasiController::class, 'submit'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/delete', [WebLaporanIrigasiController::class, 'delete'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/verifikasi', [WebLaporanIrigasiController::class, 'verify'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/tolak', [WebLaporanIrigasiController::class, 'reject'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/archive', [WebLaporanIrigasiController::class, 'archive'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/resubmit', [WebLaporanIrigasiController::class, 'resubmit'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/foto/delete', [WebLaporanIrigasiController::class, 'deleteFoto'], [WebAuthMiddleware::class]);
$router->post('/laporan-irigasi/{id}/foto', [WebLaporanIrigasiController::class, 'uploadFoto'], [WebAuthMiddleware::class]);

// ================================
// API v1 — Laporan Irigasi
// ================================
$router->get('/api/v1/laporan-irigasi', [ApiLaporanIrigasiController::class, 'index'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi', [ApiLaporanIrigasiController::class, 'store'], [ApiAuthMiddleware::class]);
$router->get('/api/v1/laporan-irigasi/{id}', [ApiLaporanIrigasiController::class, 'show'], [ApiAuthMiddleware::class]);
$router->put('/api/v1/laporan-irigasi/{id}', [ApiLaporanIrigasiController::class, 'update'], [ApiAuthMiddleware::class]);
$router->delete('/api/v1/laporan-irigasi/{id}', [ApiLaporanIrigasiController::class, 'destroy'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/submit', [ApiLaporanIrigasiController::class, 'submit'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/verifikasi', [ApiLaporanIrigasiController::class, 'verify'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/tolak', [ApiLaporanIrigasiController::class, 'reject'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/archive', [ApiLaporanIrigasiController::class, 'archive'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/resubmit', [ApiLaporanIrigasiController::class, 'resubmit'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/foto/delete', [ApiLaporanIrigasiController::class, 'deleteFoto'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/laporan-irigasi/{id}/foto', [ApiLaporanIrigasiController::class, 'uploadFoto'], [ApiAuthMiddleware::class]);

// ================================
// API v1 — Device Tokens (JWT)
// ================================
$router->post('/api/v1/device-tokens', [ApiDeviceTokenController::class, 'store'], [ApiAuthMiddleware::class]);
$router->delete('/api/v1/device-tokens', [ApiDeviceTokenController::class, 'destroy'], [ApiAuthMiddleware::class]);
$router->delete('/api/v1/device-tokens/all', [ApiDeviceTokenController::class, 'destroyAll'], [ApiAuthMiddleware::class]);
