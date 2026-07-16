<?php
// Create a test session and fetch as authenticated user
session_start();
$_SESSION['user_id'] = 2;  // admin user
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['nama_lengkap'] = 'Administrator';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Now include the view file directly to see rendered output
require 'config/config.php';
require 'config/database.php';

// Simulate authenticated session
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';
$_SESSION['nama_lengkap'] = 'Admin User';
$_SESSION['csrf_token'] = 'test_csrf_123456';

// Try to capture output buffer
ob_start();
require 'app/views/laporan/index.php';
$content = ob_get_clean();

echo "Rendered content length: " . strlen($content) . " bytes\n";

// Check for overlay
if (strpos($content, 'photoPreviewOverlay') !== false) {
    echo "✓ Overlay present\n";
} else {
    echo "✗ Overlay NOT found in rendered view\n";
}

// Check for show class
if (preg_match('/<div[^>]*id="photoPreviewOverlay"[^>]*class="[^"]*show[^"]*"/', $content, $m)) {
    echo "✗ Overlay has 'show' class - WILL BE VISIBLE\n";
    echo "Match: " . $m[0] . "\n";
} else {
    echo "✓ Overlay does NOT have show class\n";
}

// Show CSS snippet for overlay
if (preg_match('/\.photo-preview-overlay\s*\{[^}]*\}/s', $content, $m)) {
    echo "\n--- Overlay CSS ---\n";
    echo substr($m[0], 0, 300) . "\n";
}