<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\LaporanHama;
use App\Services\LaporanHamaService;
use PHPUnit\Framework\TestCase;

final class StatistisiReportAccessTest extends TestCase
{
    /** @var list<int> */
    private array $createdIds = [];
    private bool $dbAvailable = true;

    protected function setUp(): void
    {
        try {
            Database::connect();
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    protected function tearDown(): void
    {
        if (!$this->dbAvailable || $this->createdIds === []) {
            return;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($this->createdIds), '?'));
            Database::connect()->prepare("DELETE FROM laporan_hama WHERE id IN ({$placeholders})")
                ->execute($this->createdIds);
        } catch (\Throwable) {
            // Best-effort cleanup; abaikan bila DB mati.
        }
    }

    public function testStatistisiSeesGlobalOfficialReportsButNeverDrafts(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database tidak tersedia');
        }

        $userIds = Database::connect()->query('SELECT id FROM users ORDER BY id LIMIT 2')
            ->fetchAll(\PDO::FETCH_COLUMN);
        if (count($userIds) < 2) {
            $this->markTestSkipped('Memerlukan minimal dua user fixture');
        }

        $submittedId = $this->insertReport((int) $userIds[0], 'Submitted');
        $draftId = $this->insertReport((int) $userIds[1], 'Draf');

        $result = LaporanHamaService::listForCurrentUser(
            ['id' => 999999, 'role' => 'statistisi'],
            ['include_draft' => 'true', 'limit' => 100, 'order_col' => 'lh.id', 'order_dir' => 'DESC']
        );
        $ids = array_map('intval', array_column($result['data'], 'id'));

        self::assertContains($submittedId, $ids, 'Statistisi harus melihat laporan resmi milik user lain');
        self::assertNotContains($draftId, $ids, 'Statistisi tidak boleh melihat Draf');
        self::assertNotNull(LaporanHama::findAccessibleById($submittedId, ['id' => 999999, 'role' => 'statistisi']));
        self::assertNull(LaporanHama::findAccessibleById($draftId, ['id' => 999999, 'role' => 'statistisi']));
    }

    public function testAllReportModulesApplyTheSameStatistisiReadPolicy(): void
    {
        foreach (['Hama', 'Irigasi', 'Pupuk', 'Panen', 'Cuaca', 'AlatSarana'] as $module) {
            $service = file_get_contents(dirname(__DIR__, 2) . "/app/Services/Laporan{$module}Service.php");
            $model = file_get_contents(dirname(__DIR__, 2) . "/app/Models/Laporan{$module}.php");

            self::assertIsString($service);
            // Delegasi ke single source of truth (P0): tidak ada parsing role inline lagi.
            self::assertStringContainsString('ReportAuthorizationPolicy::resolveListScope', $service, $module);
            self::assertStringContainsString('ReportAuthorizationPolicy::canViewRow', $service, $module);
            self::assertStringContainsString("\$scope['mode'] === 'petugas'", $service, $module);
            self::assertStringContainsString('::listForPetugas(', $service, $module);
            self::assertStringContainsString('::listForAdmin(', $service, $module);
            self::assertStringNotContainsString("\$isPetugas = \$role === 'petugas'", $service, $module . ': parsing role harus di policy');
            self::assertIsString($model);
            self::assertStringContainsString('ReportAuthorizationPolicy::accessibleCondition', $model, $module);
            self::assertStringNotContainsString("\$currentUser['role'] === 'petugas'", $model, $module . ': parsing role harus di policy');
        }

        // Kontrak policy murni (tanpa DB): statistisi mengabaikan include_draft.
        $scope = \App\Policies\ReportAuthorizationPolicy::resolveListScope(
            ['id' => 7, 'role' => 'statistisi'],
            ['include_draft' => 'true']
        );
        self::assertSame('official', $scope['mode']);
        self::assertFalse($scope['includeDraft']);
        self::assertSame('Submitted,Diverifikasi', $scope['queryFilters']['status']);
    }

    private function insertReport(int $userId, string $status): int
    {
        $stmt = Database::connect()->prepare(
            "INSERT INTO laporan_hama
             (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id,
              tingkat_keparahan, luas_serangan, populasi, status, created_at, updated_at)
             VALUES (?, 1, CURDATE(), 1, 1, 1, 'Ringan', 1.00, 1.00, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $status]);
        $id = (int) Database::connect()->lastInsertId();
        $this->createdIds[] = $id;

        return $id;
    }
}
