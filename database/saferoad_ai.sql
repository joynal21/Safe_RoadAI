CREATE DATABASE IF NOT EXISTS saferoad_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE saferoad_ai;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('citizen','admin','ambulance') NOT NULL DEFAULT 'citizen',
  phone VARCHAR(40) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ambulance_locations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS response_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NULL,
  ambulance_id INT NULL,
  action VARCHAR(120) NOT NULL,
  note TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_report (report_id),
  INDEX idx_logs_ambulance (ambulance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  type VARCHAR(60) NOT NULL,
  area VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS road_accident_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  road_id VARCHAR(80) NOT NULL,
  road_name VARCHAR(160) NOT NULL,
  occurred_at DATETIME NOT NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'demo_history',
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_road_history_road (road_id),
  INDEX idx_road_history_occurred (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password, role, phone) VALUES
('Admin User','admin@saferoad.test','$2y$12$NGmon7kyUOJ2BbHtSNTm4O29HM2gaEn/RKiefIy5t0VgCt3hHz7eG','admin','01700000099'),
('Citizen User','citizen@saferoad.test','$2y$12$/iwTv1ApYr76IiIkHF5p.eP4K1J4NQziFDFvLTP4fxw.GCuKiciiK','citizen','01700000000')
ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password), role=VALUES(role), phone=VALUES(phone);

INSERT INTO ambulance_locations (id, ambulance_name, driver_name, phone, latitude, longitude, status, speed_kmh) VALUES
(1,'Ambulance A-01','Driver Rahim','01700000001',23.8515000,90.4084000,'available',42),
(2,'Ambulance A-02','Driver Karim','01700000002',23.8067000,90.3687000,'available',38),
(3,'Ambulance A-03','Driver Hasan','01700000003',23.7450000,90.3922000,'available',46),
(4,'Ambulance A-04','Driver Nayeem','01700000004',23.7106000,90.4257000,'available',44)
ON DUPLICATE KEY UPDATE ambulance_name=VALUES(ambulance_name), driver_name=VALUES(driver_name), phone=VALUES(phone), latitude=VALUES(latitude), longitude=VALUES(longitude), status=VALUES(status), speed_kmh=VALUES(speed_kmh);

INSERT INTO emergency_contacts (service_name, phone, type, area) VALUES
('Demo Emergency Control Room','999','National Emergency','Bangladesh'),
('Dhaka Medical Ambulance Unit','01711111111','Ambulance','Dhaka'),
('Traffic Police Demo Desk','01722222222','Police','Dhaka'),
('Fire Service Demo Desk','01733333333','Fire Service','Dhaka');
