<?php
# טעינת ספריות עזר
require_once __DIR__ . '/../../lib/session.php';   # ניהול סשנים
require_once __DIR__ . '/../../lib/db.php';        # חיבור למסד נתונים
require_once __DIR__ . '/../../lib/json.php';      # פונקציות JSON

# בדיקה שהמשתמש מחובר
require_auth();

# בדיקה של הרשאות (ניתן להפעיל בעתיד)
# require_perm('payroll','create');

# ---------- התחברות למסד הנתונים ----------
$pdo = db();

# ---------- קריאת קלט מהבקשה ----------
# ניתוח JSON שהגיע מהלקוח
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$employee_id = (int)($in['employee_id'] ?? 0);           # מזהה העובד
$year        = (int)($in['period_year'] ?? 0);          # שנת התלוש
$month       = (int)($in['period_month'] ?? 0);         # חודש התלוש
$res         = $in['result'] ?? null;                  # תוצאות חישוב השכר (gross, net וכו')

# ---------- אימות קלט ----------
# בדיקה שהפרמטרים תקינים
if (!$employee_id || $year < 2000 || $month < 1 || $month > 12) {
  json_err('VALIDATION', 'Invalid input');
}

# ---------- התחלת טרנזקציה ----------
# על מנת שכל ההכנסה למסד תהיה אטומית
$pdo->beginTransaction();

# ---------- יצירת/עדכון רשומת payroll_runs ----------
# כאן נשמרים סכומי השכר הכלליים
$st = $pdo->prepare("
  INSERT INTO payroll_runs (employee_id, period_year, period_month, gross, total_deductions, net_pay)
  VALUES (:e,:y,:m,:g,:td,:n)
  ON DUPLICATE KEY UPDATE 
    gross=VALUES(gross), 
    total_deductions=VALUES(total_deductions), 
    net_pay=VALUES(net_pay)
");
$st->execute([
  ':e'=>$employee_id,
  ':y'=>$year,
  ':m'=>$month,
  ':g'=>$res['gross'] ?? 0,
  ':td'=>$res['totalDeductions'] ?? 0,
  ':n'=>$res['net'] ?? 0,
]);

# קבלת מזהה התלוש (INSERT חדש או קיים)
$runId = $pdo->lastInsertId();
if (!$runId) {
  # במקרה שהייתה UPDATE בגלל UNIQUE KEY
  $st2 = $pdo->prepare("SELECT id FROM payroll_runs WHERE employee_id=? AND period_year=? AND period_month=?");
  $st2->execute([$employee_id, $year, $month]);
  $runId = (int)$st2->fetchColumn();
}

# ---------- מחיקת פריטים קודמים ----------
# נקה פריטים קיימים לפני הכנסת חדשים
$pdo->prepare("DELETE FROM payroll_items WHERE payroll_run_id=?")->execute([$runId]);

# ---------- הכנסה של פריטי השכר ----------
$ins = $pdo->prepare("INSERT INTO payroll_items (payroll_run_id,item_key,label,amount,type) VALUES (?,?,?,?,?)");

# הכנסת תוספות (allowances)
foreach (($res['items']['allowances'] ?? []) as $it) {
  $ins->execute([
    $runId, 
    (string)($it['key']??'allowance'), 
    (string)($it['label']??'Allowance'), 
    (float)($it['amount']??0), 
    'allowance'
  ]);
}

# הכנסת ניכויים (deductions)
foreach (($res['items']['deductions'] ?? []) as $it) {
  $ins->execute([
    $runId, 
    (string)($it['key']??'deduction'), 
    (string)($it['label']??'Deduction'), 
    (float)($it['amount']??0), 
    'deduction'
  ]);
}

# הכנסת מס לפי מדרגות (taxDetail)
foreach (($res['items']['taxDetail'] ?? []) as $i=>$it) {
  $ins->execute([
    $runId, 
    'tax_b'.$i, 
    (string)($it['bracket']??'Tax'), 
    (float)($it['amount']??0), 
    'tax'
  ]);
}

# הכנסת שעות נוספות אם קיימות
if (!empty($res['items']['overtime'])) {
  $ot = $res['items']['overtime'];
  $ins->execute([
    $runId, 
    'overtime', 
    'Overtime', 
    (float)($ot['amount']??0), 
    'overtime'
  ]);
}

# ---------- סיום טרנזקציה ----------
$pdo->commit();

# ---------- החזרת תגובה ללקוח ----------
json_ok(['id'=>$runId]);  # מחזיר את מזהה התלוש שנוצר/עודכן
