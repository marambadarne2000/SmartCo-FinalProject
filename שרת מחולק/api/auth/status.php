<?php
// קובץ זה מחזיר את מצב המשתמש הנוכחי ואת הטוקן לאבטחה

declare(strict_types=1);

// טעינת ספריות לניהול סשן ובסיס נתונים
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';

// החזרת כותרת שמציינת שהתגובה בפורמט JSON
header('Content-Type: application/json; charset=utf-8');

// התחברות לבסיס הנתונים וקבלת מזהה המשתמש הנוכחי מהסשן
$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

// יצירת טוקן אבטחה
$csrf = csrf_token();

// אם המשתמש מחובר
if ($uid > 0) {
    // שליפת פרטי המשתמש כולל תפקידו מהטבלאות users ו-roles
    $st = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.status, u.created_at, u.role_id,
               r.slug AS role_slug, r.name AS role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    // בדיקה שהמשתמש פעיל
    if ($u && ($u['status'] ?? 'inactive') === 'active') {
        // החזרת פרטי המשתמש וטוקן האבטחה
        echo json_encode([
            'ok' => true,
            'data' => [
                'user' => [
                    'id'         => (int)$u['id'],
                    'name'       => (string)$u['name'],
                    'email'      => (string)$u['email'],
                    'status'     => (string)$u['status'],
                    'created_at' => (string)$u['created_at'],
                    'role'       => [
                        'id'   => (int)$u['role_id'],
                        'slug' => (string)$u['role_slug'],
                        'name' => (string)$u['role_name'],
                    ],
                ],
                'csrf' => $csrf
            ]
        ]);
        exit;
    }
}

// אם המשתמש לא מחובר או לא פעיל
// החזרת JSON עם user = null וטוקן האבטחה
echo json_encode([
    'ok'   => true,
    'data' => [ 'user' => null, 'csrf' => $csrf ]
]);
