-- CareSlot - Healthcare Appointment Management System
CREATE DATABASE IF NOT EXISTS healthcare_appointment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE healthcare_appointment;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointment_status_history, appointments, doctor_unavailable_dates, doctor_availability, doctors, specializations, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('patient','admin') NOT NULL DEFAULT 'patient',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE specializations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE doctors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  specialization_id INT UNSIGNED NOT NULL,
  qualification VARCHAR(120) NULL,
  experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fee DECIMAL(8,2) NOT NULL DEFAULT 0,
  bio TEXT NULL,
  photo VARCHAR(160) NULL,
  slot_minutes TINYINT UNSIGNED NOT NULL DEFAULT 30,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doctor_spec (specialization_id, active),
  CONSTRAINT fk_doctor_spec FOREIGN KEY (specialization_id) REFERENCES specializations(id)
) ENGINE=InnoDB;

CREATE TABLE doctor_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Monday ... 7=Sunday',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  UNIQUE KEY uq_doctor_weekday (doctor_id, weekday),
  CONSTRAINT fk_avail_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctor_unavailable_dates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT UNSIGNED NOT NULL,
  off_date DATE NOT NULL,
  reason VARCHAR(120) NULL,
  UNIQUE KEY uq_doctor_off (doctor_id, off_date),
  CONSTRAINT fk_off_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- slot_key = "doctor|date|time" while the appointment is active, NULL once cancelled.
-- The UNIQUE index on it is what makes a double booking impossible at database level.
CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  patient_id INT UNSIGNED NOT NULL,
  doctor_id INT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  concerns TEXT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  slot_key VARCHAR(40) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_active_slot (slot_key),
  INDEX idx_appt_date (appointment_date, status),
  INDEX idx_appt_patient (patient_id),
  INDEX idx_appt_doctor_date (doctor_id, appointment_date),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE appointment_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL,
  changed_by INT UNSIGNED NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Demo accounts: admin@careslot.local / Admin@123 , patient@example.com / Patient@123
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Site Administrator', 'admin@careslot.local', '01700000000', '$2y$10$Z4J4EP6MkLlMXmVzAJHleeRpqa6rKHSA7Eyk91WSrGGcxUBUa8idK', 'admin'),
('Demo Patient', 'patient@example.com', '01711111111', '$2y$10$E7v5ZUYiGepBLTSvAFhsOuEoqka5UmlZHqFriBeZ/2dc7IMh1w/rK', 'patient'),
('Rahim Uddin', 'rahim@example.com', '01722222222', '$2y$10$E7v5ZUYiGepBLTSvAFhsOuEoqka5UmlZHqFriBeZ/2dc7IMh1w/rK', 'patient');

INSERT INTO specializations (name) VALUES
('Cardiology'),('Dermatology'),('Neurology'),('Pediatrics'),('Orthopedics'),('General Medicine'),('Gynecology'),('ENT');

INSERT INTO doctors (name, specialization_id, qualification, experience_years, fee, bio, slot_minutes) VALUES
('Dr. Ayesha Rahman', 1, 'MBBS, MD (Cardiology)', 14, 1200, 'Consultant cardiologist focused on preventive cardiology, hypertension and heart failure follow-up.', 30),
('Dr. Imran Hossain', 1, 'MBBS, FCPS (Medicine), DM', 9, 1000, 'Treats chest pain, arrhythmia and cardiac rehabilitation patients with a practical, evidence-led approach.', 30),
('Dr. Nusrat Jahan', 2, 'MBBS, MD (Dermatology)', 8, 900, 'Skin allergies, acne, eczema and hair-loss clinic. Also sees paediatric skin conditions.', 20),
('Dr. Tanvir Ahmed', 3, 'MBBS, MD (Neurology)', 17, 1500, 'Migraine, epilepsy and stroke recovery specialist with long hospital experience.', 30),
('Dr. Farzana Karim', 4, 'MBBS, DCH, MD (Paediatrics)', 11, 800, 'Child health, vaccination schedules, growth and nutrition guidance for infants to teenagers.', 20),
('Dr. Sajid Chowdhury', 5, 'MBBS, MS (Orthopaedics)', 13, 1100, 'Joint pain, sports injuries and fracture care. Offers non-surgical options first.', 30),
('Dr. Maliha Sultana', 6, 'MBBS, MRCP', 6, 600, 'General physician for fever, infections, diabetes and routine health checks.', 15),
('Dr. Rafiq Islam', 6, 'MBBS, FCPS (Medicine)', 20, 700, 'Senior physician with two decades of experience in internal medicine.', 15),
('Dr. Sabrina Haque', 7, 'MBBS, FCPS (Gynae & Obs)', 12, 1000, 'Antenatal care, women health check-ups and menstrual disorders.', 30),
('Dr. Kamal Uddin', 8, 'MBBS, DLO, MS (ENT)', 10, 900, 'Ear, nose and throat problems including sinusitis, hearing loss and tonsil issues.', 20),
('Dr. Lamia Parvin', 2, 'MBBS, DDV', 5, 700, 'Cosmetic and clinical dermatology, with a focus on acne and pigmentation.', 20);

