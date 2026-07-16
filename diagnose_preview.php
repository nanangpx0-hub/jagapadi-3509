<?php
// Test the /laporan page for preview overlay issues
$url = 'http://localhost/jagapadi/laporan';

// Fetch the page (simulate browser)
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0\r\n" .
                    "Accept: text/html\r\n"
    ]
];
$context = stream_context_create($options);
$html = @file_get_contents($url, false, $context);

if ($html === false) {
    echo "ERROR: Could not fetch $url\n";
    echo "Make sure Laragon is running and the server is up.\n";
    exit(1);
}

echo "=== Preview Overlay Analysis ===\n\n";

// 1. Check overlay presence
if (strpos($html, 'photoPreviewOverlay') !== false) {
    echo "✓ Overlay element exists in DOM\n";
} else {
    echo "✗ Overlay element NOT found\n";
}

// 2. Check if overlay has 'show' class by default
if (preg_match('/<div[^>]*id="photoPreviewOverlay"[^>]*class="[^"]*show[^"]*"/', $html)) {
    echo "✗ PROBLEM: Overlay has 'show' class → WILL BE VISIBLE\n";
    $hasShow = true;
} else {
    echo "✓ Overlay does NOT have 'show' class → hidden by default\n";
    $hasShow = false;
}

// 3. Extract and analyze CSS for .photo-preview-overlay
if (preg_match('/\.photo-preview-overlay\s*\{[^}]*\}/s', $html, $match)) {
    $css = $match[0];
    echo "\n--- .photo-preview-overlay CSS ---\n";
    echo $css . "\n";
    
    // Count display declarations
    $displayCount = substr_count($css, 'display:');
    echo "Display declarations: $displayCount\n";
    if ($displayCount > 1) {
        echo "✗ WARNING: Multiple 'display' declarations - last one wins!\n";
    }
    
    // Check if display: none is overridden by later declaration
    if (strpos($css, 'display: none;') !== false && strrpos($css, 'display:') !== strpos($css, 'display: none;')) {
        $lastDisplayPos = strrpos($css, 'display:');
        $afterLast = substr($css, $lastDisplayPos);
        if (strpos($afterLast, 'display: none;') === false) {
            echo "✗ CRITICAL: 'display: none' is overridden by later declaration!\n";
        }
    }
}

// 4. Check .photo-preview-overlay.show exists
if (preg_match('/\.photo-preview-overlay\.show\s*\{[^}]*\}/s', $html, $match2)) {
    echo "\n--- .photo-preview-overlay.show CSS ---\n";
    echo $match2[0] . "\n";
    echo "✓ Show modifier class defined\n";
} else {
    echo "\n✗ .photo-preview-overlay.show modifier class NOT found\n";
}

// 5. Check if any inline style makes overlay visible
if (preg_match('/<div[^>]*id="photoPreviewOverlay"[^>]*style="[^"]*display:\s*flex/i', $html)) {
    echo "✗ Overlay has inline display:flex → always visible!\n";
}

// Summary
echo "\n=== DIAGNOSIS ===\n";
if ($hasShow) {
    echo "ISSUE: Overlay has 'show' class in HTML → forced visible.\n";
    echo "FIX: Remove 'show' class from overlay div.\n";
} elseif (strpos($html, 'display: flex;') !== false && strpos($html, '.photo-preview-overlay.show') !== false) {
    // Check if display:flex is in base rule
    if (preg_match('/\.photo-preview-overlay\s*\{[^}]*display:\s*flex/s', $html)) {
        echo "ISSUE: Base .photo-preview-overlay CSS has 'display: flex' → always visible.\n";
        echo "FIX: Move 'display: flex' to .photo-preview-overlay.show modifier only.\n";
    } else {
        echo "CSS structure appears correct. Preview should be hidden.\n";
    }
} else {
    echo "Preview overlay should be hidden. If still visible, check browser console for JS errors.\n";
}
