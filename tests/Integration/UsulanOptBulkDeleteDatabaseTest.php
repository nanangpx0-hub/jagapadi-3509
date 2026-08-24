<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/app/services/DuplicateMasterException.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';
require_once ROOT_PATH . '/app/services/UsulanOptReviewService.php';
require_once ROOT_PATH . '/app/services/UsulanOptService.php';

/**
 * UsulanOptBulkDeleteDatabaseTest
 *
 * Regresi fitur Select All + Hapus Massal (khusus Admin, runtime root):
 *  - Hanya Admin yang diizinkan (controller + route).
 *  - Status Disetujui/Digabungkan dilindungi (dilewati, tidak terhapus).
 *  - Soft delete transaksional: baris, foto, riwayat, dan file tetap tersedia.
 *  - Audit activity_log bulk_delete tercipta.
 *  - Sanitasi ID: non-numerik, duplikat, kosong, dan batas maksimal.
 */
final class UsulanOptBulkDeleteDatabaseTest extends TestCase
{
    private PDO $db;
    private UsulanOptService $service;
    private int $adminId;
    private int $petugasAId;
    private string $marker;
    private array $files = [];

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->adminId = $this->findUserId('admin');
        $this->petugasAId = $this->findUserId('petugas');

        if ($this->adminId <= 0 || $this->petugasAId <= 0) {
            self::markTestSkipped('Akun admin dan petugas diperlukan.');
        }

        $this->marker = 'CODEX-BULK-' . bin2hex(random_bytes(5));
        $this->service = new UsulanOptService($this->db);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        foreach ($this->files as $relative) {
            $full = ROOT_PATH . '/' . $relative;
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $this->files = [];

        $like = '%' . $this->marker . '%';
        $this->db->prepare('DELETE FROM notifications WHERE body LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM activity_log WHERE description LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM usulan_opt WHERE nama_lokal LIKE ?')->execute([$like]);
        $this->db->prepare('DELETE FROM master_opt WHERE nama_opt LIKE ?')->execute([$like]);
    }

