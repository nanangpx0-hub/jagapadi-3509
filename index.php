<?php
// Define ROOT_PATH
define('ROOT_PATH', __DIR__);

// Define BASE_URL
//
// Base URL dihitung dari relasi filesystem antara ROOT_PATH dan DOCUMENT_ROOT,
// BUKAN dari `dirname($_SERVER['SCRIPT_NAME'])` secara mentah. Pendekatan lama
// menyertakan path absolut mesin (mis. C:/laragon/www/jagapadi-3509) ke dalam
// URL ketika aplikasi pertama kali diakses lewat link/path absolut, sehingga
// seluruh link navigasi yang dibangun dari BASE_URL mengarah ke URL yang salah
// (contoh: http://localhost/C:/laragon/www/jagapadi-3509/).
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

$basePath = '';
$docRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
    : '';
$rootAsUrl = str_replace('\\', '/', ROOT_PATH);

// 1) Selisih DOCUMENT_ROOT terhadap ROOT_PATH -> segmen path web yang benar
//    (contoh: docroot C:/laragon/www + ROOT C:/laragon/www/jagapadi-3509
//    menghasilkan base path "/jagapadi-3509").
if ($docRoot !== '' && str_starts_with($rootAsUrl, $docRoot)) {
    $basePath = trim(substr($rootAsUrl, strlen($docRoot)), '/');
} elseif ($docRoot !== '' && $rootAsUrl === $docRoot) {
    // Aplikasi di-root docroot: base URL adalah "/".
    $basePath = '';
} else {
    // 2) Fallback aman: ambil nama folder aplikasi (basename ROOT_PATH) dan
    //    bersihkan sisa-sisa path absolut bila DOCUMENT_ROOT tidak tersedia.
    $basePath = trim(basename(ROOT_PATH), '/');
    if (preg_match('~^[A-Za-z]:$~', $basePath) === 1) {
        $basePath = '';
    }
}

$baseUrl = $protocol . '://' . $host . '/' . ltrim($basePath, '/');
$baseUrl = rtrim($baseUrl, '/') . '/';
define('BASE_URL', $baseUrl);

