<?php
// /api/time/clock_in.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/json.php';

require_auth();
require_csrf();

$uid = current_user_id();
if (!$uid) { json_err('UNAUTHORIZED', 'Not logged in', [], 401); }

$db = db();
try {
    $db->beginTransaction();

    // أغلق أي جلسة مفتوحة حالية (سلامة)
    $st = $db->prepare("
        UPDATE work_sessions
        SET ended_at = NOW(),
            last_seen = NOW(),
            seconds_worked = seconds_worked + GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())),
            closed_reason = COALESCE(closed_reason, 'force_close')
        WHERE user_id = ? AND ended_at IS NULL
    ");
    $st->execute([$uid]);

    // افتح جلسة جديدة
    $st2 = $db->prepare("
        INSERT INTO work_sessions (user_id, started_at, last_seen, source, ip, user_agent)
        VALUES (?, NOW(), NOW(), 'web', ?, ?)
    ");
    $st2->execute([
        $uid,
        $_SERVER['REMOTE_ADDR']   ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $sid = (int)$db->lastInsertId();
    $db->commit();
    json_ok(['clocked_in' => true, 'session_id' => $sid]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    json_err('CLOCK_IN_ERROR', $e->getMessage(), [], 500);
}
