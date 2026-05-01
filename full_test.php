<?php
// Simulate a full browser session: login, then visit /laporan
$base = 'http://localhost/jagapadi';

function curl_req($method, $url, $data = null, $cookies = '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
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

function get_cookies($header) {
    preg_match_all('/^Set-Cookie:\s*([^=]+=[^;]+)/mi', $header, $m);
    $out = [];
    foreach ($m[1] as $c) $out[] = explode(';', $c)[0];
    return implode('; ', $out);
}

// Step 1: Get login page
echo "1. Fetching login page...\n";
$r1 = curl_req('GET', "$base/auth/login", null, '');
$cookies = get_cookies($r1['header']);

// Extract CSRF token
if (!preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $r1['body'], $m)) {
    die("ERROR: Could not find CSRF token\n");
}
$csrf = $m[1];
echo "   CSRF token obtained\n";

// Step 2: Login as admin
echo "2. Logging in as admin...\n";
$r2 = curl_req('POST', "$base/auth/login", [
    'csrf_token' => $csrf,
    'username' => 'admin',  // assuming admin exists
    'password' => 'admin123', // adjust if needed
    'remember' => 'on'
], $cookies);
$cookies = get_cookies($r2['header']);
echo "   HTTP {$r2['code']}\n";

// Step 3: Visit /laporan page
echo "3. Loading /laporan page...\n";
$r3 = curl_req('GET', "$base/laporan", null, $cookies);
echo "   HTTP {$r3['code']}, Content-Length: " . strlen($r3['body']) . "\n";

// Analysis
echo "\n=== ANALYSIS ===\n";

// Check if it's HTML
if (strpos($r3['body'], '<!DOCTYPE') !== false) {
    echo "✓ Full HTML page returned\n";
} else {
    echo "✗ Not a full HTML page (possibly redirect/error)\n";
}

// Check for overlay element
if (strpos($r3['body'], 'photoPreviewOverlay') !== false) {
    echo "✓ Photo preview overlay element exists\n";
    
    // Check if it has 'show' class
    if (preg_match('/<div[^>]*id="photoPreviewOverlay"[^>]*class="[^"]*show[^"]*"/', $r3['body'])) {
        echo "✗ CRITICAL: Overlay has 'show' class → VISIBLE by default\n";
    } else {
        echo "✓ Overlay does NOT have 'show' class (hidden by CSS)\n";
    }
    
    // Check CSS rules in the page
    if (preg_match('/\.photo-preview-overlay\s*\{[^}]*display:\s*none/s', $r3['body'])) {
        echo "✓ CSS base rule: display: none\n";
    } else {
        echo "✗ CSS base rule missing display: none or overridden\n";
    }
    
    if (preg_match('/\.photo-preview-overlay\.show\s*\{[^}]*display:\s*flex/s', $r3['body'])) {
        echo "✓ CSS .show rule: display: flex\n";
    } else {
        echo "✗ CSS .show rule missing\n";
    }
} else {
    echo "ℹ Overlay element not found in page (may be outside content area)\n";
}

// Check if page has JavaScript errors
if (strpos($r3['body'], 'ReferenceError') !== false || strpos($r3['body'], 'Uncaught') !== false) {
    echo "⚠ Page may contain JS errors\n";
} else {
    echo "✓ No obvious JS errors in HTML\n";
}

// Check table structure
if (strpos($r3['body'], 'id="laporanTable"') !== false) {
    echo "✓ Laporan table element exists\n";
} else {
    echo "✗ Table not found\n";
}

// Check if loadTable function is present
if (strpos($r3['body'], 'function loadTable') !== false || strpos($r3['body'], 'async function loadTable') !== false) {
    echo "✓ loadTable function defined\n";
} else {
    echo "✗ loadTable function missing\n";
}

echo "\n=== CONCLUSION ===\n";
$overlayVisible = (strpos($r3['body'], 'photoPreviewOverlay') !== false && 
                   preg_match('/<div[^>]*id="photoPreviewOverlay"[^>]*class="[^"]*show[^"]*"/', $r3['body']));
if ($overlayVisible) {
    echo "ISSUE: Preview overlay is FORCED VISIBLE (has 'show' class or CSS incorrect).\n";
} elseif (strpos($r3['body'], 'photoPreviewOverlay') !== false) {
    echo "OK: Overlay exists but is hidden by CSS (no 'show' class).\n";
} else {
    echo "OK: No overlay element found at all.\n";
}
