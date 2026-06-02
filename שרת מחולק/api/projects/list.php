<?php
// /api/projects/list.php
declare(strict_types=1);

// اضبط جذر المشروع مرة واحدة واعتمد عليه بكل الاستدعاءات
$ROOT = dirname(__DIR__, 2); // من api/projects → api → (جذر backend)
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

// اسمح بـ GET فقط
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', [], 405);
}

require_auth();
require_permission('projects', 'read');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

// معرفة إن كان المستخدم Admin (للسماح برؤية كل المشاريع)
$roleStmt = $pdo->prepare("
    SELECT r.slug AS role_slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleStmt->execute([$uid]);
$roleSlug = (string)($roleStmt->fetchColumn() ?: 'user');
$isAdmin  = ($roleSlug === 'admin');

// فلاتر واستعلامات
$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$mine     = isset($_GET['mine']) ? (int)$_GET['mine'] : 0;      // 1: فقط مشاريعي
$ownerId  = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$dueFrom  = isset($_GET['due_from']) ? (string)$_GET['due_from'] : '';
$dueTo    = isset($_GET['due_to'])   ? (string)$_GET['due_to']   : '';
$orderBy  = 'p.created_at';  // ترتيب آمن
$orderDir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

// ترقيم
$pg = paginate_from_query($_GET, 20, 100);
$limit  = $pg['limit'];
$offset = $pg['offset'];

// بناء الشرط
$where  = [];
$params = [];

// بحث نصي على الاسم والوصف
if ($q !== '') {
    $like = '%' . sql_like_escape($q) . '%';
    $where[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.description LIKE ? ESCAPE '\\\\')";
    $params[] = $like;
    $params[] = $like;
}

// فلتر مالك المشروع
if ($ownerId > 0) {
    $where[]  = "p.owner_id = ?";
    $params[] = $ownerId;
}

// فلتر تواريخ الاستحقاق
$validDate = static function (string $s): bool {
    if ($s === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $s);
    return $dt && $dt->format('Y-m-d') === $s;
};

if ($dueFrom !== '' && !$validDate($dueFrom)) {
    json_err('VALIDATION_ERROR', 'Invalid due_from (expected YYYY-MM-DD)', ['field' => 'due_from'], 422);
}
if ($dueTo !== '' && !$validDate($dueTo)) {
    json_err('VALIDATION_ERROR', 'Invalid due_to (expected YYYY-MM-DD)', ['field' => 'due_to'], 422);
}
if ($dueFrom !== '' && $dueTo !== '') {
    $where[]  = "p.due_date BETWEEN ? AND ?";
    $params[] = $dueFrom;
    $params[] = $dueTo;
} elseif ($dueFrom !== '') {
    $where[]  = "p.due_date >= ?";
    $params[] = $dueFrom;
} elseif ($dueTo !== '') {
    $where[]  = "p.due_date <= ?";
    $params[] = $dueTo;
}

// نطاق الرؤية
if (!$isAdmin) {
    $where[]  = "(p.owner_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))";
    $params[] = $uid;
    $params[] = $uid;
} else if ($mine === 1) {
    $where[]  = "(p.owner_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))";
    $params[] = $uid;
    $params[] = $uid;
}

// دمج WHERE
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// إجمالي النتائج
$countSql = "SELECT COUNT(*) FROM projects p $whereSql";
$countSt  = $pdo->prepare($countSql);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

// الاستعلام الرئيسي
$sql = "
    SELECT
        p.id,
        p.name,
        p.description,
        p.due_date,
        p.created_at,
        p.owner_id,
        u.name AS owner
    FROM projects p
    JOIN users u ON u.id = p.owner_id
    $whereSql
    ORDER BY $orderBy $orderDir
    LIMIT ? OFFSET ?
";
$st = $pdo->prepare($sql);
$st->execute(array_merge($params, [$limit, $offset]));
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// إخراج
json_ok($rows, [
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'admin'  => $isAdmin,
]);
