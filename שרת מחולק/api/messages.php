<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/json.php';

try {
    $pdo = db();
    $me  = current_user_id();
    if (!$me) json_err('UNAUTHORIZED', 'Login required', [], 401);

    $threadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
    $limit    = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;

    // ندعم before أو before_id
    $beforeId = 0;
    if (isset($_GET['before']))      $beforeId = (int)$_GET['before'];
    elseif (isset($_GET['before_id'])) $beforeId = (int)$_GET['before_id'];

    if ($threadId <= 0) json_err('BAD_REQUEST', 'thread_id is required', [], 400);

    // تأكيد المشاركة
    $st = $pdo->prepare("SELECT 1 FROM task_thread_participants WHERE thread_id = :tid AND user_id = :uid LIMIT 1");
    $st->execute([':tid' => $threadId, ':uid' => $me]);
    if (!$st->fetchColumn()) json_err('FORBIDDEN', 'Forbidden', [], 403);

    // نبني SQL بمعاملات مُسمّاة
    $sql = "
        SELECT
            tm.id,
            tm.thread_id,
            tm.sender_id,
            tm.type,
            tm.text,
            tm.file_url,
            tm.created_at,
            EXISTS(
                SELECT 1
                FROM task_message_reads r
                WHERE r.message_id = tm.id AND r.user_id = :me
            ) AS read_by_me
        FROM task_messages tm
        WHERE tm.thread_id = :threadId
    ";

    if ($beforeId > 0) {
        $sql .= " AND tm.id < :beforeId";
    }

    $sql .= " ORDER BY tm.id DESC LIMIT :lim";

    $st = $pdo->prepare($sql);
    $st->bindValue(':me', $me, PDO::PARAM_INT);
    $st->bindValue(':threadId', $threadId, PDO::PARAM_INT);
    if ($beforeId > 0) {
        $st->bindValue(':beforeId', $beforeId, PDO::PARAM_INT);
    }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);

    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // نرجّع تصاعدي
    $rows = array_reverse($rows);

    // نُنسّق created_at إلى ISO-8601 إن أمكن
    $messages = array_map(function ($m) {
        $iso = $m['created_at'];
        $ts = strtotime((string)$m['created_at']);
        if ($ts !== false) $iso = date('c', $ts);
        return [
            'id'         => (int)$m['id'],
            'thread_id'  => (int)$m['thread_id'],
            'sender_id'  => (int)$m['sender_id'],
            'type'       => $m['type'],
            'text'       => $m['text'],
            'file_url'   => $m['file_url'],
            'created_at' => $iso,
            'read_by_me' => (bool)$m['read_by_me'],
        ];
    }, $rows);

    json_ok([
        'messages' => $messages,
        'paging' => [
            'has_more'    => count($rows) === $limit,
            'next_before' => count($rows) ? (int)$rows[0]['id'] : null, // الأقدم في الصفحة الحالية
        ],
    ]);

} catch (Throwable $e) {
    json_err('SERVER_ERROR', $e->getMessage(), [], 500);
}
