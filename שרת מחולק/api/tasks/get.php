<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', null, 405);
}

require_auth();
require_permission('tasks', 'read');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid task id', ['field' => 'id'], 422);
}

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

$roleStmt = $pdo->prepare("
    SELECT COALESCE(r.slug, '') AS slug
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleStmt->execute([$uid]);
$isAdmin = strtolower((string)$roleStmt->fetchColumn()) === 'admin';

$hasColumn = static function(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
};

$hasCreatedAt = $hasColumn($pdo, 'tasks', 'created_at');
$hasPriority = $hasColumn($pdo, 'tasks', 'priority');

$selectCols = [
    't.id',
    't.project_id',
    't.title',
    't.title AS name',
    't.description',
    't.status',
    't.due_date',
    't.assignee_id',
    'u.name AS assignee_name',
    'p.name AS project_name',
    'p.owner_id',
    '(SELECT name FROM users WHERE id = p.owner_id) AS owner_name',
];

if ($hasPriority) {
    $selectCols[] = 't.priority';
}
if ($hasCreatedAt) {
    $selectCols[] = 't.created_at';
}

$sql = "
    SELECT " . implode(",\n           ", $selectCols) . "
    FROM tasks t
    LEFT JOIN projects p ON p.id = t.project_id
    LEFT JOIN users u ON u.id = t.assignee_id
    WHERE t.id = ?
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$id]);
$task = $st->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    json_err('NOT_FOUND', 'Task not found', ['id' => $id], 404);
}

$projectId = (int)$task['project_id'];
$ownerId = (int)$task['owner_id'];
$assigneeId = isset($task['assignee_id']) ? (int)$task['assignee_id'] : 0;

if (!$isAdmin && $ownerId !== $uid && $assigneeId !== $uid) {
    $memberStmt = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $memberStmt->execute([$projectId, $uid]);
    if (!$memberStmt->fetchColumn()) {
        json_err('FORBIDDEN', 'You do not have access to this task', ['task_id' => $id], 403);
    }
}

if ($hasPriority && array_key_exists('priority', $task)) {
    if ($task['priority'] === null || $task['priority'] === '') {
        $task['priority'] = null;
    } elseif (is_numeric($task['priority'])) {
        $priority = (int)$task['priority'];
        $task['priority'] = $priority === 3 ? 'high' : ($priority === 2 ? 'medium' : 'low');
    } else {
        $priority = strtolower((string)$task['priority']);
        $task['priority'] = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : null;
    }
}

json_ok($task);
