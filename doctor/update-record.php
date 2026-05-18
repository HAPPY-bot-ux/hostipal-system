<?php
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::requireRole('doctor');
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : (isset($_POST['appointment_id']) ? $_POST['appointment_id'] : 0);

// Get doctor info
$doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get appointment and patient info
$appointmentQuery = "SELECT a.*, u.id as patient_id, u.full_name as patient_name, u.phone, u.email, u.address 
                     FROM appointments a
                     JOIN users u ON a.patient_id = u.id
                     WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id";
$stmt = $db->prepare($appointmentQuery);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->bindParam(':doctor_id', $doctor['id']);
$stmt->execute();
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Invalid appointment or unauthorized access.");
}

// Check if medical record already exists
$recordQuery = "SELECT * FROM medical_records 
                WHERE patient_id = :patient_id AND doctor_id = :doctor_id 
                AND record_date = CURDATE()";
$stmt = $db->prepare($recordQuery);
$stmt->bindParam(':patient_id', $appointment['patient_id']);
$stmt->bindParam(':doctor_id', $user_id);
$stmt->execute();
$existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $diagnosis = htmlspecialchars(strip_tags($_POST['diagnosis']));
    $prescription = htmlspecialchars(strip_tags($_POST['prescription']));
    $blood_pressure = htmlspecialchars(strip_tags($_POST['blood_pressure']));
    $heart_rate = intval($_POST['heart_rate']);
    $temperature = floatval($_POST['temperature']);
    $weight = floatval($_POST['weight']);
    $allergies = htmlspecialchars(strip_tags($_POST['allergies']));
    $notes = htmlspecialchars(strip_tags($_POST['notes']));
    
    if ($existing_record) {
        // Update existing record
        $query = "UPDATE medical_records SET 
                  diagnosis = :diagnosis, prescription = :prescription,
                  blood_pressure = :blood_pressure, heart_rate = :heart_rate,
                  temperature = :temperature, weight = :weight,
                  allergies = :allergies, notes = :notes
                  WHERE id = :record_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':record_id', $existing_record['id']);
    } else {
        // Insert new record
        $query = "INSERT INTO medical_records 
                  (patient_id, doctor_id, diagnosis, prescription, blood_pressure, 
                   heart_rate, temperature, weight, allergies, notes, record_date)
                  VALUES 
                  (:patient_id, :doctor_id, :diagnosis, :prescription, :blood_pressure,
                   :heart_rate, :temperature, :weight, :allergies, :notes, CURDATE())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $appointment['patient_id']);
        $stmt->bindParam(':doctor_id', $user_id);
    }
    
    $stmt->bindParam(':diagnosis', $diagnosis);
    $stmt->bindParam(':prescription', $prescription);
    $stmt->bindParam(':blood_pressure', $blood_pressure);
    $stmt->bindParam(':heart_rate', $heart_rate);
    $stmt->bindParam(':temperature', $temperature);
    $stmt->bindParam(':weight', $weight);
    $stmt->bindParam(':allergies', $allergies);
    $stmt->bindParam(':notes', $notes);
    
    if ($stmt->execute()) {
        // Update appointment status to completed
        $updateAppointment = "UPDATE appointments SET status = 'completed' WHERE id = :appointment_id";
        $stmt2 = $db->prepare($updateAppointment);
        $stmt2->bindParam(':appointment_id', $appointment_id);
        $stmt2->execute();
        
        $success = "Medical record saved successfully!";
    } else {
        $error = "Failed to save medical record.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Medical Record - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .patient-info {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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
        <h2>Medical Consultation</h2>
        
        <div class="patient-info">
            <h3>Patient Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['phone']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['email']); ?></p>
            <p><strong>Symptoms:</strong> <?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?></p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Blood Pressure</label>
                    <input type="text" name="blood_pressure" class="form-control" placeholder="120/80" value="<?php echo $existing_record['blood_pressure'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Heart Rate (bpm)</label>
                    <input type="number" name="heart_rate" class="form-control" placeholder="72" value="<?php echo $existing_record['heart_rate'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Temperature (°F)</label>
                    <input type="number" step="0.1" name="temperature" class="form-control" placeholder="98.6" value="<?php echo $existing_record['temperature'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Weight (kg)</label>
                    <input type="number" step="0.1" name="weight" class="form-control" placeholder="70" value="<?php echo $existing_record['weight'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Diagnosis</label>
                <textarea name="diagnosis" class="form-control" rows="3" placeholder="Enter diagnosis..."><?php echo $existing_record['diagnosis'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prescription</label>
                <textarea name="prescription" class="form-control" rows="4" placeholder="Enter prescription..."><?php echo $existing_record['prescription'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Allergies</label>
                <textarea name="allergies" class="form-control" rows="2" placeholder="Known allergies..."><?php echo $existing_record['allergies'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."><?php echo $existing_record['notes'] ?? ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Medical Record</button>
            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
        </form>
    </div>
</body>
</html>