    public function testBulkDeleteRemovesDeletableAndSkipsProtected(): void
    {
        $draftId = $this->createWithStatus(UsulanOpt::STATUS_DRAFT);
        $pendingId = $this->createWithStatus(UsulanOpt::STATUS_PENDING);
        $revisionId = $this->createWithStatus(UsulanOpt::STATUS_REVISION);
        $rejectedId = $this->createWithStatus(UsulanOpt::STATUS_REJECTED);
        $approvedId = $this->createWithStatus(UsulanOpt::STATUS_APPROVED);
        $mergedId = $this->createWithStatus(UsulanOpt::STATUS_MERGED);

        $result = $this->service->bulkDeleteForAdmin(
            [$draftId, $pendingId, $revisionId, $rejectedId, $approvedId, $mergedId, 999999999],
            $this->adminId
        );

        self::assertSame(7, $result['requested'], 'ID tidak dikenal tetap dihitung sebagai permintaan');
        self::assertSame(4, $result['deleted'], 'Draf/Pending/Revision/Rejected harus terhapus');
        self::assertSame(2, $result['skipped'], 'Disetujui + Digabungkan dilindungi');
        self::assertSame(
            [UsulanOpt::STATUS_APPROVED => 1, UsulanOpt::STATUS_MERGED => 1],
            $result['skipped_statuses']
        );

        foreach ([$draftId, $pendingId, $revisionId, $rejectedId] as $gone) {
            self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM usulan_opt WHERE id = ? AND deleted_at IS NOT NULL', [$gone]),
                'Status deletable wajib masuk recycle bin');
        }
        foreach ([$approvedId, $mergedId] as $kept) {
            self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM usulan_opt WHERE id = ?', [$kept]),
                'Status terkait master wajib dipertahankan');
        }

        $audit = $this->countRows(
            "SELECT COUNT(*) FROM activity_log WHERE action = 'bulk_delete' AND table_name = 'usulan_opt' AND record_id = ?",
            [$draftId]
        );
        self::assertSame(1, $audit, 'Audit bulk_delete wajib tercipta (record_id = id pertama)');

        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM usulan_opt_status_history WHERE usulan_opt_id = ?',
            [$draftId]
        ), 'Riwayat status harus dipertahankan selama berada di recycle bin');
    }

    public function testBulkDeleteRemovesPhotoRowsAndDiskFiles(): void
    {
        $id = $this->createWithStatus(UsulanOpt::STATUS_DRAFT);
        $relative = 'public/uploads/usulan-opt/' . date('Y/m') . '/bulk_' . bin2hex(random_bytes(4)) . '.png';
        $absolute = ROOT_PATH . '/' . $relative;
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($absolute, 'png-bytes-uji');
        $this->files[] = $relative;

        $this->db->prepare('INSERT INTO usulan_opt_photos (usulan_opt_id, file_path, created_by) VALUES (?, ?, ?)')
            ->execute([$id, $relative, $this->petugasAId]);
        self::assertFileExists($absolute);

        $result = $this->service->bulkDeleteForAdmin([$id], $this->adminId);

        self::assertSame(1, $result['deleted']);
        self::assertSame(0, $result['files'], 'Soft delete tidak menghapus file foto');
        self::assertFileExists($absolute, 'File foto harus dipertahankan agar data dapat dipulihkan');
        self::assertSame(1, $this->countRows(
            'SELECT COUNT(*) FROM usulan_opt_photos WHERE usulan_opt_id = ?',
            [$id]
        ));
    }

    public function testBulkDeleteSanitizesIds(): void
    {
        $a = $this->createWithStatus(UsulanOpt::STATUS_DRAFT);
        $b = $this->createWithStatus(UsulanOpt::STATUS_PENDING);

        $result = $this->service->bulkDeleteForAdmin(
            [(string) $a, $a, 'abc', -5, 0, '  ' . $b . ' ', null],
            $this->adminId
        );

        self::assertSame(2, $result['requested'], 'ID unik valid: a dan b');
        self::assertSame(2, $result['deleted']);
        self::assertSame(2, $this->countRows('SELECT COUNT(*) FROM usulan_opt WHERE id IN (?, ?) AND deleted_at IS NOT NULL', [$a, $b]));
    }

    public function testBulkDeleteWithEmptySelectionIsNoOp(): void
    {
        $result = $this->service->bulkDeleteForAdmin([], $this->adminId);

        self::assertSame(0, $result['requested']);
        self::assertSame(0, $result['deleted']);
    }

    public function testBulkDeleteRespectsMaxLimit(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createWithStatus(UsulanOpt::STATUS_DRAFT);
        }
        $padding = range(900000000, 900000000 + UsulanOptService::BULK_DELETE_MAX + 9);
        $padded = array_merge($ids, $padding);

        $result = $this->service->bulkDeleteForAdmin($padded, $this->adminId);

        self::assertSame(UsulanOptService::BULK_DELETE_MAX, $result['requested'],
            'Jumlah ID dipotong pada batas maksimal');
        self::assertSame(3, $result['deleted']);
    }

    public function testRouteAndControllerAreAdminOnlyWithCsrf(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('UsulanOpt@bulkDelete', $routes['usulan-opt/bulk-delete'] ?? null,
            'Route hapus massal wajib terdaftar eksplisit');

        $controller = file_get_contents(ROOT_PATH . '/app/controllers/UsulanOptController.php');
        $start = strpos($controller, 'public function bulkDelete(');
        self::assertNotFalse($start, 'Method bulkDelete wajib ada');
        $next = strpos($controller, 'public function ', $start + 10);
        $body = substr($controller, $start, ($next ?: $start + 1500) - $start);

        self::assertStringContainsString("checkRole(['admin'])", $body,
            'Hapus massal wajib admin-only di controller');
        self::assertStringContainsString("requireStateChangingRequest(['POST'])", $body,
            'Hapus massal wajib POST + CSRF');

        $view = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');
        self::assertStringContainsString('bulk_delete_form', $view);
        self::assertStringContainsString('bulk_select_all', $view);
        self::assertStringContainsString("if (\$is_admin && !empty(\$proposals)):", $view,
            'UI hapus massal hanya untuk admin');
        self::assertStringContainsString(
            'in_array($proposal[\'status\'], UsulanOptService::BULK_DELETE_PROTECTED',
            $view,
            'Checkbox status terlindungi tidak dirender'
        );
    }

    private function createWithStatus(string $status): int
    {
        $id = (new UsulanOpt())->create([
            'user_id' => $this->petugasAId,
            'nama_nasional' => strtoupper($this->marker) . ' ' . strtoupper(bin2hex(random_bytes(2))),
            'nama_lokal' => $this->marker . '-' . bin2hex(random_bytes(3)),
            'jenis' => 'hama',
            'komoditas' => 'Padi',
            'ciri_ciri' => 'Ciri uji bulk delete',
            'status' => $status,
        ]);

        $this->db->prepare('INSERT INTO usulan_opt_status_history (usulan_opt_id, from_status, to_status, changed_by) VALUES (?, NULL, ?, ?)')
            ->execute([(int) $id, $status, $this->adminId]);

        return (int) $id;
    }

    private function countRows(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function findUserId(string $role, int $excludeId = 0): int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE role = ? AND id != ? ORDER BY id LIMIT 1');
        $stmt->execute([$role, $excludeId]);

        return (int) $stmt->fetchColumn();
    }

    private function loadEnvironment(): void
    {
        foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
