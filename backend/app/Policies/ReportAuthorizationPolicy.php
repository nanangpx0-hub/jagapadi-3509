<?php

declare(strict_types=1);

namespace App\Policies;

use App\Helpers\LaporanPolicy;

/**
 * Single source of truth otorisasi laporan Backend v1 (ADR-011).
 *
 * Memusatkan parsing role dan filter status yang sebelumnya tersebar
 * di 6 Service/Model laporan (Hama, Irigasi, Pupuk, Panen, Cuaca,
 * Alat/Sarana). Kelas ini murni (tanpa I/O database) agar dapat
 * diuji in-memory tanpa socket MySQL hidup.
 *
 * Aturan terkunci (AGENTS.md §3-§4, BLUEPRINT §4/§6):
 * - Petugas: owner-scoped via session/JWT id; Draf default terlihat
 *   hanya miliknya sendiri (IDOR-safe via query + re-check).
 * - Statistisi/Operator/Viewer: scope global read-only analitik, hanya
 *   status resmi Submitted + Diverifikasi; `include_draft=true` diabaikan.
 * - Admin: akses global hanya bermakna pada rute administratif eksplisit
 *   (dijaga AdminMiddleware per-route); pada level policy Admin melihat
 *   semua status bila `include_draft=true`, default hanya resmi.
 */
