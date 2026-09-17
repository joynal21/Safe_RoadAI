<?php
require_once __DIR__ . '/road-registry.php';
function saferoadTableExists(mysqli $conn, string $tableName): bool {
    $sql = "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function saferoadColumnExists(mysqli $conn, string $tableName, string $columnName): bool {
    $sql = "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function saferoadAddColumnIfMissing(mysqli $conn, string $tableName, string $columnName, string $definition): void {
    if (!saferoadColumnExists($conn, $tableName, $columnName)) {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN `$columnName` $definition");
    }
}

function saferoadEnsureCoreTables(mysqli $conn): void {
    // Keep deployment fast: run the expensive migration/seed work only when the schema version changes.
    $schemaVersion = '2026-09-17-render-1';
    $conn->query("CREATE TABLE IF NOT EXISTS app_meta (
        meta_key VARCHAR(120) PRIMARY KEY,
        meta_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $versionStmt = $conn->prepare("SELECT meta_value FROM app_meta WHERE meta_key='schema_version' LIMIT 1");
    $versionStmt->execute();
    $versionRow = $versionStmt->get_result()->fetch_assoc();
    if (($versionRow['meta_value'] ?? '') === $schemaVersion) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('citizen','admin','ambulance') NOT NULL DEFAULT 'citizen',
        phone VARCHAR(40) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    saferoadAddColumnIfMissing($conn, 'users', 'phone', "VARCHAR(40) NULL");

    $conn->query("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        title VARCHAR(255) NOT NULL,
        road_name VARCHAR(255) NULL,
        location VARCHAR(255) NOT NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        description TEXT NOT NULL,
        severity VARCHAR(50) DEFAULT 'Medium',
        emergency_type VARCHAR(80) DEFAULT 'Road Accident',
        reporter_phone VARCHAR(40) NULL,
        people_injured INT DEFAULT 0,
        vehicles_involved VARCHAR(255) NULL,
        nearest_landmark VARCHAR(255) NULL,
        police_required TINYINT(1) DEFAULT 0,
        fire_service_required TINYINT(1) DEFAULT 0,
        image VARCHAR(255) NULL,
        video VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        ai_verdict VARCHAR(50) DEFAULT 'Not Checked',
        ai_score INT DEFAULT 0,
        priority_score INT DEFAULT 0,
        ai_reason TEXT NULL,
        admin_note TEXT NULL,
        assigned_ambulance_id INT NULL,
        ambulance_eta_minutes INT NULL,
        response_started_at DATETIME NULL,
        resolved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reports_user (user_id),
        INDEX idx_reports_status (status),
        INDEX idx_reports_location (latitude, longitude),
        INDEX idx_reports_severity (severity),
        INDEX idx_reports_assigned_ambulance (assigned_ambulance_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    saferoadAddColumnIfMissing($conn, 'reports', 'user_id', "INT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'title', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'road_name', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'location', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'latitude', "DECIMAL(10,7) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'longitude', "DECIMAL(10,7) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'description', "TEXT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'severity', "VARCHAR(50) DEFAULT 'Medium'");
    saferoadAddColumnIfMissing($conn, 'reports', 'emergency_type', "VARCHAR(80) DEFAULT 'Road Accident'");
    saferoadAddColumnIfMissing($conn, 'reports', 'reporter_phone', "VARCHAR(40) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'people_injured', "INT DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'reports', 'vehicles_involved', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'nearest_landmark', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'police_required', "TINYINT(1) DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'reports', 'fire_service_required', "TINYINT(1) DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'reports', 'image', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'video', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'status', "VARCHAR(50) DEFAULT 'Pending'");
    saferoadAddColumnIfMissing($conn, 'reports', 'ai_verdict', "VARCHAR(50) DEFAULT 'Not Checked'");
    saferoadAddColumnIfMissing($conn, 'reports', 'ai_score', "INT DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'reports', 'priority_score', "INT DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'reports', 'ai_reason', "TEXT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'admin_note', "TEXT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'assigned_ambulance_id', "INT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'ambulance_eta_minutes', "INT NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'response_started_at', "DATETIME NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'resolved_at', "DATETIME NULL");
    saferoadAddColumnIfMissing($conn, 'reports', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    saferoadAddColumnIfMissing($conn, 'reports', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $conn->query("CREATE TABLE IF NOT EXISTS ambulance_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ambulance_name VARCHAR(120) NOT NULL,
        driver_name VARCHAR(120) NULL,
        phone VARCHAR(40) NULL,
        latitude DECIMAL(10,7) NOT NULL DEFAULT 23.8103310,
        longitude DECIMAL(10,7) NOT NULL DEFAULT 90.4125210,
        status VARCHAR(50) NOT NULL DEFAULT 'available',
        assigned_report_id INT NULL,
        destination_latitude DECIMAL(10,7) NULL,
        destination_longitude DECIMAL(10,7) NULL,
        speed_kmh INT DEFAULT 40,
        heading_degree INT DEFAULT 0,
        current_mission VARCHAR(255) NULL,
        last_ping DATETIME NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ambulance_status (status),
        INDEX idx_ambulance_report (assigned_report_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'driver_name', "VARCHAR(120) NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'phone', "VARCHAR(40) NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'assigned_report_id', "INT NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'destination_latitude', "DECIMAL(10,7) NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'destination_longitude', "DECIMAL(10,7) NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'speed_kmh', "INT DEFAULT 40");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'heading_degree', "INT DEFAULT 0");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'current_mission', "VARCHAR(255) NULL");
    saferoadAddColumnIfMissing($conn, 'ambulance_locations', 'last_ping', "DATETIME NULL");

    $conn->query("CREATE TABLE IF NOT EXISTS response_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NULL,
        ambulance_id INT NULL,
        action VARCHAR(120) NOT NULL,
        note TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logs_report (report_id),
        INDEX idx_logs_ambulance (ambulance_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS emergency_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(120) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        type VARCHAR(60) NOT NULL,
        area VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


    $conn->query("CREATE TABLE IF NOT EXISTS road_accident_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        road_id VARCHAR(80) NOT NULL,
        road_name VARCHAR(160) NOT NULL,
        occurred_at DATETIME NOT NULL,
        source VARCHAR(80) NOT NULL DEFAULT 'demo_history',
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_road_history_road (road_id),
        INDEX idx_road_history_occurred (occurred_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $adminHash = '$2y$12$NGmon7kyUOJ2BbHtSNTm4O29HM2gaEn/RKiefIy5t0VgCt3hHz7eG'; // admin123
    $citizenHash = '$2y$12$/iwTv1ApYr76IiIkHF5p.eP4K1J4NQziFDFvLTP4fxw.GCuKiciiK'; // user123
    // Seed demo accounts only when missing. Existing passwords are never overwritten on normal requests.
    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
    $name = 'Admin User'; $email = 'admin@saferoad.test'; $hash = $adminHash; $role = 'admin'; $phone = '01700000099';
    $stmt->bind_param('sssss', $name, $email, $hash, $role, $phone);
    $stmt->execute();
    $name = 'Citizen User'; $email = 'citizen@saferoad.test'; $hash = $citizenHash; $role = 'citizen'; $phone = '01700000000';
    $stmt->bind_param('sssss', $name, $email, $hash, $role, $phone);
    $stmt->execute();

    $result = $conn->query("SELECT COUNT(*) AS total FROM ambulance_locations");
    $row = $result->fetch_assoc();
    if ((int)($row['total'] ?? 0) === 0) {
        // Four units are placed in different parts of Dhaka so the nearest-unit
        // dispatch algorithm can be demonstrated from accidents at arbitrary GPS points.
        $conn->query("INSERT INTO ambulance_locations (id, ambulance_name, driver_name, phone, latitude, longitude, status, speed_kmh) VALUES
            (1, 'Ambulance A-01', 'Driver Rahim', '01700000001', 23.8515000, 90.4084000, 'available', 42),
            (2, 'Ambulance A-02', 'Driver Karim', '01700000002', 23.8067000, 90.3687000, 'available', 38),
            (3, 'Ambulance A-03', 'Driver Hasan', '01700000003', 23.7450000, 90.3922000, 'available', 46),
            (4, 'Ambulance A-04', 'Driver Nayeem', '01700000004', 23.7106000, 90.4257000, 'available', 44)");
    } else {
        // Safe migration for users upgrading from the older 3-ambulance version.
        // INSERT IGNORE adds A-04 only when it does not already exist and never
        // resets a running mission on A-01/A-02/A-03.
        $conn->query("INSERT IGNORE INTO ambulance_locations (id, ambulance_name, driver_name, phone, latitude, longitude, status, speed_kmh) VALUES
            (4, 'Ambulance A-04', 'Driver Nayeem', '01700000004', 23.7106000, 90.4257000, 'available', 44)");
    }

    $contacts = $conn->query("SELECT COUNT(*) AS total FROM emergency_contacts")->fetch_assoc();
    if ((int)($contacts['total'] ?? 0) === 0) {
        $conn->query("INSERT INTO emergency_contacts (service_name, phone, type, area) VALUES
            ('Demo Emergency Control Room','999','National Emergency','Bangladesh'),
            ('Dhaka Medical Ambulance Unit','01711111111','Ambulance','Dhaka'),
            ('Traffic Police Demo Desk','01722222222','Police','Dhaka'),
            ('Fire Service Demo Desk','01733333333','Fire Service','Dhaka')");
    }


    // Keep a rolling-looking 30-day sample history for every registered Dhaka
    // Division corridor. This is upgrade-safe: existing five-road installations
    // keep their data and only missing road IDs receive demo history rows.
    $registeredRoads = srDhakaDivisionRoadRegistry();
    $historyCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM road_accident_history WHERE road_id = ? AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $historyStmt = $conn->prepare("INSERT INTO road_accident_history (road_id, road_name, occurred_at, source, note) VALUES (?, ?, ?, 'demo_history', ?)");
    foreach ($registeredRoads as $road) {
        $roadId = (string)$road['id'];
        $roadName = (string)$road['road_name'];
        $targetCount = max(0, (int)($road['baseline_accidents'] ?? 0));
        $historyCountStmt->bind_param('s', $roadId);
        $historyCountStmt->execute();
        $existingRow = $historyCountStmt->get_result()->fetch_assoc();
        $existingCount = (int)($existingRow['total'] ?? 0);
        if ($existingCount > 0 || $targetCount < 1) continue;

        for ($i = 0; $i < $targetCount; $i++) {
            $daysAgo = 1 + (($i * 3 + strlen($roadId)) % 27);
            $occurredAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -" . (($i * 47) % 720) . " minutes"));
            $note = 'Seeded Dhaka Division road-risk event for academic analytics.';
            $historyStmt->bind_param('ssss', $roadId, $roadName, $occurredAt, $note);
            $historyStmt->execute();
        }
    }

    $versionSave = $conn->prepare("INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
    $versionSave->bind_param('s', $schemaVersion);
    $versionSave->execute();
}
?>