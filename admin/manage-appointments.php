<?php
// admin/manage-appointments.php - Manage all appointments in the system
require_once '../config/database.php';
require_once '../includes/SessionManager.php';
require_once '../includes/Auth.php';

// Start session and check admin role
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle appointment actions
$error = '';
$success = '';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$doctor_filter = $_GET['doctor'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build count query for pagination
$countQuery = "SELECT COUNT(*) as total FROM appointments a 
               LEFT JOIN users p ON a.patient_id = p.id 
               LEFT JOIN doctors d ON a.doctor_id = d.id
               LEFT JOIN users u ON d.user_id = u.id
               WHERE 1=1";
$countParams = [];

if ($status_filter !== 'all') {
    $countQuery .= " AND a.status = :status";
    $countParams[':status'] = $status_filter;
}

if ($doctor_filter !== 'all') {
    $countQuery .= " AND d.id = :doctor_id";
    $countParams[':doctor_id'] = $doctor_filter;
}

if (!empty($date_filter)) {
    $countQuery .= " AND a.appointment_date = :date";
    $countParams[':date'] = $date_filter;
}

if (!empty($search)) {
    $countQuery .= " AND (p.full_name LIKE :search OR u.full_name LIKE :search OR p.username LIKE :search)";
    $countParams[':search'] = "%$search%";
}

$countStmt = $db->prepare($countQuery);
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total_appointments = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_appointments / $limit);

// Build main query
$query = "SELECT a.*, 
          p.full_name as patient_name, 
          p.email as patient_email,
          p.phone as patient_phone,
          u.full_name as doctor_name,
          u.email as doctor_email,
          d.specialization,
          d.consultation_fee
          FROM appointments a
          LEFT JOIN users p ON a.patient_id = p.id
          LEFT JOIN doctors d ON a.doctor_id = d.id
          LEFT JOIN users u ON d.user_id = u.id
          WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if ($doctor_filter !== 'all') {
    $query .= " AND d.id = :doctor_id";
    $params[':doctor_id'] = $doctor_filter;
}

if (!empty($date_filter)) {
    $query .= " AND a.appointment_date = :date";
    $params[':date'] = $date_filter;
}

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE :search OR u.full_name LIKE :search OR p.username LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC LIMIT :limit OFFSET :offset";
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
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors list for filter
$doctorsQuery = "SELECT d.id, u.full_name, d.specialization 
                 FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE u.is_active = 1 
                 ORDER BY u.full_name";
$doctorsStmt = $db->query($doctorsQuery);
$doctors = $doctorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['appointment_id']) && isset($_POST['new_status'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $new_status = $_POST['new_status'];
    $valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    
    if (in_array($new_status, $valid_statuses)) {
        $updateQuery = "UPDATE appointments SET status = :status WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':status', $new_status);
        $updateStmt->bindParam(':id', $appointment_id);
        
        if ($updateStmt->execute()) {
            $success = "Appointment status updated successfully!";
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = SessionManager::getUserId();
            $log_action = "Appointment Status Changed";
            $log_details = "Appointment ID {$appointment_id} status changed to {$new_status}";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            header("Location: manage-appointments.php?status=$status_filter&doctor=$doctor_filter&date=$date_filter&search=$search&page=$page");
            exit();
        } else {
            $error = "Failed to update appointment status.";
        }
    }
}

// Handle appointment deletion
if (isset($_POST['delete_appointment']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    
    $deleteQuery = "DELETE FROM appointments WHERE id = :id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindParam(':id', $appointment_id);
    
    if ($deleteStmt->execute()) {
        $success = "Appointment deleted successfully!";
        
        // Log the action
        $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $log_user_id = SessionManager::getUserId();
        $log_action = "Appointment Deleted";
        $log_details = "Appointment ID {$appointment_id} was deleted";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt->bindParam(':user_id', $log_user_id);
        $logStmt->bindParam(':action', $log_action);
        $logStmt->bindParam(':details', $log_details);
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
        
        header("Location: manage-appointments.php?status=$status_filter&doctor=$doctor_filter&date=$date_filter&search=$search&page=$page");
        exit();
    } else {
        $error = "Failed to delete appointment.";
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today
    FROM appointments";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Hospital System</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .filter-select, .search-input, .filter-date {
            padding: 0.625rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.875rem;
            font-family: inherit;
            background: white;
            transition: all 0.3s;
        }

        .filter-select:focus, .search-input:focus, .filter-date:focus {
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

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Status Select */
        .status-select {
            padding: 0.375rem;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.75rem;
            font-family: inherit;
            cursor: pointer;
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
                <li><a href="manage-appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-calendar-check"></i> Manage Appointments</h2>
                <p>View, manage, and control all appointments in the system</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today']; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
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
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-user-md"></i> Doctor</label>
                        <select name="doctor" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $doctor_filter == 'all' ? 'selected' : ''; ?>>All Doctors</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['full_name']); ?> (<?php echo $doctor['specialization']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date</label>
                        <input type="date" name="date" class="filter-date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div class="filter-group" style="flex: 1;">
                        <label><i class="fas fa-search"></i> Search</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="search-input" placeholder="Search by patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search || $date_filter || $doctor_filter != 'all' || $status_filter != 'all'): ?>
                                <a href="manage-appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Appointments Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><i class="fas fa-user"></i> Patient</th>
                            <th><i class="fas fa-user-md"></i> Doctor</th>
                            <th><i class="fas fa-stethoscope"></i> Specialization</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-clock"></i> Time</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                            <th><i class="fas fa-notes-medical"></i> Symptoms</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($appointments) > 0): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo $appointment['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                        <br>
                                        <small style="color: #6b7280;"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></strong>
                                        <br>
                                        <small style="color: #6b7280;"><?php echo htmlspecialchars($appointment['specialization'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['specialization'] ?? '-'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i>
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? '', 0, 50)) . (strlen($appointment['symptoms'] ?? '') > 50 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td class="action-buttons">
                                        <!-- Update Status Form -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <select name="new_status" class="status-select" onchange="this.form.submit()" title="Change Status">
                                                <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                                <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                                <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Completed</option>
                                                <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        
                                        <!-- View Details Button -->
                                        <button class="icon-btn" style="color: #3b82f6;" title="View Details" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Delete Appointment -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This action is permanent!\n\nAre you sure you want to delete this appointment?')">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <button type="submit" name="delete_appointment" class="icon-btn" style="color: #dc2626;" title="Delete Appointment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block;"></i>
                                    No appointments found matching the criteria.
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
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Info Note -->
            <div class="alert alert-success" style="margin-top: 1.5rem; background: #dbeafe; color: #1e40af; border-left-color: #3b82f6;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You can change appointment status by selecting from the dropdown. Completed appointments will be moved to medical records.</span>
            </div>
        </div>
    </div>
    
    <script>
        // View appointment details function
        function viewAppointment(id) {
            alert('Appointment ID: ' + id + '\n\nFull details feature coming soon!');
        }
        
        // Auto-submit when status changes
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                if (confirm('Change appointment status?')) {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>