<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StorytellingContractTest extends TestCase
{
    public function testWebRoutesContainStorytellingEndpoints(): void
    {
        $routes = require dirname(__DIR__, 2) . '/config/web_routes.php';
        self::assertIsArray($routes);

        self::assertArrayHasKey('storytelling', $routes);
        self::assertSame('Storytelling@index', $routes['storytelling']);

        self::assertArrayHasKey('storytelling/generateAnalysis', $routes);
        self::assertSame('Storytelling@generateAnalysis', $routes['storytelling/generateAnalysis']);

        self::assertArrayHasKey('storytelling/store', $routes);
        self::assertSame('Storytelling@store', $routes['storytelling/store']);

        self::assertArrayHasKey('storytelling/getChartData', $routes);
        self::assertSame('Storytelling@getChartData', $routes['storytelling/getChartData']);

        self::assertArrayHasKey('storytelling/runMethod', $routes);
        self::assertSame('Storytelling@runMethod', $routes['storytelling/runMethod']);

        self::assertArrayHasKey('storytelling/publish', $routes);
        self::assertSame('Storytelling@publish', $routes['storytelling/publish']);
    }

    public function testControllerHasRequiredPublicMethods(): void
    {
        $ref = new ReflectionClass(StorytellingController::class);
        $expectedMethods = [
            'index',
            'generateAnalysis',
            'store',
            'getChartData',
            'runMethod',
            'getRecent',
            'getAnalysis',
            'publish',
            'exportCsv',
            'exportDossier',
        ];

        foreach ($expectedMethods as $method) {
            self::assertTrue(
                $ref->hasMethod($method),
                "StorytellingController harus memiliki method {$method}"
            );
            self::assertTrue(
                $ref->getMethod($method)->isPublic(),
                "Method {$method} harus bertipe public"
            );
        }
    }

    public function testStorytellingAnalysisServiceSupportsEcosystemVariables(): void
    {
        $service = new StorytellingAnalysisService();
        $ref = new ReflectionClass($service);
        $const = $ref->getConstant('SUPPORTED_VARIABLES');

        self::assertIsArray($const);
        self::assertContains('rain', $const);
        self::assertContains('pest', $const);
        self::assertContains('irrigation', $const);
        self::assertContains('wind', $const);
    }
}
