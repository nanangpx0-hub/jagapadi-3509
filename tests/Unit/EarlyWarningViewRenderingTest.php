<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EarlyWarningViewRenderingTest extends TestCase
{
    private string $viewContent;

    protected function setUp(): void
    {
        parent::setUp();
        $viewPath = dirname(__DIR__, 2) . '/app/views/early_warning/index.php';
        self::assertFileExists($viewPath);
        $this->viewContent = (string) file_get_contents($viewPath);
    }

    public function testViewDoesNotHaveRedundantNestedWrappers(): void
    {
        // Must not contain duplicate content-wrapper or content-header
        self::assertStringNotContainsString(
            '<div class="content-wrapper',
            $this->viewContent,
            'index.php must not introduce nested content-wrapper (already in header.php)'
        );
        self::assertStringNotContainsString(
            '<div class="content-header',
            $this->viewContent,
            'index.php must not introduce nested content-header (already in header.php)'
        );
    }

    public function testViewContainsHeroBannerAndKpis(): void
    {
        self::assertStringContainsString('ews-hero-card', $this->viewContent);
        self::assertStringContainsString('kpi-ews-card danger', $this->viewContent);
        self::assertStringContainsString('kpi-ews-card warning', $this->viewContent);
        self::assertStringContainsString('kpi-ews-card success', $this->viewContent);
    }

    public function testViewContainsTabsAndVisionTools(): void
    {
        self::assertStringContainsString('id="radar-pane"', $this->viewContent);
        self::assertStringContainsString('id="vision-pane"', $this->viewContent);
        self::assertStringContainsString('id="catalog-pane"', $this->viewContent);
        self::assertStringContainsString('id="agent-pane"', $this->viewContent);
        self::assertStringContainsString('btnSampleWbc', $this->viewContent);
        self::assertStringContainsString('btnSamplePbpk', $this->viewContent);
    }
}
