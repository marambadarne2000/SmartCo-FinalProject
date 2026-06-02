<?php
declare(strict_types=1);

function emit_json(array $payload, int $http = 200, array $headers = []): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) header($k . ': ' . $v);
        http_response_code($http);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * نجاح موحّد: ok=true
 */
function json_ok($data = null, array $meta = []): void {
    emit_json([
        'ok'   => true,
        'data' => $data,
        'meta' => (object)$meta,
    ], 200);
}

/**
 * خطأ موحّد: ok=false
 * التوقيع: (code, message, details = [], http = 400)
 */
function json_err(string $code, string $message = '', array $details = [], int $http = 400): void {
    if ($message === '') {
        $defaults = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
        ];
        $message = $defaults[$http] ?? 'Error';
    }

    emit_json([
        'ok'    => false,
        'error' => [
            'code'    => $code,
            'message' => $message,
            'details' => (object)$details,
        ],
    ], $http);
}
