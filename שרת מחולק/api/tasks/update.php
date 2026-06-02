<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 2); // = backend

require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/json.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

require_auth();
require_csrf();
require_permission('tasks', 'update');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

/* ===== Helpers: task capacity (limit per user) ===== */
function get_user_task_limit(PDO $pdo, int $userId): int {
    try {
        $st = $pdo->prepare("SELECT max_active_tasks FROM users WHERE id = ?");
        $st->execute([$userId]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null) return 3;
        $lim = (int)$val;
        return $lim > 0 ? $lim : 3;
    } catch (Throwable $e) {
        return 3; // fallback if column missing
    }
}
function get_user_active_task_count(PDO $pdo, int $userId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status IN ('todo','in_progress')");
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}
function ensure_capacity_or_fail(PDO $pdo, int $assigneeId): void {
    $limit = get_user_task_limit($pdo, $assigneeId);
    $count = get_user_active_task_count($pdo, $assigneeId);
    if ($count >= $limit) {
        json_err('TASK_LIMIT_REACHED', "User has reached the active task limit ($limit).", [
            'limit' => $limit, 'active' => $count,
        ], 409);
    }
}

/* ===== Input ===== */
$in = json_input();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid task id', ['field' => 'id'], 422);
}

// حقول اختيارية (نحدّث فقط المُرسل)
$title       = array_key_exists('title', $in)       ? trim((string)$in['title'])       : null;
$desc        = array_key_exists('description', $in) ? (string)$in['description']       : null;
$status      = array_key_exists('status', $in)      ? (string)$in['status']            : null;
$priorityIn  = array_key_exists('priority', $in)    ? $in['priority']                  : null;
$assigneeIn  = array_key_exists('assignee_id', $in) ? $in['assignee_id']               : null; // قد تكون null لمسح المكلّف
$dueIn       = array_key_exists('due_date', $in)    ? $in['due_date']                  : null;

