<?php
require_once '../_bootstrap.php';        // يتضمن start session + pdo + require_auth + json helpers

// Fallback: define has_permission if it's not provided by bootstrap or other includes
if (!function_exists('has_permission')) {
function has_permission($pdo, $user_id, $module, $action) {
    // Get the user's role_id
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['role_id'])) {
        return false;
    }
    $role_id = (int)$u['role_id'];

    // Check if role has the permission for the given module/action
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = :rid AND p.module = :module AND p.action = :action
    ");
    $stmt->execute([
        ':rid' => $role_id,
        ':module' => $module,
        ':action' => $action,
    ]);
    return ((int)$stmt->fetchColumn()) > 0;
}
}

require_auth();

$user = current_user(); // يعيد user مع role_id

if (!has_permission($pdo, $user['id'], 'permissions', 'manage')) {
  json_err(403, 'forbidden');
}

$roles = $pdo->query("SELECT id, name, slug FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$perms = $pdo->query("SELECT id, module, action FROM permissions ORDER BY module, action")->fetchAll(PDO::FETCH_ASSOC);

$rp = [];
$stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $rp[$row['role_id']][] = (int)$row['permission_id']; }

json_ok([ 'roles'=>$roles, 'permissions'=>$perms, 'rolePermissions'=>$rp ]);
