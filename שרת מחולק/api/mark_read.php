<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/json.php';

try {
    require_csrf();

    $pdo = db();
    $me  = current_user_id();
    if (!$me) json_err('UNAUTHORIZED', 'Login required', [], 401);

    $data     = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);
    $threadId = (int)($data['thread_id'] ?? 0);
    $upToId   = (int)($data['up_to_message_id'] ?? 0);

    if ($threadId <= 0 || $upToId <= 0) {
        json_err('BAD_REQUEST', 'thread_id and up_to_message_id are required', [], 400);
    }

    // تحقق المشاركة
    $st = $pdo->prepare("SELECT 1 FROM task_thread_participants WHERE thread_id=? AND user_id=? LIMIT 1");
    $st->execute([$threadId, $me]);
    if (!$st->fetchColumn()) json_err('FORBIDDEN', 'Forbidden', [], 403);

    // تعليم مقروء
    $sql = "
        INSERT IGNORE INTO task_message_reads (message_id, user_id, read_at)
        SELECT tm.id, :me, NOW()
        FROM task_messages tm
        WHERE tm.thread_id = :thread
          AND tm.id <= :upTo
    ";
    $stm = $pdo->prepare($sql);
    $stm->execute([':me' => $me, ':thread' => $threadId, ':upTo' => $upToId]);

    json_ok(['ok' => true]);

} catch (Throwable $e) {
    json_err('SERVER_ERROR', $e->getMessage(), [], 500);
}
