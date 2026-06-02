<?php
declare(strict_types=1);

# טעינת ספרייה לניהול סשנים כדי לעבוד עם משתנים של משתמשים מחוברים
require_once __DIR__ . '/../../lib/session.php';

# התחברות למסד הנתונים כדי לבצע שאילתות
require_once __DIR__ . '/../../lib/db.php';

# פונקציות לשליחת תגובות בפורמט JSON ולניהול שגיאות
require_once __DIR__ . '/../../lib/json.php';

# בדיקה אם המשתמש מחובר, אם לא מחזיר שגיאה ומונע גישה לקוד
require_auth();

# הגדרת כותרת שהתגובה תהיה בפורמט JSON ומקודדת ב-UTF8
header('Content-Type: application/json; charset=utf-8');

# אחזור מזהה המשתמש מהסשן כדי לדעת למי ההודעה שייכת
$uid = (int)($_SESSION['user_id'] ?? 0);

# אתחול מזהה ההודעה
$nid = 0;

# בדיקה אם גוף הבקשה מכיל JSON כדי לקרוא מזהה ההודעה משם
$ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($ct, 'application/json') !== false) {
    # קריאה של גוף הבקשה
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        # המרה למערך PHP
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            # אחזור מזהה ההודעה מתוך JSON
            $nid = (int)($data['id'] ?? $data['notification_id'] ?? 0);
        }
    }
}

# ניסיון לקרוא מזהה מהטופס אם לא נמצא בגוף JSON
if ($nid <= 0) $nid = (int)($_POST['id'] ?? $_POST['notification_id'] ?? 0);

# ניסיון לקרוא מזהה מהקישור אם לא נמצא בשום מקום
if ($nid <= 0) $nid = (int)($_GET['id'] ?? $_GET['notification_id'] ?? 0);

# אם המשתמש לא מחובר מחזיר שגיאה
if ($uid <= 0) json_err('UNAUTHENTICATED', 'You must be logged in.', [], 401);

# אם מזהה ההודעה לא קיים מחזיר שגיאה
if ($nid <= 0) json_err('INVALID_ID', 'Invalid notification ID', [], 422);

# התחברות למסד הנתונים
$pdo = db();

# קביעת מצב טיפול בשגיאות כדי לקבל חריגות במקרה של בעיות במסד
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    # בדיקה שההודעה קיימת במסד
    $chk = $pdo->prepare("SELECT 1 FROM notifications WHERE id = ? LIMIT 1");
    $chk->execute([$nid]);
    if (!$chk->fetchColumn()) {
        # אם ההודעה לא קיימת מחזיר שגיאה
        json_err('NOT_FOUND', 'Notification does not exist.', [], 404);
    }

    # בדיקה אם ההודעה כבר נקראה על ידי המשתמש
    $sel = $pdo->prepare("SELECT is_read FROM user_notifications WHERE user_id = ? AND notification_id = ? LIMIT 1");
    $sel->execute([$uid, $nid]);
    $prev = $sel->fetch(PDO::FETCH_ASSOC);

    # שמירת מצב קריאה קודם כדי להחזיר ללקוח
    $alreadyRead = $prev ? ((int)$prev['is_read'] === 1) : false;

    # ניסיון לעדכן או ליצור רשומה שמסמנת שההודעה נקראה
    try {
        $sql = "
            INSERT INTO user_notifications (user_id, notification_id, is_read, read_at)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                is_read = GREATEST(is_read, VALUES(is_read)),
                read_at = IF(VALUES(is_read)=1, NOW(), read_at)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $nid]);
    } catch (PDOException $e) {
        # טיפול אם העמודה read_at לא קיימת במסד
        if ($e->getCode() === '42S22') {
            $sql = "
                INSERT INTO user_notifications (user_id, notification_id, is_read)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    is_read = GREATEST(is_read, VALUES(is_read))
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$uid, $nid]);
        } else {
            # כל חריגה אחרת מועברת הלאה
            throw $e;
        }
    }

    # שליפה של פרטי ההודעה כולל לינק ומצב קריאה
    $selNotification = $pdo->prepare("
        SELECT n.title, n.message, n.link, un.is_read, un.read_at
        FROM notifications n
        JOIN user_notifications un ON un.notification_id = n.id
        WHERE un.user_id = ? AND n.id = ?
        LIMIT 1
    ");
    $selNotification->execute([$uid, $nid]);
    $notif = $selNotification->fetch(PDO::FETCH_ASSOC);

    # אם ההודעה לא נמצאה למשתמש מחזיר שגיאה
    if (!$notif) {
        json_err('NOT_FOUND', 'Notification not found for this user.', [], 404);
    }

    # החזרת פרטי ההודעה ללקוח
    json_ok([
        'id' => $nid,
        'title' => $notif['title'],
        'message' => $notif['message'],
        'link' => $notif['link'],          # החזרת לינק ההודעה
        'read' => true,                     # סימון שההודעה נקראה
        'already_read' => (int)$notif['is_read'] === 1 # מצב קריאה קודם
    ]);

# טיפול בשגיאות בלתי צפויות והחזרת מידע ללקוח
} catch (Throwable $e) {
    json_err('SERVER_ERROR', 'Unexpected error', ['sql_error' => $e->getMessage()], 500);
}
