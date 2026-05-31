<?php
// doctor/patients.php - Completely Redesigned Patients Manager
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor info
$doctorQuery = "SELECT d.id as doctor_id, d.specialization, d.consultation_fee, u.full_name 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'last_visit';
$order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build count query to get unique patients
$countQuery = "SELECT COUNT(DISTINCT u.id) as total 
               FROM users u
               JOIN appointments a ON u.id = a.patient_id
               WHERE a.doctor_id = :doctor_id";
$countParams = [':doctor_id' => $doctor['doctor_id']];

if ($search) {
    $countQuery .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $countParams[':search'] = "%$search%";
}

$countStmt = $db->prepare($countQuery);
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total_patients = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_patients / $limit);

// Build main query
$query = "SELECT DISTINCT 
          u.id as patient_id, 
          u.username, 
          u.full_name, 
          u.email, 
          u.phone, 
          u.address, 
          u.created_at as registered_date,
          (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = u.id AND doctor_id = :doctor_id1) as last_visit,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id AND doctor_id = :doctor_id2) as total_visits,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id AND doctor_id = :doctor_id3 AND status = 'completed') as completed_visits,
          (SELECT GROUP_CONCAT(DISTINCT status) FROM appointments WHERE patient_id = u.id AND doctor_id = :doctor_id4) as statuses
          FROM users u
          JOIN appointments a ON u.id = a.patient_id
          WHERE a.doctor_id = :doctor_id5";
$params = [
    ':doctor_id1' => $doctor['doctor_id'],
    ':doctor_id2' => $doctor['doctor_id'],
    ':doctor_id3' => $doctor['doctor_id'],
    ':doctor_id4' => $doctor['doctor_id'],
    ':doctor_id5' => $doctor['doctor_id']
];

