<?php
// doctor/schedule.php - Completely Redesigned Doctor Schedule Interface
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();

// Get doctor info
$doctorQuery = "SELECT d.id as doctor_id, d.specialization, d.available_days, 
                d.available_time_start, d.available_time_end, d.consultation_fee,
                d.qualification, d.experience_years, u.full_name, u.email, u.phone
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor profile not found. Please contact administrator.");
}

// Handle schedule update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
    $available_time_start = $_POST['available_time_start'];
    $available_time_end = $_POST['available_time_end'];
    $consultation_fee = $_POST['consultation_fee'];
    
    $updateQuery = "UPDATE doctors SET 
                    available_days = :available_days,
                    available_time_start = :available_time_start,
                    available_time_end = :available_time_end,
                    consultation_fee = :consultation_fee
                    WHERE id = :doctor_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':available_days', $available_days);
    $updateStmt->bindParam(':available_time_start', $available_time_start);
    $updateStmt->bindParam(':available_time_end', $available_time_end);
    $updateStmt->bindParam(':consultation_fee', $consultation_fee);
    $updateStmt->bindParam(':doctor_id', $doctor['doctor_id']);
    
    if ($updateStmt->execute()) {
        $success = "Schedule updated successfully!";
        $stmt->execute();
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                    VALUES (:user_id, :action, :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindParam(':user_id', $user_id);
        $log_action = "Schedule Updated";
        $logStmt->bindParam(':action', $log_action);
        $log_details = "Doctor updated their weekly schedule";
        $logStmt->bindParam(':details', $log_details);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt->bindParam(':ip', $ip);
        $logStmt->execute();
    } else {
        $error = "Failed to update schedule.";
    }
}

// Parse available days into array
$available_days_array = $doctor['available_days'] ? explode(',', $doctor['available_days']) : [];

// Get upcoming appointments for the week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$appointmentsQuery = "SELECT a.*, u.full_name as patient_name, u.phone, u.email
                     FROM appointments a
                     JOIN users u ON a.patient_id = u.id
                     WHERE a.doctor_id = :doctor_id 
                     AND a.appointment_date BETWEEN :week_start AND :week_end
                     ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$appointmentsStmt = $db->prepare($appointmentsQuery);
