<?php
// doctor/patients.php - Manage doctor's patients with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check doctor role
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

// Build query to get unique patients
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
                LIMIT 6";
$recentStmt = $db->prepare($recentQuery);
$recentStmt->bindParam(':doctor_id', $doctor['doctor_id']);
$recentStmt->execute();
$recent_patients = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Patients | MediFlow HMS - Doctor Portal</title>
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

        /* Modern Navbar */
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

        .logo i {
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
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

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Page Header */
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

        .page-header h1 i {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
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

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 24px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .card-header {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .card-header i {
            color: #2563eb;
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
            padding: 0.6rem;
            background: #f8fafc;
            border-radius: 16px;
            transition: all 0.2s;
        }

        .recent-item:hover {
            background: #eff6ff;
            transform: translateX(3px);
        }

        .recent-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .recent-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .progress-bar {
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            height: 6px;
        }

        .progress-fill {
            height: 6px;
            border-radius: 10px;
        }

        /* Filters Bar */
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

        .search-input {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #f8fafc;
            transition: all 0.2s;
            min-width: 260px;
        }

        .search-input:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

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

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
        }

        /* Patient Cards Grid */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.25rem;
        }

        .patient-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }

        .patient-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.12);
        }

        .patient-card-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .patient-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .patient-name {
            flex: 1;
        }

        .patient-name h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .patient-name p {
            font-size: 0.7rem;
            color: #64748b;
        }

        .patient-card-body {
            padding: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }

        .info-row i {
            width: 24px;
            color: #2563eb;
        }

        .visit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #dbeafe;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            color: #2563eb;
        }

        .patient-card-footer {
            padding: 1rem;
            background: #fafcff;
            border-top: 1px solid #eef2ff;
            display: flex;
            gap: 0.75rem;
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

        .badge-success {
            background: #d1fae5;
            color: #059669;
        }

        .badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-info {
            background: #dbeafe;
            color: #2563eb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 24px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
            display: block;
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 32px;
            max-width: 600px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eef2ff;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .close-modal:hover {
            color: #dc2626;
        }

        .detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-label {
            font-weight: 600;
            width: 110px;
            color: #475569;
            font-size: 0.8rem;
        }

        .detail-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.85rem;
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
                padding: 0 1rem;
            }
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            .container {
                padding: 0 1rem;
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
            .patients-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heartbeat"></i>
                <span>MediFlow HMS</span>
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

    <div class="container">
        <div class="fade-in">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-users"></i> My Patients</h1>
                <p>View and manage all your patients, their medical history, and appointment records</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?php echo $stats['total_patients'] ?? 0; ?></div><div class="stat-label">Total Patients</div></div>
                <div class="stat-card"><div class="stat-icon">🟢</div><div class="stat-value"><?php echo $stats['active_patients'] ?? 0; ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></div><div class="stat-label">Appointments</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?php echo $stats['completed_appointments'] ?? 0; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value"><?php echo $stats['last_30days'] ?? 0; ?></div><div class="stat-label">Last 30 days</div></div>
            </div>

            <!-- Dashboard Widgets -->
            <div class="dashboard-grid">
                <!-- Recent Patients -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-plus"></i> Recently Active
                        <span style="margin-left: auto; font-size: 0.65rem; color: #64748b;">Last 30 days</span>
                    </div>
                    <div class="recent-list">
                        <?php if (count($recent_patients) > 0): ?>
                            <?php foreach ($recent_patients as $recent): ?>
                                <div class="recent-item">
                                    <div class="recent-info">
                                        <div class="recent-avatar"><?php echo strtoupper(substr($recent['full_name'], 0, 1)); ?></div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($recent['full_name']); ?></strong>
                                            <br><small style="color:#64748b;"><?php echo htmlspecialchars($recent['email']); ?></small>
                                        </div>
                                    </div>
                                    <div><small><i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($recent['last_visit'])); ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:1rem; color:#94a3b8;">No recent patients</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Practice Analytics
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Completion Rate</span>
                            <strong><?php echo ($stats['total_appointments'] ?? 0) > 0 ? round((($stats['completed_appointments'] ?? 0) / max($stats['total_appointments'], 1)) * 100) : 0; ?>%</strong>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($stats['total_appointments'] ?? 0) > 0 ? round((($stats['completed_appointments'] ?? 0) / max($stats['total_appointments'], 1)) * 100) : 0; ?>%; background:#10b981;"></div></div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Patient Retention</span>
                            <strong><?php echo ($stats['total_patients'] ?? 0) > 0 ? round((($stats['active_patients'] ?? 0) / max($stats['total_patients'], 1)) * 100) : 0; ?>%</strong>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($stats['total_patients'] ?? 0) > 0 ? round((($stats['active_patients'] ?? 0) / max($stats['total_patients'], 1)) * 100) : 0; ?>%; background:#3b82f6;"></div></div>
                    </div>
                    <div style="padding-top:0.5rem; border-top:1px solid #eef2ff;">
                        <div style="display:flex; justify-content:space-between;">
                            <span><i class="fas fa-chart-line"></i> Avg. Visits/Patient</span>
                            <strong><?php echo ($stats['total_patients'] ?? 0) > 0 ? round(($stats['total_appointments'] ?? 0) / max($stats['total_patients'], 1), 1) : 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group" style="flex: 1;">
                        <label><i class="fas fa-search"></i> Search Patient</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="search-input" placeholder="Name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                            <input type="hidden" name="order" value="<?php echo $order; ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <?php if ($search): ?>
                                <a href="patients.php?sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Patients Grid -->
            <?php if (count($patients) > 0): ?>
                <div class="patients-grid">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-card-header">
                                <div class="patient-avatar"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                <div class="patient-name">
                                    <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($patient['username']); ?></p>
                                </div>
                                <?php 
                                $hasActive = strpos($patient['statuses'] ?? '', 'pending') !== false || strpos($patient['statuses'] ?? '', 'confirmed') !== false;
                                ?>
                                <span class="badge <?php echo $hasActive ? 'badge-warning' : 'badge-success'; ?>">
                                    <i class="fas <?php echo $hasActive ? 'fa-clock' : 'fa-check-circle'; ?>"></i> <?php echo $hasActive ? 'Active' : 'Stable'; ?>
                                </span>
                            </div>
                            <div class="patient-card-body">
                                <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></div>
                                <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></div>
                                <div class="info-row"><i class="fas fa-calendar-alt"></i> Registered: <?php echo date('M d, Y', strtotime($patient['registered_date'])); ?></div>
                                <div class="info-row">
                                    <i class="fas fa-stethoscope"></i> 
                                    <span class="visit-badge"><i class="fas fa-calendar-check"></i> <?php echo $patient['total_visits']; ?> total visits</span>
                                    <span class="visit-badge"><i class="fas fa-check-double"></i> <?php echo $patient['completed_visits']; ?> completed</span>
                                </div>
                                <?php if ($patient['last_visit']): ?>
                                    <div class="info-row"><i class="fas fa-clock"></i> Last visit: <?php echo date('M d, Y', strtotime($patient['last_visit'])); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="patient-card-footer">
                                <button onclick="viewPatient(<?php echo $patient['patient_id']; ?>)" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Profile</button>
                                <button onclick="viewMedicalHistory(<?php echo $patient['patient_id']; ?>)" class="btn btn-primary btn-sm"><i class="fas fa-notes-medical"></i> History</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
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

    <!-- Patient Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-user-circle"></i> Patient Details</span>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="patientDetails"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" id="viewHistoryBtn">Medical History</button>
            </div>
        </div>
    </div>

    <script>
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