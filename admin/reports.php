<?php
// admin/reports.php - Complete reports and analytics dashboard (FIXED)
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check admin role
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get report type from URL
$report_type = $_GET['type'] ?? 'overview';
$date_range = $_GET['date_range'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Set date range based on selection
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
    case 'custom':
        // Use provided dates
        break;
}

// Validate custom dates
if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Default to this month if no dates
if (!$start_date || !$end_date) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Format dates for display
$date_range_display = date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));

// ============ APPOINTMENT STATISTICS ============
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

// ============ DOCTOR PERFORMANCE ============
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

// ============ PATIENT STATISTICS ============
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

// ============ REVENUE STATISTICS ============
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

// ============ MONTHLY TRENDS (Last 12 months) ============
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

// ============ TOP SPECIALIZATIONS ============
$specQuery = "SELECT 
    d.specialization,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    COUNT(DISTINCT d.id) as doctor_count
    FROM doctors d
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN :start_date AND :end_date
    GROUP BY d.specialization
    ORDER BY total_appointments DESC";
$specStmt = $db->prepare($specQuery);
$specStmt->bindParam(':start_date', $start_date);
$specStmt->bindParam(':end_date', $end_date);
$specStmt->execute();
$specialization_stats = $specStmt->fetchAll(PDO::FETCH_ASSOC);

// ============ USER GROWTH ============
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

