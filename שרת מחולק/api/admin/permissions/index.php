<?php
// /api/admin/permissions/index.php
declare(strict_types=1);

// تحديد جذر المشروع (backend)
$ROOT = dirname(__DIR__, 3); // من admin/permissions → admin → api → backend root

// استدعاء المكتبات الأساسية
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/utils.php';

// السماح فقط للأدمن
function require_admin(PDO $pdo): void {
    require_auth();
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare("
        SELECT r.slug
        FROM users u 
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? 
        LIMIT 1
    ");
    $st->execute([$uid]);
    $slug = (string)$st->fetchColumn();
    if ($slug !== 'admin') {
        json_err('FORBIDDEN', 'Admins only', [], 403);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();

if ($method === 'GET') {
    require_admin($pdo);

    // جلب الأدوار
    $roles = $pdo->query("
        SELECT id, name, slug, description 
        FROM roles 
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // جلب كل الصلاحيات
    $perms = $pdo->query("
        SELECT id, module, action, description 
        FROM permissions 
        ORDER BY module, action
    ")->fetchAll(PDO::FETCH_ASSOC);

    // جلب صلاحيات كل دور
    $rp = $pdo->query("
        SELECT role_id, permission_id 
        FROM role_permissions
    ")->fetchAll(PDO::FETCH_ASSOC);

    $byRole = [];
    foreach ($rp as $row) {
        $rid = (int)$row['role_id'];
        $pid = (int)$row['permission_id'];
        $byRole[$rid][] = $pid;
    }

    // دمج الصلاحيات مع الأدوار
    foreach ($roles as &$r) {
        $rid = (int)$r['id'];
        $r['permissions'] = $byRole[$rid] ?? [];
    }

    json_ok([
        'roles'       => $roles,
        'permissions' => $perms,
    ]);
    exit;
}

if ($method === 'POST') {
    require_admin($pdo);
    require_csrf();

    $in = json_input();
    $roleId = (int)($in['role_id'] ?? 0);
    $permIds = $in['permissions'] ?? [];

    if ($roleId <= 0 || !is_array($permIds)) {
        json_err('VALIDATION_ERROR', 'role_id and permissions[] are required', [], 422);
    }

    // تأكد أن الدور موجود
    $st = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = ?");
    $st->execute([$roleId]);
    if ((int)$st->fetchColumn() === 0) {
        json_err('NOT_FOUND', 'Role not found', [], 404);
    }

    // فلترة الـ permissions
    $permIds = array_values(array_unique(array_map('intval', $permIds)));
    if ($permIds) {
        $inQuery = implode(',', array_fill(0, count($permIds), '?'));
        $check = $pdo->prepare("SELECT id FROM permissions WHERE id IN ($inQuery)");
        $check->execute($permIds);
        $valid = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN));
    } else {
        $valid = [];
    }

    // تحديث الصلاحيات
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $del->execute([$roleId]);

        if ($valid) {
            $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($valid as $pid) {
                $ins->execute([$roleId, $pid]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_err('SERVER_ERROR', $e->getMessage(), [], 500);
    }

    json_ok([
        'updated'  => true,
        'role_id'  => $roleId,
        'count'    => count($valid)
    ]);
    exit;
}

json_err('METHOD_NOT_ALLOWED', 'Only GET/POST are allowed', [], 405);
