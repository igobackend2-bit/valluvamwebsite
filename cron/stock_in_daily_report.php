<?php
// Daily Stock In report — intended to be triggered by a server crontab entry
// at 9:30am every day (see README.md in this folder), NOT by anyone visiting
// it in a browser. Protected by a secret key so it can't be triggered by
// strangers guessing the URL.
//
// What it does: pulls every stock_movements row with movement_type='stock_in'
// for "today" (the day the script actually runs), builds a CSV, saves it into
// cron/reports/, and emails it as an attachment to the admin address below.
// Uses the exact same SMTP credentials already used elsewhere in this project
// (assets/db_query/order/create.php) — nothing new to configure.

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../assets/db_query/config.php';

// ── Secret key check ────────────────────────────────────────────────────
// Set via the CRON_REPORT_KEY environment variable (same .env / getenv()
// mechanism config.php already uses). Falls back to a generated default so
// this still works out of the box — change it if you want to rotate it.
$expectedKey = getenv('CRON_REPORT_KEY') ?: 'cbf64ff8f511ba21bbcf2f8f9300b1730f8f0154';
$providedKey = $_GET['key'] ?? ($argv[1] ?? '');
if (!hash_equals($expectedKey, (string)$providedKey)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$adminEmail = getenv('STOCK_REPORT_EMAIL') ?: 'admin@valluvam.com';
$today = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT sm.created_at, sm.quantity, sm.previous_stock, sm.new_stock, sm.reference_number, sm.reason,
               sm.created_by, p.product_name
        FROM stock_movements sm
        LEFT JOIN product_details p ON p.id = sm.product_id
        WHERE sm.movement_type = 'stock_in' AND DATE(sm.created_at) = ?
        ORDER BY sm.created_at ASC
    ");
    $stmt->execute([$today]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB error: " . $e->getMessage() . "\n";
    exit;
}

// ── Build CSV ────────────────────────────────────────────────────────────
$csvLines = ['Time,Product,Quantity In,Previous Stock,New Stock,Reference,Reason,Recorded By'];
$totalQty = 0;
foreach ($rows as $r) {
    $totalQty += (int)$r['quantity'];
    $line = [
        date('H:i', strtotime($r['created_at'])),
        $r['product_name'] ?? ('Product #' . $r['product_id']),
        $r['quantity'],
        $r['previous_stock'],
        $r['new_stock'],
        $r['reference_number'] ?? '',
        $r['reason'] ?? '',
        $r['created_by'] ?? '',
    ];
    $csvLines[] = implode(',', array_map('csv_escape', $line));
}
$csvContent = implode("\n", $csvLines);

// ── Save a copy on disk ──────────────────────────────────────────────────
$reportsDir = __DIR__ . '/reports';
if (!is_dir($reportsDir)) { @mkdir($reportsDir, 0755, true); }
$filePath = $reportsDir . '/stock_in_' . $today . '.csv';
@file_put_contents($filePath, $csvContent);

// ── Email it ─────────────────────────────────────────────────────────────
$mailResult = send_report_email(
    $adminEmail,
    'Daily Stock In Report — ' . $today,
    '<h3>Stock In report for ' . htmlspecialchars($today) . '</h3>'
        . '<p>' . count($rows) . ' movement(s), ' . $totalQty . ' unit(s) received in total today.</p>'
        . '<p>Full CSV is attached.</p>',
    $filePath,
    'stock_in_' . $today . '.csv'
);

echo "OK — " . count($rows) . " row(s). Email: " . ($mailResult['ok'] ? 'sent' : ('FAILED: ' . $mailResult['error'])) . "\n";

// ── Shared mail helper (kept local to this script — does not touch any
//    other file's PHPMailer usage) ────────────────────────────────────────
function send_report_email(string $toEmail, string $subject, string $htmlBody, ?string $attachmentPath = null, ?string $attachmentName = null): array {
    $projectRoot = dirname(__DIR__);
    $autoload = $projectRoot . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'error' => 'vendor/autoload.php not found'];
    }
    require_once $autoload;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = "smtp.gmail.com";
        $mail->SMTPAuth   = true;
        $mail->Username   = "marketing@igogroups.com";
        $mail->Password   = "ochv yqhv gvml fqxa";
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = "UTF-8";
        $mail->setFrom("marketing@igogroups.com", "Valluvam Products");
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        if ($attachmentPath && is_file($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
        }
        $mail->send();
        return ['ok' => true];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function csv_escape($val) {
    $str = (string)($val ?? '');
    if (strpos($str, ',') !== false || strpos($str, '"') !== false || strpos($str, "\n") !== false) {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    return $str;
}
