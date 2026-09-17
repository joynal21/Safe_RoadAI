<?php
header('Content-Type: application/json');
require_once __DIR__ . "/../Config/db.php";

$ambulanceId = (int)($_POST['ambulance_id'] ?? 1);
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$status = trim($_POST['status'] ?? 'available');
$allowedStatus = ['available', 'on_the_way', 'arrived', 'offline'];

if ($ambulanceId <= 0 || $latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(["success" => false, "message" => "Invalid location data."]); exit();
}
if (!in_array($status, $allowedStatus, true)) $status = 'available';

try {
    $name = "Ambulance A-" . str_pad((string)$ambulanceId, 2, '0', STR_PAD_LEFT);
    $query = "INSERT INTO ambulance_locations (id, ambulance_name, latitude, longitude, status, last_ping, last_updated)
        VALUES (?, ?, ?, ?, ?, NOW(), CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude), longitude = VALUES(longitude), status = VALUES(status), last_ping = NOW(), last_updated = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdds", $ambulanceId, $name, $latitude, $longitude, $status);
    $stmt->execute();
    echo json_encode(["success" => true, "message" => "Ambulance location updated."]);
} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "Update failed: " . $e->getMessage()]);
}
?>
