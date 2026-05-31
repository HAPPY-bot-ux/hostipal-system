<?php
// patient/medical-records.php - Completely Redesigned Medical Records Interface
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

// Get unique doctors count
$uniqueDoctors = count(array_unique(array_column($records, 'doctor_name')));
$diagnosisCount = 0;
$prescriptionCount = 0;
foreach ($records as $r) {
    if (!empty($r['diagnosis'])) $diagnosisCount++;
    if (!empty($r['prescription'])) $prescriptionCount++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records | MediFlow HMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Design Variables */
        :root {
            --bg-main: #0A0C15;
            --surface-card: rgba(18, 22, 33, 0.75);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-main: #F3F4F6;
            --text-muted: #9CA3AF;
            --primary: #6366F1;
            --primary-dark: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.2);
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

        /* Animated Background */
        .bg-orb-1 {
            position: fixed;
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, transparent 70%);
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

        /* Navbar */
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
            background: rgba(99, 102, 241, 0.1);
        }

        /* Main Layout */
        .records-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 2rem;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #FFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 14px;
            color: var(--text-muted);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-block {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-block:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .stat-block i {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Timeline Style Records */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--primary), var(--accent), transparent);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-dot {
            position: absolute;
            left: -2rem;
            top: 1.5rem;
            width: 16px;
            height: 16px;
            border-radius: 16px;
            background: var(--primary);
            border: 2px solid var(--bg-main);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .record-card {
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .record-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
        }

        /* Record Header */
        .record-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: rgba(99, 102, 241, 0.03);
        }

        .doctor-badge {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .doctor-meta h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .doctor-meta p {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .date-chip {
            background: rgba(99, 102, 241, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Record Body */
        .record-body {
            padding: 1.5rem;
        }

        /* Vitals Grid */
        .vitals-panel {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            padding: 1rem;
        }

        .vital-chip {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 120px;
        }

        .vital-chip i {
            font-size: 1.3rem;
            color: var(--primary);
        }

        .vital-info .label {
            font-size: 0.6rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .vital-info .value {
            font-size: 1rem;
            font-weight: 700;
        }

        /* Info Sections */
        .info-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
        }

        .section-content {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            padding: 1rem;
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--text-muted);
        }

        .allergy-box {
            background: rgba(245, 158, 11, 0.08);
            border-left: 3px solid var(--warning);
        }

        /* Empty State */
        .empty-hero {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(18, 22, 33, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 32px;
        }

        .empty-hero i {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .empty-hero h3 {
            margin-bottom: 0.5rem;
        }

        .empty-hero p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        /* Footer Alert */
        .info-footer {
            margin-top: 2rem;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .info-footer i {
            color: var(--info);
            font-size: 1.2rem;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .vitals-panel {
                flex-direction: column;
            }
            .vital-chip {
                width: 100%;
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
            .records-wrapper {
                padding: 0 1rem;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .timeline {
                padding-left: 1rem;
            }
            .timeline::before {
                left: 0;
            }
            .timeline-dot {
                left: -0.9rem;
            }
            .record-header {
                flex-direction: column;
                align-items: flex-start;
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
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link active"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="records-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-folder-medical"></i> Medical History</h1>
                <p>Your complete health record timeline</p>
            </div>
            <div class="action-buttons">
                <button class="btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Export
                </button>
                <button class="btn-outline" onclick="showHelp()">
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-block">
                <i class="fas fa-file-alt"></i>
                <div class="stat-number"><?php echo $totalRecords; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-block">
                <i class="fas fa-user-md"></i>
                <div class="stat-number"><?php echo $uniqueDoctors; ?></div>
                <div class="stat-label">Doctors</div>
            </div>
            <div class="stat-block">
                <i class="fas fa-clipboard-list"></i>
                <div class="stat-number"><?php echo $diagnosisCount; ?></div>
                <div class="stat-label">Diagnoses</div>
            </div>
            <div class="stat-block">
                <i class="fas fa-pills"></i>
                <div class="stat-number"><?php echo $prescriptionCount; ?></div>
                <div class="stat-label">Prescriptions</div>
            </div>
        </div>

        <!-- Records Timeline -->
        <?php if (count($records) > 0): ?>
            <div class="timeline">
                <?php foreach ($records as $index => $record): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="record-card">
                            <div class="record-header">
                                <div class="doctor-badge">
                                    <div class="doctor-icon">
                                        <?php echo strtoupper(substr($record['doctor_name'], 0, 1)); ?>
                                    </div>
                                    <div class="doctor-meta">
                                        <h3>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($record['specialization'] ?? 'General Medicine'); ?>
                                        <?php if (!empty($record['qualification'])): ?>
                                            • <?php echo htmlspecialchars($record['qualification']); ?>
                                        <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="date-chip">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y', strtotime($record['record_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="record-body">
                                <!-- Vitals -->
                                <?php if ($record['blood_pressure'] || $record['heart_rate'] || $record['temperature'] || $record['weight']): ?>
                                    <div class="vitals-panel">
                                        <?php if ($record['blood_pressure']): ?>
                                            <div class="vital-chip">
                                                <i class="fas fa-heartbeat"></i>
                                                <div class="vital-info">
                                                    <div class="label">Blood Pressure</div>
                                                    <div class="value"><?php echo htmlspecialchars($record['blood_pressure']); ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($record['heart_rate']): ?>
                                            <div class="vital-chip">
                                                <i class="fas fa-heart"></i>
                                                <div class="vital-info">
                                                    <div class="label">Heart Rate</div>
                                                    <div class="value"><?php echo $record['heart_rate']; ?> <span style="font-size:0.7rem;">bpm</span></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($record['temperature']): ?>
                                            <div class="vital-chip">
                                                <i class="fas fa-thermometer-half"></i>
                                                <div class="vital-info">
                                                    <div class="label">Temperature</div>
                                                    <div class="value"><?php echo $record['temperature']; ?>°F</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($record['weight']): ?>
                                            <div class="vital-chip">
                                                <i class="fas fa-weight-scale"></i>
                                                <div class="vital-info">
                                                    <div class="label">Weight</div>
                                                    <div class="value"><?php echo $record['weight']; ?> kg</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Diagnosis -->
                                <?php if (!empty($record['diagnosis'])): ?>
                                    <div class="info-section">
                                        <div class="section-header">
                                            <i class="fas fa-stethoscope"></i> Diagnosis
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Prescription -->
                                <?php if (!empty($record['prescription'])): ?>
                                    <div class="info-section">
                                        <div class="section-header">
                                            <i class="fas fa-prescription-bottle"></i> Prescription
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(htmlspecialchars($record['prescription'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Allergies -->
                                <?php if (!empty($record['allergies'])): ?>
                                    <div class="info-section">
                                        <div class="section-header">
                                            <i class="fas fa-allergies"></i> Allergies
                                        </div>
                                        <div class="section-content allergy-box">
                                            <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                                            <?php echo nl2br(htmlspecialchars($record['allergies'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Notes -->
                                <?php if (!empty($record['notes'])): ?>
                                    <div class="info-section">
                                        <div class="section-header">
                                            <i class="fas fa-pen-alt"></i> Clinical Notes
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-hero">
                <i class="fas fa-folder-open"></i>
                <h3>No Medical Records Yet</h3>
                <p>Your health records will appear here after your consultations</p>
                <a href="book-appointment.php" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i> Book First Appointment
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Info Footer -->
        <div class="info-footer">
            <i class="fas fa-phone-alt"></i>
            <span><strong>Need a copy of your records?</strong> Contact medical records: (555) 123-4567 or records@mediflow.com</span>
        </div>
    </div>

    <script>
        function showHelp() {
            alert("📋 Medical Records Help\n\n• Your records are automatically added after each consultation\n• Each record includes diagnosis, prescription, and vital signs\n• For questions about your records, contact our support team at (555) 123-4567");
        }
    </script>
</body>
</html>