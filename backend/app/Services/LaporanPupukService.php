<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\LaporanPupukValidator;
use App\Helpers\LaporanStatus;
use App\Helpers\NomorLaporanGenerator;
use App\Models\ActivityLog;
use App\Models\LaporanPupuk;
use App\Models\LaporanStatusHistory;
use App\Services\DashboardService;

class LaporanPupukService
{
    private const DRAFT_ALLOWED = [
        'master_opt_id', 'tanggal', 'kabupaten_id', 'kecamatan_id', 'desa_id',
        'lokasi', 'alamat_lengkap', 'latitude', 'longitude',
        'jenis_pupuk', 'dosis', 'satuan_dosis', 'metode_aplikasi', 'foto_url', 'catatan',
    ];

    public static function createDraft(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanPupukValidator::validateDraft($input);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Data laporan tidak valid',
                'errors' => $errors,
                'code' => 422,
            ];
        }

        $data = self::whitelistDraftFields($input);
        $data['user_id'] = $userId;
        $data['status'] = 'Draf';
        $data['ip_pengirim'] = $ip;

        $id = LaporanPupuk::insert($data);

        ActivityLog::log($userId, 'laporan_pupuk_draft_created', 'laporan_pupuk', (int) $id, 'Draf laporan pupuk dibuat', $ip, $userAgent);

        $laporan = LaporanPupuk::findWithRelations((int) $id);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan pupuk berhasil dibuat', 'data' => $laporan, 'code' => 201];
    }

    public static function updateDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if (!LaporanStatus::isEditableByPetugas($existing['status'])) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Laporan dengan status ini tidak dapat diubah.', 'code' => 409];
        }

        $errors = LaporanPupukValidator::validateDraft($input);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Data laporan tidak valid',
                'errors' => $errors,
                'code' => 422,
            ];
        }

        $data = self::whitelistDraftFields($input);
        if (count($data) === 0) {
            return ['success' => true, 'message' => 'Tidak ada data yang diubah.', 'data' => $existing, 'code' => 200];
        }

        LaporanPupuk::update($id, $data);

        if ($existing['status'] === 'Draf') {
            ActivityLog::log($userId, 'laporan_pupuk_draft_updated', 'laporan_pupuk', $id, 'Draf laporan pupuk diperbarui', $ip, $userAgent);
        } else {
            ActivityLog::log($userId, 'laporan_pupuk_draft_updated', 'laporan_pupuk', $id, 'Laporan pupuk diperbarui sebelum dikirim ulang', $ip, $userAgent);
        }

        $laporan = LaporanPupuk::findWithRelations($id);

        $msg = $existing['status'] === 'Draf' ? 'Draf laporan pupuk berhasil diperbarui' : 'Laporan pupuk berhasil diperbarui';
        DashboardService::invalidateCache();
        return ['success' => true, 'message' => $msg, 'data' => $laporan, 'code' => 200];
    }

    public static function deleteDraft(int $id, int $userId, string $ip, string $userAgent): array
    {
        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dihapus.', 'code' => 409];
        }

        $deleted = LaporanPupuk::deleteDraft($id, $userId);
        if (!$deleted) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Gagal menghapus laporan.', 'code' => 409];
        }

        ActivityLog::log($userId, 'laporan_pupuk_draft_deleted', 'laporan_pupuk', $id, 'Draf laporan pupuk dihapus', $ip, $userAgent);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan pupuk berhasil dihapus', 'code' => 200];
    }

    public static function createAndSubmit(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanPupukValidator::validateSubmit($input);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Data laporan tidak valid',
                'errors' => $errors,
                'code' => 422,
            ];
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $tanggal = $input['tanggal'];
            $nomor = NomorLaporanGenerator::generate('LP', $tanggal);

            $data = self::whitelistDraftFields($input);
            $data['user_id'] = $userId;
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $nomor;
            $data['ip_pengirim'] = $ip;

            $id = LaporanPupuk::insert($data);

            ActivityLog::log($userId, 'laporan_pupuk_submitted', 'laporan_pupuk', (int) $id, 'Laporan pupuk dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPupuk::findWithRelations((int) $id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('pupuk', (int) $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan pupuk berhasil dikirim', 'data' => $laporan, 'code' => 201];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function submitDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dikirim.', 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanPupukValidator::validateSubmit($merged);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Data laporan tidak valid',
                'errors' => $errors,
                'code' => 422,
            ];
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $tanggal = $merged['tanggal'];
            $nomor = NomorLaporanGenerator::generate('LP', $tanggal);

            $updateData = ['status' => 'Submitted', 'nomor_laporan' => $nomor];
            foreach (self::DRAFT_ALLOWED as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $updateData[$field] = $input[$field];
                } elseif (isset($existing[$field]) && $existing[$field] !== null) {
                    $updateData[$field] = $existing[$field];
                }
            }

            LaporanPupuk::update($id, $updateData);

            ActivityLog::log($userId, 'laporan_pupuk_submitted', 'laporan_pupuk', $id, 'Laporan pupuk dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPupuk::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('pupuk', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan pupuk berhasil dikirim', 'data' => $laporan, 'code' => 200];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetailForCurrentUser(int $id, array $currentUser): array
    {
        $laporan = LaporanPupuk::findAccessibleById($id, $currentUser);
        if ($laporan === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        return ['success' => true, 'data' => $laporan, 'code' => 200];
    }

    public static function listForCurrentUser(array $currentUser, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));

        $role = strtolower((string) ($currentUser['role'] ?? ''));
        $isPetugas = $role === 'petugas';

        $includeDraft = isset($filters['include_draft'])
            ? filter_var($filters['include_draft'], FILTER_VALIDATE_BOOLEAN)
            : $isPetugas;

        $queryFilters = $filters;
        if (!$includeDraft && !isset($queryFilters['status'])) {
            $queryFilters['status'] = 'Submitted,Diverifikasi';
        }

        if ($role === 'admin') {
            $result = LaporanPupuk::listForAdmin($queryFilters, $page, $limit);
        } else {
            $result = LaporanPupuk::listForPetugas((int) $currentUser['id'], $queryFilters, $page, $limit);
        }

        $total = $result['total'];
        $lastPage = max(1, (int) ceil($total / $limit));

        return [
            'success' => true,
            'data' => $result['data'],
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $lastPage,
            ],
            'code' => 200,
        ];
    }

    public static function verify(int $id, int $adminId, ?string $catatan, string $ip, string $userAgent): array
    {
        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DIVERIFIKASI, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $catatanTrimmed = $catatan !== null ? trim($catatan) : null;

        LaporanPupuk::updateStatusAndVerification($id, LaporanStatus::DIVERIFIKASI, $adminId, $catatanTrimmed);

        $desc = 'Laporan pupuk diverifikasi oleh admin';
        if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
            $desc .= ': ' . $catatanTrimmed;
        }
        ActivityLog::log($adminId, 'laporan_pupuk_verified', 'laporan_pupuk', $id, $desc, $ip, $userAgent);

        $laporan = LaporanPupuk::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutVerification('pupuk', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan pupuk berhasil diverifikasi', 'data' => $laporan, 'code' => 200];
    }

    public static function reject(int $id, int $adminId, string $alasan, string $ip, string $userAgent): array
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 10) {
            return ['success' => false, 'error' => 'ValidationError', 'message' => 'Alasan penolakan minimal 10 karakter.', 'code' => 422];
        }
        if (mb_strlen($alasan) > 2000) {
            return ['success' => false, 'error' => 'ValidationError', 'message' => 'Alasan penolakan maksimal 2000 karakter.', 'code' => 422];
        }

        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DITOLAK, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        LaporanPupuk::updateStatusAndVerification($id, LaporanStatus::DITOLAK, $adminId, $alasan);

        $desc = 'Laporan pupuk ditolak oleh admin: ' . $alasan;
        ActivityLog::log($adminId, 'laporan_pupuk_rejected', 'laporan_pupuk', $id, $desc, $ip, $userAgent);

        $laporan = LaporanPupuk::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutRejection('pupuk', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '', $alasan);
        return ['success' => true, 'message' => 'Laporan pupuk berhasil ditolak', 'data' => $laporan, 'code' => 200];
    }

    public static function archive(int $id, int $adminId, ?string $catatan, string $ip, string $userAgent): array
    {
        $catatanTrimmed = $catatan !== null ? trim($catatan) : null;
        if ($catatanTrimmed !== null && mb_strlen($catatanTrimmed) > 2000) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Catatan pengarsipan maksimal 2000 karakter.',
                'code' => 422,
            ];
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $existing = LaporanPupuk::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
            if ($existing === null) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
            }

            try {
                LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DIARSIPKAN, 'admin');
            } catch (\DomainException $e) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
            }

            LaporanPupuk::updateStatusAndVerification($id, LaporanStatus::DIARSIPKAN, $adminId, $catatanTrimmed);

            LaporanStatusHistory::record(
                $id,
                (string) $existing['status'],
                LaporanStatus::DIARSIPKAN,
                $adminId,
                $catatanTrimmed
            );

            $desc = 'Laporan pupuk diarsipkan oleh admin';
            if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
                $desc .= ': ' . $catatanTrimmed;
            }
            ActivityLog::log($adminId, 'laporan_pupuk_archived', 'laporan_pupuk', $id, $desc, $ip, $userAgent);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $laporan = LaporanPupuk::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutArchive('pupuk', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan pupuk berhasil diarsipkan', 'data' => $laporan, 'code' => 200];
    }

    public static function resubmit(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPupuk::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::SUBMITTED, 'petugas');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanPupukValidator::validateSubmit($merged);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'error' => 'ValidationError',
                'message' => 'Data laporan tidak valid',
                'errors' => $errors,
                'code' => 422,
            ];
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $updateData = ['status' => LaporanStatus::SUBMITTED];
            foreach (self::DRAFT_ALLOWED as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $updateData[$field] = $input[$field];
                } elseif (isset($existing[$field]) && $existing[$field] !== null) {
                    $updateData[$field] = $existing[$field];
                }
            }

            LaporanPupuk::update($id, $updateData);
            LaporanPupuk::resetVerification($id);

            $nomor = $existing['nomor_laporan'] ?? '-';
            ActivityLog::log($userId, 'laporan_pupuk_resubmitted', 'laporan_pupuk', $id, 'Laporan pupuk dikirim ulang: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPupuk::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutResubmit('pupuk', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan pupuk berhasil dikirim ulang', 'data' => $laporan, 'code' => 200];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function notifyAdminsAboutSubmission(string $entity, int $laporanId, string $nomor, int $userId): void
    {
        try {
            $ns = new NotificationService();
            $ns->notifyAdmins(
                'laporan_submitted',
                'Laporan baru masuk',
                "{$nomor} menunggu verifikasi.",
                [
                    'entity' => $entity,
                    'laporan_id' => $laporanId,
                    'nomor_laporan' => $nomor,
                    'status' => 'Submitted',
                    'web_path' => "/laporan-{$entity}/{$laporanId}",
                    'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
                ],
                $userId
            );
        } catch (\Throwable $e) {
            Logger::warning('Notification failed on submit', ['error' => $e->getMessage()]);
        }
    }

    private static function notifyAdminsAboutResubmit(string $entity, int $laporanId, string $nomor, int $userId): void
    {
        try {
            $ns = new NotificationService();
            $ns->notifyAdmins(
                'laporan_resubmitted',
                'Laporan dikirim ulang',
                "{$nomor} dikirim ulang untuk verifikasi.",
                [
                    'entity' => $entity,
                    'laporan_id' => $laporanId,
                    'nomor_laporan' => $nomor,
                    'status' => 'Submitted',
                    'web_path' => "/laporan-{$entity}/{$laporanId}",
                    'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
                ],
                $userId
            );
        } catch (\Throwable $e) {
            Logger::warning('Notification failed on resubmit', ['error' => $e->getMessage()]);
        }
    }

    private static function notifyOwnerAboutVerification(string $entity, int $laporanId, int $ownerId, string $nomor): void
    {
        try {
            $ns = new NotificationService();
            $ns->notifyUser(
                $ownerId,
                'laporan_verified',
                'Laporan diverifikasi',
                "{$nomor} telah diverifikasi oleh admin.",
                [
                    'entity' => $entity,
                    'laporan_id' => $laporanId,
                    'nomor_laporan' => $nomor,
                    'status' => 'Diverifikasi',
                    'web_path' => "/laporan-{$entity}/{$laporanId}",
                    'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('Notification failed on verify', ['error' => $e->getMessage()]);
        }
    }

    private static function notifyOwnerAboutRejection(string $entity, int $laporanId, int $ownerId, string $nomor, string $alasan): void
    {
        try {
            $alasanTruncated = mb_strlen($alasan) > 120 ? mb_substr($alasan, 0, 117) . '...' : $alasan;
            $ns = new NotificationService();
            $ns->notifyUser(
                $ownerId,
                'laporan_rejected',
                'Laporan ditolak',
                "{$nomor}: {$alasanTruncated}",
                [
                    'entity' => $entity,
                    'laporan_id' => $laporanId,
                    'nomor_laporan' => $nomor,
                    'status' => 'Ditolak',
                    'web_path' => "/laporan-{$entity}/{$laporanId}",
                    'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('Notification failed on reject', ['error' => $e->getMessage()]);
        }
    }

    private static function notifyOwnerAboutArchive(string $entity, int $laporanId, int $ownerId, string $nomor): void
    {
        try {
            $ns = new NotificationService();
            $ns->notifyUser(
                $ownerId,
                'laporan_archived',
                'Laporan diarsipkan',
                "{$nomor} telah diarsipkan.",
                [
                    'entity' => $entity,
                    'laporan_id' => $laporanId,
                    'nomor_laporan' => $nomor,
                    'status' => 'Diarsipkan',
                    'web_path' => "/laporan-{$entity}/{$laporanId}",
                    'api_path' => "/api/v1/laporan-{$entity}/{$laporanId}",
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('Notification failed on archive', ['error' => $e->getMessage()]);
        }
    }

    private static function whitelistDraftFields(array $input): array
    {
        $data = [];
        foreach (self::DRAFT_ALLOWED as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $data[$field] = $input[$field];
            }
        }
        return $data;
    }
}
