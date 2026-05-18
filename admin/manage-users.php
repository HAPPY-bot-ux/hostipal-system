<?php
// admin/manage-users.php - Complete user management system
require_once '../config/database.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Auth.php';

// Start session and check admin role
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle user actions
$error = '';
$success = '';

// Get filter parameters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build count query for pagination
$countQuery = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$countParams = [];

if ($role_filter !== 'all') {
    $countQuery .= " AND role = :role";
    $countParams[':role'] = $role_filter;
}

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 1 : 0;
    $countQuery .= " AND is_active = :is_active";
    $countParams[':is_active'] = $is_active;
}

if (!empty($search)) {
    $countQuery .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
    $countParams[':search'] = "%$search%";
}

$countStmt = $db->prepare($countQuery);
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total_users = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Build main query
$query = "SELECT u.*, 
          CASE 
              WHEN u.role = 'doctor' THEN d.specialization 
              ELSE NULL 
          END as specialization,
          CASE 
              WHEN u.role = 'doctor' THEN d.consultation_fee 
              ELSE NULL 
          END as consultation_fee,
          CASE 
              WHEN u.role = 'doctor' THEN d.qualification 
              ELSE NULL 
          END as qualification
          FROM users u
          LEFT JOIN doctors d ON u.id = d.user_id
          WHERE 1=1";
$params = [];

if ($role_filter !== 'all') {
    $query .= " AND u.role = :role";
    $params[':role'] = $role_filter;
}

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 1 : 0;
    $query .= " AND u.is_active = :is_active";
    $params[':is_active'] = $is_active;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    if ($key == ':limit' || $key == ':offset') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle user status toggle
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id == SessionManager::getUserId()) {
        $error = "You cannot deactivate your own account!";
    } else {
        $checkQuery = "SELECT is_active, username, role FROM users WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $user_id);
        $checkStmt->execute();
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $new_status = $user['is_active'] ? 0 : 1;
            $updateQuery = "UPDATE users SET is_active = :is_active WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':is_active', $new_status);
            $updateStmt->bindParam(':id', $user_id);
            
            if ($updateStmt->execute()) {
                $action = $new_status ? 'activated' : 'deactivated';
                $success = "User '{$user['username']}' has been {$action} successfully!";
                
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $log_user_id = SessionManager::getUserId();
                $log_action = "User {$action}";
                $log_details = "User '{$user['username']}' ({$user['role']}) was {$action} by admin";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':user_id', $log_user_id);
                $logStmt->bindParam(':action', $log_action);
                $logStmt->bindParam(':details', $log_details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                // Refresh page to show updated status
                header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
                exit();
            } else {
                $error = "Failed to update user status.";
            }
        }
    }
}

