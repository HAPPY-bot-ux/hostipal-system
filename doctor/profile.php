<?php
// doctor/profile.php - Completely Redesigned Doctor Profile Interface
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

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
                $_SESSION['full_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $stmt->execute();
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                            VALUES (:user_id, :action, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $logStmt->bindParam(':user_id', $user_id);
                $log_action = "Profile Updated";
                $logStmt->bindParam(':action', $log_action);
                $log_details = "Doctor updated their profile information";
                $logStmt->bindParam(':details', $log_details);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
    
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
                    
                    $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                                VALUES (:user_id, :action, :details, :ip)";
                    $logStmt = $db->prepare($logQuery);
                    $logStmt->bindParam(':user_id', $user_id);
                    $log_action = "Password Changed";
                    $logStmt->bindParam(':action', $log_action);
                    $log_details = "Doctor changed their password";
                    $logStmt->bindParam(':details', $log_details);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
    $patientQuery = "SELECT * FROM users WHERE id = :patient_id AND role = 'patient'";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':patient_id', $view_patient_id);
    $patientStmt->execute();
    $view_patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($view_patient) {
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
                      LIMIT 10";
$patientsListStmt = $db->prepare($patientsListQuery);
$patientsListStmt->bindParam(':doctor_id', $doctor['id']);
$patientsListStmt->execute();
$doctor_patients = $patientsListStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view_patient ? 'Patient Profile' : 'My Profile'; ?> | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0A0C15;
            --surface-card: rgba(18, 22, 33, 0.75);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #0EA5E9;
            --primary-dark: #0284C7;
            --primary-glow: rgba(14, 165, 233, 0.2);
            --accent: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            position: relative;
        }

        .bg-orb-1 {
            position: fixed;
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
            animation: float 20s ease-in-out infinite;
        }

        .bg-orb-2 {
            position: fixed;
            width: 500px;
            height: 500px;
            bottom: -150px;
            left: -150px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10, 12, 21, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 0;
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            list-style: none;
            flex-wrap: wrap;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(14, 165, 233, 0.1);
        }

        .profile-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        .top-bar {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .top-bar p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 14px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .glass-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(14, 165, 233, 0.03);
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Profile Avatar */
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .profile-name {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .profile-role {
            text-align: center;
            font-size: 0.75rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        /* Detail Rows */
        .detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-label {
            width: 110px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .detail-value {
            flex: 1;
            font-size: 0.85rem;
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
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label i {
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.85rem;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 70px;
        }

        .btn {
            padding: 0.7rem 1.25rem;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
            width: auto;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 40px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }

        .badge-info {
            background: rgba(14, 165, 233, 0.15);
            color: #7DD3FC;
        }

        /* Patient List */
        .patient-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .patient-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .patient-item:hover {
            background: rgba(14, 165, 233, 0.08);
            transform: translateX(3px);
        }

        .patient-avatar-sm {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #059669);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.75rem;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .data-table th {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover td {
            background: rgba(14, 165, 233, 0.05);
        }

        /* Stats Circle */
        .stats-circle {
            text-align: center;
            padding: 1rem;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid var(--accent);
            color: #A7F3D0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid var(--danger);
            color: #FCA5A5;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 900px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                padding: 0 1rem;
            }
            .nav-menu {
                justify-content: center;
            }
            .profile-wrapper {
                padding: 0 1rem;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="bg-orb-1"></div>
    <div class="bg-orb-2"></div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heart-pulse"></i>
                <span>Hospital System</span>
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

    <div class="profile-wrapper">
        <?php if ($view_patient): ?>
            <!-- Patient Profile View -->
            <div class="top-bar">
                <div>
                    <h1><i class="fas fa-user-injured"></i> Patient Profile</h1>
                    <p>View patient information and medical history</p>
                </div>
                <a href="profile.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to My Profile</a>
            </div>

            <?php if ($view_patient): ?>
                <div class="two-columns">
                    <!-- Left Column: Patient Info -->
                    <div class="glass-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-circle"></i> Patient Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-avatar" style="background: linear-gradient(135deg, var(--accent), #059669);">
                                <?php echo strtoupper(substr($view_patient['full_name'], 0, 1)); ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($view_patient['full_name']); ?></div>
                            <div class="profile-role">Patient since <?php echo date('M Y', strtotime($view_patient['created_at'])); ?></div>
                            
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-envelope"></i> Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($view_patient['email']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-phone"></i> Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($view_patient['phone'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                                <div class="detail-value"><?php echo htmlspecialchars($view_patient['address'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-calendar"></i> Registered</div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($view_patient['created_at'])); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-circle"></i> Status</div>
                                <div class="detail-value"><span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Statistics -->
                    <div class="glass-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Visit Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-circle">
                                <div class="stats-number"><?php echo count($patient_appointments); ?></div>
                                <div style="color: var(--text-muted); font-size: 0.8rem;">Total Appointments</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-check-double"></i> Completed</div>
                                <div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'completed')); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-clock"></i> Pending</div>
                                <div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'pending')); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-ban"></i> Cancelled</div>
                                <div class="detail-value"><?php echo count(array_filter($patient_appointments, fn($a) => $a['status'] == 'cancelled')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="glass-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-notes-medical"></i> Medical Records</h3>
                        <a href="add-record.php?patient_id=<?php echo $view_patient_id; ?>" class="btn-primary btn-sm" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;"><i class="fas fa-plus"></i> Add Record</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($patient_medical_records) > 0): ?>
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr><th>Date</th><th>Diagnosis</th><th>Prescription</th><th>BP</th><th>HR</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patient_medical_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                                                <td><?php echo htmlspecialchars(substr($record['diagnosis'] ?? '', 0, 35)); ?></td>
                                                <td><?php echo htmlspecialchars(substr($record['prescription'] ?? '', 0, 35)); ?></td>
                                                <td><?php echo htmlspecialchars($record['blood_pressure'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($record['heart_rate'] ?? '—'); ?></td>
                                                <td><button class="btn-outline btn-sm" onclick="viewRecord(<?php echo $record['id']; ?>)" style="background: transparent;"><i class="fas fa-eye"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                No medical records found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Doctor Profile View -->
            <div class="top-bar">
                <div>
                    <h1><i class="fas fa-user-md"></i> My Profile</h1>
                    <p>Manage your professional profile and account settings</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <div class="two-columns">
                <!-- Left Column: Profile Info & Patients -->
                <div>
                    <div class="glass-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-circle"></i> Professional Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-avatar"><?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?></div>
                            <div class="profile-name">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></div>
                            <div class="profile-role"><?php echo htmlspecialchars($doctor['specialization']); ?> Specialist</div>
                            
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-star"></i> Experience</div>
                                <div class="detail-value"><?php echo $doctor['experience_years']; ?>+ years</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification</div>
                                <div class="detail-value"><?php echo htmlspecialchars($doctor['qualification']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-dollar-sign"></i> Fee</div>
                                <div class="detail-value">R<?php echo number_format($doctor['consultation_fee'], 2); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-clock"></i> Working Hours</div>
                                <div class="detail-value"><?php echo $doctor['available_time_start'] ? date('h:i A', strtotime($doctor['available_time_start'])) . ' - ' . date('h:i A', strtotime($doctor['available_time_end'])) : 'Not set'; ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-calendar-week"></i> Working Days</div>
                                <div class="detail-value"><?php echo $doctor['available_days'] ?: 'Not set'; ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-language"></i> Languages</div>
                                <div class="detail-value"><?php echo htmlspecialchars($doctor['languages'] ?? 'English'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-calendar-alt"></i> Member Since</div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($doctor['registered_date'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> Recent Patients</h3>
                            <a href="patients.php" class="btn-outline btn-sm" style="text-decoration: none;">View All →</a>
                        </div>
                        <div class="card-body">
                            <div class="patient-list">
                                <?php if (count($doctor_patients) > 0): ?>
                                    <?php foreach ($doctor_patients as $patient): ?>
                                        <div class="patient-item" onclick="location.href='profile.php?patient_id=<?php echo $patient['id']; ?>'">
                                            <div style="display: flex; align-items: center;">
                                                <div class="patient-avatar-sm"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                                <div>
                                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                                    <div style="font-size: 0.65rem; color: var(--text-muted);"><?php echo htmlspecialchars($patient['email']); ?></div>
                                                </div>
                                            </div>
                                            <span class="badge badge-info"><?php echo $patient['total_visits']; ?> visits</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        <i class="fas fa-user-friends" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                        No patients yet
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Edit Forms -->
                <div>
                    <div class="glass-card">
                        <div class="card-header">
                            <h3><i class="fas fa-edit"></i> Edit Profile</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($doctor['full_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-phone"></i> Phone</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($doctor['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-stethoscope"></i> Specialization</label>
                                    <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-graduation-cap"></i> Qualification</label>
                                    <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-briefcase"></i> Experience (Years)</label>
                                    <input type="number" name="experience_years" class="form-control" value="<?php echo $doctor['experience_years']; ?>" min="0" max="50">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-comment"></i> Bio / About</label>
                                    <textarea name="bio" class="form-control" rows="2" placeholder="Brief professional bio..."><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-language"></i> Languages Spoken</label>
                                    <input type="text" name="languages" class="form-control" value="<?php echo htmlspecialchars($doctor['languages'] ?? ''); ?>" placeholder="e.g., English, Spanish">
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </form>
                        </div>
                    </div>

                    <div class="glass-card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3><i class="fas fa-key"></i> Change Password</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return validatePassword()">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-key"></i> New Password</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                                    <small style="color: var(--text-muted); font-size: 0.65rem;">Min 8 chars, uppercase, lowercase, number</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Set doctor theme
        document.documentElement.style.setProperty('--primary', '#0EA5E9');
        document.documentElement.style.setProperty('--primary-dark', '#0284C7');

        function viewRecord(recordId) {
            window.location.href = `view-record.php?id=${recordId}`;
        }

        function validatePassword() {
            let pwd = document.getElementById('new_password');
            let confirm = document.getElementById('confirm_password');
            
            if (pwd.value !== confirm.value) {
                alert('Passwords do not match!');
                return false;
            }
            if (pwd.value.length < 8) {
                alert('Password must be at least 8 characters!');
                return false;
            }
            if (!/[A-Z]/.test(pwd.value) || !/[a-z]/.test(pwd.value) || !/[0-9]/.test(pwd.value)) {
                alert('Password must contain uppercase, lowercase, and number!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>