-- MediTrack seed data (Phase 4)
-- Run after schema.sql:
--   mysql -u root -p meditrack < database/seed.sql
-- Default password for all seeded accounts is "ChangeMe123!" — change on first login.

INSERT INTO roles (name) VALUES ('bhw'), ('midwife'), ('ipho');

-- Placeholder puroks — replace with Barangay New Bulatukan's actual purok
-- names/coordinates (see docs/01-requirements-analysis.md §13.3).
INSERT INTO puroks (name, latitude, longitude) VALUES
    ('Purok 1', NULL, NULL),
    ('Purok 2', NULL, NULL),
    ('Purok 3', NULL, NULL),
    ('Purok 4', NULL, NULL),
    ('Purok 5', NULL, NULL);

-- password_hash('ChangeMe123!', PASSWORD_DEFAULT) — regenerate for production use.
INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES
    ('bhw.admin',  '$2y$12$g8FCcRym2CfjUIf7vMosOuAp.ZD1/jdpShrk4RySeHlrktXdpMMju', 'Barangay Health Worker', 1, 1),
    ('midwife.demo', '$2y$12$g8FCcRym2CfjUIf7vMosOuAp.ZD1/jdpShrk4RySeHlrktXdpMMju', 'Midwife Demo', 2, 1),
    ('ipho.demo', '$2y$12$g8FCcRym2CfjUIf7vMosOuAp.ZD1/jdpShrk4RySeHlrktXdpMMju', 'IPHO Demo', 3, 1);

INSERT INTO medicines (name, category, description, unit, stock_quantity, low_stock_threshold) VALUES
    ('Amlodipine 5mg', 'Hypertension', 'Calcium channel blocker for blood pressure control', 'tablet', 0, 50),
    ('Losartan 50mg', 'Hypertension', 'Angiotensin receptor blocker', 'tablet', 0, 50),
    ('Metformin 500mg', 'Diabetes', 'First-line oral medication for type 2 diabetes', 'tablet', 0, 50);

INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('default_low_stock_threshold', '20'),
    ('default_distribution_venue', 'Barangay New Bulatukan Health Center'),
    ('default_time_slot', '9:00 AM - 12:00 PM'),
    ('schedule_lead_days', '3'),
    ('sms_schedule_template', 'MediTrack: Your {medicine} pickup is on {date} at {time}, {venue}. Please arrive on time.');
