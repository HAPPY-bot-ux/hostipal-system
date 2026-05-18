<?php
// admin/system-settings.php - System settings management
require_once '../config/database.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Auth.php';

// Start session and check admin role
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle settings actions
$error = '';
$success = '';

// Get current admin user
$admin_id = SessionManager::getUserId();
$admin_query = "SELECT * FROM users WHERE id = :id";
$admin_stmt = $db->prepare($admin_query);
$admin_stmt->bindParam(':id', $admin_id);
$admin_stmt->execute();
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = htmlspecialchars(strip_tags(trim($_POST['full_name'])));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(strip_tags(trim($_POST['phone'])));
    $address = htmlspecialchars(strip_tags(trim($_POST['address'])));
    
    if (empty($full_name) || empty($email)) {
        $error = "Full name and email are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } else {
        $updateQuery = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':full_name', $full_name);
        $updateStmt->bindParam(':email', $email);
        $updateStmt->bindParam(':phone', $phone);
        $updateStmt->bindParam(':address', $address);
        $updateStmt->bindParam(':id', $admin_id);
        
        if ($updateStmt->execute()) {
            $success = "Profile updated successfully!";
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            
            // Refresh admin data
            $admin_stmt->execute();
            $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = $admin_id;
            $log_action = "Profile Updated";
            $log_details = "Admin profile was updated";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
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
        $result = $auth->changePassword($admin_id, $current_password, $new_password);
        if ($result['success']) {
            $success = $result['message'];
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = $admin_id;
            $log_action = "Password Changed";
            $log_details = "Admin password was changed";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
        } else {
            $error = $result['message'];
        }
    }
}

// Handle system settings update
if (isset($_POST['update_system_settings'])) {
    $site_name = htmlspecialchars(strip_tags(trim($_POST['site_name'])));
    $site_description = htmlspecialchars(strip_tags(trim($_POST['site_description'])));
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $appointment_days_limit = (int)$_POST['appointment_days_limit'];
    
    // In a real application, these would be saved to a settings table
    // For now, we'll store in a JSON file or session
    $settings = [
        'site_name' => $site_name,
        'site_description' => $site_description,
        'maintenance_mode' => $maintenance_mode,
        'appointment_days_limit' => $appointment_days_limit,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save to a JSON file (in production, use database)
    $settings_file = '../config/system_settings.json';
    if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
        $success = "System settings updated successfully!";
        
        // Log the action
        $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $log_user_id = $admin_id;
        $log_action = "System Settings Updated";
        $log_details = "System settings were updated by admin";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt->bindParam(':user_id', $log_user_id);
        $logStmt->bindParam(':action', $log_action);
        $logStmt->bindParam(':details', $log_details);
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
    } else {
        $error = "Failed to save system settings.";
    }
}

// Load current system settings
$system_settings = [
    'site_name' => 'Hospital Management System',
    'site_description' => 'Complete hospital management solution',
    'maintenance_mode' => 0,
    'appointment_days_limit' => 30
];

$settings_file = '../config/system_settings.json';
if (file_exists($settings_file)) {
    $loaded_settings = json_decode(file_get_contents($settings_file), true);
    if ($loaded_settings) {
        $system_settings = array_merge($system_settings, $loaded_settings);
    }
}

// Get system logs for display
$logs_query = "SELECT l.*, u.full_name, u.role 
               FROM system_logs l
               LEFT JOIN users u ON l.user_id = u.id
               ORDER BY l.created_at DESC
               LIMIT 20";
