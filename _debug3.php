<?php
require __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$pwHash = password_hash('Test1234!', PASSWORD_BCRYPT);
$db->prepare("UPDATE users SET password = ? WHERE username = 'test_dev@local'")->execute([$pwHash]);

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
    if (preg_match_all('/^Set-Cookie:\s*([^=]+=[^;]+)/m', $h, $m)) {
        foreach ($m[1] as $c) $out[] = explode(';', $c)[0];
    }
    return implode('; ', $out);
}

$r1 = req('GET', "$base/auth/login");
preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $r1['body'], $cm);
$ck = getCookies($r1['header']);

$r2 = req('POST', "$base/auth/login", ['csrf_token'=>$cm[1],'username'=>'test_dev@local','password'=>'Test1234!','remember'=>'on'], $ck);
$ck = getCookies($r2['header']);

$r3 = req('GET', "$base/laporan/fetch?page=1&per_page=3&search=&status=&sort_col=tanggal&sort_dir=desc", null, $ck);
if ($r3['code'] === 200) {
    $j = json_decode($r3['body'], true);
    echo "Fetch Response ({$r3['code']}) = {$j['success']} = {$j['data']['total']} rows\n";
    echo "success: {$j['success']}\n";
    echo "total: {$j['data']['total']}\n";
    echo "totalPages: {$j['data']['totalPages']}\n";
    echo "page: {$j['data']['page']}\n";
    echo "perPage: {$j['data']['perPage']}\n";
    echo "from: {$j['data']['from']} | to: {$j['data']['to']}\n";
    echo "rows count: " . count($j['data']['rows']) . "\n";
    if (!empty($j['data']['rows'])) {
        $r = $j['data']['rows'][0];
        echo "\nFirst row keys: " . implode(', ', array_keys($r)) . "\n";
        echo "First row id: {$r['id']}, tanggal: {$r['tanggal']}, status: {$r['status']}\n";
    }
    echo "statusCounts: " . json_encode($j['data']['statusCounts']) . "\n";
} else {
    echo "fetch HTTP {$r3['code']}\n";
    echo "body: " . substr($r3['body'], 0, 200) . "\n";
}

echo "\n=== Check JS inline in page ===\n";
$r4 = req('GET', "$base/laporan", null, $ck);
preg_match('/async function loadTable\(\)/', $r4['body'], $hasLoadTable);
preg_match('/fetch\(buildURL\(\)/', $r4['body'], $hasFetch);
preg_match('/id="tableBody"/', $r4['body'], $hasBody);
preg_match('/buildTableRow/', $r4['body'], $hasBuildRow);
preg_match('/function buildTableRow/', $r4['body'], $hasBuildFn);
preg_match('/DOMContentLoaded/', $r4['body'], $hasDOM);
echo "Has loadTable fn: " . (!empty($hasLoadTable) ? 'YES' : 'NO') . "\n";
echo "Has fetch call: " . (!empty($hasFetch) ? 'YES' : 'NO') . "\n";
echo "Has #tableBody: " . (!empty($hasBody) ? 'YES' : 'NO') . "\n";
echo "Has buildTableRow ref: " . (!empty($hasBuildRow) ? 'YES' : 'NO') . "\n";
echo "Has buildTableRow fn: " . (!empty($hasBuildFn) ? 'YES' : 'NO') . "\n";
echo "Has DOMContentLoaded: " . (!empty($hasDOM) ? 'YES' : 'NO') . "\n";

echo "Has state block: " . (!empty($hasState) ? 'YES' : 'NO') . "\n";