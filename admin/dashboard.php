<?php
// admin/dashboard.php - Admin Dashboard with new design
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

// Recent activities
$query = "SELECT l.*, u.full_name, u.role 
          FROM system_logs l
          JOIN users u ON l.user_id = u.id
          ORDER BY l.created_at DESC
          LIMIT 10";
$stmt = $db->query($query);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hospital System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
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

        /* Card Styles */
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Grid Layout */
        .grid-2cols {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
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
            padding: 0.875rem;
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

        /* Badge Styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.375rem;
        }

        .badge-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-doctor {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-patient {
            background: #d1fae5;
            color: #065f46;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            
            .grid-2cols {
                grid-template-columns: 1fr;
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
                <span>Hospital System - Admin</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars(SessionManager::getFullName()); ?>! Here's what's happening in your hospital today.</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-number"><?php echo $stats['total_doctors']; ?></div>
                    <div class="stat-label">Doctors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user"></i></div>
                    <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                    <div class="stat-label">Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today_appointments']; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            
            <!-- Charts and Recent Activities -->
            <div class="grid-2cols">
                <!-- Appointment Chart -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar" style="color: #2563eb;"></i>
                        Appointment Statistics
                    </div>
                    <canvas id="appointmentChart" style="max-height: 300px; width: 100%;"></canvas>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history" style="color: #2563eb;"></i>
                        Recent Activities
                    </div>
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user"></i> User</th>
                                        <th><i class="fas fa-bolt"></i> Action</th>
                                        <th><i class="fas fa-clock"></i> Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <br>
                                            <span class="badge badge-<?php echo $activity['role']; ?>">
                                                <i class="fas <?php echo $activity['role'] == 'admin' ? 'fa-user-shield' : ($activity['role'] == 'doctor' ? 'fa-user-md' : 'fa-user'); ?>"></i>
                                                <?php echo ucfirst($activity['role']); ?>
                                            </span>
                                         </td>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem; display: block;"></i>
                            No recent activities found.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Stats Footer -->
            <div class="stats-grid" style="margin-top: 1rem;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['completed_appointments'] ?? 0; ?></div>
                    <div class="stat-label">Completed Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo round(($stats['completed_appointments'] / max($stats['total_appointments'], 1)) * 100); ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Chart for appointments
    const ctx = document.getElementById('appointmentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total', 'Today', 'Pending', 'Completed'],
            datasets: [{
                label: 'Appointments',
                data: [
                    <?php echo $stats['total_appointments']; ?>,
                    <?php echo $stats['today_appointments']; ?>,
                    <?php echo $stats['pending_appointments']; ?>,
                    <?php echo $stats['completed_appointments'] ?? 0; ?>
                ],
                backgroundColor: [
                    'rgba(37, 99, 235, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(16, 185, 129, 0.7)'
                ],
                borderColor: [
                    'rgba(37, 99, 235, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(16, 185, 129, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Inter',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            family: 'Inter'
                        }
                    },
                    grid: {
                        borderDash: [5, 5]
                    }
                },
                x: {
                    ticks: {
                        font: {
                            family: 'Inter'
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>