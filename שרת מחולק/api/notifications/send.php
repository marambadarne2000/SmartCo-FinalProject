<?php
declare(strict_types=1);

// טעינת ספריות חיוניות:
// ניהול סשן, חיבור למסד הנתונים, פונקציות עזר לקריאה/כתיבה ב-JSON
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';

// בדיקה שהמשתמש מחובר
require_auth();

// חיבור למסד הנתונים וקבלת מזהה המשתמש המחובר
$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

// בדיקה האם המשתמש הוא מנהל או מנהל מערכת
$stRole = $pdo->prepare("
  SELECT r.slug
  FROM users u
  LEFT JOIN roles r ON r.id = u.role_id
  WHERE u.id = ? LIMIT 1
");
$stRole->execute([$uid]);
$senderRole = strtolower((string)$stRole->fetchColumn() ?: '');

// גיבוי אם אין טבלת roles והעמוד הוא role בעמוד users
if ($senderRole === '') {
  $stRole2 = $pdo->prepare("SELECT LOWER(role) FROM users WHERE id = ? LIMIT 1");
  $stRole2->execute([$uid]);
  $senderRole = strtolower((string)$stRole2->fetchColumn() ?: '');
}

// אם המשתמש אינו מנהל או מנהל מערכת, החזרת שגיאה
if (!in_array($senderRole, ['admin', 'manager'], true)) {
  json_err('ACCESS_DENIED', 'You do not have permission', [], 403);
}

// קריאת הנתונים מהבקשה (JSON או POST)
$in = json_input(); 

$title      = trim((string)($in['title']   ?? ''));
$message    = trim((string)($in['message'] ?? ''));
$link       = trim((string)($in['link']    ?? ''));
$roleTarget = strtolower((string)($in['role'] ?? ''));
if ($roleTarget === 'all') { $roleTarget = 'both'; }

// בדיקה שכל השדות הדרושים מלאים
if ($title === '' || $message === '' || $roleTarget === '') {
  json_err('MISSING_FIELDS', 'Please fill in all required fields', [], 422);
}

// בדיקה שהערך role חוקי (admin, manager או both)
if (!in_array($roleTarget, ['admin', 'manager', 'both'], true)) {
  json_err('VALIDATION_ERROR', "Invalid 'role' value. Must be 'admin', 'manager' or 'both'.", [], 422);
}

// התחלת טרנזקציה
$pdo->beginTransaction();
try {
  // יצירת ההודעה בטבלת notifications
  $insN = $pdo->prepare("INSERT INTO notifications (title, message, link) VALUES (?, ?, ?)");
  $insN->execute([$title, $message, ($link !== '' ? $link : null)]);
  $nid = (int)$pdo->lastInsertId();

  // בחירת המשתמשים היעדיים לפי role
  $where = '';
  if ($roleTarget === 'admin') {
    $where = "LOWER(r.slug) = 'admin'";
  } elseif ($roleTarget === 'manager') {
    $where = "LOWER(r.slug) = 'manager'";
  } else { // both
    $where = "1"; // הכל
  }

  $sqlUsers = "
    SELECT u.id
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE $where
  ";
  $stUsers = $pdo->query($sqlUsers);
  $userIds = $stUsers->fetchAll(PDO::FETCH_COLUMN);
  $userIds = array_map('intval', $userIds);

  // קישור ההודעה לכל המשתמשים שנבחרו
  if ($userIds) {
    $insUN = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
    foreach ($userIds as $u) {
      $insUN->execute([$u, $nid]);
    }
  }

  // סיום הטרנזקציה והחזרת מזהה ההודעה ומספר הנמען
  $pdo->commit();
  json_ok(['notification_id' => $nid, 'sent_to' => count($userIds)]);
} catch (Throwable $e) {
  // במקרה של שגיאה, ביטול הטרנזקציה והחזרת שגיאה
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_err('DB_ERROR', $e->getMessage(), [], 500);
}
