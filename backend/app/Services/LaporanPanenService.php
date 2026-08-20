<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\LaporanPanenValidator;
use App\Helpers\LaporanStatus;
use App\Helpers\NomorLaporanGenerator;
use App\Models\ActivityLog;
use App\Models\LaporanPanen;
use App\Models\LaporanStatusHistory;
use App\Services\DashboardService;

class LaporanPanenService
{
    private const DRAFT_ALLOWED = [
        'master_opt_id', 'tanggal', 'kabupaten_id', 'kecamatan_id', 'desa_id',
        'lokasi', 'alamat_lengkap', 'latitude', 'longitude',
        'komoditas', 'luas_panen', 'volume_panen', 'satuan', 'harga_per_unit', 'foto_url', 'catatan',
    ];

    public static function createDraft(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanPanenValidator::validateDraft($input);
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

        $id = LaporanPanen::insert($data);

        ActivityLog::log($userId, 'laporan_panen_draft_created', 'laporan_panen', (int) $id, 'Draf laporan panen dibuat', $ip, $userAgent);

        $laporan = LaporanPanen::findWithRelations((int) $id);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan panen berhasil dibuat', 'data' => $laporan, 'code' => 201];
    }

    public static function updateDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPanen::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if (!LaporanStatus::isEditableByPetugas($existing['status'])) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Laporan dengan status ini tidak dapat diubah.', 'code' => 409];
        }

        $errors = LaporanPanenValidator::validateDraft($input);
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

        LaporanPanen::update($id, $data);

        if ($existing['status'] === 'Draf') {
            ActivityLog::log($userId, 'laporan_panen_draft_updated', 'laporan_panen', $id, 'Draf laporan panen diperbarui', $ip, $userAgent);
        } else {
            ActivityLog::log($userId, 'laporan_panen_draft_updated', 'laporan_panen', $id, 'Laporan panen diperbarui sebelum dikirim ulang', $ip, $userAgent);
        }

        $laporan = LaporanPanen::findWithRelations($id);

        $msg = $existing['status'] === 'Draf' ? 'Draf laporan panen berhasil diperbarui' : 'Laporan panen berhasil diperbarui';
        DashboardService::invalidateCache();
        return ['success' => true, 'message' => $msg, 'data' => $laporan, 'code' => 200];
    }

    public static function deleteDraft(int $id, int $userId, string $ip, string $userAgent): array
    {
        $existing = LaporanPanen::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dihapus.', 'code' => 409];
        }

        $deleted = LaporanPanen::deleteDraft($id, $userId);
        if (!$deleted) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Gagal menghapus laporan.', 'code' => 409];
        }

        ActivityLog::log($userId, 'laporan_panen_draft_deleted', 'laporan_panen', $id, 'Draf laporan panen dihapus', $ip, $userAgent);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan panen berhasil dihapus', 'code' => 200];
    }

    public static function createAndSubmit(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanPanenValidator::validateSubmit($input);
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
            $nomor = NomorLaporanGenerator::generate('LPA', $tanggal);

            $data = self::whitelistDraftFields($input);
            $data['user_id'] = $userId;
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $nomor;
            $data['ip_pengirim'] = $ip;

            $id = LaporanPanen::insert($data);

            ActivityLog::log($userId, 'laporan_panen_submitted', 'laporan_panen', (int) $id, 'Laporan panen dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPanen::findWithRelations((int) $id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('panen', (int) $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan panen berhasil dikirim', 'data' => $laporan, 'code' => 201];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function submitDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPanen::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dikirim.', 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanPanenValidator::validateSubmit($merged);
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
            $nomor = NomorLaporanGenerator::generate('LPA', $tanggal);

            $updateData = ['status' => 'Submitted', 'nomor_laporan' => $nomor];
            foreach (self::DRAFT_ALLOWED as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $updateData[$field] = $input[$field];
                } elseif (isset($existing[$field]) && $existing[$field] !== null) {
                    $updateData[$field] = $existing[$field];
                }
            }

            LaporanPanen::update($id, $updateData);

            ActivityLog::log($userId, 'laporan_panen_submitted', 'laporan_panen', $id, 'Laporan panen dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPanen::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('panen', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan panen berhasil dikirim', 'data' => $laporan, 'code' => 200];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetailForCurrentUser(int $id, array $currentUser): array
    {
        $laporan = LaporanPanen::findAccessibleById($id, $currentUser);
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
            $result = LaporanPanen::listForAdmin($queryFilters, $page, $limit);
        } else {
            $result = LaporanPanen::listForPetugas((int) $currentUser['id'], $queryFilters, $page, $limit);
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
        $existing = LaporanPanen::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DIVERIFIKASI, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $catatanTrimmed = $catatan !== null ? trim($catatan) : null;

        LaporanPanen::updateStatusAndVerification($id, LaporanStatus::DIVERIFIKASI, $adminId, $catatanTrimmed);

        $desc = 'Laporan panen diverifikasi oleh admin';
        if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
            $desc .= ': ' . $catatanTrimmed;
        }
        ActivityLog::log($adminId, 'laporan_panen_verified', 'laporan_panen', $id, $desc, $ip, $userAgent);

        $laporan = LaporanPanen::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutVerification('panen', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan panen berhasil diverifikasi', 'data' => $laporan, 'code' => 200];
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

        $existing = LaporanPanen::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DITOLAK, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        LaporanPanen::updateStatusAndVerification($id, LaporanStatus::DITOLAK, $adminId, $alasan);

        $desc = 'Laporan panen ditolak oleh admin: ' . $alasan;
        ActivityLog::log($adminId, 'laporan_panen_rejected', 'laporan_panen', $id, $desc, $ip, $userAgent);

        $laporan = LaporanPanen::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutRejection('panen', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '', $alasan);
        return ['success' => true, 'message' => 'Laporan panen berhasil ditolak', 'data' => $laporan, 'code' => 200];
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
            $existing = LaporanPanen::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
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

            LaporanPanen::updateStatusAndVerification($id, LaporanStatus::DIARSIPKAN, $adminId, $catatanTrimmed);

            LaporanStatusHistory::record(
                $id,
                (string) $existing['status'],
                LaporanStatus::DIARSIPKAN,
                $adminId,
                $catatanTrimmed
            );

            $desc = 'Laporan panen diarsipkan oleh admin';
            if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
                $desc .= ': ' . $catatanTrimmed;
            }
            ActivityLog::log($adminId, 'laporan_panen_archived', 'laporan_panen', $id, $desc, $ip, $userAgent);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $laporan = LaporanPanen::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutArchive('panen', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan panen berhasil diarsipkan', 'data' => $laporan, 'code' => 200];
    }

    public static function resubmit(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanPanen::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::SUBMITTED, 'petugas');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanPanenValidator::validateSubmit($merged);
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

            LaporanPanen::update($id, $updateData);
            LaporanPanen::resetVerification($id);

            $nomor = $existing['nomor_laporan'] ?? '-';
            ActivityLog::log($userId, 'laporan_panen_resubmitted', 'laporan_panen', $id, 'Laporan panen dikirim ulang: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanPanen::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutResubmit('panen', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan panen berhasil dikirim ulang', 'data' => $laporan, 'code' => 200];
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
