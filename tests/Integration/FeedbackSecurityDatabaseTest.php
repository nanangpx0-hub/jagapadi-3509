<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * FeedbackSecurityDatabaseTest
 *
 * Verifikasi end-to-end fitur Feedback pada lapisan model + kebijakan
 * keamanan yang diterapkan di controller:
 *  - Ownership scoping (petugas hanya melihat feedback milik sendiri)
 *  - IDOR guard pada vote (petugas tidak bisa vote feedback petugas lain)
 *  - Validasi multibyte & batas panjang (judul/deskripsi)
 *  - Catatan admin disimpan mentah (escaping dilakukan saat output)
 *  - Transaksi: feedback + feedback_status_history tidak pernah parsial
 *  - Filter (jenis/status/search/year/month) & pagination
 *  - Akurasi rekap per petugas & ringkasan admin
 *  - Upload directory aman (.htaccess) & rute web/API yang benar
 */
final class FeedbackSecurityDatabaseTest extends TestCase
{
    private PDO $db;
    private int $adminId;
    private int $petugasAId;
    private int $petugasBId;
    private string $marker;

    protected function setUp(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $this->adminId = $this->findUserId('admin');
        $this->petugasAId = $this->findUserId('petugas');
        $this->petugasBId = $this->findUserId('petugas', $this->petugasAId);

        if ($this->adminId <= 0 || $this->petugasAId <= 0) {
            self::markTestSkipped('Akun admin dan petugas diperlukan untuk pengujian feedback.');
        }

        $this->marker = 'CODEX-FB-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        $like = '%' . $this->marker . '%';
        $stmt = $this->db->prepare(
            "DELETE FROM feedback_votes
             WHERE feedback_id IN (SELECT id FROM feedback WHERE judul LIKE ?)"
        );
        $stmt->execute([$like]);
        $stmt = $this->db->prepare(
            "DELETE FROM feedback_status_history
             WHERE feedback_id IN (SELECT id FROM feedback WHERE judul LIKE ?)"
        );
        $stmt->execute([$like]);
        $stmt = $this->db->prepare('DELETE FROM feedback WHERE judul LIKE ?');
        $stmt->execute([$like]);
    }

    // ==================== Transaksi & persistensi ====================

    public function testCreatePersistsFeedbackAndInitialStatusHistory(): void
    {
        $id = $this->createFeedback($this->petugasAId);

        $feedback = (new Feedback())->getById($id);
        self::assertNotNull($feedback, 'Feedback harus tersimpan setelah create()');
        self::assertSame('diterima', $feedback['status']);

        $history = (new Feedback())->getStatusHistory($id);
        self::assertCount(1, $history, 'create() harus mencatat 1 riwayat status awal');
        self::assertSame('diterima', $history[0]['new_status']);
        self::assertNull($history[0]['old_status']);
        self::assertSame($this->petugasAId, (int) $history[0]['changed_by']);
    }

    public function testCreateReturnsFalseAndRollsBackOnDatabaseError(): void
    {
        $model = new Feedback();

        $result = $model->create([
            'user_id'        => $this->petugasAId,
            'jenis_feedback' => 'bug',
            'judul'          => str_repeat('x', 300), // melebihi varchar(255) → error database
            'deskripsi'      => str_repeat('y', 30),
            'prioritas'      => 'medium',
        ]);

        self::assertFalse($result, 'create() harus mengembalikan false saat terjadi error database');
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM feedback WHERE judul = ?');
        $stmt->execute([str_repeat('x', 300)]);
        self::assertSame(0, (int) $stmt->fetchColumn(),
            'Tidak boleh ada data parsial tersisa setelah rollback');
    }

    public function testUpdateStatusPersistsHistoryAndStoresNotesRaw(): void
    {
        $id = $this->createFeedback($this->petugasAId);
        $rawNotes = '<b>Catatan & "quotes"</b>';

        $ok = (new Feedback())->updateStatus($id, 'selesai', $this->adminId, $rawNotes);
        self::assertTrue($ok, 'updateStatus() harus sukses');

        $feedback = (new Feedback())->getById($id);
        self::assertSame('selesai', $feedback['status']);
        self::assertSame($rawNotes, $feedback['admin_notes'],
            'Catatan admin harus disimpan mentah (escaping hanya saat output)');
        self::assertSame($this->adminId, (int) $feedback['processed_by']);
        self::assertNotNull($feedback['processed_at']);

        $history = (new Feedback())->getStatusHistory($id);
        self::assertCount(2, $history, 'Harus ada riwayat status awal + perubahan');
        $last = $history[1];
        self::assertSame('diterima', $last['old_status']);
        self::assertSame('selesai', $last['new_status']);
        self::assertSame($rawNotes, $last['notes']);
    }

    // ==================== Ownership & IDOR ====================

