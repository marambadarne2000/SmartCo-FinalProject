<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/utils.php';

// اسمح فقط بـ POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_err('METHOD_NOT_ALLOWED', 'Only POST is allowed', [], 405);
}

// التحقق من CSRF (يمكنك إلغاءه في صفحة تسجيل الدخول إن لزم)
// ملاحظة: session.php يولّد token تلقائياً، أرسله للواجهات عبر /api/me مثلاً
require_csrf();

$in    = json_input();
$email = mb_strtolower(trim((string)($in['email'] ?? '')), 'UTF-8');
$pass  = (string)($in['password'] ?? '');

// تحقق مدخلات
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass, 'UTF-8') < 6) {
    json_err('VALIDATION_ERROR', 'Invalid email or password format', ['fields' => ['email','password']], 422);
}

/**
 * Throttling بسيط ضد التخمين:
 * - يراكم عدد الإخفاقات في الجلسة
 * - backoff أُسّي حتى حد أقصى 5 دقائق
 */
$now  = time();
$fail = $_SESSION['_login_fail'] ?? ['count' => 0, 'until' => 0];

if ($now < (int)$fail['until']) {
    $retryAfter = (int)$fail['until'] - $now;
    json_err('TOO_MANY_ATTEMPTS', 'Please try again later', ['retry_after' => $retryAfter], 429);
}

$pdo = db();



// جلب المستخدم حسب الإيميل
$st = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.password_hash, u.role_id, u.status,
           r.slug AS role_slug, r.name AS role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE LOWER(u.email) = ?
    LIMIT 1
");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

// تحقق من الحالة وكلمة المرور
$valid = false;
if ($user && ($user['status'] ?? null) === 'active') {
    // password_verify آمنة توقيتيًا
    $valid = password_verify($pass, (string)$user['password_hash']);
}

// معالجة الإخفاق
if (!$valid) {
    // زِد العدّاد وحدّد وقت الحظر التالي
    $fail['count'] = (int)($fail['count'] ?? 0) + 1;
    // backoff: 2, 4, 8, 16, 32, 60, 120, 300 (ثواني)
    $delays = [2,4,8,16,32,60,120,300];
    $delay  = $delays[min($fail['count'] - 1, count($delays) - 1)];
    $fail['until'] = $now + $delay;
    $_SESSION['_login_fail'] = $fail;

    // رسالة موحّدة لتفادي تسريب إن كان الإيميل موجودًا من عدمه
    json_err('BAD_CREDENTIALS', 'Invalid email or password', [], 401);
}

// نجاح: صفّر عدّاد الإخفاق
unset($_SESSION['_login_fail']);

// ترقية الهاش عند الحاجة (تكلفة أعلى/خوارزمية أحدث)
if (password_needs_rehash((string)$user['password_hash'], PASSWORD_BCRYPT, ['cost' => 10])) {
    try {
        $newHash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
        $upd->execute([$newHash, (int)$user['id']]);
    } catch (Throwable $e) {
        // لا نُفشل تسجيل الدخول بسبب فشل الترقية
        error_log('PASSWORD_REHASH_ERROR: ' . $e->getMessage());
    }
}

// سجّل الدخول (يُدوّر session_id ويولّد CSRF إن لزم)
login_user(
    (int)$user['id'],
    (string)$user['name'],
    (string)$user['email'],
    isset($user['role_id']) ? (int)$user['role_id'] : null,
    (string)$user['status']
);

// جلب صلاحيات الدور
$permStmt = $pdo->prepare("
    SELECT p.module, p.action
    FROM role_permissions rp
    JOIN permissions p ON p.id = rp.permission_id
    WHERE rp.role_id = ?
");
$permStmt->execute([(int)$user['role_id']]);
$permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);

// أعد CSRF token للاستخدام في الطلبات اللاحقة
$csrf = csrf_token();

json_ok([
    'id'          => (int)$user['id'],
    'name'        => (string)$user['name'],
    'email'       => (string)$user['email'],
    'role'        => (string)$user['role_slug'],
    'role_id'     => (int)$user['role_id'],
    'roleName'    => (string)$user['role_name'],
    'permissions' => $permissions,
    'csrf'        => $csrf
]);
