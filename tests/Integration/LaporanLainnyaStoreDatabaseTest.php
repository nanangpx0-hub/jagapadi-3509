<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LaporanLainnyaStoreDatabaseTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function testCreateFormUsesFreshCsrfHelperAndExplicitStoreRoute(): void
    {
        $view = file_get_contents(ROOT_PATH . '/app/views/laporan-lainnya/create.php');
        self::assertStringContainsString('action="<?= BASE_URL ?>laporan-lainnya/store"', $view);
        self::assertStringContainsString('method="POST"', $view);
        self::assertStringContainsString('Security::getCsrfField()', $view);
        self::assertStringNotContainsString("\$_SESSION['csrf_token'] ?? ''", $view);

        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('LaporanLainnya@store', $routes['laporan-lainnya/store'] ?? null);

        $controller = file_get_contents(ROOT_PATH . '/app/controllers/LaporanLainnyaController.php');
        $start = strpos($controller, 'public function store()');
        self::assertNotFalse($start);
        $next = strpos($controller, 'public function ', $start + 20);
        $body = substr($controller, $start, ($next ?: strlen($controller)) - $start);
        self::assertStringContainsString('requireStateChangingRequest()', $body);
        self::assertStringContainsString('createReport($reportData)', $body);
    }

    public function testValidReportCanBePersistedAndReadBack(): void
    {
        $userId = (int) $this->db->query("SELECT id FROM users WHERE aktif = 1 ORDER BY id LIMIT 1")->fetchColumn();
        $jenisId = (int) $this->db->query("SELECT id FROM master_jenis_laporan WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
        if ($userId <= 0 || $jenisId <= 0) {
            self::markTestSkipped('Fixture user dan jenis laporan aktif diperlukan.');
        }

        $this->db->beginTransaction();
        try {
            $id = (new LaporanLainnya())->createReport([
                'user_id' => $userId,
                'jenis_id' => $jenisId,
                'kode_laporan' => null,
                'tanggal_kejadian' => date('Y-m-d'),
                'data_json' => json_encode(['test' => 'store'], JSON_THROW_ON_ERROR),
                'deskripsi' => 'Fixture pengujian penyimpanan laporan lainnya',
                'status' => 'draft',
            ]);

            self::assertGreaterThan(0, $id);
            $stmt = $this->db->prepare(
                'SELECT user_id, jenis_id, status, deleted_at FROM laporan_lainnya WHERE id = ?'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($row);
            self::assertSame($userId, (int) $row['user_id']);
            self::assertSame($jenisId, (int) $row['jenis_id']);
            self::assertSame('draft', $row['status']);
            self::assertNull($row['deleted_at']);

            $model = new LaporanLainnya();
            self::assertContains(
                $id,
                array_map('intval', array_column(
                    $model->getAllWithFilters(['include_draft' => true], 500, 0),
                    'id'
                )),
                'Draf yang baru disimpan harus muncul pada daftar pengelolaan'
            );
            self::assertContains(
                $id,
                array_map('intval', array_column(
                    $model->getAllWithFilters(['status' => 'draft', 'include_draft' => false], 500, 0),
                    'id'
                )),
                'Filter status Draf harus tetap berfungsi saat checkbox sertakan draf tidak aktif'
            );
        } finally {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    public function testManagementListDefaultsToShowingDraftAndPreservesFilterInPagination(): void
    {
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/LaporanLainnyaController.php');
        self::assertStringContainsString(": true;", $controller);

        $view = file_get_contents(ROOT_PATH . '/app/views/laporan-lainnya/index.php');
        self::assertStringContainsString('name="include_draft" value="false"', $view);
        self::assertStringContainsString("'&include_draft='", $view);
    }
}
