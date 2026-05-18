<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::requireRole('patient');
$database = new Database();
$db = $database->getConnection();

$specializations = ['Cardiology', 'Neurology', 'Pediatrics', 'Orthopedics', 'Dermatology', 'Ophthalmology', 'ENT', 'General Medicine'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $symptoms = htmlspecialchars(strip_tags($_POST['symptoms']));
    $patient_id = $_SESSION['user_id'];
    
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
        $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms) 
                  VALUES (:patient_id, :doctor_id, :date, :time, :symptoms)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':doctor_id', $doctor_id);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time', $time);
        $stmt->bindParam(':symptoms', $symptoms);
        
        if ($stmt->execute()) {
            $success = "Appointment booked successfully!";
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
    <title>Book Appointment - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="logo">🏥 Hospital System</a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="book-appointment.php" class="nav-link">Book Appointment</a></li>
                <li><a href="my-appointments.php" class="nav-link">My Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link">Medical Records</a></li>
                <li><a href="../logout.php" class="nav-link">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="glass-container fade-in">
        <h2>Book a New Appointment</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="bookingForm">
            <div class="form-group">
                <label class="form-label">Specialization</label>
                <select id="specialization" class="form-control" required>
                    <option value="">Select Specialization</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo $spec; ?>"><?php echo $spec; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Select Doctor</label>
                <select name="doctor_id" id="doctor_id" class="form-control" required>
                    <option value="">First select specialization</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Appointment Date</label>
                <input type="date" name="date" id="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Appointment Time</label>
                <select name="time" id="time" class="form-control" required>
                    <option value="">Select Time</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Symptoms / Reason for Visit</label>
                <textarea name="symptoms" class="form-control" rows="4" required placeholder="Please describe your symptoms..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Book Appointment</button>
        </form>
    </div>
    
    <script>
    document.getElementById('specialization').addEventListener('change', function() {
        const specialization = this.value;
        if (specialization) {
            fetch(`../api/get-doctors.php?specialization=${encodeURIComponent(specialization)}`)
                .then(response => response.json())
                .then(data => {
                    const doctorSelect = document.getElementById('doctor_id');
                    doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
                    data.forEach(doctor => {
                        doctorSelect.innerHTML += `<option value="${doctor.id}">${doctor.name} - ${doctor.specialization}</option>`;
                    });
                });
        }
    });
    
    document.getElementById('doctor_id').addEventListener('change', function() {
        const doctorId = this.value;
        if (doctorId) {
            fetch(`../api/get-available-slots.php?doctor_id=${doctorId}&date=${document.getElementById('date').value}`)
                .then(response => response.json())
                .then(data => {
                    const timeSelect = document.getElementById('time');
                    timeSelect.innerHTML = '<option value="">Select Time</option>';
                    data.forEach(slot => {
                        timeSelect.innerHTML += `<option value="${slot.time}">${slot.time} (${slot.status})</option>`;
                    });
                });
        }
    });
    </script>
</body>
</html>