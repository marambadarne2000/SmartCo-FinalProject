<?php
// /api/csrf.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';

header('Content-Type: application/json; charset=utf-8');

// اسمح بـ GET فقط
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Only GET is allowed']
    ]);
    exit;
}

// أنشئ/أعد نفس التوكن المخزّن بالج session
$token = csrf_token();

echo json_encode([
    'ok'   => true,
    'data' => ['csrf' => $token]
]);
