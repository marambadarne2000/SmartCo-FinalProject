<?php
declare(strict_types=1);

/**
 * Admin -> Employee monthly attendance
 * GET params:
 *   user_id (int, required)
 *   year    (int, optional, default current year)
 *   month   (int, optional, default current month)
 */

$ROOT = realpath(__DIR__ . '/../../..');
if ($ROOT === false || !is_dir($ROOT)) {
  $ROOT = 'C:\\xampp\\htdocs\\backend';
}

function require_once_safe(string $path): void {
  if (!is_file($path)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => [
        'code' => 'BOOTSTRAP_MISSING',
        'message' => "Missing required file: $path",
      ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  require_once $path;
}

if (!function_exists('cors_send_headers')) {
  function cors_send_headers(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [
      'http://localhost:4200',
      'http://127.0.0.1:4200',
    ];
    if ($origin && in_array($origin, $allowed, true)) {
      header("Access-Control-Allow-Origin: {$origin}");
      header('Vary: Origin');
      header('Access-Control-Allow-Credentials: true');
    } else {
      header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
  }
}
if (!function_exists('cors_send_preflight')) {
  function cors_send_preflight(): void {
    cors_send_headers();
    http_response_code(204);
    exit;
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  cors_send_preflight();
}
cors_send_headers();

require_once_safe($ROOT . '/lib/utils.php');
require_once_safe($ROOT . '/lib/session.php');
require_once_safe($ROOT . '/lib/db.php');
require_once_safe($ROOT . '/lib/authz_db.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

if (!function_exists('current_user_id')) {
  function current_user_id(): ?int {
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (isset($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    return null;
  }
}
if (!function_exists('require_auth')) {
  function require_auth(): void {
    if (!current_user_id()) {
      json_err('UNAUTHORIZED', 'Not logged in', (object)[], 401);
    }
  }
}
if (!function_exists('require_permission')) {
  function require_permission(string $module, string $action): void {
    $uid = current_user_id();
    if (!$uid) json_err('UNAUTHORIZED', 'Not logged in', (object)[], 401);

    try {
      $pdo = db();

      $st = $pdo->prepare("
        SELECT r.slug
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
      ");
      $st->execute([$uid]);
      $slug = (string)$st->fetchColumn();
      if ($slug === 'admin') return;

      $st2 = $pdo->prepare("
        SELECT 1
        FROM users u
        JOIN role_permissions rp ON rp.role_id = u.role_id
        JOIN permissions p ON p.id = rp.permission_id
        WHERE u.id = ? AND p.module = ? AND p.action = ?
        LIMIT 1
      ");
      $st2->execute([$uid, strtolower($module), strtolower($action)]);
      if (!$st2->fetchColumn()) {
        json_err('FORBIDDEN', "Permission '{$module}:{$action}' denied", (object)[], 403);
      }
    } catch (\Throwable $e) {
      error_log('AUTHZ_ERROR: ' . $e->getMessage());
      json_err('AUTHZ_ERROR', 'Authorization check failed', (object)[], 500);
    }
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', null, 405);
}

require_auth();
require_permission('users', 'read');

$pdo = db();
$uid = current_user_id();

$roleStmt = $pdo->prepare("
  SELECT r.slug
  FROM users u
  JOIN roles r ON r.id = u.role_id
  WHERE u.id = ?
  LIMIT 1
");
$roleStmt->execute([$uid]);
if ((string)$roleStmt->fetchColumn() !== 'admin') {
  json_err('FORBIDDEN', 'Admins only', null, 403);
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$year   = isset($_GET['year']) ? max(1970, (int)$_GET['year']) : (int)date('Y');
$month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($month < 1 || $month > 12) $month = (int)date('n');

if ($userId <= 0) {
  json_err('BAD_INPUT', 'user_id is required', null, 422);
}

$start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$end   = date('Y-m-d H:i:s', strtotime("$start +1 month"));

$hasWorkSessions = false;
try {
  $pdo->query("SELECT 1 FROM work_sessions LIMIT 1");
  $hasWorkSessions = true;
} catch (\Throwable $e) {
  $hasWorkSessions = false;
}

$hourlyRate = 0.0;
try {
  $stRate = $pdo->prepare("SELECT hourly_rate FROM users WHERE id = ? LIMIT 1");
  $stRate->execute([$userId]);
  $hourlyRate = (float)($stRate->fetchColumn() ?: 0);
} catch (\Throwable $e) {
  $hourlyRate = 0.0;
}

$rows = [];

if ($hasWorkSessions) {
  $sql = "
    SELECT
      DATE(GREATEST(started_at, :start1)) AS work_date,
      MIN(started_at) AS first_start,
      MAX(ended_at) AS last_end,
      SUM(
        GREATEST(
          0,
          TIMESTAMPDIFF(
            SECOND,
            GREATEST(started_at, :start2),
            LEAST(COALESCE(ended_at, NOW()), :end1)
          )
        )
      ) / 3600 AS hours_day
    FROM work_sessions
    WHERE user_id = :uid
      AND COALESCE(ended_at, NOW()) > :start3
      AND started_at < :end2
    GROUP BY DATE(GREATEST(started_at, :start4))
    ORDER BY work_date ASC
  ";

  try {
    $st = $pdo->prepare($sql);
    $st->execute([
      ':uid'    => $userId,
      ':start1' => $start,
      ':start2' => $start,
      ':start3' => $start,
      ':start4' => $start,
      ':end1'   => $end,
      ':end2'   => $end,
    ]);
    $raw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($raw as $r) {
      $date = (string)$r['work_date'];
      $rows[] = [
        'date'        => $date,
        'day_name'    => date('l', strtotime($date)),
        'first_start' => $r['first_start'] ?: null,
        'last_end'    => $r['last_end'] ?: null,
        'hours'       => round((float)($r['hours_day'] ?? 0), 2),
      ];
    }
  } catch (\Throwable $e) {
    error_log('ATTENDANCE_QUERY_ERROR: ' . $e->getMessage());
    json_err('DB_QUERY_ERROR', 'Failed to load attendance', null, 500);
  }
}

$totalHours = 0.0;
$totalDays = 0;

foreach ($rows as $r) {
  $h = (float)($r['hours'] ?? 0);
  $totalHours += $h;
  if ($h > 0) $totalDays++;
}
$totalHours = round($totalHours, 2);
$estimatedPay = round($totalHours * $hourlyRate, 2);

json_ok([
  'rows' => $rows,
  'summary' => [
    'total_days' => $totalDays,
    'total_hours' => $totalHours,
    'estimated_pay' => $estimatedPay,
    'month' => $month,
    'year' => $year,
  ]
], [
  'has_work_sessions' => $hasWorkSessions
]);