<?php

class Router {
    private $routes = [];
    private $middlewares = [];
    private $routesByMethod = [];
    private Container $container;

    public function __construct(?Container $container = null) {
        $this->container = $container ?? Container::getInstance();
        $this->loadApiRoutes();
    }

    /**
     * Optimized route lookup by grouping routes by HTTP method
     */
    private function getRoutesByMethod($method) {
        if (!isset($this->routesByMethod[$method])) {
            $this->routesByMethod[$method] = array_filter($this->routes, function($route) use ($method) {
                return $route['method'] === $method;
            });
        }
        return $this->routesByMethod[$method];
    }

    /**
     * Add a GET route
     */
    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add a POST route
     */
    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add a PUT route
     */
    public function put($path, $handler, $middleware = []) {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add a DELETE route
     */
    public function delete($path, $handler, $middleware = []) {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add a route with any method
     */
    private function addRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Load API routes
     */
    private function loadApiRoutes() {
        // Internal web/API routes use the authenticated web session.
        // State-changing internal routes are CSRF-checked in executeRoute().
        // Laporan Hama API Routes
        $this->get('/api/laporan-hama', 'Api\LaporanHamaController@index', ['auth']);
        $this->get('/api/laporan-hama/{id}', 'Api\LaporanHamaController@show', ['auth']);
        $this->post('/api/laporan-hama', 'Api\LaporanHamaController@store', ['auth']);
        $this->put('/api/laporan-hama/{id}', 'Api\LaporanHamaController@update', ['auth']);
        $this->delete('/api/laporan-hama/{id}', 'Api\LaporanHamaController@destroy', ['auth', 'admin']);

        // External API routes never use web sessions. They require X-API-Key or Bearer token.
        $this->post('/api/external/report', 'ApiController@submitReport', ['external_auth', 'rate_limit']);
        $this->get('/api/external/mitra', 'ApiController@getMitra', ['external_auth', 'rate_limit']);
        $this->get('/api/external/kegiatan', 'ApiController@getKegiatan', ['external_auth', 'rate_limit']);
        $this->post('/api/external/honor', 'ApiController@addHonorPoptPelaporan', ['external_auth', 'rate_limit']);
        $this->get('/api/external/validate-sbml', 'ApiController@validateSBML', ['external_auth', 'rate_limit']);
        $this->post("/api/qwen/token", "Api\QwenController@token", ["external_auth", "rate_limit"]);
        $this->get("/api/qwen/status", "Api\QwenController@status", ["external_auth", "rate_limit"]);

        // Irigasi API Routes
        $this->get('/api/irigasi', 'Api\IrigasiController@index', ['auth']);
        $this->get('/api/irigasi/dashboard-summary', 'Api\IrigasiController@dashboardSummary', ['auth']);
        $this->get('/api/irigasi/{id}', 'Api\IrigasiController@show', ['auth']);
        $this->post('/api/irigasi', 'Api\IrigasiController@store', ['auth']);
        $this->put('/api/irigasi/{id}', 'Api\IrigasiController@update', ['auth']);
        $this->delete('/api/irigasi/{id}', 'Api\IrigasiController@destroy', ['auth', 'admin']);

        // Irigasi Monitoring & Analytics API Routes (NEW)
        $this->get('/api/irigasi/{id}/monitoring', 'Api\IrigasiController@monitoring', ['auth']);
        $this->get('/api/irigasi/{id}/weather', 'Api\IrigasiController@weather', ['auth']);
        $this->get('/api/irigasi/{id}/rules', 'Api\IrigasiController@getRules', ['auth']);
        $this->get('/api/irigasi/{id}/analytics', 'Api\IrigasiController@analytics', ['auth']);
        $this->post('/api/irigasi/rules', 'Api\IrigasiController@createRule', ['auth', 'operator']);
        $this->put('/api/irigasi/rules/{id}', 'Api\IrigasiController@updateRule', ['auth', 'operator']);
        $this->post('/api/irigasi/rules/{id}/toggle', 'Api\IrigasiController@toggleRule', ['auth', 'operator']);
        $this->post('/api/irigasi/rules/{id}/execute', 'Api\IrigasiController@executeRule', ['auth', 'operator']);
        $this->post('/api/irigasi/{id}/evaluate-rules', 'Api\IrigasiController@evaluateRules', ['auth', 'operator']);
        $this->post('/api/irigasi/alerts/{id}/dismiss', 'Api\IrigasiController@dismissAlert', ['auth']);


        // IoT/Pengairan API Routes
        $this->get('/api/pengairan/sensor', 'Api\IoTController@getSensors', ['auth']);
        $this->get('/api/pengairan/aktuator', 'Api\IoTController@getActuators', ['auth']);
        $this->get('/api/pengairan/log', 'Api\IoTController@getLogs', ['auth']);
        $this->post('/api/pengairan/sensor/{id}/update', 'Api\IoTController@updateSensor', ['auth']);
        $this->post('/api/pengairan/aktuator/{id}/control', 'Api\IoTController@controlActuator', ['auth']);
        $this->get('/api/pengairan/sensor/realtime', 'Api\IoTController@getRealtimeSensors', ['auth']);
        $this->get('/api/pengairan/schedule', 'Api\IoTController@getSchedule', ['auth']);
        $this->post('/api/pengairan/schedule/update', 'Api\IoTController@updateSchedule', ['auth']);

        // Wilayah API Routes
        $this->get('/api/wilayah/kabupaten', 'Api\WilayahController@getKabupaten', ['rate_limit']);
        $this->get('/api/wilayah/kecamatan/{kabupaten_id}', 'Api\WilayahController@getKecamatan', ['rate_limit']);
        $this->get('/api/wilayah/desa/{kecamatan_id}', 'Api\WilayahController@getDesa', ['rate_limit']);
        $this->get('/api/wilayah/hierarchy', 'Api\WilayahController@getHierarchy', ['rate_limit']);
        $this->get('/api/wilayah/search', 'Api\WilayahController@search', ['rate_limit']);
        $this->get('/api/wilayah/stats', 'Api\WilayahController@getStats', ['auth']);
        $this->get('/api/wilayah/by-coordinates', 'Api\WilayahController@getByCoordinates', ['rate_limit']);

        // Dashboard API Routes
        $this->get('/api/dashboard/stats', 'Api\DashboardController@getStats', ['auth']);
        $this->get('/api/dashboard/charts', 'Api\DashboardController@getChartData', ['auth']);
        $this->get('/api/dashboard/activities', 'Api\DashboardController@getActivities', ['auth']);
        $this->get('/api/dashboard/alerts', 'Api\DashboardController@getAlerts', ['auth']);

        // Dashboard Map API Routes (NEW)
        $this->get('/api/dashboard/map/layers', 'Api\DashboardMapApiController@layers', ['auth']);
        $this->get('/api/dashboard/map/hama', 'Api\DashboardMapApiController@hama', ['auth']);
        $this->get('/api/dashboard/map/irigasi', 'Api\DashboardMapApiController@irigasi', ['auth']);
        $this->get('/api/dashboard/map/weather', 'Api\DashboardMapApiController@weather', ['auth']);
        $this->get('/api/dashboard/map/all', 'Api\DashboardMapApiController@all', ['auth']);
        $this->get('/api/dashboard/map/hamaSummary', 'Api\DashboardMapApiController@hamaSummary', ['auth']);

        // Dashboard Charts API Routes (NEW)
        $this->get('/api/dashboard/charts/rainfall', 'Api\DashboardChartsApiController@rainfall', ['auth']);
        $this->get('/api/dashboard/charts/wind', 'Api\DashboardChartsApiController@wind', ['auth']);
        $this->get('/api/dashboard/charts/weather', 'Api\DashboardChartsApiController@weather', ['auth']);
        $this->get('/api/dashboard/charts/prices', 'Api\DashboardChartsApiController@prices', ['auth']);
        $this->get('/api/dashboard/charts/production', 'Api\DashboardChartsApiController@production', ['auth']);
        $this->get('/api/dashboard/charts/hama', 'Api\DashboardChartsApiController@hama', ['auth']);
        $this->get('/api/dashboard/charts/irrigation', 'Api\DashboardChartsApiController@irrigation', ['auth']);
        $this->get('/api/dashboard/charts/summary', 'Api\DashboardChartsApiController@summary', ['auth']);
        $this->get('/api/dashboard/charts/export', 'Api\DashboardChartsApiController@export', ['auth']);

        // User API Routes
        $this->get('/api/users', 'Api\UserController@index', ['auth', 'admin']);
        $this->get('/api/users/{id}', 'Api\UserController@show', ['auth']);
        $this->post('/api/users', 'Api\UserController@store', ['auth', 'admin']);
        $this->put('/api/users/{id}', 'Api\UserController@update', ['auth']);
        $this->delete('/api/users/{id}', 'Api\UserController@destroy', ['auth', 'admin']);
        $this->post('/api/users/{id}/toggle-status', 'Api\UserController@toggleStatus', ['auth', 'admin']);
        $this->get('/api/users/profile', 'Api\UserController@getProfile', ['auth']);
        $this->put('/api/users/profile', 'Api\UserController@updateProfile', ['auth']);
        $this->post('/api/users/change-password', 'Api\UserController@changePassword', ['auth']);
        $this->post('/api/users/force-change-password', 'Api\UserController@forceChangePassword', ['auth']);
        $this->get('/api/users/check-password-change', 'Api\UserController@checkPasswordChange', ['auth']);
        $this->post('/api/users/{id}/force-password-change', 'Api\UserController@setForcePasswordChange', ['auth', 'admin']);
        $this->get('/api/users/needing-password-change', 'Api\UserController@getUsersNeedingPasswordChange', ['auth', 'admin']);

        // OPT API Routes
        $this->get('/api/opt', 'Api\OptController@index', ['auth']);
        $this->get('/api/opt/{id}', 'Api\OptController@show', ['auth']);
        $this->post('/api/opt', 'Api\OptController@store', ['auth', 'admin']);
        $this->put('/api/opt/{id}', 'Api\OptController@update', ['auth', 'admin']);
        $this->delete('/api/opt/{id}', 'Api\OptController@destroy', ['auth', 'admin']);
        $this->post('/api/opt/{id}/toggle-status', 'Api\OptController@toggleStatus', ['auth', 'admin']);
        $this->get('/api/opt/stats', 'Api\OptController@getStats', ['auth']);

        // Data Storytelling API Routes (NEW)
        $this->get('/api/storytelling/analyses', 'Api\StorytellingController@getAnalyses', ['auth', 'statistisi']);
        $this->get('/api/storytelling/analyses/{id}', 'Api\StorytellingController@getAnalysis', ['auth', 'statistisi']);
        $this->post('/api/storytelling/generate', 'Api\StorytellingController@generateAnalysis', ['auth', 'statistisi']);
        $this->post('/api/storytelling/save', 'Api\StorytellingController@saveAnalysis', ['auth', 'statistisi']);
        $this->post('/api/storytelling/publish/{id}', 'Api\StorytellingController@publishAnalysis', ['auth', 'statistisi']);
        $this->get('/api/storytelling/chart-data', 'Api\StorytellingController@getChartData', ['auth', 'statistisi']);
        $this->get('/api/storytelling/stats', 'Api\StorytellingController@getStats', ['auth', 'statistisi']);
        $this->get('/api/opt/search', 'Api\OptController@search', ['auth']);
        $this->get('/api/opt/by-category/{category}', 'Api\OptController@getByCategory', ['auth']);
        $this->get('/api/opt/by-type/{type}', 'Api\OptController@getByType', ['auth']);

        // Laporan Hama Analytics Page Routes
        $this->get('/laporan-hama/analytics', 'LaporanHamaController@analytics', ['auth']);
        $this->get('/api/laporan-hama/analytics/export', 'LaporanHamaController@exportAnalytics', ['auth']);
        $this->get('/api/laporan-hama/analytics/export-csv', 'LaporanHamaController@exportCSV', ['auth']);

        // Laporan AJAX Routes
        $this->get('/laporan/fetch', 'LaporanController@fetch', ['auth']);
    }

    /**
     * Handle the current request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Remove base path and query string
        $uri = str_replace('/jagapadi', '', $uri);
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/');

        // Find matching route using method-based grouping for optimization
        foreach ($this->getRoutesByMethod($method) as $route) {
            if ($this->matchRoute($uri, $route)) {
                return $this->executeRoute($route, $uri);
            }
        }

        return false; // No API route matched
    }

    /**
     * Check if route matches
     */
    private function matchRoute($uri, $route) {
        $pattern = $this->convertToRegex($route['path']);
        return preg_match($pattern, $uri);
    }

    /**
     * Convert route path to regex pattern
     */
    private function convertToRegex($path) {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Execute the matched route
     */
    private function executeRoute($route, $uri) {
        // Extract parameters
        $params = $this->extractParams($route['path'], $uri);

        // Apply middleware
        if (!$this->applyMiddleware($route['middleware'])) {
            return true; // Middleware handled the response
        }

        if (!$this->enforceCsrfForSessionMutation($route)) {
            return true;
        }

        // Parse handler
        list($controller, $method) = explode('@', $route['handler']);

        // Handle namespaced controllers and controller suffixes properly
        $controllerPath = $controller;
        if (strpos($controllerPath, '\\') !== false) {
            $controllerPath = str_replace('\\', '/', $controllerPath);
        }
        if (!str_ends_with($controllerPath, 'Controller')) {
            $controllerPath .= 'Controller';
        }

        $controllerFile = ROOT_PATH . '/app/controllers/' . $controllerPath . '.php';

        if (!file_exists($controllerFile)) {
            $this->sendJsonResponse([
                'error' => 'Endpoint not implemented',
                'message' => "Controller {$controllerPath} belum tersedia"
            ], 501);
            return true;
        }

        require_once $controllerFile;

        // Get the actual class name
        $className = basename($controllerPath);

        if (!class_exists($className)) {
            $this->sendJsonResponse([
                'error' => 'Endpoint not implemented',
                'message' => "Class {$className} belum tersedia"
            ], 501);
            return true;
        }

        $controllerInstance = $this->container->make($className);

        if (!method_exists($controllerInstance, $method)) {
            $this->sendJsonResponse([
                'error' => 'Endpoint not implemented',
                'message' => "Method {$className}@{$method} belum tersedia"
            ], 501);
            return true;
        }

        // Call the method with parameters
        call_user_func_array([$controllerInstance, $method], $params);
        return true;
    }

    /**
     * Extract parameters from URI
     */
    private function extractParams($routePath, $uri) {
        $routeParts = explode('/', trim($routePath, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        $params = [];
        foreach ($routeParts as $index => $part) {
            if (preg_match('/\{([^}]+)\}/', $part, $matches)) {
                $params[$matches[1]] = $uriParts[$index] ?? null;
            }
        }

        return array_values($params);
    }

    /**
     * Apply middleware
     */
    private function applyMiddleware($middlewares) {
        foreach ($middlewares as $middleware) {
            switch ($middleware) {
                case 'auth':
                    // Session-backed web/API auth. External integrations must use external_auth/mobile_auth/scraper_auth.
                    if (!isset($_SESSION['user_id'])) {
                        $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                        exit; // Stop execution after sending response
                    }
                    break;

                case 'admin':
                    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                        $this->sendJsonResponse(['error' => 'Forbidden'], 403);
                        exit; // Stop execution after sending response
                    }
                    break;

                case 'operator':
                    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
                        $this->sendJsonResponse(['error' => 'Forbidden'], 403);
                        exit; // Stop execution after sending response
                    }
                    break;

                case 'statistisi':
                    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'statistisi'])) {
                        $this->sendJsonResponse(['error' => 'Forbidden'], 403);
                        exit; // Stop execution after sending response
                    }
                    break;

                case 'rate_limit':
                    if (class_exists('RateLimiter')) {
                        RateLimiter::apply();
                    }
                    break;

                case 'external_auth':
                    if (class_exists('ApiAuthMiddleware')) {
                        ApiAuthMiddleware::requireAuth('external');
                    } else {
                        $this->sendJsonResponse(['error' => 'Internal server error'], 500);
                        exit;
                    }
                    break;

                case 'mobile_auth':
                    if (class_exists('ApiAuthMiddleware')) {
                        ApiAuthMiddleware::requireAuth('mobile');
                    } else {
                        $this->sendJsonResponse(['error' => 'Internal server error'], 500);
                        exit;
                    }
                    break;

                case 'scraper_auth':
                    if (class_exists('ApiAuthMiddleware')) {
                        ApiAuthMiddleware::requireAuth('scraper');
                    } else {
                        $this->sendJsonResponse(['error' => 'Internal server error'], 500);
                        exit;
                    }
                    break;

                default:
                    error_log("[Router] Unknown middleware: {$middleware}");
                    $this->sendJsonResponse(['error' => 'Internal server error', 'message' => 'Unknown middleware configuration'], 500);
                    exit;
            }
        }

        return true;
    }

    private function enforceCsrfForSessionMutation($route) {
        $usesSessionAuth = in_array('auth', $route['middleware'], true);
        $usesTokenAuth = !empty(array_intersect(
            ['external_auth', 'mobile_auth', 'scraper_auth'],
            $route['middleware']
        ));
        $isStateChanging = in_array($route['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if (!$usesSessionAuth || $usesTokenAuth || !$isStateChanging) {
            return true;
        }

        if (!class_exists('Security')) {
            $this->sendJsonResponse(['error' => 'Internal server error'], 500);
            return false;
        }

        $token = Security::getRequestCsrfToken();
        if (!Security::validateCsrfToken($token)) {
            Security::logSecurityEvent(
                'CSRF_VIOLATION',
                'Invalid CSRF token for session-backed API route ' . ($route['path'] ?? ''),
                $_SESSION['user_id'] ?? null
            );
            $this->sendJsonResponse(['error' => 'CSRF token validation failed'], 403);
            return false;
        }

        return true;
    }

    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
