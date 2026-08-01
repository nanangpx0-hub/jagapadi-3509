<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$paths = [
    $basePath . '/app',
    $basePath . '/config',
    $basePath . '/public',
    $basePath . '/scripts',
];

$files = [];
foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }
}

sort($files);
$errors = 0;
$linted = 0;

foreach ($files as $file) {
    $command = 'php -l ' . escapeshellarg($file);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $errors++;
        echo implode(PHP_EOL, $output) . PHP_EOL;
        continue;
    }

    $linted++;
}

if ($errors > 0) {
    fwrite(STDERR, sprintf("Lint failed for %d file(s).%s", $errors, PHP_EOL));
    exit(1);
}

echo sprintf("Linted %d PHP file(s) successfully.%s", $linted, PHP_EOL);
exit(0);