final class ReportAuthorizationPolicy
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PETUGAS = 'petugas';
    public const ROLE_STATISTISI = 'statistisi';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_VIEWER = 'viewer';

    /** Status resmi yang boleh dikonsumsi agregat/analitik. */
    public const OFFICIAL_STATUSES = ['Submitted', 'Diverifikasi'];

    /** @var list<string> */
    private const KNOWN_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_PETUGAS,
        self::ROLE_STATISTISI,
        self::ROLE_OPERATOR,
        self::ROLE_VIEWER,
    ];

    /**
     * Normalisasi role kiriman session/JWT menjadi huruf kecil.
     * Role tak dikenal di-fail-closed-kan menjadi 'viewer'.
     */
    public static function normalizeRole(mixed $role): string
    {
        $normalized = strtolower(trim((string) $role));
        if (in_array($normalized, self::KNOWN_ROLES, true)) {
            return $normalized;
        }
        return self::ROLE_VIEWER;
    }

    public static function isPetugas(array $currentUser): bool
    {
        return self::normalizeRole($currentUser['role'] ?? '') === self::ROLE_PETUGAS;
    }

    public static function isAdmin(array $currentUser): bool
    {
        return self::normalizeRole($currentUser['role'] ?? '') === self::ROLE_ADMIN;
    }

    public static function isStatistisi(array $currentUser): bool
    {
        return self::normalizeRole($currentUser['role'] ?? '') === self::ROLE_STATISTISI;
    }

    public static function isOperator(array $currentUser): bool
    {
        return self::normalizeRole($currentUser['role'] ?? '') === self::ROLE_OPERATOR;
    }

    public static function isViewer(array $currentUser): bool
    {
        return self::normalizeRole($currentUser['role'] ?? '') === self::ROLE_VIEWER;
    }

    /**
     * Role analitik baca-saja: statistisi, operator, viewer (dan role
     * tak dikenal yang di-fail-closed-kan menjadi viewer).
     */
    public static function isReadOnlyAnalytics(array $currentUser): bool
    {
        $role = self::normalizeRole($currentUser['role'] ?? '');
        return $role === self::ROLE_STATISTISI
            || $role === self::ROLE_OPERATOR
            || $role === self::ROLE_VIEWER;
    }

    /**
     * Apakah flag include_draft efektif untuk user + filter?
     * Statistisi/operator/viewer selalu false (abaikan permintaan client).
     */
    public static function shouldIncludeDraft(array $currentUser, array $filters): bool
    {
        if (self::isReadOnlyAnalytics($currentUser)) {
            return false;
        }
        if (isset($filters['include_draft'])) {
            return filter_var($filters['include_draft'], FILTER_VALIDATE_BOOLEAN);
        }
        return self::isPetugas($currentUser);
    }

    /**
     * Scope daftar laporan untuk service.
     *
     * @param array<string,mixed> $currentUser ['id'=>int,'role'=>string]
     * @param array<string,mixed> $filters filter mentah dari query string
     * @return array{mode:string,ownerId:?int,queryFilters:array<string,mixed>,includeDraft:bool}
     *   mode: 'petugas' (owner-scoped) | 'admin' (global) | 'official' (global resmi)
     */
    public static function resolveListScope(array $currentUser, array $filters): array
    {
        $role = self::normalizeRole($currentUser['role'] ?? '');
        $queryFilters = $filters;
        $includeDraft = self::shouldIncludeDraft($currentUser, $filters);

        if ($role === self::ROLE_PETUGAS) {
            if (!$includeDraft && !isset($queryFilters['status'])) {
                $queryFilters['status'] = implode(',', self::OFFICIAL_STATUSES);
            }
            return [
                'mode' => 'petugas',
                'ownerId' => (int) ($currentUser['id'] ?? 0),
                'queryFilters' => $queryFilters,
                'includeDraft' => $includeDraft,
            ];
        }

        if ($role === self::ROLE_ADMIN) {
            if (!$includeDraft && !isset($queryFilters['status'])) {
                $queryFilters['status'] = implode(',', self::OFFICIAL_STATUSES);
            }
            return [
                'mode' => 'admin',
                'ownerId' => null,
                'queryFilters' => $queryFilters,
                'includeDraft' => $includeDraft,
            ];
        }

        // Statistisi/operator/viewer/unknown: paksa status resmi, abaikan include_draft.
        $queryFilters['status'] = implode(',', self::OFFICIAL_STATUSES);
        return [
            'mode' => 'official',
            'ownerId' => null,
            'queryFilters' => $queryFilters,
            'includeDraft' => false,
        ];
    }

    /**
     * Kondisi akses baris untuk query detail (prepared-statement safe).
     *
     * @return array{0:string,1:list<mixed>} [klausa AND tambahan, params]
     */
    public static function accessibleCondition(string $alias, array $currentUser): array
    {
        $role = self::normalizeRole($currentUser['role'] ?? '');
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($role === self::ROLE_PETUGAS) {
            return ["{$prefix}user_id = ?", [(int) ($currentUser['id'] ?? 0)]];
        }
        if ($role === self::ROLE_ADMIN) {
            return ['', []];
        }
        return ["{$prefix}status IN ('Submitted', 'Diverifikasi')", []];
    }

    /**
     * Pengecekan baris pasca-fetch (defense-in-depth: query sudah
     * di-scope, baris dicek ulang di sini).
     *
     * @param array<string,mixed> $row baris laporan dari database
     * @param array<string,mixed> $currentUser identitas dari session/JWT
     */
    public static function canViewRow(array $row, array $currentUser): bool
    {
        $role = self::normalizeRole($currentUser['role'] ?? '');
        if ($role === self::ROLE_ADMIN) {
            return true;
        }
        if ($role === self::ROLE_PETUGAS) {
            return (int) ($row['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)
                && (int) ($currentUser['id'] ?? 0) > 0;
        }
        $status = (string) ($row['status'] ?? '');
        return in_array($status, self::OFFICIAL_STATUSES, true);
    }

    /**
     * Delegasi denial edit petugas (sumber tetap LaporanPolicy agar
     * kontrak error 404/409 tidak berubah).
     *
     * @param array<string,mixed> $laporan
     * @return array{error:string,message:string,code:int}|null
     */
    public static function editDenial(array $laporan, int $userId): ?array
    {
        return LaporanPolicy::editDenial($laporan, $userId);
    }

    /**
     * Admin global hanya bermakna pada rute administratif eksplisit.
     * Helper ini menegaskan intent di controller/service; enforcement
     * sesungguhnya tetap di AdminMiddleware per-route.
     */
    public static function requiresExplicitAdminRoute(array $currentUser): bool
    {
        return self::isAdmin($currentUser);
    }
}
