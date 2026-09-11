<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\DeviceToken;
use App\Models\LaporanHama;
use App\Models\LaporanIrigasi;
use App\Models\Notification;
use App\Services\DashboardService;
use App\Services\ExportService;
use PHPUnit\Framework\TestCase;

final class OwnershipIdorMatrixTest extends TestCase
{
    private ?int $petugasA = null;
    private ?int $petugasB = null;
    private ?int $admin = null;
    private array $idsHama = [];
    private array $idsIrigasi = [];
    private array $notifIds = [];
    private array $tokenIds = [];

    public static function setUpBeforeClass(): void
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, 'DB_') && str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    putenv(trim($k) . '=' . trim($v));
                }
            }
        }
    }

    protected function setUp(): void
    {
        try {
            $pdo = Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database tidak tersedia: ' . $e->getMessage());
        }
        // Create isolation users
        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('Test123!@#', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, nama_lengkap, role, aktif, must_change_password) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute(["idor_a_{$suffix}", $hash, "idor_a_{$suffix}@test.local", "IDOR A {$suffix}", "petugas", 1, 0]);
        $this->petugasA = (int)$pdo->lastInsertId();
        $stmt->execute(["idor_b_{$suffix}", $hash, "idor_b_{$suffix}@test.local", "IDOR B {$suffix}", "petugas", 1, 0]);
        $this->petugasB = (int)$pdo->lastInsertId();
        $stmt->execute(["idor_admin_{$suffix}", $hash, "idor_admin_{$suffix}@test.local", "IDOR Admin {$suffix}", "admin", 1, 0]);
        $this->admin = (int)$pdo->lastInsertId();

        // Hama laporan for A
        $stmt2 = $pdo->prepare("INSERT INTO laporan_hama (user_id, master_opt_id, tanggal, kabupaten_id, kecamatan_id, desa_id, tingkat_keparahan, luas_serangan, status, created_at, updated_at) VALUES (?,1,CURDATE(),1,1,1,'Ringan',1.0,'Submitted',NOW(),NOW())");
        $stmt2->execute([$this->petugasA]);
        $this->idsHama[] = (int)$pdo->lastInsertId();
        $stmt2->execute([$this->petugasB]);
        $this->idsHama[] = (int)$pdo->lastInsertId();

        // Irigasi laporan for A/B
        $stmt3 = $pdo->prepare("INSERT INTO laporan_irigasi (user_id, tanggal, kabupaten_id, kecamatan_id, desa_id, nama_saluran, kondisi_fisik, debit_air, status, created_at, updated_at) VALUES (?,CURDATE(),1,1,1,'Saluran A','Sedang','Cukup','Submitted',NOW(),NOW())");
        $stmt3->execute([$this->petugasA]);
        $this->idsIrigasi[] = (int)$pdo->lastInsertId();
        $stmt3->execute([$this->petugasB]);
        $this->idsIrigasi[] = (int)$pdo->lastInsertId();

        // Notifications: one per user
        $stmtN = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body, data_json) VALUES (?,?,?,?,?)");
        $stmtN->execute([$this->petugasA, 'laporan_verified', 'Test', 'Body A', '{}']);
        $this->notifIds['A'] = (int)$pdo->lastInsertId();
        $stmtN->execute([$this->petugasB, 'laporan_verified', 'Test', 'Body B', '{}']);
        $this->notifIds['B'] = (int)$pdo->lastInsertId();

        // Device tokens
        $tokA = 'tok_' . bin2hex(random_bytes(16));
        $tokB = 'tok_' . bin2hex(random_bytes(16));
        DeviceToken::upsertForUser($this->petugasA, $tokA, 'android', 'UA-A');
        DeviceToken::upsertForUser($this->petugasB, $tokB, 'android', 'UA-B');
        $this->tokenIds['A'] = $tokA;
        $this->tokenIds['B'] = $tokB;
    }

    protected function tearDown(): void
    {
        try {
            $pdo = Database::connect();
        } catch (\Throwable) {
            return;
        }
        if ($this->petugasA === null || $this->petugasB === null || $this->admin === null) {
            return;
        }
        if (!empty($this->idsHama)) {
            $in = implode(',', array_fill(0, count($this->idsHama), '?'));
            $pdo->prepare("DELETE FROM laporan_hama WHERE id IN ($in)")->execute($this->idsHama);
        }
        if (!empty($this->idsIrigasi)) {
            $in = implode(',', array_fill(0, count($this->idsIrigasi), '?'));
            $pdo->prepare("DELETE FROM laporan_irigasi WHERE id IN ($in)")->execute($this->idsIrigasi);
        }
        if (!empty($this->notifIds)) {
            $pdo->prepare("DELETE FROM notifications WHERE id IN (?,?)")->execute([$this->notifIds['A'], $this->notifIds['B']]);
        }
        if (!empty($this->tokenIds)) {
            $pdo->prepare("DELETE FROM device_tokens WHERE token IN (?,?)")->execute([$this->tokenIds['A'], $this->tokenIds['B']]);
        }
        $pdo->prepare("DELETE FROM users WHERE id IN (?,?,?)")->execute([$this->petugasA, $this->petugasB, $this->admin]);
    }

    public function testPetugasCannotReadOtherHamaLaporan(): void
    {
        $idA = $this->idsHama[0];
        $idB = $this->idsHama[1];
        // A can read own
        $own = LaporanHama::findAccessibleById($idA, ['id' => $this->petugasA, 'role' => 'petugas']);
        $this->assertNotNull($own, 'Petugas A harus bisa baca laporan sendiri');
        // A cannot read B's
        $other = LaporanHama::findAccessibleById($idB, ['id' => $this->petugasA, 'role' => 'petugas']);
        $this->assertNull($other, 'Petugas A tidak boleh baca laporan B (IDOR)');
        // B cannot read A's
        $other2 = LaporanHama::findAccessibleById($idA, ['id' => $this->petugasB, 'role' => 'petugas']);
        $this->assertNull($other2);
        // Admin can read both
        $adminA = LaporanHama::findAccessibleById($idA, ['id' => $this->admin, 'role' => 'admin']);
        $adminB = LaporanHama::findAccessibleById($idB, ['id' => $this->admin, 'role' => 'admin']);
        $this->assertNotNull($adminA);
        $this->assertNotNull($adminB);
    }

    public function testPetugasListIsScoped(): void
    {
        $listA = LaporanHama::listForPetugas($this->petugasA, [], 1, 100);
        $idsA = array_column($listA['data'], 'id');
        $this->assertContains($this->idsHama[0], $idsA);
        $this->assertNotContains($this->idsHama[1], $idsA);

        $listB = LaporanHama::listForPetugas($this->petugasB, [], 1, 100);
        $idsB = array_column($listB['data'], 'id');
        $this->assertContains($this->idsHama[1], $idsB);
        $this->assertNotContains($this->idsHama[0], $idsB);
    }

    public function testNotificationOwnership(): void
    {
        $idA = $this->notifIds['A'];
        $idB = $this->notifIds['B'];
        // A can find own
        $this->assertNotNull(Notification::findForUser($this->petugasA, $idA));
        // A cannot find B's
        $this->assertNull(Notification::findForUser($this->petugasA, $idB));
        // A cannot mark B's as read
        $this->assertFalse(Notification::markRead($this->petugasA, $idB));
        // A cannot delete B's
        $this->assertFalse(Notification::deleteForUser($this->petugasA, $idB));
        // Admin cannot use petugas endpoint to read petugas notification (must be owner)
        $this->assertNull(Notification::findForUser($this->admin, $idA));
    }

    public function testDeviceTokenOwnership(): void
    {
        $tokA = $this->tokenIds['A'];
        $tokB = $this->tokenIds['B'];
        // List scoped
        $listA = DeviceToken::listByUserId($this->petugasA);
        $tokensA = array_column($listA, 'token');
        $this->assertContains($tokA, $tokensA);
        $this->assertNotContains($tokB, $tokensA);

        // Delete scoped: A cannot delete B's token
        $deleted = DeviceToken::deleteByTokenForUser($this->petugasA, $tokB);
        // rowCount false? execute returns true but rowCount 0; check that token still exists
        $still = DeviceToken::findByToken($tokB);
        $this->assertNotNull($still, 'Token B harus tetap ada setelah percobaan hapus oleh A');
        $this->assertSame($this->petugasB, (int)$still['user_id']);
    }

    public function testDashboardOwnership(): void
    {
        // DashboardService for petugas A should not include B's data
        // We test via getMapHama which filters by user_id
        $svcA = new DashboardService('petugas', $this->petugasA, (int)date('Y'));
        $svcB = new DashboardService('petugas', $this->petugasB, (int)date('Y'));
        // Both have at least 1 laporan Submitted with no coordinates (so map will be empty)
        // Instead test stats isolation: create with coordinates and test map
        $pdo = Database::connect();
        // update laporan to have coordinates
        $pdo->prepare("UPDATE laporan_hama SET latitude=-8.17, longitude=113.70 WHERE id=?")->execute([$this->idsHama[0]]);
        $pdo->prepare("UPDATE laporan_hama SET latitude=-8.18, longitude=113.71 WHERE id=?")->execute([$this->idsHama[1]]);
        \App\Core\CacheManager::init(dirname(__DIR__, 2) . '/storage/cache');
        DashboardService::invalidateCache();
        $mapA = $svcA->getMapHama('aktif', 500);
        $idsMapA = array_map(fn($f) => $f['properties']['id'], $mapA['features']);
        $this->assertContains($this->idsHama[0], $idsMapA);
        $this->assertNotContains($this->idsHama[1], $idsMapA);

        $mapB = $svcB->getMapHama('aktif', 500);
        $idsMapB = array_map(fn($f) => $f['properties']['id'], $mapB['features']);
        $this->assertContains($this->idsHama[1], $idsMapB);
        $this->assertNotContains($this->idsHama[0], $idsMapB);

        // Admin sees both
        $svcAdmin = new DashboardService('admin', null, (int)date('Y'));
        DashboardService::invalidateCache();
        $mapAdmin = $svcAdmin->getMapHama('aktif', 500);
        $idsAdmin = array_map(fn($f) => $f['properties']['id'], $mapAdmin['features']);
        $this->assertContains($this->idsHama[0], $idsAdmin);
        $this->assertContains($this->idsHama[1], $idsAdmin);
    }

    public function testExportOwnership(): void
    {
        // Verifikasi via list scope (mewakili export scope)
        $adminList = LaporanHama::listForAdmin([], 1, 100);
        $adminIds = array_column($adminList['data'], 'id');
        $this->assertContains($this->idsHama[0], $adminIds);
        $this->assertContains($this->idsHama[1], $adminIds);
        $aList = LaporanHama::listForPetugas($this->petugasA, [], 1, 100);
        $aIds = array_column($aList['data'], 'id');
        $this->assertContains($this->idsHama[0], $aIds);
        $this->assertNotContains($this->idsHama[1], $aIds);
    }

    public function testAdminGlobalOnlyOnExplicitRoute(): void
    {
        $routesContent = file_get_contents(dirname(__DIR__, 2) . '/config/routes.php');
        $this->assertIsString($routesContent);
        // Harus ada route admin eksplisit (wilayah/opt/delete) dengan AdminMiddleware
        $adminCount = substr_count($routesContent, 'AdminMiddleware::class');
        $this->assertGreaterThan(10, $adminCount, 'Harus ada >10 route admin eksplisit di backend v1');
        $this->assertStringContainsString('/wilayah/kabupaten', $routesContent);
        $this->assertStringContainsString('/opt', $routesContent);
        // Pastikan AdminMiddleware tidak di-addGlobalMiddleware secara langsung (harus eksplisit per-route)
        $this->assertStringNotContainsString('addGlobalMiddleware(AdminMiddleware', $routesContent);
        $this->assertStringNotContainsString('addGlobalMiddleware(App\Middleware\AdminMiddleware', $routesContent);
    }
}
