<?php
// doctor/appointments.php - Manage appointments with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check doctor role
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
$limit = 12;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Appointments | MediFlow HMS - Doctor Portal</title>
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

        .filter-select, .search-input, .filter-date {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .filter-select:focus, .search-input:focus, .filter-date:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .search-input {
            min-width: 260px;
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

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
        }

        /* Appointment Cards */
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.25rem;
        }

        .appointment-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
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

        .patient-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .patient-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .patient-details h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .patient-details p {
            font-size: 0.7rem;
            color: #64748b;
        }

        .date-badge {
            text-align: right;
            font-size: 0.7rem;
            color: #2563eb;
            font-weight: 500;
        }

        .card-body {
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

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row i {
            width: 24px;
            color: #2563eb;
        }

        .symptoms {
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 12px;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #475569;
        }

        .card-footer {
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

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-completed {
            background: #d1fae5;
            color: #059669;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Status Select */
        .status-select {
            padding: 0.4rem 0.6rem;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.7rem;
            font-family: inherit;
            cursor: pointer;
            background: white;
        }

        .status-select:focus {
            outline: none;
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
            max-width: 550px;
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
            width: 120px;
            color: #475569;
            font-size: 0.8rem;
        }

        .detail-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.85rem;
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

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e293b;
            color: white;
            padding: 0.875rem 1.25rem;
            border-radius: 16px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 3000;
            animation: slideInRight 0.3s ease;
        }

        .toast.success { background: #059669; }
        .toast.error { background: #dc2626; }
        .toast.show { display: flex; }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
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
            .appointments-grid {
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
                <li><a href="appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
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
                <h1><i class="fas fa-calendar-alt"></i> Appointment Manager</h1>
                <p>View and manage all your patient appointments in one place</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?php echo $stats['total']; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?php echo $stats['confirmed']; ?></div><div class="stat-label">Confirmed</div></div>
                <div class="stat-card"><div class="stat-icon">✔️</div><div class="stat-value"><?php echo $stats['completed']; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-value"><?php echo $stats['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters" id="filterForm">
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
                </form>
            </div>

            <!-- Appointments Grid -->
            <?php if (count($appointments) > 0): ?>
                <div class="appointments-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="card-header">
                                <div class="patient-info">
                                    <div class="patient-avatar">
                                        <?php echo strtoupper(substr($appointment['patient_name'], 0, 1)); ?>
                                    </div>
                                    <div class="patient-details">
                                        <h3><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($appointment['email']); ?></p>
                                    </div>
                                </div>
                                <div class="date-badge">
                                    <?php echo date('M d', strtotime($appointment['appointment_date'])); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <i class="fas fa-calendar-day"></i>
                                    <span><?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="symptoms">
                                    <i class="fas fa-notes-medical" style="margin-right: 0.5rem;"></i>
                                    <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms noted', 0, 80)) . ((strlen($appointment['symptoms'] ?? '') > 80) ? '...' : ''); ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <select class="status-select" onchange="updateStatus(<?php echo $appointment['id']; ?>, this.value)">
                                    <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                    <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                    <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Completed</option>
                                    <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                </select>
                                <button onclick="viewDetails(<?php echo $appointment['id']; ?>)" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</button>
                                <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                    <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm"><i class="fas fa-stethoscope"></i> Consult</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
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
    </div>

    <!-- Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-calendar-check"></i> Appointment Details</span>
                <span class="close-modal" onclick="closeModal()">&times;</span>
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
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed to update status', 'error');
                    location.reload();
                }
            })
            .catch(() => { showToast('Error updating status', 'error'); location.reload(); });
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
                            <div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="badge badge-${data.status}">${data.status}</span></div></div>
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