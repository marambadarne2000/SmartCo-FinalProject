<?php
declare(strict_types=1);

// From api/tasks → api → (project root)
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

/* ===============================
   Allow only POST requests
================================= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

/* ===============================
   Security checks
   - require_auth()  → user must be logged in
   - require_csrf()  → protect against CSRF attacks
   - require_permission() → check user has permission to delete tasks
================================= */
require_auth();
require_csrf();
require_permission('tasks', 'delete');

/* ===============================
   Read input JSON
================================= */
$in = json_input();
$id = isset($in['id']) ? (int)$in['id'] : 0;

// Validate task ID
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid task ID', ['field' => 'id'], 422);
}

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

/* ===============================
   Check if task exists and fetch its project_id
================================= */
$st = $pdo->prepare("SELECT t.project_id FROM tasks t WHERE t.id = ? LIMIT 1");
$st->execute([$id]);
$projectId = $st->fetchColumn();

if (!$projectId) {
    json_err('NOT_FOUND', 'Task not found', ['id' => $id], 404);
}

/* ===============================
   Check user role (is admin or not)
================================= */
$roleSt = $pdo->prepare("
    SELECT r.slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleSt->execute([$uid]);
$isAdmin = ((string)$roleSt->fetchColumn() === 'admin');

/* ===============================
   If user is not admin:
   - Must be either the project owner
   - Or a project member
   Otherwise: access forbidden
================================= */
if (!$isAdmin) {
    $stVis = $pdo->prepare("
        SELECT 1
        FROM projects p
        WHERE p.id = ?
          AND (p.owner_id = ? OR EXISTS (
              SELECT 1 FROM project_members pm
              WHERE pm.project_id = p.id AND pm.user_id = ?
          ))
        LIMIT 1
    ");
    $stVis->execute([$projectId, $uid, $uid]);
    if (!$stVis->fetchColumn()) {
        json_err('FORBIDDEN', 'You do not have access to this task', ['task_id' => $id], 403);
    }
}

/* ===============================
   Delete the task
   - If successful → return deleted = true
   - If fails → handle error safely
================================= */
try {
    $del = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $del->execute([$id]);

    json_ok([
        'deleted' => $del->rowCount() > 0,
        'id'      => $id
    ]);
} catch (Throwable $e) {
    error_log('TASK_DELETE_ERROR: ' . $e->getMessage());
    json_err('DB_ERROR', 'Failed to delete task', null, 500);
}
