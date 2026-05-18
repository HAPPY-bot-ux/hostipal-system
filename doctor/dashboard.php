<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php'; // Changed from session.php to SessionManager.php

// Start session if not already started
SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor info
$doctorQuery = "SELECT d.* FROM doctors d WHERE d.user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Get statistics
$statsQuery = "SELECT 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--gray-600);
            margin-top: 0.5rem;
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

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
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

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .schedule-info {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">🏥 Hospital System</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="schedule.php" class="nav-link">My Schedule</a></li>
                <li><a href="appointments.php" class="nav-link">Appointments</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="glass-container fade-in">
        <div class="welcome-banner">
            <h2>Welcome, Dr. <?php echo htmlspecialchars(SessionManager::getFullName()); ?></h2>
            <p><?php echo htmlspecialchars($doctor['specialization']); ?> | <?php echo $doctor['experience_years']; ?>+ years experience</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today'] ?? 0; ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">📋 Today's Schedule</div>
            <?php if (count($today_appointments) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient Name</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></span>
                                <td><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                <td>
                                    <span class="badge badge-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </span>
                                <td>
                                    <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-primary">Start Consultation</button>
                                </span>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--gray-500);">No appointments scheduled for today. 📅</p>
            <?php endif; ?>
        </div>
        
        <div class="schedule-info">
            <strong>🕒 Your Schedule:</strong> <?php echo htmlspecialchars($doctor['available_days']); ?> | 
            <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - 
            <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?>
        </div>
    </div>
    
    <script>
    function startConsultation(id) {
        window.location.href = `update-record.php?appointment_id=${id}`;
    }
    </script>
</body>
</html>