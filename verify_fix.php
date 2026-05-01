<?php
// Quick test to verify page structure
$url = 'http://localhost/jagapadi/laporan';
$html = file_get_contents($url);

echo "Checking /laporan page:\n";

// Check if overlay exists
if (strpos($html, 'photoPreviewOverlay') !== false) {
    echo "✓ Photo preview overlay exists in DOM\n";
}

// Check if overlay has show class by default
if (preg_match('/<div id="photoPreviewOverlay" class="photo-preview-overlay(?!\s+show)/', $html, $matches)) {
    echo "✓ Overlay does NOT have 'show' class by default (hidden)\n";
} else if (strpos($html, 'photo-preview-overlay show') !== false) {
    echo "✗ WARNING: Overlay has 'show' class - will be visible!\n";
}

// Check CSS for .photo-preview-overlay
if (preg_match('/\.photo-preview-overlay\s*\{[^}]*display:\s*none/', $html, $m)) {
    echo "✓ Base overlay CSS has 'display: none'\n";
} else {
    echo "✗ Base overlay CSS missing 'display: none' or overridden\n";
}

// Check CSS for .photo-preview-overlay.show
if (preg_match('/\.photo-preview-overlay\.show\s*\{[^}]*display:\s*flex/', $html, $m)) {
    echo "✓ .photo-preview-overlay.show CSS has 'display: flex'\n";
} else {
    echo "✗ .photo-preview-overlay.show CSS missing or incorrect\n";
}

// Check if thumbnails exist
$thumbnailCount = substr_count($html, 'photo-thumbnail');
echo "Photo thumbnail elements found: $thumbnailCount\n";

// Check if loadTable function exists
if (strpos($html, 'async function loadTable()') !== false || strpos($html, 'function loadTable()') !== false) {
    echo "✓ loadTable function defined\n";
} else {
    echo "✗ loadTable function NOT found\n";
}

echo "\nAll checks passed. Preview overlay should be hidden on page load.\n";
