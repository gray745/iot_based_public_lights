<?php
// insert_data.php
// Receives sensor data from Arduino (ESP32) every ~10 seconds.
// Detects: FAILED (bulb dead), OVERCURRENT (short/fire), ON, OFF.
// Sends email + SMS alert on status change, with throttle to prevent spam.

include 'db.php';
include 'sms_helper.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─── CONFIG ───────────────────────────────────────────────────────────────────
define('ALERT_EMAIL_TO',    'officialgray09@gmail.com');
define('ALERT_EMAIL_FROM',  'officialgray09@gmail.com');
define('SMTP_PASSWORD',     'vkyv fyhf neor ptfv');

// Sensor thresholds
define('CURRENT_THRESHOLD', 0.10);  // A — below this = lamp not drawing power
define('OVERCURRENT_MAX',   2.50);  // A — above this = short circuit / fire risk

// Alert throttle — minimum minutes between repeated alerts per device + type
define('ALERT_THROTTLE_MIN', 10);

// ─── READ POST DATA ───────────────────────────────────────────────────────────
$device_id      = trim($_POST['device_id']  ?? '');
$current        = floatval($_POST['current']   ?? 0);
$brightness     = intval($_POST['ldr']         ?? 0);
$relay_state    = strtoupper(trim($_POST['relay']  ?? 'ON'));
$arduino_status = strtoupper(trim($_POST['status'] ?? ''));
$latitude       = $_POST['latitude']  ?? null;
$longitude      = $_POST['langitude'] ?? null;   // typo kept to match Arduino sketch

if (!$device_id) {
    http_response_code(400);
    echo "Missing device_id";
    exit;
}

$absCurrent = abs($current);

// Normalize — always work with positive current value
$current = $absCurrent;

// ─── STATUS LOGIC ─────────────────────────────────────────────────────────────
// Trust Arduino's explicit status always — it has full sensor context.
// Only re-derive if Arduino sends empty status.

if (in_array($arduino_status, ['OVERCURRENT', 'FAILED', 'ON', 'OFF'])) {
    $status = $arduino_status;
} elseif ($relay_state === 'ON') {
    if ($absCurrent >= OVERCURRENT_MAX) {
        $status = 'OVERCURRENT';
    } elseif ($absCurrent > CURRENT_THRESHOLD) {
        $status = 'ON';
    } else {
        $status = 'FAILED';
    }
} else {
    $status = 'OFF';              // 💤 intentionally off
}

// ─── STORE SENSOR READING ─────────────────────────────────────────────────────
$conn->prepare(
    "INSERT INTO street_light_data (device_id, current, brightness, status) VALUES (?, ?, ?, ?)"
)->execute([$device_id, $current, $brightness, $status]);
echo "Data stored. Status: $status<br>";

// ─── ALERT LOGIC ──────────────────────────────────────────────────────────────

