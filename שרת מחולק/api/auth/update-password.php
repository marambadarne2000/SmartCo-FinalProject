<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Only POST is allowed';
    exit;
}

function ensure_password_resets_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_resets_user_id (user_id),
            INDEX idx_password_resets_expires_at (expires_at),
            CONSTRAINT fk_password_resets_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function html_response(string $title, string $message, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="font-family:Arial,sans-serif;padding:32px;max-width:620px;margin:auto">'
        . '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</body></html>';
    exit;
}

try {
    $pdo = db();
    ensure_password_resets_table($pdo);

    $token = trim((string)($_POST['token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($token === '' || mb_strlen($password, 'UTF-8') < 8) {
        html_response('Reset Failed', 'The token is invalid or the new password is too short.', 422);
    }

    $st = $pdo->query("
        SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at, u.status
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.used_at IS NULL
        ORDER BY pr.id DESC
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $match = null;
    foreach ($rows as $row) {
        if (($row['status'] ?? '') !== 'active') {
            continue;
        }
        if (!empty($row['used_at'])) {
            continue;
        }
        if (strtotime((string)$row['expires_at']) < time()) {
            continue;
        }
        if (password_verify($token, (string)$row['token_hash'])) {
            $match = $row;
            break;
        }
    }

    if (!$match) {
        html_response('Reset Failed', 'The reset link is invalid or has expired.', 400);
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE users
        SET password_hash = ?
        WHERE id = ?
        LIMIT 1
    ")->execute([
        $newHash,
        (int)$match['user_id'],
    ]);

    $pdo->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE id = ?
        LIMIT 1
    ")->execute([
        (int)$match['id'],
    ]);

    $pdo->commit();

    html_response('Password Updated', 'Your password has been updated successfully. You can now log in with the new password.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    html_response('Server Error', 'Failed to update the password. Please try again later.', 500);
}
