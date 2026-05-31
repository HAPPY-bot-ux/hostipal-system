<?php
// patient/profile.php - Completely Redesigned Patient Profile Interface
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$error = '';
$success = '';

// Fetch current user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch appointment statistics
$statsQuery = "SELECT 
    COUNT(*) as total_appointments,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as upcoming
    FROM appointments WHERE patient_id = :user_id";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->bindParam(':user_id', $user_id);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch last appointment date
$lastApptQuery = "SELECT appointment_date FROM appointments 
                  WHERE patient_id = :user_id AND status = 'completed' 
                  ORDER BY appointment_date DESC LIMIT 1";
$lastApptStmt = $db->prepare($lastApptQuery);
$lastApptStmt->bindParam(':user_id', $user_id);
$lastApptStmt->execute();
$last_appointment = $lastApptStmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = htmlspecialchars(strip_tags(trim($_POST['full_name'])));
    $phone = htmlspecialchars(strip_tags(trim($_POST['phone'])));
    $address = htmlspecialchars(strip_tags(trim($_POST['address'])));
    $email = htmlspecialchars(strip_tags(trim($_POST['email'])));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->bindParam(':user_id', $user_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $error = "Email address is already used by another account.";
        } else {
            $updateQuery = "UPDATE users SET full_name = :full_name, phone = :phone, address = :address, email = :email WHERE id = :user_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':full_name', $full_name);
            $updateStmt->bindParam(':phone', $phone);
            $updateStmt->bindParam(':address', $address);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->bindParam(':user_id', $user_id);
            
            if ($updateStmt->execute()) {
                $success = "Profile updated successfully!";
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['full_name'] = $full_name;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $passQuery = "SELECT password FROM users WHERE id = :user_id";
        $passStmt = $db->prepare($passQuery);
        $passStmt->bindParam(':user_id', $user_id);
        $passStmt->execute();
        $user_pass = $passStmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user_pass['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePassQuery = "UPDATE users SET password = :password WHERE id = :user_id";
            $updatePassStmt = $db->prepare($updatePassQuery);
            $updatePassStmt->bindParam(':password', $hashed_password);
            $updatePassStmt->bindParam(':user_id', $user_id);
            
            if ($updatePassStmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Design Variables */
        :root {
            --bg-main: #0A0C15;
            --surface-card: rgba(18, 22, 33, 0.75);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #6366F1;
            --primary-dark: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.2);
            --accent: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
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

        /* Animated Background */
        .bg-orb-1 {
            position: fixed;
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, transparent 70%);
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

        /* Navbar */
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
            background: rgba(99, 102, 241, 0.1);
        }

        /* Main Layout */
        .profile-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        /* Top Bar */
        .top-bar {
            margin-bottom: 2rem;
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

        /* Two Column Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
        }

        /* Left Panel - Profile Card */
        .profile-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 1.5rem;
            text-align: center;
            position: sticky;
            top: 90px;
        }

        .avatar {
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

        .profile-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
        }

        .role-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.15);
            padding: 0.25rem 1rem;
            border-radius: 40px;
            font-size: 0.7rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .member-since {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 0.75rem;
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .stats-list {
            text-align: left;
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .stat-value {
            font-weight: 700;
            color: var(--primary);
        }

        /* Right Panel - Tabs */
        .tabs-container {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            background: rgba(0, 0, 0, 0.2);
        }

        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .tab-pane {
            display: none;
            padding: 1.75rem;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
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
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            font-size: 0.9rem;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 14px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Password Requirements */
        .requirements-box {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .requirements-box h4 {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .requirement {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem 0;
        }

        .requirement.valid {
            color: var(--accent);
        }

        /* Health Summary */
        .health-summary {
            text-align: center;
            padding: 2rem;
        }

        .health-icon {
            font-size: 3rem;
            color: var(--primary);
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .health-tip {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 20px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: left;
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
            .profile-layout {
                grid-template-columns: 1fr;
            }
            .profile-card {
                position: static;
            }
            .tabs-header {
                flex-wrap: wrap;
            }
            .tab-btn {
                flex: auto;
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
            .tab-pane {
                padding: 1.25rem;
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="profile-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your personal information and account settings</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-layout">
            <!-- Left Panel -->
            <div class="profile-card">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <span class="role-badge"><i class="fas fa-user-injured"></i> Patient</span>
                
                <div class="member-since">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                </div>

                <div class="stats-list">
                    <div class="stat-item">
                        <span class="stat-label">Total Appointments</span>
                        <span class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Completed Visits</span>
                        <span class="stat-value"><?php echo $stats['completed'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Upcoming</span>
                        <span class="stat-value"><?php echo $stats['upcoming'] ?? 0; ?></span>
                    </div>
                    <?php if ($last_appointment): ?>
                    <div class="stat-item">
                        <span class="stat-label">Last Visit</span>
                        <span class="stat-value"><?php echo date('M d, Y', strtotime($last_appointment['appointment_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="personal">
                        <i class="fas fa-user-edit"></i> Personal Info
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-lock"></i> Security
                    </button>
                    <button class="tab-btn" data-tab="health">
                        <i class="fas fa-heartbeat"></i> Health
                    </button>
                </div>

                <!-- Personal Info Tab -->
                <div id="personal" class="tab-pane active">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Your full address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user-tag"></i> Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small style="color: var(--text-muted); font-size: 0.7rem;">Username cannot be changed</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security" class="tab-pane">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-key"></i> New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                        </div>
                        
                        <div class="requirements-box">
                            <h4>Password Requirements:</h4>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="req-match">
                                <i class="fas fa-circle"></i> Passwords match
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Update Password
                        </button>
                    </form>
                </div>

                <!-- Health Tab -->
                <div id="health" class="tab-pane">
                    <div class="health-summary">
                        <div class="health-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem;">Health Dashboard</h3>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Track your health metrics and view insights</p>
                        
                        <a href="medical-records.php" class="btn btn-outline">
                            <i class="fas fa-notes-medical"></i> View Medical Records
                        </a>
                        
                        <div class="health-tip">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-leaf" style="color: var(--accent);"></i>
                                <strong>Health Tip</strong>
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Regular health check-ups help detect issues early. Schedule your next preventive visit today!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Password validation
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');

        function validatePassword() {
            if (newPass.value.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            
            if (confirmPass.value.length > 0 && newPass.value === confirmPass.value) {
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }

        if (newPass) newPass.addEventListener('input', validatePassword);
        if (confirmPass) confirmPass.addEventListener('input', validatePassword);
    </script>
</body>
</html>