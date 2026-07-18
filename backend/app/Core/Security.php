<?php

declare(strict_types=1);

namespace App\Core;

class Security
{
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name = Env::get('SESSION_NAME', 'jagapadi_session');
        $isSecure = Request::isSecure();

        session_name($name);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', '28800');
        ini_set('session.cookie_lifetime', '0');

        if ($isSecure) {
            ini_set('session.cookie_secure', '1');
        }

        session_start();

        self::checkSessionIdle();
    }

    public static function checkSessionIdle(): void
    {
        $maxIdle = 28800;
        $loginAt = $_SESSION['login_at'] ?? null;
        if ($loginAt !== null && (time() - $loginAt) > $maxIdle) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }

    public static function regenerateSession(): void
    {
        session_regenerate_id(true);
    }

    public static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            self::regenerateCsrfToken();
        }

        $tokenAge = time() - ($_SESSION['csrf_token_time'] ?? 0);
        if ($tokenAge > 3600) {
            self::regenerateCsrfToken();
        }

        return $_SESSION['csrf_token'];
    }

    public static function regenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $stored = $_SESSION['csrf_token'] ?? '';
        if ($stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::csrfToken() . '">';
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
