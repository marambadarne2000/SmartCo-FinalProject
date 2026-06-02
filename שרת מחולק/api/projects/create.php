<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__, 2); // من api/projects → api → (جذر backend)
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';
require_once $ROOT . '/lib/authz_db.php';

// يجب تسجيل الدخول + CSRF (لأنها عملية كتابة)
require_auth();
require_csrf();

// التحقق من صلاحية إنشاء المشاريع
require_permission('projects', 'create');

$u  = current_user();
$in = json_input();

$name = trim((string)($in['name'] ?? ''));
$desc = trim((string)($in['description'] ?? ''));
$due  = isset($in['due_date']) && $in['due_date'] !== '' ? (string)$in['due_date'] : null;

// تحقق الاسم: 3..200 حروف
if (mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 200) {
    json_err('VALIDATION_ERROR', 'Project name must be between 3 and 200 characters', ['field' => 'name'], 422);
}

// اختياري: تحديد طول للوصف (TEXT لكن نضع سقفاً عملياً)
if ($desc !== '' && mb_strlen($desc, 'UTF-8') > 5000) {
    json_err('VALIDATION_ERROR', 'Description is too long', ['field' => 'description', 'max' => 5000], 422);
}

// تحقق التاريخ (YYYY-MM-DD)
if ($due !== null) {
    $dt = DateTime::createFromFormat('Y-m-d', $due);
    $ok = $dt && $dt->format('Y-m-d') === $due;
    if (!$ok) {
        json_err('VALIDATION_ERROR', 'Invalid due_date format, expected YYYY-MM-DD', ['field' => 'due_date'], 422);
    }
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // إدراج المشروع
    $st = $pdo->prepare("
        INSERT INTO projects (name, description, owner_id, due_date)
        VALUES (?, ?, ?, ?)
    ");
    $st->execute([$name, $desc !== '' ? $desc : null, (int)$u['id'], $due]);

    $projectId = (int)$pdo->lastInsertId();

    // إضافة صاحب المشروع كعضو (يمكن لاحقًا تعيين role داخل المشروع)
    $st2 = $pdo->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
    $st2->execute([$projectId, (int)$u['id']]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('PROJECT_CREATE_ERROR: ' . $e->getMessage());
    json_err('SERVER_ERROR', 'Could not create project', [], 500);
}

// إعادة الكيان المنشأ (يمكنك توسيعها إن أردت)
json_ok([
    'id'          => $projectId,
    'name'        => $name,
    'description' => $desc !== '' ? $desc : null,
    'owner_id'    => (int)$u['id'],
    'due_date'    => $due,
]);
