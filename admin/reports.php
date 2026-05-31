<?php
// admin/reports.php - Completely Redesigned Reports & Analytics Dashboard
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();

$report_type = $_GET['type'] ?? 'overview';
$date_range = $_GET['date_range'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

switch ($date_range) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
}

if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

if (!$start_date || !$end_date) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$date_range_display = date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));

// Appointment Statistics
$apptQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN appointment_date BETWEEN :start_date AND :end_date THEN 1 ELSE 0 END) as period_total,
    SUM(CASE WHEN appointment_date BETWEEN :start_date AND :end_date AND status = 'completed' THEN 1 ELSE 0 END) as period_completed,
    SUM(CASE WHEN appointment_date BETWEEN :start_date AND :end_date AND status = 'cancelled' THEN 1 ELSE 0 END) as period_cancelled
    FROM appointments
    WHERE appointment_date BETWEEN :start_date AND :end_date";
$apptStmt = $db->prepare($apptQuery);
$apptStmt->bindParam(':start_date', $start_date);
$apptStmt->bindParam(':end_date', $end_date);
$apptStmt->execute();
$appointment_stats = $apptStmt->fetch(PDO::FETCH_ASSOC);

// Doctor Performance
$doctorQuery = "SELECT 
    d.id,
    u.full_name as doctor_name,
    d.specialization,
    d.consultation_fee,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
    ROUND(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(a.id), 0), 1) as completion_rate,
    COUNT(DISTINCT a.patient_id) as unique_patients
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start_date AND :end_date
    WHERE u.is_active = 1
    GROUP BY d.id, u.full_name, d.specialization, d.consultation_fee
    ORDER BY total_appointments DESC
    LIMIT 10";
$doctorStmt = $db->prepare($doctorQuery);
$doctorStmt->bindParam(':start_date', $start_date);
$doctorStmt->bindParam(':end_date', $end_date);
$doctorStmt->execute();
$doctor_performance = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

// Patient Statistics
$patientQuery = "SELECT 
    COUNT(DISTINCT u.id) as total_patients,
    SUM(CASE WHEN u.created_at BETWEEN :start_date AND :end_date THEN 1 ELSE 0 END) as new_patients,
    COUNT(DISTINCT a.patient_id) as active_patients,
    COUNT(DISTINCT CASE WHEN a.appointment_date BETWEEN :start_date AND :end_date THEN a.patient_id END) as period_active_patients
    FROM users u
    LEFT JOIN appointments a ON u.id = a.patient_id
    WHERE u.role = 'patient'";
$patientStmt = $db->prepare($patientQuery);
$patientStmt->bindParam(':start_date', $start_date);
$patientStmt->bindParam(':end_date', $end_date);
$patientStmt->execute();
$patient_stats = $patientStmt->fetch(PDO::FETCH_ASSOC);

// Revenue Statistics
$revenueQuery = "SELECT 
    SUM(d.consultation_fee) as total_revenue,
    SUM(CASE WHEN a.appointment_date BETWEEN :start_date AND :end_date THEN d.consultation_fee ELSE 0 END) as period_revenue,
    AVG(d.consultation_fee) as avg_consultation_fee,
    SUM(CASE WHEN a.status = 'completed' AND a.appointment_date BETWEEN :start_date AND :end_date THEN d.consultation_fee ELSE 0 END) as collected_revenue
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.status = 'completed'";
$revenueStmt = $db->prepare($revenueQuery);
$revenueStmt->bindParam(':start_date', $start_date);
$revenueStmt->bindParam(':end_date', $end_date);
$revenueStmt->execute();
$revenue_stats = $revenueStmt->fetch(PDO::FETCH_ASSOC);

// Monthly Trends
$trendsQuery = "SELECT 
    DATE_FORMAT(appointment_date, '%Y-%m') as month,
    DATE_FORMAT(appointment_date, '%b %Y') as month_name,
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m'), DATE_FORMAT(appointment_date, '%b %Y')
    ORDER BY month ASC";
$trendsStmt = $db->query($trendsQuery);
$monthly_trends = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);

// Top Specializations
$specQuery = "SELECT 
    d.specialization,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    COUNT(DISTINCT d.id) as doctor_count
    FROM doctors d
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start_date AND :end_date
    GROUP BY d.specialization
    ORDER BY total_appointments DESC
    LIMIT 6";
