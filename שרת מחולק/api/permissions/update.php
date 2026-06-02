<?php
require_once '../_bootstrap.php';
require_auth();
$user = current_user();

if (!has_permission($pdo, $user['id'], 'permissions', 'manage')) { json_err(403,'forbidden'); }

$body = json_input(); // { role_id, permission_ids: number[] }
$roleId = (int)($body['role_id'] ?? 0);
$ids = $body['permission_ids'] ?? [];

$pdo->beginTransaction();
$del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?");
$del->execute([$roleId]);

if (!empty($ids)) {
  $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)");
  foreach ($ids as $pid) { $ins->execute([$roleId, (int)$pid]); }
}
$pdo->commit();
json_ok([ 'updated'=>true ]);
