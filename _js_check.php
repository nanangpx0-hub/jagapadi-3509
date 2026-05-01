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

// Login as nanangpx
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

// Get the laporan page
$r3 = req('GET', "$base/laporan", null, $ck);
$page = $r3['body'];

echo "=== Checking for JS errors in rendered page ===\n";

// Check if BASE_URL is defined
if (preg_match('/const BASE_URL = [^;]+;/', $page)) {
    echo "✓ BASE_URL defined\n";
} else {
    echo "✗ BASE_URL NOT defined\n";
}

// Check if state is defined
if (preg_match('/let state = \{/', $page)) {
    echo "✓ state defined\n";
} else {
    echo "✗ state NOT defined\n";
}

// Check if loadTable function is defined
if (preg_match('/async function loadTable\(\)/', $page)) {
    echo "✓ loadTable function defined\n";
} else {
    echo "✗ loadTable function NOT defined\n";
}

// Check if loadTable is called
if (preg_match('/loadTable\(\);/', $page)) {
    echo "✓ loadTable() called\n";
} else {
    echo "✗ loadTable() NOT called\n";
}

// Check if tbody is assigned
if (preg_match('/let tbody = null;/', $page) && preg_match('/tbody = document\.querySelector\([\'"]#tableBody[\'"]\);/', $page)) {
    echo "✓ tbody assigned to #tableBody\n";
} else {
    echo "✗ tbody NOT properly assigned\n";
}

// Check for any obvious syntax errors in the JS - look for unmatched brackets/braces in inline JS
// Extract the main inline JS block (between <script> tags that don't have src)
if (preg_match_all('/<script[^>]*>([\s\S]*?)<\/script>/', $page, $scriptMatches)) {
    $inlineJs = '';
    foreach ($scriptMatches[1] as $js) {
        if (strpos($js, 'src=') === false) { // inline script only
            $inlineJs .= $js . "\n";
        }
    }
    if (!empty($inlineJs)) {
        // Check for balanced braces and parentheses
        $braces = substr_count($inlineJs, '{') - substr_count($inlineJs, '}');
        $parens = substr_count($inlineJs, '(') - substr_count($inlineJs, ')');
        $brackets = substr_count($inlineJs, '[') - substr_count($inlineJs, ']');
        if ($braces === 0 && $parens === 0 && $brackets === 0) {
            echo "✓ JS braces/parens/brackets balanced\n";
        } else {
            echo "✗ JS imbalance - braces: $braces, parens: $parens, brackets: $brackets\n";
        }
    }
}

// Check if there's any alert or error handling that might be hiding issues
if (strpos($page, 'Gagal memuat:') !== false) {
    echo "! Page contains error message placeholder\n";
}

// Now test the actual fetch that loadTable would make
$r4 = req('GET', "$base/laporan/fetch?page=1&per_page=10&search=&status=&sort_col=tanggal&sort_dir=desc", null, $ck);
if ($r4['code'] === 200) {
    $j = json_decode($r4['body'], true);
    if ($j && isset($j['success']) && $j['success'] === 1) {
        echo "✓ AJAX endpoint returns valid data: {$j['data']['total']} rows\n";
    } else {
        echo "✗ AJAX endpoint returned invalid JSON\n";
        echo "   Body: " . substr($r4['body'], 0, 200) . "\n";
    }
} else {
    echo "✗ AJAX endpoint HTTP {$r4['code']}\n";
}

// Let's also check if there are any console.error or try/catch that might be swallowing errors
if (preg_match('/try\s*\{/', $page) && preg_match('/catch\s*\(/', $page)) {
    echo "! Page contains try/catch - errors might be swallowed\n";
}

// Check if AbortError is being ignored (which would hide real errors)
if (preg_match('/AbortError/', $page)) {
    echo "! Page handles AbortError\n";
}

// Finally, let's see what the initial tbody content looks like
preg_match('/<tbody id="tableBody"[^>]*>(.*?)<\/tbody>/s', $page, $tbodyMatch);
if (!empty($tbodyMatch)) {
    $content = trim(strip_tags($tbodyMatch[1]));
    if ($content === '') {
        echo "▶ tbody is initially empty (expected)\n";
    } else {
        echo "▶ tbody has initial content: '" . substr($content, 0, 50) . "...'\n";
    }
}

// Check if there's a loading spinner shown initially
if (strpos($page, 'Memuat data...') !== false) {
    echo "▶ Loading spinner present in initial HTML\n";
}