<?php
// admin/manage-doctors.php - Completely Redesigned Doctor Management
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
$specialization_filter = $_GET['specialization'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build count query
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
            header("Location: manage-doctors.php?specialization=$specialization_filter&status=$status_filter&search=$search&page=$page");
            exit();
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
            header("Location: manage-doctors.php?specialization=$specialization_filter&status=$status_filter&search=$search&page=$page");
            exit();
        }
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT d.id) as total,
    COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN d.id END) as active,
    COUNT(DISTINCT CASE WHEN u.is_active = 0 THEN d.id END) as inactive,
    COUNT(DISTINCT CASE WHEN d.experience_years >= 10 THEN d.id END) as experienced,
    COUNT(DISTINCT CASE WHEN d.experience_years >= 5 AND d.experience_years < 10 THEN d.id END) as mid_level,
    COUNT(DISTINCT CASE WHEN d.experience_years < 5 THEN d.id END) as junior
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
    <title>Manage Doctors | MediFlow HMS - Admin</title>
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
        .stat-pill.active i { color: var(--accent); }
        .stat-pill.inactive i { color: var(--danger); }
        .stat-pill.experienced i { color: var(--warning); }
        .stat-pill.mid i { color: var(--info); }
        .stat-pill.junior i { color: var(--primary); }

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

        .filter-select, .search-input {
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .filter-select:focus, .search-input:focus {
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

        /* Doctors Grid */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }

        .doctor-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .doctor-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(139, 92, 246, 0.03);
        }

        .doctor-avatar {
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

        .doctor-info h4 {
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
        }

        .doctor-info p {
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
            margin-left: auto;
        }

        .badge-active { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .badge-inactive { background: rgba(107, 114, 128, 0.15); color: #9CA3AF; }
        .badge-spec { background: rgba(139, 92, 246, 0.15); color: #A78BFA; }

        .card-body {
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

        .card-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 0.4rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }

        .action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .delete-btn:hover {
            border-color: var(--danger);
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

        /* Alert Info */
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
            border-radius: 28px;
            max-width: 550px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            font-weight: 600;
            width: 120px;
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
            .doctors-grid {
                grid-template-columns: 1fr;
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
            .stats-row {
                gap: 0.5rem;
            }
            .stat-pill {
                padding: 0.4rem 0.8rem;
            }
            .stat-pill .label {
                display: none;
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
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="admin-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-user-md"></i> Doctor Management</h1>
            <p>View, manage, and control all doctors in the system</p>
        </div>

        <!-- Stats Pills -->
        <div class="stats-row">
            <div class="stat-pill total"><i class="fas fa-user-md"></i><span class="count"><?php echo $stats['total']; ?></span><span class="label">Total</span></div>
            <div class="stat-pill active"><i class="fas fa-check-circle"></i><span class="count"><?php echo $stats['active']; ?></span><span class="label">Active</span></div>
            <div class="stat-pill inactive"><i class="fas fa-ban"></i><span class="count"><?php echo $stats['inactive']; ?></span><span class="label">Inactive</span></div>
            <div class="stat-pill experienced"><i class="fas fa-star"></i><span class="count"><?php echo $stats['experienced']; ?></span><span class="label">10+ Years</span></div>
            <div class="stat-pill mid"><i class="fas fa-chart-line"></i><span class="count"><?php echo $stats['mid_level']; ?></span><span class="label">5-9 Years</span></div>
            <div class="stat-pill junior"><i class="fas fa-seedling"></i><span class="count"><?php echo $stats['junior']; ?></span><span class="label">Junior</span></div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" class="filters">
                <div class="filter-group">
                    <label><i class="fas fa-stethoscope"></i> Specialization</label>
                    <select name="specialization" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $specialization_filter == 'all' ? 'selected' : ''; ?>>All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization_filter == $spec['specialization'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec['specialization']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-circle"></i> Status</label>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 1;">
                    <label><i class="fas fa-search"></i> Search</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="search" class="search-input" placeholder="Name, email, specialization..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <?php if ($search || $specialization_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage-doctors.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Doctors Grid -->
        <?php if (count($doctors) > 0): ?>
            <div class="doctors-grid">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="card-header">
                            <div class="doctor-avatar"><?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?></div>
                            <div class="doctor-info">
                                <h4>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                                <p>@<?php echo htmlspecialchars($doctor['username']); ?></p>
                            </div>
                            <span class="status-badge badge-<?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                <i class="fas <?php echo $doctor['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></div>
                            <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></div>
                            <div class="info-row"><i class="fas fa-stethoscope"></i> <span class="status-badge badge-spec"><?php echo htmlspecialchars($doctor['specialization']); ?></span></div>
                            <div class="info-row"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars(substr($doctor['qualification'], 0, 35)); ?></div>
                            <div class="info-row"><i class="fas fa-briefcase"></i> <?php echo $doctor['experience_years']; ?> years experience</div>
                            <div class="info-row"><i class="fas fa-dollar-sign"></i> <strong>R<?php echo number_format($doctor['consultation_fee'], 2); ?></strong> per consultation</div>
                        </div>
                        <div class="card-footer">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <button type="submit" name="toggle_status" class="action-btn" title="<?php echo $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $doctor['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                </button>
                            </form>
                            <button class="action-btn" onclick='viewDoctor(<?php echo json_encode($doctor); ?>)' title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ PERMANENT ACTION!\n\nDelete Dr. <?php echo addslashes($doctor['full_name']); ?>?\nAll associated data will be lost!')">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <button type="submit" name="delete_doctor" class="action-btn delete-btn" title="Delete Doctor">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-md-slash"></i>
                <h3>No Doctors Found</h3>
                <p>No doctors match your search criteria.</p>
                <a href="manage-doctors.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-sync-alt"></i> Clear Filters</a>
            </div>
        <?php endif; ?>

        <!-- Info Note -->
        <div class="alert-info">
            <i class="fas fa-info-circle"></i>
            <span><strong>Note:</strong> You can activate/deactivate doctors, view their full details, or remove them from the system. Deactivated doctors cannot be booked by patients.</span>
        </div>
    </div>

    <!-- Doctor Details Modal -->
    <div id="doctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-md"></i> Doctor Details
                <span style="cursor: pointer; margin-left: auto; font-size: 1.2rem;" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="doctorDetails"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.documentElement.style.setProperty('--primary', '#8B5CF6');
        document.documentElement.style.setProperty('--primary-dark', '#7C3AED');

        function viewDoctor(doctor) {
            const modal = document.getElementById('doctorModal');
            const detailsDiv = document.getElementById('doctorDetails');
            detailsDiv.innerHTML = `
                <div class="detail-row"><div class="detail-label">Full Name:</div><div class="detail-value"><strong>Dr. ${doctor.full_name}</strong></div></div>
                <div class="detail-row"><div class="detail-label">Username:</div><div class="detail-value">@${doctor.username}</div></div>
                <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">${doctor.email}</div></div>
                <div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">${doctor.phone || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Address:</div><div class="detail-value">${doctor.address || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Specialization:</div><div class="detail-value"><span class="status-badge badge-spec">${doctor.specialization}</span></div></div>
                <div class="detail-row"><div class="detail-label">Qualification:</div><div class="detail-value">${doctor.qualification}</div></div>
                <div class="detail-row"><div class="detail-label">Experience:</div><div class="detail-value">${doctor.experience_years} years</div></div>
                <div class="detail-row"><div class="detail-label">Consultation Fee:</div><div class="detail-value"><strong>R${parseFloat(doctor.consultation_fee).toFixed(2)}</strong></div></div>
                <div class="detail-row"><div class="detail-label">Available Days:</div><div class="detail-value">${doctor.available_days || 'Not set'}</div></div>
                <div class="detail-row"><div class="detail-label">Working Hours:</div><div class="detail-value">${doctor.available_time_start || 'N/A'} - ${doctor.available_time_end || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Member Since:</div><div class="detail-value">${new Date(doctor.created_at).toLocaleDateString()}</div></div>
                <div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="status-badge ${doctor.is_active ? 'badge-active' : 'badge-inactive'}">${doctor.is_active ? 'Active' : 'Inactive'}</span></div></div>
            `;
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('doctorModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('doctorModal');
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>