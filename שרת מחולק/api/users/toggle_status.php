<?php
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/authz_db.php';

// ✅ تحقق من الدخول والصلاحية
require_auth();
require_permission('users', 'read');

$pdo = db();

// 📌 تعريف القيم المسموحة
$allowedStatuses = ['active', 'suspended'];
$allowedSortCols = ['id', 'name', 'email', 'status', 'created_at'];
$allowedSortDir  = ['asc', 'desc'];

// 📥 قراءة الفلاتر من الـ GET
$q       = trim((string)($_GET['q'] ?? ''));
$role    = trim((string)($_GET['role'] ?? '')); 
$status  = trim((string)($_GET['status'] ?? ''));
$limit   = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset  = max(0, (int)($_GET['offset'] ?? 0));
$sortCol = in_array(strtolower($_GET['sort'] ?? ''), $allowedSortCols, true) ? strtolower($_GET['sort']) : 'id';
$sortDir = in_array(strtolower($_GET['dir'] ?? ''), $allowedSortDir, true) ? strtoupper($_GET['dir']) : 'DESC';

$where   = [];
$params  = [];

// 🔍 البحث الحر
if ($q !== '') {
    $where[] = "(u.name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

// 🛡 فلترة بالحالة
if (in_array($status, $allowedStatuses, true)) {
    $where[] = "u.status = :st";
    $params[':st'] = $status;
}

// 🛡 فلترة بالدور
if ($role !== '') {
    if (ctype_digit($role)) {
        $where[] = "u.role_id = :rid";
        $params[':rid'] = (int)$role;
    } else {
        $where[] = "r.slug = :rslug";
        $params[':rslug'] = $role;
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 📊 جلب العدد الإجمالي
$countSql = "
    SELECT COUNT(1) 
    FROM users u 
    JOIN roles r ON r.id = u.role_id 
    $whereSql
";
$cnt = $pdo->prepare($countSql);
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

// 📋 جلب البيانات
$listSql = "
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.status, 
        u.created_at,
        r.name AS role_name, 
        r.slug AS role_slug, 
        r.id   AS role_id
    FROM users u
    JOIN roles r ON r.id = u.role_id
    $whereSql
    ORDER BY u.$sortCol $sortDir
    LIMIT :limit OFFSET :offset
";

$st = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':limit', $limit, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();

json_ok([
    'items'  => $st->fetchAll(PDO::FETCH_ASSOC),
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'sort'   => $sortCol,
    'dir'    => $sortDir
]);
