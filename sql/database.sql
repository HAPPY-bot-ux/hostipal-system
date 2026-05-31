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
-- NEW DEFAULT USERS (All passwords: SecurePass123!)
-- ============================================

-- Admin Users
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('superadmin', 'superadmin@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Johnson', '555-0001', 'Executive Office, Medical Tower Level 5', 'admin', 1, 1),
('itadmin', 'itadmin@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michael Chen', '555-0002', 'IT Department, Technology Wing', 'admin', 1, 1);

-- Doctor Users (NEW)
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('dr.patel', 'dr.patel@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Priya Patel', '555-1001', 'Cardiology Dept, Room 204', 'doctor', 1, 1),
('dr.martinez', 'dr.martinez@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Carlos Martinez', '555-1002', 'Pediatrics Dept, Floor 2', 'doctor', 1, 1),
('dr.kim', 'dr.kim@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Jennifer Kim', '555-1003', 'Neurology Dept, Room 310', 'doctor', 1, 1),
('dr.osei', 'dr.osei@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Kwame Osei', '555-1004', 'Orthopedics Dept, Suite B', 'doctor', 1, 1),
('dr.wilson', 'dr.wilson@medflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Emily Wilson', '555-1005', 'Dermatology Clinic', 'doctor', 1, 1);

-- Patient Users (NEW)
INSERT INTO users (username, email, password, full_name, phone, address, role, is_active, email_verified) VALUES 
('emma.thompson', 'emma.thompson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emma Thompson', '555-2001', '42 Maple Avenue, Springfield', 'patient', 1, 1),
('james.wilson', 'james.wilson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'James Wilson', '555-2002', '15 Oak Street, Rivertown', 'patient', 1, 1),
('sophia.rodriguez', 'sophia.rodriguez@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sophia Rodriguez', '555-2003', '78 Pine Road, Lakewood', 'patient', 1, 1),
('liam.nguyen', 'liam.nguyen@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Liam Nguyen', '555-2004', '234 Cedar Lane, Hillcrest', 'patient', 1, 1),
('olivia.brown', 'olivia.brown@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Olivia Brown', '555-2005', '567 Birch Blvd, Fairview', 'patient', 1, 1),
('noah.davis', 'noah.davis@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Noah Davis', '555-2006', '890 Spruce Way, Greenfield', 'patient', 1, 1),
('mia.garcia', 'mia.garcia@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mia Garcia', '555-2007', '123 Willow Drive, Sunset Valley', 'patient', 1, 1);

-- ============================================
-- DOCTOR PROFILES
-- ============================================

INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee, available_days, available_time_start, available_time_end) VALUES 
((SELECT id FROM users WHERE username = 'dr.patel'), 'Cardiology', 'MD, FACC - Stanford University', 12, 175.00, 'Monday,Tuesday,Wednesday,Thursday', '09:00:00', '17:00:00'),
((SELECT id FROM users WHERE username = 'dr.martinez'), 'Pediatrics', 'MD, FAAP - UCLA Medical Center', 8, 130.00, 'Monday,Tuesday,Wednesday,Friday', '10:00:00', '18:00:00'),
((SELECT id FROM users WHERE username = 'dr.kim'), 'Neurology', 'MD, PhD - Johns Hopkins University', 15, 220.00, 'Tuesday,Wednesday,Thursday,Friday', '09:30:00', '16:30:00'),
((SELECT id FROM users WHERE username = 'dr.osei'), 'Orthopedics', 'MD - University of Ghana Medical School', 10, 160.00, 'Monday,Wednesday,Thursday,Saturday', '08:00:00', '15:00:00'),
((SELECT id FROM users WHERE username = 'dr.wilson'), 'Dermatology', 'MD, FAAD - Harvard Medical School', 7, 145.00, 'Monday,Tuesday,Thursday,Friday', '11:00:00', '19:00:00');

-- ============================================
-- SAMPLE APPOINTMENTS
-- ============================================

INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, symptoms, notes) VALUES 
((SELECT id FROM users WHERE username = 'emma.thompson'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.patel')), CURDATE(), '10:00:00', 'confirmed', 'Chest pain, shortness of breath', 'ECG and stress test scheduled'),
((SELECT id FROM users WHERE username = 'james.wilson'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.martinez')), CURDATE() + INTERVAL 1 DAY, '14:30:00', 'pending', 'Fever, persistent cough', 'Flu test recommended'),
((SELECT id FROM users WHERE username = 'sophia.rodriguez'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.kim')), CURDATE() + INTERVAL 2 DAY, '11:15:00', 'confirmed', 'Severe migraines, vision changes', 'MRI and neurological assessment'),
((SELECT id FROM users WHERE username = 'liam.nguyen'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.osei')), CURDATE() + INTERVAL 1 DAY, '09:30:00', 'confirmed', 'Knee pain, difficulty walking', 'X-ray and physical therapy consult'),
((SELECT id FROM users WHERE username = 'olivia.brown'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.wilson')), CURDATE() + INTERVAL 3 DAY, '15:45:00', 'pending', 'Skin rash, itching', 'Allergy testing scheduled'),
((SELECT id FROM users WHERE username = 'noah.davis'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.patel')), CURDATE() + INTERVAL 2 DAY, '13:00:00', 'pending', 'High blood pressure, fatigue', 'Follow-up appointment'),
((SELECT id FROM users WHERE username = 'mia.garcia'), (SELECT id FROM doctors WHERE user_id = (SELECT id FROM users WHERE username = 'dr.martinez')), CURDATE() + INTERVAL 4 DAY, '10:30:00', 'confirmed', 'Ear infection, fever', 'Antibiotics prescribed');

-- ============================================
-- SAMPLE MEDICAL RECORDS
-- ============================================

INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, blood_pressure, heart_rate, temperature, weight, allergies, notes, record_date) VALUES 
((SELECT id FROM users WHERE username = 'emma.thompson'), (SELECT id FROM users WHERE username = 'dr.patel'), 'Hypertension Stage 1', 'Lisinopril 10mg daily', '135/85', 78, 98.6, 68.5, 'Penicillin', 'Monitor BP weekly', CURDATE()),
((SELECT id FROM users WHERE username = 'james.wilson'), (SELECT id FROM users WHERE username = 'dr.martinez'), 'Upper Respiratory Infection', 'Amoxicillin 500mg 3x daily', '118/72', 90, 99.2, 82.0, 'None', 'Rest and fluids', CURDATE()),
((SELECT id FROM users WHERE username = 'sophia.rodriguez'), (SELECT id FROM users WHERE username = 'dr.kim'), 'Chronic Migraines', 'Sumatriptan 50mg as needed', '122/78', 72, 98.4, 58.5, 'Sulfa drugs', 'Keep headache diary', CURDATE()),
((SELECT id FROM users WHERE username = 'liam.nguyen'), (SELECT id FROM users WHERE username = 'dr.osei'), 'ACL Strain', 'Physical therapy, Ibuprofen 400mg', '125/80', 85, 98.7, 75.0, 'NSAIDs', 'Follow up in 2 weeks', CURDATE()),
((SELECT id FROM users WHERE username = 'olivia.brown'), (SELECT id FROM users WHERE username = 'dr.wilson'), 'Contact Dermatitis', 'Hydrocortisone cream 1%', '110/70', 70, 98.5, 62.0, 'Latex', 'Avoid irritants', CURDATE());

-- ============================================
-- SAMPLE BILLS
-- ============================================

INSERT INTO bills (patient_id, appointment_id, amount, status, payment_method, payment_date, description) VALUES 
((SELECT id FROM users WHERE username = 'emma.thompson'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'emma.thompson') LIMIT 1), 175.00, 'paid', 'Credit Card', NOW(), 'Cardiology consultation'),
((SELECT id FROM users WHERE username = 'james.wilson'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'james.wilson') LIMIT 1), 130.00, 'pending', NULL, NULL, 'Pediatrics consultation'),
((SELECT id FROM users WHERE username = 'sophia.rodriguez'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'sophia.rodriguez') LIMIT 1), 220.00, 'paid', 'Insurance', NOW(), 'Neurology consultation'),
((SELECT id FROM users WHERE username = 'liam.nguyen'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'liam.nguyen') LIMIT 1), 160.00, 'pending', NULL, NULL, 'Orthopedics consultation'),
((SELECT id FROM users WHERE username = 'olivia.brown'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'olivia.brown') LIMIT 1), 145.00, 'pending', NULL, NULL, 'Dermatology consultation'),
((SELECT id FROM users WHERE username = 'mia.garcia'), (SELECT id FROM appointments WHERE patient_id = (SELECT id FROM users WHERE username = 'mia.garcia') LIMIT 1), 130.00, 'paid', 'Cash', NOW(), 'Pediatrics follow-up');

-- ============================================
-- SYSTEM LOGS
-- ============================================

INSERT INTO system_logs (user_id, action, details, ip_address) VALUES 
((SELECT id FROM users WHERE username = 'superadmin'), 'Database Setup', 'Initial database setup completed with new user profiles', '127.0.0.1'),
((SELECT id FROM users WHERE username = 'itadmin'), 'System Initialization', 'Hospital management system deployed with enhanced security', '127.0.0.1');

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
SELECT 'NEW DEFAULT LOGIN CREDENTIALS:' AS '';
SELECT '-----------------------------------------' AS '';
SELECT 'ADMIN ACCESS:' AS '';
SELECT '  Username: superadmin' AS '';
SELECT '  Password: SecurePass123!' AS '';
SELECT '  Username: itadmin' AS '';
SELECT '  Password: SecurePass123!' AS '';
SELECT '' AS '';
SELECT 'DOCTOR ACCESS:' AS '';
SELECT '  Username: dr.patel (Cardiology)' AS '';
SELECT '  Username: dr.martinez (Pediatrics)' AS '';
SELECT '  Username: dr.kim (Neurology)' AS '';
SELECT '  Username: dr.osei (Orthopedics)' AS '';
SELECT '  Username: dr.wilson (Dermatology)' AS '';
SELECT '  Password: SecurePass123! (for all doctors)' AS '';
SELECT '' AS '';
SELECT 'PATIENT ACCESS:' AS '';
SELECT '  Username: emma.thompson' AS '';
SELECT '  Username: james.wilson' AS '';
SELECT '  Username: sophia.rodriguez' AS '';
SELECT '  Username: liam.nguyen' AS '';
SELECT '  Username: olivia.brown' AS '';
SELECT '  Username: noah.davis' AS '';
SELECT '  Username: mia.garcia' AS '';
SELECT '  Password: SecurePass123! (for all patients)' AS '';
SELECT '-----------------------------------------' AS '';

-- List all users for verification
SELECT '' AS '';
SELECT 'All Registered Users:' AS '';
SELECT id, username, email, full_name, role, is_active, email_verified FROM users ORDER BY role, username;