/* ===== Fetch current task + project for authZ ===== */
$st = $pdo->prepare("
    SELECT t.id, t.project_id, t.assignee_id AS old_assignee_id, t.status AS old_status, p.owner_id
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE t.id = ?
    LIMIT 1
");
$st->execute([$id]);
$cur = $st->fetch(PDO::FETCH_ASSOC);
if (!$cur) {
    json_err('NOT_FOUND', 'Task not found', ['id' => $id], 404);
}
$projectId    = (int)$cur['project_id'];
$oldAssignee  = $cur['old_assignee_id'] !== null ? (int)$cur['old_assignee_id'] : null;
$oldStatus    = (string)$cur['old_status'];
$projectOwner = (int)$cur['owner_id'];

/* ===== Role/admin check ===== */
$roleStmt = $pdo->prepare("
    SELECT r.slug
    FROM users u JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? LIMIT 1
");
$roleStmt->execute([$uid]);
$isAdmin = ((string)$roleStmt->fetchColumn() === 'admin');

/* ===== Non-admin authorization =====
   يسمح لغير الأدمن بالتحديث فقط إذا:
   - مالك المشروع، أو
   - عضو بالمشروع، أو
   - هو نفسه المكلّف الحالي للمهمة
*/
if (!$isAdmin) {
    $allowed = false;

    if ($projectOwner === $uid) {
        $allowed = true;
    } else {
        // عضو بالمشروع؟
        $stM = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $stM->execute([$projectId, $uid]);
        if ($stM->fetchColumn()) {
            $allowed = true;
        }
        // أو المكلّف الحالي
        if (!$allowed && $oldAssignee !== null && $oldAssignee === $uid) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        json_err('FORBIDDEN', 'You do not have access to update this task', ['task_id' => $id], 403);
    }
}

/* ===== Validate optional fields ===== */

// status
if ($status !== null && !in_array($status, ['todo','in_progress','done'], true)) {
    json_err('VALIDATION_ERROR', 'Invalid status', ['field' => 'status'], 422);
}

// due_date
$dueDate = null;
if ($dueIn !== null) {
    if ($dueIn === '') {
        $dueDate = null; // مسح التاريخ
    } else {
        $dueDate = (string)$dueIn;
        $dt = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$dt || $dt->format('Y-m-d') !== $dueDate) {
            json_err('VALIDATION_ERROR', 'Invalid due_date (expected YYYY-MM-DD)', ['field' => 'due_date'], 422);
        }
    }
}

// assignee
$targetAssignee = $oldAssignee;
$assigneeProvided = array_key_exists('assignee_id', $in);
if ($assigneeProvided) {
    if ($assigneeIn === null || $assigneeIn === '') {
        $targetAssignee = null; // إزالة المكلّف
    } else {
        $targetAssignee = (int)$assigneeIn;
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
        $chk->execute([$targetAssignee]);
        if (!$chk->fetchColumn()) {
            json_err('VALIDATION_ERROR', 'Assignee not found', ['field' => 'assignee_id'], 422);
        }
    }
}

// priority
$prioritySqlPart = '';
$priorityParam   = null;
if ($priorityIn !== null) {
    if ($priorityIn === '') {
        $prioritySqlPart = ", priority = NULL";
    } else {
        if (is_numeric($priorityIn)) {
            $priorityParam = (int)$priorityIn;
        } else {
            $p = strtolower((string)$priorityIn);
            $priorityParam = $p === 'high' ? 3 : ($p === 'medium' ? 2 : ($p === 'low' ? 1 : null));
        }
        $prioritySqlPart = ", priority = ?";
    }
}

/* ===== Capacity check logic =====
   - إذا تغيّر المكلّف، أو
   - إذا تحوّلت الحالة من منتهية إلى نشطة
*/
$targetStatus   = $status !== null ? $status : $oldStatus;
$becomingActive = in_array($targetStatus, ['todo','in_progress'], true);
$wasActive      = in_array($oldStatus,    ['todo','in_progress'], true);
$assigneeChanged = ($targetAssignee !== $oldAssignee);

// نحتاج فحص السعة فقط إن أصبح الهدف نشطًا
$needCapacityCheck = $becomingActive && ($assigneeChanged || !$wasActive);

try {
    if ($needCapacityCheck && $targetAssignee !== null) {
        $pdo->beginTransaction();

        // اقفل صف المستخدم المستهدف
        $lock = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $lock->execute([$targetAssignee]);

        ensure_capacity_or_fail($pdo, $targetAssignee);

        // ابني UPDATE الآن داخل نفس الترانزاكشن
        $sets = [];
        $params = [];

        if ($title !== null) { $sets[] = "title = ?";        $params[] = $title; }
        if ($desc  !== null) { $sets[] = "description = ?";  $params[] = $desc; }
        if ($status !== null){ $sets[] = "status = ?";       $params[] = $targetStatus; }
        if ($assigneeProvided) { $sets[] = "assignee_id = ?"; $params[] = $targetAssignee; }
        if ($dueIn !== null)  { $sets[] = "due_date = ?";    $params[] = $dueDate; }

        if ($prioritySqlPart !== '') {
            $sets[] = substr($prioritySqlPart, 2); // "priority = ?/NULL"
            if ($priorityParam !== null) $params[] = $priorityParam;
        }

        if (!$sets) {
            $pdo->rollBack();
            json_ok(['updated' => false, 'id' => $id, 'message' => 'Nothing to update']);
        }

        $params[] = $id;
        $sql = "UPDATE tasks SET ".implode(', ', $sets)." WHERE id = ?";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        $pdo->commit();
        json_ok(['updated' => true, 'id' => $id]);
    } else {
        // لا حاجة لقفل/فحص سعة، نفّذ UPDATE عادي
        $sets = [];
        $params = [];

        if ($title !== null) { $sets[] = "title = ?";        $params[] = $title; }
        if ($desc  !== null) { $sets[] = "description = ?";  $params[] = $desc; }
        if ($status !== null){ $sets[] = "status = ?";       $params[] = $targetStatus; }
        if ($assigneeProvided) { $sets[] = "assignee_id = ?"; $params[] = $targetAssignee; }
        if ($dueIn !== null)  { $sets[] = "due_date = ?";    $params[] = $dueDate; }

        if ($prioritySqlPart !== '') {
            $sets[] = substr($prioritySqlPart, 2);
            if ($priorityParam !== null) $params[] = $priorityParam;
        }

        if (!$sets) {
            json_ok(['updated' => false, 'id' => $id, 'message' => 'Nothing to update']);
        }

        $params[] = $id;
        $sql = "UPDATE tasks SET ".implode(', ', $sets)." WHERE id = ?";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        json_ok(['updated' => true, 'id' => $id]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TASK_UPDATE_ERROR: ' . $e->getMessage());
    json_err('DB_ERROR', 'Failed to update task', null, 500);
}
