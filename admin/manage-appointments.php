<?php
// admin/manage-appointments.php - Manage all appointments in the system with modern UI
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
$limit = 12;
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
    $countQuery .= " AND (p.full_name LIKE :search OR u.full_name LIKE :search OR p.username LIKE :search OR p.email LIKE :search)";
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
          d.consultation_fee,
          d.qualification
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
    $query .= " AND (p.full_name LIKE :search OR u.full_name LIKE :search OR p.username LIKE :search OR p.email LIKE :search)";
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
    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today,
    SUM(CASE WHEN appointment_date > CURDATE() AND status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as upcoming
    FROM appointments";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Appointments | MediFlow HMS - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .nav-link:hover, .nav-link.active { color: #2563eb; background: #eff6ff; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header p { color: #64748b; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px -8px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-number { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 0.65rem; color: #64748b; margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px; }
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
        .filter-select, .search-input, .filter-date {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .filter-select:focus, .search-input:focus, .filter-date:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }
        .search-input { min-width: 220px; }
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
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.25rem;
        }
        .appointment-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }
        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .patient-info { display: flex; align-items: center; gap: 0.75rem; }
        .patient-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        .card-body { padding: 1rem; }
        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }
        .info-row i { width: 24px; color: #2563eb; }
        .card-footer {
            padding: 1rem;
            background: #fafcff;
            border-top: 1px solid #eef2ff;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
            gap: 0.3rem;
        }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-confirmed { background: #dbeafe; color: #2563eb; }
        .badge-completed { background: #d1fae5; color: #059669; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .status-select {
            padding: 0.35rem 0.6rem;
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
        .page-link:hover, .page-link.active { background: #2563eb; color: white; border-color: #2563eb; }
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
            .appointments-grid { grid-template-columns: 1fr; }
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
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> Appointment Manager</h1>
                <p>View, manage, and control all appointments in the system</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-number"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['confirmed']; ?></div><div class="stat-label">Confirmed</div></div>
                <div class="stat-card"><div class="stat-icon">✔️</div><div class="stat-number"><?php echo $stats['completed']; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-number"><?php echo $stats['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-number"><?php echo $stats['upcoming']; ?></div><div class="stat-label">Upcoming</div></div>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div><?php endif; ?>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group"><label>Status</label><select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select></div>
                    <div class="filter-group"><label>Doctor</label><select name="doctor" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $doctor_filter == 'all' ? 'selected' : ''; ?>>All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($doctor['full_name']); ?> (<?php echo $doctor['specialization']; ?>)</option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="filter-group"><label>Date</label><input type="date" name="date" class="filter-date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()"></div>
                    <div class="filter-group" style="flex:1"><label>Search</label><div style="display:flex; gap:0.5rem;">
                        <input type="text" name="search" class="search-input" placeholder="Patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <?php if ($search || $date_filter || $doctor_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage-appointments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div></div>
                </form>
            </div>

            <!-- Appointments Grid -->
            <?php if (count($appointments) > 0): ?>
                <div class="appointments-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="card-header">
                                <div class="patient-info">
                                    <div class="patient-avatar"><?php echo strtoupper(substr($appointment['patient_name'] ?? '?', 0, 1)); ?></div>
                                    <div><strong><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></strong><br><small><?php echo htmlspecialchars($appointment['patient_email'] ?? ''); ?></small></div>
                                </div>
                                <span class="badge badge-<?php echo $appointment['status']; ?>"><i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i> <?php echo ucfirst($appointment['status']); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="info-row"><i class="fas fa-user-md"></i> <strong>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></strong><br><small><?php echo htmlspecialchars($appointment['specialization'] ?? ''); ?></small></div>
                                <div class="info-row"><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                <div class="info-row"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone'] ?? 'N/A'); ?></div>
                                <div class="info-row"><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms', 0, 60)) . ((strlen($appointment['symptoms'] ?? '') > 60) ? '...' : ''); ?></div>
                                <?php if ($appointment['consultation_fee']): ?>
                                    <div class="info-row"><i class="fas fa-dollar-sign"></i> Fee: $<?php echo number_format($appointment['consultation_fee'], 2); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <select name="new_status" class="status-select" onchange="this.form.submit()" title="Change Status">
                                        <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                        <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                        <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Completed</option>
                                        <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Delete this appointment? This action cannot be undone.')">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <button type="submit" name="delete_appointment" class="icon-btn" style="color:#dc2626;" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1;"></i><h3 style="margin-top: 1rem;">No Appointments Found</h3><p>No appointments match your search criteria.</p></div>
            <?php endif; ?>

            <div class="alert alert-info" style="margin-top: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You can change appointment status by selecting from the dropdown. Completed appointments will be moved to medical records.</span>
            </div>
        </div>
    </div>
</body>
</html>