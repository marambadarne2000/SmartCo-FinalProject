<?php
// /api/reports/tasks_per_project.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', '405');
}

require_auth();
require_permission('tasks', 'read');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 50)) : 10;

$roleStmt = $pdo->prepare("
  SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id
  WHERE u.id = ? LIMIT 1
");
$roleStmt->execute([$uid]);
$isAdmin = ($roleStmt->fetchColumn() === 'admin');

$scopeSql = '';
$scopeParams = [];
if (!$isAdmin) {
  $scopeSql = " WHERE (p.owner_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?) OR EXISTS (SELECT 1 FROM tasks tx WHERE tx.project_id = p.id AND tx.assignee_id = ?))";
  $scopeParams = [$uid, $uid, $uid];
}


$sql = "
  SELECT p.id, p.name AS project, COUNT(t.id) AS cnt
  FROM projects p
  LEFT JOIN tasks t ON t.project_id = p.id
  $scopeSql
  GROUP BY p.id, p.name
  ORDER BY cnt DESC, p.id DESC
  LIMIT ?
";
$st = $pdo->prepare($sql);
$params = array_merge($scopeParams, [$limit]);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// تأكد من الأنواع
foreach ($rows as &$r) {
  $r['id'] = (int)$r['id'];
  $r['cnt'] = (int)$r['cnt'];
}
unset($r);

json_ok($rows);
