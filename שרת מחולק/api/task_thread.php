<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/json.php';  // يوفر json_ok/json_err
require_once __DIR__ . '/../lib/utils.php';  // احتياطي لو الدوال هنا
// لا حاجة لـ require_csrf() لأننا GET

try {
    $pdo = db();
    $me  = current_user_id();
    if (!$me) {
        json_err('UNAUTHORIZED', 'Login required', [], 401);
    }

    $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
    if ($taskId <= 0) {
        json_err('BAD_REQUEST', 'task_id is required', [], 400);
    }

    // احضر المهمة + مالك المشروع + المكلّف
    $st = $pdo->prepare("
        SELECT t.id AS task_id, t.assignee_id, p.owner_id
        FROM tasks t
        JOIN projects p ON p.id = t.project_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $st->execute([$taskId]);
    $task = $st->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        json_err('NOT_FOUND', 'Task not found', [], 404);
    }

    $ownerId    = (int)$task['owner_id'];
    $assigneeId = (int)($task['assignee_id'] ?? 0);

    // السماح: أدمن أو مالك أو مكلّف
    $isAdmin = false;
    $st = $pdo->prepare("SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1");
    $st->execute([$me]);
    $roleSlug = $st->fetchColumn();
    if ($roleSlug === 'admin') $isAdmin = true;

    if (!$isAdmin && $me !== $ownerId && ($assigneeId === 0 || $me !== $assigneeId)) {
        json_err('FORBIDDEN', 'Forbidden', [], 403);
    }

    // إيجاد أو إنشاء القناة
    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT id FROM task_threads WHERE task_id=? LIMIT 1");
    $st->execute([$taskId]);
    $threadId = (int)$st->fetchColumn();

    if ($threadId === 0) {
        $creator = $ownerId ?: $me;
        $st = $pdo->prepare("INSERT INTO task_threads (task_id, created_by) VALUES (?, ?)");
        $st->execute([$taskId, $creator]);
        $threadId = (int)$pdo->lastInsertId();
    }

    // ضم المشاركين
    $ins = $pdo->prepare("INSERT IGNORE INTO task_thread_participants (thread_id, user_id, role_hint) VALUES (?, ?, ?)");
    $ins->execute([$threadId, $ownerId, 'manager']);
    if ($assigneeId > 0) {
        $ins->execute([$threadId, $assigneeId, 'employee']);
    }

    // (اختياري) ضمّ الأدمن كـ مراقب دائم
    // $ins->execute([$threadId, 1, 'admin']);

    // استرجاع المشاركين بالأسماء
    $ps = $pdo->prepare("
        SELECT ttp.user_id, u.name, u.email, ttp.role_hint
        FROM task_thread_participants ttp
        JOIN users u ON u.id = ttp.user_id
        WHERE ttp.thread_id = ?
        ORDER BY ttp.joined_at ASC
    ");
    $ps->execute([$threadId]);
    $participants = $ps->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    json_ok([
        'thread'       => ['id' => $threadId, 'task_id' => $taskId],
        'participants' => $participants,
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_err('SERVER_ERROR', $e->getMessage(), [], 500);
}
