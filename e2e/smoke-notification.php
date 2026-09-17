<?php

declare(strict_types=1);

// Smoke driver untuk API notifikasi (Backend v1). Base URL mengikuti
// docs/SMOKE_TEST.md: `php -S localhost:8080 -t public` dari folder backend.
// Override dengan env API_BASE_URL (mis. deployment document root backend/public).
$base = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8080/api/v1', '/');
$fixture = __DIR__ . '/fixtures/notification-smoke.php';

// RateLimitMiddleware (global) membatasi `login` 5x/15 menit per IP dan
// `api_guest` 20x/menit, sedangkan satu kali smoke memakai 4 login dan belasan
// request /api. Tanpa reset, smoke gagal 429 saat diulang. Reset hanya dijalankan
// untuk base URL loopback (server berbagi filesystem = smoke lokal sesuai
// docs/SMOKE_TEST.md); langkah ini tidak mengubah kode produksi dan dilewati
// bila autoloader backend tidak tersedia.
$autoload = dirname(__DIR__) . '/backend/vendor/autoload.php';
if (is_file($autoload) && preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?/#', $base) === 1) {
    require_once $autoload;
    // Identifier diturunkan persis seperti RateLimitMiddleware::resolveIdentifier()
    // ('ip_' . str_replace(':', '_', $ip)) untuk loopback IPv4 dan IPv6. Diturunkan
    // secara programatik agar tidak salah hitung jumlah underscore pada '::1'.
    $clientIds = array_map(
        static fn (string $ip): string => 'ip_' . str_replace(':', '_', $ip),
        ['127.0.0.1', '::1']
    );
    foreach ($clientIds as $clientId) {
        foreach (['login', 'api_guest', 'notif_poll'] as $limitPrefix) {
            \App\Helpers\RateLimiter::reset($limitPrefix, $clientId);
        }
    }
}

$run = 'smoke_' . substr(bin2hex(random_bytes(8)), 0, 12);

function run_php(string $script, string $input): ?string {
    $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $proc = proc_open([PHP_BINARY, $script], $descriptors, $pipes);
    if (!is_resource($proc)) return null;
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    fclose($pipes[2]); proc_close($proc); return $out;
}

// Ringkas error/message dari respons API agar kegagalan 429/401 dapat
// dibedakan sumbernya (RateLimitMiddleware vs brute force vs JWT) tanpa
// mencetak token atau data pribadi.
function err_note(?string $body): string {
    $json = json_decode((string) $body, true);
    if (!is_array($json)) return '';
    $code = $json['error'] ?? '';
    $message = $json['message'] ?? '';
    $note = trim(($code !== '' ? $code . ': ' : '') . $message);
    return $note === '' ? '' : ' (' . mb_substr($note, 0, 80) . ')';
}

function api(string $method, string $url, ?string $token, $data = null): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    if ($data !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : null,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

$create = run_php($fixture, json_encode(['run' => $run, 'action' => 'create']));
$out = json_decode($create ?? 'null', true);
if (empty($out['users'])) {
    exit('fixture creation failed: ' . ($create ?? 'null') . "\n");
}

$results = [];
$failed = 0;

foreach ($out['users'] as $user) {
    $expected = 2;

    [$lc, $lb] = api('POST', $base . '/auth/login', null, ['username' => $user['username'], 'password' => $user['password']]);
    $ljson = json_decode($lb, true);
    $token = $ljson['data']['token'] ?? null;
    if (!$token) { $results[] = sprintf("[FAIL] %s login http=%d%s", $user['username'], $lc, err_note($lb)); $failed++; continue; }
    $results[] = sprintf("[PASS] %s login http=%d", $user['username'], $lc);

    [$uc, $ub] = api('GET', $base . '/notifications/unread-count', $token);
    $uj = json_decode($ub, true);
    $cnt = $uj['data']['count'] ?? null;
    $ok = $uc === 200 && $cnt === $expected; if (!$ok) $failed++;
    $results[] = sprintf("[%s] %s unread-count http=%d count=%s expected=%d", $ok ? 'PASS' : 'FAIL', $user['role'], $uc, $cnt, $expected);

    [$nl, $nb] = api('GET', $base . '/notifications?limit=2', $token);
    $nj = json_decode($nb, true);
    $listOk = $nl === 200 && ($nj['meta']['total'] ?? null) === $expected && count($nj['data'] ?? []) === 2; if (!$listOk) $failed++;
    $results[] = sprintf("[%s] %s list http=%d total=%s items=%d", $listOk ? 'PASS' : 'FAIL', $user['role'], $nl, $nj['meta']['total'] ?? null, count($nj['data'] ?? []));

    $nid = $user['notifications'][0];
    [$rc, $rb] = api('POST', $base . '/notifications/' . $nid . '/read', $token, []);
    $rok = $rc === 200; if (!$rok) $failed++;
    $results[] = sprintf("[%s] %s mark-read http=%d", $rok ? 'PASS' : 'FAIL', $user['role'], $rc);

    [$uc2, $ub2] = api('GET', $base . '/notifications/unread-count', $token);
    $u2j = json_decode($ub2, true);
    $decOk = $uc2 === 200 && ($u2j['data']['count'] ?? null) === $expected - 1; if (!$decOk) $failed++;
    $results[] = sprintf("[%s] %s unread-decrement http=%d count=%s", $decOk ? 'PASS' : 'FAIL', $user['role'], $uc2, $u2j['data']['count'] ?? null);

    [$ra, $rb] = api('POST', $base . '/notifications/read-all', $token, []);
    $raOk = $ra === 200; if (!$raOk) $failed++;
    $results[] = sprintf("[%s] %s read-all http=%d", $raOk ? 'PASS' : 'FAIL', $user['role'], $ra);
}

// Ownership isolation: admin must NOT access another user's notification (expect 404)
$admin = $out['users'][2];
[$alc, $alb] = api('POST', $base . '/auth/login', null, ['username' => $admin['username'], 'password' => $admin['password']]);
$aj = json_decode($alb, true);
$atoken = $aj['data']['token'] ?? null;
if ($atoken === null) {
    echo "admin login failed http=$alc" . err_note($alb) . "\n";
}
$otherId = $out['users'][0]['notifications'][0];
[$xc, $xb] = api('POST', $base . '/notifications/' . $otherId . '/read', $atoken, []);
$xok = $xc === 404; if (!$xok) $failed++;
$results[] = sprintf("[%s] admin ownership-isolation http=%d expected=404%s", $xok ? 'PASS' : 'FAIL', $xc, err_note($xb));

echo implode("\n", $results) . "\n";
$cleanup = run_php($fixture, json_encode(['run' => $run, 'action' => 'cleanup']));
echo "cleanup: " . trim((string)$cleanup) . "\n";
echo "\nSUMMARY: " . (count($results) - $failed) . "/" . count($results) . " checks passed\n";
exit($failed > 0 ? 1 : 0);


