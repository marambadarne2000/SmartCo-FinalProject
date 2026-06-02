<?php
// טעינת ספריות חיוניות:
// ניהול סשן למשתמשים, חיבור לבסיס הנתונים, ופונקציות עזר כלליות
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php'; // כאן נמצאות פונקציות JSON ועזרים אחרים

// בדיקה שהמשתמש מחובר, אחרת החזרת שגיאה
require_auth();

// קבלת מזהה המשתמש המחובר מתוך הסשן
$uid = $_SESSION['user_id'];

// יצירת חיבור לבסיס הנתונים
$pdo = db();

// שאילתה להבאת כל ההתראות של המשתמש
// מחברת את טבלת ההתראות עם טבלת הקשר למשתמש
// מסדרת את ההתראות מהחדשות לישנות
$sql = "SELECT n.id, n.title, n.message, n.link, n.created_at, un.is_read
        FROM user_notifications un
        JOIN notifications n ON n.id = un.notification_id
        WHERE un.user_id = ?
        ORDER BY n.created_at DESC";

// הכנה לביצוע השאילתה
$st = $pdo->prepare($sql);

// ביצוע השאילתה עם מזהה המשתמש המחובר
$st->execute([$uid]);

// אחסון התוצאה במערך אסוציאטיבי
$data = $st->fetchAll(PDO::FETCH_ASSOC);

// החזרת הנתונים בפורמט JSON לממשק המשתמש
json_ok($data);
