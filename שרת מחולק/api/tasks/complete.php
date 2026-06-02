<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/json.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', null, 405);
}

require_auth();
require_csrf();
// لا نطلب tasks:update هنا. نتحقق بالمنطق أدناه.

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);

$in = json_input();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid task id', ['field' => 'id'], 422);
}

// جلب الحالة الحالية + المُكلّف + المشروع
$st = $pdo->prepare("
    SELECT t.status, t.assignee_id, t.project_id, p.owner_id
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE t.id = ?
    LIMIT 1
");
$st->execute([$id]);
$cur = $st->fetch(PDO::FETCH_ASSOC);
if (!$cur) {
    json_err('NOT_FOUND', 'Task not found', ['id' => $id], 404);
}

$oldStatus    = (string)$cur['status'];
$assigneeId   = $cur['assignee_id'] !== null ? (int)$cur['assignee_id'] : null;
$projectId    = (int)$cur['project_id'];
$projectOwner = (int)$cur['owner_id'];

// تحقق هل المستخدم أدمن؟
$roleStmt = $pdo->prepare("
    SELECT r.slug
    FROM users u JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? LIMIT 1
");
$roleStmt->execute([$uid]);
$isAdmin = ((string)$roleStmt->fetchColumn() === 'admin');

// السماح فقط لو المستخدم هو المكلّف الحالي أو أدمن
if (!$isAdmin && ($assigneeId === null || $assigneeId !== $uid)) {
    json_err('FORBIDDEN', 'You can only complete tasks assigned to you', ['task_id' => $id], 403);
}

/* ====== حقول الوقت الاختيارية ======
   - hours: عدد الساعات (0..24 لكل إدخال)
   - note: ملاحظة قصيرة (≤ 255)
   - work_date: YYYY-MM-DD (افتراضي اليوم UTC)
   - hours_user_id: لمن تُسجَّل الساعات (افتراضي $uid). يتطلب Active + وصول للمشروع.
*/
$hoursRaw      = $in['hours'] ?? null;
$noteRaw       = isset($in['note']) ? trim((string)$in['note']) : '';
$workDateInput = isset($in['work_date']) && $in['work_date'] !== '' ? (string)$in['work_date'] : null;
$hoursUserId   = isset($in['hours_user_id']) ? (int)$in['hours_user_id'] : $uid;

// إن أُرسلت ساعات، تحقق صحتها
$shouldLogTime = ($hoursRaw !== null);
if ($shouldLogTime) {
    if (!is_numeric($hoursRaw)) {
        json_err('VALIDATION_ERROR', 'hours must be a number', ['field' => 'hours'], 422);
    }
    $hours = (float)$hoursRaw;
    if ($hours <= 0 || $hours > 24) {
        json_err('VALIDATION_ERROR', 'hours must be within (0, 24]', ['field' => 'hours'], 422);
    }

    // work_date
    $workDate = $workDateInput ?? (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
    $dtTL = DateTime::createFromFormat('Y-m-d', $workDate);
    if (!$dtTL || $dtTL->format('Y-m-d') !== $workDate) {
        json_err('VALIDATION_ERROR', 'Invalid work_date (YYYY-MM-DD)', ['field' => 'work_date'], 422);
    }

    // note ≤ 255
    if ($noteRaw !== '' && mb_strlen($noteRaw) > 255) {
        $noteRaw = mb_substr($noteRaw, 0, 255);
    }

    // المستخدم الهدف موجود وActive
    $chkUser = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $chkUser->execute([$hoursUserId]);
    $uStatus = $chkUser->fetchColumn();
    if ($uStatus === false) {
        json_err('VALIDATION_ERROR', 'hours_user_id not found', ['field' => 'hours_user_id'], 422);
    }
    if ($uStatus !== 'active') {
        json_err('VALIDATION_ERROR', 'hours_user_id must be active', ['field' => 'hours_user_id'], 422);
    }

    // لديه وصول للمشروع (مالك أو عضو)
    $stVis = $pdo->prepare("
        SELECT 1
        FROM projects p
        WHERE p.id = ?
          AND (p.owner_id = ? OR EXISTS (
            SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?
          ))
        LIMIT 1
    ");
    $stVis->execute([$projectId, $hoursUserId, $hoursUserId]);
    if (!$stVis->fetchColumn()) {
        json_err('FORBIDDEN', 'hours_user_id has no access to this project', ['field' => 'hours_user_id'], 403);
    }
}

/* ====== منطق الإكمال + تسجيل الوقت بذريّة واحدة ====== */
try {
    $pdo->beginTransaction();

    $updatedStatus = false;

    // لو ليست منتهية، حدّثها إلى done
    if ($oldStatus !== 'done') {
        $upd = $pdo->prepare("UPDATE tasks SET status = 'done' WHERE id = ?");
        $upd->execute([$id]);
        $updatedStatus = true;
    }

    $timeEntryId = null;

    if ($shouldLogTime) {
        // نحاول تحديث إدخال موجود لنفس (user_id, task_id, work_date) بزيادة الساعات
        $updTE = $pdo->prepare("
            UPDATE time_entries
            SET hours = hours + ?,
                note  = CASE WHEN ? IS NULL OR ? = '' THEN note ELSE ? END
            WHERE user_id = ? AND task_id = ? AND work_date = ?
        ");
        $noteParam = ($noteRaw !== '' ? $noteRaw : null);
        $updTE->execute([
            $hours,
            $noteParam, $noteParam, $noteParam,
            $hoursUserId, $id, $workDate
        ]);

        if ($updTE->rowCount() === 0) {
            // لا يوجد سجل بنفس المفتاح المنطقي → أنشئ سجلًا جديدًا
            $insTE = $pdo->prepare("
                INSERT INTO time_entries (user_id, project_id, task_id, work_date, hours, note)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insTE->execute([
                $hoursUserId,
                $projectId,
                $id,
                $workDate,
                $hours,
                $noteParam
            ]);
            $timeEntryId = (int)$pdo->lastInsertId();
        } else {
            // اجلب id للسجل المحدث (اختياري)
            $selTE = $pdo->prepare("
                SELECT id FROM time_entries
                WHERE user_id = ? AND task_id = ? AND work_date = ?
                ORDER BY id DESC LIMIT 1
            ");
            $selTE->execute([$hoursUserId, $id, $workDate]);
            $timeEntryId = (int)($selTE->fetchColumn() ?: 0);
        }
    }

    $pdo->commit();

    // الاستجابة:
    // - لو كانت منتهية سابقًا ولم ترسل ساعات → message: Already done
    // - غير ذلك نرجع updated=true/false + time_entry_id إن وُجد
    if ($oldStatus === 'done' && !$shouldLogTime) {
        json_ok([
            'updated'       => false,
            'id'            => $id,
            'message'       => 'Already done'
        ]);
    } else {
        json_ok([
            'updated'       => $updatedStatus,
            'id'            => $id,
            'time_logged'   => $shouldLogTime ? [
                'time_entry_id' => $timeEntryId,
                'user_id'       => $shouldLogTime ? $hoursUserId : null,
                'work_date'     => $shouldLogTime ? $workDate : null,
                'hours'         => $shouldLogTime ? $hours : null,
                'note'          => $shouldLogTime ? ($noteRaw !== '' ? $noteRaw : null) : null
            ] : null
        ]);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TASK_COMPLETE_ERROR: ' . $e->getMessage());
    json_err('DB_ERROR', 'Failed to complete task / log time', null, 500);
}
