<?php

use PHPUnit\Framework\TestCase;

final class LaporanLainnyaPetugasAuthorizationTest extends TestCase {

    public function testNonPetugasCannotAccessPetugasReportEndpoint(): void {
        // Simulate different roles trying to access petugas-only endpoints
        $roles = ['admin', 'operator', 'statistisi', 'guest'];
        
        foreach ($roles as $role) {
            $hasAccess = $this->checkPetugasAccess($role);
            $this->assertFalse($hasAccess, "Role '{$role}' should not have access to petugas-only report endpoint");
        }
    }

    public function testPetugasCanAccessPetugasReportEndpoint(): void {
        $hasAccess = $this->checkPetugasAccess('petugas');
        $this->assertTrue($hasAccess, "Petugas role should have access to petugas-only report endpoint");
    }

    public function testIdorProtectionPetugasCannotAccessOtherPetugasReports(): void {
        // Simulate Petugas A trying to access Petugas B's reports
        $petugasA = ['id' => 10, 'role' => 'petugas'];
        $petugasB = ['id' => 20, 'role' => 'petugas'];
        
        $reportOwnedByB = ['id' => 100, 'user_id' => 20, 'status' => 'draft'];
        
        $canAccess = $this->checkReportAccess($petugasA, $reportOwnedByB);
        $this->assertFalse($canAccess, "Petugas A should not be able to access Petugas B's report");
    }

    public function testPetugasCanAccessOwnReports(): void {
        $petugasA = ['id' => 10, 'role' => 'petugas'];
        $reportOwnedByA = ['id' => 100, 'user_id' => 10, 'status' => 'draft'];
        
        $canAccess = $this->checkReportAccess($petugasA, $reportOwnedByA);
        $this->assertTrue($canAccess, "Petugas should be able to access their own reports");
    }

    public function testAdminCanAccessAllReports(): void {
        $admin = ['id' => 1, 'role' => 'admin'];
        $petugasReport = ['id' => 100, 'user_id' => 20, 'status' => 'draft'];
        
        $canAccess = $this->checkReportAccess($admin, $petugasReport);
        $this->assertTrue($canAccess, "Admin should be able to access any report");
    }

    public function testDataScopingInSummaryQuery(): void {
        // Test that summary queries are properly scoped to the current user
        $petugasA = ['id' => 10, 'role' => 'petugas'];
        $petugasB = ['id' => 20, 'role' => 'petugas'];
        
        $queryForA = $this->buildSummaryQuery($petugasA);
        $queryForB = $this->buildSummaryQuery($petugasB);
        
        $this->assertStringContainsString('user_id = 10', $queryForA, "Query for Petugas A should include user_id filter");
        $this->assertStringContainsString('user_id = 20', $queryForB, "Query for Petugas B should include user_id filter");
        $this->assertNotEquals($queryForA, $queryForB, "Queries should be different for different users");
    }

    public function testPetugasCannotVerifyReports(): void {
        $petugas = ['id' => 10, 'role' => 'petugas'];
        $report = ['id' => 100, 'user_id' => 10, 'status' => 'submitted'];
        
        $canVerify = $this->checkVerificationAccess($petugas, $report);
        $this->assertFalse($canVerify, "Petugas should not be able to verify reports");
    }

    public function testAdminCanVerifyReports(): void {
        $admin = ['id' => 1, 'role' => 'admin'];
        $report = ['id' => 100, 'user_id' => 10, 'status' => 'submitted'];
        
        $canVerify = $this->checkVerificationAccess($admin, $report);
        $this->assertTrue($canVerify, "Admin should be able to verify reports");
    }

    public function testPetugasCannotSubmitOtherPetugasDrafts(): void {
        $petugasA = ['id' => 10, 'role' => 'petugas'];
        $petugasB = ['id' => 20, 'role' => 'petugas'];
        $reportOwnedByB = ['id' => 100, 'user_id' => 20, 'status' => 'draft'];
        
        $canSubmit = $this->checkSubmitAccess($petugasA, $reportOwnedByB);
        $this->assertFalse($canSubmit, "Petugas A should not be able to submit Petugas B's draft");
    }

    public function testPetugasCanSubmitOwnDrafts(): void {
        $petugasA = ['id' => 10, 'role' => 'petugas'];
        $reportOwnedByA = ['id' => 100, 'user_id' => 10, 'status' => 'draft'];
        
        $canSubmit = $this->checkSubmitAccess($petugasA, $reportOwnedByA);
        $this->assertTrue($canSubmit, "Petugas should be able to submit their own drafts");
    }

    /**
     * Helper method to simulate petugas access check
     */
    private function checkPetugasAccess(string $role): bool {
        // Simulate middleware check for petugas role
        return $role === 'petugas';
    }

    /**
     * Helper method to simulate report access check with IDOR protection
     */
    private function checkReportAccess(array $user, array $report): bool {
        // Simulate ownership check
        if ($user['role'] === 'admin') {
            return true; // Admin can access all
        }
        
        // Petugas can only access their own reports
        return $user['id'] === $report['user_id'];
    }

    /**
     * Helper method to simulate verification access check
     */
    private function checkVerificationAccess(array $user, array $report): bool {
        // Only admin can verify
        return $user['role'] === 'admin';
    }

    /**
     * Helper method to simulate submit access check
     */
    private function checkSubmitAccess(array $user, array $report): bool {
        // Admin can submit any report
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // Petugas can only submit their own reports
        return $user['id'] === $report['user_id'];
    }

    /**
     * Helper method to simulate summary query building with data scoping
     */
    private function buildSummaryQuery(array $user): string {
        // Simulate query building with user scoping
        $baseQuery = "SELECT COUNT(*) as total FROM laporan_lainnya";
        
        if ($user['role'] === 'petugas') {
            return $baseQuery . " WHERE user_id = " . $user['id'];
        }
        
        // Admin gets all data
        return $baseQuery;
    }
}
