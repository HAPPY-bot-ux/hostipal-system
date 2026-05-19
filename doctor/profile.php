<?php
// doctor/profile.php - Manage doctor profile and view patient profiles with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check doctor role
SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor complete profile
$doctorQuery = "SELECT d.*, u.username, u.email, u.full_name, u.phone, u.address, 
                u.created_at as registered_date, u.last_login, u.is_active
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = htmlspecialchars(strip_tags(trim($_POST['full_name'])));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = htmlspecialchars(strip_tags(trim($_POST['phone'])));
        $address = htmlspecialchars(strip_tags(trim($_POST['address'])));
        $specialization = htmlspecialchars(strip_tags(trim($_POST['specialization'])));
        $qualification = htmlspecialchars(strip_tags(trim($_POST['qualification'])));
        $experience_years = intval($_POST['experience_years']);
        $bio = htmlspecialchars(strip_tags(trim($_POST['bio'])));
        $languages = htmlspecialchars(strip_tags(trim($_POST['languages'])));
        
        if (empty($full_name) || empty($email)) {
            $error = "Full name and email are required!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        } else {
            // Update users table
            $updateUserQuery = "UPDATE users SET 
                                full_name = :full_name, 
                                email = :email, 
                                phone = :phone, 
                                address = :address 
                                WHERE id = :user_id";
            $updateUserStmt = $db->prepare($updateUserQuery);
            $updateUserStmt->bindParam(':full_name', $full_name);
            $updateUserStmt->bindParam(':email', $email);
            $updateUserStmt->bindParam(':phone', $phone);
            $updateUserStmt->bindParam(':address', $address);
            $updateUserStmt->bindParam(':user_id', $user_id);
            
            // Update doctors table
            $updateDoctorQuery = "UPDATE doctors SET 
                                  specialization = :specialization,
                                  qualification = :qualification,
                                  experience_years = :experience_years,
                                  bio = :bio,
                                  languages = :languages
                                  WHERE user_id = :user_id";
            $updateDoctorStmt = $db->prepare($updateDoctorQuery);
            $updateDoctorStmt->bindParam(':specialization', $specialization);
            $updateDoctorStmt->bindParam(':qualification', $qualification);
            $updateDoctorStmt->bindParam(':experience_years', $experience_years);
            $updateDoctorStmt->bindParam(':bio', $bio);
            $updateDoctorStmt->bindParam(':languages', $languages);
            $updateDoctorStmt->bindParam(':user_id', $user_id);
            
            if ($updateUserStmt->execute() && $updateDoctorStmt->execute()) {
                $success = "Profile updated successfully!";
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                
                // Refresh doctor data
                $stmt->execute();
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log the action
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                            VALUES (:user_id, :action, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $log_action = "Profile Updated";
                $log_details = "Doctor updated their profile information";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':user_id', $user_id);
                $logStmt->bindParam(':action', $log_action);
                $logStmt->bindParam(':details', $log_details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required!";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long!";
        } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number!";
        } else {
            // Verify current password
            $passwordQuery = "SELECT password FROM users WHERE id = :user_id";
            $passwordStmt = $db->prepare($passwordQuery);
            $passwordStmt->bindParam(':user_id', $user_id);
            $passwordStmt->execute();
            $user = $passwordStmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user['password'])) {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE users SET password = :password WHERE id = :user_id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':password', $hashedPassword);
                $updateStmt->bindParam(':user_id', $user_id);
                
                if ($updateStmt->execute()) {
                    $success = "Password changed successfully!";
                    
                    // Log the action
                    $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                                VALUES (:user_id, :action, :details, :ip)";
                    $logStmt = $db->prepare($logQuery);
                    $log_action = "Password Changed";
                    $log_details = "Doctor changed their password";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $logStmt->bindParam(':user_id', $user_id);
                    $logStmt->bindParam(':action', $log_action);
                    $logStmt->bindParam(':details', $log_details);
                    $logStmt->bindParam(':ip', $ip);
                    $logStmt->execute();
                } else {
                    $error = "Failed to change password.";
                }
            } else {
                $error = "Current password is incorrect!";
            }
        }
    }
}

