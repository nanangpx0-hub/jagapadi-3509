<?php
require __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$db->prepare('UPDATE users SET password = ? WHERE username = ?')
    ->execute([password_hash('Test1234!', PASSWORD_BCRYPT), 'nanangpx@gmail.com']);

$base = 'http://localhost/jagapadi';
function req($method, $url, $data = null, $cookies = '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15, CURLOPT_COOKIE => $cookies, CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code'=>$code, 'header'=>substr($raw,0,$hs), 'body'=>substr($raw,$hs)];
}
function getCookies($h) {
    $out = [];
    preg_match_all('/^Set-Cookie:\s*([^=]+=[^;]+)/m', $h, $m);
    foreach ($m[1] as $c) $out[] = explode(';', $c)[0];
    return implode('; ', $out);
}

// Login
$r1 = req('GET', "$base/auth/login");
preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $r1['body'], $cm);
$ck = getCookies($r1['header']);
$r2 = req('POST', "$base/auth/login", [
    'csrf_token'=>$cm[1],
    'username'=>'nanangpx@gmail.com',
    'password'=>'Test1234!',
    'remember'=>'on',
], $ck);
$ck = getCookies($r2['header']);

// Fetch endpoint with same params as page would use
$r3 = req('GET', "$base/laporan/fetch?page=1&per_page=10&search=&status=&sort_col=tanggal&sort_dir=desc", null, $ck);
echo "HTTP {$r3['code']}\n";
echo "Content-Type: " . preg_match('/^Content-Type: ([^\r\n]+)/mi', $r3['header'], $m) ? $m[1] : 'none' . "\n";
echo "Body length: " . strlen($r3['body']) . "\n";

// Try to parse JSON
$data = json_decode($r3['body'], true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON valid\n";
    if (isset($data['success'])) {
        echo "success: {$data['success']}\n";
    }
    if (isset($data['data'])) {
        echo "data keys: " . implode(', ', array_keys($data['data'])) . "\n";
        if (isset($data['data']['rows'])) {
            echo "rows count: " . count($data['data']['rows']) . "\n";
            if (!empty($data['data']['rows'])) {
                $first = $data['data']['rows'][0];
                echo "first row id: {$first['id']}\n";
            }
        }
    }
} else {
    echo "JSON INVALID. Error: " . json_last_error_msg() . "\n";
    echo "Body start: " . substr($r3['body'], 0, 500) . "\n";
    echo "Body end: " . substr($r3['body'], -500) . "\n";
}
// Also check if body contains HTML (redirect)
if (strpos($r3['body'], '<!DOCTYPE html>') !== false || strpos($r3['body'], '<html') !== false) {
    echo "!!! Body contains HTML - likely redirect to login !!!\n";
    // Show first 1000 chars
    echo substr($r3['body'], 0, 1000) . "\n";
}