<?php
// admin/manage-appointments.php - Completely Redesigned Appointment Management
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
$status_filter = $_GET['status'] ?? 'all';
$doctor_filter = $_GET['doctor'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build count query
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
            header("Location: manage-appointments.php?status=$status_filter&doctor=$doctor_filter&date=$date_filter&search=$search&page=$page");
            exit();
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
        header("Location: manage-appointments.php?status=$status_filter&doctor=$doctor_filter&date=$date_filter&search=$search&page=$page");
        exit();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | MediFlow HMS - Admin</title>
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
            --primary: #8B5CF6;
            --primary-dark: #7C3AED;
            --primary-glow: rgba(139, 92, 246, 0.2);
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

        .bg-orb-1 {
            position: fixed;
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 70%);
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
            background: rgba(139, 92, 246, 0.1);
        }

        .admin-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

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

        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
        }

        .stat-pill:hover {
            border-color: var(--primary);
        }

        .stat-pill i {
            font-size: 1.1rem;
        }
        .stat-pill.total i { color: var(--primary); }
        .stat-pill.pending i { color: var(--warning); }
        .stat-pill.confirmed i { color: var(--info); }
        .stat-pill.completed i { color: var(--accent); }
        .stat-pill.cancelled i { color: var(--danger); }
        .stat-pill.upcoming i { color: var(--primary); }

        .stat-pill .count {
            font-weight: 800;
            font-size: 1.1rem;
        }

        .stat-pill .label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* Filters Card */
        .filters-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
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
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select, .filter-date, .search-input {
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .filter-select:focus, .filter-date:focus, .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-input {
            min-width: 220px;
        }

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

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Appointments Table */
        .appointments-table-container {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table th {
            text-align: left;
            padding: 1rem 1rem;
            background: rgba(139, 92, 246, 0.05);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .appointments-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: middle;
        }

        .appointments-table tr:hover td {
            background: rgba(139, 92, 246, 0.05);
        }

        .patient-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .patient-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .patient-email {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .doctor-cell {
            display: flex;
            flex-direction: column;
        }

        .doctor-name {
            font-weight: 500;
        }

        .doctor-spec {
            font-size: 0.65rem;
            color: var(--primary);
        }

        .date-cell {
            font-size: 0.85rem;
        }

        .date-cell small {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .status-pending { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .status-confirmed { background: rgba(59, 130, 246, 0.15); color: #60A5FA; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #F87171; }

        .status-select {
            padding: 0.3rem 0.6rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 0.7rem;
            cursor: pointer;
        }

        .action-icons {
            display: flex;
            gap: 0.5rem;
        }

        .icon-btn {
            background: transparent;
            border: none;
            padding: 0.4rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }

        .icon-btn:hover {
            background: rgba(139, 92, 246, 0.15);
            color: var(--primary);
        }

        .delete-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 0.9rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .page-link:hover, .page-link.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(18, 22, 33, 0.5);
            border-radius: 28px;
        }

        .empty-state i {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        /* Alert */
        .alert-info {
            background: rgba(139, 92, 246, 0.08);
            border-left: 3px solid var(--primary);
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .appointments-table {
                display: block;
                overflow-x: auto;
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
            .stats-row {
                justify-content: center;
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
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="admin-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-calendar-alt"></i> Appointment Manager</h1>
            <p>View, manage, and control all appointments in the system</p>
        </div>

        <!-- Stats Pills -->
        <div class="stats-row">
            <div class="stat-pill total"><i class="fas fa-list"></i><span class="count"><?php echo $stats['total']; ?></span><span class="label">Total</span></div>
            <div class="stat-pill pending"><i class="fas fa-clock"></i><span class="count"><?php echo $stats['pending']; ?></span><span class="label">Pending</span></div>
            <div class="stat-pill confirmed"><i class="fas fa-check-circle"></i><span class="count"><?php echo $stats['confirmed']; ?></span><span class="label">Confirmed</span></div>
            <div class="stat-pill completed"><i class="fas fa-check-double"></i><span class="count"><?php echo $stats['completed']; ?></span><span class="label">Completed</span></div>
            <div class="stat-pill cancelled"><i class="fas fa-ban"></i><span class="count"><?php echo $stats['cancelled']; ?></span><span class="label">Cancelled</span></div>
            <div class="stat-pill upcoming"><i class="fas fa-calendar-week"></i><span class="count"><?php echo $stats['upcoming']; ?></span><span class="label">Upcoming</span></div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
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
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($doctor['full_name']); ?> (<?php echo $doctor['specialization']; ?>)</option>
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
                        <input type="text" name="search" class="search-input" placeholder="Patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <?php if ($search || $date_filter || $doctor_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage-appointments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <?php if (count($appointments) > 0): ?>
            <div class="appointments-table-container">
                <table class="appointments-table">
                    <thead>
                        <tr><th>Patient</th><th>Doctor</th><th>Date & Time</th><th>Status</th><th>Symptoms</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar"><?php echo strtoupper(substr($appointment['patient_name'] ?? '?', 0, 1)); ?></div>
                                        <div>
                                            <div class="patient-name"><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></div>
                                            <div class="patient-email"><?php echo htmlspecialchars($appointment['patient_email'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="doctor-cell">
                                        <span class="doctor-name">Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></span>
                                        <span class="doctor-spec"><?php echo htmlspecialchars($appointment['specialization'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    <br><small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i>
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td style="max-width: 180px;">
                                    <span style="font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                                        <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms', 0, 40)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <select name="new_status" class="status-select" onchange="this.form.submit()" title="Change Status">
                                                <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                                <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirm</option>
                                                <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Complete</option>
                                                <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancel</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Delete this appointment? This action cannot be undone.')">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <button type="submit" name="delete_appointment" class="icon-btn delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&doctor=<?php echo $doctor_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>No appointments match your search criteria.</p>
                <a href="manage-appointments.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-sync-alt"></i> Clear Filters</a>
            </div>
        <?php endif; ?>

        <!-- Info Note -->
        <div class="alert-info">
            <i class="fas fa-info-circle"></i>
            <span><strong>Note:</strong> You can change appointment status by selecting from the dropdown. Completed appointments will be moved to medical records.</span>
        </div>
    </div>

    <script>
        document.documentElement.style.setProperty('--primary', '#8B5CF6');
        document.documentElement.style.setProperty('--primary-dark', '#7C3AED');
    </script>
</body>
</html>