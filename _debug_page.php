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

// Step 1: Get login page
$r1 = req('GET', "$base/auth/login");
preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $r1['body'], $cm);
$ck = getCookies($r1['header']);

// Step 2: Login
$r2 = req('POST', "$base/auth/login", [
    'csrf_token'=>$cm[1],
    'username'=>'nanangpx@gmail.com',
    'password'=>'Test1234!',
    'remember'=>'on',
], $ck);
$ck = getCookies($r2['header']);

// Step 3: Get laporan page
$r3 = req('GET', "$base/laporan", null, $ck);
$page = $r3['body'];

echo "=== Page loaded. Checking for issues ===\n";

// Check if the tbody assignment is present
if (preg_match('/let tbody = null;/', $page) && preg_match('/tbody = document\.querySelector\([\'"]#tableBody[\'"]\);/', $page)) {
    echo "✓ tbody assignment found\n";
} else {
    echo "✗ tbody assignment MISSING\n";
}

// Look for the loadTable function and see if there's any syntax error before the tbody check
// We'll extract the loadTable function body
if (preg_match('/async function loadTable\(\)\s*\{([\s\S]*?)\}\s*$/m', $page, $matches)) {
    $funcBody = $matches[1];
    // Check if there's a return before tbody usage
    if (preg_match('/if\s*\(!\s*tbody\s*\)\s*return;/', $funcBody)) {
        echo "✓ early return for !tbody found\n";
    } else {
        echo "✗ early return for !tbody NOT found\n";
    }
    // Check if tbody is used after that
    if (preg_match('/tbody\s*\.innerHTML\s*=/', $funcBody)) {
        echo "✓ tbody.innerHTML assignment found\n";
    } else {
        echo "✗ tbody.innerHTML assignment NOT found\n";
    }
} else {
    echo "✗ Could not extract loadTable function\n";
}

// Check for any undefined variables that might be used before assignment
// Look for any use of 'state' before it's defined? state is defined earlier.

// Check if there's any error in showLoader
if (preg_match('/function showLoader\(\)/', $page)) {
    echo "✓ showLoader function exists\n";
} else {
    echo "✗ showLoader function missing\n";
}

// Now, let's see if there's any error in the actual execution by trying to evaluate the JS in a safe way? Not possible, but we can check for syntax.

// Let's also check if the page contains any error messages that would indicate a JS error was caught and displayed.
if (preg_match('/Gagal memuat:/', $page)) {
    echo "! Page contains error message placeholder (means JS catch is active)\n";
}

// Check if there's a try/catch around the fetch or the whole loadTable
if (preg_match('/try\s*\{/', $page) && preg_match('/catch\s*\(/', $page)) {
    echo "! loadTable has try/catch (errors might be caught and not shown)\n";
}

// Let's also check the network request simulation again to be sure
$r4 = req('GET', "$base/laporan/fetch?page=1&per_page=10&search=&status=&sort_col=tanggal&sort_dir=desc", null, $ck);
if ($r4['code'] === 200) {
    $j = json_decode($r4['body'], true);
    if ($j && isset($j['success']) && $j['success'] === 1) {
        echo "✓ AJAX endpoint works: {$j['data']['total']} rows\n";
    } else {
        echo "✗ AJAX endpoint returned invalid data\n";
    }
} else {
    echo "✗ AJAX endpoint HTTP {$r4['code']}\n";
}

// Finally, let's output a snippet of the page around the tbody initialization and loadTable call for manual inspection
echo "\n=== Snippet around tbody init ===\n";
if (preg_match('/let tbody = null;[\s\S]*?tbody = document\.querySelector\([\'"]#tableBody[\'"]\);/', $page, $m)) {
    echo "Found: " . $m[0] . "\n";
}
echo "\n=== Snippet around loadTable call ===\n";
if (preg_match('/loadTable\(\);/', $page, $m)) {
    // Get 100 chars before and after
    $pos = strpos($page, $m[0]);
    $start = max(0, $pos - 100);
    $end = min(strlen($page), $pos + strlen($m[0]) + 100);
    echo substr($page, $start, $end) . "\n";
}