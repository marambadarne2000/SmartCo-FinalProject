<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    // إعدادات الاتصال - من الممكن أخذها من متغيرات بيئة
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'scm'; // عدّل الاسم إذا لازم
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '1234';

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // أمان إضافي
        ]);
    } catch (PDOException $e) {
        error_log("DB CONNECTION ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Database connection failed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $pdo;
}
