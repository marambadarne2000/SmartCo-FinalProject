<?php
# טעינת ספרייה לניהול סשנים כדי לדעת מי המשתמש המחובר
require_once __DIR__ . '/../../lib/session.php';

# התחברות למסד הנתונים כדי לבצע חישובים ושאילתות
require_once __DIR__ . '/../../lib/db.php';

# פונקציות לשליחת תגובות JSON בצורה מסודרת
require_once __DIR__ . '/../../lib/json.php';

# בדיקה אם המשתמש מחובר, אם לא מחזיר שגיאה ומונע גישה
require_auth();

# התחברות למסד הנתונים
$pdo = db();

# קריאה של הנתונים שנשלחו בגוף הבקשה והמרה למערך PHP
$in = json_decode(file_get_contents('php://input'), true) ?: [];

# אחזור נתוני בסיס, שעות נוספות, שיעור ותוספות והפחתות
$base = (float)($in['base'] ?? 0);
$overtime_hours = (float)($in['overtime_hours'] ?? 0);
$overtime_rate  = (float)($in['overtime_rate']  ?? 0);
$allowances = is_array($in['allowances'] ?? null) ? $in['allowances'] : [];
$deductions = is_array($in['deductions'] ?? null) ? $in['deductions'] : [];

# שליפה של מדרגות מס כדי לחשב מס לפי סכום ההכנסה
$st = $pdo->query("SELECT bracket_from, bracket_to, rate_percent FROM tax_brackets ORDER BY bracket_from ASC");
$brackets = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

# פונקציה לעיגול מספרים לשתי ספרות אחרי הנקודה
function round2($n){ return round($n + 1e-12, 2); }

# חישוב מס פרוגרסיבי לפי מדרגות המס
function calc_tax_progressive($amount, $brackets){
    $remaining = $amount; 
    $total = 0; 
    $detail = [];
    foreach ($brackets as $b){
        $from = (float)$b['bracket_from'];
        $to   = isset($b['bracket_to']) ? (float)$b['bracket_to'] : INF;
        $rate = ((float)$b['rate_percent'])/100.0;
        $cap = $to - $from; 
        if ($cap <= 0) continue;
        if ($remaining <= 0) break;
        $taxable = min($remaining, $cap);
        $t = $taxable * $rate; 
        $total += $t;
        $detail[] = ['bracket' => $from.'-'.($to===INF?'∞':$to).' @ '.round2($rate*100).'%','amount'=>round2($t)];
        $remaining -= $taxable;
    }
    # החזרת סכום המס וסיכום פרטי החישוב לכל מדרגה
    return ['tax'=>round2($total), 'detail'=>$detail];
}

# חישוב תשלום עבור שעות נוספות
$ot = round2($overtime_hours * $overtime_rate);

# חישוב ההכנסה ברוטו כולל בסיס, שעות נוספות ותוספות
$gross = round2($base + $ot + array_reduce($allowances, fn($s,$a)=>$s + (float)($a['amount']??0), 0));

# חישוב מס לפי מדרגות
$taxRes = calc_tax_progressive($gross, $brackets);

# חישוב הפחתות נוספות
$other = round2(array_reduce($deductions, fn($s,$d)=>$s + (float)($d['amount']??0), 0));

# חישוב סכום כולל של המס וההפחתות
$totalDed = round2($taxRes['tax'] + $other);

# חישוב נטו לאחר הפחתות
$net = round2($gross - $totalDed);

# הכנת מבנה הנתונים שישלח ללקוח
$data = [
    'gross' => $gross,
    'tax' => $taxRes['tax'],
    'otherDeductions' => $other,
    'totalDeductions' => $totalDed,
    'net' => $net,
    'items' => [
        # פירוט התוספות
        'allowances' => array_map(fn($a)=>[
            'key'=> (string)($a['key'] ?? 'allowance'),
            'label'=> (string)($a['label'] ?? 'Allowance'),
            'amount'=> round2((float)($a['amount'] ?? 0))
        ], $allowances),
        # פירוט ההפחתות
        'deductions' => array_map(fn($d)=>[
            'key'=> (string)($d['key'] ?? 'deduction'),
            'label'=> (string)($d['label'] ?? 'Deduction'),
            'amount'=> round2((float)($d['amount'] ?? 0))
        ], $deductions),
        # פירוט חישוב המס לכל מדרגה
        'taxDetail' => $taxRes['detail'],
        # פירוט שעות נוספות אם קיימות
        'overtime' => $ot ? ['hours'=>$overtime_hours,'rate'=>$overtime_rate,'amount'=>$ot] : null,
    ],
];

# שליחת התוצאה בפורמט JSON ללקוח
json_ok($data);
