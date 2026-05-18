<?php
// doctor/dashboard.php - Doctor Dashboard with enhanced design
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Hospital System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: "👨‍⚕️";
            position: absolute;
            right: 20px;
            bottom: 10px;
            font-size: 80px;
            opacity: 0.1;
        }

        .welcome-banner h2 {
            font-size: 1.875rem;
            margin-bottom: 0.5rem;
        }

        .welcome-banner p {
            opacity: 0.9;
        }

        .doctor-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            margin-top: 1rem;
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
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
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
            font-size: 2.5rem;
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

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
            color: #1f2937;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
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

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.375rem;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s;
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #2563eb;
            color: white;
        }

        /* Schedule Info */
        .schedule-info {
            background: linear-gradient(135deg, #f9fafb, #ffffff);
            padding: 1.25rem;
            border-radius: 12px;
            margin-top: 1rem;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .schedule-info i {
            color: #2563eb;
            margin-right: 0.5rem;
        }

        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
            display: block;
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
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-banner h2 {
                font-size: 1.25rem;
            }
            
            .schedule-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-hospital"></i>
                <span>Hospital System - Doctor Portal</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php" class="nav-link"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="glass-card fade-in">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2><i class="fas fa-waveform"></i> Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?></h2>
                <p><?php echo htmlspecialchars($doctor['specialization']); ?> Specialist with <?php echo $doctor['experience_years']; ?>+ years of experience</p>
                <div class="doctor-badge">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?> &nbsp;|&nbsp;
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today'] ?? 0; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
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
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-day" style="color: #2563eb;"></i>
                        Today's Schedule
                        <span style="margin-left: auto; font-size: 0.75rem; color: #6b7280;">
                            <i class="fas fa-clock"></i> <?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                    <?php if (count($today_appointments) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-clock"></i> Time</th>
                                        <th><i class="fas fa-user"></i> Patient</th>
                                        <th><i class="fas fa-phone"></i> Contact</th>
                                        <th><i class="fas fa-tag"></i> Status</th>
                                        <th><i class="fas fa-cogs"></i> Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong>
                                        </span>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                            <br>
                                            <small style="color: #6b7280;"><?php echo htmlspecialchars($appointment['email']); ?></small>
                                         </span>
                                        <td><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                        <td>
                                            <span class="badge badge-<?php echo $appointment['status']; ?>">
                                                <i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-clock' : ($appointment['status'] == 'confirmed' ? 'fa-check-circle' : 'fa-check-double'); ?>"></i>
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </span>
                                        <td>
                                            <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm">
                                                <i class="fas fa-stethoscope"></i> Start
                                            </button>
                                        </span>
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
                        <i class="fas fa-calendar-week" style="color: #2563eb;"></i>
                        Upcoming Appointments (Next 7 Days)
                    </div>
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-clock"></i> Time</th>
                                        <th><i class="fas fa-user"></i> Patient</th>
                                        <th><i class="fas fa-tag"></i> Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                         </span>
                                        <td>
                                            <span class="badge badge-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </span>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No upcoming appointments in the next 7 days.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Schedule Information -->
            <div class="schedule-info">
                <div>
                    <i class="fas fa-calendar-alt"></i> <strong>Working Days:</strong> <?php echo htmlspecialchars($doctor['available_days']); ?>
                </div>
                <div>
                    <i class="fas fa-clock"></i> <strong>Working Hours:</strong> 
                    <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - 
                    <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?>
                </div>
                <div>
                    <i class="fas fa-dollar-sign"></i> <strong>Consultation Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="card" style="margin-top: 0; background: linear-gradient(135deg, #eff6ff, #ffffff);">
                <div class="card-header">
                    <i class="fas fa-lightbulb" style="color: #f59e0b;"></i>
                    Quick Tips
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <strong>Start Consultation:</strong> Click "Start" button to begin patient consultation
                    </div>
                    <div>
                        <i class="fas fa-notes-medical" style="color: #3b82f6;"></i>
                        <strong>Medical Records:</strong> Update patient records after consultation
                    </div>
                    <div>
                        <i class="fas fa-calendar-check" style="color: #8b5cf6;"></i>
                        <strong>Manage Schedule:</strong> Check your schedule regularly for updates
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
        
        // Auto-refresh today's appointments every minute (optional)
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>