<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EarlyWarningNavigationContractTest extends TestCase
{
    private string $headerContent;

    protected function setUp(): void
    {
        parent::setUp();
        $headerPath = dirname(__DIR__, 2) . '/app/views/layouts/header.php';
        self::assertFileExists($headerPath);
        $this->headerContent = (string) file_get_contents($headerPath);
    }

    public function testTopNavbarContainsEarlyWarningLink(): void
    {
        self::assertStringContainsString(
            'href="<?= BASE_URL ?>earlyWarning"',
            $this->headerContent,
            'Top navbar or sidebar must link to earlyWarning controller'
        );

        self::assertStringContainsString(
            'Early Warning (EWS)',
            $this->headerContent,
            'Top navbar must have visible label "Early Warning (EWS)"'
        );

        self::assertStringContainsString(
            'fa-shield-virus',
            $this->headerContent,
            'Early Warning menu must feature the shield-virus icon'
        );
    }

    public function testSidebarContainsEarlyWarningMenuItem(): void
    {
        self::assertStringContainsString(
            'data-sidebar-menu="early-warning"',
            $this->headerContent,
            'Sidebar must include data-sidebar-menu="early-warning"'
        );

        self::assertStringContainsString(
            'Early Warning Hama',
            $this->headerContent,
            'Sidebar must have menu text "Early Warning Hama"'
        );

        self::assertStringContainsString(
            '<span class="right badge badge-danger">EWS</span>',
            $this->headerContent,
            'Sidebar must include the EWS badge indicator'
        );
    }

    public function testActiveStateVariableIsDeclared(): void
    {
        self::assertStringContainsString(
            '$earlyWarningMenuActive',
            $this->headerContent,
            'header.php must define $earlyWarningMenuActive state'
        );

        self::assertStringContainsString(
            "SidebarState::matches(\$sidebarRoute, 'earlyWarning')",
            $this->headerContent,
            'State check must match earlyWarning route'
        );
    }
}
