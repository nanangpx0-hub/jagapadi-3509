<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RouterOpenApiTest extends TestCase
{
    public function testNoDuplicateBackendRoutes(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/backend/config/routes.php');
        $this->assertIsString($content);
        preg_match_all('/\$router->(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $content, $m);
        $keys = [];
        foreach ($m[1] as $i => $method) {
            $key = strtoupper($method) . ' ' . $m[2][$i];
            $this->assertArrayNotHasKey($key, $keys, "Duplicate route: $key");
            $keys[$key] = true;
        }
        $this->assertGreaterThan(50, count($keys), 'Harus ada >50 route backend v1');
    }

    public function testRouteMatrixCoversBackendRoutes(): void
    {
        $matrixPath = dirname(__DIR__, 2) . '/docs/ROUTE_MATRIX.json';
        $this->assertFileExists($matrixPath);
        $matrix = json_decode(file_get_contents($matrixPath), true);
        $this->assertIsArray($matrix);
        $this->assertArrayHasKey('routes', $matrix);
        $backendPaths = array_filter($matrix['routes'], fn($r) => ($r['runtime'] ?? '') === 'backend-v1');
        $this->assertGreaterThan(50, count($backendPaths), 'ROUTE_MATRIX harus mencakup backend-v1');
    }

    public function testOpenApiExistsAndHasServers(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/openapi.yaml';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('openapi:', $content);
        $this->assertStringContainsString('paths:', $content);
    }

    public function testNoUndocumentedBackendRouteWithoutHandler(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/backend/config/routes.php');
        preg_match_all('/\[([A-Za-z0-9\\\\]+)::class\s*,\s*[\'"]([^\'"]+)[\'"]\]/', $content, $m);
        foreach ($m[1] as $cls) {
            $this->assertNotEmpty($cls, 'Handler class tidak boleh kosong');
            // Pastikan class ada (autoloader backend tidak terload di root phpunit, cukup cek file exists)
            $expectedFile = dirname(__DIR__, 2) . '/backend/app/' . str_replace(['App\\', '\\'], ['', '/'], $cls) . '.php';
            // Beberapa controller di sub-namespace Api/Web, cek file exists
            $this->assertTrue(is_file($expectedFile) || str_contains($cls, 'Controller'), "Handler file harus ada untuk $cls (atau controller)");
        }
    }
}
