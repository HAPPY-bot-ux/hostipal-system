<?php
// admin/manage-doctors.php - Manage all doctors in the system with modern UI
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
$limit = 12;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Doctors | MediFlow HMS - Admin</title>
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
        .filter-select, .search-input {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .filter-select:focus, .search-input:focus {
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
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }
        .doctor-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }
        .doctor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .doctor-avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
        }
        .doctor-info h3 { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; }
        .doctor-info p { font-size: 0.7rem; color: #64748b; }
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
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }
        .badge-specialization { background: #dbeafe; color: #2563eb; }
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
        .modal.active { display: flex; }
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
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #eef2ff; display: flex; justify-content: flex-end; }
        .detail-row {
            display: flex;
            padding: 0.7rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-label { font-weight: 600; width: 120px; color: #475569; font-size: 0.8rem; }
        .detail-value { flex: 1; color: #1e293b; font-size: 0.85rem; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
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
            .doctors-grid { grid-template-columns: 1fr; }
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
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h1><i class="fas fa-user-md"></i> Doctor Management</h1>
                <p>View, manage, and control all doctors in the system</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">👨‍⚕️</div><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-icon">⛔</div><div class="stat-number"><?php echo $stats['inactive']; ?></div><div class="stat-label">Inactive</div></div>
                <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-number"><?php echo $stats['experienced']; ?></div><div class="stat-label">10+ Years</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-number"><?php echo $stats['mid_level']; ?></div><div class="stat-label">5-9 Years</div></div>
                <div class="stat-card"><div class="stat-icon">🌱</div><div class="stat-number"><?php echo $stats['junior']; ?></div><div class="stat-label">Junior</div></div>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div><?php endif; ?>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group"><label>Specialization</label><select name="specialization" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $specialization_filter == 'all' ? 'selected' : ''; ?>>All</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization_filter == $spec['specialization'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec['specialization']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="filter-group"><label>Status</label><select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select></div>
                    <div class="filter-group" style="flex:1"><label>Search</label><div style="display:flex; gap:0.5rem;">
                        <input type="text" name="search" class="search-input" placeholder="Name, email, specialization..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <?php if ($search || $specialization_filter != 'all' || $status_filter != 'all'): ?>
                            <a href="manage-doctors.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div></div>
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
                                    <h3>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($doctor['username']); ?></p>
                                </div>
                                <span class="badge badge-<?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>" style="margin-left: auto;">
                                    <i class="fas <?php echo $doctor['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></div>
                                <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></div>
                                <div class="info-row"><i class="fas fa-stethoscope"></i> <span class="badge badge-specialization"><?php echo htmlspecialchars($doctor['specialization']); ?></span></div>
                                <div class="info-row"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars(substr($doctor['qualification'], 0, 40)) . (strlen($doctor['qualification'] ?? '') > 40 ? '...' : ''); ?></div>
                                <div class="info-row"><i class="fas fa-briefcase"></i> <?php echo $doctor['experience_years']; ?> years experience</div>
                                <div class="info-row"><i class="fas fa-dollar-sign"></i> <strong>$<?php echo number_format($doctor['consultation_fee'], 2); ?></strong> per consultation</div>
                            </div>
                            <div class="card-footer">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                    <button type="submit" name="toggle_status" class="icon-btn" style="color: <?php echo $doctor['is_active'] ? '#f59e0b' : '#10b981'; ?>" title="<?php echo $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas <?php echo $doctor['is_active'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                    </button>
                                </form>
                                <button class="icon-btn" style="color: #3b82f6;" title="View Details" onclick='viewDoctor(<?php echo json_encode($doctor); ?>)'>
                                    <i class="fas fa-eye"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ PERMANENT ACTION!\n\nDelete Dr. <?php echo addslashes($doctor['full_name']); ?>?\nAll associated data will be lost!')">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                    <button type="submit" name="delete_doctor" class="icon-btn" style="color: #dc2626;" title="Delete Doctor"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&specialization=<?php echo $specialization_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-user-md-slash" style="font-size: 3rem; color: #cbd5e1;"></i><h3 style="margin-top: 1rem;">No Doctors Found</h3><p>No doctors match your search criteria.</p></div>
            <?php endif; ?>

            <div class="alert alert-info" style="margin-top: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> You can activate/deactivate doctors, view their full details, or remove them from the system. Deactivated doctors cannot be booked by patients.</span>
            </div>
        </div>
    </div>

    <!-- Doctor Details Modal -->
    <div id="doctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><i class="fas fa-user-md"></i> Doctor Details</div>
            <div class="modal-body" id="doctorDetails"></div>
            <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal()">Close</button></div>
        </div>
    </div>

    <script>
        function viewDoctor(doctor) {
            const modal = document.getElementById('doctorModal');
            const detailsDiv = document.getElementById('doctorDetails');
            detailsDiv.innerHTML = `
                <div class="detail-row"><div class="detail-label">Full Name:</div><div class="detail-value"><strong>Dr. ${doctor.full_name}</strong></div></div>
                <div class="detail-row"><div class="detail-label">Username:</div><div class="detail-value">@${doctor.username}</div></div>
                <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">${doctor.email}</div></div>
                <div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">${doctor.phone || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Address:</div><div class="detail-value">${doctor.address || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Specialization:</div><div class="detail-value"><span class="badge badge-specialization">${doctor.specialization}</span></div></div>
                <div class="detail-row"><div class="detail-label">Qualification:</div><div class="detail-value">${doctor.qualification}</div></div>
                <div class="detail-row"><div class="detail-label">Experience:</div><div class="detail-value">${doctor.experience_years} years</div></div>
                <div class="detail-row"><div class="detail-label">Consultation Fee:</div><div class="detail-value"><strong>$${parseFloat(doctor.consultation_fee).toFixed(2)}</strong></div></div>
                <div class="detail-row"><div class="detail-label">Available Days:</div><div class="detail-value">${doctor.available_days || 'Not set'}</div></div>
                <div class="detail-row"><div class="detail-label">Working Hours:</div><div class="detail-value">${doctor.available_time_start || 'N/A'} - ${doctor.available_time_end || 'N/A'}</div></div>
                <div class="detail-row"><div class="detail-label">Member Since:</div><div class="detail-value">${new Date(doctor.created_at).toLocaleDateString()}</div></div>
            `;
            modal.classList.add('active');
        }
        function closeModal() { document.getElementById('doctorModal').classList.remove('active'); }
        window.onclick = function(event) { if (event.target == document.getElementById('doctorModal')) closeModal(); }
    </script>
</body>
</html>