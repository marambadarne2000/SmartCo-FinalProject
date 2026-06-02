<?php
declare(strict_types=1);

/**
 * authz_db.php
 * طبقة صلاحيات مبنية على قاعدة البيانات (أدوار/صلاحيات/عضويات المشاريع).
 * تعتمد على: db.php (PDO), session.php (جلسة + current_user_id/require_auth), utils.php (json_ok/json_err).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/utils.php';

/* ====================================================================
   Helpers from session.php (تجنّب إعادة التعريف)
   ==================================================================== */

/** جلب role_id للمستخدم الحالي من الـ Session (إن وُجد) */
if (!function_exists('current_role_id')) {
    function current_role_id(): ?int {
        $u = current_user();
        return $u && isset($u['role_id']) ? (int)$u['role_id'] : null;
    }
}

/** تأكيد تسجيل الدخول (بديل خفيف لـ require_auth) */
if (!function_exists('require_login')) {
    function require_login(): void {
        if (!is_logged_in()) {
            json_err('UNAUTHORIZED', 'Login required', (object)[], 401); // json_err يخرج
        }
    }
}

/* ====================================================================
   Authorization core
   ==================================================================== */

/**
 * مفتاح كاش بسيط لكل طلب لتقليل ضربات الداتابيس
 */
if (!function_exists('__perm_cache_key')) {
    function __perm_cache_key(int $uid, string $module, string $action): string {
        return $uid . '|' . strtolower($module) . '|' . strtolower($action);
    }
}

/**
 * هل يملك المستخدم الحالي الصلاحية المطلوبة؟
 * - يتحقق من أن المستخدم active.
 * - يدعم كاش داخل الطلب.
 * - يعامل المستخدم الذي دوره admin كـ superuser.
 */
