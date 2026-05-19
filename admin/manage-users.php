<?php
// admin/manage-users.php - Complete user management system with modern UI
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
$limit = 12;
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
    $countQuery .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search OR phone LIKE :search)";
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
          END as qualification,
          CASE 
              WHEN u.role = 'doctor' THEN d.experience_years 
              ELSE NULL 
          END as experience_years
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
    $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search OR u.phone LIKE :search)";
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
    $new_password = 'password123';
    
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users | MediFlow HMS - Admin</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 20px;
            transition: all 0.3s;
            border: 1px solid rgba(37, 99, 235, 0.08);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.1);
        }

        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-number { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #64748b; margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Filters */
        .filters-bar {
            background: white;
            padding: 1.25rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
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
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select, .search-input {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .filter-select:focus, .search-input:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .search-input { min-width: 250px; }

        /* Buttons */
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3); }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }

        /* User Cards Grid */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }

        .user-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }

        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .user-info h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .user-info p {
            font-size: 0.7rem;
            color: #64748b;
        }

        .card-body {
            padding: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }

        .info-row i {
            width: 24px;
            color: #2563eb;
        }

        .card-footer {
            padding: 1rem;
            background: #fafcff;
            border-top: 1px solid #eef2ff;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
            gap: 0.3rem;
        }

        .badge-admin { background: #fee2e2; color: #dc2626; }
        .badge-doctor { background: #dbeafe; color: #2563eb; }
        .badge-patient { background: #d1fae5; color: #059669; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }

        /* Role Select */
        .role-select {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.7rem;
            background: white;
            cursor: pointer;
        }

        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.4rem;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .icon-btn:hover { background: #f1f5f9; transform: scale(1.05); }

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
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: #475569;
            transition: all 0.2s;
            font-size: 0.8rem;
        }

        .page-link:hover, .page-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #2563eb; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 24px;
            color: #94a3b8;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in { animation: fadeInUp 0.5s ease-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .navbar-container { flex-direction: column; gap: 1rem; padding: 0 1rem; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .container { padding: 0 1rem; }
            .glass-card { padding: 1rem; }
            .filters { flex-direction: column; }
            .filter-group { width: 100%; }
            .search-input { width: 100%; }
            .users-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo"><i class="fas fa-heartbeat"></i><span>MediFlow HMS</span></a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="manage-users.php" class="nav-link active"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <p>View, manage, and control all users in the system</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-icon">🛡️</div><div class="stat-number"><?php echo $stats['admins']; ?></div><div class="stat-label">Admins</div></div>
                <div class="stat-card"><div class="stat-icon">👨‍⚕️</div><div class="stat-number"><?php echo $stats['doctors']; ?></div><div class="stat-label">Doctors</div></div>
                <div class="stat-card"><div class="stat-icon">🩺</div><div class="stat-number"><?php echo $stats['patients']; ?></div><div class="stat-label">Patients</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-icon">⛔</div><div class="stat-number"><?php echo $stats['inactive']; ?></div><div class="stat-label">Inactive</div></div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group"><label>Role</label><select name="role" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="doctor" <?php echo $role_filter == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                        <option value="patient" <?php echo $role_filter == 'patient' ? 'selected' : ''; ?>>Patient</option>
                    </select></div>
                    <div class="filter-group"><label>Status</label><select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select></div>
                    <div class="filter-group" style="flex:1"><label>Search</label><div style="display:flex; gap:0.5rem;">
                        <input type="text" name="search" class="search-input" placeholder="Username, email, name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <?php if ($search): ?><a href="manage-users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
                    </div></div>
                </form>
            </div>

            <!-- Users Grid -->
            <?php if (count($users) > 0): ?>
                <div class="users-grid">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="card-header">
                                <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <span class="badge badge-<?php echo $user['role']; ?>"><i class="fas <?php echo $user['role'] == 'admin' ? 'fa-user-shield' : ($user['role'] == 'doctor' ? 'fa-user-md' : 'fa-user'); ?>"></i> <?php echo ucfirst($user['role']); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                                <?php if ($user['role'] == 'doctor' && $user['specialization']): ?>
                                    <div class="info-row"><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($user['specialization']); ?></div>
                                    <div class="info-row"><i class="fas fa-dollar-sign"></i> Fee: $<?php echo number_format($user['consultation_fee'], 2); ?></div>
                                <?php endif; ?>
                                <div class="info-row"><i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                <div class="info-row"><i class="fas fa-circle"></i> Status: <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></div>
                            </div>
                            <div class="card-footer">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="toggle_status" class="icon-btn" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>" style="color: <?php echo $user['is_active'] ? '#f59e0b' : '#10b981'; ?>">
                                        <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="new_role" class="role-select" onchange="this.form.submit()" title="Change Role">
                                        <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>👤 Patient</option>
                                        <option value="doctor" <?php echo $user['role'] == 'doctor' ? 'selected' : ''; ?>>👨‍⚕️ Doctor</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>🔧 Admin</option>
                                    </select>
                                    <input type="hidden" name="change_role" value="1">
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password for <?php echo addslashes($user['username']); ?>? New password: password123')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reset_password" class="icon-btn" title="Reset Password" style="color: #f59e0b;"><i class="fas fa-key"></i></button>
                                </form>
                                <?php if ($user['id'] != SessionManager::getUserId()): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ PERMANENT ACTION!\n\nDelete user: <?php echo addslashes($user['username']); ?>?\nAll associated data will be lost!')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="icon-btn" title="Delete User" style="color: #dc2626;"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-users-slash" style="font-size: 3rem; color: #cbd5e1;"></i><h3 style="margin-top: 1rem;">No Users Found</h3><p>No users match your search criteria.</p></div>
            <?php endif; ?>

            <div class="alert alert-info" style="margin-top: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You cannot delete or modify your own account. Password reset sets the password to 'password123'.</span>
            </div>
        </div>
    </div>
</body>
</html>