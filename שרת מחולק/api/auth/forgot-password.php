<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', [], 405);
}

require_csrf();

/**
 * Keep the reset flow isolated from the rest of the app:
 * - create the password_resets table if it does not exist yet
 * - always return a generic success response to avoid email enumeration
 */
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

function generic_success(): void {
    json_ok([
        'message' => 'If the email exists, a reset link has been sent.',
    ]);
}

try {
    $pdo = db();
    ensure_password_resets_table($pdo);

    $in = json_input();
    $email = mb_strtolower(trim((string)($in['email'] ?? '')), 'UTF-8');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        generic_success();
    }

    $st = $pdo->prepare("
        SELECT id, name, email, status
        FROM users
        WHERE LOWER(email) = ?
        LIMIT 1
    ");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || ($user['status'] ?? '') !== 'active') {
        generic_success();
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE user_id = ? AND used_at IS NULL
    ")->execute([(int)$user['id']]);

    $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (?, ?, ?)
    ")->execute([
        (int)$user['id'],
        $tokenHash,
        $expiresAt,
    ]);

    $pdo->commit();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/auth/forgot-password.php'))), '/');
    $resetUrl = $scheme . '://' . $host . $basePath . '/auth/reset-password.php?token=' . urlencode($token);

        json_ok([
        'message' => 'Reset link generated successfully.',
        'reset_url' => $resetUrl,
    ]);


} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_err('SERVER_ERROR', 'Failed to process reset request', [], 500);
}
