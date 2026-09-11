<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StatistisiFeatureContractTest extends TestCase
{
    public function testStatistisiNavigationExposesEvaluationAndStorytelling(): void
    {
        $header = (string) file_get_contents(ROOT_PATH . '/app/views/layouts/header.php');

        self::assertStringContainsString("['admin', 'statistisi']", $header);
        self::assertStringContainsString('Evaluasi Akurasi', $header);
        self::assertStringContainsString('Data Storytelling', $header);
    }

    public function testEvaluationReadEndpointsAllowStatistisiButMutationsRemainAdminOnly(): void
    {
        $controller = (string) file_get_contents(ROOT_PATH . '/app/controllers/EvaluasiController.php');

        foreach (['index', 'getData', 'getChartData', 'getStatistics', 'getRecord', 'getLogs'] as $method) {
            self::assertStringContainsString(
                '$this->checkEvaluationReadAccess();',
                $this->methodBody($controller, $method),
                "{$method} harus memakai policy baca statistik"
            );
        }

        foreach (['generateSnapshot', 'storeRilis', 'store', 'update', 'delete', 'importExcel', 'previewImport', 'downloadTemplate'] as $method) {
            self::assertStringContainsString(
                '$this->checkAdmin();',
                $this->methodBody($controller, $method),
                "{$method} harus tetap khusus Admin"
            );
        }
    }

    public function testEvaluationViewIsReadOnlyForStatistisi(): void
    {
        $view = (string) file_get_contents(ROOT_PATH . '/app/views/evaluasi/index.php');

        self::assertStringContainsString('if ($canManageEvaluation)', $view);
        self::assertStringContainsString('Mode baca Statistisi', $view);
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, "public function {$method}(");
        self::assertNotFalse($start, "Method {$method} wajib tersedia");
        $next = strpos($source, 'public function ', $start + 16);

        return substr($source, $start, ($next === false ? strlen($source) : $next) - $start);
    }
}
