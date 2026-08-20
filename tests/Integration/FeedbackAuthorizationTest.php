<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeedbackAuthorizationTest extends TestCase
{
    private ?PDO $db = null;

    protected function tearDown(): void
    {
        if ($this->db?->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testPetugasWebEntryPointsAreRoleRestricted(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/controllers/FeedbackController.php');
        self::assertNotFalse($source);
        self::assertStringContainsString('private const PETUGAS_ROLE', $source);

        foreach (['index', 'create', 'vote'] as $method) {
            self::assertMatchesRegularExpression(
                '/function ' . $method . '\([^)]*\).*?checkRole\(self::PETUGAS_ROLE\)/s',
                $source,
                $method . ' harus eksklusif untuk Petugas'
            );
        }
    }

    public function testAdminWebEntryPointsAreAdminOnly(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/controllers/FeedbackController.php');
        self::assertNotFalse($source);

        foreach (['adminSummary', 'report', 'updateStatus', 'delete'] as $method) {
            self::assertMatchesRegularExpression(
                '/function ' . $method . '\([^)]*\).*?checkRole\(\[\'admin\'\]\)/s',
                $source,
                $method . ' harus eksklusif untuk Admin'
            );
        }
    }

    public function testDetailEntryPointAllowsPetugasAndAdminWithOwnershipCheck(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/controllers/FeedbackController.php');
        self::assertNotFalse($source);
        self::assertStringContainsString('private const DETAIL_ROLES = [\'admin\', \'petugas\'];', $source);
        self::assertStringContainsString('checkRole(self::DETAIL_ROLES)', $source);
        self::assertStringContainsString("if (!\$isAdmin && (int) \$feedback['user_id'] !== (int) \$user['id'])", $source);
    }

    public function testAdminEndpointsHaveRouterLevelAuthorization(): void
    {
        $router = file_get_contents(__DIR__ . '/../../app/core/Router.php');
        self::assertNotFalse($router);
        self::assertStringContainsString("'/api/feedback/summary', 'Api\\FeedbackController@summary', ['auth', 'admin']", $router);
        self::assertStringContainsString("'/api/feedback', 'Api\\FeedbackController@index', ['auth', 'admin']", $router);
    }

    public function testPetugasListAlwaysAppliesOwnership(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/controllers/FeedbackController.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/function index\(\).*?\$filters\[\'user_id\'\] = \(int\) \$user\[\'id\'\]/s',
            $source
        );
    }

    public function testFeedbackIsPersistedAndSummaryIsAccurate(): void
    {
        $this->loadEnvironment();
        $this->db = Database::getInstance()->getConnection();
        $userId = (int) $this->db->query("SELECT id FROM users WHERE role = 'petugas' AND aktif = 1 LIMIT 1")->fetchColumn();
        if ($userId <= 0) {
            self::markTestSkipped('Akun Petugas aktif tidak tersedia.');
        }

        $this->db->beginTransaction();
        $model = new Feedback();
        $before = $model->getDashboardStatsByUser($userId);
        $marker = 'CODEX-FEEDBACK-' . bin2hex(random_bytes(4));
        $id = $model->create([
            'user_id' => $userId,
            'jenis_feedback' => 'peningkatan',
            'judul' => $marker,
            'deskripsi' => 'Deskripsi masukan integrasi yang valid dan lengkap.',
            'prioritas' => 'medium',
        ]);

        self::assertIsInt($id);
        self::assertSame($marker, $model->getById($id)['judul']);
        self::assertSame($before['total'] + 1, $model->getDashboardStatsByUser($userId)['total']);
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM feedback_status_history WHERE feedback_id = ' . $id)->fetchColumn());
    }

    private function loadEnvironment(): void
    {
        foreach ([dirname(__DIR__, 2) . '/.env', dirname(__DIR__, 2) . '/.env.local'] as $path) {
            if (!is_file($path)) continue;
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                putenv($key . '=' . $value);
            }
        }
    }
}
