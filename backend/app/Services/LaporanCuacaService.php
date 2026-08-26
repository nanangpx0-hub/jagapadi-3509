<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\LaporanCuacaValidator;
use App\Helpers\LaporanPolicy;
use App\Helpers\LaporanStatus;
use App\Helpers\NomorLaporanGenerator;
use App\Models\ActivityLog;
use App\Models\LaporanCuaca;
use App\Models\LaporanStatusHistory;
use App\Services\DashboardService;

class LaporanCuacaService
{
    private const DRAFT_ALLOWED = [
        'tanggal', 'kabupaten_id', 'kecamatan_id', 'desa_id',
        'lokasi', 'alamat_lengkap', 'latitude', 'longitude',
        'kondisi_cuaca', 'suhu', 'kelembaban', 'curah_hujan', 'kecepatan_angin', 'arah_angin',
        'foto_url', 'catatan',
    ];

    public static function createDraft(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanCuacaValidator::validateDraft($input);
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

        $id = LaporanCuaca::insert($data);

        ActivityLog::log($userId, 'laporan_cuaca_draft_created', 'laporan_cuaca', (int) $id, 'Draf laporan cuaca dibuat', $ip, $userAgent);

        $laporan = LaporanCuaca::findWithRelations((int) $id);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan cuaca berhasil dibuat', 'data' => $laporan, 'code' => 201];
    }

    public static function updateDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        $denial = LaporanPolicy::editDenial($existing, $userId);
        if ($denial !== null) {
            return ['success' => false] + $denial;
        }

        $errors = LaporanCuacaValidator::validateDraft($input);
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

        LaporanCuaca::update($id, $data);

        if ($existing['status'] === 'Draf') {
            ActivityLog::log($userId, 'laporan_cuaca_draft_updated', 'laporan_cuaca', $id, 'Draf laporan cuaca diperbarui', $ip, $userAgent);
        } else {
            ActivityLog::log($userId, 'laporan_cuaca_draft_updated', 'laporan_cuaca', $id, 'Laporan cuaca diperbarui sebelum dikirim ulang', $ip, $userAgent);
        }

        $laporan = LaporanCuaca::findWithRelations($id);

