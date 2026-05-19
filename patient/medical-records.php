<?php
// patient/medical-records.php - Medical Records with modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$user_name = SessionManager::getFullName();

$query = "SELECT mr.*, u.full_name as doctor_name, d.specialization, d.qualification
          FROM medical_records mr
          JOIN users u ON mr.doctor_id = u.id
          LEFT JOIN doctors d ON u.id = d.user_id
          WHERE mr.patient_id = :user_id
          ORDER BY mr.record_date DESC, mr.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$totalRecords = count($records);
$latestRecord = !empty($records) ? $records[0] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Medical Records | MediFlow HMS</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 24px;
            border: 1px solid rgba(37, 99, 235, 0.08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #2563eb;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Record Cards */
        .record-card {
            background: white;
            border-radius: 28px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .record-card:hover {
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.12);
        }

        .record-header {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eef2ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .doctor-details h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .doctor-details p {
            font-size: 0.75rem;
            color: #64748b;
        }

        .record-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #eff6ff;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            color: #2563eb;
        }

        .record-body {
            padding: 1.5rem;
        }

        /* Vitals Grid */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .vital-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 20px;
            text-align: center;
            transition: all 0.2s;
            border: 1px solid #eef2ff;
        }

        .vital-card:hover {
            background: #eff6ff;
            transform: translateY(-2px);
        }

        .vital-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .vital-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        .vital-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0.25rem;
        }

        /* Record Sections */
        .record-section {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #eef2ff;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }

        .section-title i {
            color: #2563eb;
            width: 20px;
        }

        .section-content {
            color: #475569;
            line-height: 1.6;
            font-size: 0.9rem;
            background: #fafcff;
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid #eef2ff;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 28px;
            padding: 3rem;
            text-align: center;
            border: 1px solid rgba(37, 99, 235, 0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.6rem 1.25rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        /* Alert */
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            padding: 1rem 1.25rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid #2563eb;
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
            .record-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .vitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.5s ease-out;
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link active"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="fade-in">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-folder-medical"></i> My Medical Records</h1>
                <p>Your complete health history at your fingertips</p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $totalRecords; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-md"></i></div>
                    <div class="stat-number"><?php echo count(array_unique(array_column($records, 'doctor_name'))); ?></div>
                    <div class="stat-label">Doctors Consulted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                    <div class="stat-number"><?php 
                        $diagnosisCount = 0;
                        foreach ($records as $r) {
                            if (!empty($r['diagnosis'])) $diagnosisCount++;
                        }
                        echo $diagnosisCount;
                    ?></div>
                    <div class="stat-label">Diagnoses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-pills"></i></div>
                    <div class="stat-number"><?php 
                        $prescriptionCount = 0;
                        foreach ($records as $r) {
                            if (!empty($r['prescription'])) $prescriptionCount++;
                        }
                        echo $prescriptionCount;
                    ?></div>
                    <div class="stat-label">Prescriptions</div>
                </div>
            </div>

            <!-- Medical Records List -->
            <?php if (count($records) > 0): ?>
                <?php foreach ($records as $record): ?>
                    <div class="record-card">
                        <div class="record-header">
                            <div class="doctor-info">
                                <div class="doctor-avatar">
                                    <?php echo strtoupper(substr($record['doctor_name'], 0, 1)); ?>
                                </div>
                                <div class="doctor-details">
                                    <h3>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h3>
                                    <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($record['specialization'] ?? 'General Medicine'); ?> 
                                    <?php if (!empty($record['qualification'])): ?>
                                        | <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($record['qualification']); ?>
                                    <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="record-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('F d, Y', strtotime($record['record_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="record-body">
                            <!-- Vitals Section -->
                            <?php if ($record['blood_pressure'] || $record['heart_rate'] || $record['temperature'] || $record['weight']): ?>
                                <div class="vitals-grid">
                                    <?php if ($record['blood_pressure']): ?>
                                        <div class="vital-card">
                                            <div class="vital-icon"><i class="fas fa-heartbeat"></i></div>
                                            <div class="vital-label">Blood Pressure</div>
                                            <div class="vital-value"><?php echo htmlspecialchars($record['blood_pressure']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['heart_rate']): ?>
                                        <div class="vital-card">
                                            <div class="vital-icon"><i class="fas fa-heart"></i></div>
                                            <div class="vital-label">Heart Rate</div>
                                            <div class="vital-value"><?php echo $record['heart_rate']; ?> <span style="font-size:0.7rem;">bpm</span></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['temperature']): ?>
                                        <div class="vital-card">
                                            <div class="vital-icon"><i class="fas fa-thermometer-half"></i></div>
                                            <div class="vital-label">Temperature</div>
                                            <div class="vital-value"><?php echo $record['temperature']; ?> °F</div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['weight']): ?>
                                        <div class="vital-card">
                                            <div class="vital-icon"><i class="fas fa-weight-scale"></i></div>
                                            <div class="vital-label">Weight</div>
                                            <div class="vital-value"><?php echo $record['weight']; ?> kg</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Diagnosis -->
                            <?php if (!empty($record['diagnosis'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-clipboard-list"></i> Diagnosis
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Prescription -->
                            <?php if (!empty($record['prescription'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-prescription-bottle"></i> Prescription
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['prescription'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Allergies -->
                            <?php if (!empty($record['allergies'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-allergies"></i> Allergies
                                    </div>
                                    <div class="section-content" style="background: #fef3c7; border-color: #fde68a;">
                                        <i class="fas fa-exclamation-triangle" style="color: #d97706;"></i>
                                        <?php echo nl2br(htmlspecialchars($record['allergies'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Additional Notes -->
                            <?php if (!empty($record['notes'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-pen-alt"></i> Clinical Notes
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Medical Records Yet</h3>
                    <p>Your medical records will appear here after your consultations with our doctors.</p>
                    <a href="book-appointment.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Health Reminder -->
            <div class="alert-info" style="margin-top: 1.5rem; background: #f0fdf4; border-left-color: #22c55e;">
                <i class="fas fa-phone-alt" style="color: #22c55e;"></i>
                <span><strong>Need a copy of your records?</strong> Contact our medical records department at (555) 123-4567 or email records@mediflow.com</span>
            </div>
        </div>
    </div>
</body>
</html>