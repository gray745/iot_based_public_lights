<?php
header('Content-Type: application/json');
include 'db.php';

$stmt = $conn->prepare("SELECT device_id, alert_type, message, sent_to, sent_at FROM alerts ORDER BY sent_at DESC LIMIT 20");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['status'=>'ok','data'=>$rows]);
?>
