<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=jagapadi_local;charset=utf8mb4';
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->prepare('DELETE FROM users WHERE username = ?')->execute(['audit_admin']);
echo "OK deleted audit_admin\n";