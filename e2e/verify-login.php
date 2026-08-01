<?php
$users = [
    ['admin', 'Jember3509'],
    ['petugas01', 'Jember3509'],
    ['petugas02', 'Jember3509'],
    ['operator01', 'Jember3509'],
    ['statistisi01', 'Jember3509'],
];

$allOk = true;
foreach ($users as $u) {
    // GET login page for CSRF
    $ch = curl_init('http://localhost:8080/login');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    $hdr = substr($resp, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = substr($resp, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);

    preg_match('/name="_csrf_token"[^>]*value="([^"]+)"/', $body, $m);
    $csrf = $m[1] ?? '';
    preg_match('/Set-Cookie: ([^;]+)/', $hdr, $m);
    $cookie = $m[1] ?? '';

    // POST login
    $ch = curl_init('http://localhost:8080/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $u[0], 'password' => $u[1], '_csrf_token' => $csrf]),
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Cookie: ' . $cookie],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr = substr($resp, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);

    $loc = '';
    if (preg_match('/Location: (.+)/', $hdr, $m)) $loc = trim($m[1]);
    $ok = $code === 302 && strpos($loc, 'dashboard') !== false;
    echo ($ok ? '[OK]' : '[FAIL]') . " {$u[0]} -> $code $loc\n";
    if (!$ok) $allOk = false;
}

exit($allOk ? 0 : 1);
