<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private string $basePath = '';
    private array $groupMiddleware = [];
    private array $globalMiddleware = [];

    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function addGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddleware[] = $middlewareClass;
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $previousBasePath = $this->basePath;
        $this->basePath = rtrim($this->basePath . $prefix, '/');

        $callback($this);

        $this->basePath = $previousBasePath;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $allMiddleware = array_merge($this->globalMiddleware, $this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'path' => $this->normalizePath($this->basePath . $path),
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($uri);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            $params = $this->matchRoute($route['path'], $path);
            if ($params !== null) {
                $this->runMiddleware($route['middleware'], $route, $params);
                return;
            }
        }

        $this->handleNotFound($method, $path);
    }

    private function runMiddleware(array $middlewareList, array $route, array $params): void
    {
        foreach ($middlewareList as $mwClass) {
            if (class_exists($mwClass)) {
                $instance = new $mwClass();
                if (method_exists($instance, 'handle')) {
                    $result = $instance->handle($route, $params);
                    if ($result === false) {
                        return;
                    }
                }
            }
        }

        $this->executeHandler($route['handler'], $params);
    }

    private function matchRoute(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestPath, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $path = preg_replace('#/+#', '/', $path);
        return $path ?: '/';
    }

    private function executeHandler(array|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            call_user_func_array([$instance, $method], [$params]);
            return;
        }

        throw new \RuntimeException('Invalid route handler');
    }

    private function handleNotFound(string $method, string $path): void
    {
        if (str_starts_with($path, '/api/')) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'NotFound',
                'message' => 'Endpoint tidak ditemukan.',
            ]);
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 - Halaman tidak ditemukan.';
    }
}
