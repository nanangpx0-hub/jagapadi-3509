<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\LaporanAlatSaranaValidator;
use App\Helpers\LaporanPolicy;
use App\Helpers\LaporanStatus;
use App\Helpers\NomorLaporanGenerator;
use App\Models\ActivityLog;
use App\Models\LaporanAlatSarana;
use App\Models\LaporanStatusHistory;
use App\Services\DashboardService;

class LaporanAlatSaranaService
{
    private const DRAFT_ALLOWED = [
        'tanggal', 'kabupaten_id', 'kecamatan_id', 'desa_id',
        'lokasi', 'alamat_lengkap', 'latitude', 'longitude',
        'jenis_sarana', 'nama_alat', 'jumlah', 'kondisi', 'foto_url', 'catatan',
    ];

    public static function createDraft(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanAlatSaranaValidator::validateDraft($input);
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

        $id = LaporanAlatSarana::insert($data);

        ActivityLog::log($userId, 'laporan_alat_sarana_draft_created', 'laporan_alat_sarana', (int) $id, 'Draf laporan alat sarana dibuat', $ip, $userAgent);

        $laporan = LaporanAlatSarana::findWithRelations((int) $id);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan alat sarana berhasil dibuat', 'data' => $laporan, 'code' => 201];
    }

    public static function updateDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        $denial = LaporanPolicy::editDenial($existing, $userId);
        if ($denial !== null) {
            return ['success' => false] + $denial;
        }

        $errors = LaporanAlatSaranaValidator::validateDraft($input);
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

        LaporanAlatSarana::update($id, $data);

        if ($existing['status'] === 'Draf') {
            ActivityLog::log($userId, 'laporan_alat_sarana_draft_updated', 'laporan_alat_sarana', $id, 'Draf laporan alat sarana diperbarui', $ip, $userAgent);
        } else {
            ActivityLog::log($userId, 'laporan_alat_sarana_draft_updated', 'laporan_alat_sarana', $id, 'Laporan alat sarana diperbarui sebelum dikirim ulang', $ip, $userAgent);
        }

        $laporan = LaporanAlatSarana::findWithRelations($id);

