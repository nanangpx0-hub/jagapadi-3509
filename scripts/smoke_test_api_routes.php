<?php
/**
 * Smoke test: validate every API route handler points
 * to an existing controller file and method.
 *
 * Usage:
 *   php scripts/smoke_test_api_routes.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$routerFile = $root . '/app/core/Router.php';

if (!file_exists($routerFile)) {
    fwrite(STDERR, "[FAIL] Router file not found: {$routerFile}\n");
    exit(1);
}

$content = file_get_contents($routerFile);
if ($content === false) {
    fwrite(STDERR, "[FAIL] Unable to read router file\n");
    exit(1);
}

$pattern = '/\\$this->(get|post|put|delete)\\(\'\\/api\\/([^\']*)\',\\s*\'([^\']+)\'/m';
preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

if (empty($matches)) {
    fwrite(STDERR, "[FAIL] No API route definitions found in Router\n");
    exit(1);
}

$errors = [];
$checked = 0;

foreach ($matches as $match) {
    $httpMethod = strtoupper($match[1]);
    $apiPath = '/api/' . $match[2];
    $handler = $match[3];
    $checked++;

    if (!str_contains($handler, '@')) {
        $errors[] = "{$httpMethod} {$apiPath} => invalid handler format: {$handler}";
        continue;
    }

    [$controller, $method] = explode('@', $handler, 2);
    $controllerPath = str_replace('\\', '/', $controller);
    if (!str_ends_with($controllerPath, 'Controller')) {
        $controllerPath .= 'Controller';
    }

    $controllerFile = $root . '/app/controllers/' . $controllerPath . '.php';
    if (!file_exists($controllerFile)) {
        $errors[] = "{$httpMethod} {$apiPath} => missing controller file: {$controllerFile}";
        continue;
    }

    $controllerSource = file_get_contents($controllerFile);
    if ($controllerSource === false) {
        $errors[] = "{$httpMethod} {$apiPath} => unreadable controller file: {$controllerFile}";
        continue;
    }

    $methodPattern = '/function\\s+' . preg_quote($method, '/') . '\\s*\\(/';
    if (!preg_match($methodPattern, $controllerSource)) {
        $errors[] = "{$httpMethod} {$apiPath} => missing method {$method} in {$controllerFile}";
    }
}

echo "=== API Route Smoke Test ===\n";
echo "Routes checked: {$checked}\n";

if (!empty($errors)) {
    echo "Status: FAIL\n\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Status: PASS\n";
echo "All API routes point to valid controller files and methods.\n";
exit(0);
