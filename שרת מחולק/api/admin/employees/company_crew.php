<?php
declare(strict_types=1);

/**
 * Admin -> Company crew list
 * Returns active company employees with basic info and optional current session info.
 */

$ROOT = realpath(__DIR__ . '/../../..');
if ($ROOT === false || !is_dir($ROOT)) {
  $ROOT = 'C:\\xampp\\htdocs\\backend';
}

/**
 * Safely load required project files.
 */
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
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', null, 405);
}

require_auth();
require_permission('users', 'read');

$pdo = db();
$uid = current_user_id();

/**
 * Allow admins only.
 */
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

/**
 * Detect whether work_sessions exists.
 */
$hasWorkSessions = false;
try {
  $pdo->query("SELECT 1 FROM work_sessions LIMIT 1");
  $hasWorkSessions = true;
} catch (\Throwable $e) {
  $hasWorkSessions = false;
}

$currentSessionJoin = $hasWorkSessions ? "
  LEFT JOIN (
    SELECT ws1.user_id, ws1.started_at
    FROM work_sessions ws1
    INNER JOIN (
      SELECT user_id, MAX(id) AS last_id
      FROM work_sessions
      GROUP BY user_id
    ) latest ON latest.last_id = ws1.id
  ) ws ON ws.user_id = u.id
" : "";

$currentSessionFields = $hasWorkSessions ? "
  , ws.started_at AS current_start
" : "
  , NULL AS current_start
";

$sql = "
SELECT
  u.id,
  u.name,
  u.email,
  u.status,
  r.slug AS role_slug,
  r.name AS role_name
  {$currentSessionFields}
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
{$currentSessionJoin}
WHERE u.status = 'active'
ORDER BY u.name ASC
";

try {
  $st = $pdo->prepare($sql);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
  error_log('COMPANY_CREW_QUERY_ERROR: ' . $e->getMessage());
  json_err('DB_QUERY_ERROR', 'Failed to load company crew', null, 500);
}

$data = array_map(static function (array $row): array {
  return [
    'id' => (int)$row['id'],
    'name' => (string)($row['name'] ?? ''),
    'email' => (string)($row['email'] ?? ''),
    'role' => (string)($row['role_name'] ?: $row['role_slug'] ?: ''),
    'status' => (string)($row['status'] ?? ''),
    'current_start' => $row['current_start'] ?? null,
    'current_duration' => null,
  ];
}, $rows);

json_ok($data, [
  'count' => count($data),
  'has_work_sessions' => $hasWorkSessions,
]);