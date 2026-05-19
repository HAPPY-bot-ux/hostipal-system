<?php
// patient/book-appointment.php - Book appointment with modern medical UI
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

        /* Booking Grid */
        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 28px;
            padding: 1.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .form-card-header {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .form-card-header i {
            color: #2563eb;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
        }

        .form-label i {
            color: #2563eb;
            font-size: 0.9rem;
        }

        .form-control, select.form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            color: #1e293b;
        }

        .form-control:focus, select.form-control:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Time Slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .time-slot {
            padding: 0.7rem 0.5rem;
            text-align: center;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
        }

        .time-slot:hover:not(.disabled) {
            background: #eff6ff;
            border-color: #2563eb;
            transform: translateY(-2px);
        }

        .time-slot.selected {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border-color: #2563eb;
        }

        .time-slot.disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Buttons */
        .btn {
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Alerts */
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Info Sidebar */
        .info-card {
            background: white;
            border-radius: 28px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .info-card-header {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }

        .info-card-header i {
            color: #2563eb;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0;
            color: #475569;
            font-size: 0.85rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item i {
            width: 24px;
            color: #2563eb;
        }

        .help-number {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
        }

        .help-number span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2563eb;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(3, 1fr);
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
            
            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .time-slots {
                grid-template-columns: 1fr;
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
            <h1><i class="fas fa-calendar-plus"></i> Schedule an Appointment</h1>
            <p>Connect with our medical specialists for personalized care</p>
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
        
        <div class="booking-grid">
            <!-- Main Booking Form -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="fas fa-clipboard-list"></i> Appointment Details
                </div>
                
                <form method="POST" action="" id="bookingForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i> Medical Specialty
                        </label>
                        <select id="specialization" class="form-control" required>
                            <option value="">Select a specialty</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>"><?php echo htmlspecialchars($spec); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-md"></i> Choose Physician
                        </label>
                        <select name="doctor_id" id="doctor_id" class="form-control" required disabled>
                            <option value="">First select a specialty</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar-day"></i> Preferred Date
                        </label>
                        <input type="date" name="date" id="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock"></i> Available Time Slots
                        </label>
                        <div id="time_slots" class="time-slots">
                            <div style="grid-column: span 4; text-align: center; color: #94a3b8; padding: 1rem;">
                                <i class="fas fa-info-circle"></i> Select doctor and date first
                            </div>
                        </div>
                        <input type="hidden" name="time" id="selected_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-notes-medical"></i> Symptoms & Reason for Visit
                        </label>
                        <textarea name="symptoms" id="symptoms" class="form-control" rows="4" placeholder="Please describe your symptoms, medical history, or reason for consultation..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-calendar-check"></i> Confirm Booking
                    </button>
                </form>
            </div>
            
            <!-- Sidebar Information -->
            <div>
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-info-circle"></i> Before Your Visit
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Arrive 15 minutes before scheduled time</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-id-card"></i>
                        <span>Bring valid ID & insurance card</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-folder-open"></i>
                        <span>Previous medical records (if any)</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-ban"></i>
                        <span>Cancellations: 24 hours notice required</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-phone-alt"></i> 24/7 Support
                    </div>
                    <div class="help-number">
                        <i class="fas fa-phone"></i>
                        <span>+1 (555) 123-4567</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span>care@mediflow.com</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-comment-dots"></i>
                        <span>Live chat available 24/7</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-shield-alt"></i> Your Privacy Matters
                    </div>
                    <div class="info-item">
                        <i class="fas fa-lock"></i>
                        <span>HIPAA compliant platform</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-database"></i>
                        <span>Secure medical records storage</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-secret"></i>
                        <span>Confidential consultations</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Get doctors by specialization
        document.getElementById('specialization').addEventListener('change', function() {
            const specialization = this.value;
            const doctorSelect = document.getElementById('doctor_id');
            const dateInput = document.getElementById('date');
            const timeSlotsDiv = document.getElementById('time_slots');
            
            if (specialization) {
                doctorSelect.innerHTML = '<option value="">Loading doctors...</option>';
                doctorSelect.disabled = true;
                
                fetch(`../api/get-doctors.php?specialization=${encodeURIComponent(specialization)}`)
                    .then(response => response.json())
                    .then(data => {
                        doctorSelect.innerHTML = '<option value="">Select a doctor</option>';
                        doctorSelect.disabled = false;
                        data.forEach(doctor => {
                            doctorSelect.innerHTML += `<option value="${doctor.id}" data-start="${doctor.start_time}" data-end="${doctor.end_time}" data-days="${doctor.available_days}">
                                Dr. ${doctor.name} - ${doctor.specialization} (${doctor.experience} yrs, $${doctor.fee})
                            </option>`;
                        });
                        dateInput.disabled = false;
                        timeSlotsDiv.innerHTML = '<div style="grid-column: span 4; text-align: center; color: #94a3b8; padding: 1rem;">Select a doctor to see available dates</div>';
                    })
                    .catch(error => {
                        doctorSelect.innerHTML = '<option value="">Error loading doctors</option>';
                        console.error(error);
                    });
            } else {
                doctorSelect.innerHTML = '<option value="">First select a specialty</option>';
                doctorSelect.disabled = true;
                dateInput.disabled = true;
                dateInput.value = '';
                timeSlotsDiv.innerHTML = '<div style="grid-column: span 4; text-align: center; color: #94a3b8; padding: 1rem;">Select doctor and date first</div>';
            }
        });
        
        // Load time slots when doctor and date are selected
        document.getElementById('doctor_id').addEventListener('change', function() {
            const dateInput = document.getElementById('date');
            if (this.value) {
                dateInput.disabled = false;
                dateInput.min = new Date().toISOString().split('T')[0];
            } else {
                dateInput.disabled = true;
                dateInput.value = '';
                document.getElementById('time_slots').innerHTML = '<div style="grid-column: span 4; text-align: center; color: #94a3b8; padding: 1rem;">Select a doctor first</div>';
            }
        });
        
        document.getElementById('date').addEventListener('change', loadTimeSlots);
        
        function loadTimeSlots() {
            const doctorId = document.getElementById('doctor_id').value;
            const date = document.getElementById('date').value;
            const timeSlotsDiv = document.getElementById('time_slots');
            const selectedTimeInput = document.getElementById('selected_time');
            
            selectedTimeInput.value = '';
            
            if (doctorId && date) {
                timeSlotsDiv.innerHTML = '<div style="grid-column: span 4; text-align: center; padding: 1rem;"><div class="loading-spinner"></div> Loading available slots...</div>';
                
                fetch(`../api/get-available-slots.php?doctor_id=${doctorId}&date=${date}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.slots && data.slots.length > 0) {
                            timeSlotsDiv.innerHTML = '';
                            data.slots.forEach(slot => {
                                const slotDiv = document.createElement('div');
                                slotDiv.className = 'time-slot';
                                if (!slot.available) {
                                    slotDiv.classList.add('disabled');
                                }
                                slotDiv.textContent = slot.time;
                                if (slot.available) {
                                    slotDiv.onclick = function() {
                                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                                        this.classList.add('selected');
                                        selectedTimeInput.value = slot.time;
                                    };
                                }
                                timeSlotsDiv.appendChild(slotDiv);
                            });
                        } else {
                            timeSlotsDiv.innerHTML = '<div style="grid-column: span 4; text-align: center; color: #94a3b8; padding: 1rem;"><i class="fas fa-calendar-times"></i> No available slots for this date</div>';
                        }
                    })
                    .catch(error => {
                        timeSlotsDiv.innerHTML = '<div style="grid-column: span 4; text-align: center; color: #dc2626; padding: 1rem;">Error loading time slots</div>';
                        console.error(error);
                    });
            }
        }
        
        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const selectedTime = document.getElementById('selected_time').value;
            if (!selectedTime) {
                e.preventDefault();
                alert('Please select an appointment time');
            }
        });
    </script>
</body>
</html>