<?php
declare(strict_types=1);

/**
 * Generate machine-readable route matrix for both runtimes.
 * Output: docs/ROUTE_MATRIX.json
 * Fields: runtime, method, path, controller, authentication, role, ownership, csrf, idempotency, rate_limit, status_codes
 */

$backendRoutesFile = __DIR__ . '/../backend/config/routes.php';
$rootRoutesFile = __DIR__ . '/../config/web_routes.php';

$matrix = [];
$generatedAt = date('c');

// Helper to infer metadata from middleware
function inferAuth(array $middleware): string {
    if (in_array('App\Middleware\ApiAuthMiddleware', $middleware, true)) return 'JWT Bearer';
    if (in_array('App\Middleware\WebAuthMiddleware', $middleware, true)) return 'Session';
    if (in_array('App\Middleware\AdminMiddleware', $middleware, true)) return 'Session+Admin';
    return 'Public';
}
function inferRole(array $middleware): string {
    if (in_array('App\Middleware\AdminMiddleware', $middleware, true)) return 'admin';
    if (in_array('App\Middleware\PetugasAdminMiddleware', $middleware, true)) return 'petugas,admin';
    if (in_array('App\Middleware\ApiAuthMiddleware', $middleware, true)) return 'authenticated (role-scoped)';
    if (in_array('App\Middleware\WebAuthMiddleware', $middleware, true)) return 'authenticated';
    return 'guest';
}
function inferOwnership(string $path): string {
    $owned = ['laporan-hama','laporan-irigasi','laporan-pupuk','laporan-panen','laporan-cuaca','laporan-alat-sarana','notifications','device-tokens','export','feedback','laporan-lainnya','usulan-opt'];
    foreach ($owned as $kw) {
        if (str_contains($path, $kw)) return 'owner-scoped (petugas) / Admin global pada route Admin eksplisit';
    }
    return 'global / admin-only where marked';
}

// Backend routes: parse file via regex (avoid executing router)
$backendContent = file_get_contents($backendRoutesFile);
preg_match_all('/\$router->(get|post|put|delete|patch)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[([^\]]+)\]/i', $backendContent, $m, PREG_SET_ORDER);
foreach ($m as $match) {
    $method = strtoupper($match[1]);
    $path = $match[2];
    $handlerRaw = $match[3];
    // extract class and method
    preg_match('/([A-Za-z0-9\\\\_]+)::class\s*,\s*[\'"]([^\'"]+)[\'"]/', $handlerRaw, $hm);
    $controller = $hm[1] ?? 'unknown';
    $action = $hm[2] ?? 'unknown';
    // middleware: capture trailing array if present
    // Look ahead for middleware array
    $pos = strpos($backendContent, $match[0]);
    $tail = substr($backendContent, $pos, 600);
    preg_match('/\[([A-Za-z0-9\\\\_:, \]]+)\]\s*\)\s*;/', $tail, $mwMatch);
    // better: extract middleware via second regex on same line
    $middleware = [];
    if (preg_match('/,\s*\[([^\]]+)\]\s*\)\s*;/', $match[0] . substr($tail, 0, 400), $mm)) {
        // parse each middleware class
        preg_match_all('/([A-Za-z0-9\\\\]+)::class/', $mm[1], $mwClasses);
        $middleware = $mwClasses[1] ?? [];
    } else {
        // try to find middleware array after handler
        $fullLine = $match[0];
        // retrieve full router line
        $lineEnd = strpos($backendContent, ';', $pos);
        $full = substr($backendContent, $pos, $lineEnd - $pos + 1);
        if (preg_match_all('/([A-Za-z\\\\\\\\]+)::class/', $full, $allClasses)) {
            // first is controller, rest are middleware
            $all = $allClasses[1];
            array_shift($all);
            $middleware = $all;
        }
    }
    $matrix[] = [
        'runtime' => 'backend-v1',
        'front_controller' => 'backend/public/index.php',
        'document_root' => 'backend/public',
        'method' => $method,
        'path' => $path,
        'controller' => $controller . '@' . $action,
        'authentication' => inferAuth($middleware),
        'role' => inferRole($middleware),
        'ownership' => inferOwnership($path),
        'csrf' => in_array('App\Middleware\CsrfMiddleware', $middleware, true) || !str_starts_with($path, '/api/') ? 'required for web mutasi' : 'exempt (API JWT)',
        'idempotency' => in_array('App\Middleware\IdempotencyMiddleware', $middleware, true) ? 'Idempotency-Key supported' : 'none',
        'rate_limit' => in_array('App\Middleware\RateLimitMiddleware', $middleware, true) ? 'global 60/min auth, 20/min guest' : 'none',
        'status_codes' => [200,201,400,401,403,404,409,422,429,500],
    ];
}

// Root routes
$rootRoutes = require $rootRoutesFile;
foreach ($rootRoutes as $path => $handler) {
    $matrix[] = [
        'runtime' => 'root',
        'front_controller' => 'index.php',
        'document_root' => '.',
        'method' => 'GET|POST (convention)',
        'path' => '/' . ltrim($path, '/'),
        'controller' => $handler,
        'authentication' => str_contains($path, 'auth') || $path === 'login' ? 'Public/Session' : 'Session',
        'role' => in_array($path, ['user','adminWilayah/kabupaten']) ? 'admin' : 'authenticated (petugas scope enforced in controller)',
        'ownership' => inferOwnership($path),
        'csrf' => 'required for mutasi (POST) via Security::validateCsrfToken',
        'idempotency' => str_contains($path, 'store') ? 'idempotency_token (double-submit guard)' : 'none',
        'rate_limit' => 'session-based brute-force + CacheManager rate_limit',
        'status_codes' => [200,302,400,401,403,404,409,422,500],
    ];
}

// Also add fallback convention routes note
$matrix[] = [
    'runtime' => 'root',
    'front_controller' => 'index.php',
    'method' => 'ANY (convention)',
    'path' => '/{controller}/{method}/{params} (fallback)',
    'controller' => 'dynamic Controller@method',
    'authentication' => 'Session (if not public)',
    'role' => 'controller-enforced',
    'ownership' => 'controller-enforced',
    'csrf' => 'enforced for state-changing methods list in index.php',
    'idempotency' => 'idem_tokens (session)',
    'rate_limit' => 'per-IP via CacheManager',
    'status_codes' => [200,404,403],
    'note' => 'Fallback for unmapped web_routes — compatibility layer'
];

// Sort by runtime, method, path
usort($matrix, fn($a,$b) => [$a['runtime'], $a['path'], $a['method']] <=> [$b['runtime'], $b['path'], $b['method']]);

$out = [
    'generated_at' => $generatedAt,
    'canonical_runtime' => 'backend-v1 (backend/public)',
    'compatibility_runtime' => 'root (frozen, ADR-011)',
    'authoritative' => false,
    'warning' => 'F-04: Generator saat ini memakai regex (tidak akurat untuk middleware global/per-route, auth, role, CSRF, idempotency, rate limit). Jangan gunakan sebagai sumber kebijakan keamanan sampai diinstrumentasi via Router registry / PHP AST (P1). Lihat docs/AUDIT_KODE_DAN_FUNGSI_2026-09-01.md',
    'total_routes' => count($matrix),
    'routes' => $matrix,
];

$target = __DIR__ . '/../docs/ROUTE_MATRIX.json';
file_put_contents($target, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Generated $target with " . count($matrix) . " entries\n";
