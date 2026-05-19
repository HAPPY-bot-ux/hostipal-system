<?php
// doctor/dashboard.php - Doctor Dashboard with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session if not already started
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Doctor Dashboard | MediFlow HMS</title>
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .welcome-banner::before {
            content: "👨‍⚕️";
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 120px;
            opacity: 0.08;
        }

        .welcome-banner h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-banner p {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .doctor-badge {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
            flex-wrap: wrap;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 24px;
            transition: all 0.3s;
            border: 1px solid rgba(37, 99, 235, 0.08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #2563eb;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .card-header i {
            color: #2563eb;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.875rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table th {
            font-weight: 600;
            color: #64748b;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            gap: 0.375rem;
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

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 12px;
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
            border: 1px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.7rem;
        }

        /* Schedule Info */
        .schedule-info {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            border-radius: 20px;
            padding: 1.25rem;
            margin-top: 1rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #475569;
        }

        .schedule-item i {
            color: #2563eb;
            width: 20px;
        }

        /* Patient List */
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
            background: #f8fafc;
            border-radius: 16px;
            transition: all 0.2s;
        }

        .patient-item:hover {
            background: #eff6ff;
            transform: translateX(4px);
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .patient-avatar {
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
            display: block;
        }

        /* Tips Section */
        .tips-section {
            background: linear-gradient(135deg, #f0fdf4, #f0fdf4);
            border-radius: 20px;
            padding: 1.25rem;
            margin-top: 1rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .tips-section h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #166534;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .tip-item {
            font-size: 0.8rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Animations */
        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 968px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .welcome-banner h2 {
                font-size: 1.25rem;
            }
            .schedule-info {
                flex-direction: column;
                align-items: flex-start;
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
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="fade-in">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2><i class="fas fa-waveform"></i> Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?></h2>
                <p><?php echo htmlspecialchars($doctor['specialization']); ?> Specialist with <?php echo $doctor['experience_years']; ?>+ years of experience</p>
                <div class="doctor-badge">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today'] ?? 0; ?></div>
                    <div class="stat-label">Today's Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-day"></i> Today's Schedule
                        <span style="margin-left: auto; font-size: 0.7rem; color: #64748b;">
                            <i class="fas fa-clock"></i> <?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                    <?php if (count($today_appointments) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Time</th><th>Patient</th><th>Status</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_appointments as $appointment): ?>
                                    <tr>
                                        <td><strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                            <br><small style="color:#64748b;"><?php echo htmlspecialchars($appointment['email']); ?></small>
                                        </td>
                                        <td><span class="badge badge-<?php echo $appointment['status']; ?>"><i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : 'fa-check'; ?>"></i> <?php echo ucfirst($appointment['status']); ?></span></td>
                                        <td><button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm"><i class="fas fa-stethoscope"></i> Start</button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No appointments scheduled for today.</p>
                            <small>Enjoy your day! 🎉</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Appointments -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-week"></i> Upcoming (Next 7 Days)
                    </div>
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Date</th><th>Time</th><th>Patient</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong></td>
                                        <td><span class="badge badge-<?php echo $appointment['status']; ?>"><?php echo ucfirst($appointment['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Patients & Schedule Info Row -->
            <div class="dashboard-grid">
                <!-- Recent Patients -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-clock"></i> Recent Patients
                        <a href="patients.php" class="btn btn-outline btn-sm" style="margin-left: auto;">View all <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (count($recent_patients) > 0): ?>
                        <div class="patient-list">
                            <?php foreach ($recent_patients as $patient): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-avatar"><?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                        <br><small style="color:#64748b;">Last visit: <?php echo date('M d, Y', strtotime($patient['appointment_date'])); ?></small>
                                    </div>
                                </div>
                                <a href="patient-details.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-notes-medical"></i> View</a>
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

                <!-- Schedule Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i> My Schedule
                    </div>
                    <div class="schedule-info">
                        <div class="schedule-item"><i class="fas fa-calendar-alt"></i> <strong>Days:</strong> <?php echo htmlspecialchars($doctor['available_days']); ?></div>
                        <div class="schedule-item"><i class="fas fa-hourglass-start"></i> <strong>Hours:</strong> <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?></div>
                        <div class="schedule-item"><i class="fas fa-dollar-sign"></i> <strong>Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?></div>
                    </div>
                    <div class="tips-section" style="margin-top: 1rem;">
                        <h4><i class="fas fa-lightbulb"></i> Quick Tips</h4>
                        <div class="tips-grid">
                            <div class="tip-item"><i class="fas fa-play-circle" style="color:#10b981;"></i> Click "Start" to begin consultation</div>
                            <div class="tip-item"><i class="fas fa-notes-medical" style="color:#3b82f6;"></i> Update medical records after visit</div>
                            <div class="tip-item"><i class="fas fa-calendar-check" style="color:#8b5cf6;"></i> Check schedule daily for updates</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function startConsultation(id) {
            if (confirm('Start consultation for this patient?')) {
                window.location.href = `update-record.php?appointment_id=${id}`;
            }
        }
    </script>
</body>
</html>