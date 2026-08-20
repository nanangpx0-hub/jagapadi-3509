<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PetugasReportUiConsistencyTest extends TestCase
{
    public function testHamaAndIrigasiUseTheSamePetugasListPartial(): void
    {
        $hama = file_get_contents(__DIR__ . '/../../app/views/laporan/index.php');
        $irigasi = file_get_contents(__DIR__ . '/../../app/views/irigasi/index.php');
        $sharedPath = "app/views/reports/petugas_list.php";

        self::assertStringContainsString($sharedPath, $hama);
        self::assertStringContainsString($sharedPath, $irigasi);
    }

    public function testSharedListProvidesTheRequiredInteractionPattern(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/reports/petugas_list.php');
        foreach (['name="status"', 'name="date_from"', 'name="date_to"', 'name="search"', 'name="per_page"', 'table-responsive', 'pagination'] as $element) {
            self::assertStringContainsString($element, $view);
        }
    }

    public function testPetugasModeMessageWasRemovedFromAllReportViews(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../app/views'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            self::assertStringNotContainsString('Mode Petugas', $source, $file->getPathname());
            self::assertStringNotContainsString('Anda hanya dapat melihat laporan yang Anda buat sendiri', $source, $file->getPathname());
        }
    }

    public function testControllersEnforcePetugasOwnershipAtQueryBoundary(): void
    {
        $hama = file_get_contents(__DIR__ . '/../../app/controllers/LaporanController.php');
        $irigasi = file_get_contents(__DIR__ . '/../../app/controllers/IrigasiController.php');
        self::assertStringContainsString("fetchPaginated(\$filters, \$page, \$perPage, (int) \$user['id'])", $hama);
        self::assertStringContainsString("\$userId = \$user['role'] === 'petugas' ? (int) \$user['id'] : null", $irigasi);
    }
}
