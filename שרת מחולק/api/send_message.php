<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/json.php';
require_once __DIR__ . '/../lib/notify.php';

try {
    require_csrf();

    $pdo = db();
    $me  = current_user_id();
    if (!$me) json_err('UNAUTHORIZED', 'Login required', [], 401);

    // دعم JSON أو FormData (اختياري—but handy)
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJson = stripos($ct, 'application/json') !== false;
    $J = [];
    if ($isJson) {
        $raw = file_get_contents('php://input') ?: '';
        $J = json_decode($raw, true) ?: [];
    }
    $in = function(string $k, $def = null) use ($J) { return $_POST[$k] ?? $J[$k] ?? $def; };

    $threadId = (int)($in('thread_id', 0));
    $type     = (string)$in('type', 'text');
    $type     = $type === 'file' ? 'file' : 'text';

    if ($threadId <= 0) json_err('BAD_REQUEST', 'thread_id is required', [], 400);

    // تحقق المشاركة
    $st = $pdo->prepare("SELECT 1 FROM task_thread_participants WHERE thread_id=? AND user_id=? LIMIT 1");
    $st->execute([$threadId, $me]);
    if (!$st->fetchColumn()) json_err('FORBIDDEN', 'Forbidden', [], 403);

    $text = null;
    $fileUrl = null;

    if ($type === 'file') {
        $f = $_FILES['file'] ?? ($_FILES['attachment'] ?? null);
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            json_err('BAD_REQUEST', 'file is required', [], 400);
        }
        $name = basename((string)$f['name']);
        $tmp  = (string)$f['tmp_name'];
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $uploadsDir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
        $dest = rtrim($uploadsDir, '/\\') . '/chat_' . time() . '_' . $safe;
        if (!@move_uploaded_file($tmp, $dest)) {
            json_err('SERVER_ERROR', 'Upload failed', [], 500);
        }
         $fileUrl = '/api/serve-file.php?file=' . urlencode(basename($dest));
    } else {
        $t = $in('text');
        if ($t === null) $t = $in('message');
        if ($t === null) $t = $in('body');
        if ($t === null) $t = $in('content');
        $text = trim((string)($t ?? ''));
        if ($text === '') json_err('BAD_REQUEST', 'text is empty', [], 400);
    }

    // ابدأ الترانزكشن فقط إذا لم تكن قائمة
    if (!$pdo->inTransaction()) $pdo->beginTransaction();

    // إدراج الرسالة
    $st = $pdo->prepare("INSERT INTO task_messages (thread_id, sender_id, type, text, file_url) VALUES (?, ?, ?, ?, ?)");
    $st->execute([$threadId, $me, $type, $text, $fileUrl]);
    $messageId = (int)$pdo->lastInsertId();

    // علّم رسالة المُرسل كمقروءة له
    $pdo->prepare("INSERT IGNORE INTO task_message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())")
        ->execute([$messageId, $me]);

    // اجلب task_id لصناعة رابط المهمة
    $st = $pdo->prepare("
        SELECT t.id AS task_id
        FROM task_threads tt
        JOIN tasks t ON t.id = tt.task_id
        WHERE tt.id = ?
        LIMIT 1
    ");
    $st->execute([$threadId]);
    $taskId = (int)$st->fetchColumn();

    // اجلب المشاركين الآخرين (سنُشعرهم بعد الـ COMMIT)
    $st = $pdo->prepare("SELECT user_id FROM task_thread_participants WHERE thread_id=? AND user_id<>?");
    $st->execute([$threadId, $me]);
    $others = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    // انهي ترانزكشن الرسالة
    if ($pdo->inTransaction()) $pdo->commit();

    // ===== مهم: نفّذ الإشعارات بعد الـ COMMIT لتجنب nested TX =====
    if (!empty($others)) {
        // لو كانت notify_users تبدأ ترانزكشن، الآن لا توجد واحدة نشِطة
        notify_users(
            $pdo,
            $others,
            'New chat message',
            'You have a new message on a task',
            '/app/tasks/' . $taskId
        );
    }

    json_ok([
        'id'         => $messageId,
        'thread_id'  => $threadId,
        'sender_id'  => $me,
        'type'       => $type,
        'text'       => $text,
        'file_url'   => $fileUrl,
        'created_at' => date('c'),
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_err('SERVER_ERROR', $e->getMessage(), [], 500);
}