if ($search) {
    $query .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add sorting
switch ($sort) {
    case 'name':
        $query .= " ORDER BY u.full_name $order";
        break;
    case 'visits':
        $query .= " ORDER BY total_visits $order";
        break;
    case 'registered':
        $query .= " ORDER BY u.created_at $order";
        break;
    case 'last_visit':
    default:
        $query .= " ORDER BY last_visit $order, u.full_name ASC";
        break;
}

$query .= " LIMIT :limit OFFSET :offset";
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
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
    COUNT(DISTINCT u.id) as total_patients,
    COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN u.id END) as completed_patients,
    COUNT(DISTINCT CASE WHEN a.status IN ('pending', 'confirmed') THEN u.id END) as active_patients,
    COUNT(a.id) as total_appointments,
    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_appointments,
    COUNT(CASE WHEN a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30days
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = :doctor_id";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->bindParam(':doctor_id', $doctor['doctor_id']);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent patients (last 30 days)
$recentQuery = "SELECT DISTINCT u.id as patient_id, u.full_name, u.email, u.phone, MAX(a.appointment_date) as last_visit
                FROM users u
                JOIN appointments a ON u.id = a.patient_id
                WHERE a.doctor_id = :doctor_id 
                AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY u.id
                ORDER BY last_visit DESC
                LIMIT 5";
$recentStmt = $db->prepare($recentQuery);
$recentStmt->bindParam(':doctor_id', $doctor['doctor_id']);
$recentStmt->execute();
$recent_patients = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients | MediFlow HMS - Doctor Portal</title>
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

        .patients-wrapper {
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

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .glass-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(14, 165, 233, 0.03);
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.25rem 1.5rem;
        }

        /* Recent Patients List */
        .recent-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            transition: all 0.2s;
        }

        .recent-item:hover {
            background: rgba(14, 165, 233, 0.08);
        }

        .recent-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 0.75rem;
        }

        .recent-info {
            flex: 1;
        }

        .recent-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .recent-date {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        /* Progress Bars */
        .progress-item {
            margin-bottom: 1rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            height: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 6px;
            border-radius: 10px;
        }

        .fill-primary { background: var(--primary); }
        .fill-success { background: var(--accent); }

        .analytics-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .analytics-row:last-child {
            border-bottom: none;
        }

        /* Filters */
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
            flex: 1;
        }

        .filter-group label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .search-input {
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.85rem;
            color: var(--text-main);
            width: 100%;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
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

        /* Patients Grid */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }

        .patient-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .patient-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .patient-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(14, 165, 233, 0.03);
        }

        .patient-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .patient-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .patient-info p {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 40px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(245, 158, 11, 0.15);
            color: #FBBF24;
        }

        .badge-stable {
            background: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }

        .patient-body {
            padding: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.75rem;
        }

        .info-row i {
            width: 24px;
            color: var(--primary);
        }

        .visit-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
        }

        .visit-stat {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .visit-stat strong {
            color: var(--primary);
        }

        .patient-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
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
            gap: 0.75rem;
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

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .two-columns {
                grid-template-columns: 1fr;
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
            .patients-wrapper {
                padding: 0 1rem;
            }
            .filters {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
            .patients-grid {
                grid-template-columns: 1fr;
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
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="patients-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-users"></i> My Patients</h1>
            <p>View and manage all your patients, their medical history, and appointment records</p>
        </div>

        <!-- Stats Pills -->
        <div class="stats-row">
            <div class="stat-pill"><i class="fas fa-user-friends"></i><span class="count"><?php echo $stats['total_patients'] ?? 0; ?></span><span class="label">Total</span></div>
            <div class="stat-pill"><i class="fas fa-user-check"></i><span class="count"><?php echo $stats['active_patients'] ?? 0; ?></span><span class="label">Active</span></div>
            <div class="stat-pill"><i class="fas fa-calendar-check"></i><span class="count"><?php echo $stats['total_appointments'] ?? 0; ?></span><span class="label">Appointments</span></div>
            <div class="stat-pill"><i class="fas fa-check-double"></i><span class="count"><?php echo $stats['completed_appointments'] ?? 0; ?></span><span class="label">Completed</span></div>
            <div class="stat-pill"><i class="fas fa-chart-line"></i><span class="count"><?php echo $stats['last_30days'] ?? 0; ?></span><span class="label">Last 30d</span></div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-columns">
            <!-- Right Column: Recent & Analytics -->
            <div>
                <!-- Recent Patients -->
                <div class="glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recently Active</h3>
                        <span style="font-size: 0.65rem; color: var(--text-muted);">Last 30 days</span>
                    </div>
                    <div class="card-body">
                        <div class="recent-list">
                            <?php if (count($recent_patients) > 0): ?>
                                <?php foreach ($recent_patients as $recent): ?>
                                    <div class="recent-item">
                                        <div style="display: flex; align-items: center;">
                                            <div class="recent-avatar"><?php echo strtoupper(substr($recent['full_name'], 0, 1)); ?></div>
                                            <div class="recent-info">
                                                <div class="recent-name"><?php echo htmlspecialchars($recent['full_name']); ?></div>
                                                <div class="recent-date"><?php echo htmlspecialchars($recent['email']); ?></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--primary);">
                                            <?php echo date('M d', strtotime($recent['last_visit'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 1rem;">No recent patients</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Practice Analytics -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Practice Analytics</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-item">
                            <div class="progress-header"><span>Completion Rate</span><span><?php echo ($stats['total_appointments'] ?? 0) > 0 ? round((($stats['completed_appointments'] ?? 0) / max($stats['total_appointments'], 1)) * 100) : 0; ?>%</span></div>
                            <div class="progress-bar"><div class="progress-fill fill-success" style="width: <?php echo ($stats['total_appointments'] ?? 0) > 0 ? round((($stats['completed_appointments'] ?? 0) / max($stats['total_appointments'], 1)) * 100) : 0; ?>%"></div></div>
                        </div>
                        <div class="progress-item">
                            <div class="progress-header"><span>Patient Retention</span><span><?php echo ($stats['total_patients'] ?? 0) > 0 ? round((($stats['active_patients'] ?? 0) / max($stats['total_patients'], 1)) * 100) : 0; ?>%</span></div>
                            <div class="progress-bar"><div class="progress-fill fill-primary" style="width: <?php echo ($stats['total_patients'] ?? 0) > 0 ? round((($stats['active_patients'] ?? 0) / max($stats['total_patients'], 1)) * 100) : 0; ?>%"></div></div>
                        </div>
                        <div class="analytics-row">
                            <span>Avg. Visits/Patient</span>
                            <strong><?php echo ($stats['total_patients'] ?? 0) > 0 ? round(($stats['total_appointments'] ?? 0) / max($stats['total_patients'], 1), 1) : 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Left Column: Search & Patient Grid -->
            <div>
                <!-- Filters -->
                <div class="filters-card">
                    <form method="GET" action="" class="filters">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search Patient</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" name="search" class="search-input" placeholder="Name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                <?php if ($search): ?>
                                    <a href="patients.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Patients Grid -->
                <?php if (count($patients) > 0): ?>
                    <div class="patients-grid">
                        <?php foreach ($patients as $patient): ?>
                            <?php $hasActive = strpos($patient['statuses'] ?? '', 'pending') !== false || strpos($patient['statuses'] ?? '', 'confirmed') !== false; ?>
                            <div class="patient-card">
                                <div class="patient-header">
                                    <div class="patient-avatar"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                    <div class="patient-info">
                                        <h4><?php echo htmlspecialchars($patient['full_name']); ?></h4>
                                        <p>@<?php echo htmlspecialchars($patient['username']); ?></p>
                                    </div>
                                    <span class="status-badge <?php echo $hasActive ? 'badge-active' : 'badge-stable'; ?>">
                                        <i class="fas <?php echo $hasActive ? 'fa-clock' : 'fa-check-circle'; ?>"></i> <?php echo $hasActive ? 'Active' : 'Stable'; ?>
                                    </span>
                                </div>
                                <div class="patient-body">
                                    <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></div>
                                    <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></div>
                                    <div class="info-row"><i class="fas fa-calendar-alt"></i> Since <?php echo date('M Y', strtotime($patient['registered_date'])); ?></div>
                                    <div class="visit-stats">
                                        <div class="visit-stat"><strong><?php echo $patient['total_visits']; ?></strong> total</div>
                                        <div class="visit-stat"><strong><?php echo $patient['completed_visits']; ?></strong> completed</div>
                                        <?php if ($patient['last_visit']): ?>
                                            <div class="visit-stat"><strong><?php echo date('M d', strtotime($patient['last_visit'])); ?></strong> last</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="patient-footer">
                                    <button onclick="viewPatient(<?php echo $patient['patient_id']; ?>)" class="btn btn-outline"><i class="fas fa-eye"></i> Profile</button>
                                    <button onclick="viewMedicalHistory(<?php echo $patient['patient_id']; ?>)" class="btn btn-primary"><i class="fas fa-notes-medical"></i> History</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-friends"></i>
                        <h3>No Patients Found</h3>
                        <p>No patients match your search criteria.</p>
                        <a href="patients.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-sync-alt"></i> Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Patient Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-user-circle"></i> Patient Details</span>
                <span class="close-modal" onclick="closeModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
            </div>
            <div class="modal-body" id="patientDetails"></div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" id="viewHistoryBtn">Medical History</button>
            </div>
        </div>
    </div>

    <script>
        // Set doctor theme
        document.documentElement.style.setProperty('--primary', '#0EA5E9');
        document.documentElement.style.setProperty('--primary-dark', '#0284C7');

        let currentPatientId = null;

        function viewPatient(patientId) {
            currentPatientId = patientId;
            fetch(`../api/get-patient.php?id=${patientId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('patientModal');
                        const detailsDiv = document.getElementById('patientDetails');
                        detailsDiv.innerHTML = `
                            <div class="detail-row"><div class="detail-label">Full Name:</div><div class="detail-value"><strong>${data.full_name}</strong></div></div>
                            <div class="detail-row"><div class="detail-label">Username:</div><div class="detail-value">${data.username}</div></div>
                            <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">${data.email}</div></div>
                            <div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">${data.phone || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label">Address:</div><div class="detail-value">${data.address || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label">Member Since:</div><div class="detail-value">${data.created_at}</div></div>
                        `;
                        modal.classList.add('active');
                        document.getElementById('viewHistoryBtn').onclick = () => viewMedicalHistory(patientId);
                    } else {
                        alert('Failed to load patient details');
                    }
                })
                .catch(() => alert('Error loading patient details'));
        }

        function viewMedicalHistory(patientId) {
            window.location.href = `medical-history.php?patient_id=${patientId}`;
        }

        function closeModal() {
            document.getElementById('patientModal').classList.remove('active');
            currentPatientId = null;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('patientModal');
            if (event.target == modal) modal.classList.remove('active');
        }
    </script>
</body>
</html>