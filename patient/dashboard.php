<?php
// patient/dashboard.php - Completely Redesigned Patient Dashboard
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$user_name = SessionManager::getFullName();

// Get patient info
$patientQuery = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($patientQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get appointment statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM appointments WHERE patient_id = :user_id";
$stmt = $db->prepare($statsQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcomingQuery = "SELECT a.*, 
                         u.full_name as doctor_name, 
                         d.specialization,
                         d.consultation_fee
                  FROM appointments a
                  JOIN doctors d ON a.doctor_id = d.id
                  JOIN users u ON d.user_id = u.id
                  WHERE a.patient_id = :user_id 
                  AND a.appointment_date >= CURDATE()
                  AND a.status IN ('pending', 'confirmed')
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 5";
$stmt = $db->prepare($upcomingQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent medical records
$recentRecordsQuery = "SELECT * FROM medical_records 
                       WHERE patient_id = :user_id 
                       ORDER BY record_date DESC, created_at DESC 
                       LIMIT 5";
$stmt = $db->prepare($recentRecordsQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recommended doctors
$recommendedQuery = "SELECT d.*, u.full_name, u.email, u.phone,
                     COUNT(a.id) as appointment_count
                     FROM doctors d
                     JOIN users u ON d.user_id = u.id
                     LEFT JOIN appointments a ON d.id = a.doctor_id
                     WHERE u.is_active = 1
                     GROUP BY d.id
                     ORDER BY appointment_count DESC
                     LIMIT 4";
$recommendedStmt = $db->query($recommendedQuery);
$recommended_doctors = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Design Variables */
        :root {
            --bg-main: #0A0C15;
            --surface-card: rgba(18, 22, 33, 0.75);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #6366F1;
            --primary-dark: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.2);
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

        /* Animated Background */
        .bg-orb-1 {
            position: fixed;
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
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
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        /* Navbar */
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
            background: rgba(99, 102, 241, 0.1);
        }

        /* Main Layout */
        .dashboard-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        /* Top Bar with Greeting */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .greeting h1 {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .greeting p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .date-badge {
            background: rgba(255, 255, 255, 0.03);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            border: 1px solid var(--border-color);
        }

        /* Metric Cards Row */
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1.25rem;
            transition: all 0.3s;
        }

        .metric-card:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
            transform: translateY(-3px);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .metric-icon {
            width: 44px;
            height: 44px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .metric-icon i {
            font-size: 1.3rem;
            color: var(--primary);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 800;
        }

        .metric-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Timeline Style Appointments */
        .timeline-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .card-head {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-head h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeline-list {
            padding: 0.5rem 0;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.2s;
        }

        .timeline-item:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .timeline-dot {
            width: 10px;
            height: 10px;
            border-radius: 10px;
            background: var(--primary);
            margin-right: 1rem;
            box-shadow: 0 0 8px var(--primary);
        }

        .timeline-dot.pending {
            background: var(--warning);
            box-shadow: 0 0 8px var(--warning);
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .timeline-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .timeline-time {
            font-size: 0.75rem;
            color: var(--primary);
        }

        .cancel-btn {
            background: rgba(239, 68, 68, 0.1);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            color: #F87171;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cancel-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Quick Actions Grid */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .action-btn {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .action-btn:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 1.4rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .action-btn span {
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Records List Compact */
        .records-list {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
        }

        .record-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .record-item:last-child {
            border-bottom: none;
        }

        .record-info h4 {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .record-info p {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .view-record {
            background: transparent;
            border: 1px solid var(--border-color);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            color: var(--text-muted);
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-record:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Doctors Grid */
        .doctors-section {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            margin-top: 1.5rem;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .doctors-horizontal {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .doc-card {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s;
        }

        .doc-card:hover {
            background: rgba(99, 102, 241, 0.08);
            transform: translateY(-3px);
        }

        .doc-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 0.75rem;
        }

        .doc-card h4 {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .doc-specialty {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .doc-fee {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        .book-btn {
            background: rgba(99, 102, 241, 0.15);
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .book-btn:hover {
            background: var(--primary);
            color: white;
        }

        /* Tips Strip */
        .tips-strip {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), rgba(16, 185, 129, 0.05));
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .tips-strip span {
            font-size: 0.8rem;
        }

        .tips-strip i {
            color: var(--warning);
            margin-right: 0.5rem;
        }

        .support-number {
            font-size: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(18, 22, 33, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            padding: 0.875rem 1.25rem;
            border-radius: 20px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 3000;
            animation: slideIn 0.3s ease;
        }

        .toast.show {
            display: flex;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .metrics-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
            .doctors-horizontal {
                grid-template-columns: repeat(2, 1fr);
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
            .metrics-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .doctors-horizontal {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="greeting">
                <h1>Hello, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?> <span style="color: var(--primary);">👋</span></h1>
                <p>Your health dashboard is ready</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i> 
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-calendar-week"></i></div>
                </div>
                <div class="metric-value"><?php echo ($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0); ?></div>
                <div class="metric-label">Upcoming Appointments</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="metric-label">Completed Visits</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="metric-label">Pending</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-chart-simple"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="metric-label">Total Appointments</div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-columns">
            <!-- Left Column: Timeline Appointments -->
            <div class="timeline-card">
                <div class="card-head">
                    <h3><i class="fas fa-clock"></i> Upcoming Schedule</h3>
                    <a href="my-appointments.php" style="color: var(--primary); font-size: 0.75rem; text-decoration: none;">View all →</a>
                </div>
                <div class="timeline-list">
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot <?php echo $appointment['status']; ?>"></div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                    <div class="timeline-sub"><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></div>
                                </div>
                                <div class="timeline-time">
                                    <?php echo date('M d', strtotime($appointment['appointment_date'])); ?> • <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </div>
                                <button onclick="cancelAppointment(<?php echo $appointment['id']; ?>)" class="cancel-btn" style="margin-left: 1rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-day" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>No upcoming appointments</p>
                            <a href="book-appointment.php" class="book-btn" style="margin-top: 0.5rem; display: inline-block;">Book Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Actions + Records -->
            <div>
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="book-appointment.php" class="action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Book Appointment</span>
                    </a>
                    <a href="medical-records.php" class="action-btn">
                        <i class="fas fa-folder-open"></i>
                        <span>View Records</span>
                    </a>
                    <a href="profile.php" class="action-btn">
                        <i class="fas fa-user-edit"></i>
                        <span>Update Profile</span>
                    </a>
                    <a href="#" class="action-btn" onclick="showHelp()">
                        <i class="fas fa-headset"></i>
                        <span>Get Help</span>
                    </a>
                </div>

                <!-- Recent Records -->
                <div class="records-list">
                    <div class="card-head">
                        <h3><i class="fas fa-file-medical"></i> Recent Medical Records</h3>
                        <a href="medical-records.php" style="color: var(--primary); font-size: 0.75rem; text-decoration: none;">See all →</a>
                    </div>
                    <?php if (count($recent_records) > 0): ?>
                        <?php foreach ($recent_records as $record): ?>
                            <div class="record-item">
                                <div class="record-info">
                                    <h4><?php echo htmlspecialchars(substr($record['diagnosis'] ?? 'Medical Record', 0, 35)); ?></h4>
                                    <p><?php echo date('F d, Y', strtotime($record['record_date'])); ?></p>
                                </div>
                                <button onclick="viewRecord(<?php echo $record['id']; ?>)" class="view-record">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-notes-medical" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>No medical records yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

       
        <!-- Tips Strip -->
        <div class="tips-strip">
            <div>
                <i class="fas fa-lightbulb"></i> <span>Quick Tip: Schedule follow-ups 2 weeks in advance for better availability</span>
            </div>
            <div class="support-number">
                <i class="fas fa-phone-alt"></i> Emergency: <strong style="color: var(--primary);">+1 (555) 123-4567</strong>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">
        <i class="fas"></i>
        <span id="toastMsg"></span>
    </div>

    <script>
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toastMsg');
            const icon = toast.querySelector('.fas');
            toastMsg.innerText = msg;
            toast.classList.add('show');
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            setTimeout(() => toast.classList.remove('show'), 3500);
        }

        function cancelAppointment(id) {
            if (confirm('Cancel this appointment? This cannot be undone.')) {
                fetch('../api/cancel-appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Appointment cancelled', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'Failed to cancel', 'error');
                    }
                })
                .catch(() => showToast('Network error', 'error'));
            }
        }

        function viewRecord(id) {
            window.location.href = `view-record.php?id=${id}`;
        }

        function showHelp() {
            showToast('Call support: +1 (555) 123-4567', 'success');
        }
    </script>
</body>
</html>