$specStmt = $db->prepare($specQuery);
$specStmt->bindParam(':start_date', $start_date);
$specStmt->bindParam(':end_date', $end_date);
$specStmt->execute();
$specialization_stats = $specStmt->fetchAll(PDO::FETCH_ASSOC);

// User Growth
$growthQuery = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as new_users,
    SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as new_doctors,
    SUM(CASE WHEN role = 'patient' THEN 1 ELSE 0 END) as new_patients
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$growthStmt = $db->query($growthQuery);
$user_growth = $growthStmt->fetchAll(PDO::FETCH_ASSOC);

$completion_rate = $appointment_stats['period_total'] > 0 
    ? round(($appointment_stats['period_completed'] / $appointment_stats['period_total']) * 100, 1) 
    : 0;
$cancellation_rate = $appointment_stats['period_total'] > 0 
    ? round(($appointment_stats['period_cancelled'] / $appointment_stats['period_total']) * 100, 1) 
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | MediFlow HMS - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Date Range Bar */
        .date-bar {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .date-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .date-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .date-btn:hover, .date-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .custom-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .custom-form input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.75rem;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }

        .metric-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .metric-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.6rem;
            font-weight: 800;
        }

        .metric-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

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
            align-items: center;
            gap: 0.5rem;
            background: rgba(139, 92, 246, 0.03);
            font-weight: 700;
        }

        .card-header i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        canvas {
            max-height: 260px;
            width: 100%;
        }

        /* Doctor Table */
        .doctors-table-container {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .doctors-table {
            width: 100%;
            border-collapse: collapse;
        }

        .doctors-table th {
            text-align: left;
            padding: 1rem 1rem;
            background: rgba(139, 92, 246, 0.05);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .doctors-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .doctors-table tr:hover td {
            background: rgba(139, 92, 246, 0.05);
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            height: 6px;
            width: 80px;
            overflow: hidden;
        }

        .progress-fill {
            height: 6px;
            border-radius: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 40px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34D399; }

        /* Export Buttons */
        .export-bar {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
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
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        .btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
            .charts-grid {
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
            .admin-wrapper {
                padding: 0 1rem;
            }
            .date-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .custom-form {
                justify-content: center;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .doctors-table {
                display: block;
                overflow-x: auto;
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
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="admin-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-chart-pie"></i> Reports & Analytics</h1>
            <p>Comprehensive insights into your hospital's performance</p>
        </div>

        <!-- Date Range Bar -->
        <div class="date-bar">
            <div class="date-buttons">
                <a href="?date_range=today" class="date-btn <?php echo $date_range == 'today' ? 'active' : ''; ?>">Today</a>
                <a href="?date_range=this_week" class="date-btn <?php echo $date_range == 'this_week' ? 'active' : ''; ?>">This Week</a>
                <a href="?date_range=this_month" class="date-btn <?php echo $date_range == 'this_month' ? 'active' : ''; ?>">This Month</a>
                <a href="?date_range=last_month" class="date-btn <?php echo $date_range == 'last_month' ? 'active' : ''; ?>">Last Month</a>
                <a href="?date_range=this_year" class="date-btn <?php echo $date_range == 'this_year' ? 'active' : ''; ?>">This Year</a>
            </div>
            <form method="GET" action="" class="custom-form">
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <span style="color: var(--text-muted);">to</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <input type="hidden" name="date_range" value="custom">
                <button type="submit" class="btn" style="background: var(--primary); color: white;"><i class="fas fa-calendar-alt"></i> Apply</button>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="stats-row">
            <div class="metric-card">
                <div class="metric-icon">📅</div>
                <div class="metric-value"><?php echo $appointment_stats['period_total']; ?></div>
                <div class="metric-label">Total Appointments</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">✅</div>
                <div class="metric-value"><?php echo $appointment_stats['period_completed']; ?></div>
                <div class="metric-label">Completed</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">📊</div>
                <div class="metric-value"><?php echo $completion_rate; ?>%</div>
                <div class="metric-label">Completion Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">💰</div>
                <div class="metric-value">R<?php echo number_format($revenue_stats['period_revenue'] ?? 0, 0); ?></div>
                <div class="metric-label">Revenue</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-value"><?php echo $patient_stats['period_active_patients'] ?? 0; ?></div>
                <div class="metric-label">Active Patients</div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🆕</div>
                <div class="metric-value"><?php echo $patient_stats['new_patients'] ?? 0; ?></div>
                <div class="metric-label">New Patients</div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="charts-grid">
            <div class="glass-card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Monthly Appointment Trends
                </div>
                <div class="card-body">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
            <div class="glass-card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Appointment Status
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="charts-grid">
            <div class="glass-card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i> Top Specializations
                </div>
                <div class="card-body">
                    <canvas id="specializationChart"></canvas>
                </div>
            </div>
            <div class="glass-card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> User Growth
                </div>
                <div class="card-body">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Doctor Performance Table -->
        <div class="doctors-table-container">
            <div class="card-header">
                <i class="fas fa-trophy"></i> Top Performing Doctors
                <span style="margin-left: auto; font-size: 0.65rem; color: var(--text-muted);">Based on appointment completion</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="doctors-table">
                    <thead>
                        <tr><th>Doctor</th><th>Specialization</th><th>Appointments</th><th>Completed</th><th>Rate</th><th>Patients</th><th>Revenue</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctor_performance as $doctor): ?>
                            <tr>
                                <td><strong>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                <td><?php echo $doctor['total_appointments']; ?></td>
                                <td><?php echo $doctor['completed_appointments']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $doctor['completion_rate']; ?>%; background: var(--accent);"></div></div>
                                        <span style="font-size: 0.7rem;"><?php echo $doctor['completion_rate']; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo $doctor['unique_patients']; ?></td>
                                <td>R<?php echo number_format($doctor['completed_appointments'] * $doctor['consultation_fee'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export Section -->
        <div class="export-bar">
            <button class="btn" onclick="exportReport('csv')"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
        </div>
    </div>

    <script>
        // Set admin theme
        document.documentElement.style.setProperty('--primary', '#8B5CF6');

        // Monthly Trends Chart
        const trendsData = <?php echo json_encode($monthly_trends); ?>;
        if (trendsData.length > 0) {
            new Chart(document.getElementById('trendsChart'), {
                type: 'line',
                data: {
                    labels: trendsData.map(d => d.month_name),
                    datasets: [
                        { label: 'Total', data: trendsData.map(d => d.total_appointments), borderColor: '#8B5CF6', backgroundColor: 'rgba(139,92,246,0.05)', borderWidth: 2, fill: true, tension: 0.3 },
                        { label: 'Completed', data: trendsData.map(d => d.completed_appointments), borderColor: '#10B981', borderWidth: 2, tension: 0.3 },
                        { label: 'Cancelled', data: trendsData.map(d => d.cancelled_appointments), borderColor: '#EF4444', borderWidth: 2, tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#9CA3AF' } } }, scales: { y: { ticks: { color: '#9CA3AF' }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#9CA3AF' }, grid: { display: false } } } }
            });
        }

        // Status Distribution Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $appointment_stats['period_completed']; ?>, <?php echo $appointment_stats['confirmed']; ?>, <?php echo $appointment_stats['pending']; ?>, <?php echo $appointment_stats['period_cancelled']; ?>],
                    backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { color: '#9CA3AF' } } } }
        });

        // Specialization Chart
        const specData = <?php echo json_encode($specialization_stats); ?>;
        if (specData.length > 0) {
            new Chart(document.getElementById('specializationChart'), {
                type: 'bar',
                data: {
                    labels: specData.map(d => d.specialization.length > 15 ? d.specialization.substring(0, 12) + '...' : d.specialization),
                    datasets: [{ label: 'Appointments', data: specData.map(d => d.total_appointments), backgroundColor: '#8B5CF6', borderRadius: 8 }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: '#9CA3AF' }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#9CA3AF' }, grid: { display: false } } } }
            });
        }

        // User Growth Chart
        const growthData = <?php echo json_encode($user_growth); ?>;
        if (growthData.length > 0) {
            new Chart(document.getElementById('growthChart'), {
                type: 'line',
                data: {
                    labels: growthData.map(d => d.month),
                    datasets: [
                        { label: 'New Patients', data: growthData.map(d => d.new_patients), borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.05)', fill: true, tension: 0.3 },
                        { label: 'New Doctors', data: growthData.map(d => d.new_doctors), borderColor: '#8B5CF6', tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { labels: { color: '#9CA3AF' } } }, scales: { y: { beginAtZero: true, ticks: { color: '#9CA3AF' }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#9CA3AF' }, grid: { display: false } } } }
            });
        }

        function exportReport(format) {
            alert('Export functionality - Would generate ' + format.toUpperCase() + ' file with complete report data for the selected period.');
        }
    </script>
</body>
</html>