    public function testGetAllScopesToOwnerWhenUserIdFilterPresent(): void
    {
        $idA = $this->createFeedback($this->petugasAId);
        $this->createFeedback($this->petugasBId);

        $result = (new Feedback())->getAll(['user_id' => $this->petugasAId], 1, 50);
        $ids = array_map('intval', array_column($result['data'], 'id'));

        self::assertContains($idA, $ids);
        foreach ($ids as $fid) {
            $feedback = (new Feedback())->getById($fid);
            self::assertSame($this->petugasAId, (int) $feedback['user_id'],
                'Semua feedback yang terlihat oleh petugas harus miliknya sendiri');
        }
    }

    public function testVoteEndpointHasIdorGuardInController(): void
    {
        $content = file_get_contents(ROOT_PATH . '/app/controllers/FeedbackController.php');
        self::assertStringContainsString(
            "'Anda tidak memiliki akses ke masukan ini'], 403",
            $content,
            'vote() harus menolak petugas yang mencoba vote feedback milik petugas lain (403)'
        );
        self::assertStringContainsString(
            "'Tidak dapat vote pada masukan sendiri'], 400",
            $content,
            'vote() harus menolak vote pada feedback milik sendiri (400)'
        );
    }

    public function testDetailEnforcesOwnershipForPetugasInController(): void
    {
        $content = file_get_contents(ROOT_PATH . '/app/controllers/FeedbackController.php');
        self::assertStringContainsString(
            "'Anda tidak memiliki akses ke masukan ini.'",
            $content,
            'detail() harus menolak akses petugas ke feedback milik petugas lain'
        );
        self::assertStringContainsString(
            "checkRole(self::DETAIL_ROLES)",
            $content,
            'detail() harus membatasi akses ke role admin & petugas'
        );
    }

    public function testVoteToggleWorksBetweenDifferentPetugas(): void
    {
        $id = $this->createFeedback($this->petugasBId);
        $model = new Feedback();

        $result = $model->toggleVote($id, $this->petugasAId);
        self::assertSame('added', $result['action']);
        self::assertSame(1, $result['vote_count']);
        self::assertTrue($model->hasUserVoted($id, $this->petugasAId));

        $result = $model->toggleVote($id, $this->petugasAId);
        self::assertSame('removed', $result['action']);
        self::assertSame(0, $result['vote_count']);
        self::assertFalse($model->hasUserVoted($id, $this->petugasAId));
    }

    // ==================== Validasi multibyte & upload ====================

    public function testCreateValidationUsesMultibyteLengthAndDeskripsiMax(): void
    {
        $content = file_get_contents(ROOT_PATH . '/app/controllers/FeedbackController.php');
        self::assertStringContainsString('mb_strlen($judul)', $content,
            'Validasi judul harus memakai mb_strlen (multibyte-safe)');
        self::assertStringContainsString('mb_strlen($deskripsi)', $content,
            'Validasi deskripsi harus memakai mb_strlen (multibyte-safe)');
        self::assertStringContainsString('Deskripsi maksimal 5000 karakter.', $content,
            'Deskripsi harus memiliki batas maksimal');
    }

    public function testUploadErrorIsExplicitlyReported(): void
    {
        $content = file_get_contents(ROOT_PATH . '/app/controllers/FeedbackController.php');
        self::assertStringContainsString('uploadErrorMessage($attachmentError)', $content,
            'Error upload harus dieksplisitkan, tidak diabaikan diam-diam');
        self::assertStringContainsString('is_uploaded_file', $content,
            'Upload harus memvalidasi is_uploaded_file');
    }

    public function testUploadDirectoryIsProtectedByHtaccess(): void
    {
        $htaccess = ROOT_PATH . '/public/uploads/feedback/.htaccess';
        self::assertFileExists($htaccess, 'uploads/feedback harus memiliki .htaccess');
        $content = file_get_contents($htaccess);
        self::assertStringContainsString('Require all denied', $content,
            '.htaccess harus memblokir eksekusi file berbahaya');
    }

    // ==================== Filter & pagination ====================

    public function testFiltersAndPagination(): void
    {
        $model = new Feedback();
        $year = (int) date('Y');
        $month = (int) date('n');

        $idBug = $this->createFeedback($this->petugasAId, 'bug');
        $idFitur = $this->createFeedback($this->petugasAId, 'fitur_baru');
        $model->updateStatus($idBug, 'selesai', $this->adminId, '');
        $this->createFeedback($this->petugasAId, 'peningkatan');

        $byJenis = $model->getAll(['jenis' => 'bug', 'search' => $this->marker], 1, 50);
        self::assertSame(1, $byJenis['total'], 'Filter jenis harus hanya mengembalikan bug');
        self::assertSame($idBug, (int) $byJenis['data'][0]['id']);

        $byStatus = $model->getAll(['status' => 'selesai', 'search' => $this->marker], 1, 50);
        self::assertSame(1, $byStatus['total'], 'Filter status harus hanya mengembalikan selesai');
        self::assertSame($idBug, (int) $byStatus['data'][0]['id']);

        $byPeriod = $model->getAll(['year' => $year, 'month' => $month, 'search' => $this->marker], 1, 50);
        self::assertSame(3, $byPeriod['total'], 'Filter tahun+bulan harus mengembalikan semua data marker bulan ini');

        $bySearch = $model->getAll(['search' => $this->marker . '-fitur'], 1, 50);
        self::assertSame(1, $bySearch['total'], 'Pencarian judul harus menemukan feedback yang cocok');
        self::assertSame($idFitur, (int) $bySearch['data'][0]['id']);

        $page1 = $model->getAll(['search' => $this->marker], 1, 2);
        self::assertSame(3, $page1['total'], 'Total harus tetap 3 walau dibatasi per halaman');
        self::assertEquals(2, $page1['totalPages'], 'totalPages = ceil(3/2) = 2');
        self::assertCount(2, $page1['data'], 'Halaman 1 harus berisi 2 item');
    }

