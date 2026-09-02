-- =====================================================================
-- MEDITRACK DATABASE SCHEMA
-- Module 1: Login and Authentication
-- Engine: MariaDB (via XAMPP)
-- -----------------------------------------------------------------------
-- Notes:
--   - This file is added to incrementally as each module is built.
--   - All tables use InnoDB (supports foreign keys) and utf8mb4.
--   - Passwords are never stored in plain text; PHP password_hash()
--     (bcrypt) is used in the application layer.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS meditrack_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE meditrack_db;

-- ---------------------------------------------------------------------
-- Table: users
-- Stores login accounts for all three system roles.
-- role determines what each user can access (checked in PHP auth.php).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)        NOT NULL,
    username        VARCHAR(50)         NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('bhw', 'midwife', 'ipho') NOT NULL DEFAULT 'bhw',
    contact_number  VARCHAR(20)         DEFAULT NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    last_login_at   DATETIME            DEFAULT NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table: login_attempts
-- Tracks failed login attempts per username for simple brute-force
-- lockout protection (5 failed attempts = 5 minute lockout).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)   NOT NULL,
    ip_address      VARCHAR(45)   NOT NULL,
    attempted_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    was_successful  TINYINT(1)    NOT NULL DEFAULT 0,
    INDEX idx_username_time (username, attempted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Seed data: one account per role for testing Module 1.
-- Default password for ALL seed accounts below: BhwAdmin@2026
-- (Hashed with PHP password_hash() / bcrypt - change after first login)
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, username, password_hash, role, contact_number, is_active)
VALUES
('Maria Santos',   'bhw_admin',     '$2y$12$tgTsMTeZazNuc66uRQWc/u3Q3F4w/E9faexKyV8H57yux3crK4uVW', 'bhw',     '09171234567', 1),
('Nurse Dela Cruz', 'midwife_user', '$2y$12$tgTsMTeZazNuc66uRQWc/u3Q3F4w/E9faexKyV8H57yux3crK4uVW', 'midwife', '09181234567', 1),
('IPHO Officer',    'ipho_user',    '$2y$12$tgTsMTeZazNuc66uRQWc/u3Q3F4w/E9faexKyV8H57yux3crK4uVW', 'ipho',    '09191234567', 1)
ON DUPLICATE KEY UPDATE username = username;
