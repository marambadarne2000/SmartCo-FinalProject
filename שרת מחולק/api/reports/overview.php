<?php
// /api/reports/overview.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';
require_once __DIR__ . '/../../lib/authz_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('METHOD_NOT_ALLOWED', '405');
}

require_auth();
require_permission('tasks', 'read');

$pdo = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

/** Helper: نفّذ COUNT(*) وأعد رقمًا */
$execCount = function (PDO $pdo, string $sql, array $params): int {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
};

try {
    // هل المستخدم أدمن؟
    $roleStmt = $pdo->prepare("
        SELECT r.slug
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = :uid
        LIMIT 1
    ");
    $roleStmt->execute([':uid' => $uid]);
    $isAdmin = ($roleStmt->fetchColumn() === 'admin');

    // ----------------------------
    // نطاق الرؤية (عالألياس p)
    // ----------------------------
    $scopeClausesP = [];
    $scopeParamsP  = [];

   if (!$isAdmin) {
    $scopeClausesP[] = "(p.owner_id = :uid_owner OR EXISTS (
        SELECT 1
        FROM project_members pm
        WHERE pm.project_id = p.id
          AND pm.user_id   = :uid_member
    ))";
    $scopeParamsP[':uid_owner']  = $uid;
    $scopeParamsP[':uid_member'] = $uid;
}


    // ----------------------------
    // فلتر المشروع على المهام (اختياري)
    // ----------------------------
    $taskClauses = [];
    $taskParams  = [];
    if ($projectId > 0) {
        $taskClauses[] = "t.project_id = :pid";
        $taskParams[':pid'] = $projectId;
    }

    // ----------------------------
    // 1) إجمالي المشاريع ضمن النطاق
    // ----------------------------
    $projectsWhere = ['1=1'];
    
    if ($scopeClausesP) {
        $projectsWhere[] = implode(' AND ', $scopeClausesP);
    }
    $sqlProjects = "SELECT COUNT(*) FROM projects p WHERE " . implode(' AND ', $projectsWhere);
    $projects = $execCount($pdo, $sqlProjects, $scopeParamsP);

    // ----------------------------
    // قاعدة JOIN/WHERE للمهام
    // ----------------------------
    $tasksWhereParts = ['1=1'];
    if (!$isAdmin) {
    $tasksWhereParts[] = "(p.owner_id = :uid_owner OR EXISTS (
        SELECT 1
        FROM project_members pm
        WHERE pm.project_id = p.id
          AND pm.user_id   = :uid_member
    ) OR t.assignee_id = :uid_assignee)";
    $scopeParamsP[':uid_assignee'] = $uid;
}

    if ($taskClauses)  $tasksWhereParts[] = implode(' AND ', $taskClauses);

    $tasksBaseFrom = "
        FROM tasks t
        JOIN projects p ON p.id = t.project_id
        WHERE " . implode(' AND ', $tasksWhereParts);

    // دمج بارامترات المهام + النطاق (لاحظ أسماء مختلفة فلا تعارض)
    $baseParams = array_merge($taskParams, $scopeParamsP);

    // 2) إجمالي المهام
    $totalTasks = $execCount($pdo, "SELECT COUNT(*) $tasksBaseFrom", $baseParams);

    // 3) الحالات
    $done = $execCount(
        $pdo,
        "SELECT COUNT(*) $tasksBaseFrom AND t.status = :status_done",
        $baseParams + [':status_done' => 'done']
    );

    $inProgress = $execCount(
        $pdo,
        "SELECT COUNT(*) $tasksBaseFrom AND t.status = :status_in",
        $baseParams + [':status_in' => 'in_progress']
    );

    $todo = $execCount(
        $pdo,
        "SELECT COUNT(*) $tasksBaseFrom AND t.status = :status_todo",
        $baseParams + [':status_todo' => 'todo']
    );

    // 4) المتأخرة
    $today = (new DateTime('today'))->format('Y-m-d');
    $overdue = $execCount(
        $pdo,
        "SELECT COUNT(*) $tasksBaseFrom
         AND t.due_date IS NOT NULL
         AND t.due_date < :today
         AND t.status <> 'done'",
        $baseParams + [':today' => $today]
    );

    json_ok([
        'projects'     => $projects,
        'tasks'        => $totalTasks,
        'done'         => $done,
        'in_progress'  => $inProgress,
        'todo'         => $todo,
        'overdue'      => $overdue,
    ]);
} catch (Throwable $e) {
    json_err('SERVER_ERROR', $e->getMessage(), 500);
}
