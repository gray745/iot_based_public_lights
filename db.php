<?php
$host = "127.0.0.1";
$port = "5432";
$dbname = "iot_based_public_lights";
$user = "postgres";
$pass = "Safari@!12";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
