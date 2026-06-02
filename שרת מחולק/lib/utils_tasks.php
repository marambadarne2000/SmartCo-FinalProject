<?php
declare(strict_types=1);

function get_user_task_limit(PDO $pdo, int $userId): int {
    // لو العمود غير موجود، يرجّع 3 افتراضيًا
    try {
        $st = $pdo->prepare("SELECT max_active_tasks FROM users WHERE id = ?");
        $st->execute([$userId]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null) return 3;
        $lim = (int)$val;
        return $lim > 0 ? $lim : 3;
    } catch (Throwable $e) {
        return 3;
    }
}

function get_user_active_task_count(PDO $pdo, int $userId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status IN ('todo','in_progress')");
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function ensure_capacity_or_fail(PDO $pdo, int $assigneeId): void {
    $limit = get_user_task_limit($pdo, $assigneeId);
    $count = get_user_active_task_count($pdo, $assigneeId);
    if ($count >= $limit) {
        json_err(
            'TASK_LIMIT_REACHED',
            "User has reached the active task limit ($limit).",
            ['limit' => $limit, 'active' => $count],
            409
        );
    }
}
