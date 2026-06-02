<?php
// /api/time/clock_out.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/json.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    // لغير GET نلزم CSRF
    require_csrf();
}

$uid = current_user_id();
if (!$uid) { json_err('UNAUTHORIZED', 'Not logged in', [], 401); }

$db = db();
$st = $db->prepare("
    UPDATE work_sessions
    SET ended_at = NOW(),
        last_seen = NOW(),
        seconds_worked = seconds_worked + GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())),
        closed_reason = COALESCE(closed_reason, 'logout')
    WHERE user_id = ? AND ended_at IS NULL
");
$st->execute([$uid]);

json_ok(['clocked_out' => true, 'affected' => $st->rowCount()]);
