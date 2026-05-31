<?php
// patient/my-appointments.php - Re-imagined Next-Gen Interface for Appointment Management
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and enforce role-based access
SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Handle appointment cancellation
if (isset($_POST['cancel_appointment']) && isset($_POST['appointment_id'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    
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

// Get filter parameters
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Modern System Design Variables — Consistent with Login Gateway */
        :root {
            --bg-main: #090B11;
            --surface-card: rgba(18, 22, 33, 0.65);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #6366F1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --accent: #10B981;
            --gradient-angle: 135deg;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Fluid Background Elements */
        .ambient-glow-1 {
            position: fixed;
            width: 500px;
            height: 500px;
            top: -150px;
            left: -100px;
            background: radial-gradient(circle, var(--primary-glow) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
            transition: background 0.5s ease;
            pointer-events: none;
        }

        .ambient-glow-2 {
            position: fixed;
            width: 600px;
            height: 600px;
            bottom: -200px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* Navbar - Glass Morphic */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(18, 22, 33, 0.85);
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
            letter-spacing: -0.5px;
            background: linear-gradient(120deg, #FFF 40%, var(--text-muted) 100%);
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
            background: rgba(99, 102, 241, 0.1);
        }

        /* Main Container */
        .container {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Glass Card Component */
        .glass-card {
            background: var(--surface-card);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.25rem;
        }

        /* Filters Bar */
        .filters-bar {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1.25rem;
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
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select, .filter-date {
            padding: 0.6rem 1rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-size: 0.85rem;
            color: var(--text-main);
            cursor: pointer;
        }

        .filter-select:focus, .filter-date:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Button Styles */
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #3B82F6 100%);
            color: white;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #FCA5A5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Appointments Grid */
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.25rem;
        }

        .appointment-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .appointment-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .doctor-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, #3B82F6 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.8rem;
        }

        .info-row i {
            width: 24px;
            color: var(--primary);
        }

        .card-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            background: rgba(0, 0, 0, 0.1);
        }

        /* Badge Styles */
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
            background: rgba(245, 158, 11, 0.15);
            color: #FBBF24;
        }

        .badge-confirmed {
            background: rgba(59, 130, 246, 0.15);
            color: #60A5FA;
        }

        .badge-completed {
            background: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }

        .badge-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #F87171;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 24px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            opacity: 0.5;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: paneEntrance 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #A7F3D0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }

        @keyframes paneEntrance {
            from { opacity: 0; transform: translateY(-8px); }
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
                padding: 0 1rem;
            }
            .nav-menu {
                justify-content: center;
            }
            .container {
                padding: 0 1rem;
            }
            .glass-card {
                padding: 1rem;
            }
            .filters {
                flex-direction: column;
            }
            .appointments-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1" id="ambient1"></div>
    <div class="ambient-glow-2"></div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heart-pulse"></i>
                <span>Hospital System</span>
            </a>
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
                <p>View and manage all your scheduled clinical interactions</p>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Total Appointments</div></div>
                <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div><div class="stat-label">Confirmed</div></div>
                <div class="stat-card"><div class="stat-icon">✔️</div><div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div><div class="stat-label">Cancelled</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-number"><?php echo $stats['upcoming'] ?? 0; ?></div><div class="stat-label">Upcoming</div></div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <!-- Filter Controls -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters">
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-day"></i> Date</label>
                        <input type="date" name="date" class="filter-date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Apply</button>
                    </div>
                    <?php if ($status_filter != 'all' || $date_filter): ?>
                        <div class="filter-group">
                            <a href="my-appointments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
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
                                    <div>
                                        <strong>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></strong>
                                        <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $appointment['status']; ?>">
                                    <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : ($appointment['status'] == 'completed' ? 'fa-check-double' : 'fa-ban')); ?>"></i>
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="info-row"><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                <div class="info-row"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                <div class="info-row"><i class="fas fa-dollar-sign"></i> Consultation Fee: $<?php echo number_format($appointment['consultation_fee'], 2); ?></div>
                                <div class="info-row"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($appointment['qualification'] ?? 'N/A'); ?></div>
                                <div class="info-row"><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars(substr($appointment['symptoms'] ?? 'No symptoms noted', 0, 60)) . ((strlen($appointment['symptoms'] ?? '') > 60) ? '...' : ''); ?></div>
                            </div>
                            <div class="card-footer">
                                <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3 style="margin-top: 1rem;">No Appointments Found</h3>
                    <p>You don't have any appointments matching your criteria.</p>
                    <a href="book-appointment.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-calendar-plus"></i> Book an Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Dynamic theme mutation based on patient role (consistent with login page)
        const root = document.documentElement;
        root.style.setProperty('--primary', '#6366F1');
        root.style.setProperty('--primary-glow', 'rgba(99, 102, 241, 0.15)');
        root.style.setProperty('--accent', '#10B981');

        // Ambient glow animation
        const ambient1 = document.getElementById('ambient1');
        let hue = 0;
        setInterval(() => {
            hue = (hue + 1) % 360;
            if (ambient1) {
                ambient1.style.background = `radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(0,0,0,0) 70%)`;
            }
        }, 8000);
    </script>
</body>
</html>