if (!function_exists('has_permission_db')) {
    function has_permission_db(string $module, string $action): bool {
        $uid = current_user_id();
        if (!$uid) return false;

        static $permCache = []; // memoization per-request
        $module = strtolower($module);
        $action = strtolower($action);
        $key    = __perm_cache_key((int)$uid, $module, $action);

        if (array_key_exists($key, $permCache)) {
            return $permCache[$key];
        }

        try {
            $pdo = db();

            // استعلام موحّد: تأكد أن المستخدم active + إن كان admin اسمح، وإلا تحقق من الصلاحية
            // أسرع من عمل استعلامين منفصلين.
            // 1) جلب slug الحالة والدور
            $st = $pdo->prepare("
                SELECT u.status, r.slug AS role_slug
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE u.id = ?
                LIMIT 1
            ");
            $st->execute([$uid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $permCache[$key] = false;
            }

            if (($row['status'] ?? '') !== 'active') {
                return $permCache[$key] = false;
            }

            if (($row['role_slug'] ?? '') === 'admin') {
                return $permCache[$key] = true; // superuser
            }

            // 2) تحقق الصلاحية عبر role_permissions
            $st2 = $pdo->prepare("
                SELECT 1
                FROM users u
                JOIN role_permissions rp ON rp.role_id = u.role_id
                JOIN permissions p      ON p.id = rp.permission_id
                WHERE u.id = ? AND p.module = ? AND p.action = ?
                LIMIT 1
            ");
            $st2->execute([$uid, $module, $action]);
            $ok = (bool)$st2->fetchColumn();

            return $permCache[$key] = $ok;

        } catch (Throwable $e) {
            error_log("PERMISSION CHECK ERROR: user_id={$uid} module={$module} action={$action} err=" . $e->getMessage());
            return $permCache[$key] = false; // الفشل = رفض
        }
    }
}

/**
 * إلزام المستخدم بامتلاك الصلاحية أو إرجاع 403
 */
if (!function_exists('require_permission')) {
    function require_permission(string $module, string $action): void {
        if (!has_permission_db($module, $action)) {
            $uid  = current_user_id() ?? 0;
            $path = $_SERVER['REQUEST_URI']  ?? '';
            $ip   = $_SERVER['REMOTE_ADDR']  ?? '';
            error_log("PERMISSION DENIED: user_id={$uid} module={$module} action={$action} path={$path} ip={$ip}");
            json_err('FORBIDDEN', "Permission '{$module}:{$action}' denied", (object)[], 403);
        }
    }
}

/**
 * تتطلب أي واحدة من مجموعة صلاحيات (OR)
 * مثال: require_any([['projects','create'], ['tasks','create']]);
 */
if (!function_exists('require_any')) {
    function require_any(array $pairs): void {
        foreach ($pairs as $pair) {
            // دعم كل من ['mod','act'] أو ['module' => 'mod', 'action' => 'act']
            if (is_array($pair)) {
                if (array_is_list($pair) && count($pair) >= 2) {
                    [$m, $a] = $pair;
                } else {
                    $m = (string)($pair['module'] ?? '');
                    $a = (string)($pair['action'] ?? '');
                }
                if ($m !== '' && $a !== '' && has_permission_db($m, $a)) {
                    return; // مُر
                }
            }
        }
        $uid  = current_user_id() ?? 0;
        $need = array_map(function ($p) {
            if (array_is_list($p) && count($p) >= 2) return "{$p[0]}:{$p[1]}";
            return "{$p['module']}:{$p['action']}";
        }, $pairs);
        error_log("PERMISSION DENIED (ANY): user_id={$uid} need_any=" . implode('|', $need));
        json_err('FORBIDDEN', 'Insufficient permissions (any)', ['need_any' => $need], 403);
    }
}

/**
 * تتطلب كل الصلاحيات (AND)
 * مثال: require_all([['projects','update'], ['tasks','update']]);
 */
if (!function_exists('require_all')) {
    function require_all(array $pairs): void {
        foreach ($pairs as $pair) {
            if (array_is_list($pair) && count($pair) >= 2) {
                [$m, $a] = $pair;
            } else {
                $m = (string)($pair['module'] ?? '');
                $a = (string)($pair['action'] ?? '');
            }
            if ($m === '' || $a === '' || !has_permission_db($m, $a)) {
                $uid = current_user_id() ?? 0;
                $miss = "{$m}:{$a}";
                error_log("PERMISSION DENIED (ALL): user_id={$uid} missing={$miss}");
                json_err('FORBIDDEN', 'Insufficient permissions (all)', ['missing' => $miss], 403);
            }
        }
    }
}

/* ====================================================================
   Project membership checks
   ==================================================================== */

/**
 * تحقّق من عضوية/دور المستخدم داخل مشروع معيّن
 * $roles مثال: ['member','manager'] — اتركها null لقبول أي دور.
 */
if (!function_exists('is_project_member')) {
    function is_project_member(PDO $pdo, int $projectId, int $userId, ?array $roles = null): bool {
        if ($roles && count($roles) > 0) {
            $place  = implode(',', array_fill(0, count($roles), '?'));
            $params = array_merge([$projectId, $userId], $roles);
            $sql    = "SELECT 1 FROM project_members WHERE project_id=? AND user_id=? AND role IN ($place) LIMIT 1";
            $st     = $pdo->prepare($sql);
            $st->execute($params);
        } else {
            $st = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id=? AND user_id=? LIMIT 1");
            $st->execute([$projectId, $userId]);
        }
        return (bool)$st->fetchColumn();
    }
}

/**
 * إلزام المستخدم أن يكون عضوًا/بدور معيّن داخل المشروع
 */
if (!function_exists('require_project_role')) {
    function require_project_role(int $projectId, ?array $roles = null): void {
        $uid = current_user_id();
        if (!$uid) {
            json_err('UNAUTHORIZED', 'Login required', (object)[], 401);
        }

        $pdo = db();

        // تأكد من أن المستخدم active
        try {
            $st = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $status = $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('USER STATUS CHECK ERROR: ' . $e->getMessage());
            json_err('SERVER_ERROR', 'Failed to verify user status', (object)[], 500);
        }

        if ($status !== 'active') {
            error_log("PROJECT ROLE DENIED (INACTIVE): user_id={$uid} project_id={$projectId}");
            json_err('FORBIDDEN', 'User is not active', (object)[], 403);
        }

        // تحقق العضوية/الدور داخل المشروع
        if (!is_project_member($pdo, $projectId, $uid, $roles)) {
            $roleStr = $roles ? implode('|', $roles) : 'any';
            error_log("PROJECT ROLE DENIED: user_id={$uid} project_id={$projectId} need_role={$roleStr}");
            json_err('FORBIDDEN', 'Project membership required', [
                'project_id' => $projectId,
                'need_role'  => $roleStr,
            ], 403);
        }
    }
}
