<?php
declare(strict_types=1);

/**
 * Admin/Manager -> Update employee settings (and status)
 * - Updates: hourly_rate, max_active_tasks, status
 * - Requires: authenticated session + CSRF + users:update permission
 */

$ROOT = dirname(__DIR__, 3);

require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

/* ---------- Method guard ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if ($method !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

/* ---------- Read body ONCE and prep CSRF ---------- */
$raw = file_get_contents('php://input') ?: '';
$in  = [];

if ($raw !== '') {
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) {
    $in = $tmp;

    // If csrf is provided in JSON body, forward it to header before require_csrf()
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) && isset($in['csrf']) && is_string($in['csrf'])) {
      $_SERVER['HTTP_X_CSRF_TOKEN'] = $in['csrf'];
    }
  }
}

/* ---------- AuthN / CSRF / AuthZ ---------- */
require_auth();                   // 401 if not logged in
require_csrf();                   // 419 if CSRF fails
require_permission('users', 'update'); // Admin or any role with users:update permission

$pdo = db();

/* ---------- Validate & sanitize input ---------- */
$userId         = isset($in['user_id']) ? (int)$in['user_id'] : 0;
$hourlyRate     = array_key_exists('hourly_rate', $in) ? (float)$in['hourly_rate'] : null;
$maxActiveTasks = array_key_exists('max_active_tasks', $in) ? (int)$in['max_active_tasks'] : null;
$status         = array_key_exists('status', $in) ? strtolower(trim((string)$in['status'])) : null;

/**
 * Allowed input keys (extra keys are ignored).
 */
$allowedKeys = ['user_id', 'hourly_rate', 'max_active_tasks', 'status', 'csrf'];
$unknown = array_diff(array_keys($in), $allowedKeys);
// You can reject unknown keys if you want strict validation.
// if ($unknown) json_err('VALIDATION', 'Unknown fields provided', ['unknown' => array_values($unknown)], 422);

if ($userId <= 0) {
  json_err('VALIDATION', 'Invalid user_id', ['field' => 'user_id'], 422);
}

if ($hourlyRate !== null && $hourlyRate < 0) {
  json_err('VALIDATION', 'hourly_rate must be >= 0', ['field' => 'hourly_rate'], 422);
}

if ($maxActiveTasks !== null && $maxActiveTasks < 0) {
  json_err('VALIDATION', 'max_active_tasks must be >= 0', ['field' => 'max_active_tasks'], 422);
}

/**
 * Status: allow exactly what you requested.
 * (If you still use "suspended" elsewhere, you can add it here too.)
 */
$allowedStatuses = ['active', 'inactive', 'banned'];
if ($status !== null && !in_array($status, $allowedStatuses, true)) {
  json_err('VALIDATION', 'Invalid status', ['field' => 'status', 'allowed' => $allowedStatuses], 422);
}

if ($hourlyRate === null && $maxActiveTasks === null && $status === null) {
  json_ok(['updated' => false, 'message' => 'Nothing to update']);
}

/* ---------- Ensure target user exists ---------- */
try {
  $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
  $chk->execute([$userId]);
  $exists = (bool)$chk->fetchColumn();
  if (!$exists) {
    json_err('NOT_FOUND', 'User not found', ['user_id' => $userId], 404);
  }
} catch (Throwable $e) {
  error_log('USER EXISTENCE CHECK ERROR: ' . $e->getMessage());
  json_err('DB_ERROR', 'Failed to verify user', null, 500);
}

/* ---------- Build update ---------- */
$parts  = [];
$params = [];

if ($hourlyRate !== null) {
  $parts[]  = "hourly_rate = ?";
  $params[] = $hourlyRate;
}
if ($maxActiveTasks !== null) {
  $parts[]  = "max_active_tasks = ?";
  $params[] = $maxActiveTasks;
}
if ($status !== null) {
  $parts[]  = "status = ?";
  $params[] = $status;
}

$params[] = $userId;
$sql = "UPDATE users SET " . implode(', ', $parts) . " WHERE id = ?";

/* ---------- Execute within a transaction ---------- */
try {
  $pdo->beginTransaction();
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $affected = $st->rowCount();
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('USER UPDATE ERROR: ' . $e->getMessage());
  json_err('DB_QUERY_ERROR', 'Failed to update user', null, 500);
}

/* ---------- Response ---------- */
$updatedFields = [];
if ($hourlyRate !== null)     $updatedFields[] = 'hourly_rate';
if ($maxActiveTasks !== null) $updatedFields[] = 'max_active_tasks';
if ($status !== null)         $updatedFields[] = 'status';

json_ok([
  'updated'        => $affected > 0,
  'user_id'        => $userId,
  'updated_fields' => $updatedFields,
  'affected_rows'  => $affected,
]);