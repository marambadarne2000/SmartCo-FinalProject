<?php
declare(strict_types=1);

/**
 * Admin -> Upload employee CV file
 * This endpoint uploads a CV file, saves it on the server,
 * and stores the file URL inside employee_profiles.cv.
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

/**
 * Send CORS headers.
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

    header('Access-Control-Allow-Methods: POST, OPTIONS');
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
 * Ensure session is started.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/**
 * Fallback helper for current user id.
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
    if (!$uid) {
      json_err('UNAUTHORIZED', 'Not logged in', (object)[], 401);
    }

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

    if ($slug === 'admin') {
      return;
    }

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

/**
 * Allow POST only.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

require_auth();
require_permission('users', 'update');
require_csrf();

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
 * Read target employee id.
 */
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0) {
  json_err('BAD_INPUT', 'user_id is required', null, 422);
}

/**
 * Make sure the employee exists.
 */
$userStmt = $pdo->prepare("
  SELECT id
  FROM users
  WHERE id = ?
  LIMIT 1
");
$userStmt->execute([$userId]);

if (!$userStmt->fetchColumn()) {
  json_err('NOT_FOUND', 'Employee not found', null, 404);
}

/**
 * Validate uploaded file.
 */
$file = $_FILES['cv'] ?? null;
if (!$file || !isset($file['tmp_name']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
  json_err('BAD_INPUT', 'cv file is required', null, 422);
}

$originalName = (string)($file['name'] ?? 'cv_file');
$tmpName = (string)$file['tmp_name'];
$size = (int)($file['size'] ?? 0);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

/**
 * Allow only common CV file types.
 */
$allowedExtensions = ['pdf', 'doc', 'docx'];
if (!in_array($ext, $allowedExtensions, true)) {
  json_err('BAD_INPUT', 'Only PDF, DOC, and DOCX files are allowed', null, 422);
}

/**
 * Limit file size to 10 MB.
 */
if ($size <= 0 || $size > 10 * 1024 * 1024) {
  json_err('BAD_INPUT', 'File size must be between 1 byte and 10 MB', null, 422);
}

/**
 * Create upload directory if it does not exist.
 */
$uploadDir = $ROOT . '/uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
  json_err('SERVER_ERROR', 'Failed to create upload directory', null, 500);
}

/**
 * Create a safe unique filename.
 */
$safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
if (!$safeBaseName) {
  $safeBaseName = 'cv';
}

$finalFileName = 'employee_' . $userId . '_' . time() . '_' . $safeBaseName . '.' . $ext;
$destination = $uploadDir . '/' . $finalFileName;

/**
 * Move uploaded file into the upload directory.
 */
if (!move_uploaded_file($tmpName, $destination)) {
  json_err('SERVER_ERROR', 'Failed to save uploaded file', null, 500);
}

/**
 * Build the public file URL.
 */
$fileUrl = '/api/serve-file.php?file=' . urlencode($finalFileName);
/**
 * Save the uploaded CV file URL into employee_profiles.
 * If the profile exists, update it.
 * If not, create a new profile row first.
 */
$profileExistsStmt = $pdo->prepare("
  SELECT id
  FROM employee_profiles
  WHERE user_id = ?
  LIMIT 1
");
$profileExistsStmt->execute([$userId]);
$profileId = (int)($profileExistsStmt->fetchColumn() ?: 0);

if ($profileId > 0) {
  $updateStmt = $pdo->prepare("
    UPDATE employee_profiles
    SET cv = ?, updated_at = CURRENT_TIMESTAMP
    WHERE user_id = ?
  ");
  $updateStmt->execute([$fileUrl, $userId]);
} else {
  $insertStmt = $pdo->prepare("
    INSERT INTO employee_profiles (user_id, cv)
    VALUES (?, ?)
  ");
  $insertStmt->execute([$userId, $fileUrl]);
}

/**
 * Return success response.
 */
json_ok([
  'user_id' => $userId,
  'file_name' => $finalFileName,
  'file_url' => $fileUrl,
]);