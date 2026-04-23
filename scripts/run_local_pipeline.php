<?php
/**
 * Local pre-deploy pipeline:
 * 1) PHP syntax lint
 * 2) Basic unit test
 * 3) API route smoke test
 *
 * Usage:
 *   php scripts/run_local_pipeline.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

function runStep(string $title, string $command, string $cwd): int
{
    echo "\n=== {$title} ===\n";
    echo "Command: {$command}\n\n";

    $descriptorspec = [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ];

    $process = proc_open($command, $descriptorspec, $pipes, $cwd);
    if (!is_resource($process)) {
        fwrite(STDERR, "[FAIL] Cannot start process: {$command}\n");
        return 1;
    }

    return proc_close($process);
}

$steps = [
    [
        'title' => 'PHP Lint',
        'command' => 'php -l app/core/Router.php && php -l app/controllers/Api/BaseApiController.php && php -l app/models/LaporanHama.php && php -l app/models/User.php && php -l app/models/MasterOpt.php && php -l app/models/Irigasi.php && php -l app/models/Wilayah.php',
    ],
    [
        'title' => 'Basic Unit Test',
        'command' => 'php tests/Unit/CurahHujanValidatorTest.php',
    ],
    [
        'title' => 'API Route Smoke Test',
        'command' => 'php scripts/smoke_test_api_routes.php',
    ],
];

$failed = false;

foreach ($steps as $step) {
    $exitCode = runStep($step['title'], $step['command'], $root);
    if ($exitCode !== 0) {
        $failed = true;
        fwrite(STDERR, "\n[FAIL] {$step['title']} failed with exit code {$exitCode}\n");
        break;
    }
    echo "\n[PASS] {$step['title']}\n";
}

echo "\n=== Pipeline Result ===\n";
if ($failed) {
    echo "FAILED\n";
    exit(1);
}

echo "PASSED\n";
exit(0);
