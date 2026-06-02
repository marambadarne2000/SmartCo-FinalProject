<?php
declare(strict_types=1);

/**
 * Admin -> Employee profile details
 * This endpoint returns one employee with personal profile fields.
 */

$ROOT = realpath(__DIR__ . '/../../..');
if ($ROOT === false || !is_dir($ROOT)) {
  $ROOT = 'C:\\xampp\\htdocs\\backend';
}

/**
 * Safely load required files.
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

/**
 * Basic CORS headers.
 */
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

/**
 * Ensure session exists.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/**
 * Fallback helper if project does not already define it.
 */
if (!function_exists('current_user_id')) {
  function current_user_id(): ?int {
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (isset($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    return null;
  }
}

/**
 * Fallback auth helper.
 */
if (!function_exists('require_auth')) {
  function require_auth(): void {
    if (!current_user_id()) {
      json_err('UNAUTHORIZED', 'Not logged in', (object)[], 401);
    }
  }
}

/**
 * Fallback permission helper.
 */
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

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
  json_err('BAD_INPUT', 'user_id is required', null, 422);
}

/**
 * Load user + employee profile in one query.
 */
$sql = "
SELECT
  u.id,
  u.name,
  u.email,
  u.status,
  r.slug AS role_slug,
  r.name AS role_name,
  ep.experience,
  ep.bio,
  ep.skills,
  ep.notes,
  ep.department,
  ep.phone,
  ep.address,
  ep.cv,
  ep.previous_jobs
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
LEFT JOIN employee_profiles ep ON ep.user_id = u.id
WHERE u.id = :user_id
LIMIT 1
";

try {
  $st = $pdo->prepare($sql);
  $st->bindValue(':user_id', $userId, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
  error_log('PROFILE_QUERY_ERROR: ' . $e->getMessage());
  json_err('DB_QUERY_ERROR', 'Failed to load employee profile', null, 500);
}

if (!$row) {
  json_err('NOT_FOUND', 'Employee not found', null, 404);
}

/**
 * Normalize result values.
 */
$row['id'] = (int)$row['id'];
$row['name'] = (string)($row['name'] ?? '');
$row['email'] = (string)($row['email'] ?? '');
$row['status'] = (string)($row['status'] ?? '');
$row['role_slug'] = (string)($row['role_slug'] ?? '');
$row['role_name'] = (string)($row['role_name'] ?? '');
$row['experience'] = $row['experience'] ?? '';
$row['bio'] = $row['bio'] ?? '';
$row['skills'] = $row['skills'] ?? '';
$row['notes'] = $row['notes'] ?? '';
$row['department'] = $row['department'] ?? '';
$row['phone'] = $row['phone'] ?? '';
$row['address'] = $row['address'] ?? '';
$row['cv'] = $row['cv'] ?? '';
$row['previous_jobs'] = $row['previous_jobs'] ?? '';

json_ok($row);