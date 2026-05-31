<?php
// doctor/appointments.php - Completely Redesigned Appointments Manager
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor id
$doctorQuery = "SELECT id, specialization, consultation_fee FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build count query for pagination
$countQuery = "SELECT COUNT(*) as total FROM appointments a
               JOIN users u ON a.patient_id = u.id
               WHERE a.doctor_id = :doctor_id";
$countParams = [':doctor_id' => $doctor['id']];

if ($status_filter != 'all') {
    $countQuery .= " AND a.status = :status";
    $countParams[':status'] = $status_filter;
}
if ($date_filter) {
    $countQuery .= " AND a.appointment_date = :date";
    $countParams[':date'] = $date_filter;
}
if ($search) {
    $countQuery .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
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
$query = "SELECT a.*, u.full_name as patient_name, u.phone, u.email, u.address 
          FROM appointments a
          JOIN users u ON a.patient_id = u.id
          WHERE a.doctor_id = :doctor_id";
$params = [':doctor_id' => $doctor['id']];

if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}
if ($date_filter) {
    $query .= " AND a.appointment_date = :date";
    $params[':date'] = $date_filter;
}
if ($search) {
    $query .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT :limit OFFSET :offset";
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

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE doctor_id = :doctor_id";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->bindParam(':doctor_id', $doctor['id']);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | MediFlow HMS - Doctor Portal</title>
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
            --primary: #0EA5E9;
            --primary-dark: #0284C7;
            --primary-glow: rgba(14, 165, 233, 0.2);
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
            background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, transparent 70%);
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
            background: rgba(14, 165, 233, 0.1);
        }

        .appointments-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1400px;
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
            color: var(--primary);
        }

        .stat-pill .count {
            font-weight: 800;
            font-size: 1.1rem;
        }

        .stat-pill .label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* Filters Bar */
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
            font-family: inherit;
        }

        .filter-select:focus, .filter-date:focus, .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-input {
            min-width: 260px;
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

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Table Style Appointments */
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
            background: rgba(14, 165, 233, 0.05);
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
            background: rgba(14, 165, 233, 0.05);
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
        .status-confirmed { background: rgba(14, 165, 233, 0.15); color: #7DD3FC; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #F87171; }

        .status-select {
            padding: 0.4rem 0.6rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.7rem;
            cursor: pointer;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            width: 32px;
            height: 32px;
            border-radius: 10px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 1rem;
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
        }

        .empty-state i {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(18, 22, 33, 0.95);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            max-width: 550px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
        }

        .detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-label {
            width: 110px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .detail-value {
            flex: 1;
            font-size: 0.85rem;
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
            animation: slideInRight 0.3s ease;
        }

        .toast.show { display: flex; }
        .toast.success { border-left: 3px solid var(--accent); }
        .toast.error { border-left: 3px solid var(--danger); }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
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
        }

        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                padding: 0 1rem;
            }
            .nav-menu {
                justify-content: center;
            }
            .appointments-wrapper {
                padding: 0 1rem;
            }
            .stats-row {
                justify-content: center;
            }
            .action-buttons {
                flex-direction: column;
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
                <li><a href="appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="appointments-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-calendar-alt"></i> Appointment Manager</h1>
            <p>View and manage all your patient consultations</p>
        </div>

        <!-- Stats Pills -->
        <div class="stats-row">
            <div class="stat-pill"><i class="fas fa-list"></i><span class="count"><?php echo $stats['total']; ?></span><span class="label">Total</span></div>
            <div class="stat-pill"><i class="fas fa-hourglass-half"></i><span class="count"><?php echo $stats['pending']; ?></span><span class="label">Pending</span></div>
            <div class="stat-pill"><i class="fas fa-check-circle"></i><span class="count"><?php echo $stats['confirmed']; ?></span><span class="label">Confirmed</span></div>
            <div class="stat-pill"><i class="fas fa-check-double"></i><span class="count"><?php echo $stats['completed']; ?></span><span class="label">Completed</span></div>
            <div class="stat-pill"><i class="fas fa-ban"></i><span class="count"><?php echo $stats['cancelled']; ?></span><span class="label">Cancelled</span></div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" id="filterForm">
                <div class="filters">
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
                        <label><i class="fas fa-calendar"></i> Date</label>
                        <input type="date" name="date" class="filter-date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group" style="flex: 1;">
                        <label><i class="fas fa-search"></i> Search</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="search-input" placeholder="Patient name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <?php if ($search || $date_filter || $status_filter != 'all'): ?>
                                <a href="appointments.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Appointments Table -->
        <?php if (count($appointments) > 0): ?>
            <div class="appointments-table-container">
                <table class="appointments-table">
                    <thead>
                        <tr><th>Patient</th><th>Date & Time</th><th>Contact</th><th>Status</th><th>Symptoms</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <div class="patient-cell">
                                        <div class="patient-avatar"><?php echo strtoupper(substr($appointment['patient_name'], 0, 1)); ?></div>
                                        <div>
                                            <div class="patient-name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                            <div class="patient-email">ID: #<?php echo $appointment['patient_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    <br><small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <div style="font-size: 0.75rem;">📞 <?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">✉️ <?php echo htmlspecialchars($appointment['email']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i>
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td style="max-width: 200px;">
                                    <div style="font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms', 0, 40)); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <select class="status-select" onchange="updateStatus(<?php echo $appointment['id']; ?>, this.value)">
                                            <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                            <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirm</option>
                                            <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Complete</option>
                                            <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancel</option>
                                        </select>
                                        <button onclick="viewDetails(<?php echo $appointment['id']; ?>)" class="btn-icon" title="View Details"><i class="fas fa-eye"></i></button>
                                        <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                            <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn-icon" title="Start Consultation"><i class="fas fa-stethoscope"></i></button>
                                        <?php endif; ?>
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
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>No appointments match your search criteria.</p>
                <a href="appointments.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-sync-alt"></i> Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-calendar-check"></i> Appointment Details</span>
                <span class="close-modal" onclick="closeModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
            </div>
            <div class="modal-body" id="appointmentDetails"></div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"><i class="fas"></i><span id="toastMessage"></span></div>

    <script>
        // Set doctor theme
        document.documentElement.style.setProperty('--primary', '#0EA5E9');
        document.documentElement.style.setProperty('--primary-dark', '#0284C7');

        function updateStatus(id, status) {
            fetch('../api/update-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: status })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast('Failed to update status', 'error');
                }
            })
            .catch(() => showToast('Error updating status', 'error'));
        }

        function viewDetails(id) {
            fetch(`../api/get-appointment.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('appointmentModal');
                        const detailsDiv = document.getElementById('appointmentDetails');
                        detailsDiv.innerHTML = `
                            <div class="detail-row"><div class="detail-label">Patient:</div><div class="detail-value"><strong>${data.patient_name}</strong></div></div>
                            <div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">${data.phone || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">${data.email || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label">Address:</div><div class="detail-value">${data.address || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label">Date:</div><div class="detail-value">${data.appointment_date}</div></div>
                            <div class="detail-row"><div class="detail-label">Time:</div><div class="detail-value">${data.appointment_time}</div></div>
                            <div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="status-badge status-${data.status}">${data.status}</span></div></div>
                            <div class="detail-row"><div class="detail-label">Symptoms:</div><div class="detail-value">${data.symptoms || 'No symptoms noted'}</div></div>
                            <div class="detail-row"><div class="detail-label">Notes:</div><div class="detail-value">${data.notes || 'No additional notes'}</div></div>
                        `;
                        modal.classList.add('active');
                    } else {
                        showToast('Failed to load details', 'error');
                    }
                })
                .catch(() => showToast('Error loading details', 'error'));
        }

        function startConsultation(id) {
            if (confirm('Start consultation for this patient?')) {
                window.location.href = `update-record.php?appointment_id=${id}`;
            }
        }

        function closeModal() {
            document.getElementById('appointmentModal').classList.remove('active');
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('.fas');
            toastMessage.textContent = message;
            toast.classList.add('show', type);
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            setTimeout(() => toast.classList.remove('show', type), 3000);
        }

        window.onclick = function(event) {
            const modal = document.getElementById('appointmentModal');
            if (event.target == modal) modal.classList.remove('active');
        }
    </script>
</body>
</html>