// Define UPLOAD_PATH
define('UPLOAD_PATH', ROOT_PATH . '/storage/uploads/');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Load .env file (base), then .env.local (overrides)
$envPaths = [ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'];
$loadedKeys = [];
foreach ($envPaths as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if (empty($key)) {
                continue;
            }
            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || 
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            // Always override: .env.local takes precedence over .env
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load configuration before starting the session so session ini settings can be applied.
// Note: config/config.php and config/database.php might not exist; skip if missing
$configPath = ROOT_PATH . '/config/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}
$dbConfigPath = ROOT_PATH . '/config/database.php';
if (file_exists($dbConfigPath)) {
    require_once $dbConfigPath;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// CORS Policy Headers - configured via environment variables with LAN/private IP support
$corsOrigins = getenv('CORS_ALLOWED_ORIGINS');
$allowedOrigins = $corsOrigins ? array_map('trim', explode(',', $corsOrigins)) : [
    'http://localhost',
    'http://localhost:8080',
    'http://localhost:3000',
    'http://10.0.2.2:8080',
    'https://bpsjember.my.id',
    'https://jagapadi.bpsjember.my.id'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$isLanOrigin = false;
if ($origin !== '') {
    $parsedHost = parse_url($origin, PHP_URL_HOST);
    if ($parsedHost) {
        $isLanOrigin = filter_var($parsedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

if ($origin !== '' && (in_array($origin, $allowedOrigins, true) || $isLanOrigin)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-TOKEN, X-Requested-With, X-Idempotency-Key');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 3600');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        'app/controllers/',
        'app/models/',
        'app/core/',
        'app/helpers/',
        'app/middleware/',
        'app/services/'
    ];

    foreach ($paths as $path) {
        $file = ROOT_PATH . '/' . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

$container = Container::getInstance();
$container->singleton(Database::class, fn() => Database::getInstance());
$container->singleton(PDO::class, fn() => Database::getInstance()->getConnection());
$container->singleton(CacheManager::class, fn() => CacheManager::getInstance());
$container->singleton(Logger::class, fn() => new Logger());

// Get request URI and strip base path dynamically
$request = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = dirname($scriptName);
if ($baseDir !== '/' && $baseDir !== '\\') {
    $request = substr($request, strlen($baseDir));
}
$request = strtok($request, '?'); // Remove query string

// Check if this is an API request
if (strpos($request, 'api/') === 0 || strpos($request, '/api/') === 0) {
    // Handle API requests with the Router class
    require_once ROOT_PATH . '/app/core/Router.php';
    $router = $container->make(Router::class);

    if ($router->handleRequest()) {
        // API request was handled
        exit;
    } else {
        // API route not found
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'API endpoint not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Handle regular web requests
// Default route
if ($request == '' || $request == '/') {
    if (isset($_SESSION['user_id'])) {
        $request = 'dashboard';
    } else {
        $request = 'auth/login';
    }
}

// Parse route
$parts = explode('/', trim($request, '/'));
$controllerPart = (!empty($parts[0])) ? $parts[0] : 'dashboard';
// Handle camelCase and dash/underscore in route names
$controllerName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $controllerPart))) . 'Controller';
$method = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

// Explicit route map (config/web_routes.php) — dipakai jika tersedia,
// dengan fallback konvensi default di bawah untuk backward compatibility.
$explicitRoutes = [];
$webRoutesFile = ROOT_PATH . '/config/web_routes.php';
if (file_exists($webRoutesFile)) {
    $explicitRoutes = require $webRoutesFile;
}
$routePath = ltrim(rtrim($request, '/'), '/');
// Pencocokan case-insensitive terhadap route map. .htaccess me-lowercase URL
// berhuruf besar, sehingga route campuran seperti "dashboardPadi" akan datang
// sebagai "dashboardpadi"; lookup ini memastikan keduanya tetap ter-resolve
// tanpa 404.
$matchedRoute = null;
foreach ($explicitRoutes as $mapKey => $handler) {
    if (strcasecmp((string)$mapKey, $routePath) === 0) {
        $matchedRoute = $handler;
        break;
    }
}
if ($matchedRoute !== null) {
    $routeHandler = explode('@', $matchedRoute);
    $controllerName = $routeHandler[0] . 'Controller';
    $method = $routeHandler[1];
}

// Check if controller exists
$controllerFile = ROOT_PATH . '/app/controllers/' . $controllerName . '.php';
if (!file_exists($controllerFile)) {
    http_response_code(404);
    echo "404 - Page Not Found";
    exit;
}

// Create controller and call method
require_once $controllerFile;
        $controller = $container->make($controllerName);

if (!is_callable([$controller, $method])) {
    http_response_code(404);
    echo "404 - Method Not Found";
    exit;
}

$stateChangingMethods = [
    'logout',
    'store',
    'storerilis',
    'update',
    'delete',
    'destroy',
    'togglestatus',
    'updatestatus',
    'verify',
    'reject',
    'archive',
    'bulkdelete',
    'uploadpreview',
    'processimport',
    'uploadphoto',
    'deletephoto',
    'runscraper',
    'runscraperbackground',
    'importexcel',
    'importksa',
    'syncksatoannual',
    'deletebyyear',
    'deletemultiple',
    'deletelog',
    'deletemultiplelogs',
    'saverule',
    'togglerulestatus',
    'deleterule',
    'runruleengine',
    'vote',
    'publish',
    'saveanalysis',
    'kabupaten_delete',
    'kabupaten_update',
    'kecamatan_delete',
    'kecamatan_bulk_delete',
    'kecamatan_update',
    'desa_delete',
    'desa_update',
    'submit',
    'resubmit',
    'requestrevision',
    'delete_draft',
];

$methodLower = strtolower($method);
if (in_array($methodLower, $stateChangingMethods, true)) {
    $allowedMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $expectsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if (!in_array($requestMethod, $allowedMethods, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));

        if ($expectsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        } else {
            echo '405 - Method Not Allowed';
        }
        exit;
    }

    $token = Security::getRequestCsrfToken();
    if (!Security::validateCsrfToken($token)) {
        Security::logSecurityEvent(
            'CSRF_VIOLATION',
            "Invalid CSRF token for {$controllerName}@{$method}",
            $_SESSION['user_id'] ?? null
        );

        http_response_code(403);
        if ($expectsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
        } else {
            echo '403 - CSRF token validation failed';
        }
        exit;
    }
}

call_user_func_array([$controller, $method], $params);
