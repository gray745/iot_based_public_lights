<?php
// get_command.php
// Arduino polls this every second to get its relay command.
// Command stays in DB permanently (not cleared) so relay state survives reboots.
// 'AUTO' means no admin override — Arduino uses its own logic.

header('Content-Type: application/json');
include 'db.php';

$device_id = trim($_GET['device_id'] ?? '');

if (!$device_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'device_id required']);
    exit;
}

$stmt = $conn->prepare("SELECT command, dim_level, dim_pwm FROM devices WHERE device_id = ?");
$stmt->execute([$device_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    // Auto-register device with default ON + full brightness
    $conn->prepare("INSERT INTO devices (device_id, command, dim_level, dim_pwm) VALUES (?, 'ON', 'DIM_HIGH', 255)")
         ->execute([$device_id]);
    echo json_encode(['status' => 'ok', 'command' => 'ON', 'dim_pwm' => 255]);
    exit;
}

$command  = $row['command']  ?? 'ON';
$dim_pwm  = intval($row['dim_pwm'] ?? 255);

// Return both command and pwm value so Arduino can apply correct brightness
echo json_encode(['status' => 'ok', 'command' => $command, 'dim_pwm' => $dim_pwm]);