INSERT INTO doctor_availability (doctor_id, weekday, start_time, end_time) VALUES
(1,1,'09:00','13:00'),(1,3,'09:00','13:00'),(1,6,'10:00','14:00'),
(2,2,'14:00','19:00'),(2,4,'14:00','19:00'),(2,7,'14:00','18:00'),
(3,1,'10:00','14:00'),(3,2,'10:00','14:00'),(3,4,'10:00','14:00'),(3,6,'10:00','13:00'),
(4,3,'09:00','14:00'),(4,5,'15:00','19:00'),(4,7,'09:00','13:00'),
(5,1,'09:00','13:00'),(5,2,'09:00','13:00'),(5,3,'09:00','13:00'),(5,4,'09:00','13:00'),(5,7,'09:00','12:00'),
(6,2,'15:00','19:00'),(6,4,'15:00','19:00'),(6,6,'09:00','13:00'),
(7,1,'09:00','17:00'),(7,2,'09:00','17:00'),(7,3,'09:00','17:00'),(7,4,'09:00','17:00'),(7,7,'09:00','17:00'),
(8,1,'16:00','20:00'),(8,3,'16:00','20:00'),(8,5,'16:00','20:00'),(8,6,'16:00','20:00'),
(9,2,'10:00','15:00'),(9,4,'10:00','15:00'),(9,7,'10:00','14:00'),
(10,1,'15:00','19:00'),(10,3,'15:00','19:00'),(10,6,'15:00','19:00'),
(11,2,'13:00','18:00'),(11,4,'13:00','18:00'),(11,5,'16:00','20:00'),(11,7,'13:00','17:00');

INSERT INTO doctor_unavailable_dates (doctor_id, off_date, reason) VALUES
(7, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Medical conference'),
(1, DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'Personal leave');

-- Sample appointments (relative to today so the dashboard has data to show)
INSERT INTO appointments (reference, patient_id, doctor_id, appointment_date, appointment_time, concerns, status, slot_key) VALUES
('HA-SEED-001', 2, 1, CURDATE(), '09:00', 'Chest tightness while climbing stairs', 'confirmed', CONCAT('1|', CURDATE(), '|09:00')),
('HA-SEED-002', 3, 7, CURDATE(), '10:30', 'Fever for three days', 'pending', CONCAT('7|', CURDATE(), '|10:30')),
('HA-SEED-003', 2, 5, CURDATE(), '11:00', 'Child vaccination follow-up', 'confirmed', CONCAT('5|', CURDATE(), '|11:00')),
('HA-SEED-004', 3, 3, CURDATE(), '12:20', 'Skin rash on arms', 'cancelled', NULL),
('HA-SEED-005', 2, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00', 'Recurring migraine', 'completed', CONCAT('4|', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '|10:00')),
('HA-SEED-006', 3, 8, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '16:30', 'Blood pressure review', 'completed', CONCAT('8|', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '|16:30')),
('HA-SEED-007', 2, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:30', 'Knee pain', 'completed', CONCAT('6|', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '|15:30')),
('HA-SEED-008', 3, 9, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:00', 'Routine check-up', 'cancelled', NULL),
('HA-SEED-009', 2, 10, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '16:00', 'Sinus congestion', 'completed', CONCAT('10|', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '|16:00')),
('HA-SEED-010', 3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:30', 'Palpitations at night', 'pending', CONCAT('2|', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '|14:30')),
('HA-SEED-011', 2, 11, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00', 'Acne treatment', 'confirmed', CONCAT('11|', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '|14:00')),
('HA-SEED-012', 3, 7, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:30', 'Diabetes check', 'pending', CONCAT('7|', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '|09:30'));

INSERT INTO appointment_status_history (appointment_id, status, changed_by)
SELECT id, status, 1 FROM appointments;
