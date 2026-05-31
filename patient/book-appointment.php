<?php
// patient/book-appointment.php - Re-imagined Next-Gen Interface for Appointment Booking
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$error = '';
$success = '';

// Get all specializations from doctors table
$specializationQuery = "SELECT DISTINCT specialization FROM doctors ORDER BY specialization";
$specializationStmt = $db->query($specializationQuery);
$specializations = $specializationStmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $symptoms = htmlspecialchars(strip_tags($_POST['symptoms']));
    
    // Check if slot is available
    $checkQuery = "SELECT id FROM appointments 
                   WHERE doctor_id = :doctor_id 
                   AND appointment_date = :date 
                   AND appointment_time = :time";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':doctor_id', $doctor_id);
    $checkStmt->bindParam(':date', $date);
    $checkStmt->bindParam(':time', $time);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $error = "This time slot is already booked. Please choose another time.";
    } else {
        $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status) 
                  VALUES (:patient_id, :doctor_id, :date, :time, :symptoms, 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $user_id);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':symptoms', $symptoms);
        
        if ($stmt->execute()) {
            $success = "Appointment booked successfully! You will receive a confirmation soon.";
            
            // Log the action
            $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                        VALUES (:user_id, :action, :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $log_action = "Appointment Booked";
            $log_details = "Patient booked appointment with doctor ID: $doctor_id on $date at $time";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bindParam(':user_id', $user_id);
            $logStmt->bindParam(':action', $log_action);
            $logStmt->bindParam(':details', $log_details);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
        } else {
            $error = "Failed to book appointment. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Modern System Design Variables */
        :root {
            --bg-main: #090B11;
            --surface-card: rgba(18, 22, 33, 0.65);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #6366F1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --accent: #10B981;
            --gradient-angle: 135deg;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Fluid Background Elements */
        .ambient-glow-1 {
            position: fixed;
            width: 500px;
            height: 500px;
            top: -150px;
            left: -100px;
            background: radial-gradient(circle, var(--primary-glow) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        .ambient-glow-2 {
            position: fixed;
            width: 600px;
            height: 600px;
            bottom: -200px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* Navbar */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(18, 22, 33, 0.85);
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
            background: linear-gradient(120deg, #FFF 40%, var(--text-muted) 100%);
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
            background: rgba(99, 102, 241, 0.1);
        }

        /* Main Container */
        .container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #FFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            color: var(--text-muted);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: fadeIn 0.4s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #A7F3D0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Main Form Card */
        .form-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            overflow: hidden;
        }

        /* Split Layout Inside Card */
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            min-height: 580px;
        }

        /* Left Side - Form Fields */
        .form-fields {
            padding: 2rem;
            border-right: 1px solid var(--border-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label i {
            margin-right: 6px;
            color: var(--primary);
        }

        .form-control, select.form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            font-size: 0.9rem;
            color: var(--text-main);
            transition: all 0.2s;
        }

        .form-control:focus, select.form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Time Slots - Horizontal Scroll */
        .time-slots-wrapper {
            margin-top: 0.5rem;
        }

        .time-slots-title {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-slots-scroll {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .time-slot-btn {
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .time-slot-btn:hover:not(.disabled) {
            background: rgba(99, 102, 241, 0.15);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .time-slot-btn.selected {
            background: linear-gradient(135deg, var(--primary), #3B82F6);
            color: white;
            border-color: transparent;
        }

        .time-slot-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), #3B82F6);
            border: none;
            border-radius: 20px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        /* Right Side - Info Panel */
        .info-panel {
            background: rgba(0, 0, 0, 0.2);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-section {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 1.25rem;
        }

        .info-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-section h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .appointment-preview {
            background: rgba(99, 102, 241, 0.08);
            border-radius: 20px;
            padding: 1rem;
        }

        .preview-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            font-size: 0.8rem;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
        }

        .preview-row:last-child {
            border-bottom: none;
        }

        .preview-label {
            color: var(--text-muted);
        }

        .preview-value {
            font-weight: 600;
            color: var(--primary);
        }

        .reminder-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .reminder-item i {
            width: 28px;
            color: var(--primary);
        }

        .doctor-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .doctor-card:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .doctor-card.selected {
            background: rgba(99, 102, 241, 0.15);
            border-color: var(--primary);
        }

        .doctor-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), #3B82F6);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .doctor-details h5 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .doctor-details p {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .doctor-fee {
            margin-left: auto;
            font-weight: 700;
            color: var(--primary);
            font-size: 0.8rem;
        }

        .doctors-list {
            max-height: 280px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .doctors-list::-webkit-scrollbar {
            width: 4px;
        }

        .doctors-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        .doctors-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .loading-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 900px) {
            .split-layout {
                grid-template-columns: 1fr;
            }
            .form-fields {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-group.full-width {
                grid-column: span 1;
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
            .container {
                padding: 0 1rem;
            }
            .form-fields, .info-panel {
                padding: 1.5rem;
            }
            .page-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-heart-pulse"></i>
                <span>Hospital System</span>
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="book-appointment.php" class="nav-link active"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-plus"></i> Book an Appointment</h1>
            <p>Connect with expert physicians in just a few clicks</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="" id="bookingForm">
                <div class="split-layout">
                    <!-- Left Side: Form Fields -->
                    <div class="form-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-stethoscope"></i> Specialty</label>
                                <select id="specialization" class="form-control" required>
                                    <option value="">Select specialty</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec); ?>"><?php echo htmlspecialchars($spec); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar-day"></i> Date</label>
                                <input type="date" name="date" id="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user-md"></i> Select Doctor</label>
                            <div id="doctors_container" class="doctors-list">
                                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fas fa-info-circle"></i> Choose a specialty first
                                </div>
                            </div>
                            <input type="hidden" name="doctor_id" id="selected_doctor_id" required>
                        </div>

                        <div class="form-group">
                            <div class="time-slots-wrapper">
                                <div class="time-slots-title">
                                    <i class="fas fa-clock"></i> Available Time Slots
                                </div>
                                <div id="time_slots_container" class="time-slots-scroll">
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">Select a doctor and date first</div>
                                </div>
                                <input type="hidden" name="time" id="selected_time" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-notes-medical"></i> Symptoms & Reason</label>
                            <textarea name="symptoms" id="symptoms" class="form-control" rows="3" placeholder="Please describe your symptoms, medical history, or reason for consultation..." required></textarea>
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">
                            <i class="fas fa-calendar-check"></i> Confirm Appointment
                        </button>
                    </div>

                    <!-- Right Side: Info Panel -->
                    <div class="info-panel">
                        <div class="info-section">
                            <h4><i class="fas fa-receipt"></i> Appointment Summary</h4>
                            <div class="appointment-preview" id="live_preview">
                                <div class="preview-row">
                                    <span class="preview-label">Specialty:</span>
                                    <span class="preview-value">—</span>
                                </div>
                                <div class="preview-row">
                                    <span class="preview-label">Doctor:</span>
                                    <span class="preview-value">—</span>
                                </div>
                                <div class="preview-row">
                                    <span class="preview-label">Date:</span>
                                    <span class="preview-value">—</span>
                                </div>
                                <div class="preview-row">
                                    <span class="preview-label">Time:</span>
                                    <span class="preview-value">—</span>
                                </div>
                                <div class="preview-row">
                                    <span class="preview-label">Fee:</span>
                                    <span class="preview-value">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="info-section">
                            <h4><i class="fas fa-clipboard-list"></i> Before You Go</h4>
                            <div class="reminder-item">
                                <i class="fas fa-id-card"></i>
                                <span>Bring valid ID & insurance card</span>
                            </div>
                            <div class="reminder-item">
                                <i class="fas fa-clock"></i>
                                <span>Arrive 15 minutes early</span>
                            </div>
                            <div class="reminder-item">
                                <i class="fas fa-ban"></i>
                                <span>Free cancellation up to 24h</span>
                            </div>
                        </div>

                        <div class="info-section">
                            <h4><i class="fas fa-headset"></i> Need Help?</h4>
                            <div class="reminder-item">
                                <i class="fas fa-phone"></i>
                                <span>+1 (555) 123-4567</span>
                            </div>
                            <div class="reminder-item">
                                <i class="fas fa-envelope"></i>
                                <span>care@mediflow.com</span>
                            </div>
                            <div class="reminder-item">
                                <i class="fas fa-comment-dots"></i>
                                <span>Live chat available 24/7</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const root = document.documentElement;
        root.style.setProperty('--primary', '#6366F1');

        let selectedDoctorFee = 0;
        let selectedDoctorName = '';

        // Specialty change -> load doctors
        document.getElementById('specialization').addEventListener('change', function() {
            const specialization = this.value;
            const doctorsContainer = document.getElementById('doctors_container');
            const dateInput = document.getElementById('appointment_date');
            
            if (specialization) {
                doctorsContainer.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading-spinner"></div> Loading doctors...</div>';
                dateInput.disabled = true;
                dateInput.value = '';
                
                fetch(`../api/get-doctors.php?specialization=${encodeURIComponent(specialization)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            doctorsContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-muted);">No doctors found for this specialty</div>';
                            return;
                        }
                        doctorsContainer.innerHTML = '';
                        data.forEach(doctor => {
                            const card = document.createElement('div');
                            card.className = 'doctor-card';
                            card.setAttribute('data-id', doctor.id);
                            card.setAttribute('data-name', doctor.name);
                            card.setAttribute('data-fee', doctor.fee);
                            card.innerHTML = `
                                <div class="doctor-avatar">${doctor.name.charAt(0)}</div>
                                <div class="doctor-details">
                                    <h5>Dr. ${doctor.name}</h5>
                                    <p>${doctor.specialization} • ${doctor.experience} yrs exp</p>
                                </div>
                                <div class="doctor-fee">$${doctor.fee}</div>
                            `;
                            card.onclick = () => {
                                document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
                                card.classList.add('selected');
                                document.getElementById('selected_doctor_id').value = doctor.id;
                                selectedDoctorFee = doctor.fee;
                                selectedDoctorName = `Dr. ${doctor.name}`;
                                dateInput.disabled = false;
                                updatePreview('doctor', selectedDoctorName, selectedDoctorFee);
                                updatePreview('specialty', specialization);
                                // Clear time slots when doctor changes
                                document.getElementById('time_slots_container').innerHTML = '<div style="color: var(--text-muted); font-size: 0.8rem;">Select a date to see available slots</div>';
                                document.getElementById('selected_time').value = '';
                                document.getElementById('appointment_date').value = '';
                            };
                            doctorsContainer.appendChild(card);
                        });
                    })
                    .catch(error => {
                        doctorsContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #dc2626;">Error loading doctors</div>';
                        console.error(error);
                    });
            } else {
                doctorsContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-muted);">Choose a specialty first</div>';
                dateInput.disabled = true;
            }
        });

      // Date change -> load time slots
document.getElementById('appointment_date').addEventListener('change', function() {
    const doctorId = document.getElementById('selected_doctor_id').value;
    const date = this.value;
    const timeContainer = document.getElementById('time_slots_container');
    const selectedTimeInput = document.getElementById('selected_time');
    
    selectedTimeInput.value = '';
    updatePreview('date', date);
    updatePreview('time', '—');
    
    if (doctorId && date) {
        timeContainer.innerHTML = '<div><div class="loading-spinner"></div> Loading slots...</div>';
        
        fetch(`../api/get-available-slots.php?doctor_id=${doctorId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.slots && data.slots.length > 0) {
                    timeContainer.innerHTML = '';
                    data.slots.forEach(slot => {
                        const slotBtn = document.createElement('div');
                        slotBtn.className = 'time-slot-btn';
                        if (!slot.available) {
                            slotBtn.classList.add('disabled');
                        }
                        slotBtn.textContent = slot.time; // Display: 09:00 AM
                        if (slot.available) {
                            slotBtn.onclick = () => {
                                document.querySelectorAll('.time-slot-btn').forEach(s => s.classList.remove('selected'));
                                slotBtn.classList.add('selected');
                                // Store the 24-hour format for database submission
                                selectedTimeInput.value = slot.time_24; // Database: 09:00:00
                                updatePreview('time', slot.time);
                            };
                        }
                        timeContainer.appendChild(slotBtn);
                    });
                } else {
                    timeContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.8rem;">No available slots for this date</div>';
                }
            })
            .catch(error => {
                timeContainer.innerHTML = '<div style="color: #dc2626; font-size: 0.8rem;">Error loading slots</div>';
                console.error(error);
            });
    }
});

        // Update preview panel
        function updatePreview(field, value, fee = null) {
            const previewDiv = document.getElementById('live_preview');
            const rows = previewDiv.querySelectorAll('.preview-row');
            
            if (field === 'specialty') {
                if (rows[0]) rows[0].innerHTML = `<span class="preview-label">Specialty:</span><span class="preview-value">${value}</span>`;
            } else if (field === 'doctor') {
                if (rows[1]) rows[1].innerHTML = `<span class="preview-label">Doctor:</span><span class="preview-value">${value}</span>`;
                if (rows[4] && fee) rows[4].innerHTML = `<span class="preview-label">Fee:</span><span class="preview-value">$${fee}</span>`;
            } else if (field === 'date') {
                if (rows[2]) rows[2].innerHTML = `<span class="preview-label">Date:</span><span class="preview-value">${value}</span>`;
            } else if (field === 'time') {
                if (rows[3]) rows[3].innerHTML = `<span class="preview-label">Time:</span><span class="preview-value">${value}</span>`;
            }
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const selectedTime = document.getElementById('selected_time').value;
            const selectedDoctor = document.getElementById('selected_doctor_id').value;
            const selectedDate = document.getElementById('appointment_date').value;
            
            if (!selectedDoctor) {
                e.preventDefault();
                alert('Please select a doctor');
            } else if (!selectedDate) {
                e.preventDefault();
                alert('Please select an appointment date');
            } else if (!selectedTime) {
                e.preventDefault();
                alert('Please select an appointment time');
            }
        });

        // Ambient animation
        setInterval(() => {
            const ambient = document.querySelector('.ambient-glow-1');
            if (ambient) {
                ambient.style.background = `radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(0,0,0,0) 70%)`;
            }
        }, 8000);
    </script>
</body>
</html>