<?php
// patient/my-appointments.php - Patient appointments management with modern UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session if not already started
SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Handle appointment cancellation
if (isset($_POST['cancel_appointment']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    
    // Check if appointment belongs to this patient and is not already completed/cancelled
    $checkQuery = "SELECT id, status FROM appointments WHERE id = :id AND patient_id = :patient_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $appointment_id);
    $checkStmt->bindParam(':patient_id', $user_id);
    $checkStmt->execute();
    $appointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment && in_array($appointment['status'], ['pending', 'confirmed'])) {
        $updateQuery = "UPDATE appointments SET status = 'cancelled' WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $appointment_id);
        
        if ($updateStmt->execute()) {
            $success = "Appointment cancelled successfully!";
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                        VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_action = "Appointment Cancelled";
            $log_details = "Patient cancelled appointment ID: $appointment_id";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            header("Location: my-appointments.php");
            exit();
        } else {
            $error = "Failed to cancel appointment.";
        }
    } else {
        $error = "Cannot cancel this appointment.";
    }
}

// Get filter parameter
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

// Build query for appointments
$query = "SELECT a.*, 
          u.full_name as doctor_name, 
          u.email as doctor_email,
          u.phone as doctor_phone,
          d.specialization,
          d.qualification,
          d.consultation_fee,
          d.experience_years
          FROM appointments a
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          WHERE a.patient_id = :patient_id";
$params = [':patient_id' => $user_id];

if ($status_filter !== 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND a.appointment_date = :date";
    $params[':date'] = $date_filter;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
    COUNT(CASE WHEN appointment_date >= CURDATE() AND status IN ('pending', 'confirmed') THEN 1 END) as upcoming
    FROM appointments WHERE patient_id = :patient_id";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->bindParam(':patient_id', $user_id);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Appointments | MediFlow HMS</title>
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
            text-align: center;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px -8px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-number { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.25rem; }
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
        }
        .filter-select, .filter-date {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.85rem;
            background: #f8fafc;
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
        .btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(37,99,235,0.3); }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; transform: translateY(-2px); }
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
        .appointment-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -12px rgba(0,0,0,0.12); }
        .card-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .doctor-info { display: flex; align-items: center; gap: 0.75rem; }
        .doctor-avatar {
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
            gap: 0.75rem;
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
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 24px;
            color: #94a3b8;
        }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
                <p>View and manage all your scheduled appointments</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div><div class="stat-label">Confirmed</div></div>
                <div class="stat-card"><div class="stat-icon">✔️</div><div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div><div class="stat-label">Cancelled</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-number"><?php echo $stats['upcoming'] ?? 0; ?></div><div class="stat-label">Upcoming</div></div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo $success; ?></span></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo $error; ?></span></div>
            <?php endif; ?>

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
                    <div class="filter-group"><label>Date</label><input type="date" name="date" class="filter-date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()"></div>
                    <div class="filter-group"><button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button></div>
                    <?php if ($status_filter != 'all' || $date_filter): ?>
                        <div class="filter-group"><a href="my-appointments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a></div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Appointments Grid -->
            <?php if (count($appointments) > 0): ?>
                <div class="appointments-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="card-header">
                                <div class="doctor-info">
                                    <div class="doctor-avatar"><?php echo strtoupper(substr($appointment['doctor_name'], 0, 1)); ?></div>
                                    <div><strong>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></strong><br><small><?php echo htmlspecialchars($appointment['specialization']); ?></small></div>
                                </div>
                                <span class="badge badge-<?php echo $appointment['status']; ?>">
                                    <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i>
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="info-row"><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                <div class="info-row"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                <div class="info-row"><i class="fas fa-dollar-sign"></i> Consultation Fee: $<?php echo number_format($appointment['consultation_fee'], 2); ?></div>
                                <div class="info-row"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($appointment['qualification'] ?? 'N/A'); ?></div>
                                <div class="info-row"><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms noted', 0, 60)) . ((strlen($appointment['symptoms'] ?? '') > 60) ? '...' : ''); ?></div>
                            </div>
                            <div class="card-footer">
                                <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-danger"><i class="fas fa-times"></i> Cancel</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($appointment['status'] == 'completed'): ?>
                                    <button class="btn btn-secondary" onclick="viewRecord(<?php echo $appointment['id']; ?>)" disabled><i class="fas fa-file-medical"></i> View Record</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <h3 style="margin-top: 1rem;">No Appointments Found</h3>
                    <p>You don't have any appointments matching your criteria.</p>
                    <a href="book-appointment.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-calendar-plus"></i> Book an Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewRecord(appointmentId) {
            window.location.href = `view-record.php?appointment_id=${appointmentId}`;
        }
    </script>
</body>
</html>