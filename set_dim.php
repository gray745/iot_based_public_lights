<?php
// set_dim.php
// Admin sends a dim level to a specific device.
// Saves dim_level to devices table. Arduino polls get_command.php and reads it.
// Does NOT touch existing ON/OFF command logic.

header('Content-Type: application/json');
include 'db.php';
include 'sms_helper.php';

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

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('ALERT_EMAIL_TO',   'officialgray09@gmail.com');
define('ALERT_EMAIL_FROM', 'officialgray09@gmail.com');
define('SMTP_PASSWORD',    'vkyv fyhf neor ptfv');
define('HTTPSMS_API_KEY',  'uk_j17XWYWsKXbyFOYxsyPFRrRdRDLO4bpqkxT057E9gOQE8jNBrPqSVyXnjFoZ1EUp');
define('SMS_FROM',         '+250723593648');
define('SMS_TO',           '+250784910745');

// Allowed dim levels → mapped to PWM duty cycle (0–255)
$dimMap = [
    'DIM_LOW'    => 64,   // 25% brightness
    'DIM_MEDIUM' => 128,  // 50% brightness
    'DIM_HIGH'   => 255,  // 100% brightness (full ON)
];

// --- READ INPUT ---
$input = file_get_contents('php://input');
$data  = $_POST;
if ($input && empty($_POST)) {
    $json = json_decode($input, true);
    if ($json) $data = $json;
}

$device_id = trim($data['device_id'] ?? '');
$dim_level = strtoupper(trim($data['dim_level'] ?? ''));

if (!$device_id || !array_key_exists($dim_level, $dimMap)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'device_id and dim_level (DIM_LOW, DIM_MEDIUM, DIM_HIGH) required']);
    exit;
}

$pwm_value = $dimMap[$dim_level];

// --- ENSURE DEVICE EXISTS ---
$stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ?");
$stmt->execute([$device_id]);
if (!$stmt->fetch()) {
    $conn->prepare("INSERT INTO devices (device_id, command, dim_level, dim_pwm) VALUES (?, 'ON', ?, ?)")
         ->execute([$device_id, $dim_level, $pwm_value]);
} else {
    // Save dim level — also force command to ON so relay is active when dimming
    $conn->prepare("UPDATE devices SET dim_level = ?, dim_pwm = ?, command = 'ON', command_sent_at = NOW() WHERE device_id = ?")
         ->execute([$dim_level, $pwm_value, $device_id]);
}

// --- LOG ACTION ---
$msg = "Admin set $device_id to $dim_level (PWM $pwm_value) via dashboard.";
$conn->prepare("INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)")
     ->execute([$device_id, "ADMIN_DIM_$dim_level", $msg, ALERT_EMAIL_TO]);

// --- RESPOND IMMEDIATELY ---
echo json_encode([
    'status'    => 'ok',
    'device_id' => $device_id,
    'dim_level' => $dim_level,
    'pwm_value' => $pwm_value,
]);

// --- FLUSH TO BROWSER ---
if (ob_get_level() > 0) ob_end_flush();
flush();
$conn = null;

// --- EMAIL IN BACKGROUND ---
$labels = ['DIM_LOW' => 'Low (25%)', 'DIM_MEDIUM' => 'Medium (50%)', 'DIM_HIGH' => 'High (100%)'];
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = ALERT_EMAIL_FROM;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom(ALERT_EMAIL_FROM, 'Streetlight Monitor');
    $mail->addAddress(ALERT_EMAIL_TO, 'System Admin');
    $mail->isHTML(true);
    $mail->Subject = "Streetlight $device_id dimmed to {$labels[$dim_level]} by Admin";
    $mail->Body    = "
        <h3>Dimming Control Action</h3>
        <p>Device <b>$device_id</b> brightness set to <b>{$labels[$dim_level]}</b> (PWM: $pwm_value / 255).</p>
        <p>Time: " . date('Y-m-d H:i:s') . "</p>
    ";
    $mail->send();
} catch (Exception $e) { /* silent */ }

// --- SMS IN BACKGROUND ---
$smsBody = "Streetlight $device_id dimmed to {$labels[$dim_level]} by Admin at " . date('H:i:s');
sendKSMS(SMS_TO, $smsBody);
?>
