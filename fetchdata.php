<?php
header('Content-Type: application/json');
include 'db.php';

try {
    $stmt = $conn->prepare("
        SELECT s1.id, s1.device_id, s1.current, s1.brightness, s1.timestamp, s1.status
        FROM street_light_data s1
        INNER JOIN (
            SELECT device_id, MAX(timestamp) AS max_time
            FROM street_light_data
            GROUP BY device_id
        ) s2
        ON s1.device_id = s2.device_id AND s1.timestamp = s2.max_time
        ORDER BY s1.device_id ASC
    ");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($devices);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
