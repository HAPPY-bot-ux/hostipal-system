<?php
// doctor/dashboard.php - Completely Redesigned Doctor Dashboard
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$doctor_name = SessionManager::getFullName();

// Get doctor info
$doctorQuery = "SELECT d.*, u.email, u.phone FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
    COUNT(CASE WHEN DATE(appointment_date) = CURDATE() AND status IN ('confirmed', 'pending') THEN 1 END) as today
    FROM appointments WHERE doctor_id = :doctor_id";
$stmt = $db->prepare($statsQuery);
$stmt->bindParam(':doctor_id', $doctor['id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's appointments
$todayQuery = "SELECT a.*, u.full_name as patient_name, u.phone, u.email 
               FROM appointments a
               JOIN users u ON a.patient_id = u.id
               WHERE a.doctor_id = :doctor_id AND a.appointment_date = CURDATE()
               ORDER BY a.appointment_time ASC";
$stmt = $db->prepare($todayQuery);
$stmt->bindParam(':doctor_id', $doctor['id']);
$stmt->execute();
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments (next 7 days)
$upcomingQuery = "SELECT a.*, u.full_name as patient_name, u.phone, u.email 
                  FROM appointments a
                  JOIN users u ON a.patient_id = u.id
                  WHERE a.doctor_id = :doctor_id 
                  AND a.appointment_date > CURDATE() 
                  AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  AND a.status IN ('pending', 'confirmed')
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 5";
$stmt = $db->prepare($upcomingQuery);
$stmt->bindParam(':doctor_id', $doctor['id']);
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent patients (last 5 completed appointments)
$recentPatientsQuery = "SELECT DISTINCT u.id, u.full_name, u.phone, u.email, a.appointment_date
                        FROM appointments a
                        JOIN users u ON a.patient_id = u.id
                        WHERE a.doctor_id = :doctor_id AND a.status = 'completed'
                        ORDER BY a.appointment_date DESC
                        LIMIT 5";
$stmt = $db->prepare($recentPatientsQuery);
$stmt->bindParam(':doctor_id', $doctor['id']);
$stmt->execute();
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate consultation count for today's estimated earnings
$today_earnings = $stats['today'] * $doctor['consultation_fee'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | MediFlow HMS</title>
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

        .dashboard-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.12) 0%, rgba(2, 132, 199, 0.05) 100%);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .hero-info h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .hero-info h1 span {
            background: linear-gradient(135deg, #FFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-info p {
            color: var(--text-muted);
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
        }

        .hero-stat {
            text-align: center;
            padding: 0 1rem;
            border-left: 1px solid var(--border-color);
        }

        .hero-stat:first-child {
            border-left: none;
        }

        .hero-stat .number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
        }

        .hero-stat .label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .stat-card i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-number {
            font-size: 1.6rem;
            font-weight: 800;
        }

        .stat-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Glass Cards */
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

        /* Timeline Appointments */
        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            transition: all 0.2s;
        }

        .timeline-item:hover {
            background: rgba(14, 165, 233, 0.08);
        }

        .timeline-time {
            min-width: 80px;
            font-weight: 700;
            color: var(--primary);
            font-size: 0.85rem;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-content .name {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .timeline-content .email {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .status-pending { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .status-confirmed { background: rgba(14, 165, 233, 0.15); color: #7DD3FC; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #34D399; }

        .btn-start {
            background: rgba(14, 165, 233, 0.15);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            color: var(--primary);
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-start:hover {
            background: var(--primary);
            color: white;
        }

        /* Upcoming List */
        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .upcoming-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
        }

        .upcoming-date {
            min-width: 60px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .upcoming-patient {
            flex: 1;
            font-weight: 500;
            font-size: 0.85rem;
        }

        /* Schedule Info */
        .schedule-block {
            background: rgba(14, 165, 233, 0.08);
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .schedule-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            font-size: 0.8rem;
        }

        .schedule-row i {
            width: 24px;
            color: var(--primary);
        }

        /* Recent Patients */
        .patient-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .patient-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            transition: all 0.2s;
        }

        .patient-item:hover {
            background: rgba(14, 165, 233, 0.08);
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
            margin-right: 0.75rem;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .patient-date {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .btn-view {
            background: transparent;
            border: 1px solid var(--border-color);
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            color: var(--text-muted);
            font-size: 0.65rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2rem;
            opacity: 0.5;
            margin-bottom: 0.5rem;
        }

        /* Tips Bar */
        .tips-bar {
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.08), rgba(16, 185, 129, 0.04));
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
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
            .dashboard-wrapper {
                padding: 0 1rem;
            }
            .hero-section {
                flex-direction: column;
                text-align: center;
            }
            .hero-stats {
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .timeline-item {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .timeline-time {
                min-width: 100%;
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
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-info">
                <h1>Welcome back, <span>Dr. <?php echo htmlspecialchars($doctor_name); ?></span></h1>
                <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialization']); ?> Specialist • <?php echo $doctor['experience_years']; ?>+ years experience</p>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="number"><?php echo $stats['today'] ?? 0; ?></div>
                    <div class="label">Today</div>
                </div>
                <div class="hero-stat">
                    <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="label">Total Patients</div>
                </div>
                <div class="hero-stat">
                    <div class="number"><?php echo number_format($today_earnings, 0); ?>R</div>
                    <div class="label">Today's Est.</div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-calendar-day"></i><div class="stat-number"><?php echo $stats['today'] ?? 0; ?></div><div class="stat-label">Today's Patients</div></div>
            <div class="stat-card"><i class="fas fa-hourglass-half"></i><div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div><div class="stat-label">Pending</div></div>
            <div class="stat-card"><i class="fas fa-check-circle"></i><div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div><div class="stat-label">Confirmed</div></div>
            <div class="stat-card"><i class="fas fa-check-double"></i><div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div><div class="stat-label">Completed</div></div>
            <div class="stat-card"><i class="fas fa-ban"></i><div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div><div class="stat-label">Cancelled</div></div>
            <div class="stat-card"><i class="fas fa-chart-simple"></i><div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Total</div></div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-columns">
            <!-- Left Column: Today's Schedule -->
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
                    <span style="font-size: 0.7rem; color: var(--text-muted);"><?php echo date('F j, Y'); ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($today_appointments) > 0): ?>
                        <div class="timeline-list">
                            <?php foreach ($today_appointments as $appointment): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                    <div class="timeline-content">
                                        <div class="name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                        <div class="email"><?php echo htmlspecialchars($appointment['email']); ?></div>
                                    </div>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                    <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn-start">
                                        <i class="fas fa-stethoscope"></i> Start
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today</p>
                            <small style="color: var(--text-muted);">Enjoy your day off! 🎉</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Info Cards -->
            <div>
                <!-- Upcoming Appointments -->
                <div class="glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-week"></i> Upcoming (7 days)</h3>
                        <a href="appointments.php" style="color: var(--primary); font-size: 0.7rem;">View all →</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcoming_appointments) > 0): ?>
                            <div class="upcoming-list">
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="upcoming-item">
                                        <div class="upcoming-date"><?php echo date('M d', strtotime($appointment['appointment_date'])); ?></div>
                                        <div class="upcoming-patient"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>No upcoming appointments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Schedule Info -->
                <div class="glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> My Schedule</h3>
                    </div>
                    <div class="card-body">
                        <div class="schedule-block">
                            <div class="schedule-row">
                                <i class="fas fa-calendar-alt"></i>
                                <span><strong>Days:</strong> <?php echo htmlspecialchars($doctor['available_days'] ?: 'Not set'); ?></span>
                            </div>
                            <div class="schedule-row">
                                <i class="fas fa-hourglass-start"></i>
                                <span><strong>Hours:</strong> <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?></span>
                            </div>
                            <div class="schedule-row">
                                <i class="fas fa-dollar-sign"></i>
                                <span><strong>Fee:</strong> R<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Patients -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-clock"></i> Recent Patients</h3>
                        <a href="patients.php" style="color: var(--primary); font-size: 0.7rem;">View all →</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_patients) > 0): ?>
                            <div class="patient-list">
                                <?php foreach ($recent_patients as $patient): ?>
                                    <div class="patient-item">
                                        <div style="display: flex; align-items: center;">
                                            <div class="patient-avatar"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                            <div class="patient-info">
                                                <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                                <div class="patient-date">Last visit: <?php echo date('M d, Y', strtotime($patient['appointment_date'])); ?></div>
                                            </div>
                                        </div>
                                        <button onclick="viewPatient(<?php echo $patient['id']; ?>)" class="btn-view">
                                            <i class="fas fa-notes-medical"></i> View
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No recent patients</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips Bar -->
        <div class="tips-bar">
            <div><i class="fas fa-lightbulb" style="color: var(--warning);"></i> Click "Start" to begin a consultation and update medical records</div>
            <div><i class="fas fa-calendar-check"></i> Keep your schedule updated for patient bookings</div>
        </div>
    </div>

    <script>
        function startConsultation(id) {
            if (confirm('Start consultation for this patient?')) {
                window.location.href = `update-record.php?appointment_id=${id}`;
            }
        }

        function viewPatient(id) {
            window.location.href = `patient-details.php?id=${id}`;
        }

        // Set doctor theme
        document.documentElement.style.setProperty('--primary', '#0EA5E9');
        document.documentElement.style.setProperty('--primary-dark', '#0284C7');
    </script>
</body>
</html>