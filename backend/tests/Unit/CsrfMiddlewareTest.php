<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private array $serverBackup = [];
    private array $postBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        http_response_code(200);
    }

    public function testPostTanpaTokenDitolak403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/laporan-hama/1';

        ob_start();
        $result = (new CsrfMiddleware())->handle([], []);
        ob_end_clean();

        $this->assertFalse($result);
        $this->assertSame(403, http_response_code());
    }

    public function testPostTanpaTokenVersiJsonMengembalikanEnvelope(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/laporan-hama/1';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        $result = (new CsrfMiddleware())->handle([], []);
        $body = (string) ob_get_clean();

        $this->assertFalse($result);
        $this->assertSame(403, http_response_code());
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Forbidden', $decoded['error'] ?? null);
    }

    public function testGetTidakDicekCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/laporan-hama';

        $this->assertTrue((new CsrfMiddleware())->handle([], []));
    }

    public function testSubfolderApiUsesApplicationRelativePath(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/jagapadi-3509/backend/public/index.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/jagapadi-3509/backend/public/api/v1/auth/login';

        $this->assertTrue((new CsrfMiddleware())->handle([], []));
    }

    public function testSubfolderWebStillRequiresCsrf(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/jagapadi-3509/backend/public/index.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/jagapadi-3509/backend/public/login';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        try {
            $result = (new CsrfMiddleware())->handle([], []);
        } finally {
            ob_end_clean();
        }
        $this->assertFalse($result);
        $this->assertSame(403, http_response_code());
    }

    public function testApiPathDilewati(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/laporan-hama';

        $this->assertTrue((new CsrfMiddleware())->handle([], []));
    }
}
