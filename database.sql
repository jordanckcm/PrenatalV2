-- ============================================================================
-- MaternalCare — Railway MySQL Database Schema
-- Web-Based Prenatal Health Center Booking Appointment
-- and Record Management System
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Users Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('patient', 'healthcare_worker', 'admin') NOT NULL DEFAULT 'patient',
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    `address` TEXT DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `room` VARCHAR(100) DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. Patients Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `patient_code` VARCHAR(20) NOT NULL UNIQUE,
    `dob` DATE DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `blood_type` VARCHAR(5) DEFAULT 'A+',
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
    `lmp` DATE DEFAULT NULL,
    `edd` DATE DEFAULT NULL,
    `gravida` INT DEFAULT 1,
    `para` INT DEFAULT 0,
    `abortus` INT DEFAULT 0,
    `medical_history` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. Healthcare Services Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 30,
    `max_daily_slots` INT NOT NULL DEFAULT 15,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. Schedules Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT DEFAULT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    `start_time` TIME NOT NULL DEFAULT '08:00:00',
    `end_time` TIME NOT NULL DEFAULT '16:00:00',
    `max_patients_per_slot` INT NOT NULL DEFAULT 2,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4b. Slot Overrides
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `slot_overrides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `override_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `max_patients` INT NOT NULL DEFAULT 2,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_slot` (`override_date`, `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4c. Staff Duty Schedules
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_duty_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    `start_time` TIME NOT NULL DEFAULT '08:00:00',
    `end_time` TIME NOT NULL DEFAULT '16:00:00',
    `is_duty` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `idx_staff_day` (`staff_id`, `day_of_week`),
    FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. Appointments Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appointment_code` VARCHAR(20) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `healthcare_worker_id` INT DEFAULT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'missed') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `room` VARCHAR(100) DEFAULT NULL,
    `worker_notified` TINYINT(1) NOT NULL DEFAULT 0,
    `worker_confirmed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_appt_date_service` (`appointment_date`, `service_id`, `status`),
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`healthcare_worker_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. Prenatal Records Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prenatal_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `appointment_id` INT DEFAULT NULL,
    `healthcare_worker_id` INT NOT NULL,
    `visit_date` DATE NOT NULL,
    `gestational_age_weeks` INT NOT NULL DEFAULT 4,
    `weight_kg` DECIMAL(5,2) DEFAULT NULL,
    `systolic_bp` INT DEFAULT NULL,
    `diastolic_bp` INT DEFAULT NULL,
    `fundal_height_cm` DECIMAL(4,1) DEFAULT NULL,
    `fetal_heart_rate` INT DEFAULT NULL,
    `fetal_presentation` VARCHAR(50) DEFAULT 'Cephalic',
    `edema` ENUM('none', 'mild', 'moderate', 'severe') DEFAULT 'none',
    `urine_protein` VARCHAR(20) DEFAULT 'Negative',
    `urine_sugar` VARCHAR(20) DEFAULT 'Negative',
    `hemoglobin_level` DECIMAL(4,1) DEFAULT NULL,
    `vitamins_prescribed` TEXT DEFAULT NULL,
    `iron_folic_given` TINYINT(1) DEFAULT 1,
    `tetanus_vaccine_given` VARCHAR(50) DEFAULT 'TT1',
    `risk_assessment` ENUM('low_risk', 'moderate_risk', 'high_risk') DEFAULT 'low_risk',
    `clinical_notes` TEXT DEFAULT NULL,
    `next_visit_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`healthcare_worker_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. Notifications Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('appointment', 'reminder', 'followup', 'system') NOT NULL DEFAULT 'system',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. Audit Logs Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `user_role` VARCHAR(30) DEFAULT 'guest',
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 9. System Settings Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Default System Settings
-- ----------------------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('center_name', 'MaternalCare Prenatal Health & Wellness Center'),
('center_address', '123 Healthcare Way, Wellness District, Metro City'),
('center_phone', '+63 912 345 6789'),
('center_email', 'info@maternalcare-health.org'),
('reminder_days_before', '1'),
('max_advance_booking_days', '30'),
('site_name', 'MaternalCare Prenatal Health System'),
('site_description', 'Web-Based Prenatal Health Center Booking Appointment and Record Management System'),
('contact_email', 'info@maternalcare.com'),
('contact_phone', '+63 912 345 6789'),
('clinic_address', '123 Health Street, Barangay San Isidro, Metro Manila'),
('appointment_duration', '30'),
('enable_notifications', '1'),
('enable_patient_registration', '1'),
('system_logo', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ----------------------------------------------------------------------------
-- Default Services
-- ----------------------------------------------------------------------------
INSERT INTO `services` (`id`, `service_name`, `description`, `duration_minutes`, `max_daily_slots`, `is_active`) VALUES
(1, 'Routine Prenatal Consultation', 'Regular monthly prenatal evaluation, vitals checking, and fetal growth check.', 30, 20, 1),
(2, 'Obstetric Ultrasound (Pelvic/3D)', 'Fetal anatomical scan, amniotic fluid evaluation, and growth measurement.', 45, 10, 1),
(3, 'High-Risk Prenatal Screening', 'Specialized consultation for mothers with pre-existing conditions or complications.', 45, 8, 1),
(4, 'Tetanus Toxoid & Maternal Immunization', 'Vaccination administration for maternal and neonatal tetanus protection.', 15, 25, 1),
(5, 'Laboratory Tests (Blood & Urine)', 'Complete Blood Count (CBC), Blood Typing, Urinalysis, Glucose screening.', 20, 15, 1),
(6, 'Postnatal Checkup & Family Planning', 'Maternal recovery check, newborn care guidance, and contraceptive counseling.', 30, 15, 1)
ON DUPLICATE KEY UPDATE `service_name` = VALUES(`service_name`);

-- ----------------------------------------------------------------------------
-- Default Clinic Schedule
-- ----------------------------------------------------------------------------
INSERT INTO `schedules` (`day_of_week`, `start_time`, `end_time`, `max_patients_per_slot`, `is_active`)
SELECT * FROM (
    SELECT 'Monday' AS d, '08:00:00' AS s, '16:00:00' AS e, 2 AS m, 1 AS a UNION ALL
    SELECT 'Tuesday', '08:00:00', '16:00:00', 2, 1 UNION ALL
    SELECT 'Wednesday', '08:00:00', '16:00:00', 2, 1 UNION ALL
    SELECT 'Thursday', '08:00:00', '16:00:00', 2, 1 UNION ALL
    SELECT 'Friday', '08:00:00', '16:00:00', 2, 1 UNION ALL
    SELECT 'Saturday', '09:00:00', '13:00:00', 2, 1
) AS default_schedule
WHERE NOT EXISTS (SELECT 1 FROM `schedules`);

-- ----------------------------------------------------------------------------
-- Default Users
-- All seed accounts use password: password123
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `full_name`, `phone`, `status`) VALUES
(1, 'admin', 'admin@prenatalcare.org', '$2y$10$hnyoY17wd4E9Ktl.jF1is.5h79bzxCEQWzYlH.mWVSWlHncEEog0m', 'admin', 'Administrator System Admin', '+63 900 000 0001', 'active'),
(2, 'doctor1', 'dr.maria@prenatalcare.org', '$2y$10$hnyoY17wd4E9Ktl.jF1is.5h79bzxCEQWzYlH.mWVSWlHncEEog0m', 'healthcare_worker', 'Dr. Maria Santos, MD', '+63 900 000 0002', 'active'),
(3, 'nurse1', 'nurse.sarah@prenatalcare.org', '$2y$10$hnyoY17wd4E9Ktl.jF1is.5h79bzxCEQWzYlH.mWVSWlHncEEog0m', 'healthcare_worker', 'Nurse Sarah Jenkins, RN', '+63 900 000 0003', 'active'),
(4, 'patient1', 'jane.doe@example.com', '$2y$10$hnyoY17wd4E9Ktl.jF1is.5h79bzxCEQWzYlH.mWVSWlHncEEog0m', 'patient', 'Jane Doe', '+63 900 000 1234', 'active'),
(5, 'patient2', 'emily.smith@example.com', '$2y$10$hnyoY17wd4E9Ktl.jF1is.5h79bzxCEQWzYlH.mWVSWlHncEEog0m', 'patient', 'Emily Smith', '+63 900 000 5678', 'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- ----------------------------------------------------------------------------
-- Sample Patient Profiles
-- ----------------------------------------------------------------------------
INSERT INTO `patients` (`id`, `user_id`, `patient_code`, `dob`, `address`, `blood_type`, `emergency_contact_name`, `emergency_contact_phone`, `lmp`, `edd`, `gravida`, `para`, `abortus`, `medical_history`) VALUES
(1, 4, 'PN-2026-0001', '1995-04-12', '456 Rosewood Lane, Metro City', 'O+', 'John Doe (Husband)', '+63 900 111 9999', '2026-03-01', '2026-12-06', 1, 0, 0, 'No chronic illnesses. Mild asthma in childhood.'),
(2, 5, 'PN-2026-0002', '1992-09-25', '789 Maple St, Metro City', 'A+', 'Michael Smith', '+63 900 222 0000', '2026-01-15', '2026-10-22', 2, 1, 0, 'Previous normal spontaneous vaginal delivery in 2023.')
ON DUPLICATE KEY UPDATE `patient_code` = VALUES(`patient_code`);

-- ----------------------------------------------------------------------------
-- Default Staff Duty Schedules
-- ----------------------------------------------------------------------------
INSERT INTO `staff_duty_schedules` (`staff_id`, `day_of_week`, `start_time`, `end_time`, `is_duty`) VALUES
(2, 'Monday', '08:00:00', '16:00:00', 1),
(2, 'Tuesday', '08:00:00', '16:00:00', 1),
(2, 'Wednesday', '08:00:00', '16:00:00', 1),
(2, 'Thursday', '08:00:00', '16:00:00', 1),
(2, 'Friday', '08:00:00', '16:00:00', 1),
(2, 'Saturday', '09:00:00', '13:00:00', 1),
(2, 'Sunday', '08:00:00', '12:00:00', 0),
(3, 'Monday', '08:00:00', '16:00:00', 1),
(3, 'Tuesday', '08:00:00', '16:00:00', 1),
(3, 'Wednesday', '08:00:00', '16:00:00', 1),
(3, 'Thursday', '08:00:00', '16:00:00', 1),
(3, 'Friday', '08:00:00', '16:00:00', 1),
(3, 'Saturday', '09:00:00', '13:00:00', 1),
(3, 'Sunday', '08:00:00', '12:00:00', 0)
ON DUPLICATE KEY UPDATE `is_duty` = VALUES(`is_duty`);

-- ----------------------------------------------------------------------------
-- Sample Appointments
-- ----------------------------------------------------------------------------
INSERT INTO `appointments` (`id`, `appointment_code`, `patient_id`, `service_id`, `healthcare_worker_id`, `appointment_date`, `appointment_time`, `status`, `notes`) VALUES
(1, 'APT-20260904-001', 1, 1, 2, CURDATE() + INTERVAL 1 DAY, '09:00:00', 'confirmed', 'Routine prenatal evaluation for 26th week check.'),
(2, 'APT-20260905-002', 2, 2, 2, CURDATE() + INTERVAL 2 DAY, '10:30:00', 'pending', '3D Fetal Ultrasound scanning.'),
(3, 'APT-20260901-003', 1, 1, 2, CURDATE() - INTERVAL 2 DAY, '08:30:00', 'completed', 'Follow-up consultation completed cleanly.')
ON DUPLICATE KEY UPDATE `appointment_code` = VALUES(`appointment_code`);

-- ----------------------------------------------------------------------------
-- Sample Prenatal Record
-- ----------------------------------------------------------------------------
INSERT INTO `prenatal_records` (`id`, `patient_id`, `appointment_id`, `healthcare_worker_id`, `visit_date`, `gestational_age_weeks`, `weight_kg`, `systolic_bp`, `diastolic_bp`, `fundal_height_cm`, `fetal_heart_rate`, `fetal_presentation`, `edema`, `urine_protein`, `urine_sugar`, `hemoglobin_level`, `vitamins_prescribed`, `iron_folic_given`, `tetanus_vaccine_given`, `risk_assessment`, `clinical_notes`, `next_visit_date`) VALUES
(1, 1, 3, 2, CURDATE() - INTERVAL 2 DAY, 26, 62.50, 118, 76, 25.0, 142, 'Cephalic', 'none', 'Negative', 'Negative', 12.4, 'Prenatal Multivitamins & Calcium 500mg daily', 1, 'TT2', 'low_risk', 'Mother and fetus progressing normally. Good fetal movement reported.', CURDATE() + INTERVAL 2 WEEK)
ON DUPLICATE KEY UPDATE `visit_date` = VALUES(`visit_date`);

-- ----------------------------------------------------------------------------
-- Sample Notifications
-- ----------------------------------------------------------------------------
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(4, 'Appointment Confirmed', 'Your appointment for Routine Prenatal Consultation on tomorrow at 09:00 AM has been confirmed by Dr. Maria Santos.', 'appointment', 0),
(4, 'Prenatal Record Updated', 'Your prenatal checkup record from 2 days ago is now available in your portal.', 'system', 1),
(5, 'Booking Pending Review', 'Your booking request for Obstetric Ultrasound is currently pending healthcare worker confirmation.', 'appointment', 0);

SET FOREIGN_KEY_CHECKS = 1;
