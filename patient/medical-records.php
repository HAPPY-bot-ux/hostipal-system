<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::requireRole('patient');
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

$query = "SELECT mr.*, u.full_name as doctor_name, d.specialization 
          FROM medical_records mr
          JOIN users u ON mr.doctor_id = u.id
          LEFT JOIN doctors d ON u.id = d.user_id
          WHERE mr.patient_id = :user_id
          ORDER BY mr.record_date DESC, mr.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .record-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .record-card:hover {
            box-shadow: var(--shadow-lg);
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
        }
        .record-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        .record-date {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        .record-section {
            margin-top: 1rem;
        }
        .record-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.25rem;
        }
        .record-value {
            color: var(--gray-600);
            line-height: 1.5;
        }
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .vital-item {
            background: var(--gray-100);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            text-align: center;
        }
        .vital-label {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        .vital-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
    </style>
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
        <h2>My Medical Records</h2>
        
        <?php if (count($records) > 0): ?>
            <?php foreach ($records as $record): ?>
                <div class="record-card">
                    <div class="record-header">
                        <div class="record-title">
                            <?php echo htmlspecialchars($record['doctor_name']); ?>
                            <small style="font-size: 0.875rem; color: var(--gray-500);"> (<?php echo htmlspecialchars($record['specialization']); ?>)</small>
                        </div>
                        <div class="record-date"><?php echo date('F d, Y', strtotime($record['record_date'])); ?></div>
                    </div>
                    
                    <div class="vitals-grid">
                        <?php if ($record['blood_pressure']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Blood Pressure</div>
                                <div class="vital-value"><?php echo htmlspecialchars($record['blood_pressure']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($record['heart_rate']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Heart Rate</div>
                                <div class="vital-value"><?php echo $record['heart_rate']; ?> bpm</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($record['temperature']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Temperature</div>
                                <div class="vital-value"><?php echo $record['temperature']; ?> °F</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($record['weight']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Weight</div>
                                <div class="vital-value"><?php echo $record['weight']; ?> kg</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($record['diagnosis']): ?>
                        <div class="record-section">
                            <div class="record-label">Diagnosis</div>
                            <div class="record-value"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($record['prescription']): ?>
                        <div class="record-section">
                            <div class="record-label">Prescription</div>
                            <div class="record-value"><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($record['allergies']): ?>
                        <div class="record-section">
                            <div class="record-label">Allergies</div>
                            <div class="record-value"><?php echo nl2br(htmlspecialchars($record['allergies'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($record['notes']): ?>
                        <div class="record-section">
                            <div class="record-label">Additional Notes</div>
                            <div class="record-value"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No medical records found. Your records will appear here after your appointments.</div>
        <?php endif; ?>
    </div>
</body>
</html>