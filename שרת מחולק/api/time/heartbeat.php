<?php
// /api/time/heartbeat.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/json.php';

require_auth();

$uid = current_user_id();
if (!$uid) { json_err('UNAUTHORIZED', 'Not logged in', [], 401); }

$db = db();

$st = $db->prepare("SELECT id FROM work_sessions WHERE user_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
$st->execute([$uid]);
$row = $st->fetch();

if (!$row) {
    json_ok(['has_open' => false]);
}

$upd = $db->prepare("UPDATE work_sessions SET last_seen = NOW() WHERE id = ?");
$upd->execute([(int)$row['id']]);

json_ok(['has_open' => true, 'session_id' => (int)$row['id']]);