// Get patient ID if viewing patient profile
$view_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
$view_patient = null;
$patient_appointments = [];
$patient_medical_records = [];

if ($view_patient_id) {
    // Get patient details
    $patientQuery = "SELECT * FROM users WHERE id = :patient_id AND role = 'patient'";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':patient_id', $view_patient_id);
    $patientStmt->execute();
    $view_patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($view_patient) {
        // Get patient appointments with this doctor
        $appointmentsQuery = "SELECT a.*, d.specialization 
                             FROM appointments a
                             JOIN doctors d ON a.doctor_id = d.id
                             WHERE a.patient_id = :patient_id AND a.doctor_id = :doctor_id
                             ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $appointmentsStmt = $db->prepare($appointmentsQuery);
        $appointmentsStmt->bindParam(':patient_id', $view_patient_id);
        $appointmentsStmt->bindParam(':doctor_id', $doctor['id']);
        $appointmentsStmt->execute();
        $patient_appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get patient medical records from this doctor
        $recordsQuery = "SELECT * FROM medical_records 
                        WHERE patient_id = :patient_id AND doctor_id = :doctor_id
                        ORDER BY record_date DESC, created_at DESC";
        $recordsStmt = $db->prepare($recordsQuery);
        $recordsStmt->bindParam(':patient_id', $view_patient_id);
        $recordsStmt->bindParam(':doctor_id', $user_id);
        $recordsStmt->execute();
        $patient_medical_records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get doctor's patients list for quick access
$patientsListQuery = "SELECT DISTINCT u.id, u.full_name, u.email, u.phone,
                      MAX(a.appointment_date) as last_visit,
                      COUNT(a.id) as total_visits
                      FROM users u
                      JOIN appointments a ON u.id = a.patient_id
                      WHERE a.doctor_id = :doctor_id
                      GROUP BY u.id
                      ORDER BY last_visit DESC
                      LIMIT 15";
$patientsListStmt = $db->prepare($patientsListQuery);
$patientsListStmt->bindParam(':doctor_id', $doctor['id']);
$patientsListStmt->execute();
$doctor_patients = $patientsListStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $view_patient ? 'Patient Profile' : 'My Profile'; ?> | MediFlow HMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
            min-height: 100vh;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            list-style: none;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover, .nav-link.active {
            color: #2563eb;
            background: #eff6ff;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            color: #64748b;
        }

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }

        .card-header {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .card-header i {
            color: #2563eb;
        }

        /* Profile Avatar */
        .profile-avatar {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
        }

        .profile-name {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .profile-role {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.8rem;
        }

        .form-label i {
            color: #2563eb;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 0.7rem 1.25rem;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
        }

        /* Detail Rows */
        .detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-label {
            font-weight: 600;
            width: 120px;
            color: #475569;
            font-size: 0.8rem;
        }

        .detail-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.85rem;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            gap: 0.3rem;
        }

        .badge-success {
            background: #d1fae5;
            color: #059669;
        }

        .badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-info {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Patient List */
        .patient-list {
            max-height: 380px;
            overflow-y: auto;
        }

        .patient-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 16px;
        }

        .patient-item:hover {
            background: #f8fafc;
            transform: translateX(3px);
        }

        .patient-avatar-sm {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Table */
        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table th {
            font-weight: 600;
            color: #64748b;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover td {
            background: #f8fafc;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 968px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
                padding: 0 1rem;
            }
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            .container {
                padding: 0 1rem;
            }
            .glass-card {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heartbeat"></i>
                <span>MediFlow HMS</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link active"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <?php if ($view_patient): ?>
                <!-- Patient Profile View -->
                <div class="page-header">
                    <a href="profile.php" class="btn btn-outline btn-sm" style="margin-bottom: 1rem;">
                        <i class="fas fa-arrow-left"></i> Back to My Profile
                    </a>
                    <h1><i class="fas fa-user-injured"></i> Patient Profile</h1>
                    <p>View patient information, medical history, and appointment records</p>
                </div>
                
                <div class="profile-grid">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-user-circle"></i> Patient Information</div>
                        <div style="text-align:center;">
                            <div class="profile-avatar" style="background: linear-gradient(135deg, #10b981, #059669); margin:0 auto 1rem;">
                                <?php echo strtoupper(substr($view_patient['full_name'], 0, 1)); ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($view_patient['full_name']); ?></div>
                            <div class="profile-role">Patient since <?php echo date('F Y', strtotime($view_patient['created_at'])); ?></div>
                        </div>
                        <div style="margin-top:1rem;">
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-envelope"></i> Email:</div><div class="detail-value"><?php echo htmlspecialchars($view_patient['email']); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-phone"></i> Phone:</div><div class="detail-value"><?php echo htmlspecialchars($view_patient['phone'] ?? 'N/A'); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-map-marker-alt"></i> Address:</div><div class="detail-value"><?php echo htmlspecialchars($view_patient['address'] ?? 'N/A'); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-calendar"></i> Registered:</div><div class="detail-value"><?php echo date('F d, Y', strtotime($view_patient['created_at'])); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-circle"></i> Status:</div><div class="detail-value"><span class="badge badge-success">Active</span></div></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fas fa-chart-line"></i> Visit Statistics</div>
                        <div style="text-align:center; padding:0.5rem;">
                            <div style="font-size:3rem; font-weight:700; color:#2563eb;"><?php echo count($patient_appointments); ?></div>
                            <div style="color:#64748b;">Total Appointments</div>
                        </div>
                        <div style="margin-top:0.5rem;">
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-check-double"></i> Completed:</div><div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'completed')); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-clock"></i> Pending:</div><div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'pending')); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-ban"></i> Cancelled:</div><div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'cancelled')); ?></div></div>
                        </div>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="card" style="margin-top:1.5rem;">
                    <div class="card-header">
                        <i class="fas fa-notes-medical"></i> Medical Records
                        <button class="btn btn-primary btn-sm" style="margin-left:auto;" onclick="location.href='add-record.php?patient_id=<?php echo $view_patient_id; ?>'"><i class="fas fa-plus"></i> Add Record</button>
                    </div>
                    <?php if (count($patient_medical_records) > 0): ?>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>Diagnosis</th><th>Prescription</th><th>BP</th><th>HR</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($patient_medical_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($record['diagnosis'] ?? '', 0, 40)); ?></td>
                                        <td><?php echo htmlspecialchars(substr($record['prescription'] ?? '', 0, 40)); ?></td>
                                        <td><?php echo htmlspecialchars($record['blood_pressure'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['heart_rate'] ?? 'N/A'); ?></td>
                                        <td><button class="btn btn-outline btn-sm" onclick="viewRecord(<?php echo $record['id']; ?>)"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:2rem; color:#94a3b8;"><i class="fas fa-folder-open" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>No medical records found.</div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Doctor Profile View -->
                <div class="page-header">
                    <h1><i class="fas fa-user-md"></i> My Profile</h1>
                    <p>Manage your professional profile and account settings</p>
                </div>
                
                <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div><?php endif; ?>

                <div class="profile-grid">
                    <div>
                        <div class="card">
                            <div class="card-header"><i class="fas fa-chart-pie"></i> Professional Summary</div>
                            <div style="text-align:center;">
                                <div class="profile-avatar"><?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?></div>
                                <div class="profile-name">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></div>
                                <div class="profile-role"><?php echo htmlspecialchars($doctor['specialization']); ?> Specialist</div>
                            </div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-star"></i> Experience:</div><div class="detail-value"><?php echo $doctor['experience_years']; ?>+ years</div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification:</div><div class="detail-value"><?php echo htmlspecialchars($doctor['qualification']); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-dollar-sign"></i> Consultation Fee:</div><div class="detail-value">$<?php echo number_format($doctor['consultation_fee'], 2); ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-clock"></i> Working Hours:</div><div class="detail-value"><?php echo $doctor['available_time_start'] ? date('h:i A', strtotime($doctor['available_time_start'])) . ' - ' . date('h:i A', strtotime($doctor['available_time_end'])) : 'Not set'; ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-calendar-week"></i> Working Days:</div><div class="detail-value"><?php echo $doctor['available_days'] ?: 'Not set'; ?></div></div>
                            <div class="detail-row"><div class="detail-label"><i class="fas fa-calendar-alt"></i> Member Since:</div><div class="detail-value"><?php echo date('F d, Y', strtotime($doctor['registered_date'])); ?></div></div>
                        </div>

                        <div class="card" style="margin-top:1.5rem;">
                            <div class="card-header"><i class="fas fa-users"></i> Recent Patients <a href="patients.php" class="btn btn-outline btn-sm" style="margin-left:auto;">View All</a></div>
                            <div class="patient-list">
                                <?php if (count($doctor_patients) > 0): ?>
                                    <?php foreach ($doctor_patients as $patient): ?>
                                        <div class="patient-item" onclick="location.href='profile.php?patient_id=<?php echo $patient['id']; ?>'">
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <div class="patient-avatar-sm"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                                <div><strong><?php echo htmlspecialchars($patient['full_name']); ?></strong><br><small><?php echo htmlspecialchars($patient['email']); ?></small></div>
                                            </div>
                                            <div><span class="badge badge-info"><?php echo $patient['total_visits']; ?> visits</span></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align:center; padding:2rem; color:#94a3b8;"><i class="fas fa-user-friends" style="font-size:2rem;"></i><p>No patients yet</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card">
                            <div class="card-header"><i class="fas fa-edit"></i> Edit Profile</div>
                            <form method="POST">
                                <div class="form-group"><label class="form-label"><i class="fas fa-user"></i> Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($doctor['full_name']); ?>" required></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-envelope"></i> Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-phone"></i> Phone</label><input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>"></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label><textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($doctor['address'] ?? ''); ?></textarea></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-stethoscope"></i> Specialization</label><input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-graduation-cap"></i> Qualification</label><input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-briefcase"></i> Experience (Years)</label><input type="number" name="experience_years" class="form-control" value="<?php echo $doctor['experience_years']; ?>" min="0" max="50"></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-comment"></i> Bio / About</label><textarea name="bio" class="form-control" rows="2" placeholder="Brief professional bio..."><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-language"></i> Languages Spoken</label><input type="text" name="languages" class="form-control" value="<?php echo htmlspecialchars($doctor['languages'] ?? ''); ?>" placeholder="e.g., English, Spanish"></div>
                                <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </form>
                        </div>

                        <div class="card" style="margin-top:1.5rem;">
                            <div class="card-header"><i class="fas fa-key"></i> Change Password</div>
                            <form method="POST" onsubmit="return validatePassword()">
                                <div class="form-group"><label class="form-label"><i class="fas fa-lock"></i> Current Password</label><input type="password" name="current_password" id="current_password" class="form-control" required></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-key"></i> New Password</label><input type="password" name="new_password" id="new_password" class="form-control" required><small style="color:#64748b;">Min 8 chars, uppercase, lowercase, number</small></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label><input type="password" name="confirm_password" id="confirm_password" class="form-control" required></div>
                                <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewRecord(recordId) { window.location.href = `view-record.php?id=${recordId}`; }
        function viewAppointment(id) { window.location.href = `view-appointment.php?id=${id}`; }
        
        function validatePassword() {
            let pwd = document.getElementById('new_password');
            let confirm = document.getElementById('confirm_password');
            if (pwd.value !== confirm.value) { alert('Passwords do not match!'); return false; }
            if (pwd.value.length < 8) { alert('Password must be at least 8 characters!'); return false; }
            if (!/[A-Z]/.test(pwd.value) || !/[a-z]/.test(pwd.value) || !/[0-9]/.test(pwd.value)) {
                alert('Password must contain uppercase, lowercase, and number!'); return false;
            }
            return true;
        }
    </script>
</body>
</html>