<?php
declare(strict_types=1);

// من api/tasks → api → (جذر المشروع)
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

// اسمح بـ POST فقط
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

require_auth();
require_csrf();
require_permission('tasks', 'update');

$in = json_input();
$id = isset($in['id']) ? (int)$in['id'] : 0;
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid task ID', ['field' => 'id'], 422);
}

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

// جلب project_id للمهمة للتحقق من نطاق الرؤية
$st = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ? LIMIT 1");
$st->execute([$id]);
$projectId = $st->fetchColumn();
if (!$projectId) {
    json_err('NOT_FOUND', 'Task not found', ['id' => $id], 404);
}

// هل المستخدم Admin؟
$roleSt = $pdo->prepare("
    SELECT r.slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleSt->execute([$uid]);
$isAdmin = ((string)$roleSt->fetchColumn() === 'admin');

// لغير الأدمن: يجب أن يكون مالك المشروع أو عضوًا فيه
if (!$isAdmin) {
    $vis = $pdo->prepare("
        SELECT 1
        FROM projects p
        WHERE p.id = ?
          AND (p.owner_id = ? OR EXISTS (
              SELECT 1 FROM project_members pm
              WHERE pm.project_id = p.id AND pm.user_id = ?
          ))
        LIMIT 1
    ");
    $vis->execute([$projectId, $uid, $uid]);
    if (!$vis->fetchColumn()) {
        json_err('FORBIDDEN', 'You do not have access to this task', ['task_id' => $id], 403);
    }
}

// ===== بناء جملة التحديث حسب الحقول المُرسلة =====
$sets = [];
$params = [];

// title/name
if (array_key_exists('title', $in) || array_key_exists('name', $in)) {
    $title = trim((string)($in['title'] ?? $in['name'] ?? ''));
    if ($title === '' || mb_strlen($title) < 3) {
        json_err('VALIDATION_ERROR', 'Title must be at least 3 characters', ['field' => 'title'], 422);
    }
    $sets[] = "title = ?";
    $params[] = $title;
}

// description
if (array_key_exists('description', $in)) {
    $desc = trim((string)$in['description']);
    $sets[] = "description = ?";
    $params[] = $desc;
}

// status
if (array_key_exists('status', $in)) {
    $status = (string)$in['status'];
    if (!in_array($status, ['todo','in_progress','done'], true)) {
        json_err('VALIDATION_ERROR', 'Invalid status', ['field' => 'status'], 422);
    }
    $sets[] = "status = ?";
    $params[] = $status;
}

// priority (يدعم نص أو رقم؛ يخزّن tinyint 1/2/3 أو NULL)
if (array_key_exists('priority', $in)) {
    $p = $in['priority'];
    if ($p === null || $p === '') {
        $sets[] = "priority = NULL";
    } else {
        if (is_string($p)) {
            $map = ['low' => 1, 'medium' => 2, 'high' => 3];
            $pi = $map[strtolower($p)] ?? null;
        } elseif (is_numeric($p)) {
            $pi = (int)$p;
        } else {
            $pi = null;
        }
        if ($pi === null) {
            $sets[] = "priority = NULL";
        } else {
            $sets[] = "priority = ?";
            $params[] = $pi;
        }
    }
}

// assignee_id (يدعم null لإزالة الإسناد)
if (array_key_exists('assignee_id', $in)) {
    if ($in['assignee_id'] === null || $in['assignee_id'] === '') {
        $sets[] = "assignee_id = NULL";
    } else {
        $assignee = (int)$in['assignee_id'];
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
        $chk->execute([$assignee]);
        if (!$chk->fetchColumn()) {
            json_err('VALIDATION_ERROR', 'Assignee not found', ['field' => 'assignee_id'], 422);
        }
        $sets[] = "assignee_id = ?";
        $params[] = $assignee;
    }
}

// due_date (YYYY-MM-DD أو null)
if (array_key_exists('due_date', $in)) {
    if ($in['due_date'] === null || $in['due_date'] === '') {
        $sets[] = "due_date = NULL";
    } else {
        $due = (string)$in['due_date'];
        $dt = DateTime::createFromFormat('Y-m-d', $due);
        if (!$dt || $dt->format('Y-m-d') !== $due) {
            json_err('VALIDATION_ERROR', 'Invalid due_date (expected YYYY-MM-DD)', ['field' => 'due_date'], 422);
        }
        $sets[] = "due_date = ?";
        $params[] = $due;
    }
}

if (empty($sets)) {
    // لا شيء لتحديثه
    json_ok(['updated' => 0]);
}

// تنفيذ التحديث
try {
    $sql = "UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ?";
    $params[] = $id;
    $up = $pdo->prepare($sql);
    $up->execute($params);
    $updated = $up->rowCount();

    // إعادة المهمة بعد التحديث (اختياري)
    $task = null;
    if ($updated > 0) {
        $q = $pdo->prepare("
            SELECT t.*, u.name AS assignee_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $q->execute([$id]);
        $task = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    json_ok(['updated' => $updated, 'task' => $task]);
} catch (Throwable $e) {
    error_log('TASK_UPDATE_ERROR: ' . $e->getMessage());
    json_err('DB_ERROR', 'Failed to update task', null, 500);
}
