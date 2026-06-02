<?php
// /api/users/list.php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 2); // من api/users → api → جذر backend
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

// اسمح بـ GET فقط
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', [], 405);
}

require_auth();
require_permission('users', 'read');

$pdo = db();

// فلاتر اختيارية (بسيطة)
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status  = isset($_GET['status']) ? (string)$_GET['status'] : '';   // active/inactive/banned
$role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;

// WHERE ديناميكي
$where = [];
$params = [];

if ($q !== '') {
    $like = '%' . (function_exists('sql_like_escape') ? sql_like_escape($q) : $q) . '%';
    $where[] = "(u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $where[] = "u.status = ?";
    $params[] = $status;
}
if ($role_id > 0) {
    $where[] = "u.role_id = ?";
    $params[] = $role_id;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT
    u.id, u.name, u.email, u.status, u.created_at,
    u.role_id, r.slug AS role_slug, r.name AS role_name
  FROM users u
  JOIN roles r ON r.id = u.role_id
  $whereSql
  ORDER BY u.id DESC
  LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// لا تُرجع أي حقول حساسة كـ password_hash
json_ok(array_map(function ($u) {
    return [
        'id'         => (int)$u['id'],
        'name'       => (string)$u['name'],
        'email'      => (string)$u['email'],
        'status'     => (string)$u['status'],
        'created_at' => (string)$u['created_at'],
        'role_id'    => (int)$u['role_id'],
        'role_slug'  => (string)$u['role_slug'],
        'role_name'  => (string)$u['role_name'],
    ];
}, $rows));
