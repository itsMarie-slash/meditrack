-- =========================================================================
-- MediTrack seed data: one test account per role + sample records so the
-- app is walkable end-to-end right after setup.
--
-- Login credentials (email / password):
--   bhw@meditrack.test      / bhw123
--   midwife@meditrack.test  / midwife123
--   ipho@meditrack.test     / ipho123
-- =========================================================================

INSERT INTO users (name, email, password, role, status) VALUES
('Jessa Reyes',      'bhw@meditrack.test',     '$2y$12$sU/rwzp/m/MZZ9CnbcJcvuS8lRAT4z8x7w5mKRoJ5x2t7WGenWeaq', 'bhw',     'active'),
('Nurse Liza Cruz',  'midwife@meditrack.test', '$2y$12$QfZvfeQPkIRTNpxVAqK00Op7dsQsIFS40TbwQchdUMexB6q4DEkmm', 'midwife', 'active'),
('Engr. Paolo Diaz', 'ipho@meditrack.test',    '$2y$12$MpVXBKq6khg3gMYYjxtsEuvHGCo7963C5p8GWJBNqLEG6r7CoDj2u', 'ipho',    'active');

INSERT INTO senior_citizens
(full_name, birthdate, sex, purok_zone, address, contact_number, senior_id_number, maintenance_medicine, health_conditions, allergies, emergency_contact_name, emergency_contact_number, latitude, longitude, status, created_by) VALUES
('Rosario Bautista', '1952-03-14', 'Female', 'Purok 1', 'Zone 1, New Bulatukan', '09171234567', 'SC-0001', 'Losartan 50mg', 'Hypertension', 'None',        'Mario Bautista', '09181234567', 7.1907, 125.4553, 'active', 1),
('Eduardo Santos',   '1948-11-02', 'Male',   'Purok 2', 'Zone 2, New Bulatukan', '09182345678', 'SC-0002', 'Metformin 500mg', 'Diabetes Type 2', 'Sulfa drugs', 'Elena Santos',    '09192345678', 7.1921, 125.4570, 'active', 1),
('Corazon Villamor', '1955-07-22', 'Female', 'Purok 1', 'Zone 1, New Bulatukan', '09193456789', 'SC-0003', 'Amlodipine 5mg', 'Hypertension', 'None',        'Jun Villamor',    '09203456789', 7.1899, 125.4548, 'active', 1),
('Pedro Ramos',      '1950-01-30', 'Male',   'Purok 3', 'Zone 3, New Bulatukan', '09204567890', 'SC-0004', 'Metformin 500mg', 'Diabetes Type 2', 'None',        'Susan Ramos',     '09214567890', 7.1933, 125.4561, 'active', 1),
('Teresita Manalo',  '1946-09-09', 'Female', 'Purok 2', 'Zone 2, New Bulatukan', NULL,          'SC-0005', 'Losartan 50mg',   'Hypertension, Arthritis', 'Penicillin', 'Ana Manalo', '09225678901', 7.1915, 125.4580, 'active', 1);

INSERT INTO medicines (name, category, unit, current_stock, reorder_threshold, expiry_date, date_received, status) VALUES
('Losartan 50mg',   'Antihypertensive', 'tablet', 200, 50, '2027-06-30', '2026-01-15', 'active'),
('Metformin 500mg', 'Antidiabetic',     'tablet', 150, 40, '2027-03-31', '2026-01-15', 'active'),
('Amlodipine 5mg',  'Antihypertensive', 'tablet', 30,  40, '2026-10-15', '2025-12-01', 'active'),
('Multivitamins',   'Supplement',       'bottle', 80,  20, '2027-01-01', '2026-02-01', 'active');

INSERT INTO distributions (senior_citizen_id, medicine_id, quantity, scheduled_date, dispensed_date, status, recorded_by) VALUES
(1, 1, 30, '2026-08-01', '2026-08-01', 'Dispensed', 1),
(2, 2, 30, '2026-08-01', '2026-08-01', 'Dispensed', 1),
(3, 3, 30, '2026-08-05', NULL, 'Not Dispensed', 1),
(4, 2, 30, '2026-08-05', '2026-08-05', 'Dispensed', 1),
(1, 1, 30, '2026-09-05', NULL, 'Scheduled', 1),
(5, 1, 30, '2026-09-06', NULL, 'Scheduled', 1);

INSERT INTO inventory_transactions (medicine_id, type, quantity, notes, created_by) VALUES
(1, 'received', 200, 'Initial stock from IPHO', 1),
(2, 'received', 150, 'Initial stock from IPHO', 1),
(3, 'received', 60,  'Initial stock from IPHO', 1),
(4, 'received', 80,  'Initial stock from IPHO', 1),
(1, 'dispensed', 30, 'Distribution #1', 1),
(2, 'dispensed', 30, 'Distribution #2', 1),
(3, 'dispensed', 30, 'Distribution #3 (later reversed)', 1),
(2, 'dispensed', 30, 'Distribution #4', 1);
