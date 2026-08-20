<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PetugasDashboardSimplificationTest extends TestCase
{
    public function testPetugasDashboardContainsRequiredCardsChartsAndRecentPanels(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/dashboard/petugas.php');
        foreach ([
            'Fenomena Hama',
            'Fenomena Irigasi',
            'Fenomena Lainnya',
            'Tren Laporan Lainnya',
            'Laporan Hama Terbaru',
            'Laporan Irigasi Terbaru',
            'Laporan Lainnya Terbaru',
            'Lihat Semua',
        ] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringNotContainsString('Peta Sebaran', $view);
        self::assertStringNotContainsString('Ringkasan Kinerja', $view);
        self::assertStringContainsString('array_slice($items, 0, 3)', $view);
    }

    public function testChartsLainnyaApiScopesEveryQueryToAuthenticatedUser(): void
    {
        $controller = file_get_contents(
            __DIR__ . '/../../backend/app/Controllers/Api/DashboardController.php'
        );
        self::assertStringContainsString("!== 'petugas'", $controller);
        self::assertGreaterThanOrEqual(2, substr_count($controller, 'll.user_id = :user_id'));
        self::assertStringContainsString('(int) $currentUser[\'id\']', $controller);
    }

    public function testFeedbackPetugasViewHasNoGlobalSummaryOrVoting(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/views/feedback/petugas_history.php');
        self::assertStringContainsString('Saran dan Aduan Saya', $view);
        self::assertStringContainsString('Kirim Saran', $view);
        self::assertStringNotContainsString('Total Masukan', $view);
        self::assertStringNotContainsString('Saran Populer', $view);
        self::assertStringNotContainsString('toggleVote', $view);
    }
}
