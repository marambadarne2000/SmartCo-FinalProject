<?php
declare(strict_types=1);

// טעינת ספריות לניהול סשן, בסיס נתונים ופונקציות עזר
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';

// בדיקה שהבקשה היא מסוג GET בלבד
// מונע שימוש בשיטות אחרות כמו POST או PUT
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', 'Only GET is allowed', [], 405);
}

// בדיקה שהמשתמש מחובר
// מוודא שיש מזהה משתמש בסשן
require_auth();
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    json_err('UNAUTHORIZED', 'Not logged in', [], 401);
}

// התחברות לבסיס הנתונים
$pdo = db();

// שליפת פרטי המשתמש והסוג שלו מהטבלה
// כולל תפקיד ומצב המשתמש
$st = $pdo->prepare("
    SELECT u.role_id, u.status, r.slug AS role_slug, r.name AS role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$st->execute([$uid]);
$row = $st->fetch();

// בדיקה שהמשתמש אכן קיים
if (!$row) {
    json_err('NOT_FOUND', 'User not found', [], 404);
}

// בדיקה שהמשתמש פעיל
// מונע גישה למשתמשים לא פעילים
if (($row['status'] ?? 'inactive') !== 'active') {
    json_err('FORBIDDEN', 'User is not active', [], 403);
}

// שמירת פרטי התפקיד במשתנים לשימוש מאוחר
$roleId   = (int)$row['role_id'];
$roleSlug = (string)$row['role_slug'];
$roleName = (string)$row['role_name'];

// שליפת הרשאות המשתמש מהטבלאות role_permissions ו-permissions
// מספק רשימת פעולות שהמשתמש מורשה לבצע לפי תפקידו
$ps = $pdo->prepare("
    SELECT LOWER(p.module) AS module, LOWER(p.action) AS action
    FROM role_permissions rp
    JOIN permissions p ON p.id = rp.permission_id
    WHERE rp.role_id = ?
    ORDER BY p.module, p.action
");
$ps->execute([$roleId]);
$perms = $ps->fetchAll();

// ארגון הרשאות לרשימה שטוחה ולמיפוי לפי מודול
$list = [];
$byModule = [];
foreach ($perms as $p) {
    $list[] = ['module' => $p['module'], 'action' => $p['action']];
    $byModule[$p['module']][] = $p['action'];
}

// אופציונלי: שמירת הרשאות בסשן לשימוש מאוחר
$_SESSION['permissions'] = $list;

// החזרת תגובה עם פרטי התפקיד והרשאות
// list – רשימה שטוחה של כל ההרשאות
// byModule – מיפוי מודול לפעולות שלו
json_ok([
    'role' => [
        'id'   => $roleId,
        'slug' => $roleSlug,
        'name' => $roleName,
    ],
    'permissions' => $list,
    'byModule'    => $byModule,
]);
