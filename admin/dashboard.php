<?php
// admin/dashboard.php - Completely Redesigned Admin Dashboard
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('admin');

$database = new Database();
$db = $database->getConnection();

// Get system statistics
$stats = [];

$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->query($query);
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM users WHERE role = 'doctor'";
$stmt = $db->query($query);
$stats['total_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM users WHERE role = 'patient'";
$stmt = $db->query($query);
$stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments";
$stmt = $db->query($query);
$stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()";
$stmt = $db->query($query);
$stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'";
$stmt = $db->query($query);
$stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'";
$stmt = $db->query($query);
$stats['completed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'confirmed'";
$stmt = $db->query($query);
$stats['confirmed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE status = 'cancelled'";
$stmt = $db->query($query);
$stats['cancelled_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COALESCE(SUM(d.consultation_fee), 0) as total_revenue 
          FROM appointments a 
          JOIN doctors d ON a.doctor_id = d.id 
          WHERE a.status = 'completed'";
$stmt = $db->query($query);
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

$query = "SELECT l.*, u.full_name, u.role 
          FROM system_logs l
          JOIN users u ON l.user_id = u.id
          ORDER BY l.created_at DESC
          LIMIT 10";
$stmt = $db->query($query);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

if (empty($months)) {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $monthly_counts = [0, 0, 0, 0, 0, 0];
}

$roleQuery = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$roleStmt = $db->query($roleQuery);
$role_data = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MediFlow HMS</title>
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

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(59, 130, 246, 0.06) 100%);
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

        .hero-date {
            background: rgba(255, 255, 255, 0.05);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
        }

        /* Metrics Row */
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
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
            transform: translateY(-3px);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .metric-icon {
            width: 44px;
            height: 44px;
            background: rgba(139, 92, 246, 0.1);
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
            font-size: 1.8rem;
            font-weight: 800;
        }

        .metric-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            justify-content: space-between;
            align-items: center;
            background: rgba(139, 92, 246, 0.03);
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
            padding: 1.5rem;
        }

        canvas {
            max-height: 260px;
            width: 100%;
        }

        /* Status Cards Row */
        .status-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s;
        }

        .status-card:hover {
            border-color: var(--primary);
        }

        .status-value {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .status-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .status-pending .status-value { color: var(--warning); }
        .status-confirmed .status-value { color: var(--info); }
        .status-completed .status-value { color: var(--accent); }
        .status-cancelled .status-value { color: var(--danger); }

        /* Activities Table */
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            transition: all 0.2s;
        }

        .activity-item:hover {
            background: rgba(139, 92, 246, 0.08);
        }

        .activity-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .activity-avatar {
            width: 36px;
            height: 36px;
            background: rgba(139, 92, 246, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .activity-details h4 {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        .activity-details p {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .activity-time {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .role-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .role-admin { background: rgba(239, 68, 68, 0.15); color: #F87171; }
        .role-doctor { background: rgba(14, 165, 233, 0.15); color: #7DD3FC; }
        .role-patient { background: rgba(16, 185, 129, 0.15); color: #34D399; }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .metrics-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
            .status-row {
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
            .admin-wrapper {
                padding: 0 1rem;
            }
            .hero-section {
                flex-direction: column;
                text-align: center;
            }
            .metrics-row {
                grid-template-columns: 1fr;
            }
            .status-row {
                grid-template-columns: 1fr;
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
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="admin-wrapper">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-info">
                <h1>Welcome back, <span><?php echo htmlspecialchars(SessionManager::getFullName()); ?></span></h1>
                <p>Here's your hospital management overview</p>
            </div>
            <div class="hero-date">
                <i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['total_users']; ?></div>
                <div class="metric-label">Total Users</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-user-md"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['total_doctors']; ?></div>
                <div class="metric-label">Doctors</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-user-injured"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['total_patients']; ?></div>
                <div class="metric-label">Patients</div>
            </div>
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="metric-value"><?php echo $stats['total_appointments']; ?></div>
                <div class="metric-label">Total Appointments</div>
            </div>
        </div>

        <!-- Two Column Charts -->
        <div class="two-columns">
            <!-- Monthly Trends Chart -->
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Trends</h3>
                    <span style="font-size: 0.65rem; color: var(--text-muted);">Last 6 months</span>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- User Distribution Chart -->
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> User Distribution</h3>
                </div>
                <div class="card-body">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Appointment Status Row -->
        <div class="status-row">
            <div class="status-card status-pending">
                <div class="status-value"><?php echo $stats['pending_appointments']; ?></div>
                <div class="status-label"><i class="fas fa-clock"></i> Pending</div>
            </div>
            <div class="status-card status-confirmed">
                <div class="status-value"><?php echo $stats['confirmed_appointments']; ?></div>
                <div class="status-label"><i class="fas fa-check-circle"></i> Confirmed</div>
            </div>
            <div class="status-card status-completed">
                <div class="status-value"><?php echo $stats['completed_appointments']; ?></div>
                <div class="status-label"><i class="fas fa-check-double"></i> Completed</div>
            </div>
            <div class="status-card status-cancelled">
                <div class="status-value"><?php echo $stats['cancelled_appointments']; ?></div>
                <div class="status-label"><i class="fas fa-ban"></i> Cancelled</div>
            </div>
        </div>

        <!-- Recent Activities & Revenue -->
        <div class="two-columns">
            <!-- Recent Activities -->
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <span style="font-size: 0.65rem; color: var(--text-muted);">Last 10 entries</span>
                </div>
                <div class="card-body">
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="activities-list">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <div class="activity-avatar">
                                            <?php echo strtoupper(substr($activity['full_name'], 0, 1)); ?>
                                        </div>
                                        <div class="activity-details">
                                            <h4>
                                                <?php echo htmlspecialchars($activity['full_name']); ?>
                                                <span class="role-badge role-<?php echo $activity['role']; ?>">
                                                    <?php echo ucfirst($activity['role']); ?>
                                                </span>
                                            </h4>
                                            <p><?php echo htmlspecialchars($activity['action']); ?></p>
                                        </div>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today & Revenue Summary -->
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-simple"></i> Quick Summary</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--border-color);">
                        <div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Today's Appointments</div>
                            <div style="font-size: 2rem; font-weight: 800; color: var(--primary);"><?php echo $stats['today_appointments']; ?></div>
                        </div>
                        <i class="fas fa-calendar-day" style="font-size: 2rem; opacity: 0.3;"></i>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0;">
                        <div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Total Revenue</div>
                            <div style="font-size: 2rem; font-weight: 800; color: var(--accent);">R<?php echo number_format($stats['total_revenue'], 0); ?></div>
                        </div>
                        <i class="fas fa-dollar-sign" style="font-size: 2rem; opacity: 0.3;"></i>
                    </div>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                            <span>Completion Rate</span>
                            <span style="color: var(--accent);">
                                <?php echo $stats['total_appointments'] > 0 ? round(($stats['completed_appointments'] / $stats['total_appointments']) * 100) : 0; ?>%
                            </span>
                        </div>
                        <div class="progress-bar" style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 6px; margin-top: 0.5rem;">
                            <div style="width: <?php echo $stats['total_appointments'] > 0 ? round(($stats['completed_appointments'] / $stats['total_appointments']) * 100) : 0; ?>%; background: var(--accent); height: 6px; border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set admin theme
        document.documentElement.style.setProperty('--primary', '#8B5CF6');
        document.documentElement.style.setProperty('--primary-dark', '#7C3AED');

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode($monthly_counts); ?>,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#8B5CF6',
                    pointBorderColor: '#0A0C15',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { labels: { color: '#9CA3AF', font: { family: 'Plus Jakarta Sans' } } } },
                scales: { y: { beginAtZero: true, ticks: { color: '#9CA3AF', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' } }, x: { ticks: { color: '#9CA3AF' }, grid: { display: false } } }
            }
        });

        // User Distribution Chart
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
                    backgroundColor: ['#8B5CF6', '#0EA5E9', '#10B981'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#9CA3AF', font: { family: 'Plus Jakarta Sans', size: 11 } } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} users (${Math.round(ctx.raw / totalUsers * 100)}%)` } }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>