-- MediTrack database schema (Phase 4)
-- MySQL 8.x / InnoDB. Run against an empty database:
--   mysql -u root -p meditrack < database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE  -- 'bhw' | 'midwife' | 'ipho'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    role_id       TINYINT UNSIGNED NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;
CREATE INDEX idx_users_role ON users(role_id);

-- ---------------------------------------------------------------------
-- puroks (lookup)
-- ---------------------------------------------------------------------
CREATE TABLE puroks (
    id        SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(60) NOT NULL UNIQUE,
    latitude  DECIMAL(10,7) NULL,   -- approximate purok centroid, used as
    longitude DECIMAL(10,7) NULL    -- a fallback when a beneficiary has no exact pin
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- medicines
-- ---------------------------------------------------------------------
CREATE TABLE medicines (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(120) NOT NULL,
    category            VARCHAR(60)  NOT NULL,   -- e.g. 'Hypertension', 'Diabetes'
    description         TEXT NULL,
    unit                VARCHAR(20)  NOT NULL,   -- e.g. 'tablet', 'box'
    stock_quantity      INT UNSIGNED NOT NULL DEFAULT 0,
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 20,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicines_name (name)
) ENGINE=InnoDB;
CREATE INDEX idx_medicines_category ON medicines(category);
CREATE INDEX idx_medicines_low_stock ON medicines(stock_quantity, low_stock_threshold);

-- ---------------------------------------------------------------------
-- medicine_batches (one medicine -> many batches; FEFO tracking)
-- ---------------------------------------------------------------------
CREATE TABLE medicine_batches (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id      INT UNSIGNED NOT NULL,
    batch_number     VARCHAR(60)  NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    quantity_remaining INT UNSIGNED NOT NULL,
    expiration_date  DATE NOT NULL,
    date_received    DATE NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_batches_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    UNIQUE KEY uq_batch_per_medicine (medicine_id, batch_number)
) ENGINE=InnoDB;
CREATE INDEX idx_batches_expiration ON medicine_batches(expiration_date);

-- ---------------------------------------------------------------------
-- senior_citizens (beneficiaries)
-- ---------------------------------------------------------------------
CREATE TABLE senior_citizens (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name           VARCHAR(150) NOT NULL,
    birthdate           DATE NOT NULL,
    gender              ENUM('male','female','other') NOT NULL,
    purok_id            SMALLINT UNSIGNED NOT NULL,
    address_detail      VARCHAR(255) NULL,
    latitude            DECIMAL(10,7) NULL,
    longitude           DECIMAL(10,7) NULL,
    mobile_number       VARCHAR(15) NOT NULL,
    guardian_name       VARCHAR(150) NULL,
    guardian_contact    VARCHAR(15) NULL,
    medical_condition   ENUM('hypertension','diabetes','both') NOT NULL,
    assigned_medicine_id INT UNSIGNED NULL,
    distribution_status ENUM('active','pending','inactive') NOT NULL DEFAULT 'active',
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sc_purok FOREIGN KEY (purok_id) REFERENCES puroks(id),
    CONSTRAINT fk_sc_medicine FOREIGN KEY (assigned_medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB;
CREATE INDEX idx_sc_name ON senior_citizens(full_name);
CREATE INDEX idx_sc_purok ON senior_citizens(purok_id);
CREATE INDEX idx_sc_condition ON senior_citizens(medical_condition);
CREATE INDEX idx_sc_status ON senior_citizens(distribution_status);

-- ---------------------------------------------------------------------
-- distribution_schedules
-- ---------------------------------------------------------------------
CREATE TABLE distribution_schedules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id     INT UNSIGNED NOT NULL,
    scheduled_date  DATE NOT NULL,
    time_slot       VARCHAR(40) NOT NULL,   -- e.g. '9:00 AM - 12:00 PM'
    venue           VARCHAR(150) NOT NULL DEFAULT 'Barangay New Bulatukan Health Center',
    status          ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    auto_generated  TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB;
CREATE INDEX idx_schedule_status ON distribution_schedules(status);
CREATE INDEX idx_schedule_date ON distribution_schedules(scheduled_date);

-- ---------------------------------------------------------------------
-- distributions (transactions; a schedule has many distributions)
-- ---------------------------------------------------------------------
CREATE TABLE distributions (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id        INT UNSIGNED NOT NULL,
    senior_citizen_id  INT UNSIGNED NOT NULL,
    medicine_batch_id  INT UNSIGNED NOT NULL,
    quantity_issued    INT UNSIGNED NOT NULL,
    date_released      DATETIME NULL,
    receiver_name      VARCHAR(150) NOT NULL,
    bhw_id             INT UNSIGNED NOT NULL,
    status             ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dist_schedule FOREIGN KEY (schedule_id) REFERENCES distribution_schedules(id),
    CONSTRAINT fk_dist_senior FOREIGN KEY (senior_citizen_id) REFERENCES senior_citizens(id),
    CONSTRAINT fk_dist_batch FOREIGN KEY (medicine_batch_id) REFERENCES medicine_batches(id),
    CONSTRAINT fk_dist_bhw FOREIGN KEY (bhw_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE INDEX idx_dist_status ON distributions(status);
CREATE INDEX idx_dist_senior ON distributions(senior_citizen_id);
CREATE INDEX idx_dist_date ON distributions(date_released);

-- ---------------------------------------------------------------------
-- sms_notifications
-- ---------------------------------------------------------------------
CREATE TABLE sms_notifications (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senior_citizen_id INT UNSIGNED NOT NULL,
    schedule_id       INT UNSIGNED NOT NULL,
    message           VARCHAR(320) NOT NULL,
    status            ENUM('sent','failed') NOT NULL,
    error_message     VARCHAR(255) NULL,
    sent_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_senior FOREIGN KEY (senior_citizen_id) REFERENCES senior_citizens(id),
    CONSTRAINT fk_sms_schedule FOREIGN KEY (schedule_id) REFERENCES distribution_schedules(id)
) ENGINE=InnoDB;
CREATE INDEX idx_sms_status ON sms_notifications(status);

-- ---------------------------------------------------------------------
-- demand_forecasts
-- ---------------------------------------------------------------------
CREATE TABLE demand_forecasts (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id              INT UNSIGNED NOT NULL,
    forecast_month           DATE NOT NULL,   -- first day of the forecasted month
    predicted_quantity       INT UNSIGNED NOT NULL,
    historical_window_months TINYINT UNSIGNED NOT NULL,
    generated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_forecast_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    UNIQUE KEY uq_forecast_medicine_month (medicine_id, forecast_month)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- audit_logs
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60)  NOT NULL,   -- e.g. 'create', 'update', 'delete', 'login', 'stock_update'
    entity_type VARCHAR(60)  NOT NULL,   -- e.g. 'senior_citizen', 'medicine', 'user'
    entity_id   INT UNSIGNED NULL,
    details     JSON NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

-- ---------------------------------------------------------------------
-- system_settings (key/value, BHW-editable)
-- ---------------------------------------------------------------------
CREATE TABLE system_settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
