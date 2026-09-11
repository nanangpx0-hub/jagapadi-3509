<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Policies\ReportAuthorizationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Murni in-memory: tidak menyentuh socket database.
 * Mengunci kontrak P0 (AGENTS.md §3-§4, ADR-011).
 */
final class ReportAuthorizationPolicyTest extends TestCase
{
    public function testNormalizeRoleIsCaseInsensitiveAndFailClosed(): void
    {
        self::assertSame('admin', ReportAuthorizationPolicy::normalizeRole('Admin'));
        self::assertSame('petugas', ReportAuthorizationPolicy::normalizeRole(' PETUGAS '));
        self::assertSame('statistisi', ReportAuthorizationPolicy::normalizeRole('STATISTISI'));
        self::assertSame('viewer', ReportAuthorizationPolicy::normalizeRole('hacker'));
        self::assertSame('viewer', ReportAuthorizationPolicy::normalizeRole(''));
        self::assertSame('viewer', ReportAuthorizationPolicy::normalizeRole(null));
    }

    public function testPetugasScopeIsOwnerScopedWithDraftByDefault(): void
    {
        $scope = ReportAuthorizationPolicy::resolveListScope(['id' => 5, 'role' => 'petugas'], []);
        self::assertSame('petugas', $scope['mode']);
        self::assertSame(5, $scope['ownerId']);
        self::assertTrue($scope['includeDraft']);
        self::assertArrayNotHasKey('status', $scope['queryFilters']);

        $noDraft = ReportAuthorizationPolicy::resolveListScope(
            ['id' => 5, 'role' => 'petugas'],
            ['include_draft' => 'false']
        );
        self::assertFalse($noDraft['includeDraft']);
        self::assertSame('Submitted,Diverifikasi', $noDraft['queryFilters']['status']);
    }

    public function testStatistisiIgnoresIncludeDraftAndSeesOnlyOfficial(): void
    {
        foreach (['statistisi', 'operator', 'viewer', 'unknown-role'] as $role) {
            $scope = ReportAuthorizationPolicy::resolveListScope(
                ['id' => 99, 'role' => $role],
                ['include_draft' => 'true']
            );
            self::assertSame('official', $scope['mode'], $role);
            self::assertFalse($scope['includeDraft'], $role);
            self::assertSame('Submitted,Diverifikasi', $scope['queryFilters']['status'], $role);
        }
    }

    public function testAdminDefaultsToOfficialWithoutDraft(): void
    {
        $default = ReportAuthorizationPolicy::resolveListScope(['id' => 1, 'role' => 'admin'], []);
        self::assertSame('admin', $default['mode']);
        self::assertFalse($default['includeDraft']);
        self::assertSame('Submitted,Diverifikasi', $default['queryFilters']['status']);

        $withDraft = ReportAuthorizationPolicy::resolveListScope(
            ['id' => 1, 'role' => 'admin'],
            ['include_draft' => 'true']
        );
        self::assertTrue($withDraft['includeDraft']);
        self::assertArrayNotHasKey('status', $withDraft['queryFilters']);
    }

    public function testCanViewRowEnforcesIdorAndOfficialStatuses(): void
    {
        // Petugas: hanya milik sendiri.
        self::assertTrue(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 5, 'status' => 'Draf'],
            ['id' => 5, 'role' => 'petugas']
        ));
        self::assertFalse(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Draf'],
            ['id' => 5, 'role' => 'petugas']
        ));
        // Petugas A vs B pada Submitted tetap IDOR.
        self::assertFalse(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Submitted'],
            ['id' => 5, 'role' => 'petugas']
        ));

        // Statistisi: global tapi hanya resmi.
        self::assertTrue(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Submitted'],
            ['id' => 9, 'role' => 'statistisi']
        ));
        self::assertTrue(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Diverifikasi'],
            ['id' => 9, 'role' => 'STATISTISI']
        ));
        self::assertFalse(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Draf'],
            ['id' => 9, 'role' => 'statistisi']
        ));
        self::assertFalse(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Ditolak'],
            ['id' => 9, 'role' => 'statistisi']
        ));

        // Admin: global pada rute eksplisit.
        self::assertTrue(ReportAuthorizationPolicy::canViewRow(
            ['user_id' => 6, 'status' => 'Draf'],
            ['id' => 1, 'role' => 'admin']
        ));
    }

    public function testAccessibleConditionUsesPlaceholders(): void
    {
        [$clause, $params] = ReportAuthorizationPolicy::accessibleCondition('lh', ['id' => 5, 'role' => 'petugas']);
        self::assertSame('lh.user_id = ?', $clause);
        self::assertSame([5], $params);

        [$adminClause, $adminParams] = ReportAuthorizationPolicy::accessibleCondition('lh', ['id' => 1, 'role' => 'admin']);
        self::assertSame('', $adminClause);
        self::assertSame([], $adminParams);

        [$officialClause, $officialParams] = ReportAuthorizationPolicy::accessibleCondition(
            'lh',
            ['id' => 9, 'role' => 'statistisi']
        );
        self::assertSame("lh.status IN ('Submitted', 'Diverifikasi')", $officialClause);
        self::assertSame([], $officialParams);
    }

    public function testEditDenialDelegatesToLaporanPolicy(): void
    {
        self::assertNull(ReportAuthorizationPolicy::editDenial(['user_id' => 5, 'status' => 'Draf'], 5));
        $denial = ReportAuthorizationPolicy::editDenial(['user_id' => 5, 'status' => 'Draf'], 6);
        self::assertNotNull($denial);
        self::assertSame(404, $denial['code']);
    }
}
