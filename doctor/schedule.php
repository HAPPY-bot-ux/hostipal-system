<?php
// doctor/schedule.php - Manage doctor's weekly schedule with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session and check doctor role
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_schedule'])) {
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $available_time_start = $_POST['available_time_start'];
        $available_time_end = $_POST['available_time_end'];
        $consultation_fee = $_POST['consultation_fee'];
        $slot_duration = $_POST['slot_duration'] ?? 30;
        
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
            // Refresh doctor data
            $stmt->execute();
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                        VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_user_id = $user_id;
            $log_action = "Schedule Updated";
            $log_details = "Doctor updated their weekly schedule";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $log_user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
        } else {
            $error = "Failed to update schedule.";
        }
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

// Days of week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Generate time slots for preview
function generateTimeSlots($start, $end, $duration = 30) {
    $slots = [];
    $current = strtotime($start);
    $end_time = strtotime($end);
    while ($current < $end_time) {
        $slots[] = date('h:i A', $current);
        $current = strtotime("+{$duration} minutes", $current);
    }
    return $slots;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Schedule | MediFlow HMS - Doctor Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 28px;
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

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.8rem;
        }

        .form-label i {
            color: #2563eb;
        }

        .form-control, select.form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus, select.form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #2563eb;
        }

        .checkbox-item label {
            cursor: pointer;
            color: #334155;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn {
            padding: 0.7rem 1.25rem;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #e2e8f0;
            color: #475569;
        }

        .btn-outline:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
        }

        /* Current Info */
        .current-info {
            background: #f8fafc;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row i {
            width: 24px;
            color: #2563eb;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #2563eb;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Weekly Schedule Table */
        .schedule-table-wrapper {
            overflow-x: auto;
            border-radius: 24px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 24px;
            overflow: hidden;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 1rem;
            text-align: left;
            border: 1px solid #eef2ff;
            vertical-align: top;
        }

        .schedule-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.85rem;
            text-align: center;
        }

        .day-header {
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .working-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .working-badge.active {
            background: #d1fae5;
            color: #059669;
        }

        .working-badge.inactive {
            background: #fee2e2;
            color: #dc2626;
        }

        .time-slot-preview {
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 12px;
            text-align: center;
            font-size: 0.75rem;
            color: #475569;
        }

        .appointment-card {
            background: #eff6ff;
            padding: 0.6rem;
            border-radius: 14px;
            margin-bottom: 0.5rem;
            border-left: 3px solid #2563eb;
        }

        .appointment-patient {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.8rem;
        }

        .appointment-time {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.2rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }

        .empty-slot {
            text-align: center;
            color: #94a3b8;
            font-size: 0.75rem;
            padding: 0.5rem;
        }

        /* Tips Section */
        .tips-section {
            background: linear-gradient(135deg, #f0fdf4, #f0fdf4);
            border-radius: 20px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .tips-section h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #166534;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }

        .tips-list {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-left: 1rem;
            font-size: 0.75rem;
            color: #475569;
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
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            .checkbox-group {
                grid-template-columns: repeat(2, 1fr);
            }
            .schedule-table th,
            .schedule-table td {
                padding: 0.5rem;
                font-size: 0.7rem;
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
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="nav-link"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="schedule.php" class="nav-link active"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="fade-in">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> My Schedule</h1>
                <p>Manage your weekly availability and view upcoming appointments</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <!-- Settings Grid -->
            <div class="schedule-grid">
                <!-- Schedule Settings -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sliders-h"></i> Set Availability
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-week"></i> Working Days</label>
                            <div class="checkbox-group">
                                <?php foreach ($days_of_week as $day): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                                               id="day_<?php echo $day; ?>"
                                               <?php echo in_array($day, $available_days_array) ? 'checked' : ''; ?>>
                                        <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-hourglass-start"></i> Start Time</label>
                            <input type="time" name="available_time_start" class="form-control" 
                                   value="<?php echo $doctor['available_time_start']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-hourglass-end"></i> End Time</label>
                            <input type="time" name="available_time_end" class="form-control" 
                                   value="<?php echo $doctor['available_time_end']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-dollar-sign"></i> Consultation Fee ($)</label>
                            <input type="number" name="consultation_fee" class="form-control" 
                                   value="<?php echo $doctor['consultation_fee']; ?>" step="10" min="0" required>
                        </div>
                        <button type="submit" name="update_schedule" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Schedule
                        </button>
                    </form>
                </div>

                <!-- Current Schedule Info -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Current Schedule
                    </div>
                    <div class="current-info">
                        <div class="info-row"><i class="fas fa-calendar-week"></i> <strong>Working Days:</strong> <?php echo $doctor['available_days'] ?: 'Not set'; ?></div>
                        <div class="info-row"><i class="fas fa-clock"></i> <strong>Hours:</strong> 
                            <?php 
                            if ($doctor['available_time_start'] && $doctor['available_time_end']) {
                                echo date('h:i A', strtotime($doctor['available_time_start'])) . ' - ' . 
                                     date('h:i A', strtotime($doctor['available_time_end']));
                            } else { echo 'Not set'; }
                            ?>
                        </div>
                        <div class="info-row"><i class="fas fa-dollar-sign"></i> <strong>Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?></div>
                        <div class="info-row"><i class="fas fa-stethoscope"></i> <strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></div>
                        <div class="info-row"><i class="fas fa-star"></i> <strong>Experience:</strong> <?php echo $doctor['experience_years']; ?>+ years</div>
                    </div>
                    <div class="alert alert-info" style="margin-top: 0;">
                        <i class="fas fa-info-circle"></i>
                        <span>Your schedule determines when patients can book appointments with you.</span>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule View -->
            <div class="card" style="margin-top: 0;">
                <div class="card-header">
                    <i class="fas fa-calendar-week"></i> Weekly Overview
                    <span style="margin-left: auto; font-size: 0.7rem; color: #64748b;">
                        <?php echo date('M j', strtotime($week_start)); ?> - <?php echo date('M j, Y', strtotime($week_end)); ?>
                    </span>
                </div>
                <div class="schedule-table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr><th>Day</th><th>Availability</th><th>Appointments</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days_of_week as $day): ?>
                                <?php
                                $is_working = in_array($day, $available_days_array);
                                $day_appointments = $appointments_by_day[$day] ?? [];
                                ?>
                                <tr>
                                    <td style="text-align: center; width: 110px;">
                                        <div class="day-header"><?php echo substr($day, 0, 3); ?></div>
                                        <span class="working-badge <?php echo $is_working ? 'active' : 'inactive'; ?>">
                                            <?php echo $is_working ? 'Working' : 'Off'; ?>
                                        </span>
                                    </td>
                                    <td style="width: 180px;">
                                        <?php if ($is_working && $doctor['available_time_start'] && $doctor['available_time_end']): ?>
                                            <div class="time-slot-preview">
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($doctor['available_time_start'])); ?> - 
                                                <?php echo date('h:i A', strtotime($doctor['available_time_end'])); ?>
                                            </div>
                                        <?php elseif (!$is_working): ?>
                                            <div class="time-slot-preview" style="background:#fef2f2; color:#dc2626;">
                                                <i class="fas fa-ban"></i> Unavailable
                                            </div>
                                        <?php else: ?>
                                            <div class="time-slot-preview" style="background:#fef3c7;">
                                                <i class="fas fa-exclamation-triangle"></i> Hours not set
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (count($day_appointments) > 0): ?>
                                            <?php foreach ($day_appointments as $appointment): ?>
                                                <div class="appointment-card">
                                                    <div class="appointment-patient">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                    </div>
                                                    <div class="appointment-time">
                                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                    </div>
                                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-slot">
                                                <i class="fas fa-calendar-day"></i> No appointments
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="tips-section">
                <h4><i class="fas fa-lightbulb"></i> Schedule Management Tips</h4>
                <div class="tips-list">
                    <span><i class="fas fa-check-circle" style="color:#10b981;"></i> Set realistic working hours</span>
                    <span><i class="fas fa-clock"></i> Include break times between appointments</span>
                    <span><i class="fas fa-sync-alt"></i> Updates apply immediately for new bookings</span>
                    <span><i class="fas fa-calendar-check"></i> Existing appointments remain unchanged</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validate time range
        const startTime = document.querySelector('input[name="available_time_start"]');
        const endTime = document.querySelector('input[name="available_time_end"]');
        
        function validateTime() {
            if (startTime.value && endTime.value) {
                if (startTime.value >= endTime.value) {
                    alert('End time must be after start time!');
                    endTime.value = '';
                }
            }
        }
        
        if (startTime && endTime) {
            startTime.addEventListener('change', validateTime);
            endTime.addEventListener('change', validateTime);
        }
    </script>
</body>
</html>