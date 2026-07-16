<?php
$url = 'http://localhost/jagapadi/laporan';
$html = @file_get_contents($url);
if ($html === false) {
    die("Failed to fetch $url\n");
}

echo "Content length: " . strlen($html) . " bytes\n";

// Simple string search
$needle = 'photoPreviewOverlay';
$pos = strpos($html, $needle);
if ($pos !== false) {
    echo "✓ Found '$needle' at position $pos\n";
    // Show context
    $start = max(0, $pos - 100);
    $snippet = substr($html, $start, 200);
    echo "Context: ...$snippet...\n";
} else {
    echo "✗ '$needle' NOT found in HTML\n";
    // Search for 'photo' at least
    if (strpos($html, 'photo') !== false) {
        echo " 'photo' string exists somewhere\n";
    }
}

// Check if it's a redirect
if (strpos($html, '<!DOCTYPE') !== false || strpos($html, '<html') !== false) {
    echo "✓ Full HTML document\n";
} else {
    echo "Content might be a redirect or partial HTML\n";
    echo "First 500 chars: " . substr($html, 0, 500) . "\n";
}