    // ==================== Rekap admin & API ====================

    public function testRekapPerPetugasAccuracy(): void
    {
        $model = new Feedback();
        $before = $this->rekapForPetugas($this->petugasAId);
        $beforeB = $this->rekapForPetugas($this->petugasBId);

        $this->createFeedback($this->petugasAId, 'bug');
        $this->createFeedback($this->petugasAId, 'fitur_baru');
        $idSelesai = $this->createFeedback($this->petugasAId, 'peningkatan');
        $model->updateStatus($idSelesai, 'selesai', $this->adminId, 'ok');
        $this->createFeedback($this->petugasBId, 'bug');

        $after = $this->rekapForPetugas($this->petugasAId);
        $afterB = $this->rekapForPetugas($this->petugasBId);

        self::assertSame($before['total'] + 3, $after['total'], 'Total petugas A bertambah 3');
        self::assertSame($before['pending'] + 2, $after['pending'], '2 feedback A tetap diterima');
        self::assertSame($before['completed'] + 1, $after['completed'], '1 feedback A menjadi selesai');
        self::assertSame($before['rejected'], $after['rejected'], 'Tidak ada feedback A ditolak');
        self::assertSame($beforeB['total'] + 1, $afterB['total'], 'Total petugas B bertambah 1');
    }

    public function testAdminSummaryStatsTotals(): void
    {
        $model = new Feedback();
        $before = $model->getAdminSummaryStats([]);

        $this->createFeedback($this->petugasAId, 'bug');
        $idSelesai = $this->createFeedback($this->petugasBId, 'fitur_baru');
        $model->updateStatus($idSelesai, 'ditolak', $this->adminId, 'tidak valid');

        $after = $model->getAdminSummaryStats([]);

        self::assertSame($before['total'] + 2, $after['total']);
        self::assertSame($before['pending'] + 1, $after['pending']);
        self::assertSame($before['in_progress'], $after['in_progress']);
        self::assertSame($before['completed'], $after['completed']);
        self::assertSame($before['rejected'] + 1, $after['rejected']);
        self::assertSame($before['bugs'] + 1, $after['bugs']);
        self::assertSame($before['features'] + 1, $after['features']);
        self::assertSame($before['improvements'], $after['improvements']);
    }

    public function testApiRoutesAreAdminOnly(): void
    {
        $router = file_get_contents(ROOT_PATH . '/app/core/Router.php');
        self::assertStringContainsString(
            "'/api/feedback/summary', 'Api\\FeedbackController@summary', ['auth', 'admin']",
            $router,
            'GET /api/feedback/summary harus memakai middleware auth+admin'
        );
        self::assertStringContainsString(
            "'/api/feedback', 'Api\\FeedbackController@index', ['auth', 'admin']",
            $router,
            'GET /api/feedback harus memakai middleware auth+admin'
        );
    }

    public function testWebRoutesIncludeAdminSummary(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertArrayHasKey('feedback', $routes);
        self::assertSame('Feedback@index', $routes['feedback']);
        self::assertArrayHasKey('feedback/admin-summary', $routes);
        self::assertSame('Feedback@adminSummary', $routes['feedback/admin-summary']);
        self::assertArrayHasKey('feedback/create', $routes);
        self::assertSame('Feedback@create', $routes['feedback/create']);
    }

    // ==================== Helpers ====================

    private function createFeedback(int $userId, string $jenis = 'bug'): int
    {
        $id = (new Feedback())->create([
            'user_id'        => $userId,
            'jenis_feedback' => $jenis,
            'judul'          => $this->marker . '-' . $jenis . '-' . bin2hex(random_bytes(3)),
            'deskripsi'      => 'Deskripsi pengujian ' . $this->marker . ' minimal 20 karakter.',
            'prioritas'      => 'medium',
        ]);

        self::assertNotFalse($id, 'create() harus sukses');
        return (int) $id;
    }

    private function deleteFeedbackById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM feedback WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function rekapForPetugas(int $userId): array
    {
        $rows = (new Feedback())->getRekapPerPetugas(['user_id' => $userId]);
        $row = $rows[0] ?? [];
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected'  => (int) ($row['rejected'] ?? 0),
        ];
    }

    private function findUserId(string $role, int $excludeId = 0): int
    {
        $sql = 'SELECT id FROM users WHERE role = ? AND id != ? ORDER BY id LIMIT 1';
        $stmt = $this->db->prepare($sql);
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