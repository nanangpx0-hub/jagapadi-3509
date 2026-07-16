<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\CacheManager;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Request;
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
CacheManager::init($cacheDir, 300);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS — restricted origins; read from env in production
$allowedOrigins = [];
$corsFromEnv = Env::get('CORS_ALLOWED_ORIGINS', '');
if ($corsFromEnv !== '') {
    $allowedOrigins = array_map('trim', explode(',', $corsFromEnv));
}
if (empty($allowedOrigins)) {
    $allowedOrigins = ['http://localhost:8080', 'http://localhost:3000', 'http://10.0.2.2:8080'];
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}
if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$csp = "default-src 'self'; "
    . "img-src 'self' data: blob:; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "connect-src 'self'; "
    . "font-src 'self' data:; "
    . "object-src 'none'; "
    . "frame-ancestors 'none';";
header("Content-Security-Policy: $csp");

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
