<?php
// admin/manage-doctors.php - Manage all doctors in the system
require_once '../config/database.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Auth.php';

// Start session and check admin role
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle doctor actions
$error = '';
$success = '';

// Get filter parameters
$specialization_filter = $_GET['specialization'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build count query for pagination
$countQuery = "SELECT COUNT(*) as total FROM users u 
               JOIN doctors d ON u.id = d.user_id 
               WHERE u.role = 'doctor'";
$countParams = [];

if ($specialization_filter !== 'all') {
    $countQuery .= " AND d.specialization = :specialization";
    $countParams[':specialization'] = $specialization_filter;
}

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 1 : 0;
    $countQuery .= " AND u.is_active = :is_active";
    $countParams[':is_active'] = $is_active;
}

if (!empty($search)) {
    $countQuery .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search OR d.specialization LIKE :search)";
    $countParams[':search'] = "%$search%";
}

$countStmt = $db->prepare($countQuery);
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total_doctors = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_doctors / $limit);

// Build main query
$query = "SELECT u.*, d.* 
          FROM users u 
          JOIN doctors d ON u.id = d.user_id 
          WHERE u.role = 'doctor'";
$params = [];

if ($specialization_filter !== 'all') {
    $query .= " AND d.specialization = :specialization";
    $params[':specialization'] = $specialization_filter;
}

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 1 : 0;
    $query .= " AND u.is_active = :is_active";
    $params[':is_active'] = $is_active;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search OR d.specialization LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY u.full_name ASC LIMIT :limit OFFSET :offset";
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
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique specializations for filter
$specializationsQuery = "SELECT DISTINCT specialization FROM doctors ORDER BY specialization";
$specializationsStmt = $db->query($specializationsQuery);
$specializations = $specializationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle doctor status toggle
if (isset($_POST['toggle_status']) && isset($_POST['doctor_id'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    
    $checkQuery = "SELECT u.is_active, u.username, u.full_name FROM users u 
                   JOIN doctors d ON u.id = d.user_id 
                   WHERE d.id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $doctor_id);
    $checkStmt->execute();
    $doctor = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $new_status = $doctor['is_active'] ? 0 : 1;
        $updateQuery = "UPDATE users SET is_active = :is_active WHERE id = (SELECT user_id FROM doctors WHERE id = :doctor_id)";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':is_active', $new_status);
        $updateStmt->bindParam(':doctor_id', $doctor_id);
        
        if ($updateStmt->execute()) {
            $action = $new_status ? 'activated' : 'deactivated';
            $success = "Dr. '{$doctor['full_name']}' has been {$action} successfully!";
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = SessionManager::getUserId();
            $log_action = "Doctor {$action}";
            $log_details = "Doctor '{$doctor['full_name']}' was {$action} by admin";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            header("Location: manage-doctors.php?specialization=$specialization_filter&status=$status_filter&search=$search&page=$page");
            exit();
        } else {
            $error = "Failed to update doctor status.";
        }
    }
}

