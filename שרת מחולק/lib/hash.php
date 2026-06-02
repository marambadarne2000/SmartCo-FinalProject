<?php
require_once __DIR__.'./db.php';
$pdo = db();

$newPass = '12345678'; // كلمة المرور الجديدة
$hash = password_hash($newPass, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password_hash=?, status='active' WHERE email=?")
    ->execute([$hash, 'admin@smartco.local']);

echo "Password reset for admin@smartco.local to {$newPass}\n";