        $msg = $existing['status'] === 'Draf' ? 'Draf laporan cuaca berhasil diperbarui' : 'Laporan cuaca berhasil diperbarui';
        DashboardService::invalidateCache();
        return ['success' => true, 'message' => $msg, 'data' => $laporan, 'code' => 200];
    }

    public static function deleteDraft(int $id, int $userId, string $ip, string $userAgent): array
    {
        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dihapus.', 'code' => 409];
        }

        $deleted = LaporanCuaca::deleteDraft($id, $userId);
        if (!$deleted) {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Gagal menghapus laporan.', 'code' => 409];
        }

        ActivityLog::log($userId, 'laporan_cuaca_draft_deleted', 'laporan_cuaca', $id, 'Draf laporan cuaca dihapus', $ip, $userAgent);

        DashboardService::invalidateCache();
        return ['success' => true, 'message' => 'Draf laporan cuaca berhasil dihapus', 'code' => 200];
    }

    public static function createAndSubmit(int $userId, array $input, string $ip, string $userAgent): array
    {
        $errors = LaporanCuacaValidator::validateSubmit($input);
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
            $nomor = NomorLaporanGenerator::generate('LC', $tanggal);

            $data = self::whitelistDraftFields($input);
            $data['user_id'] = $userId;
            $data['status'] = 'Submitted';
            $data['nomor_laporan'] = $nomor;
            $data['ip_pengirim'] = $ip;

            $id = LaporanCuaca::insert($data);

            ActivityLog::log($userId, 'laporan_cuaca_submitted', 'laporan_cuaca', (int) $id, 'Laporan cuaca dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanCuaca::findWithRelations((int) $id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('cuaca', (int) $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan cuaca berhasil dikirim', 'data' => $laporan, 'code' => 201];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function submitDraft(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        if ($existing['status'] !== 'Draf') {
            return ['success' => false, 'error' => 'Conflict', 'message' => 'Hanya laporan dengan status Draf yang dapat dikirim.', 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanCuacaValidator::validateSubmit($merged);
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
            $nomor = NomorLaporanGenerator::generate('LC', $tanggal);

            $updateData = ['status' => 'Submitted', 'nomor_laporan' => $nomor];
            foreach (self::DRAFT_ALLOWED as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $updateData[$field] = $input[$field];
                } elseif (isset($existing[$field]) && $existing[$field] !== null) {
                    $updateData[$field] = $existing[$field];
                }
            }

            LaporanCuaca::update($id, $updateData);

            ActivityLog::log($userId, 'laporan_cuaca_submitted', 'laporan_cuaca', $id, 'Laporan cuaca dikirim: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanCuaca::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutSubmission('cuaca', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan cuaca berhasil dikirim', 'data' => $laporan, 'code' => 200];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getDetailForCurrentUser(int $id, array $currentUser): array
    {
        $laporan = LaporanCuaca::findAccessibleById($id, $currentUser);
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
            $result = LaporanCuaca::listForAdmin($queryFilters, $page, $limit);
        } else {
            $result = LaporanCuaca::listForPetugas((int) $currentUser['id'], $queryFilters, $page, $limit);
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
        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DIVERIFIKASI, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $catatanTrimmed = $catatan !== null ? trim($catatan) : null;

        LaporanCuaca::updateStatusAndVerification($id, LaporanStatus::DIVERIFIKASI, $adminId, $catatanTrimmed);

        $desc = 'Laporan cuaca diverifikasi oleh admin';
        if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
            $desc .= ': ' . $catatanTrimmed;
        }
        ActivityLog::log($adminId, 'laporan_cuaca_verified', 'laporan_cuaca', $id, $desc, $ip, $userAgent);

        $laporan = LaporanCuaca::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutVerification('cuaca', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan cuaca berhasil diverifikasi', 'data' => $laporan, 'code' => 200];
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

        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::DITOLAK, 'admin');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        LaporanCuaca::updateStatusAndVerification($id, LaporanStatus::DITOLAK, $adminId, $alasan);

        $desc = 'Laporan cuaca ditolak oleh admin: ' . $alasan;
        ActivityLog::log($adminId, 'laporan_cuaca_rejected', 'laporan_cuaca', $id, $desc, $ip, $userAgent);

        $laporan = LaporanCuaca::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutRejection('cuaca', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '', $alasan);
        return ['success' => true, 'message' => 'Laporan cuaca berhasil ditolak', 'data' => $laporan, 'code' => 200];
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
            $existing = LaporanCuaca::findAccessibleById($id, ['id' => $adminId, 'role' => 'admin']);
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

            LaporanCuaca::updateStatusAndVerification($id, LaporanStatus::DIARSIPKAN, $adminId, $catatanTrimmed);

            LaporanStatusHistory::record(
                $id,
                (string) $existing['status'],
                LaporanStatus::DIARSIPKAN,
                $adminId,
                $catatanTrimmed
            );

            $desc = 'Laporan cuaca diarsipkan oleh admin';
            if ($catatanTrimmed !== null && $catatanTrimmed !== '') {
                $desc .= ': ' . $catatanTrimmed;
            }
            ActivityLog::log($adminId, 'laporan_cuaca_archived', 'laporan_cuaca', $id, $desc, $ip, $userAgent);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $laporan = LaporanCuaca::findWithRelations($id);

        DashboardService::invalidateCache();
        self::notifyOwnerAboutArchive('cuaca', $id, (int) $laporan['user_id'], $laporan['nomor_laporan'] ?? '');
        return ['success' => true, 'message' => 'Laporan cuaca berhasil diarsipkan', 'data' => $laporan, 'code' => 200];
    }

    public static function resubmit(int $id, int $userId, array $input, string $ip, string $userAgent): array
    {
        $existing = LaporanCuaca::findAccessibleById($id, ['id' => $userId, 'role' => 'petugas']);
        if ($existing === null) {
            return ['success' => false, 'error' => 'NotFound', 'message' => 'Laporan tidak ditemukan.', 'code' => 404];
        }

        try {
            LaporanStatus::assertCanTransition($existing['status'], LaporanStatus::SUBMITTED, 'petugas');
        } catch (\DomainException $e) {
            return ['success' => false, 'error' => 'Conflict', 'message' => $e->getMessage(), 'code' => 409];
        }

        $merged = array_merge(array_filter($existing, fn($v) => $v !== null), $input);
        $errors = LaporanCuacaValidator::validateSubmit($merged);
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

            LaporanCuaca::update($id, $updateData);
            LaporanCuaca::resetVerification($id);

            $nomor = $existing['nomor_laporan'] ?? '-';
            ActivityLog::log($userId, 'laporan_cuaca_resubmitted', 'laporan_cuaca', $id, 'Laporan cuaca dikirim ulang: ' . $nomor, $ip, $userAgent);

            $pdo->commit();

            $laporan = LaporanCuaca::findWithRelations($id);

            DashboardService::invalidateCache();
            self::notifyAdminsAboutResubmit('cuaca', $id, $nomor, $userId);
            return ['success' => true, 'message' => 'Laporan cuaca berhasil dikirim ulang', 'data' => $laporan, 'code' => 200];
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
