<?php
declare(strict_types=1);

/**
 * utils.php
 *
 * ملاحظات:
 * - إن كان لديك lib/json.php يعرّف نفس الدوال، يمكنك الاكتفاء به:
 *     require_once __DIR__ . '/json.php';
 *   هذه الدوال موجودة هنا كـ fallback عبر function_exists لمنع إعادة التعريف.
 */

/* ========================== JSON helpers ========================== */

if (!function_exists('emit_json')) {
    /**
     * إرسال استجابة JSON موحّدة
     */
    function emit_json(array $payload, int $http = 200, array $headers = []): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            foreach ($headers as $hName => $hValue) {
                header($hName . ': ' . $hValue);
            }
            http_response_code($http);
        }
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

if (!function_exists('json_ok')) {
    /**
     * نجاح: ok=true + data + meta (اختياري)
     */
    function json_ok($data = null, $meta = null, int $http = 200): void {
        $out = ['ok' => true, 'data' => $data];
        if ($meta !== null) {
            $out['meta'] = is_array($meta) ? (object)$meta : $meta;
        }
        emit_json($out, $http);
    }
}

if (!function_exists('json_err')) {
    /**
     * خطأ: ok=false + error {code,message,details} + http status
     */
    function json_err(string $code, string $message = '', $details = null, int $http = 400): void {
        if ($message === '') {
            $defaults = [
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                409 => 'Conflict',
                419 => 'Authentication Timeout',
                422 => 'Unprocessable Entity',
                429 => 'Too Many Requests',
                500 => 'Internal Server Error',
            ];
            $message = $defaults[$http] ?? 'Error';
        }

        if ($details === null) {
            $details = (object)[];
        } elseif (is_array($details)) {
            $details = (object)$details;
        }

        emit_json([
            'ok'    => false,
            'error' => [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $http);
    }
}

/* ========================== Request helpers ========================== */

/**
 * قراءة جسم الطلب (JSON أو x-www-form-urlencoded) ودمجهما بشكل آمن.
 */
if (!function_exists('json_input')) {
    function json_input(): array {
        $data = [];

        // 1) JSON من الجسم
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
            // لو JSON فاسد نتجاهله بصمت
        }

        // 2) دمج POST التقليدي (لا يطغى على المفاتيح الموجودة)
        if (!empty($_POST)) {
            foreach ($_POST as $k => $v) {
                if (!array_key_exists($k, $data)) {
                    $data[$k] = $v;
                }
            }
        }

        return $data;
    }
}

/**
 * إلزام وجود حقول معيّنة داخل البيانات
 */
if (!function_exists('require_fields')) {
    function require_fields(array $source, array $fields): array {
        $out = [];
        $missing = [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $source) || $source[$f] === null || $source[$f] === '') {
                $missing[] = $f;
            } else {
                $out[$f] = $source[$f];
            }
        }
        if ($missing) {
            json_err('VALIDATION_ERROR', 'Missing required fields', ['missing' => $missing], 422);
        }
        return $out;
    }
}

/**
 * انتقاء عدد صحيح من مصفوفة (GET/POST/JSON) مع قيود اختيارية
 */
if (!function_exists('int_from')) {
    function int_from(?array $src, string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int {
        $src = $src ?? [];
        if (!array_key_exists($key, $src) || $src[$key] === '' || $src[$key] === null) {
            return $default;
        }
        if (is_numeric($src[$key])) {
            $val = (int)$src[$key];
            if ($min !== null && $val < $min) $val = $min;
            if ($max !== null && $val > $max) $val = $max;
            return $val;
        }
        return $default;
    }
}

/**
 * انتقاء نص مشذّب من مصفوفة (GET/POST/JSON) مع طول أقصى
 */
if (!function_exists('str_from')) {
    function str_from(?array $src, string $key, ?string $default = null, int $maxLen = 0, bool $lower = false): ?string {
        $src = $src ?? [];
        if (!array_key_exists($key, $src) || $src[$key] === null) {
            return $default;
        }
        $val = trim((string)$src[$key]);
        if ($lower) $val = mb_strtolower($val, 'UTF-8');
        if ($maxLen > 0 && mb_strlen($val, 'UTF-8') > $maxLen) {
            $val = mb_substr($val, 0, $maxLen, 'UTF-8');
        }
        return $val;
    }
}

/**
 * استخراج معلمات الترقيم من الاستعلام
 */
if (!function_exists('paginate_from_query')) {
    function paginate_from_query(?array $query = null, int $defaultLimit = 20, int $maxLimit = 100): array {
        $q = $query ?? $_GET;
        $limit  = int_from($q, 'limit',  $defaultLimit, 1, $maxLimit);
        $offset = int_from($q, 'offset', 0, 0, PHP_INT_MAX);
        return ['limit' => $limit, 'offset' => $offset];
    }
}

/**
 * الهروب الآمن لِرموز LIKE (% و _)
 */
if (!function_exists('sql_like_escape')) {
    function sql_like_escape(string $s): string {
        return strtr($s, [
            '\\' => '\\\\',
            '%'  => '\%',
            '_'  => '\_',
        ]);
    }
}

/**
 * UUID v4 بسيط
 */
if (!function_exists('uuidv4')) {
    function uuidv4(): string {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); // نسخة 4
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80); // متغير
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

/**
 * مولّد رموز عشوائية (هكسا)
 */
if (!function_exists('random_token')) {
    function random_token(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }
}

/**
 * هل المصفوفة ترابطية؟
 */
if (!function_exists('is_assoc')) {
    function is_assoc(array $arr): bool {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

/* ========================== CSRF (fallback wrapper) ========================== */
/**
 * غلاف اختياري لـ require_csrf الموجودة في session.php
 * يُترك فارغًا هنا كي لا يسبب إعادة تعريف عند تحميل session.php.
 */
if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        // النسخة الحقيقية موجودة في session.php
    }
}

/* ========================== CORS helpers ========================== */

/**
 * أرسل رؤوس CORS المناسبة. لاحظ:
 * - عند استخدام الكوكيز (withCredentials:true)، لا يجوز استخدام '*'
 * - يجب تحديد Origin مسموح
 */
if (!function_exists('cors_send_headers')) {
    function cors_send_headers(): void {
        // عدّل قائمة الأصول المسموح بها حسب بيئتك
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = [
            'http://localhost:4200',
            'http://127.0.0.1:4200',
            // 'https://your-prod-domain.com', // أضِف دومين الإنتاج عند الحاجة
        ];

        if ($origin && in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true'); // للسماح بالكوكيز
        } else {
            // لو لا تستخدم كوكيز عبر كروس-أوريجن، يمكنك الإبقاء على '*'
            // لكن مع withCredentials يجب عدم استخدام '*'
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        // لا تضبط Content-Type هنا، json_ok/json_err سيتكفلان بذلك للردود الفعلية
    }
}

/**
 * معالجة Preflight لطلبات OPTIONS (ترد 204 بلا جسم).
 */
if (!function_exists('cors_send_preflight')) {
    function cors_send_preflight(): void {
        cors_send_headers();
        http_response_code(204);
        // لا ترجع JSON في الـ preflight
        exit;
    }
}
