<?php
function notify_users(PDO $pdo, array $userIds, string $title, string $message, ?string $link = null): void {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("INSERT INTO notifications (title, message, link) VALUES (?, ?, ?)");
        $st->execute([$title, $message, $link]);
        $notifId = $pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $ins->execute([$uid, $notifId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
