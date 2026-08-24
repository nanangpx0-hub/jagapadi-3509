<?php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

date_default_timezone_set('Asia/Jakarta');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'phpunit';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/phpunit';

spl_autoload_register(static function (string $class): void {
    $classPath = str_replace('\\', '/', $class);
    $baseName = basename($classPath);
    $paths = [
        ROOT_PATH . '/app/core/' . $classPath . '.php',
        ROOT_PATH . '/app/models/' . $classPath . '.php',
        ROOT_PATH . '/app/controllers/' . $classPath . '.php',
        ROOT_PATH . '/app/helpers/' . $classPath . '.php',
        ROOT_PATH . '/app/services/' . $classPath . '.php',
        ROOT_PATH . '/app/middleware/' . $classPath . '.php',
        ROOT_PATH . '/app/core/' . $baseName . '.php',
        ROOT_PATH . '/app/models/' . $baseName . '.php',
        ROOT_PATH . '/app/helpers/' . $baseName . '.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});
