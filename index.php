<?php
// Define ROOT_PATH
define('ROOT_PATH', __DIR__);

// Define BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . '://' . $host . ($script !== '/' ? $script : '') . '/';
define('BASE_URL', $baseUrl);

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

// CORS Policy Headers
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8080',
    'https://bpsjember.my.id',
    'https://jagapadi.yourdomain.com' // Update with your domain
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-TOKEN, X-Requested-With');
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
    'bulkdelete',
    'uploadpreview',
    'processimport',
    'uploadphoto',
    'deletephoto',
    'runscraper',
    'importexcel',
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
