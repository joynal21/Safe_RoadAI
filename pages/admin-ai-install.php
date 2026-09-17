<?php
require_once __DIR__ . "/../includes/admin-check.php";
require_once __DIR__ . "/../Config/db.php";

$checks = [];
foreach (['users', 'reports', 'ambulance_locations'] as $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $checks[$table] = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SafeRoad Installer</title>
    <style>
        body { font-family:Arial,sans-serif; background:#f4f6f9; padding:30px; }
        .box { max-width:850px; margin:auto; background:#fff; padding:25px; border-radius:14px; box-shadow:0 4px 15px rgba(0,0,0,.08); }
        .ok { background:#d1e7dd; color:#0f5132; padding:12px; border-radius:8px; margin-bottom:12px; }
        a { display:inline-block; margin:8px 8px 0 0; background:#0d6efd; color:white; padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:bold; }
        li { margin:8px 0; }
    </style>
</head>
<body>
<div class="box">
    <h1>SafeRoad AI Database Check</h1>
    <div class="ok">Core database tables are auto-created by <code>Config/db.php</code>.</div>
    <ul>
        <?php foreach ($checks as $table => $ok): ?>
            <li><?php echo htmlspecialchars($table); ?>: <strong><?php echo $ok ? 'OK' : 'Missing'; ?></strong></li>
        <?php endforeach; ?>
    </ul>
    <a href="admin-dashboard.php">Admin Dashboard</a>
    <a href="admin-reports.php">Admin Reports</a>
    <a href="submit-report.php">Submit Test Report</a>
</div>
</body>
</html>
