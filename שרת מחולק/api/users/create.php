<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/json.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', '', [], 405);
}

require_auth();

// Polyfill: لو عندك require_perm فقط، عرّف require_permission اعتمادًا عليها
if (!function_exists('require_permission') && function_exists('require_perm')) {
  function require_permission(string $module, string $action): void {
    require_perm($module, $action);
  }
}

// تحقّق الصلاحية (المثالي عبر نظام الأذونات)
if (function_exists('require_permission')) {
  // يسمح للأدوار التي تمتلك إذن users.create (غالبًا admin/manager)
  require_permission('users', 'create');
} else {
  // Fallback احتياطي: اسمح فقط للأدمن والمدير
  $pdo = db();
  $uid = (int)($_SESSION['user_id'] ?? 0);
  $st = $pdo->prepare('
    SELECT r.slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
  ');
  $st->execute([$uid]);
  $slug = strtolower((string)$st->fetchColumn());
  if (!in_array($slug, ['admin', 'manager'], true)) {
    json_err('FORBIDDEN', 'Forbidden', [], 403);
  }
}

// نقرأ بيانات الطلب (JSON أو POST) بشكل آمن
$in = json_input();

// التحقق الأساسي
$name     = trim((string)($in['name'] ?? ''));
$email    = trim((string)($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');
$role     = strtolower(trim((string)($in['role'] ?? 'employee')));
$status   = strtolower(trim((string)($in['status'] ?? 'active')));

if ($name === '' || $email === '' || $password === '') {
  json_err('VALIDATION', 'name/email/password required', ['missing' => ['name','email','password']], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_err('VALIDATION', 'invalid email', ['field' => 'email'], 422);
}
if (!in_array($role, ['admin','manager','employee'], true)) {
  json_err('VALIDATION', 'invalid role', ['role' => $role], 422);
}
if (!in_array($status, ['active','inactive','banned'], true)) {
  json_err('VALIDATION', 'invalid status', ['status' => $status], 422);
}

$pdo = $pdo ?? db();

// فحص تفرّد البريد
$st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$st->execute([$email]);
if ($st->fetch()) {
  json_err('DUPLICATE', 'email already exists', ['email' => $email], 409);
}

// تحضير role_id من جدول roles
$stR = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
$stR->execute([$role]);
$role_id = (int)($stR->fetchColumn() ?: 0);
if ($role_id <= 0) {
  json_err('VALIDATION', 'role not mapped in roles table', ['role' => $role], 422);
}

// إنشاء المستخدم
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

$stIns = $pdo->prepare('
  INSERT INTO users (name, email, password_hash, role, role_id, status)
  VALUES (?,?,?,?,?,?)
');
$stIns->execute([$name, $email, $hash, $role, $role_id, $status]);

$id = (int)$pdo->lastInsertId();

json_ok(['id' => $id]);
