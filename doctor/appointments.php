<?php
// doctor/appointments.php - Manage appointments with enhanced design
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check doctor role
SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor id
$doctorQuery = "SELECT id, specialization FROM doctors WHERE user_id = :user_id";
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
$limit = 15;
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
    <title>Manage Appointments - Doctor Portal</title>
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
            text-align: center;
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

        /* Filters Bar */
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
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
            background: white;
        }

        .status-select:hover {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
            color: #6b7280;
        }

        .close-modal:hover {
            color: #dc2626;
        }

        .detail-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-label {
            font-weight: 600;
            width: 130px;
            color: #374151;
        }

        .detail-value {
            flex: 1;
            color: #6b7280;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1f2937;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 3000;
            animation: slideInRight 0.3s ease;
        }

        .toast.success {
            background: #10b981;
        }

        .toast.error {
            background: #dc2626;
        }

        .toast.show {
            display: flex;
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

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
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
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-hospital"></i>
                <span>Hospital System - Doctor Portal</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-calendar-check"></i> Manage Appointments</h2>
                <p>View, filter, and manage all your patient appointments</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
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
            
            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters" id="filterForm">
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
                        <label><i class="fas fa-calendar"></i> Date</label>
                        <input type="date" name="date" class="filter-date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div class="filter-group" style="flex: 1;">
                        <label><i class="fas fa-search"></i> Search</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="search-input" placeholder="Search by patient name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search || $date_filter || $status_filter != 'all'): ?>
                                <a href="appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Appointments Table -->
            <?php if (count($appointments) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Patient</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-tag"></i> Status</th>
                                <th><i class="fas fa-notes-medical"></i> Symptoms</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                    <br>
                                    <small style="color: #6b7280;">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['phone']); ?>
                                    </small>
                                    <br>
                                    <small style="color: #6b7280;">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?>
                                    </small>
                                 </span>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                 </span>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                <td>
                                    <select class="status-select" onchange="updateStatus(<?php echo $appointment['id']; ?>, this.value)">
                                        <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                        <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                        <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>✔️ Completed</option>
                                        <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                    </select>
                                 </span>
                                <td>
                                    <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? '', 0, 50)) . (strlen($appointment['symptoms'] ?? '') > 50 ? '...' : ''); ?>
                                    </div>
                                 </span>
                                <td class="action-buttons">
                                    <button onclick="viewDetails(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                        <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-success btn-sm" title="Start Consultation">
                                            <i class="fas fa-stethoscope"></i> Consult
                                        </button>
                                    <?php endif; ?>
                                 </span>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No appointments found matching your criteria.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-calendar-check"></i> Appointment Details</span>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="appointmentDetails">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <i class="fas"></i>
        <span id="toastMessage"></span>
    </div>
    
    <script>
        // Update appointment status via AJAX
        function updateStatus(appointmentId, status) {
            fetch('../api/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({id: appointmentId, status: status})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed to update status', 'error');
                    // Reset select to previous value
                    location.reload();
                }
            })
            .catch(error => {
                showToast('Error updating status', 'error');
                location.reload();
            });
        }
        
        // View appointment details in modal
        function viewDetails(id) {
            // Fetch appointment details via AJAX
            fetch(`../api/get-appointment.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('appointmentModal');
                        const detailsDiv = document.getElementById('appointmentDetails');
                        
                        detailsDiv.innerHTML = `
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-user"></i> Patient Name:</div>
                                <div class="detail-value"><strong>${data.patient_name}</strong></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-phone"></i> Phone:</div>
                                <div class="detail-value">${data.phone || 'N/A'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-envelope"></i> Email:</div>
                                <div class="detail-value">${data.email || 'N/A'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Address:</div>
                                <div class="detail-value">${data.address || 'N/A'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-calendar"></i> Date:</div>
                                <div class="detail-value">${data.appointment_date}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-clock"></i> Time:</div>
                                <div class="detail-value">${data.appointment_time}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-tag"></i> Status:</div>
                                <div class="detail-value">
                                    <span class="badge badge-${data.status}">${data.status}</span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-notes-medical"></i> Symptoms:</div>
                                <div class="detail-value">${data.symptoms || 'No symptoms noted'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-comment"></i> Notes:</div>
                                <div class="detail-value">${data.notes || 'No additional notes'}</div>
                            </div>
                        `;
                        
                        modal.classList.add('active');
                    } else {
                        showToast('Failed to load appointment details', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error loading details', 'error');
                });
        }
        
        // Start consultation
        function startConsultation(id) {
            if (confirm('Start consultation for this patient?')) {
                window.location.href = `update-record.php?appointment_id=${id}`;
            }
        }
        
        // Close modal
        function closeModal() {
            const modal = document.getElementById('appointmentModal');
            modal.classList.remove('active');
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('.fas');
            
            toastMessage.textContent = message;
            toast.classList.add('show');
            toast.classList.add(type);
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
            } else {
                icon.className = 'fas fa-exclamation-triangle';
            }
            
            setTimeout(() => {
                toast.classList.remove('show');
                toast.classList.remove(type);
            }, 3000);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('appointmentModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>