// Handle doctor deletion
if (isset($_POST['delete_doctor']) && isset($_POST['doctor_id'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    
    $getDoctorQuery = "SELECT u.full_name, u.username FROM users u 
                       JOIN doctors d ON u.id = d.user_id 
                       WHERE d.id = :id";
    $getDoctorStmt = $db->prepare($getDoctorQuery);
    $getDoctorStmt->bindParam(':id', $doctor_id);
    $getDoctorStmt->execute();
    $doctor = $getDoctorStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $deleteQuery = "DELETE FROM users WHERE id = (SELECT user_id FROM doctors WHERE id = :doctor_id)";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':doctor_id', $doctor_id);
        
        if ($deleteStmt->execute()) {
            $success = "Dr. '{$doctor['full_name']}' has been deleted successfully!";
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = SessionManager::getUserId();
            $log_action = "Doctor Deleted";
            $log_details = "Doctor '{$doctor['full_name']}' was deleted by admin";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            header("Location: manage-doctors.php?specialization=$specialization_filter&status=$status_filter&search=$search&page=$page");
            exit();
        } else {
            $error = "Failed to delete doctor.";
        }
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT d.id) as total,
    COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN d.id END) as active,
    COUNT(DISTINCT CASE WHEN u.is_active = 0 THEN d.id END) as inactive,
    COUNT(DISTINCT CASE WHEN d.experience_years >= 10 THEN d.id END) as experienced
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE u.role = 'doctor'";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Hospital System</title>
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

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge-specialization {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Action Buttons */
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
            max-width: 600px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .detail-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-label {
            font-weight: 600;
            width: 140px;
            color: #374151;
        }

        .detail-value {
            flex: 1;
            color: #6b7280;
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

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
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
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-user-md"></i> Manage Doctors</h2>
                <p>View, manage, and control all doctors in the system</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-label">Inactive Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?php echo $stats['experienced']; ?></div>
                    <div class="stat-label">10+ Years Exp</div>
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
                        <label><i class="fas fa-stethoscope"></i> Specialization</label>
                        <select name="specialization" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $specialization_filter == 'all' ? 'selected' : ''; ?>>All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization_filter == $spec['specialization'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
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
                            <input type="text" name="search" class="search-input" placeholder="Search by name, email, specialization..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search || $specialization_filter != 'all' || $status_filter != 'all'): ?>
                                <a href="manage-doctors.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Doctors Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><i class="fas fa-user"></i> Doctor Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-stethoscope"></i> Specialization</th>
                            <th><i class="fas fa-graduation-cap"></i> Qualification</th>
                            <th><i class="fas fa-briefcase"></i> Experience</th>
                            <th><i class="fas fa-dollar-sign"></i> Fee</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doctors) > 0): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo $doctor['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($doctor['full_name']); ?></strong>
                                        <br>
                                        <small style="color: #6b7280;">@<?php echo htmlspecialchars($doctor['username']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td>
                                        <span class="badge badge-specialization">
                                            <i class="fas fa-stethoscope"></i>
                                            <?php echo htmlspecialchars($doctor['specialization']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($doctor['qualification'], 0, 40)) . (strlen($doctor['qualification'] ?? '') > 40 ? '...' : ''); ?></td>
                                    <td>
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo $doctor['experience_years']; ?> years
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($doctor['consultation_fee'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas <?php echo $doctor['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <!-- Toggle Status -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="toggle_status" class="icon-btn" style="color: <?php echo $doctor['is_active'] ? '#f59e0b' : '#10b981'; ?>" title="<?php echo $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $doctor['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- View Details -->
                                        <button class="icon-btn" style="color: #3b82f6;" title="View Details" onclick="viewDoctor(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['full_name']); ?>', '<?php echo addslashes($doctor['email']); ?>', '<?php echo addslashes($doctor['phone'] ?? 'N/A'); ?>', '<?php echo addslashes($doctor['address'] ?? 'N/A'); ?>', '<?php echo addslashes($doctor['specialization']); ?>', '<?php echo addslashes($doctor['qualification']); ?>', <?php echo $doctor['experience_years']; ?>, <?php echo $doctor['consultation_fee']; ?>, '<?php echo addslashes($doctor['available_days'] ?? 'N/A'); ?>', '<?php echo $doctor['available_time_start'] ?? 'N/A'; ?>', '<?php echo $doctor['available_time_end'] ?? 'N/A'; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Delete Doctor -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This action is permanent!\n\nAre you sure you want to delete Dr. <?php echo addslashes($doctor['full_name']); ?>?\n\nAll associated appointments and records will be lost!')">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="delete_doctor" class="icon-btn" style="color: #dc2626;" title="Delete Doctor">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-user-md-slash" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block;"></i>
                                    No doctors found matching the criteria.
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
                        <a href="?page=<?php echo $page-1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Info Note -->
            <div class="alert alert-info" style="margin-top: 1.5rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You can activate/deactivate doctors, view their full details, or remove them from the system.</span>
            </div>
        </div>
    </div>
    
    <!-- Doctor Details Modal -->
    <div id="doctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-md"></i> Doctor Details
            </div>
            <div class="modal-body" id="doctorDetails">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // View doctor details function
        function viewDoctor(id, name, email, phone, address, specialization, qualification, experience, fee, availableDays, startTime, endTime) {
            const modal = document.getElementById('doctorModal');
            const detailsDiv = document.getElementById('doctorDetails');
            
            detailsDiv.innerHTML = `
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-id-card"></i> Doctor ID:</div>
                    <div class="detail-value">${id}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-user"></i> Full Name:</div>
                    <div class="detail-value"><strong>${name}</strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-envelope"></i> Email:</div>
                    <div class="detail-value">${email}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-phone"></i> Phone:</div>
                    <div class="detail-value">${phone}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Address:</div>
                    <div class="detail-value">${address}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-stethoscope"></i> Specialization:</div>
                    <div class="detail-value"><span class="badge badge-specialization">${specialization}</span></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-graduation-cap"></i> Qualification:</div>
                    <div class="detail-value">${qualification}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-briefcase"></i> Experience:</div>
                    <div class="detail-value">${experience} years</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-dollar-sign"></i> Consultation Fee:</div>
                    <div class="detail-value"><strong>$${parseFloat(fee).toFixed(2)}</strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-calendar-week"></i> Available Days:</div>
                    <div class="detail-value">${availableDays}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-clock"></i> Available Hours:</div>
                    <div class="detail-value">${startTime} - ${endTime}</div>
                </div>
            `;
            
            modal.classList.add('active');
        }
        
        // Close modal function
        function closeModal() {
            const modal = document.getElementById('doctorModal');
            modal.classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('doctorModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>