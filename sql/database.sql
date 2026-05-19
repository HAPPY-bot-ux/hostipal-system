-- Drop and recreate database (WARNING: This will delete all existing data)
DROP DATABASE IF EXISTS hospital_system;
CREATE DATABASE hospital_system;
USE hospital_system;

-- Users Table (Updated with password reset columns)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('patient', 'doctor', 'admin') DEFAULT 'patient',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expiry DATETIME NULL,
    email_verified BOOLEAN DEFAULT FALSE
);

-- Password Reset Logs Table (for security monitoring)
CREATE TABLE IF NOT EXISTS password_reset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reset_completed BOOLEAN DEFAULT FALSE,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_requested_at (requested_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Doctors Profile Table
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    specialization VARCHAR(100),
    qualification VARCHAR(200),
    experience_years INT,
    consultation_fee DECIMAL(10, 2),
    available_days VARCHAR(100),
    available_time_start TIME,
    available_time_end TIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments Table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    doctor_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    symptoms TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_appointment (doctor_id, appointment_date, appointment_time)
);

-- Medical Records Table
CREATE TABLE medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    doctor_id INT,
    diagnosis TEXT,
    prescription TEXT,
    blood_pressure VARCHAR(20),
    heart_rate INT,
    temperature DECIMAL(4,1),
    weight DECIMAL(5,2),
    allergies TEXT,
    notes TEXT,
    record_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Bills Table
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    appointment_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_date TIMESTAMP NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- System Logs Table
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- INSERT DEFAULT USERS WITH PASSWORD: password123
-- ============================================

-- Admin User
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '555-0100', 'Administration Office, Hospital Main Building', 'admin', 1, 1);

-- Doctor Users
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('dr.smith', 'dr.smith@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Smith', '555-0101', 'Cardiology Department, 2nd Floor', 'doctor', 1, 1),
('dr.johnson', 'dr.johnson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Emily Johnson', '555-0102', 'Pediatrics Department, 1st Floor', 'doctor', 1, 1),
('dr.williams', 'dr.williams@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Michael Williams', '555-0103', 'Neurology Department, 3rd Floor', 'doctor', 1, 1),
('KB', 'kb.ndlovu@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. K.B. Ndlovu', '555-0109', 'Cardiology Department, Suite 204', 'doctor', 1, 1);

-- Patient Users
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('john_doe', 'john.doe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '555-0104', '123 Main Street, Cityville', 'patient', 1, 1),
('jane_smith', 'jane.smith@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', '555-0105', '456 Oak Avenue, Townsville', 'patient', 1, 1),
('bob_wilson', 'bob.wilson@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Wilson', '555-0106', '789 Pine Road, Villagetown', 'patient', 1, 1),
('alice_brown', 'alice.brown@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Brown', '555-0107', '321 Elm Street, Borough', 'patient', 1, 1),
('charlie_davis', 'charlie.davis@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Charlie Davis', '555-0108', '654 Maple Drive, Hamlet', 'patient', 1, 1);

-- ============================================
-- INSERT DOCTOR PROFILES
-- ============================================

INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee, available_days, available_time_start, available_time_end) VALUES 
((SELECT id FROM users WHERE username = 'dr.smith'), 'Cardiology', 'MD, FACC - Harvard Medical School', 15, 150.00, 'Monday,Tuesday,Wednesday,Thursday,Friday', '09:00:00', '17:00:00'),
((SELECT id FROM users WHERE username = 'dr.johnson'), 'Pediatrics', 'MD, FAAP - Johns Hopkins University', 10, 120.00, 'Monday,Tuesday,Wednesday,Thursday,Friday', '10:00:00', '18:00:00'),
((SELECT id FROM users WHERE username = 'dr.williams'), 'Neurology', 'MD, PhD - Stanford University', 20, 200.00, 'Tuesday,Wednesday,Thursday,Friday,Saturday', '09:30:00', '16:30:00'),
((SELECT id FROM users WHERE username = 'KB'), 'Cardiology', 'MD, FACC - University of Cape Town', 12, 175.00, 'Monday,Tuesday,Thursday,Friday', '08:00:00', '16:00:00');

