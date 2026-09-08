<?php
// get_history.php
// Returns last 20 brightness + current readings for a device, for the history chart.

header('Content-Type: application/json');
include 'db.php';

$device_id = trim($_GET['device_id'] ?? '');
if (!$device_id) {
    echo json_encode(['status' => 'error', 'message' => 'device_id required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT current, brightness, status, timestamp
    FROM street_light_data
    WHERE device_id = ?
    ORDER BY timestamp DESC
    LIMIT 20
");
$stmt->execute([$device_id]);
$rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode(['status' => 'ok', 'data' => $rows]);
?>
