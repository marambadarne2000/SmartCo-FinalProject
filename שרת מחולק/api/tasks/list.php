<?php
declare(strict_types=1);

// From api/tasks → api → (project root)
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

/* ===============================
   Allow only GET requests
================================= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', null, 405);
}

/* ===============================
   Security checks
   - User must be logged in
   - User must have permission to read tasks
================================= */
require_auth();
require_permission('tasks', 'read');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

/* ===============================
   Helper functions
================================= */
// Validate date in YYYY-MM-DD format
$validDate = static function (string $s): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $s);
    return $dt && $dt->format('Y-m-d') === $s;
};

// Check if a column exists in a given table
$hasColumn = static function(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
};

// Optional columns (not always available in DB)
$hasCreatedAt = $hasColumn($pdo, 'tasks', 'created_at');
$hasPriority  = $hasColumn($pdo, 'tasks', 'priority');

/* ===============================
   Determine if the user can read ALL tasks
   - Admins always can
   - Otherwise: check extra permissions if available
================================= */
$canReadAll = false;
try {
    // 1) Check user role directly from DB
    $stRole = $pdo->prepare("
        SELECT COALESCE(r.slug, '') AS slug
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stRole->execute([$uid]);
    $roleSlug = strtolower((string)$stRole->fetchColumn());

    if ($roleSlug === 'admin') {
        $canReadAll = true;
    }

    // 2) Optional: check permission helper
    if (!$canReadAll && function_exists('has_permission')) {
        if (has_permission('tasks', 'read_all', $uid)) {
            $canReadAll = true;
        }
    }

    // 3) Optional: check role helper
    if (!$canReadAll && function_exists('user_has_role')) {
        if (user_has_role($pdo, $uid, 'admin')) {
            $canReadAll = true;
        }
    }
} catch (Throwable $e) {
    // If error occurs → default false
}

/* ===============================
   Calculate user task capacity
   - max_active_tasks column if exists
   - otherwise default = 3
   - also count active tasks to know available slots
================================= */
$taskLimit = 3;
try {
    $stLim = $pdo->prepare("SELECT max_active_tasks FROM users WHERE id = ?");
    $stLim->execute([$uid]);
    $val = $stLim->fetchColumn();
    if ($val !== false && $val !== null) {
        $taskLimit = max(1, (int)$val);
    }
} catch (Throwable $e) {
    $taskLimit = 3;
}
$stActive = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status IN ('todo','in_progress')");
$stActive->execute([$uid]);
$activeCount = (int)$stActive->fetchColumn();
$availableSlots = max(0, $taskLimit - $activeCount);

/* ===============================
   Filters from GET query
================================= */
$q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$projectId  = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$status     = isset($_GET['status']) ? (string)$_GET['status'] : '';
$dueFrom    = isset($_GET['due_from']) ? (string)$_GET['due_from'] : '';
$dueTo      = isset($_GET['due_to'])   ? (string)$_GET['due_to']   : '';

// Assignee filter is allowed only for admins or users with read_all
$assigneeId = 0;
if ($canReadAll && isset($_GET['assignee_id'])) {
    $assigneeId = max(0, (int)$_GET['assignee_id']);
}

/* ===============================
   Pagination and ordering
================================= */
$pg     = paginate_from_query($_GET, 50, 200);
$limit  = $pg['limit'];
$offset = $pg['offset'];

// Allow sorting only by safe fields
$allowedOrderBy = [
    't.id','t.due_date','t.status','t.project_id'
];
if ($hasCreatedAt) $allowedOrderBy[] = 't.created_at';

$orderBy  = 't.id'; // default
if (!empty($_GET['order_by'])) {
    $candidate = strtolower(trim((string)$_GET['order_by']));
    foreach ($allowedOrderBy as $f) {
        if ($candidate === strtolower($f)) {
            $orderBy = $f;
            break;
        }
    }
}
$orderDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

/* ===============================
   Build WHERE conditions
================================= */
$params = [];
$where  = [];
$join   = "LEFT JOIN projects p ON p.id = t.project_id";
$join  .= " LEFT JOIN users u ON u.id = t.assignee_id";

// Search by title, description, project name, assignee name
if ($q !== '') {
    $like = '%' . sql_like_escape($q) . '%';
    $w = [];
    $w[] = "t.title LIKE ? ESCAPE '\\\\'";
    $params[] = $like;
    $w[] = "t.description LIKE ? ESCAPE '\\\\'";
    $params[] = $like;
    $w[] = "p.name LIKE ? ESCAPE '\\\\'";
    $params[] = $like;
    $w[] = "u.name LIKE ? ESCAPE '\\\\'";
    $params[] = $like;
    $where[] = '(' . implode(' OR ', $w) . ')';
}

// Filter by project
if ($projectId > 0) {
    $where[]  = "t.project_id = ?";
    $params[] = $projectId;
}

// Filter by status
if ($status !== '') {
    $s = strtolower($status);
    if (!in_array($s, ['todo','in_progress','done'], true)) {
        json_err('VALIDATION_ERROR', 'Invalid status', ['field' => 'status'], 422);
    }
    $where[]  = "t.status = ?";
    $params[] = $s;
}

// Filter by due date range
if ($dueFrom !== '') {
    if (!$validDate($dueFrom)) {
        json_err('VALIDATION_ERROR', 'Invalid due_from (expected YYYY-MM-DD)', ['field' => 'due_from'], 422);
    }
    $where[]  = "(t.due_date IS NOT NULL AND t.due_date >= ?)";
    $params[] = $dueFrom;
}
if ($dueTo !== '') {
    if (!$validDate($dueTo)) {
        json_err('VALIDATION_ERROR', 'Invalid due_to (expected YYYY-MM-DD)', ['field' => 'due_to'], 422);
    }
    $where[]  = "(t.due_date IS NOT NULL AND t.due_date <= ?)";
    $params[] = $dueTo;
}

/* ===============================
   Scope of visibility
   - Admin/read_all → see all
   - Otherwise → only my tasks
================================= */
if ($canReadAll) {
    if ($assigneeId > 0) {
        $where[]  = "t.assignee_id = ?";
        $params[] = $assigneeId;
    }
} else {
    $where[]  = "t.assignee_id = ?";
    $params[] = $uid;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ===============================
   Count total tasks (for pagination)
================================= */
$countSql = "SELECT COUNT(*) FROM tasks t $join $whereSql";
$stCnt = $pdo->prepare($countSql);
$stCnt->execute($params);
$total = (int)$stCnt->fetchColumn();

/* ===============================
   Select task fields
================================= */
$selectCols = [
    "t.id",
    "t.project_id",
    "t.title",
    "t.title AS name",
    "t.description",
    "t.status",
    "t.due_date",
    "t.assignee_id",
    "u.name AS assignee_name",
    "p.name AS project_name",
    "p.owner_id",
    "(SELECT name FROM users WHERE id = p.owner_id) AS owner_name"
];
if ($hasPriority)  $selectCols[] = "t.priority";
if ($hasCreatedAt) $selectCols[] = "t.created_at";

// Main query
$sql = "
SELECT
    " . implode(",\n    ", $selectCols) . "
FROM tasks t
$join
$whereSql
ORDER BY $orderBy $orderDir
LIMIT ? OFFSET ?
";
$st = $pdo->prepare($sql);
$st->execute(array_merge($params, [$limit, $offset]));
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ===============================
   Normalize priority values
   - Convert numeric to text: 1=low, 2=medium, 3=high
================================= */
if ($hasPriority) {
    foreach ($rows as &$r) {
        if (!array_key_exists('priority', $r) || $r['priority'] === null || $r['priority'] === '') {
            $r['priority'] = null;
            continue;
        }
        if (is_numeric($r['priority'])) {
            $pi = (int)$r['priority'];
            $r['priority'] = ($pi === 3 ? 'high' : ($pi === 2 ? 'medium' : ($pi === 1 ? 'low' : 'low')));
        } else {
            $p = strtolower((string)$r['priority']);
            $r['priority'] = in_array($p, ['low','medium','high'], true) ? $p : null;
        }
    }
    unset($r);
}

/* ===============================
   Final JSON response
   Includes:
   - Task list
   - Pagination data
   - Capacity data
   - Access scope
================================= */
json_ok($rows, [
    'total'        => $total,
    'limit'        => $limit,
    'offset'       => $offset,
    'mine'         => !$canReadAll && $assigneeId === 0,
    'active'       => $activeCount,
    'limit_max'    => $taskLimit,
    'available'    => $availableSlots,
    'can_read_all' => $canReadAll,
]);
