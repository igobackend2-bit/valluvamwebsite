<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

function clean($v) {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$full_name          = clean($_POST['full_name'] ?? '');
$business_name       = clean($_POST['business_name'] ?? '');
$business_type       = clean($_POST['business_type'] ?? '');
$city                = clean($_POST['city'] ?? '');
$state               = clean($_POST['state'] ?? '');
$phone               = clean($_POST['phone'] ?? '');
$email               = trim($_POST['email'] ?? '');
$gst                 = clean($_POST['gst'] ?? '');
$monthly_requirement = clean($_POST['monthly_requirement'] ?? '');
$pack_size           = clean($_POST['pack_size'] ?? '');
$message             = clean($_POST['message'] ?? '');
$products            = isset($_POST['products']) && is_array($_POST['products']) ? array_map('clean', $_POST['products']) : [];
$productsList        = !empty($products) ? implode(', ', $products) : '-';

if ($full_name === '' || $phone === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in your name and phone number.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}
$emailClean = $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '';

// Local backup log (kept independent of the database)
$folder = __DIR__ . '/../../b2b_enquiries';
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}
$logId = date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
$logData = "Full Name: $full_name\n"
    . "Business Name: $business_name\n"
    . "Business Type: $business_type\n"
    . "City: $city\n"
    . "State: $state\n"
    . "Phone: $phone\n"
    . "Email: $emailClean\n"
    . "GST: $gst\n"
    . "Products: $productsList\n"
    . "Monthly Requirement: $monthly_requirement\n"
    . "Pack Size: $pack_size\n"
    . "Message: $message\n"
    . "Submitted At: " . date('Y-m-d H:i:s') . "\n";
file_put_contents("$folder/enquiry_{$logId}.txt", $logData);

function sendMail(string $toEmail, string $toName, string $subject, string $body, string $replyTo = '', string $replyToName = ''): array
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = "smtp.gmail.com";
        $mail->SMTPAuth   = true;
        $mail->Username   = "marketing@igogroups.com";
        $mail->Password   = "ochv yqhv gvml fqxa";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = "UTF-8";
        $mail->setFrom("marketing@igogroups.com", "Valluvam B2B Enquiry");
        $mail->addAddress($toEmail, $toName);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo, $replyToName);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return ["ok" => true];
    } catch (Throwable $e) {
        error_log("B2B ENQUIRY MAIL FAIL to={$toEmail} err=" . $e->getMessage());
        return ["ok" => false, "error" => $e->getMessage()];
    }
}

$body = "
    <h3>New B2B Wholesale Enquiry</h3>
    <p><b>Full Name:</b> {$full_name}</p>
    <p><b>Business Name:</b> " . ($business_name !== '' ? $business_name : '-') . "</p>
    <p><b>Business Type:</b> " . ($business_type !== '' ? $business_type : '-') . "</p>
    <p><b>City:</b> " . ($city !== '' ? $city : '-') . "</p>
    <p><b>State:</b> " . ($state !== '' ? $state : '-') . "</p>
    <p><b>Phone / WhatsApp:</b> {$phone}</p>
    <p><b>Email:</b> " . ($emailClean !== '' ? $emailClean : '-') . "</p>
    <p><b>GST Number:</b> " . ($gst !== '' ? $gst : '-') . "</p>
    <p><b>Products Interested In:</b> {$productsList}</p>
    <p><b>Approx Monthly Requirement:</b> " . ($monthly_requirement !== '' ? $monthly_requirement : '-') . "</p>
    <p><b>Pack Size Needed:</b> " . ($pack_size !== '' ? $pack_size : '-') . "</p>
    <p><b>Message / Notes:</b><br>" . ($message !== '' ? nl2br($message) : '-') . "</p>
    <p><b>Submitted At:</b> " . date('Y-m-d H:i:s') . "</p>
";

$result = sendMail("info.thefarmersfactory@gmail.com", "Valluvam B2B Team", "New B2B Enquiry - {$full_name}", $body, $email, $full_name);

if ($result['ok']) {
    echo json_encode(['status' => 'success', 'message' => 'Thank you! Your enquiry has been sent. Our team will contact you within 24 hrs.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'We could not send your enquiry right now. Please try again or reach us on WhatsApp.']);
}
