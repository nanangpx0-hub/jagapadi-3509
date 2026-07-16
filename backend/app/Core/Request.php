<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return '/' . trim($uri, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    public static function all(): array
    {
        $method = self::method();
        if ($method === 'GET') {
            return $_GET;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $body = file_get_contents('php://input');
            if ($body !== false && $body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    return $decoded + $_POST;
                }
            }
        }

        return $_POST;
    }

    public static function ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $ips = explode(',', $forwarded);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function isApi(): bool
    {
        return str_starts_with(self::uri(), '/api/');
    }

    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }

    public static function baseUrl(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$scheme://$host";
    }
}
