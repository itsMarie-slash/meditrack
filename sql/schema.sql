-- =========================================================================
-- MediTrack: Senior Citizen Healthcare & Medicine Monitoring System
-- Barangay New Bulatukan Health Center
--
-- Run this file once in phpMyAdmin (or `mysql -u root -p < schema.sql`)
-- against an empty database, e.g.:
--   CREATE DATABASE meditrack CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- users: BHW / Midwife / IPHO staff accounts. Note per project scope,
-- the "bhw" role is treated as the system's full-access/admin role.
-- -------------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('bhw', 'midwife', 'ipho') NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- senior_citizens: beneficiary directory
-- -------------------------------------------------------------------------
CREATE TABLE senior_citizens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    birthdate DATE NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    purok_zone VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    contact_number VARCHAR(15) NULL,
    senior_id_number VARCHAR(50) NOT NULL UNIQUE,
    maintenance_medicine VARCHAR(255) NULL,
    health_conditions TEXT NULL,
    allergies TEXT NULL,
    emergency_contact_name VARCHAR(150) NULL,
    emergency_contact_number VARCHAR(15) NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_senior_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_senior_name (full_name),
    INDEX idx_senior_purok (purok_zone),
    INDEX idx_senior_status (status)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- medicines: inventory catalog
-- -------------------------------------------------------------------------
CREATE TABLE medicines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(50) NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_threshold INT NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    date_received DATE NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_medicine_name (name)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- inventory_transactions: audit trail for every stock change
-- -------------------------------------------------------------------------
CREATE TABLE inventory_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    type ENUM('received', 'dispensed', 'adjusted', 'expired') NOT NULL,
    quantity INT NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_txn_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_txn_medicine (medicine_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- distributions: scheduled/dispensed medicine events
-- -------------------------------------------------------------------------
CREATE TABLE distributions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senior_citizen_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    scheduled_date DATE NOT NULL,
    dispensed_date DATE NULL,
    status ENUM('Scheduled', 'Dispensed', 'Not Dispensed', 'Missed') NOT NULL DEFAULT 'Scheduled',
    notes VARCHAR(255) NULL,
    recorded_by INT UNSIGNED NOT NULL,
    reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dist_senior FOREIGN KEY (senior_citizen_id) REFERENCES senior_citizens(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dist_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dist_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_dist_senior (senior_citizen_id),
    INDEX idx_dist_medicine (medicine_id),
    INDEX idx_dist_status (status),
    INDEX idx_dist_scheduled (scheduled_date)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- forecast_results: historical record of every forecast run
-- -------------------------------------------------------------------------
CREATE TABLE forecast_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    forecast_period VARCHAR(50) NOT NULL,
    predicted_quantity DECIMAL(10, 2) NOT NULL,
    method_used VARCHAR(100) NOT NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_forecast_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_forecast_medicine (medicine_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- sms_logs: every SMS attempt, reminder or broadcast
-- -------------------------------------------------------------------------
CREATE TABLE sms_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senior_citizen_id INT UNSIGNED NULL,
    phone_number VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('reminder', 'broadcast') NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    response_notes VARCHAR(255) NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_senior FOREIGN KEY (senior_citizen_id) REFERENCES senior_citizens(id) ON DELETE SET NULL,
    INDEX idx_sms_senior (senior_citizen_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- activity_logs: who did what, when (auditability requirement)
-- -------------------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_user (user_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
