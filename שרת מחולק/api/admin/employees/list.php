<?php
declare(strict_types=1);

/**
 * Admin → Employees list (with active tasks + month hours)
 * GET params:
 *   year   (int)   default: current year
 *   month  (int)   1..12, default: current month
 *   status (str)   'active' | 'all'   default: 'active'
 *   limit  (int)   1..500  default: 200
 *   offset (int)   >=0     default: 0
 */

// ---------- Bootstrap (robust root resolution) ----------
$ROOT = realpath(__DIR__ . '/../../..'); // ../admin/../.. -> backend/
if ($ROOT === false || !is_dir($ROOT)) {
  // Fallback: عدّل هذا المسار عند الحاجة لبيئتك
  $ROOT = 'C:\\xampp\\htdocs\\backend';
}

function require_once_safe(string $path): void {
  if (!is_file($path)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => [
        'code'    => 'BOOTSTRAP_MISSING',
        'message' => "Missing required file: $path",
      ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  require_once $path;
}

/* ---------- Fallback CORS helpers (only if utils.php doesn’t define them) ---------- */
if (!function_exists('cors_send_headers')) {
  function cors_send_headers(): void {
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [
      'http://localhost:4200',
      'http://127.0.0.1:4200',
      // 'https://your-prod-domain.com',
    ];
    if ($origin && in_array($origin, $allowed, true)) {
      header("Access-Control-Allow-Origin: {$origin}");
      header('Vary: Origin');
      header('Access-Control-Allow-Credentials: true'); // للسماح بالكوكيز عبر كروس-أوريجن
    } else {
      // ملاحظة: عند استخدام withCredentials=true يجب ألا تكون القيمة '*'
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
    exit; // لا جسم
  }
}

/* ---------- CORS early handling ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  cors_send_preflight();
}
cors_send_headers();

/* ---------- Load libs (now actually required) ---------- */
require_once_safe($ROOT . '/lib/utils.php');    // json_ok/json_err & (ربما) cors helpers
require_once_safe($ROOT . '/lib/session.php');  // يحدد session_name() ويبدأ session عادةً
require_once_safe($ROOT . '/lib/db.php');
require_once_safe($ROOT . '/lib/authz_db.php');

/* ---------- Ensure session is started even if lib/session.php لم يبدأ ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/* ---------- Fallback auth helpers (فقط لو لم تقدّمها المكتبات) ---------- */
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
      // اسمح إن كان Admin
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

      // تحقّق الصلاحية
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

/* ---------- Method guard ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', null, 405);
}

/* ---------- AuthZ ---------- */
require_auth();
require_permission('users', 'read'); // قراءة المستخدمين فقط للأدوار المصرّح لها

$pdo = db();
$uid = current_user_id();

/* ---------- (اختياري) حصرها على الأدمن ---------- */
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

/* ---------- Inputs ---------- */
$year   = isset($_GET['year'])  ? max(1970, (int)$_GET['year']) : (int)date('Y');
$month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$status = isset($_GET['status']) ? strtolower((string)$_GET['status']) : 'active'; // active | all
if ($month < 1 || $month > 12) $month = (int)date('n');

/* نافذة الشهر: [mstart, mend) */
$mstart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$mend   = date('Y-m-d H:i:s', strtotime("$mstart +1 month"));

/* ---------- Pagination ---------- */
$limit  = isset($_GET['limit'])  ? max(1, min(500, (int)$_GET['limit'])) : 200;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

/* ---------- Feature detection ---------- */
$hasTimeEntries   = false;
$hasWorkSessions  = false;
try { $pdo->query("SELECT 1 FROM time_entries LIMIT 1"); $hasTimeEntries  = true; } catch (\Throwable $e) {}
try { $pdo->query("SELECT 1 FROM work_sessions LIMIT 1"); $hasWorkSessions = true; } catch (\Throwable $e) {}

/* ---------- Hours subquery (prefer work_sessions if available) ---------- */
if ($hasWorkSessions) {
  // استخدم أسماء باراميترات مميزة لكل ظهور لتجنّب HY093
  $hoursJoinSql = "
    SELECT
      user_id,
      SUM(
        GREATEST(
          0,
          TIMESTAMPDIFF(
            SECOND,
            GREATEST(started_at, :mstart1),
            LEAST(COALESCE(ended_at, NOW()), :mend1)
          )
        )
      ) / 3600 AS hours_month
    FROM work_sessions
    WHERE
      COALESCE(ended_at, NOW()) > :mstart2
      AND started_at < :mend2
    GROUP BY user_id
  ";
  $bindHours = [
    ':mstart1' => $mstart,
    ':mend1'   => $mend,
    ':mstart2' => $mstart,
    ':mend2'   => $mend,
  ];
} elseif ($hasTimeEntries) {
  // سلوك fallback على time_entries داخل الشهر
  $hoursJoinSql = "
    SELECT user_id, SUM(hours) AS hours_month
    FROM time_entries
    WHERE work_date BETWEEN :dfrom AND LAST_DAY(:dto)
    GROUP BY user_id
  ";
  $bindHours = [
    ':dfrom' => substr($mstart, 0, 10), // YYYY-MM-01
    ':dto'   => substr($mstart, 0, 10), // نفس اليوم، LAST_DAY يعطي آخر يوم
  ];
} else {
  // لا يوجد أي مصدر ساعات
  $hoursJoinSql = "SELECT 0 AS user_id, 0 AS hours_month";
  $bindHours = [];
}

/* ---------- Status filter ---------- */
$userFilterSql = ($status === 'active') ? "WHERE u.status = 'active'" : "";

/* ---------- Main query ---------- */
$sql = "
SELECT
  u.id,
  u.name,
  u.email,
  u.status,
  u.hourly_rate,
  u.max_active_tasks,
  r.slug AS role_slug,
  r.name AS role_name,
  COALESCE(t.active_tasks, 0) AS active_tasks,
  COALESCE(h.hours_month, 0) AS hours_month
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
LEFT JOIN (
  SELECT assignee_id, COUNT(*) AS active_tasks
  FROM tasks
  WHERE status IN ('todo','in_progress')
  GROUP BY assignee_id
) t ON t.assignee_id = u.id
LEFT JOIN (
  {$hoursJoinSql}
) h ON h.user_id = u.id
{$userFilterSql}
ORDER BY u.id ASC
LIMIT :limit OFFSET :offset
";

try {
  $st = $pdo->prepare($sql);
  $st->bindValue(':limit',  $limit,  PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  // اربط متغيرات الساعات حسب المصدر
  foreach ($bindHours as $k => $v) {
    $st->bindValue($k, $v);
  }
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
  error_log('DB_QUERY_ERROR: ' . $e->getMessage());
  json_err('DB_QUERY_ERROR', 'Failed to load employees list', [
    'hint' => 'Check SQL, indexes, and table existence',
  ], 500);
}

/* ---------- Normalize & respond ---------- */
foreach ($rows as &$r) {
  $r['id']               = (int)$r['id'];
  $r['hourly_rate']      = (float)($r['hourly_rate'] ?? 0);
  $r['max_active_tasks'] = (int)($r['max_active_tasks'] ?? 0);
  $r['active_tasks']     = (int)($r['active_tasks'] ?? 0);
  $r['hours_month']      = (float)($r['hours_month'] ?? 0);
  if ($r['role_slug'] === null) $r['role_slug'] = '';
  if ($r['role_name'] === null) $r['role_name'] = '';
  $r['month'] = (int)$month;
  $r['year']  = (int)$year;
}
unset($r);

$meta = [
  'year'               => $year,
  'month'              => $month,
  'limit'              => $limit,
  'offset'             => $offset,
  'has_time_entries'   => $hasTimeEntries,
  'has_work_sessions'  => $hasWorkSessions,
  'window_start'       => $mstart,
  'window_end'         => $mend,
];

json_ok($rows, $meta);
