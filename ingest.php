<?php
// ingest.php
header('Content-Type: application/json');
require 'db.php';
require 'send_sms.php';

// CONFIG (adjust thresholds)
define('BRIGHTNESS_THRESHOLD', 300);   // LDR raw threshold (lower -> darker) adjust to your LDR scale
define('VOLTAGE_MIN', 3.0);            // if voltage above this but brightness suggests off => failure
define('ALERT_THROTTLE_MINUTES', 60);  // do not send SMS more often than this per device

// Read POST (both JSON and form)
$input = file_get_contents('php://input');
$data = $_POST;
if ($input && empty($_POST)) {
    $json = json_decode($input, true);
    if ($json) $data = $json;
}

$device_id = isset($data['device_id']) ? trim($data['device_id']) : null;
$voltage = isset($data['voltage']) ? floatval($data['voltage']) : null;
$brightness = isset($data['brightness']) ? floatval($data['brightness']) : null;

if (!$device_id || $voltage === null || $brightness === null) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Missing device_id, voltage or brightness']);
    exit;
}

// determine status (simple logic):
// If brightness indicates dark (brightness < threshold) and voltage > V_MIN => ON expected, but if brightness shows HIGH (or above threshold?) ... 
// We'll assume LDR returns HIGH value when light present. Adjust according to your sensor mapping.
// For this example, I'll assume: brightness > BRIGHTNESS_THRESHOLD => light is ON.
$light_on = ($brightness > BRIGHTNESS_THRESHOLD);

// set status
$status = $light_on ? 'ON' : 'OFF';

// insert into sensor data
$stmt = $pdo->prepare("INSERT INTO street_light_data (device_id, voltage, brightness, status) VALUES (?, ?, ?, ?)");
$stmt->execute([$device_id, $voltage, $brightness, $status]);

// ensure device exists in devices table (optional)
$stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device) {
    // create device with NULL phone by default - admin can update phone later
    $insert = $pdo->prepare("INSERT INTO devices (device_id) VALUES (?)");
    $insert->execute([$device_id]);
    $device = ['device_id' => $device_id, 'phone_number' => null, 'last_alert' => null];
}

// FAILURE DETECTION RULE:
// It's night time (brightness low) we expect light to be ON. But since we don't know day/night here,
// we use a simpler rule: if brightness is low (<= threshold) AND voltage > VOLTAGE_MIN then the lamp is failing to light.
// NOTE: Adjust BRIGHTNESS_THRESHOLD based on your LDR mapping.
$should_alert = false;
$alert_message = '';
if (!$light_on && $voltage > VOLTAGE_MIN) {
    $should_alert = true;
    $alert_message = "ALERT: Device {$device_id} appears OFF while voltage is present (V={$voltage}, LDR={$brightness}).";
}

// Throttle alerts to avoid SMS spam
if ($should_alert && !empty($device['phone_number'])) {
    $last_alert = $device['last_alert']; // may be null
    $can_send = true;
    if ($last_alert) {
        $last_ts = strtotime($last_alert);
        $diff_minutes = (time() - $last_ts) / 60;
        if ($diff_minutes < ALERT_THROTTLE_MINUTES) $can_send = false;
    }

    if ($can_send) {
        $to = $device['phone_number'];
        $sms_result = send_sms_twilio($to, $alert_message);
        if ($sms_result['success']) {
            // update last_alert in devices table
            $upd = $pdo->prepare("UPDATE devices SET last_alert = NOW() WHERE device_id = ?");
            $upd->execute([$device_id]);

            // log the alert
            $log = $pdo->prepare("INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)");
            $log->execute([$device_id, 'LIGHT_FAILURE', $alert_message, $to]);

            echo json_encode(['status'=>'ok','message'=>'Data saved, alert sent','sms' => $sms_result]);
            exit;
        } else {
            // log failed sms attempt too
            $log = $pdo->prepare("INSERT INTO alerts (device_id, alert_type, message, sent_to) VALUES (?, ?, ?, ?)");
            $log->execute([$device_id, 'LIGHT_FAILURE_SMS_FAILED', $alert_message, $device['phone_number']]);
            echo json_encode(['status'=>'ok','message'=>'Data saved, sms failed','sms_error'=>$sms_result]);
            exit;
        }
    } else {
        echo json_encode(['status'=>'ok','message'=>'Data saved, alert throttled (recent alert exists)']);
        exit;
    }
}

echo json_encode(['status'=>'ok','message'=>'Data saved','device'=>$device_id,'voltage'=>$voltage,'brightness'=>$brightness,'status'=>$status]);
