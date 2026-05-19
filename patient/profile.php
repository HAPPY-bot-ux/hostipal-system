<?php
// patient/profile.php - Patient Profile with modern medical UI
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
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($full_name)) {
        $error = "Full name is required.";
    } else {
        // Check if email already exists for another user
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
                // Refresh user data
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                // Update session name
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
        // Verify current password
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Profile | MediFlow HMS</title>
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

        /* Modern Navbar */
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

        .logo i {
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
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

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Profile Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 28px;
            padding: 2rem 1.5rem;
            text-align: center;
            border: 1px solid rgba(37, 99, 235, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .profile-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .profile-role {
            display: inline-block;
            background: #dbeafe;
            color: #2563eb;
            padding: 0.25rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .member-since {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #64748b;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 16px;
            margin: 1rem 0;
        }

        /* Stats in Sidebar */
        .sidebar-stats {
            text-align: left;
            margin-top: 1rem;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eef2ff;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-row span:first-child {
            color: #64748b;
            font-size: 0.8rem;
        }

        .stat-row span:last-child {
            font-weight: 700;
            color: #1e293b;
        }

        /* Main Content Cards */
        .card {
            background: white;
            border-radius: 28px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #eef2ff;
        }

        .card-header i {
            font-size: 1.25rem;
            color: #2563eb;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
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
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
        }

        .form-label i {
            color: #2563eb;
            width: 18px;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            color: #1e293b;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        .form-control:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tabs */
        .profile-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .tab-btn:hover:not(.active) {
            color: #1e293b;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Password Requirements */
        .password-requirements {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .requirements-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .requirement {
            font-size: 0.7rem;
            color: #94a3b8;
            margin: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirement.valid {
            color: #10b981;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                position: static;
                margin-bottom: 1.5rem;
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
            .card {
                padding: 1.25rem;
            }
            .profile-tabs {
                flex-wrap: wrap;
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h3 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <span class="profile-role"><i class="fas fa-user-injured"></i> Patient</span>
                
                <div class="member-since">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                </div>
                
                <div class="sidebar-stats">
                    <div class="stat-row">
                        <span><i class="fas fa-calendar-check"></i> Total Appointments</span>
                        <span><?php echo $stats['total_appointments'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row">
                        <span><i class="fas fa-check-circle"></i> Completed Visits</span>
                        <span><?php echo $stats['completed'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row">
                        <span><i class="fas fa-clock"></i> Pending</span>
                        <span><?php echo $stats['pending'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row">
                        <span><i class="fas fa-calendar-week"></i> Upcoming</span>
                        <span><?php echo $stats['upcoming'] ?? 0; ?></span>
                    </div>
                    <?php if ($last_appointment): ?>
                    <div class="stat-row">
                        <span><i class="fas fa-history"></i> Last Visit</span>
                        <span><?php echo date('M d, Y', strtotime($last_appointment['appointment_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Main Content -->
            <div>
                <div class="card">
                    <div class="profile-tabs">
                        <button class="tab-btn active" data-tab="personal">
                            <i class="fas fa-user-edit"></i> Personal Info
                        </button>
                        <button class="tab-btn" data-tab="security">
                            <i class="fas fa-lock"></i> Security
                        </button>
                        <button class="tab-btn" data-tab="health">
                            <i class="fas fa-heartbeat"></i> Health Summary
                        </button>
                    </div>
                    
                    <!-- Personal Info Tab -->
                    <div id="personal" class="tab-pane active">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <textarea name="address" class="form-control" rows="3" placeholder="Your full address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tag"></i> Username
                                </label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small style="color: #94a3b8; font-size: 0.7rem;">Username cannot be changed</small>
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
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-key"></i> New Password
                                </label>
                                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-check-circle"></i> Confirm New Password
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            </div>
                            
                            <div class="password-requirements">
                                <div class="requirements-title">Password Requirements:</div>
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
                    
                    <!-- Health Summary Tab -->
                    <div id="health" class="tab-pane">
                        <div style="text-align: center; padding: 1rem 0;">
                            <i class="fas fa-chart-line" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
                            <h3 style="color: #1e293b; margin-bottom: 0.5rem;">Health Dashboard Coming Soon</h3>
                            <p style="color: #64748b; margin-bottom: 1.5rem;">Track your health metrics, view trends, and get personalized insights.</p>
                            <a href="medical-records.php" class="btn btn-outline">
                                <i class="fas fa-notes-medical"></i> View Medical Records
                            </a>
                        </div>
                        
                        <!-- Quick health tips -->
                        <div style="margin-top: 1.5rem; background: #f0fdf4; border-radius: 20px; padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-leaf" style="color: #22c55e;"></i>
                                <strong style="color: #166534;">Health Tip</strong>
                            </div>
                            <p style="color: #475569; font-size: 0.85rem;">Regular health check-ups can help detect potential issues early. Schedule your next preventive visit today!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update active pane
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Password validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');
        
        function validatePassword() {
            // Length check
            if (newPassword.value.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            
            // Match check
            if (confirmPassword.value.length > 0 && newPassword.value === confirmPassword.value) {
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }
        
        if (newPassword) {
            newPassword.addEventListener('input', validatePassword);
        }
        if (confirmPassword) {
            confirmPassword.addEventListener('input', validatePassword);
        }
    </script>
</body>
</html>