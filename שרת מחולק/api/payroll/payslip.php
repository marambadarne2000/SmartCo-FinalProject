<?php
# /api/payroll/payslip.php
declare(strict_types=1);

/**
 * יצירת תלוש שכר בפורמט PDF
 * - מחשב שעות חודש מתוך work_sessions ומגביל את החישוב לתקופת החודש.
 * - מציג פרטי עובד + סכום מוערך (שכר לשעה × שעות חודש).
 * - מציג פירוט ימי עבודה חודשי.
 * - מציג סיכום משימות אופציונלי לפי updated_at או created_at.
 * - משתמש ב-mPDF עם תמיכה בעברית.
 */

# ---------- Bootstrap ----------

$ROOT_API = realpath(__DIR__ . '/..');
if ($ROOT_API === false) { $ROOT_API = dirname(__DIR__); }

$BASE = realpath($ROOT_API . '/..');
if ($BASE === false) { $BASE = dirname($ROOT_API); }

function require_once_safe(string $p): void {
  if (!is_file($p)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => [
        'code' => 'BOOT',
        'message' => "Missing $p"
      ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  require_once $p;
}

require_once_safe($BASE . '/lib/utils.php');
require_once_safe($BASE . '/lib/session.php');
require_once_safe($BASE . '/lib/db.php');
require_once_safe($BASE . '/lib/authz_db.php');

# ---------- Auth ----------

require_auth();

if (!function_exists('require_permission')) {
  function require_permission(string $m, string $a): void {}
}
require_permission('payroll', 'read');

# ---------- Input ----------

$q = $_GET ?? [];
$userId = isset($q['user_id']) ? max(1, (int)$q['user_id']) : (current_user_id() ?? 0);
$year   = isset($q['year']) ? max(1970, (int)$q['year']) : (int)date('Y');
$month  = isset($q['month']) ? min(12, max(1, (int)$q['month'])) : (int)date('n');

if ($userId <= 0) {
  json_err('BAD_INPUT', 'Missing user_id', [], 422);
}

$mstart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$mend   = date('Y-m-d H:i:s', strtotime("$mstart +1 month"));

$pdo = db();

# ---------- Helpers ----------

function table_has_column(PDO $pdo, string $table, string $column): bool {
  try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
  } catch (\Throwable $e) {
    return false;
  }
}

function day_name_he(string $dateYmd): string {
  $map = [
    'Sunday' => 'ראשון',
    'Monday' => 'שני',
    'Tuesday' => 'שלישי',
    'Wednesday' => 'רביעי',
    'Thursday' => 'חמישי',
    'Friday' => 'שישי',
    'Saturday' => 'שבת',
  ];
  $en = date('l', strtotime($dateYmd));
  return $map[$en] ?? $en;
}

function time_hm(?string $dt): string {
  if (!$dt) return '-';
  $ts = strtotime($dt);
  if (!$ts) return '-';
  return date('H:i', $ts);
}

function date_ymd(?string $dt): string {
  if (!$dt) return '-';
  $ts = strtotime($dt);
  if (!$ts) return '-';
  return date('Y-m-d', $ts);
}

# ---------- Employee ----------

$u = $pdo->prepare("
  SELECT
    u.id,
    u.name,
    u.email,
    u.status,
    u.hourly_rate,
    r.name AS role_name,
    r.slug AS role_slug
  FROM users u
  LEFT JOIN roles r ON r.id = u.role_id
  WHERE u.id = ?
  LIMIT 1
");
$u->execute([$userId]);
$user = $u->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  json_err('NOT_FOUND', 'User not found', [], 404);
}

# ---------- Hours / Start-End ----------

$hours = 0.0;
$companyStartedAt = null;
$companyEndedAt = null;

try {
  $pdo->query("SELECT 1 FROM work_sessions LIMIT 1");

  $hoursStmt = $pdo->prepare("
    SELECT
      SUM(
        GREATEST(
          0,
          TIMESTAMPDIFF(
            SECOND,
            GREATEST(started_at, :mstart1),
            LEAST(COALESCE(ended_at, NOW()), :mend1)
          )
        )
      ) / 3600 AS hours_month
    FROM work_sessions
    WHERE user_id = :uid
      AND COALESCE(ended_at, NOW()) > :mstart2
      AND started_at < :mend2
  ");
  $hoursStmt->execute([
    ':uid'     => $userId,
    ':mstart1' => $mstart,
    ':mend1'   => $mend,
    ':mstart2' => $mstart,
    ':mend2'   => $mend,
  ]);
  $hours = (float)($hoursStmt->fetchColumn() ?: 0);

  $periodStmt = $pdo->prepare("
    SELECT
      MIN(started_at) AS started_at,
      MAX(ended_at) AS ended_at
    FROM work_sessions
    WHERE user_id = :uid
  ");
  $periodStmt->execute([':uid' => $userId]);
  $periodRow = $periodStmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $companyStartedAt = $periodRow['started_at'] ?? null;
  $companyEndedAt = $periodRow['ended_at'] ?? null;
} catch (\Throwable $e) {
  $hours = 0.0;
}

# ---------- Daily Rows ----------

$dailyRows = [];
try {
  $dailyStmt = $pdo->prepare("
    SELECT
      DATE(GREATEST(started_at, :mstart1)) AS work_date,
      MIN(started_at) AS first_start,
      MAX(ended_at) AS last_end,
      SUM(
        GREATEST(
          0,
          TIMESTAMPDIFF(
            SECOND,
            GREATEST(started_at, :mstart2),
            LEAST(COALESCE(ended_at, NOW()), :mend1)
          )
        )
      ) / 3600 AS hours_day
    FROM work_sessions
    WHERE user_id = :uid
      AND COALESCE(ended_at, NOW()) > :mstart3
      AND started_at < :mend2
    GROUP BY DATE(GREATEST(started_at, :mstart4))
    ORDER BY work_date ASC
  ");
  $dailyStmt->execute([
    ':uid'     => $userId,
    ':mstart1' => $mstart,
    ':mstart2' => $mstart,
    ':mstart3' => $mstart,
    ':mstart4' => $mstart,
    ':mend1'   => $mend,
    ':mend2'   => $mend,
  ]);
  $dailyRows = $dailyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
  $dailyRows = [];
}

# ---------- Amounts ----------

$hourly = (float)($user['hourly_rate'] ?? 0);
$amount = round($hours * $hourly, 2);

$actualWorkDays = 0;
foreach ($dailyRows as $row) {
  $hoursDay = (float)($row['hours_day'] ?? 0);
  if ($hoursDay > 0) {
    $actualWorkDays++;
  }
}

# ---------- Optional Tasks ----------

$tasks = [];
try {
  $dateCol = null;

  if (table_has_column($pdo, 'tasks', 'updated_at')) {
    $dateCol = 'updated_at';
  } elseif (table_has_column($pdo, 'tasks', 'created_at')) {
    $dateCol = 'created_at';
  }

  if ($dateCol !== null) {
    $sqlTasks = "
      SELECT t.id, t.title, t.status, t.project_id
      FROM tasks t
      WHERE t.assignee_id = :uid
        AND t.`$dateCol` >= :mstart
        AND t.`$dateCol` < :mend
      ORDER BY t.`$dateCol` DESC
      LIMIT 50
    ";
    $tasksStmt = $pdo->prepare($sqlTasks);
    $tasksStmt->execute([
      ':uid' => $userId,
      ':mstart' => $mstart,
      ':mend' => $mend
    ]);
    $tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (\Throwable $e) {
  $tasks = [];
}

# ---------- mPDF ----------

$autoload = $BASE . '/vendor/autoload.php';
if (!is_file($autoload)) {
  header('Content-Type: text/html; charset=utf-8');
  echo "<h2>Payslip (mPDF not installed)</h2>";
  echo "<p>Install with: <code>composer require mpdf/mpdf</code></p>";
  echo "<pre>" . htmlspecialchars(print_r([
    'user' => $user,
    'year' => $year,
    'month' => $month,
    'hours' => $hours,
    'hourly_rate' => $hourly,
    'amount' => $amount
  ], true)) . "</pre>";
  exit;
}
require_once $autoload;

if (!class_exists(\Mpdf\Mpdf::class)) {
  header('Content-Type: text/html; charset=utf-8');
  echo "<h2>Payslip (mPDF class missing)</h2>";
  echo "<p>Please run: <code>composer require mpdf/mpdf</code></p>";
  exit;
}

$companyName = 'SmartCo';
$currency    = '₪';
$periodLabel = sprintf('%02d/%d', $month, $year);
$today       = date('Y-m-d H:i');

$fontsDirCustom = $BASE . '/fonts/noto';
$cfgVars = new \Mpdf\Config\ConfigVariables();
$fontCfg = new \Mpdf\Config\FontVariables();

$fontDirs = $cfgVars->getDefaults()['fontDir'];
$fontData = $fontCfg->getDefaults()['fontdata'];

$fontDirMerged = is_dir($fontsDirCustom) ? array_merge($fontDirs, [$fontsDirCustom]) : $fontDirs;
$fontDataMerged = $fontData + [
  'notosanshebrew' => ['R' => 'NotoSansHebrew-Regular.ttf', 'B' => 'NotoSansHebrew-Bold.ttf'],
  'amiri' => ['R' => 'Amiri-Regular.ttf', 'B' => 'Amiri-Bold.ttf'],
];

$mpdf = new \Mpdf\Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_left' => 12,
  'margin_right' => 12,
  'margin_top' => 14,
  'margin_bottom' => 14,
  'fontDir' => $fontDirMerged,
  'fontdata' => $fontDataMerged,
  'default_font' => 'notosanshebrew',
  'autoLangToFont' => true,
  'autoScriptToLang' => true,
  'tempDir' => sys_get_temp_dir(),
]);

$mpdf->SetDirectionality('rtl');

# ---------- HTML ----------

ob_start();
?>
<html dir="rtl" lang="he">
<head>
<meta charset="utf-8"/>
<style>
  body { font-family: notosanshebrew, amiri, sans-serif; font-size: 12px; color: #222; }
  h1 { font-size: 18px; margin: 0 0 8px; }
  h3 { margin: 14px 0 6px; }
  .meta, .sig { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .meta th, .meta td { border: 1px solid #ccc; padding: 6px 8px; }
  .meta th { background: #f5f5f5; text-align: right; }
  .grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
  .grid th, .grid td { border: 1px solid #ddd; padding: 6px 8px; }
  .grid th { background: #f0f0f0; }
  .tot { font-weight: bold; }
  .small { color: #555; font-size: 11px; }
  .section-note { margin-top: 8px; }
</style>
</head>
<body>

<h1>תלוש שכר — <?= htmlspecialchars($periodLabel) ?></h1>

<table class="meta">
  <tr>
    <th>חברה</th><td><?= htmlspecialchars($companyName) ?></td>
    <th>תאריך הפקה</th><td><?= htmlspecialchars($today) ?></td>
  </tr>
  <tr>
    <th>עובד</th><td><?= htmlspecialchars($user['name'] ?? '') ?></td>
    <th>דוא"ל</th><td><?= htmlspecialchars($user['email'] ?? '') ?></td>
  </tr>
  <tr>
    <th>תפקיד</th><td><?= htmlspecialchars($user['role_name'] ?? $user['role_slug'] ?? '') ?></td>
    <th>סטטוס</th><td><?= htmlspecialchars($user['status'] ?? '') ?></td>
  </tr>
  <tr>
    <th>תחילת עבודה</th><td><?= htmlspecialchars(date_ymd($companyStartedAt)) ?></td>
    <th>סיום עבודה</th><td><?= htmlspecialchars(date_ymd($companyEndedAt)) ?></td>
  </tr>
</table>

<table class="grid">
  <tr>
    <th>תקופה</th>
    <th>ימי עבודה</th>
    <th>שעות חודש</th>
    <th>שכר לשעה</th>
    <th>סכום מוערך</th>
  </tr>
  <tr>
    <td><?= htmlspecialchars($periodLabel) ?></td>
    <td><?= $actualWorkDays ?></td>
    <td><?= number_format($hours, 2) ?></td>
    <td><?= number_format($hourly, 2) . " $currency" ?></td>
    <td class="tot"><?= number_format($amount, 2) . " $currency" ?></td>
  </tr>
</table>

<?php if (!empty($dailyRows)): ?>
  <h3>פירוט ימי עבודה בחודש</h3>
  <table class="grid">
    <tr>
      <th>תאריך</th>
      <th>יום</th>
      <th>התחלה</th>
      <th>סיום</th>
      <th>שעות</th>
    </tr>
    <?php foreach ($dailyRows as $d): ?>
      <?php
        $date = (string)($d['work_date'] ?? '');
        $dayName = $date ? day_name_he($date) : '';
        $first = time_hm($d['first_start'] ?? null);
        $last  = time_hm($d['last_end'] ?? null);
        $h = round((float)($d['hours_day'] ?? 0), 2);
      ?>
      <tr>
        <td><?= htmlspecialchars($date) ?></td>
        <td><?= htmlspecialchars($dayName) ?></td>
        <td><?= htmlspecialchars($first) ?></td>
        <td><?= htmlspecialchars($last) ?></td>
        <td><?= number_format($h, 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p class="small section-note">
    סה"כ ימי עבודה: <?= $actualWorkDays ?> |
    סה"כ שעות: <?= number_format($hours, 2) ?> |
    שכר לשעה: <?= number_format($hourly, 2) . " $currency" ?> |
    סכום: <?= number_format($amount, 2) . " $currency" ?>
  </p>
<?php endif; ?>

<?php if (!empty($tasks)): ?>
  <h3>סיכום משימות לחודש זה</h3>
  <table class="grid">
    <tr>
      <th>#</th>
      <th>משימה</th>
      <th>סטטוס</th>
      <th>פרויקט</th>
    </tr>
    <?php foreach ($tasks as $i => $t): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($t['title'] ?? ('Task #' . $t['id'])) ?></td>
        <td><?= htmlspecialchars($t['status'] ?? '') ?></td>
        <td><?= (int)($t['project_id'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<p class="small">מסמך זה הינו לצורכי הערכה בלבד. השכר הסופי עשוי לכלול ניכויים או תוספות בהתאם למדיניות החברה.</p>

<table class="sig">
  <tr>
    <td style="height:60px; width:50%;">חתימת עובד: _____________</td>
    <td style="height:60px; width:50%;">חתימת הנהלה: _____________</td>
  </tr>
</table>

</body>
</html>
<?php

$html = ob_get_clean();
$mpdf->WriteHTML($html);

$filename = sprintf('payslip_%s_%d_%02d_user%d.pdf', $companyName, $year, $month, $userId);
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
exit;