        $msg = $existing['status'] === 'Draf' ? 'Draf laporan alat sarana berhasil diperbarui' : 'Laporan alat sarana berhasil diperbarui';
        DashboardService::invalidateCache();
        return ['success' => true, 'message' => $msg, 'data' => $laporan, 'code' => 200];
    }

    public static function deleteDraft(int $id, int $userId, string $ip, string $userAgent): array
    {
        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dihapus.', 'code' => 409];
        }

        $deleted = LaporanAlatSarana::deleteDraft($id, $userId);
        if (!$deleted) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Gagal menghapus laporan.', 'code' => 409];
        }

        ActivityLog::log($userId, 'laporan_alat_sarana_draft_deleted', 'laporan_alat_sarana', $id, 'Draf laporan alat sarana dihapus', $ip, $userAgent);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan alat sarana berhasil dihapus', 'code' => 200];
    }

    public static function createAndSubmit(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanAlatSaranaValidator::validateSubmit($input);
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
            $nomor = NomorLaporanGenerator::generate('LAS', $tanggal);

            $data = self::whitelistDraftFields($input);
            $data['user_id'] = $userId;
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $nomor;
            $data['ip_pengirim'] = $ip;

            $id = LaporanAlatSarana::insert($data);

            ActivityLog::log($userId, 'laporan_alat_sarana_submitted', 'laporan_alat_sarana', (int) $id, 'Laporan alat sarana dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanAlatSarana::findWithRelations((int) $id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('alat-sarana', (int) $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan alat sarana berhasil dikirim', 'data' => $laporan, 'code' => 201];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function submitDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dikirim.', 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanAlatSaranaValidator::validateSubmit($merged);
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
            $nomor = NomorLaporanGenerator::generate('LAS', $tanggal);

            $updateData = ['status' => 'Submitted', 'nomor_laporan' => $nomor];
            foreach (self::DRAFT_ALLOWED as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $updateData[$field] = $input[$field];
                } elseif (isset($existing[$field]) && $existing[$field] !== null) {
                    $updateData[$field] = $existing[$field];
                }
            }

            LaporanAlatSarana::update($id, $updateData);

            ActivityLog::log($userId, 'laporan_alat_sarana_submitted', 'laporan_alat_sarana', $id, 'Laporan alat sarana dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanAlatSarana::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('alat-sarana', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan alat sarana berhasil dikirim', 'data' => $laporan, 'code' => 200];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetailForCurrentUser(int $id, array $currentUser): array
    {
        $laporan = LaporanAlatSarana::findAccessibleById($id, $currentUser);
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
            $result = LaporanAlatSarana::listForAdmin($queryFilters, $page, $limit);
        } else {
            $result = LaporanAlatSarana::listForPetugas((int) $currentUser['id'], $queryFilters, $page, $limit);
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
        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DIVERIFIKASI, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $catatanTrimmed = $catatan !== null ? trim($catatan) : null;

        LaporanAlatSarana::updateStatusAndVerification($id, LaporanStatus::DIVERIFIKASI, $adminId, $catatanTrimmed);

        $desc = 'Laporan alat sarana diverifikasi oleh admin';
        if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
            $desc .= ': ' . $catatanTrimmed;
        }
        ActivityLog::log($adminId, 'laporan_alat_sarana_verified', 'laporan_alat_sarana', $id, $desc, $ip, $userAgent);

        $laporan = LaporanAlatSarana::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutVerification('alat-sarana', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan alat sarana berhasil diverifikasi', 'data' => $laporan, 'code' => 200];
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

        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DITOLAK, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        LaporanAlatSarana::updateStatusAndVerification($id, LaporanStatus::DITOLAK, $adminId, $alasan);

        $desc = 'Laporan alat sarana ditolak oleh admin: ' . $alasan;
        ActivityLog::log($adminId, 'laporan_alat_sarana_rejected', 'laporan_alat_sarana', $id, $desc, $ip, $userAgent);

        $laporan = LaporanAlatSarana::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutRejection('alat-sarana', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '', $alasan);
        return ['success' => true, 'message' => 'Laporan alat sarana berhasil ditolak', 'data' => $laporan, 'code' => 200];
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
            $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
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

            LaporanAlatSarana::updateStatusAndVerification($id, LaporanStatus::DIARSIPKAN, $adminId, $catatanTrimmed);

            LaporanStatusHistory::record(
                $id,
                (string) $existing['status'],
                LaporanStatus::DIARSIPKAN,
                $adminId,
                $catatanTrimmed
            );

            $desc = 'Laporan alat sarana diarsipkan oleh admin';
            if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
                $desc .= ': ' . $catatanTrimmed;
            }
            ActivityLog::log($adminId, 'laporan_alat_sarana_archived', 'laporan_alat_sarana', $id, $desc, $ip, $userAgent);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $laporan = LaporanAlatSarana::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutArchive('alat-sarana', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan alat sarana berhasil diarsipkan', 'data' => $laporan, 'code' => 200];
    }

    public static function resubmit(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanAlatSarana::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::SUBMITTED, 'petugas');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanAlatSaranaValidator::validateSubmit($merged);
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

            LaporanAlatSarana::update($id, $updateData);
            LaporanAlatSarana::resetVerification($id);

            $nomor = $existing['nomor_laporan'] ?? '-';
            ActivityLog::log($userId, 'laporan_alat_sarana_resubmitted', 'laporan_alat_sarana', $id, 'Laporan alat sarana dikirim ulang: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanAlatSarana::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutResubmit('alat-sarana', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan alat sarana berhasil dikirim ulang', 'data' => $laporan, 'code' => 200];
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
