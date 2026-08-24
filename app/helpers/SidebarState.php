<?php

declare(strict_types=1);

final class SidebarState
{
    public static function routeFromRequest(string $requestUri, string $baseUrl): string
    {
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $basePath = parse_url($baseUrl, PHP_URL_PATH);

        $requestPath = self::normalizePath(is_string($requestPath) ? $requestPath : '');
        $basePath = self::normalizePath(is_string($basePath) ? $basePath : '');

        if ($basePath !== '' && $requestPath === $basePath) {
            return '';
        }

        if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
            $requestPath = substr($requestPath, strlen($basePath) + 1);
        }

        return self::normalizePath($requestPath);
    }

    public static function matches(string $currentRoute, string $targetRoute, bool $includeChildren = true): bool
    {
        $currentRoute = strtolower(self::normalizePath($currentRoute));
        $targetRoute = strtolower(self::normalizePath($targetRoute));

        if ($targetRoute === '') {
            return $currentRoute === '';
        }

        if ($currentRoute === $targetRoute) {
            return true;
        }

        return $includeChildren && str_starts_with($currentRoute, $targetRoute . '/');
    }

    /**
     * Label menu Usulan OPT sesuai role, atau null bila role tidak berhak.
     */
    public static function usulanOptMenuLabel(?string $role): ?string
    {
        return match ($role) {
            'admin' => 'Usulan OPT',
            'petugas' => 'Usulan OPT Saya',
            default => null,
        };
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return trim($path, '/');
    }
}
