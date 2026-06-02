<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/utils.php';
require_once __DIR__ . '/../lib/authz_db.php';

// اسمح بـ POST أو DELETE فقط
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST', 'DELETE'], true)) {
    json_err('METHOD_NOT_ALLOWED', 'Only POST or DELETE is allowed', [], 405);
}

// يجب تسجيل الدخول + CSRF (عملية كتابة)
require_auth();
require_csrf();

// صلاحية النظام (RBAC)
require_permission('projects', 'delete');

// إدخال
$in = json_input();
// في DELETE قد يأتي id كـ query string أيضاً
$id = isset($in['id']) ? (int)$in['id'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_err('VALIDATION_ERROR', 'Invalid project ID', ['field' => 'id'], 422);
}

$pdo = db();

// جلب معلومات المشروع للتحقق من الملكية
$st = $pdo->prepare("SELECT id, owner_id FROM projects WHERE id = ? LIMIT 1");
$st->execute([$id]);
$project = $st->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    json_err('NOT_FOUND', 'Project not found', [], 404);
}

// جلب دور المستخدم الحالي
$uid = (int)($_SESSION['user_id'] ?? 0);
$roleStmt = $pdo->prepare("
    SELECT r.slug AS role_slug
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$roleStmt->execute([$uid]);
$roleSlug = (string)($roleStmt->fetchColumn() ?: '');

// سياسة التفويض الإضافية على مستوى الكيان:
// - admin: مسموح دائمًا
// - غير admin: يجب أن يكون مالك المشروع
if ($roleSlug !== 'admin' && (int)$project['owner_id'] !== $uid) {
    json_err('FORBIDDEN', 'Only owner (or admin) can delete this project', [
        'project_id' => (int)$project['id']
    ], 403);
}

// الحذف (ON DELETE CASCADE سيهتم بالعلاقات إن وُجد)
$del = $pdo->prepare("DELETE FROM projects WHERE id = ? LIMIT 1");
$del->execute([$id]);

// لو حدث سباق وتغيّر الصف:
if ($del->rowCount() < 1) {
    json_err('CONFLICT', 'Project could not be deleted (possibly already deleted)', [], 409);
}

json_ok([
    'deleted'    => true,
    'project_id' => (int)$id
]);
