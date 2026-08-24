<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OptBulkDeleteContractTest extends TestCase
{
    public function testBulkDeleteRoutesAndAdminGuardsArePresent(): void
    {
        $routes = file_get_contents(ROOT_PATH . '/config/web_routes.php');
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/OptController.php');

        self::assertStringContainsString("'opt/bulk-delete' => 'Opt@bulkDelete'", $routes);
        self::assertStringContainsString("'opt/delete-all' => 'Opt@deleteAll'", $routes);
        self::assertMatchesRegularExpression(
            '/function bulkDelete\(\): void\s*\{\s*\$this->checkRole\(\[\'admin\'\]\);\s*\$this->requireStateChangingRequest\(\[\'POST\'\]\);/s',
            $controller
        );
        self::assertMatchesRegularExpression(
            '/function deleteAll\(\): void\s*\{\s*\$this->checkRole\(\[\'admin\'\]\);\s*\$this->requireStateChangingRequest\(\[\'POST\'\]\);/s',
            $controller
        );
        self::assertStringContainsString('isUsedInReports($id)', $controller);
    }

    public function testAdminViewHasIndividualSelectAllAndDeleteControls(): void
    {
        $view = file_get_contents(ROOT_PATH . '/app/views/opt/index.php');

        self::assertStringContainsString('id="optSelectAll"', $view);
        self::assertStringContainsString('class="opt-row-checkbox"', $view);
        self::assertStringContainsString('id="optBulkDeleteButton"', $view);
        self::assertStringContainsString('id="optDeleteAllButton"', $view);
        self::assertStringContainsString("input.name = 'ids[]'", $view);
        self::assertStringContainsString("(\$_SESSION['role'] ?? '') === 'admin'", $view);
    }

    public function testPetugasHasReadOnlyMasterOptAccess(): void
    {
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/OptController.php');
        $header = file_get_contents(ROOT_PATH . '/app/views/layouts/header.php');
        $view = file_get_contents(ROOT_PATH . '/app/views/opt/index.php');

        self::assertStringContainsString("['admin', 'operator', 'petugas'], true", $header);
        self::assertStringContainsString('Mode Baca Saja.', $view);
        self::assertStringContainsString("'read_only' => (\$_SESSION['role'] ?? '') === 'petugas'", $controller);
        self::assertSame(2, substr_count($controller, "checkRole(['admin', 'operator', 'petugas'])"));

        foreach (['create', 'edit', 'delete', 'bulkDelete', 'deleteAll', 'uploadPhoto', 'deletePhoto'] as $method) {
            self::assertMatchesRegularExpression(
                '/function ' . preg_quote($method, '/') . '\([^)]*\)(?:: void)?\s*\{\s*\$this->checkRole\(\[\'admin\'\]\);/s',
                $controller,
                $method . ' harus tetap khusus Admin'
            );
        }
    }

    public function testOptEditPageDisablesFloatingAnimationLocally(): void
    {
        $view = file_get_contents(ROOT_PATH . '/app/views/opt/edit.php');

        self::assertStringContainsString('class="row opt-edit-page"', $view);
        self::assertStringContainsString('.opt-edit-page *::before', $view);
        self::assertStringContainsString('animation: none !important;', $view);
        self::assertStringContainsString('transition: none !important;', $view);
        self::assertStringContainsString('transform: none !important;', $view);
    }

    public function testAutomaticOptPhotoFillIsAdminOnlyAndUsesSafeSource(): void
    {
        $routes = file_get_contents(ROOT_PATH . '/config/web_routes.php');
        $controller = file_get_contents(ROOT_PATH . '/app/controllers/OptController.php');
        $service = file_get_contents(ROOT_PATH . '/app/services/OptAutoPhotoService.php');
        $view = file_get_contents(ROOT_PATH . '/app/views/opt/index.php');

        self::assertStringContainsString("'opt/auto-fill-photos' => 'Opt@autoFillPhotos'", $routes);
        self::assertMatchesRegularExpression(
            '/function autoFillPhotos\(\): void\s*\{\s*\$this->checkRole\(\[\'admin\'\]\);\s*\$this->requireStateChangingRequest\(\[\'POST\'\]\);\s*\$this->validateCsrfToken\(\);/s',
            $controller
        );
        self::assertStringContainsString('opt/auto-fill-photos', $view);
        self::assertStringContainsString("private const ALLOWED_IMAGE_HOST = 'upload.wikimedia.org'", $service);
        self::assertStringContainsString('getimagesize($temp)', $service);
        self::assertStringContainsString("WHERE (foto_url IS NULL OR TRIM(foto_url) = '')", $service);
    }
}
