<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Security;
use App\Core\Request;

class CsrfMiddleware
{
    private const EXEMPT_PATHS = [
        '/logout',
    ];

    public function handle(array $route, array $params): bool
    {
        $method = Request::method();
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $uri = Request::uri();
        if (str_starts_with($uri, '/api/')) {
            return true;
        }

        $isExempt = $this->isExemptPath($uri);
        if ($isExempt !== null) {
            return $isExempt;
        }

        $token = Request::input('_csrf_token');

        if ($token === null) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if ($headerToken !== '') {
                $token = $headerToken;
            }
        }

        if (!Security::validateCsrfToken($token)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'CSRF token tidak valid. Silakan coba lagi.';
            $redirect = $_SERVER['HTTP_REFERER'] ?? '/login';
            if (!preg_match('#^/[a-z0-9/_-]*$#', $redirect)) {
                $redirect = '/login';
            }
            header("Location: $redirect");
            return false;
        }

        return true;
    }

    private function isExemptPath(string $uri): ?bool
    {
        foreach (self::EXEMPT_PATHS as $exemptPath) {
            $normalizedExempt = preg_quote($exemptPath, '#');
            if (preg_match('#^' . $normalizedExempt . '(/|$)#i', $uri)
                || preg_match('#^' . $normalizedExempt . '/.*#i', $uri)
            ) {
                return true;
            }
        }

        return null;
    }
}
