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

// Daftar autoload helper
$viewHelperPath = BASE_PATH . '/app/helpers/ViewHelper.php';
if (file_exists($viewHelperPath)) {
    require_once $viewHelperPath;
}

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

// Trace/request ID: dipakai untuk korelasi antar log & header respons.
$incomingRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
$requestId = (is_string($incomingRequestId) && preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,63}$/', $incomingRequestId))
    ? $incomingRequestId
    : bin2hex(random_bytes(8));
$_SERVER['JAGAPADI_REQUEST_ID'] = $requestId;
header('X-Request-ID: ' . $requestId);
$GLOBALS['request_started_at'] = microtime(true);

// Akses log terstruktur (tanpa token/password) untuk observability.
register_shutdown_function(static function (): void {
    $user = $GLOBALS['auth_user'] ?? null;
    $status = http_response_code();
    $durationMs = round((microtime(true) - ($GLOBALS['request_started_at'] ?? microtime(true))) * 1000, 1);
    Logger::info('request', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
        'status' => $status,
        'duration_ms' => $durationMs,
        'user_id' => $user['id'] ?? ($_SESSION['user_id'] ?? null),
        'role' => $user['role'] ?? ($_SESSION['role'] ?? 'guest'),
    ]);
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), microphone=(), camera=(self)');
if (Request::isSecure()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
// CSP — nonce/hash preferred, unsafe-inline retained sementara kompatibilitas diverifikasi
header("Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: https://*.tile.openstreetmap.org; " .
    "font-src 'self'; " .
    "connect-src 'self';");

// === Session lifecycle — MUST init before any $_SESSION access ===
Security::initSession();

// Enforce idle + absolute timeouts with validated APP_BASE_URL redirect
if (isset($_SESSION['user_id']) && Security::isSessionExpired()) {
    Security::destroySession();
    $base = Request::validatedRedirectBase();
    $loginUrl = $base !== '' ? $base . '/login?reason=expired' : '/login?reason=expired';
    // Fallback to relative if host injection detected
    if (!filter_var($loginUrl, FILTER_VALIDATE_URL) && !str_starts_with($loginUrl, '/')) {
        $loginUrl = '/login?reason=expired';
    }
    header('Location: ' . $loginUrl);
    exit;
}
if (isset($_SESSION['user_id'])) {
    Security::touchSession();
}

// CORS — strict whitelist via CORS_ALLOWED_ORIGINS (no LAN auto-allow in production)
$allowedOrigins = [];
$corsFromEnv = Env::get('CORS_ALLOWED_ORIGINS', '');
if ($corsFromEnv !== '') {
    $allowedOrigins = array_map('trim', explode(',', $corsFromEnv));
}
if (empty($allowedOrigins)) {
    $allowedOrigins = ['http://localhost', 'http://localhost:8080', 'http://localhost:3000', 'http://10.0.2.2:8080'];
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With, X-Idempotency-Key');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} elseif ($origin !== '') {
    // Ensure Vary is always sent when Origin present to prevent cache poisoning
    header('Vary: Origin');
}
if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

Security::initSession();

$router = new Router();

$routesPath = BASE_PATH . '/config/routes.php';
if (file_exists($routesPath)) {
    require $routesPath;
}

// Routing and middleware must use the same normalized application-relative URI.
$router->dispatch(Request::method(), Request::uri());
