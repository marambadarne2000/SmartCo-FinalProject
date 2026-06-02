<?php
declare(strict_types=1);

// טעינת ספריות חיוניות:
// ניהול חיבור לבסיס הנתונים, סשן למשתמשים, ופונקציות JSON
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/json.php';

try {
    // בדיקה של טוקן אבטחה כדי למנוע התקפות זיוף
    require_csrf();

    // חיבור לבסיס הנתונים וקבלת מזהה המשתמש המחובר
    $pdo = db();
    $me  = current_user_id();

    // בדיקה אם המשתמש מחובר, אחרת החזרת שגיאת הרשאה
    if (!$me) {
        json_err('UNAUTHORIZED', 'Login required', [], 401);
    }

    // קבלת פרמטרים מהבקשה עם הגבלות:
    // מספר פריטים להחזרה ומיקום התחלה להצגת עמודים
    $limit  = max(1, min(100, (int)($_GET['limit']  ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $onlyUnread = (int)($_GET['only_unread'] ?? 0) === 1 ? 1 : 0;
    $q = trim((string)($_GET['q'] ?? ''));

    // בניית שאילתה מורכבת להצגת השיחות של המשימות:
    // כוללת מידע על המשימה, ההודעה האחרונה, המשתתפים האחרים ומספר ההודעות שלא נקראו
    $sql = "
      SELECT
        tt.id              AS thread_id,
        t.id               AS task_id,
        t.title            AS task_name,
        t.title            AS task_title,

        lm.id              AS last_message_id,
        lm.type            AS last_type,
        lm.text            AS last_message_preview,
        lm.file_url        AS last_file_url,
        lm.created_at      AS last_message_at,
        u.name             AS last_sender_name,

        GROUP_CONCAT(DISTINCT CASE WHEN u2.id <> :me1 THEN u2.name END ORDER BY u2.name SEPARATOR ', ') AS others_names,

        (
          SELECT COUNT(*)
          FROM task_messages m
          LEFT JOIN task_message_reads r
            ON r.message_id = m.id AND r.user_id = :me2
          WHERE m.thread_id = tt.id
            AND m.sender_id <> :me3
            AND r.message_id IS NULL
        ) AS unread_count

      FROM task_threads tt
      JOIN task_thread_participants p
        ON p.thread_id = tt.id AND p.user_id = :me4
      JOIN tasks t
        ON t.id = tt.task_id

      JOIN (
        SELECT m1.*
        FROM task_messages m1
        JOIN (
          SELECT thread_id, MAX(id) AS max_id
          FROM task_messages
          GROUP BY thread_id
        ) mx ON mx.thread_id = m1.thread_id AND mx.max_id = m1.id
      ) lm ON lm.thread_id = tt.id
      JOIN users u ON u.id = lm.sender_id

      JOIN task_thread_participants p2 ON p2.thread_id = tt.id
      JOIN users u2 ON u2.id = p2.user_id

      WHERE 1=1
        " . ($onlyUnread ? "AND (
            SELECT COUNT(*)
            FROM task_messages m
            LEFT JOIN task_message_reads r
              ON r.message_id = m.id AND r.user_id = :me5
            WHERE m.thread_id = tt.id
              AND m.sender_id <> :me6
              AND r.message_id IS NULL
          ) > 0" : "") . "

        " . ($q !== '' ? "AND (
              CONCAT_WS(' ', t.title, lm.text, u2.name) LIKE :qq
            )" : "") . "

      GROUP BY tt.id
      ORDER BY lm.id DESC
      LIMIT :limit OFFSET :offset
    ";

    // הכנה לביצוע השאילתה
    $st = $pdo->prepare($sql);

    // חיבור פרמטרים של המשתמש המחובר
    $st->bindValue(':me1', $me, PDO::PARAM_INT);
    $st->bindValue(':me2', $me, PDO::PARAM_INT);
    $st->bindValue(':me3', $me, PDO::PARAM_INT);
    $st->bindValue(':me4', $me, PDO::PARAM_INT);
    if ($onlyUnread) {
        $st->bindValue(':me5', $me, PDO::PARAM_INT);
        $st->bindValue(':me6', $me, PDO::PARAM_INT);
    }

    // חיבור פרמטר חיפוש אם נשלח
    if ($q !== '') {
        $qq = '%' . $q . '%';
        $st->bindValue(':qq', $qq, PDO::PARAM_STR);
    }

    // חיבור מגבלות הצגה והתחלה
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);

    // ביצוע השאילתה והחזרת תוצאות
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // עיבוד משתתפים
    foreach ($rows as &$r) {
        $participants = [];
        if (!empty($r['others_names'])) {
            $names = explode(',', $r['others_names']);
            foreach ($names as $n) {
                $n = trim($n);
                if ($n !== '') {
                    $participants[] = ['id' => null, 'name' => $n];
                }
            }
        }
        $r['participants'] = $participants;
    }

    // החזרת התוצאה למשתמש בפורמט JSON
    json_ok($rows);

} catch (Throwable $e) {
    // טיפול בשגיאות על מנת להחזיר הודעת שגיאה מסודרת
    json_err('SERVER_ERROR', $e->getMessage(), [], 500);
}