// Calculate completion rate
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Reports & Analytics | MediFlow HMS - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .date-range-bar {
            background: white;
            border-radius: 24px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
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
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            background: #f1f5f9;
            color: #475569;
            transition: all 0.2s;
        }
        .date-btn:hover, .date-btn.active { background: #2563eb; color: white; }
        .custom-date-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .custom-date-form input {
            padding: 0.5rem 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 20px;
            border: 1px solid rgba(37, 99, 235, 0.08);
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px -8px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-number { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.25rem; }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 1.25rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
        }
        .card-header {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }
        canvas { max-height: 260px; width: 100%; }
        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-table th {
            font-weight: 600;
            color: #64748b;
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        .data-table tr:hover td { background: #f8fafc; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .progress-bar {
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            height: 6px;
        }
        .progress-fill { height: 6px; border-radius: 10px; }
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }
        @media (max-width: 768px) {
            .navbar-container { flex-direction: column; gap: 1rem; padding: 0 1rem; }
            .nav-menu { flex-wrap: wrap; justify-content: center; }
            .container { padding: 0 1rem; }
            .glass-card { padding: 1rem; }
            .charts-grid { grid-template-columns: 1fr; }
            .date-range-bar { flex-direction: column; align-items: stretch; }
        }
        .fade-in { animation: fadeInUp 0.5s ease-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h1><i class="fas fa-chart-pie"></i> Reports & Analytics</h1>
                <p>Comprehensive insights into your hospital's performance</p>
            </div>

            <!-- Date Range Filter -->
            <div class="date-range-bar">
                <div class="date-buttons">
                    <a href="?date_range=today" class="date-btn <?php echo $date_range == 'today' ? 'active' : ''; ?>">Today</a>
                    <a href="?date_range=this_week" class="date-btn <?php echo $date_range == 'this_week' ? 'active' : ''; ?>">This Week</a>
                    <a href="?date_range=this_month" class="date-btn <?php echo $date_range == 'this_month' ? 'active' : ''; ?>">This Month</a>
                    <a href="?date_range=last_month" class="date-btn <?php echo $date_range == 'last_month' ? 'active' : ''; ?>">Last Month</a>
                    <a href="?date_range=this_year" class="date-btn <?php echo $date_range == 'this_year' ? 'active' : ''; ?>">This Year</a>
                </div>
                <form method="GET" action="" class="custom-date-form">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" placeholder="Start Date">
                    <span>to</span>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" placeholder="End Date">
                    <input type="hidden" name="date_range" value="custom">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Apply</button>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-number"><?php echo $appointment_stats['period_total']; ?></div><div class="stat-label">Total Appointments</div></div>
                <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $appointment_stats['period_completed']; ?></div><div class="stat-label">Completed</div></div>
                <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-number"><?php echo $completion_rate; ?>%</div><div class="stat-label">Completion Rate</div></div>
                <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-number">$<?php echo number_format($revenue_stats['period_revenue'] ?? 0, 0); ?></div><div class="stat-label">Revenue</div></div>
                <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-number"><?php echo $patient_stats['period_active_patients'] ?? 0; ?></div><div class="stat-label">Active Patients</div></div>
                <div class="stat-card"><div class="stat-icon">🆕</div><div class="stat-number"><?php echo $patient_stats['new_patients'] ?? 0; ?></div><div class="stat-label">New Patients</div></div>
            </div>

            <!-- Charts Row 1 -->
            <div class="charts-grid">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-line"></i> Monthly Appointment Trends</div>
                    <canvas id="trendsChart"></canvas>
                </div>
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-pie"></i> Appointment Status Distribution</div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="charts-grid">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-bar"></i> Top Specializations</div>
                    <canvas id="specializationChart"></canvas>
                </div>
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-line"></i> User Growth (Last 12 Months)</div>
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <!-- Doctor Performance Table -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <i class="fas fa-trophy"></i> Top Performing Doctors
                    <span style="margin-left: auto; font-size: 0.7rem; color: #64748b;">Based on appointment completion</span>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr><th>Doctor</th><th>Specialization</th><th>Appointments</th><th>Completed</th><th>Rate</th><th>Unique Patients</th><th>Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctor_performance as $doctor): ?>
                            <tr>
                                <td><strong>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                <td><?php echo $doctor['total_appointments']; ?></td>
                                <td><?php echo $doctor['completed_appointments']; ?></td>
                                <td>
                                    <div class="progress-bar" style="width: 80px;">
                                        <div class="progress-fill" style="width: <?php echo $doctor['completion_rate']; ?>%; background: #10b981;"></div>
                                    </div>
                                    <small><?php echo $doctor['completion_rate']; ?>%</small>
                                 </span>
                                <td><?php echo $doctor['unique_patients']; ?></td>
                                <td>$<?php echo number_format($doctor['completed_appointments'] * $doctor['consultation_fee'], 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-buttons">
                <button class="btn btn-secondary" onclick="exportReport('csv')"><i class="fas fa-file-csv"></i> Export CSV</button>
                <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
            </div>
        </div>
    </div>

    <script>
        // Monthly Trends Chart
        const trendsData = <?php echo json_encode($monthly_trends); ?>;
        if (trendsData.length > 0) {
            new Chart(document.getElementById('trendsChart'), {
                type: 'line',
                data: {
                    labels: trendsData.map(d => d.month_name),
                    datasets: [
                        { label: 'Total Appointments', data: trendsData.map(d => d.total_appointments), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.05)', borderWidth: 2, fill: true, tension: 0.3 },
                        { label: 'Completed', data: trendsData.map(d => d.completed_appointments), borderColor: '#10b981', backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 },
                        { label: 'Cancelled', data: trendsData.map(d => d.cancelled_appointments), borderColor: '#ef4444', backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' } } }
            });
        }

        // Status Distribution Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Confirmed', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $appointment_stats['period_completed']; ?>, <?php echo $appointment_stats['confirmed']; ?>, <?php echo $appointment_stats['pending']; ?>, <?php echo $appointment_stats['period_cancelled']; ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Specialization Chart
        const specData = <?php echo json_encode($specialization_stats); ?>;
        if (specData.length > 0) {
            new Chart(document.getElementById('specializationChart'), {
                type: 'bar',
                data: {
                    labels: specData.map(d => d.specialization),
                    datasets: [{ label: 'Appointments', data: specData.map(d => d.total_appointments), backgroundColor: '#3b82f6', borderRadius: 8 }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
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
                        { label: 'New Patients', data: growthData.map(d => d.new_patients), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.05)', fill: true, tension: 0.3 },
                        { label: 'New Doctors', data: growthData.map(d => d.new_doctors), borderColor: '#3b82f6', tension: 0.3 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        }

        function exportReport(format) {
            alert('Export functionality - Would generate ' + format.toUpperCase() + ' file with complete report data for the selected period.');
        }
    </script>
</body>
</html>