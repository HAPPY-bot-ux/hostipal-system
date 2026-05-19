<?php
// admin/dashboard.php - Admin Dashboard with modern medical UI (FIXED SQL)
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session if not already started
SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get system statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->query($query);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total doctors
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$stmt = $db->query($query);
$stats['total_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total patients
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
$stmt = $db->query($query);
$stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total appointments
$query = "SELECT COUNT(*) as total FROM appointments";
$stmt = $db->query($query);
$stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()";
$stmt = $db->query($query);
$stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'";
$stmt = $db->query($query);
$stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Completed appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'";
$stmt = $db->query($query);
$stats['completed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Confirmed appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'confirmed'";
$stmt = $db->query($query);
$stats['confirmed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cancelled appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'cancelled'";
$stmt = $db->query($query);
$stats['cancelled_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue (sum of consultation fees from completed appointments)
$query = "SELECT COALESCE(SUM(d.consultation_fee), 0) as total_revenue 
          FROM appointments a 
          JOIN doctors d ON a.doctor_id = d.id 
          WHERE a.status = 'completed'";
$stmt = $db->query($query);
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

// Recent activities
$query = "SELECT l.*, u.full_name, u.role 
          FROM system_logs l
          JOIN users u ON l.user_id = u.id
          ORDER BY l.created_at DESC
          LIMIT 10";
$stmt = $db->query($query);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly appointment data for chart (last 6 months) - FIXED: Proper GROUP BY
$monthlyQuery = "SELECT 
                    DATE_FORMAT(appointment_date, '%b') as month,
                    DATE_FORMAT(appointment_date, '%Y-%m') as sort_date,
                    COUNT(*) as count
                 FROM appointments 
                 WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                 GROUP BY DATE_FORMAT(appointment_date, '%Y-%m'), DATE_FORMAT(appointment_date, '%b')
                 ORDER BY sort_date ASC";
$monthlyStmt = $db->query($monthlyQuery);
$monthly_data = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
$months = array_column($monthly_data, 'month');
$monthly_counts = array_column($monthly_data, 'count');

// If no data, provide default months
if (empty($months)) {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $monthly_counts = [0, 0, 0, 0, 0, 0];
}

// Get role distribution
$roleQuery = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$roleStmt = $db->query($roleQuery);
$role_data = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard | MediFlow HMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-header p {
            color: #64748b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Chart Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .card-header {
            font-size: 1.1rem;
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

        canvas {
            max-height: 260px;
            width: 100%;
        }

        /* Table Styles */
        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.875rem;
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
            gap: 0.3rem;
        }

        .badge-admin {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-doctor {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-patient {
            background: #d1fae5;
            color: #059669;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 2rem;
            color: #cbd5e1;
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Animations */
        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 900px) {
            .charts-grid {
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
            .glass-card {
                padding: 1rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            .page-header h1 {
                font-size: 1.5rem;
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
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars(SessionManager::getFullName()); ?>! Here's your hospital overview.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-number"><?php echo $stats['total_users']; ?></div><div class="stat-label">Total Users</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-md"></i></div><div class="stat-number"><?php echo $stats['total_doctors']; ?></div><div class="stat-label">Doctors</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-injured"></i></div><div class="stat-number"><?php echo $stats['total_patients']; ?></div><div class="stat-label">Patients</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-number"><?php echo $stats['total_appointments']; ?></div><div class="stat-label">Appointments</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-day"></i></div><div class="stat-number"><?php echo $stats['today_appointments']; ?></div><div class="stat-label">Today</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><div class="stat-number">$<?php echo number_format($stats['total_revenue'], 0); ?></div><div class="stat-label">Revenue</div></div>
            </div>

            <!-- Charts Row -->
            <div class="charts-grid">
                <!-- Monthly Appointments Chart -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Monthly Trends
                    </div>
                    <canvas id="monthlyChart"></canvas>
                </div>

                <!-- User Distribution Pie Chart -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> User Distribution
                    </div>
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>

            <!-- Appointment Status Chart & Recent Activities -->
            <div class="charts-grid">
                <!-- Appointment Status Bar Chart -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> Appointment Status
                    </div>
                    <canvas id="statusChart"></canvas>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Recent Activities
                        <span style="margin-left: auto; font-size: 0.65rem; color: #64748b;">Last 10 entries</span>
                    </div>
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <span class="badge badge-<?php echo $activity['role']; ?>" style="margin-left: 0.5rem;">
                                                <?php echo ucfirst($activity['role']); ?>
                                            </span>
                                         </span>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                             </div>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i><p>No recent activities</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats Footer -->
            <div class="stats-grid" style="margin-top: 0.5rem;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['confirmed_appointments']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-number"><?php echo $stats['completed_appointments']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-number"><?php echo $stats['cancelled_appointments']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Appointments Line Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode($monthly_counts); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'top', labels: { font: { family: 'Inter' } } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Inter' } } }, x: { ticks: { font: { family: 'Inter' } } } }
            }
        });

        // User Distribution Pie Chart
        <?php if (!empty($role_data)): ?>
        const roleLabels = <?php echo json_encode(array_column($role_data, 'role')); ?>;
        const roleCounts = <?php echo json_encode(array_column($role_data, 'count')); ?>;
        const totalUsers = roleCounts.reduce((a, b) => a + b, 0);
        
        const pieCtx = document.getElementById('userDistributionChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: roleLabels.map(r => r.charAt(0).toUpperCase() + r.slice(1)),
                datasets: [{
                    data: roleCounts,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 12 } } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} users (${Math.round(ctx.raw / totalUsers * 100)}%)` } }
                }
            }
        });
        <?php endif; ?>

        // Appointment Status Bar Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
                datasets: [{
                    label: 'Appointments',
                    data: [
                        <?php echo $stats['pending_appointments']; ?>,
                        <?php echo $stats['confirmed_appointments']; ?>,
                        <?php echo $stats['completed_appointments']; ?>,
                        <?php echo $stats['cancelled_appointments']; ?>
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
                    borderRadius: 10,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Inter' } } }, x: { ticks: { font: { family: 'Inter' } } } }
            }
        });
    </script>
</body>
</html>