-- ============================================
-- INSERT SAMPLE APPOINTMENTS
-- ============================================

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, symptoms, notes) VALUES 
((SELECT id FROM users WHERE username = 'john_doe'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.smith')), CURDATE(), '10:00:00', 'confirmed', 'Chest pain, shortness of breath, palpitations', 'Patient needs ECG and stress test'),
((SELECT id FROM users WHERE username = 'jane_smith'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.johnson')), CURDATE() + INTERVAL 1 DAY, '14:30:00', 'pending', 'Fever, cough, runny nose, sore throat', 'Possible seasonal flu, needs testing'),
((SELECT id FROM users WHERE username = 'bob_wilson'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.williams')), CURDATE() + INTERVAL 2 DAY, '11:15:00', 'confirmed', 'Severe headaches, blurred vision, dizziness', 'MRI recommended for further evaluation'),
((SELECT id FROM users WHERE username = 'alice_brown'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.smith')), CURDATE() + INTERVAL 3 DAY, '15:45:00', 'pending', 'High blood pressure, fatigue', 'Follow-up appointment'),
((SELECT id FROM users WHERE username = 'charlie_davis'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'KB')), CURDATE() + INTERVAL 1 DAY, '09:30:00', 'confirmed', 'Chest discomfort, irregular heartbeat', 'Echocardiogram scheduled');

-- ============================================
-- INSERT SAMPLE MEDICAL RECORDS
-- ============================================

INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, blood_pressure, heart_rate, temperature, weight, allergies, notes, record_date) VALUES 
((SELECT id FROM users WHERE username = 'john_doe'), (SELECT id FROM users WHERE username = 'dr.smith'), 'Hypertension Stage 1', 'Lisinopril 10mg once daily, Low sodium diet', '135/85', 75, 98.6, 85.5, 'None', 'Initial diagnosis, follow up in 2 weeks', CURDATE()),
((SELECT id FROM users WHERE username = 'jane_smith'), (SELECT id FROM users WHERE username = 'dr.johnson'), 'Upper Respiratory Infection', 'Amoxicillin 500mg three times daily for 7 days, Rest and fluids', '110/70', 88, 99.1, 62.0, 'Penicillin', 'Prescribed antibiotics, monitor temperature', CURDATE()),
((SELECT id FROM users WHERE username = 'bob_wilson'), (SELECT id FROM users WHERE username = 'dr.williams'), 'Migraine with aura', 'Sumatriptan 50mg as needed, Avoid triggers', '120/80', 82, 98.4, 78.0, 'Sulfa drugs', 'MRI scheduled, keep headache diary', CURDATE()),
((SELECT id FROM users WHERE username = 'charlie_davis'), (SELECT id FROM users WHERE username = 'KB'), 'Arrhythmia', 'Metoprolol 25mg daily, Reduce caffeine', '128/82', 95, 98.7, 82.0, 'None', 'Holter monitor recommended', CURDATE());

-- ============================================
-- INSERT SAMPLE BILLS
-- ============================================

INSERT INTO bills (patient_id, appointment_id, amount, status, payment_method, payment_date, description) VALUES 
((SELECT id FROM users WHERE username = 'john_doe'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'john_doe') LIMIT 1), 150.00, 'paid', 'Credit Card', NOW(), 'Cardiology consultation fee'),
((SELECT id FROM users WHERE username = 'jane_smith'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'jane_smith') LIMIT 1), 120.00, 'pending', NULL, NULL, 'Pediatrics consultation fee'),
((SELECT id FROM users WHERE username = 'bob_wilson'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'bob_wilson') LIMIT 1), 200.00, 'pending', NULL, NULL, 'Neurology consultation fee'),
((SELECT id FROM users WHERE username = 'charlie_davis'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'charlie_davis') LIMIT 1), 175.00, 'paid', 'Insurance', NOW(), 'Cardiology consultation - Dr. Ndlovu');

-- ============================================
-- INSERT SYSTEM LOGS
-- ============================================

INSERT INTO system_logs (user_id, action, details, ip_address) VALUES 
((SELECT id FROM users WHERE username = 'admin'), 'Database Setup', 'Initial database setup completed with default users', '127.0.0.1'),
((SELECT id FROM users WHERE username = 'admin'), 'System Initialization', 'Hospital management system deployed', '127.0.0.1');

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_is_active ON users(is_active);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_reset_token ON users(reset_token);
CREATE INDEX idx_users_reset_expiry ON users(reset_token_expiry);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_doctor ON appointments(doctor_id);
CREATE INDEX idx_medical_records_patient ON medical_records(patient_id);
CREATE INDEX idx_medical_records_date ON medical_records(record_date);
CREATE INDEX idx_bills_status ON bills(status);
CREATE INDEX idx_bills_patient ON bills(patient_id);
CREATE INDEX idx_system_logs_user ON system_logs(user_id);
CREATE INDEX idx_system_logs_created ON system_logs(created_at);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

SELECT '=========================================' AS '';
SELECT 'DATABASE SETUP COMPLETE!' AS Status;
SELECT '=========================================' AS '';

SELECT 'Users Summary:' AS '';
SELECT COUNT(*) AS Total_Users, 
       SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS Admins,
       SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) AS Doctors,
       SUM(CASE WHEN role = 'patient' THEN 1 ELSE 0 END) AS Patients
FROM users;

SELECT '' AS '';
SELECT 'Default Login Credentials:' AS '';
SELECT '-----------------------------------------' AS '';
SELECT 'ADMIN ACCESS:' AS '';
SELECT '  Username: admin' AS '';
SELECT '  Password: password123' AS '';
SELECT '' AS '';
SELECT 'DOCTOR ACCESS:' AS '';
SELECT '  Username: dr.smith' AS '';
SELECT '  Username: dr.johnson' AS '';
SELECT '  Username: dr.williams' AS '';
SELECT '  Username: KB' AS '';
SELECT '  Password: password123 (for all doctors)' AS '';
SELECT '' AS '';
SELECT 'PATIENT ACCESS:' AS '';
SELECT '  Username: john_doe' AS '';
SELECT '  Username: jane_smith' AS '';
SELECT '  Username: bob_wilson' AS '';
SELECT '  Username: alice_brown' AS '';
SELECT '  Username: charlie_davis' AS '';
SELECT '  Password: password123 (for all patients)' AS '';
SELECT '-----------------------------------------' AS '';

-- List all users for verification
SELECT '' AS '';
SELECT 'All Registered Users:' AS '';
SELECT id, username, email, full_name, role, is_active, email_verified FROM users ORDER BY role, username;

-- Show password reset logs table structure
SELECT '' AS '';
SELECT 'Password Reset System Ready:' AS '';
SELECT '  - reset_token column added to users table' AS '';
SELECT '  - reset_token_expiry column added to users table' AS '';
SELECT '  - email_verified column added to users table' AS '';
SELECT '  - password_reset_logs table created for security auditing' AS '';
SELECT '  - Indexes created for fast token lookups' AS '';