$logs_stmt = $db->query($logs_query);
$system_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get database statistics
$db_stats = [];
$tables = ['users', 'doctors', 'appointments', 'medical_records', 'bills', 'system_logs'];
foreach ($tables as $table) {
    $count_query = "SELECT COUNT(*) as count FROM $table";
    $count_stmt = $db->query($count_query);
    $db_stats[$table] = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Hospital System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Navbar Styles */
        .navbar {
            background: white;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
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
            background: linear-gradient(135deg, #2563eb, #3b82f6);
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
            gap: 2rem;
            list-style: none;
        }

        .nav-link {
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .nav-link:hover {
            color: #2563eb;
            background: #eff6ff;
        }

        .nav-link.active {
            color: #2563eb;
            background: #eff6ff;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 1.875rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
        }

        /* Grid Layout */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
        }

        /* Cards */
        .settings-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1f2937;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: #2563eb;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Checkbox Toggle */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            color: #374151;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s;
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.75rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        /* Stats Mini Cards */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-mini-card {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .stat-mini-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
        }

        .stat-mini-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.375rem;
        }

        .badge-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .glass-card {
                padding: 1rem;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-mini {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-hospital"></i>
                <span>Hospital System - Admin</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-cog"></i> System Settings</h2>
                <p>Configure system settings, manage your profile, and view system information</p>
            </div>
            
            <!-- Alerts -->
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
            
            <div class="settings-grid">
                <!-- Left Column -->
                <div>
                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-user-circle" style="color: #2563eb;"></i>
                            Profile Settings
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                    
                    <!-- Password Change -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-key" style="color: #2563eb;"></i>
                            Change Password
                        </div>
                        <form method="POST" action="" onsubmit="return validatePassword()">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-key"></i> New Password
                                </label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                                <small style="color: #6b7280; font-size: 0.75rem;">Minimum 8 characters with uppercase, lowercase, and number</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-check-circle"></i> Confirm New Password
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <div id="password_match_msg" style="font-size: 0.75rem; margin-top: 0.25rem;"></div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- System Configuration -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-server" style="color: #2563eb;"></i>
                            System Configuration
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-hospital"></i> Site Name
                                </label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($system_settings['site_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-info-circle"></i> Site Description
                                </label>
                                <textarea name="site_description" class="form-control" rows="2"><?php echo htmlspecialchars($system_settings['site_description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i> Appointment Booking Limit (Days)
                                </label>
                                <input type="number" name="appointment_days_limit" class="form-control" value="<?php echo $system_settings['appointment_days_limit']; ?>" min="1" max="365">
                                <small style="color: #6b7280;">How many days in advance patients can book appointments</small>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo $system_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <label for="maintenance_mode">
                                    <i class="fas fa-tools"></i> Enable Maintenance Mode
                                </label>
                            </div>
                            
                            <button type="submit" name="update_system_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save System Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Database Statistics -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-database" style="color: #2563eb;"></i>
                            Database Statistics
                        </div>
                        <div class="stats-mini">
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['users']; ?></div>
                                <div class="stat-mini-label">Total Users</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['doctors']; ?></div>
                                <div class="stat-mini-label">Doctors</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['appointments']; ?></div>
                                <div class="stat-mini-label">Appointments</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['medical_records']; ?></div>
                                <div class="stat-mini-label">Medical Records</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['bills']; ?></div>
                                <div class="stat-mini-label">Bills</div>
                            </div>
                            <div class="stat-mini-card">
                                <div class="stat-mini-number"><?php echo $db_stats['system_logs']; ?></div>
                                <div class="stat-mini-label">System Logs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Logs -->
            <div class="settings-card" style="margin-top: 1rem;">
                <div class="card-header">
                    <i class="fas fa-history" style="color: #2563eb;"></i>
                    Recent System Logs
                </div>
                <?php if (count($system_logs) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> User</th>
                                    <th><i class="fas fa-bolt"></i> Action</th>
                                    <th><i class="fas fa-info-circle"></i> Details</th>
                                    <th><i class="fas fa-clock"></i> Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($system_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php if ($log['full_name']): ?>
                                                <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                                <br>
                                                <span class="badge badge-admin"><?php echo htmlspecialchars($log['role'] ?? 'admin'); ?></span>
                                            <?php else: ?>
                                                System
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #6b7280; padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block;"></i>
                        No system logs found.
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Info Note -->
            <div class="alert alert-info" style="margin-top: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> System settings are saved locally. In production, these should be stored in a database. All actions are logged for security auditing.</span>
            </div>
        </div>
    </div>
    
    <script>
        // Password match validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatchMsg = document.getElementById('password_match_msg');
        
        function checkPasswordMatch() {
            if (newPassword.value !== confirmPassword.value) {
                passwordMatchMsg.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                passwordMatchMsg.style.color = '#dc2626';
                return false;
            } else if (confirmPassword.value !== '') {
                passwordMatchMsg.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                passwordMatchMsg.style.color = '#10b981';
                return true;
            } else {
                passwordMatchMsg.innerHTML = '';
                return false;
            }
        }
        
        newPassword.addEventListener('keyup', checkPasswordMatch);
        confirmPassword.addEventListener('keyup', checkPasswordMatch);
        
        function validatePassword() {
            const password = newPassword.value;
            
            if (password !== confirmPassword.value) {
                alert('New passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                alert('Password must contain at least one uppercase letter, one lowercase letter, and one number!');
                return false;
            }
            
            return true;
        }
        
        // Auto-refresh logs every 30 seconds (optional)
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>