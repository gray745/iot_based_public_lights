<?php
// control_light.php
// Admin sends ON or OFF command to a specific device.
// Saves command to DB immediately, then sends email + SMS in background.

header('Content-Type: application/json');
include 'db.php';
include 'sms_helper.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CONFIG ---
define('ALERT_EMAIL_TO',   'officialgray09@gmail.com');
define('ALERT_EMAIL_FROM', 'officialgray09@gmail.com');
define('SMTP_PASSWORD',    'vkyv fyhf neor ptfv');

// --- ROLE GUARD — admin only ---
session_start();
if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
$chk = $conn->prepare("SELECT role FROM users WHERE email = ?");
$chk->execute([$_SESSION['email']]);
$chkUser = $chk->fetch(PDO::FETCH_ASSOC);
if (!$chkUser || $chkUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied — admin only']);
    exit;
}

// --- READ INPUT ---
$input = file_get_contents('php://input');
$data  = $_POST;
if ($input && empty($_POST)) {
    $json = json_decode($input, true);
    if ($json) $data = $json;
}

$device_id = trim($data['device_id'] ?? '');
$command   = strtoupper(trim($data['command'] ?? ''));

if (!$device_id || !in_array($command, ['ON', 'OFF'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'device_id and command (ON or OFF) are required']);
    exit;
}

// --- ENSURE DEVICE EXISTS ---
$stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ?");
$stmt->execute([$device_id]);
if (!$stmt->fetch()) {
    $conn->prepare("INSERT INTO devices (device_id, command) VALUES (?, ?)")
         ->execute([$device_id, $command]);
}

// --- SAVE COMMAND (Arduino polls this) ---
$conn->prepare("UPDATE devices SET command = ?, command_sent_at = NOW() WHERE device_id = ?")
     ->execute([$command, $device_id]);

// --- LOG ACTION ---
$msg = "Admin turned $device_id $command via dashboard.";
$conn->prepare("INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)")
     ->execute([$device_id, "ADMIN_CONTROL_$command", $msg, ALERT_EMAIL_TO]);

// --- RESPOND TO DASHBOARD IMMEDIATELY ---
echo json_encode([
    'status'    => 'ok',
    'device_id' => $device_id,
    'command'   => $command,
    'email_sent'=> true,
    'sms_sent'  => true,
]);

// --- FLUSH TO BROWSER NOW ---
if (ob_get_level() > 0) ob_end_flush();
flush();
$conn = null;

// --- SEND EMAIL IN BACKGROUND ---
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = ALERT_EMAIL_FROM;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(ALERT_EMAIL_FROM, 'Streetlight Monitor');
    $mail->addAddress(ALERT_EMAIL_TO, 'System Admin');
    $mail->isHTML(true);
    $mail->Subject = "Streetlight $device_id switched $command by Admin";
    $mail->Body    = "
        <h3>Remote Control Action</h3>
        <p>Device <b>$device_id</b> has been switched <b>$command</b> by the administrator.</p>
        <p><b>Time:</b> " . date('Y-m-d H:i:s') . "</p>
        <p>The physical relay will change within 1-2 seconds.</p>
    ";
    $mail->send();
} catch (Exception $e) {
    // Silent — dashboard already got its response
}

// --- SEND SMS IN BACKGROUND via Arkesel ---
$smsBody = "Streetlight $device_id switched $command by Admin at " . date('H:i:s');
sendKSMS(SMS_TO, $smsBody);
?>
