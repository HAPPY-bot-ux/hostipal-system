<?php
// admin/manage-users.php - Kanban-Style User Management System
require_once '../config/database.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Auth.php';

SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

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

// Build count query
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
          CASE WHEN u.role = 'doctor' THEN d.specialization ELSE NULL END as specialization,
          CASE WHEN u.role = 'doctor' THEN d.consultation_fee ELSE NULL END as consultation_fee,
          CASE WHEN u.role = 'doctor' THEN d.qualification ELSE NULL END as qualification,
          CASE WHEN u.role = 'doctor' THEN d.experience_years ELSE NULL END as experience_years
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

// Handle actions (same as before)
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    if ($user_id != SessionManager::getUserId()) {
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
                header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
                exit();
            }
        }
    }
}

if (isset($_POST['change_role']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    if ($user_id != SessionManager::getUserId() && in_array($new_role, ['patient', 'doctor', 'admin'])) {
        $updateQuery = "UPDATE users SET role = :role WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':role', $new_role);
        $updateStmt->bindParam(':id', $user_id);
        if ($updateStmt->execute()) {
            header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
            exit();
        }
    }
}

if (isset($_POST['reset_password']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = 'password123';
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    $updateQuery = "UPDATE users SET password = :password WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':id', $user_id);
    if ($updateStmt->execute()) {
        header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
        exit();
    }
}

if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    if ($user_id != SessionManager::getUserId()) {
        $deleteQuery = "DELETE FROM users WHERE id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $user_id);
        if ($deleteStmt->execute()) {
            header("Location: manage-users.php?role=$role_filter&status=$status_filter&search=$search&page=$page");
            exit();
        }
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

// Group users by role for kanban view
$grouped_users = ['admin' => [], 'doctor' => [], 'patient' => []];
foreach ($users as $user) {
    $grouped_users[$user['role']][] = $user;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | MediFlow HMS - Admin</title>
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
            --admin-color: #EF4444;
            --doctor-color: #0EA5E9;
            --patient-color: #10B981;
            --admin-glow: rgba(239, 68, 68, 0.15);
            --doctor-glow: rgba(14, 165, 233, 0.15);
            --patient-glow: rgba(16, 185, 129, 0.15);
            --primary: #8B5CF6;
            --warning: #F59E0B;
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
        }

        /* Animated Background */
        .bg-gradient {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(139, 92, 246, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.06) 0%, transparent 50%);
            z-index: 0;
            pointer-events: none;
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
            max-width: 1600px;
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
            background: rgba(139, 92, 246, 0.1);
        }

        .admin-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1600px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        /* Header Stats Bar */
        .stats-bar {
            background: rgba(18, 22, 33, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stats-group {
            display: flex;
            gap: 1.5rem;
        }

        .stat-item {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .admin-stat { color: var(--admin-color); }
        .doctor-stat { color: var(--doctor-color); }
        .patient-stat { color: var(--patient-color); }

        /* Search Bar */
        .search-section {
            margin-bottom: 2rem;
        }

        .search-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            background: rgba(18, 22, 33, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 60px;
            padding: 0.25rem;
        }

        .search-icon {
            padding-left: 1rem;
            color: var(--text-muted);
        }

        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 0.8rem 0;
            color: var(--text-main);
            font-size: 0.9rem;
        }

        .search-input:focus {
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            padding-right: 0.5rem;
        }

        .filter-chip {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }

        .filter-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .filter-chip:hover:not(.active) {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Kanban Board */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .kanban-column {
            background: rgba(18, 22, 33, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
        }

        .column-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .column-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
        }

        .column-count {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
        }

        .admin-header .column-title { color: var(--admin-color); }
        .doctor-header .column-title { color: var(--doctor-color); }
        .patient-header .column-title { color: var(--patient-color); }

        .column-body {
            padding: 1rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        /* User Card in Kanban */
        .user-card {
            background: rgba(18, 22, 33, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
            position: relative;
        }

        .user-card:hover {
            transform: translateX(4px);
            border-color: rgba(139, 92, 246, 0.4);
        }

        .card-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .user-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .admin-avatar { background: rgba(239, 68, 68, 0.15); color: var(--admin-color); }
        .doctor-avatar { background: rgba(14, 165, 233, 0.15); color: var(--doctor-color); }
        .patient-avatar { background: rgba(16, 185, 129, 0.15); color: var(--patient-color); }

        .user-details h4 {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        .user-details p {
            font-size: 0.6rem;
            color: var(--text-muted);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 8px;
            display: inline-block;
        }

        .status-active { background: var(--patient-color); box-shadow: 0 0 6px var(--patient-color); }
        .status-inactive { background: var(--text-muted); }

        .user-contact {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-contact i {
            width: 20px;
            font-size: 0.6rem;
        }

        .doctor-spec {
            font-size: 0.65rem;
            color: var(--doctor-color);
            margin-bottom: 0.5rem;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .action-icon {
            background: transparent;
            border: none;
            padding: 0.3rem 0.6rem;
            border-radius: 10px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .action-icon:hover {
            background: rgba(139, 92, 246, 0.15);
            color: var(--primary);
        }

        .role-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.2rem 0.4rem;
            color: var(--text-main);
            font-size: 0.65rem;
            cursor: pointer;
        }

        .danger-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: var(--admin-color);
        }

        /* Pagination */
        .pagination-bar {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .page-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .page-btn:hover, .page-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Empty Column */
        .empty-column {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(18, 22, 33, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            padding: 0.875rem 1.25rem;
            border-radius: 20px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 3000;
            animation: slideIn 0.3s ease;
        }

        .toast.show { display: flex; }
        .toast.success { border-left: 3px solid var(--patient-color); }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .kanban-board {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-group {
                flex-wrap: wrap;
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
            .admin-wrapper {
                padding: 0 1rem;
            }
            .search-container {
                flex-wrap: wrap;
                border-radius: 24px;
                padding: 0.5rem;
            }
            .filter-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="bg-gradient"></div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heart-pulse"></i>
                <span>Hospital System</span>
            </a>
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

    <div class="admin-wrapper">
        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stats-group">
                <div class="stat-item"><span class="stat-value"><?php echo $stats['total']; ?></span><span class="stat-label">Total Users</span></div>
                <div class="stat-item"><span class="stat-value admin-stat"><?php echo $stats['admins']; ?></span><span class="stat-label">Admins</span></div>
                <div class="stat-item"><span class="stat-value doctor-stat"><?php echo $stats['doctors']; ?></span><span class="stat-label">Doctors</span></div>
                <div class="stat-item"><span class="stat-value patient-stat"><?php echo $stats['patients']; ?></span><span class="stat-label">Patients</span></div>
                <div class="stat-item"><span class="stat-value patient-stat"><?php echo $stats['active']; ?></span><span class="stat-label">Active</span></div>
                <div class="stat-item"><span class="stat-value"><?php echo $stats['inactive']; ?></span><span class="stat-label">Inactive</span></div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" action="" id="filterForm">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, username or phone..." value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                    <div class="filter-buttons">
                        <button type="button" class="filter-chip <?php echo $role_filter == 'all' ? 'active' : ''; ?>" onclick="setRole('all')">All</button>
                        <button type="button" class="filter-chip <?php echo $role_filter == 'admin' ? 'active' : ''; ?>" onclick="setRole('admin')">👑 Admin</button>
                        <button type="button" class="filter-chip <?php echo $role_filter == 'doctor' ? 'active' : ''; ?>" onclick="setRole('doctor')">👨‍⚕️ Doctor</button>
                        <button type="button" class="filter-chip <?php echo $role_filter == 'patient' ? 'active' : ''; ?>" onclick="setRole('patient')">🩺 Patient</button>
                        <input type="hidden" name="role" id="roleInput" value="<?php echo $role_filter; ?>">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <?php if ($search || $role_filter != 'all'): ?>
                            <a href="manage-users.php" class="filter-chip">Clear <i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Kanban Board -->
        <div class="kanban-board">
            <!-- Admins Column -->
            <div class="kanban-column">
                <div class="column-header admin-header">
                    <div class="column-title"><i class="fas fa-user-shield"></i> Administrators</div>
                    <span class="column-count"><?php echo count($grouped_users['admin']); ?></span>
                </div>
                <div class="column-body">
                    <?php if (count($grouped_users['admin']) > 0): ?>
                        <?php foreach ($grouped_users['admin'] as $user): ?>
                            <div class="user-card">
                                <div class="card-row">
                                    <div class="user-name">
                                        <div class="user-avatar admin-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                            <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                    <span class="status-indicator <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                </div>
                                <div class="user-contact"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></div>
                                <div class="card-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="toggle_status" class="action-icon" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i> <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password for <?php echo addslashes($user['username']); ?>?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="reset_password" class="action-icon"><i class="fas fa-key"></i> Reset</button>
                                    </form>
                                    <?php if ($user['id'] != SessionManager::getUserId()): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete <?php echo addslashes($user['username']); ?>? This cannot be undone!')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="action-icon danger-btn"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-column"><i class="fas fa-user-shield" style="font-size: 2rem; opacity: 0.3;"></i><p>No admins found</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Doctors Column -->
            <div class="kanban-column">
                <div class="column-header doctor-header">
                    <div class="column-title"><i class="fas fa-user-md"></i> Doctors</div>
                    <span class="column-count"><?php echo count($grouped_users['doctor']); ?></span>
                </div>
                <div class="column-body">
                    <?php if (count($grouped_users['doctor']) > 0): ?>
                        <?php foreach ($grouped_users['doctor'] as $user): ?>
                            <div class="user-card">
                                <div class="card-row">
                                    <div class="user-name">
                                        <div class="user-avatar doctor-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                        <div class="user-details">
                                            <h4>Dr. <?php echo htmlspecialchars($user['full_name']); ?></h4>
                                            <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                    <span class="status-indicator <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                </div>
                                <div class="doctor-spec"><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($user['specialization'] ?? 'General Medicine'); ?></div>
                                <div class="user-contact"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-contact"><i class="fas fa-dollar-sign"></i> Fee: R<?php echo number_format($user['consultation_fee'] ?? 0, 2); ?></div>
                                <div class="card-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="new_role" class="role-select" onchange="this.form.submit()">
                                            <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>👤 Patient</option>
                                            <option value="doctor" <?php echo $user['role'] == 'doctor' ? 'selected' : ''; ?>>👨‍⚕️ Doctor</option>
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>🔧 Admin</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="toggle_status" class="action-icon"><i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i></button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="reset_password" class="action-icon"><i class="fas fa-key"></i></button>
                                    </form>
                                    <?php if ($user['id'] != SessionManager::getUserId()): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this doctor?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="action-icon danger-btn"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-column"><i class="fas fa-user-md" style="font-size: 2rem; opacity: 0.3;"></i><p>No doctors found</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Patients Column -->
            <div class="kanban-column">
                <div class="column-header patient-header">
                    <div class="column-title"><i class="fas fa-user-injured"></i> Patients</div>
                    <span class="column-count"><?php echo count($grouped_users['patient']); ?></span>
                </div>
                <div class="column-body">
                    <?php if (count($grouped_users['patient']) > 0): ?>
                        <?php foreach ($grouped_users['patient'] as $user): ?>
                            <div class="user-card">
                                <div class="card-row">
                                    <div class="user-name">
                                        <div class="user-avatar patient-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                            <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                    <span class="status-indicator <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                </div>
                                <div class="user-contact"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></div>
                                <div class="card-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="new_role" class="role-select" onchange="this.form.submit()">
                                            <option value="patient" <?php echo $user['role'] == 'patient' ? 'selected' : ''; ?>>👤 Patient</option>
                                            <option value="doctor" <?php echo $user['role'] == 'doctor' ? 'selected' : ''; ?>>👨‍⚕️ Doctor</option>
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>🔧 Admin</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="toggle_status" class="action-icon"><i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i></button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="reset_password" class="action-icon"><i class="fas fa-key"></i></button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this patient?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="action-icon danger-btn"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-column"><i class="fas fa-user-injured" style="font-size: 2rem; opacity: 0.3;"></i><p>No patients found</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-bar">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= min($total_pages, 5); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($total_pages > 5): ?>
                    <span class="page-btn">...</span>
                    <a href="?page=<?php echo $total_pages; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function setRole(role) {
            document.getElementById('roleInput').value = role;
            document.getElementById('filterForm').submit();
        }
    </script>
</body>
</html>