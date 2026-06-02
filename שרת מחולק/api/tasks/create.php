<?php
declare(strict_types=1);

// From api/tasks → api → (project root)
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

// Only POST is allowed for creating tasks
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

require_auth();             // User must be logged in
require_csrf();             // Validate CSRF token
require_permission('tasks', 'create'); // User must have permission to create tasks

$pdo = db(); // Database connection (PDO object)
$uid = (int)($_SESSION['user_id'] ?? 0);

/* ================= Helper functions for task capacity ================= */

// Get the maximum number of active tasks allowed for a user
if (!function_exists('get_user_task_limit')) {
    function get_user_task_limit(PDO $pdo, int $userId): int {
        // If column max_active_tasks does not exist or fails → default to 3
        try {
            $st = $pdo->prepare("SELECT max_active_tasks FROM users WHERE id = ?");
            $st->execute([$userId]);
            $val = $st->fetchColumn();
            if ($val === false || $val === null) return 3;
            $lim = (int)$val;
            return $lim > 0 ? $lim : 3;
        } catch (Throwable $e) {
            return 3;
        }
    }
}

// Count how many active tasks a user currently has (todo/in_progress)
if (!function_exists('get_user_active_task_count')) {
    function get_user_active_task_count(PDO $pdo, int $userId): int {
        $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status IN ('todo','in_progress')");
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }
}

// Check if user has reached their task limit; if yes → throw error
if (!function_exists('ensure_capacity_or_fail')) {
    function ensure_capacity_or_fail(PDO $pdo, int $assigneeId): void {
        $limit = get_user_task_limit($pdo, $assigneeId);
        $count = get_user_active_task_count($pdo, $assigneeId);
        if ($count >= $limit) {
            json_err(
                'TASK_LIMIT_REACHED',
                "User has reached the active task limit ($limit).",
                ['limit' => $limit, 'active' => $count],
                409
            );
        }
    }
}

/* ================= Get user role ================= */

$roleStmt = $pdo->prepare("
    SELECT r.slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleStmt->execute([$uid]);
$roleSlug = (string)($roleStmt->fetchColumn() ?: '');
$isAdmin  = ($roleSlug === 'admin');

/* ================= Read and validate input ================= */

$in = json_input();

$projectId   = isset($in['project_id']) ? (int)$in['project_id'] : 0;
$title       = trim((string)($in['title'] ?? $in['name'] ?? ''));
$description = isset($in['description']) ? trim((string)$in['description']) : '';
$status      = (string)($in['status'] ?? 'todo');
$assigneeId  = (array_key_exists('assignee_id', $in) && $in['assignee_id'] !== null) ? (int)$in['assignee_id'] : null;
$dueRaw      = isset($in['due_date']) && $in['due_date'] !== '' ? (string)$in['due_date'] : null;

// Handle priority field: accept "low/medium/high" or numeric values
$priority = null;
if (isset($in['priority'])) {
    $p = $in['priority'];
    if (is_string($p)) {
        $map = ['low' => 1, 'medium' => 2, 'high' => 3];
        $priority = $map[strtolower($p)] ?? null;
    } elseif (is_numeric($p)) {
        $priority = (int)$p; // Accept 0..3 depending on DB configuration
    }
}

/* ================= Basic validation ================= */

if ($projectId <= 0) {
    json_err('VALIDATION_ERROR', 'project_id is required', ['field' => 'project_id'], 422);
}
if ($title === '' || mb_strlen($title) < 3) {
    json_err('VALIDATION_ERROR', 'Title must be at least 3 characters', ['field' => 'title'], 422);
}
if (!in_array($status, ['todo','in_progress','done'], true)) {
    json_err('VALIDATION_ERROR', 'Invalid status', ['field' => 'status'], 422);
}
if ($dueRaw !== null) {
    $dt = DateTime::createFromFormat('Y-m-d', $dueRaw);
    if (!$dt || $dt->format('Y-m-d') !== $dueRaw) {
        json_err('VALIDATION_ERROR', 'Invalid due_date (expected YYYY-MM-DD)', ['field' => 'due_date'], 422);
    }
}
if ($assigneeId !== null) {
    $chkAssignee = $pdo->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
    $chkAssignee->execute([$assigneeId]);
    if (!$chkAssignee->fetchColumn()) {
        json_err('VALIDATION_ERROR', 'Assignee not found', ['field' => 'assignee_id'], 422);
    }
}

// Check project exists
$chkProj = $pdo->prepare("SELECT owner_id FROM projects WHERE id = ? LIMIT 1");
$chkProj->execute([$projectId]);
$projOwnerId = $chkProj->fetchColumn();
if (!$projOwnerId) {
    json_err('NOT_FOUND', 'Project not found', ['project_id' => $projectId], 404);
}

// For non-admin users: must be project owner or member
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
        json_err('FORBIDDEN', 'You do not have access to this project', ['project_id' => $projectId], 403);
    }
}

/* ================= Task creation with capacity and transaction ================= */
/*
   To prevent race conditions:
   - Lock the assignee row before checking capacity and inserting task.
   - If no assignee or status is "done" → it doesn’t count as active, no need to lock.
*/

try {
    if ($assigneeId !== null && in_array($status, ['todo','in_progress'], true)) {
        $pdo->beginTransaction();

        // Lock the user row (assignee) to prevent race conditions
        $lock = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $lock->execute([$assigneeId]);

        // Check capacity limit
        ensure_capacity_or_fail($pdo, $assigneeId);

        // Insert the task
        $st = $pdo->prepare("
            INSERT INTO tasks (project_id, title, description, status, assignee_id, due_date, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $projectId,
            $title,
            $description,
            $status,
            $assigneeId,
            $dueRaw,
            $priority
        ]);

        $id = (int)$pdo->lastInsertId();
        $pdo->commit();

        json_ok(['id' => $id]);
    } else {
        // No assignee or task is "done" → skip capacity check
        $st = $pdo->prepare("
            INSERT INTO tasks (project_id, title, description, status, assignee_id, due_date, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $projectId,
            $title,
            $description,
            $status,
            $assigneeId,
            $dueRaw,
            $priority
        ]);

        $id = (int)$pdo->lastInsertId();
        json_ok(['id' => $id]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TASK_CREATE_ERROR: ' . $e->getMessage());
    // If json_err was already called inside ensure_capacity_or_fail we won’t reach here
    json_err('DB_ERROR', 'Failed to create task', null, 500);
}
