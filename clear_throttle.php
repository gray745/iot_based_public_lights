<?php
// clear_throttle.php
// Clears old LIGHT_FAILURE and OVERCURRENT alert logs for real devices
// so the throttle doesn't block genuine alerts.
// Run once, then this file deletes itself.

include 'db.php';

$stmt = $conn->prepare("
    DELETE FROM alerts
    WHERE alert_type IN ('LIGHT_FAILURE', 'OVERCURRENT')
      AND device_id NOT LIKE 'TEST_%'
");
$stmt->execute();
$deleted = $stmt->rowCount();
$conn = null;

unlink(__FILE__);

echo "✅ Cleared $deleted old failure alert(s). Real device alerts are now unthrottled.\nThis file has been deleted.";
?>
