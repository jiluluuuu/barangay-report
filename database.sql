CREATE DATABASE IF NOT EXISTS barrier_report_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barrier_report_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) DEFAULT 'Staff'
);
-- default login: admin / admin123 (hash is generated on first login, then change it)
INSERT IGNORE INTO users (username, password_hash, full_name) VALUES ('admin','SETUP','Staff');

-- Barangay offices / responders who act on concerns
CREATE TABLE IF NOT EXISTS teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  status ENUM('Available','Busy','Resting') DEFAULT 'Available',
  note VARCHAR(80) DEFAULT ''
);
INSERT INTO teams (id,name,status,note) VALUES
 (1,'Maintenance Crew','Available',''),
 (2,'Engineering Office','Busy',''),
 (3,'Public Safety / Tanod','Available',''),
 (4,'Utilities & Works','Resting','Shift Ends'),
 (5,'PWD Affairs Desk','Available',''),
 (6,'Barangay Secretary / Admin Desk','Available',''),
 (7,'Barangay Health Workers','Available',''),
 (8,'Punong Barangay Office','Available','')
ON DUPLICATE KEY UPDATE name=VALUES(name);

CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(24) UNIQUE,
  category ENUM('Physical','Communication','Service','Other') NOT NULL DEFAULT 'Physical',
  type VARCHAR(70) NOT NULL,
  description TEXT NULL,
  location VARCHAR(160) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  pwd_related TINYINT(1) NOT NULL DEFAULT 0,
  reported_for VARCHAR(12) NULL,            -- Myself / Someone else
  affected_groups VARCHAR(200) NULL,        -- comma list of disability / group types
  urgency ENUM('Low','Medium','High') DEFAULT 'Medium',
  status ENUM('Received','Acknowledged','Action Ongoing','Resolved') DEFAULT 'Received',
  reporter_name VARCHAR(80) DEFAULT 'Anonymous',
  contact VARCHAR(80) NULL,                 -- optional, for follow-up only
  photo VARCHAR(255) NULL,
  team_id INT NULL,
  target_date DATE NULL,                    -- when the barangay expects to act
  acknowledged_at DATETIME NULL,            -- when the concern was first "heard"
  feedback_rating ENUM('Yes','No') NULL,    -- reporter: was it really addressed?
  feedback_note VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS report_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NULL,
  message VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
