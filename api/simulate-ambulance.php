<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit();
}
require_once __DIR__ . "/../Config/db.php";

function sr_distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$lockDir = dirname(__DIR__) . '/assets/uploads';
if (!is_dir($lockDir)) mkdir($lockDir, 0775, true);
$lockPath = $lockDir . '/simulation.lock';
$stampPath = $lockDir . '/simulation.lasttick';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo json_encode(['success' => true, 'moved' => 0, 'arrived' => 0, 'message' => 'Another simulation tick is already running.']);
    exit();
}

try {
    $now = microtime(true);
    $last = is_file($stampPath) ? (float)file_get_contents($stampPath) : 0.0;
    $manual = isset($_GET['manual']) || isset($_POST['manual']);
    if (!$manual && ($now - $last) < 1.8) {
        echo json_encode(['success' => true, 'moved' => 0, 'arrived' => 0, 'throttled' => true]);
        exit();
    }
    file_put_contents($stampPath, (string)$now, LOCK_EX);

    $moved = 0;
    $arrived = 0;
    $result = $conn->query("SELECT * FROM ambulance_locations WHERE status='on_the_way' AND destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL");
    while ($amb = $result->fetch_assoc()) {
        $id = (int)$amb['id'];
        $lat = (float)$amb['latitude'];
        $lng = (float)$amb['longitude'];
        $destLat = (float)$amb['destination_latitude'];
        $destLng = (float)$amb['destination_longitude'];
        $dist = sr_distance_m($lat, $lng, $destLat, $destLng);

        if ($dist <= 55) {
            $stmt = $conn->prepare("UPDATE ambulance_locations SET latitude=?, longitude=?, status='arrived', last_ping=NOW() WHERE id=?");
            $stmt->bind_param('ddi', $destLat, $destLng, $id);
            $stmt->execute();
            $arrived++;
            if (!empty($amb['assigned_report_id'])) {
                $rid = (int)$amb['assigned_report_id'];
                $conn->query("UPDATE reports SET status='Ambulance Sent' WHERE id=$rid AND status<>'Resolved'");
                $note = $conn->real_escape_string($amb['ambulance_name'] . ' arrived near the reported accident location.');
                $conn->query("INSERT INTO response_logs (report_id, ambulance_id, action, note) VALUES ($rid,$id,'Ambulance Arrived','$note')");
            }
            continue;
        }

        $factor = 0.32; // large enough to make movement visible during class demo
        $newLat = $lat + (($destLat - $lat) * $factor);
        $newLng = $lng + (($destLng - $lng) * $factor);
        $heading = (int)round(rad2deg(atan2($destLng - $lng, $destLat - $lat)));
        if ($heading < 0) $heading += 360;
        $stmt = $conn->prepare("UPDATE ambulance_locations SET latitude=?, longitude=?, heading_degree=?, last_ping=NOW() WHERE id=? AND status='on_the_way'");
        $stmt->bind_param('ddii', $newLat, $newLng, $heading, $id);
        $stmt->execute();
        $moved += $stmt->affected_rows > 0 ? 1 : 0;
    }
    echo json_encode(['success' => true, 'moved' => $moved, 'arrived' => $arrived]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($lock) && $lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
?>
