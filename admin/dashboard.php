<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php'; // Changed from session.php to SessionManager.php

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
    <link rel="stylesheet" href="../assets/css/style.css">
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

        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-link {
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .glass-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--gray-600);
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .data-table th {
            background: var(--gray-50);
            font-weight: 600;
        }

        .grid-2cols {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        :root {
            --primary-color: #2563eb;
            --secondary-color: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .glass-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .grid-2cols {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">🏥 Hospital System - Admin</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="manage-users.php" class="nav-link">Manage Users</a></li>
                <li><a href="manage-appointments.php" class="nav-link">Appointments</a></li>
                <li><a href="manage-doctors.php" class="nav-link">Doctors</a></li>
                <li><a href="system-settings.php" class="nav-link">Settings</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="glass-container fade-in">
        <h2>Admin Dashboard</h2>
        <p style="color: var(--gray-600); margin-top: 0.5rem;">Welcome, <?php echo htmlspecialchars(SessionManager::getFullName()); ?>!</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_doctors']; ?></div>
                <div class="stat-label">Doctors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                <div class="stat-label">Patients</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today_appointments']; ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <div class="grid-2cols">
            <div class="card">
                <div class="card-header">📊 Appointment Statistics</div>
                <canvas id="appointmentChart" style="max-height: 300px;"></canvas>
            </div>
            
            <div class="card">
                <div class="card-header">🔄 Recent Activities</div>
                <?php if (count($recent_activities) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['full_name']); ?> <small>(<?php echo $activity['role']; ?>)</small></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></span>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray-500);">No recent activities found.</p>
                <?php endif; ?>
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
                    'rgba(37, 99, 235, 0.5)',
                    'rgba(59, 130, 246, 0.5)',
                    'rgba(245, 158, 11, 0.5)',
                    'rgba(16, 185, 129, 0.5)'
                ],
                borderColor: [
                    'rgba(37, 99, 235, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(16, 185, 129, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>