// ── Recovery detection — was device FAILED/OVERCURRENT before? Now ON? ───────
if ($status === 'ON') {
    // Check if the last recorded status was a failure
    $prevStmt = $conn->prepare("
        SELECT status FROM street_light_data
        WHERE device_id = ?
        ORDER BY timestamp DESC LIMIT 2
    ");
    $prevStmt->execute([$device_id]);
    $rows = $prevStmt->fetchAll(PDO::FETCH_ASSOC);
    // rows[0] is the one just inserted (ON), rows[1] is the one before
    $prevStatus = $rows[1]['status'] ?? '';
    if (in_array($prevStatus, ['FAILED', 'OVERCURRENT'])) {
        $recoveryMsg = "✅ $device_id lamp RECOVERED. Back to normal operation. Current={$current}A, Brightness={$brightness}.";
        $conn->prepare(
            "INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)"
        )->execute([$device_id, 'LAMP_RECOVERED', $recoveryMsg, ALERT_EMAIL_TO]);
        echo "✅ Recovery logged.<br>";
    }
}

// Only alert on FAILED or OVERCURRENT.
// Throttle: skip if same alert type was sent for this device within ALERT_THROTTLE_MIN minutes.

if (in_array($status, ['FAILED', 'OVERCURRENT'])) {

    $alertType = ($status === 'OVERCURRENT') ? 'OVERCURRENT' : 'LIGHT_FAILURE';

    // Check throttle
    $throttleStmt = $conn->prepare("
        SELECT sent_at FROM alerts
        WHERE device_id = ? AND alert_type = ?
        ORDER BY sent_at DESC LIMIT 1
    ");
    $throttleStmt->execute([$device_id, $alertType]);
    $lastAlert = $throttleStmt->fetch(PDO::FETCH_ASSOC);

    $minutesSinceLast = $lastAlert
        ? (time() - strtotime($lastAlert['sent_at'])) / 60
        : PHP_INT_MAX;

    if ($minutesSinceLast >= ALERT_THROTTLE_MIN) {

        // ── Build message content ────────────────────────────────────────────
        $map_link = ($latitude && $longitude)
            ? "https://www.google.com/maps?q={$latitude},{$longitude}"
            : null;
        $mapHtml = $map_link
            ? "<p><b>📍 Location:</b> <a href='$map_link'>View on Google Maps</a></p>"
            : "<p><b>📍 Location:</b> GPS not available</p>";
        $mapSms  = $map_link ? " Map: $map_link" : "";
        $time    = date('Y-m-d H:i:s');

        if ($status === 'OVERCURRENT') {
            $subject   = "🔥 URGENT: Streetlight $device_id OVERCURRENT — fire/short circuit risk";
            $emailBody = "
                <h2 style='color:red;'>🔥 OVERCURRENT ALERT — Immediate Action Required</h2>
                <p>Device <b>$device_id</b> detected dangerously high current.</p>
                <p><b>⚡ Current reading:</b> {$current} A &nbsp;(safe max: " . OVERCURRENT_MAX . " A)</p>
                <p><b>🔴 Action taken:</b> Relay automatically cut OFF by ESP32 to prevent fire.</p>
                $mapHtml
                <p><b>🕐 Time:</b> $time</p>
                <hr>
                <p style='color:red;'><b>Possible causes:</b> short circuit, damaged wiring, overloaded circuit, fire.</p>
                <p><b>⚠️ Do NOT restore power until physically inspected by a technician.</b></p>
            ";
            $smsMsg = "URGENT: $device_id OVERCURRENT {$current}A. Relay auto-OFF. Possible short/fire.$mapSms Inspect ASAP.";
        } else {
            $subject   = "⚠️ ALERT: Streetlight $device_id NOT lighting — bulb/fuse failure";
            $emailBody = "
                <h2 style='color:orange;'>⚠️ Streetlight Failure Detected</h2>
                <p>Device <b>$device_id</b> — relay is ON but <b>the lamp is not lighting</b>.</p>
                <p><b>⚡ Current reading:</b> {$current} A &nbsp;(expected &gt; " . CURRENT_THRESHOLD . " A)</p>
                <p><b>💡 Brightness (LDR):</b> $brightness / 255</p>
                $mapHtml
                <p><b>🕐 Time:</b> $time</p>
                <hr>
                <p><b>Possible causes:</b></p>
                <ul>
                    <li>Bulb burned out or broken</li>
                    <li>Fuse blown inside the fixture</li>
                    <li>Wiring disconnected or corroded connector</li>
                    <li>Lamp driver/ballast failure (LED/fluorescent)</li>
                </ul>
                <p>Please dispatch a technician to inspect and replace the faulty component.</p>
            ";
            $smsMsg = "ALERT: $device_id lamp FAILED. Relay ON but current={$current}A (too low). Bulb/fuse/wiring issue.$mapSms";
        }

        // ── Send Email ───────────────────────────────────────────────────────
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
            $mail->Subject = $subject;
            $mail->Body    = $emailBody;
            $mail->AltBody = strip_tags(str_replace(['<br>','<p>','</p>'], "\n", $emailBody));
            $mail->send();
            echo "✅ Alert email sent.<br>";
        } catch (Exception $e) {
            echo "❌ Email failed: {$mail->ErrorInfo}<br>";
        }

        // ── Send SMS via Arkesel ─────────────────────────────────────────────
        $sms = sendKSMS(SMS_TO, $smsMsg);
        if ($sms['sent']) {
            echo "✅ Alert SMS sent.<br>";
        } else {
            echo "❌ SMS failed (HTTP {$sms['code']}): {$sms['error']} — {$sms['response']}<br>";
        }

        // ── Log to DB ────────────────────────────────────────────────────────
        $conn->prepare(
            "INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)"
        )->execute([$device_id, $alertType, $smsMsg, ALERT_EMAIL_TO]);

        $conn->prepare(
            "INSERT INTO devices (device_id, last_alert) VALUES (?, NOW())
             ON CONFLICT (device_id) DO UPDATE SET last_alert = NOW()"
        )->execute([$device_id]);

    } else {
        $remaining = round(ALERT_THROTTLE_MIN - $minutesSinceLast);
        echo "⏳ Throttled — next {$alertType} alert for {$device_id} in ~{$remaining} min.<br>";
    }
}

$conn = null;
?>
