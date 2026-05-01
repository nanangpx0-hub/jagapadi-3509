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

// Step 1: Get login page and CSRF token
$r1 = req('GET', "$base/auth/login");
preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $r1['body'], $cm);
$csrf = $cm[1] ?? '';
$ck = getCookies($r1['header']);
echo "1. GET /auth/login -> {$r1['code']}\n";
echo "   CSRF token obtained\n";
echo "   Cookie: $ck\n";

// Step 2: Login with nanangpx
$r2 = req('POST', "$base/auth/login", [
    'csrf_token' => $csrf,
    'username' => 'nanangpx@gmail.com',
    'password' => 'Test1234!',
    'remember' => 'on',
], $ck);
$ck = getCookies($r2['header']);
echo "2. POST /auth/login -> {$r2['code']}\n";
preg_match('/^Location:\s*(.+)$/m', $r2['header'], $loc);
echo "   Redirect to: " . trim($loc[1] ?? '') . "\n";
echo "   Cookie after login: $ck\n";

// Step 3: Access /laporan page
$r3 = req('GET', "$base/laporan", null, $ck);
echo "3. GET /laporan -> {$r3['code']}\n";
if ($r3['code'] !== 200) {
    preg_match('/^Location:\s*(.+)$/m', $r3['header'], $loc2);
    echo "   Redirect to: " . trim($loc2[1] ?? '') . "\n";
} else {
    // Check for initial tbody content (should be empty)
    preg_match('/<tbody id="tableBody"[^>]*>(.*?)<\/tbody>/s', $r3['body'], $tbodyMatch);
    if (!empty($tbodyMatch)) {
        $content = trim(strip_tags($tbodyMatch[1]));
        echo "   tbody initial content: '" . ($content === '' ? '(empty)' : substr($content, 0, 50) . "...') . "'\n";
    }
    // Check for loading spinner
    if (strpos($r3['body'], 'Memuat data...') !== false) {
        echo "   Contains loading spinner: YES\n";
    }
    // Check for loadTable() call
    if (strpos($r3['body'], 'loadTable();') !== false) {
        echo "   Has loadTable() call: YES\n";
    }
    // Check for state initialization
    if (preg_match('/let state = \{/', $r3['body'])) {
        echo "   State initialized: YES\n";
    }
}

// Step 4: Test the AJAX endpoint that loadTable would call
$r4 = req('GET', "$base/laporan/fetch?page=1&per_page=10&search=&status=&sort_col=tanggal&sort_dir=desc", null, $ck);
echo "4. GET /laporan/fetch -> {$r4['code']}\n";
if ($r4['code'] === 200) {
    $j = json_decode($r4['body'], true);
    if ($j) {
        echo "   success: {$j['success']}\n";
        echo "   total rows: {$j['data']['total']}\n";
        echo "   rows in this page: " . count($j['data']['rows']) . "\n";
        if (!empty($j['data']['rows'])) {
            $first = $j['data']['rows'][0];
            echo "   first row id: {$first['id']}, tanggal: {$first['tanggal']}\n";
        }
    } else {
        echo "   JSON parse failed\n";
    }
} else {
    echo "   HTTP error\n";
    preg_match('/^Location:\s*(.+)$/m', $r4['header'], $loc3);
    echo "   Redirect to: " . trim($loc3[1] ?? '') . "\n";
}