// Handle user role change
if (isset($_POST['change_role']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if ($user_id == SessionManager::getUserId()) {
        $error = "You cannot change your own role!";
    } else {
        $valid_roles = ['patient', 'doctor', 'admin'];
        if (in_array($new_role, $valid_roles)) {
            $updateQuery = "UPDATE users SET role = :role WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':role', $new_role);
            $updateStmt->bindParam(':id', $user_id);
            
            if ($updateStmt->execute()) {
                $success = "User role has been updated successfully!";
                
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $log_user_id = SessionManager::getUserId();
                $log_action = "Role Changed";
                $log_details = "User ID {$user_id} role changed to {$new_role}";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':user_id', $log_user_id);
                $logStmt->bindParam(':action', $log_action);
                $logStmt->bindParam(':details', $log_details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
                exit();
            } else {
                $error = "Failed to update user role.";
            }
        } else {
            $error = "Invalid role specified.";
        }
    }
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id == SessionManager::getUserId()) {
        $error = "You cannot delete your own account!";
    } else {
        $getUserQuery = "SELECT username, role FROM users WHERE id = :id";
        $getUserStmt = $db->prepare($getUserQuery);
        $getUserStmt->bindParam(':id', $user_id);
        $getUserStmt->execute();
        $user = $getUserStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $deleteQuery = "DELETE FROM users WHERE id = :id";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(':id', $user_id);
            
            if ($deleteStmt->execute()) {
                $success = "User '{$user['username']}' has been deleted successfully!";
                
                $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
                $logStmt = $db->prepare($logQuery);
                $log_user_id = SessionManager::getUserId();
                $log_action = "User Deleted";
                $log_details = "User '{$user['username']}' ({$user['role']}) was deleted by admin";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bindParam(':user_id', $log_user_id);
                $logStmt->bindParam(':action', $log_action);
                $logStmt->bindParam(':details', $log_details);
                $logStmt->bindParam(':ip', $ip);
                $logStmt->execute();
                
                header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
                exit();
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Handle password reset
if (isset($_POST['reset_password']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = 'password123'; // Default temporary password
    
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    $updateQuery = "UPDATE users SET password = :password WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':id', $user_id);
    
    if ($updateStmt->execute()) {
        $success = "Password has been reset to 'password123' successfully!";
        
        $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $log_user_id = SessionManager::getUserId();
        $log_action = "Password Reset";
        $log_details = "Password reset for user ID {$user_id} by admin";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt->bindParam(':user_id', $log_user_id);
        $logStmt->bindParam(':action', $log_action);
        $logStmt->bindParam(':details', $log_details);
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
        
        header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
        exit();
    } else {
        $error = "Failed to reset password.";
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as doctors,
    SUM(CASE WHEN role = 'patient' THEN 1 ELSE 0 END) as patients,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Hospital Management System</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid #e5e7eb;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
            line-height: 1;
        }

        .stat-label {
            color: #6b7280;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
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

        /* Filters */
        .filters-bar {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .filter-select, .search-input {
            padding: 0.625rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.875rem;
            font-family: inherit;
            background: white;
            transition: all 0.3s;
        }

        .filter-select:focus, .search-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-input {
            min-width: 250px;
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

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
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

        /* Table */
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
            padding: 1rem;
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

        .badge-doctor {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-patient {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Action Buttons Group */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .icon-btn:hover {
            transform: scale(1.1);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .page-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }

        .modal-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .modal-body {
            margin-bottom: 1.5rem;
            color: #6b7280;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .search-input {
                width: 100%;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <li><a href="manage-users.php" class="nav-link active"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-users"></i> Manage Users</h2>
                <p>View, manage, and control all users in the system</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="stat-number"><?php echo $stats['admins']; ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-number"><?php echo $stats['doctors']; ?></div>
                    <div class="stat-label">Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user"></i></div>
                    <div class="stat-number"><?php echo $stats['patients']; ?></div>
                    <div class="stat-label">Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active Accounts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-label">Inactive Accounts</div>
                </div>
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
            
            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Role</label>
                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="doctor" <?php echo $role_filter == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="patient" <?php echo $role_filter == 'patient' ? 'selected' : ''; ?>>Patient</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 1;">
                        <label><i class="fas fa-search"></i> Search</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="search-input" placeholder="Search by username, email, or name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search): ?>
                                <a href="manage-users.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><i class="fas fa-user"></i> Username</th>
                            <th><i class="fas fa-user-circle"></i> Full Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-tag"></i> Role</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-stethoscope"></i> Specialization</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-calendar"></i> Joined</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-user-shield' : ($user['role'] == 'doctor' ? 'fa-user-md' : 'fa-user'); ?>"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['specialization'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas <?php echo $user['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="action-buttons">
                                        <!-- Toggle Status -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="toggle_status" class="icon-btn btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Change Role -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="filter-select" style="padding: 0.25rem; font-size: 0.75rem;" onchange="this.form.submit()" title="Change Role">
                                                <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>👤 Patient</option>
                                                <option value="doctor" <?php echo $user['role'] == 'doctor' ? 'selected' : ''; ?>>👨‍⚕️ Doctor</option>
                                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>🔧 Admin</option>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                        
                                        <!-- Reset Password -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password for this user? New password will be: password123')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="reset_password" class="icon-btn" style="color: #f59e0b;" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete User -->
                                        <?php if ($user['id'] != SessionManager::getUserId()): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This action is permanent!\n\nAre you sure you want to delete user: <?php echo addslashes($user['username']); ?>?\n\nAll associated data will be lost!')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="icon-btn" style="color: #dc2626;" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-users-slash" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block;"></i>
                                    No users found matching the criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Info Note -->
            <div class="alert alert-info" style="margin-top: 1.5rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You cannot delete or modify your own account. Password reset sets the password to 'password123'.</span>
            </div>
        </div>
    </div>
</body>
</html>