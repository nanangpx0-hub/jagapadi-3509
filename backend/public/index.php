<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Security;

ErrorHandler::register();

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($timezone);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

$cacheDir = BASE_PATH . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

Security::initSession();

$router = new Router();

$routesPath = BASE_PATH . '/config/routes.php';
if (file_exists($routesPath)) {
    require $routesPath;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$pos = strpos($uri, '?');
if ($pos !== false) {
    $uri = substr($uri, 0, $pos);
}

$router->dispatch($method, $uri);
