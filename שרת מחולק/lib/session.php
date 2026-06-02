<?php
declare(strict_types=1);

/**
 * session.php
 * تهيئة الـ Session + دوال المصادقة و CSRF.
 * مهيّأ ليكون قابلاً لإعادة التحميل بدون أخطاء إعادة تعريف (function_exists).
 */

/* ========================== Session init ========================== */

/* إعدادات أمان الكوكي (يمكنك تعديلها حسب بيئتك) */
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');

/* ملاحظة: session.cookie_secure يجب أن يكون 1 فقط عند HTTPS */
if (!headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @ini_set('session.cookie_secure', $isHttps ? '1' : '0');
}

/* اسم الجلسة (عدّل عند الحاجة) */
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'SCMSESSID') {
        session_name('SCMSESSID');
    }
    @session_start();
}

/* ملاحظة: لا نفرض Header Content-Type هنا تلقائياً، اترك ردود الـ JSON للطبقات الأعلى */
// إذا كنت تريد فرض JSON دائمًا من هذا الملف، أزل التعليق عن السطر التالي:
// header('Content-Type: application/json; charset=utf-8');

/* ========================== CSRF ========================== */

if (!function_exists('csrf_token')) {
    /**
     * إنشاء/جلب رمز CSRF مخزّن بالجلسة
     */
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

/**
 * تحقّق CSRF. يتجاهل GET/HEAD/OPTIONS تلقائياً.
 * يقرأ التوكن من:
 * - الهيدر: X-CSRF-Token أو X-CSRF
 * - جسم JSON: الحقل csrf
 */
if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
            return; // لا يلزم CSRF
        }

        // التوكن من الهيدر
        $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';

        // إن لم يوجد بالهيدر جرّب من JSON
        if ($tok === '') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            $isJson = stripos($contentType, 'application/json') !== false
                   || stripos($contentType, 'text/json') !== false;

            if ($isJson) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && $raw !== '') {
                    $j = json_decode($raw, true);
                    if (is_array($j) && !empty($j['csrf'])) {
                        $tok = (string)$j['csrf'];
                    }
                }
            }
        }

        $sess = $_SESSION['csrf'] ?? '';
        if (!$tok || !$sess || !hash_equals($sess, $tok)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(419);
            }
            echo json_encode([
                'ok'    => false,
                'error' => [
                    'code'    => 'CSRF_ERROR',
                    'message' => 'Invalid or missing CSRF token',
                    'details' => new stdClass(),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

/* ========================== Auth helpers ========================== */

if (!function_exists('current_user')) {
    /**
     * يعيد معلومات المستخدم من الجلسة أو null إذا غير مسجّل
     */
    function current_user(): ?array {
        if (!isset($_SESSION['user_id'])) return null;
        return [
            'id'      => (int)($_SESSION['user_id']),
            'name'    => (string)($_SESSION['user_name'] ?? ''),
            'email'   => (string)($_SESSION['user_email'] ?? ''),
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
            'status'  => (string)($_SESSION['status'] ?? ''),
        ];
    }
}

if (!function_exists('current_user_id')) {
    /**
     * معرّف المستخدم الحالي أو null
     */
    function current_user_id(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * هل المستخدم مسجّل دخول؟
     */
    function is_logged_in(): bool {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('require_auth')) {
    /**
     * يلزم تسجيل الدخول وإلا يعيد 401 JSON
     */
    function require_auth(): void {
        if (!is_logged_in()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
            }
            echo json_encode([
                'ok'    => false,
                'error' => [
                    'code'    => 'UNAUTHORIZED',
                    'message' => 'Not logged in',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

if (!function_exists('login_user')) {
    /**
     * تسجيل الدخول ووضع الحقول الأساسية + توليد CSRF
     */
    function login_user(int $id, string $name, string $email, ?int $roleId, string $status = 'active'): void {
        // تشديد الحماية ضد تثبيت الجلسة
        @session_regenerate_id(true);

        $_SESSION['user_id']    = $id;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role_id']    = $roleId;
        $_SESSION['status']     = $status;

        // توليد/تحديث رمز CSRF
        csrf_token();
    }
}

if (!function_exists('logout_user')) {
    /**
     * تسجيل الخروج وإبطال الكوكي
     */
    function logout_user(): void {
        // امسح بيانات الجلسة
        $_SESSION = [];

        // احذف كوكي الجلسة إن كانت قيد الاستخدام
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            // اجعل الحذف مطابقًا لسمات الكوكي
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => !empty($params['secure']),
                    'httponly' => !empty($params['httponly']),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        // دمّر الجلسة
        @session_destroy();
    }
}
