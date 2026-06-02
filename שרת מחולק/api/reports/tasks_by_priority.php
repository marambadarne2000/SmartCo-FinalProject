<?php
// /api/reports/tasks_by_priority.php
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
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$roleStmt = $pdo->prepare("
  SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id
  WHERE u.id = ? LIMIT 1
");
$roleStmt->execute([$uid]);
$isAdmin = ($roleStmt->fetchColumn() === 'admin');

$scopeSql = '';
$scopeParams = [];
if (!$isAdmin) {
  $scopeSql = " AND (p.owner_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?) OR t.assignee_id = ?)";
  $scopeParams = [$uid, $uid, $uid];
}

$filterSql = '';
$filterParams = [];
if ($projectId > 0) {
  $filterSql = " AND t.project_id = ? ";
  $filterParams[] = $projectId;
}

$sql = "
  SELECT
    CASE
  WHEN t.priority = 3 OR LOWER(CAST(t.priority AS CHAR)) = 'high' THEN 'high'
  WHEN t.priority = 2 OR LOWER(CAST(t.priority AS CHAR)) = 'medium' THEN 'medium'
  WHEN t.priority = 1 OR LOWER(CAST(t.priority AS CHAR)) = 'low' THEN 'low'
  WHEN t.priority = 0 THEN 'low'
  ELSE 'unknown'
END AS label,

    COUNT(*) AS count
  FROM tasks t
  JOIN projects p ON p.id = t.project_id
  WHERE 1=1
  $filterSql
  $scopeSql
  GROUP BY label
  ORDER BY FIELD(label, 'high','medium','low','unknown')
";
$st = $pdo->prepare($sql);
$st->execute(array_merge($filterParams, $scopeParams));
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$labels = ['high', 'medium', 'low'];
$map = [];
foreach ($rows as $r) {
    $map[$r['label']] = (int)$r['count'];
}

$out = [];
foreach ($labels as $l) {
    $out[] = ['label' => $l, 'count' => ($map[$l] ?? 0)];
}

json_ok($out);
