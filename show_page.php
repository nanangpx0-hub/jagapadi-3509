<?php
$html = @file_get_contents('http://localhost/jagapadi/laporan');
if ($html === false) {
    die("Failed to fetch\n");
}
echo "First 2000 characters:\n";
echo substr($html, 0, 2000);
echo "\n\n=== Full content ===\n";
echo $html;