<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Security;
use App\Core\Request;

class CsrfMiddleware
{
    private const EXEMPT_PATHS = [
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

        $token = Request::input('_csrf_token') ?? Request::input('csrf_token');

        if ($token === null) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if ($headerToken !== '') {
                $token = $headerToken;
            }
        }

        if (!Security::validateCsrfToken($token)) {
            http_response_code(403);
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            if (stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => 'CSRF token tidak valid. Silakan muat ulang halaman dan coba lagi.',
                ]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>403 Forbidden</title></head>'
                    . '<body><h1>403 Forbidden</h1>'
                    . '<p>CSRF token tidak valid. Silakan kembali, muat ulang halaman, dan coba lagi.</p></body></html>';
            }
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
