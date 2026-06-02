<?php
// public/api/users/index.php
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/json.php';

// لو عندك RBAC، فعّل قراءة المستخدمين فقط لمن يملك الإذن
// require_auth();
// require_perm('users', 'read');

$pdo = db();

// فلاتر اختيارية via querystring
$role   = $_GET['role']   ?? null;   // admin | manager | employee
$status = $_GET['status'] ?? 'active';

$sql = "SELECT id, name, email FROM users WHERE 1=1";
$params = [];

if ($status) { $sql .= " AND status = :status"; $params[':status'] = $status; }
if ($role)   { $sql .= " AND role = :role";     $params[':role']   = $role;   }

$sql .= " ORDER BY name ASC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

json_ok($rows);
