<?php
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/authz_db.php';

require_auth();
require_csrf();
require_permission('users', 'update');

$in = json_input();
$uid = (int)($in['id'] ?? 0);
if ($uid <= 0) {
    json_err('VALIDATION', 'Invalid id');
}

$pdo = db();

// ✅ التأكد أن المستخدم موجود
$st = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
$st->execute([$uid]);
$existingUser = $st->fetch(PDO::FETCH_ASSOC);
if (!$existingUser) {
    json_err('NOT_FOUND', 'User not found', [], 404);
}

$fields = [];
$args   = [];

// 📝 تحديث الاسم
if (array_key_exists('name', $in)) {
    $name = trim((string)$in['name']);
    if ($name !== '' && mb_strlen($name) < 3) {
        json_err('VALIDATION', 'Name too short');
    }
    if ($name !== '') {
        $fields[] = 'name = ?';
        $args[]   = $name;
    }
}

// 📝 تحديث البريد
if (array_key_exists('email', $in)) {
    $email = strtolower(trim((string)$in['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('VALIDATION', 'Invalid email');
    }
    $chk = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id <> ?");
    $chk->execute([$email, $uid]);
    if ($chk->fetchColumn()) {
        json_err('VALIDATION', 'Email already exists');
    }
    $fields[] = 'email = ?';
    $args[]   = $email;
}

// 📝 تحديث الدور
if (array_key_exists('role', $in)) {
    $role = (string)$in['role'];
    if (ctype_digit($role)) {
        $role_id = (int)$role;
    } else {
        $r = $pdo->prepare("SELECT id FROM roles WHERE slug = ?");
        $r->execute([$role]);
        $role_id = (int)$r->fetchColumn();
    }
    if ($role_id <= 0) {
        json_err('VALIDATION', 'Invalid role');
    }
    $fields[] = 'role_id = ?';
    $args[]   = $role_id;
}

// 📝 تحديث كلمة المرور
if (array_key_exists('password', $in)) {
    $pw = (string)$in['password'];
    if ($pw !== '' && mb_strlen($pw) < 6) {
        json_err('VALIDATION', 'Password too short');
    }
    if ($pw !== '') {
        $fields[] = 'password_hash = ?';
        $args[]   = password_hash($pw, PASSWORD_DEFAULT);
    }
}

// 📝 تحديث الحالة (اختياري)
if (array_key_exists('status', $in)) {
    $status = strtolower(trim((string)$in['status']));
    if (!in_array($status, ['active', 'suspended'], true)) {
        json_err('VALIDATION', 'Invalid status');
    }
    $fields[] = 'status = ?';
    $args[]   = $status;
}

// 🚫 لا يوجد أي تحديث
if (!$fields) {
    json_err('VALIDATION', 'Nothing to update');
}

// تنفيذ التحديث
$args[] = $uid;
$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
$up  = $pdo->prepare($sql);
$up->execute($args);

json_ok([
    'updated' => (int)$up->rowCount(),
    'id'      => $uid
]);
