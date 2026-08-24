<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UsulanOptExcelContractTest extends TestCase
{
    public function testRoutesUiAndOwnershipContractArePresent(): void
    {
        $routes = require ROOT_PATH . '/config/web_routes.php';
        self::assertSame('UsulanOpt@index', $routes['optsaya'] ?? null);
        self::assertSame('UsulanOpt@importExcel', $routes['optsaya/import'] ?? null);
        self::assertSame('UsulanOpt@exportExcel', $routes['optsaya/export'] ?? null);
        self::assertSame('UsulanOpt@downloadTemplate', $routes['optsaya/template'] ?? null);

        $controller = file_get_contents(ROOT_PATH . '/app/controllers/UsulanOptController.php');
        self::assertNotFalse($controller);
        self::assertStringContainsString('$ownerId = (int) $_SESSION[\'user_id\']', $controller);
        self::assertStringContainsString('requireStateChangingRequest([\'POST\'])', $controller);
        self::assertStringContainsString('$userId = $isAdmin ? null : (int) $_SESSION[\'user_id\']', $controller);

        $view = file_get_contents(ROOT_PATH . '/app/views/usulan-opt/index.php');
        self::assertNotFalse($view);
        self::assertStringContainsString('name="csrf_token"', $view);
        self::assertStringContainsString('Impor Excel', $view);
        self::assertStringContainsString('Ekspor Excel', $view);
        self::assertStringContainsString('Unduh Template', $view);

        self::assertNotContains('user_id', UsulanOptExcelService::IMPORT_HEADERS);
        self::assertNotContains('status', UsulanOptExcelService::IMPORT_HEADERS);
        self::assertNotContains('reviewed_by', UsulanOptExcelService::IMPORT_HEADERS);
    }
}