$appointmentsStmt->bindParam(':doctor_id', $doctor['doctor_id']);
$appointmentsStmt->bindParam(':week_start', $week_start);
$appointmentsStmt->bindParam(':week_end', $week_end);
$appointmentsStmt->execute();
$week_appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize appointments by day
$appointments_by_day = [];
foreach ($week_appointments as $appointment) {
    $day = date('l', strtotime($appointment['appointment_date']));
    $appointments_by_day[$day][] = $appointment;
}

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule | MediFlow HMS - Doctor Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #0A0C15;
            --surface-card: rgba(18, 22, 33, 0.75);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #0EA5E9;
            --primary-dark: #0284C7;
            --primary-glow: rgba(14, 165, 233, 0.2);
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
            background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, transparent 70%);
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
            background: rgba(14, 165, 233, 0.1);
        }

        .schedule-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1200px;
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

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Glass Cards */
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
            font-weight: 700;
            background: rgba(14, 165, 233, 0.03);
        }

        .card-header i {
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Days Grid */
        .days-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .day-chip {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .day-chip.selected {
            background: rgba(14, 165, 233, 0.15);
            border-color: var(--primary);
        }

        .day-chip .day-name {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .day-chip input {
            display: none;
        }

        /* Time Inputs */
        .time-input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .time-box {
            flex: 1;
        }

        .time-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            display: block;
        }

        .time-input {
            width: 100%;
            padding: 0.85rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--text-main);
            font-size: 0.9rem;
        }

        .time-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Fee Input */
        .fee-input {
            width: 100%;
            padding: 0.85rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--text-main);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        /* Info Card */
        .info-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            margin-bottom: 1.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* Weekly Calendar */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .calendar-day {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
        }

        .day-head {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            background: rgba(14, 165, 233, 0.05);
        }

        .day-head .day {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .day-head .date {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .day-body {
            padding: 0.75rem;
            min-height: 200px;
        }

        .working-indicator {
            text-align: center;
            padding: 0.3rem;
            border-radius: 12px;
            font-size: 0.65rem;
            margin-bottom: 0.75rem;
        }

        .working-indicator.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent);
        }

        .working-indicator.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .appointment-item {
            background: rgba(14, 165, 233, 0.1);
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.7rem;
        }

        .patient-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .appointment-time {
            color: var(--text-muted);
            font-size: 0.6rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            font-size: 0.55rem;
            margin-top: 0.3rem;
        }

        .status-pending { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .status-confirmed { background: rgba(14, 165, 233, 0.15); color: #7DD3FC; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #F87171; }

        .empty-slot {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.65rem;
            padding: 0.5rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid var(--accent);
            color: #A7F3D0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid var(--danger);
            color: #FCA5A5;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tips */
        .tips-bar {
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.08), rgba(16, 185, 129, 0.04));
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

        /* Responsive */
        @media (max-width: 1000px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            .calendar-grid {
                grid-template-columns: repeat(4, 1fr);
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
            .schedule-wrapper {
                padding: 0 1rem;
            }
            .days-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .calendar-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link active"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="schedule-wrapper">
        <div class="top-bar">
            <h1><i class="fas fa-calendar-alt"></i> My Schedule</h1>
            <p>Manage your weekly availability and view upcoming appointments</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <div class="two-columns">
            <!-- Left Column: Schedule Settings -->
            <div class="glass-card">
                <div class="card-header">
                    <i class="fas fa-sliders-h"></i> Set Availability
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="scheduleForm">
                        <div class="days-grid">
                            <?php foreach ($days_of_week as $day): ?>
                                <div class="day-chip <?php echo in_array($day, $available_days_array) ? 'selected' : ''; ?>" 
                                     onclick="toggleDay(this, '<?php echo $day; ?>')">
                                    <div class="day-name"><?php echo substr($day, 0, 3); ?></div>
                                    <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                                           style="display: none;" <?php echo in_array($day, $available_days_array) ? 'checked' : ''; ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="time-input-group">
                            <div class="time-box">
                                <label class="time-label"><i class="fas fa-hourglass-start"></i> Start Time</label>
                                <input type="time" name="available_time_start" class="time-input" 
                                       value="<?php echo $doctor['available_time_start']; ?>" required>
                            </div>
                            <div class="time-box">
                                <label class="time-label"><i class="fas fa-hourglass-end"></i> End Time</label>
                                <input type="time" name="available_time_end" class="time-input" 
                                       value="<?php echo $doctor['available_time_end']; ?>" required>
                            </div>
                        </div>

                        <label class="time-label"><i class="fas fa-dollar-sign"></i> Consultation Fee</label>
                        <input type="number" name="consultation_fee" class="fee-input" 
                               value="<?php echo $doctor['consultation_fee']; ?>" step="10" min="0" required>

                        <button type="submit" name="update_schedule" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Schedule
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Info Cards -->
            <div>
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Current Settings
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Working Days</span>
                            <span class="info-value"><?php echo $doctor['available_days'] ?: 'Not set'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Working Hours</span>
                            <span class="info-value">
                                <?php 
                                if ($doctor['available_time_start'] && $doctor['available_time_end']) {
                                    echo date('h:i A', strtotime($doctor['available_time_start'])) . ' - ' . 
                                         date('h:i A', strtotime($doctor['available_time_end']));
                                } else { echo 'Not set'; }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Fee per Visit</span>
                            <span class="info-value">R<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Specialization</span>
                            <span class="info-value"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Experience</span>
                            <span class="info-value"><?php echo $doctor['experience_years']; ?>+ years</span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> This Week Summary
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Total Appointments</span>
                            <span class="info-value"><?php echo count($week_appointments); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pending</span>
                            <span class="info-value" style="color: var(--warning);">
                                <?php echo count(array_filter($week_appointments, fn($a) => $a['status'] == 'pending')); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Confirmed</span>
                            <span class="info-value" style="color: var(--primary);">
                                <?php echo count(array_filter($week_appointments, fn($a) => $a['status'] == 'confirmed')); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Completed</span>
                            <span class="info-value" style="color: var(--accent);">
                                <?php echo count(array_filter($week_appointments, fn($a) => $a['status'] == 'completed')); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Calendar View -->
        <div class="glass-card">
            <div class="card-header">
                <i class="fas fa-calendar-week"></i> Weekly Overview
                <span style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted);">
                    <?php echo date('M j', strtotime($week_start)); ?> - <?php echo date('M j, Y', strtotime($week_end)); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="calendar-grid">
                    <?php 
                    $current = strtotime($week_start);
                    for ($i = 0; $i < 7; $i++):
                        $day_date = date('Y-m-d', $current);
                        $day_name = date('l', $current);
                        $is_working = in_array($day_name, $available_days_array);
                        $day_appointments = $appointments_by_day[$day_name] ?? [];
                    ?>
                        <div class="calendar-day">
                            <div class="day-head">
                                <div class="day"><?php echo substr($day_name, 0, 3); ?></div>
                                <div class="date"><?php echo date('j', $current); ?></div>
                            </div>
                            <div class="day-body">
                                <div class="working-indicator <?php echo $is_working ? 'active' : 'inactive'; ?>">
                                    <i class="fas <?php echo $is_working ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                                    <?php echo $is_working ? 'Working' : 'Off'; ?>
                                </div>
                                <?php if ($is_working && $doctor['available_time_start'] && $doctor['available_time_end']): ?>
                                    <div style="font-size: 0.6rem; text-align: center; color: var(--primary); margin-bottom: 0.75rem;">
                                        <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - 
                                        <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (count($day_appointments) > 0): ?>
                                    <?php foreach ($day_appointments as $apt): ?>
                                        <div class="appointment-item">
                                            <div class="patient-name"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                                            <div class="appointment-time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></div>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-slot">
                                        <i class="fas fa-calendar-day"></i> No appointments
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        $current = strtotime('+1 day', $current);
                    endfor; 
                    ?>
                </div>
            </div>
        </div>

        <!-- Tips Bar -->
        <div class="tips-bar">
            <div><i class="fas fa-lightbulb" style="color: var(--warning);"></i> Schedule updates apply immediately for new bookings</div>
            <div><i class="fas fa-clock"></i> Existing appointments remain unchanged</div>
        </div>
    </div>

    <script>
        function toggleDay(element, day) {
            const checkbox = element.querySelector('input');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }

        // Validate time range
        const startTime = document.querySelector('input[name="available_time_start"]');
        const endTime = document.querySelector('input[name="available_time_end"]');
        
        function validateTime() {
            if (startTime.value && endTime.value && startTime.value >= endTime.value) {
                alert('End time must be after start time!');
                endTime.value = '';
            }
        }
        
        if (startTime && endTime) {
            startTime.addEventListener('change', validateTime);
            endTime.addEventListener('change', validateTime);
        }

        // Doctor theme color override
        document.documentElement.style.setProperty('--primary', '#0EA5E9');
        document.documentElement.style.setProperty('--primary-dark', '#0284C7');
    </script>
</body>
</html>