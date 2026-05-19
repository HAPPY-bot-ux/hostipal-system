<?php
// patient/dashboard.php - Patient Dashboard with enhanced modern medical UI
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

// Start session if not already started
SessionManager::startSession();
SessionManager::requireRole('patient');

$database = new Database();
$db = $database->getConnection();

$user_id = SessionManager::getUserId();
$user_name = SessionManager::getFullName();

// Get patient info
$patientQuery = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($patientQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get appointment statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM appointments WHERE patient_id = :user_id";
$stmt = $db->prepare($statsQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcomingQuery = "SELECT a.*, 
                         u.full_name as doctor_name, 
                         d.specialization,
                         d.consultation_fee
                  FROM appointments a
                  JOIN doctors d ON a.doctor_id = d.id
                  JOIN users u ON d.user_id = u.id
                  WHERE a.patient_id = :user_id 
                  AND a.appointment_date >= CURDATE()
                  AND a.status IN ('pending', 'confirmed')
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 5";
$stmt = $db->prepare($upcomingQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent medical records
$recentRecordsQuery = "SELECT * FROM medical_records 
                       WHERE patient_id = :user_id 
                       ORDER BY record_date DESC, created_at DESC 
                       LIMIT 5";
$stmt = $db->prepare($recentRecordsQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recommended doctors (doctors with most appointments or random)
$recommendedQuery = "SELECT d.*, u.full_name, u.email, u.phone,
                     COUNT(a.id) as appointment_count
                     FROM doctors d
                     JOIN users u ON d.user_id = u.id
                     LEFT JOIN appointments a ON d.id = a.doctor_id
                     WHERE u.is_active = 1
                     GROUP BY d.id
                     ORDER BY appointment_count DESC
                     LIMIT 3";
$recommendedStmt = $db->query($recommendedQuery);
$recommended_doctors = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | MediFlow HMS</title>
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

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .welcome-section::before {
            content: "♥";
            position: absolute;
            right: -20px;
            bottom: -30px;
            font-size: 150px;
            opacity: 0.08;
            font-weight: 300;
        }

        .welcome-content h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .patient-since {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            backdrop-filter: blur(4px);
        }

        /* Stats Grid - Modern Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 24px;
            transition: all 0.3s;
            border: 1px solid rgba(37, 99, 235, 0.08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
            border-color: rgba(37, 99, 235, 0.2);
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

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(37, 99, 235, 0.08);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            font-size: 1.125rem;
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

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.875rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table th {
            font-weight: 600;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            gap: 0.375rem;
        }

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-completed {
            background: #d1fae5;
            color: #059669;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
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
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #2563eb;
            color: #2563eb;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.7rem;
        }

        /* Doctor Cards */
        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .doctor-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .doctor-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
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

        .doctor-details h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .doctor-details p {
            font-size: 0.7rem;
            color: #64748b;
            margin: 0.125rem 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
            display: block;
        }

        /* Tips Section */
        .tips-section {
            background: linear-gradient(135deg, #f0f9ff, #eef2ff);
            border-radius: 20px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.1);
        }

        .tips-section h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .tips-section ul {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-left: 1.5rem;
            color: #475569;
            font-size: 0.8rem;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e293b;
            color: white;
            padding: 0.875rem 1.25rem;
            border-radius: 16px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 3000;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .toast.success { background: #059669; }
        .toast.error { background: #dc2626; }
        .toast.show { display: flex; }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeInUp 0.5s ease-out;
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
            .container { padding: 0 1rem; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .welcome-content h2 { font-size: 1.25rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="book-appointment.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Book</a></li>
                <li><a href="my-appointments.php" class="nav-link"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="medical-records.php" class="nav-link"><i class="fas fa-notes-medical"></i> Records</a></li>
                <li><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="fade-in">
            <!-- Welcome Banner -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p>Your health journey matters. Track appointments, records, and stay connected with your care team.</p>
                    <div class="patient-since">
                        <i class="fas fa-calendar-alt"></i>
                        Patient since <?php echo date('F Y', strtotime($patient['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-number"><?php echo ($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0); ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                    <div class="stat-label">Visits Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="dashboard-grid">
                <!-- Upcoming Appointments -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i> Upcoming Visits
                        <a href="my-appointments.php" class="btn btn-outline btn-sm" style="margin-left: auto;">View all <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Doctor</th><th>Specialty</th><th>Date & Time</th><th>Status</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($appointment['doctor_name']); ?></strong><br><small style="color:#64748b;">$<?php echo number_format($appointment['consultation_fee'], 2); ?></small></td>
                                        <td><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br><small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small></td>
                                        <td><span class="badge badge-<?php echo $appointment['status']; ?>"><i class="fas <?php echo $appointment['status'] == 'pending' ? 'fa-hourglass-half' : 'fa-check'; ?>"></i> <?php echo ucfirst($appointment['status']); ?></span></td>
                                        <td><button onclick="cancelAppointment(<?php echo $appointment['id']; ?>)" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-day"></i>
                            <p>No upcoming appointments</p>
                            <a href="book-appointment.php" class="btn btn-primary" style="margin-top: 0.5rem;"><i class="fas fa-plus"></i> Book Now</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Medical Records -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-medical"></i> Recent Records
                        <a href="medical-records.php" class="btn btn-outline btn-sm" style="margin-left: auto;">History <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php if (count($recent_records) > 0): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>Diagnosis</th><th>Prescription</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($record['diagnosis'] ?? 'N/A', 0, 40)) . ((strlen($record['diagnosis'] ?? '') > 40) ? '…' : ''); ?></td>
                                        <td><?php echo htmlspecialchars(substr($record['prescription'] ?? 'N/A', 0, 35)) . ((strlen($record['prescription'] ?? '') > 35) ? '…' : ''); ?></td>
                                        <td><button class="btn btn-outline btn-sm" onclick="viewRecord(<?php echo $record['id']; ?>)"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-folder-open"></i><p>No medical records yet</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recommended Doctors -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <i class="fas fa-user-md"></i> Recommended Specialists
                    <a href="book-appointment.php" class="btn btn-outline btn-sm" style="margin-left: auto;">Book now <i class="fas fa-calendar-plus"></i></a>
                </div>
                <div class="doctor-grid">
                    <?php foreach ($recommended_doctors as $doctor): ?>
                        <div class="doctor-item">
                            <div class="doctor-info">
                                <div class="doctor-avatar"><?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?></div>
                                <div class="doctor-details">
                                    <h4>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                                    <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                    <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($doctor['consultation_fee'], 2); ?> | <i class="fas fa-star" style="color:#f59e0b;"></i> <?php echo $doctor['appointment_count'] ?? 0; ?> consultations</p>
                                </div>
                            </div>
                            <a href="book-appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-calendar-check"></i> Book</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Helpful Tips -->
            <div class="tips-section">
                <h4><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> Patient Quick Guide</h4>
                <ul>
                    <li><i class="fas fa-check-circle" style="color:#10b981;"></i> Book appointments with top specialists</li>
                    <li><i class="fas fa-clock" style="color:#3b82f6;"></i> Cancel at least 24h before visit</li>
                    <li><i class="fas fa-lock" style="color:#8b5cf6;"></i> Your medical data is fully secure</li>
                    <li><i class="fas fa-phone-alt" style="color:#ec4898;"></i> 24/7 Support: (555) 123-4567</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"><i class="fas"></i><span id="toastMessage"></span></div>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('.fas');
            toastMessage.textContent = message;
            toast.classList.add('show', type);
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            setTimeout(() => toast.classList.remove('show', type), 3500);
        }

        function cancelAppointment(id) {
            if (confirm('Cancel this appointment? This action cannot be undone.')) {
                fetch('../api/cancel-appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Appointment cancelled successfully', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.message || 'Cancellation failed', 'error');
                    }
                })
                .catch(() => showToast('Network error', 'error'));
            }
        }

        function viewRecord(recordId) {
            window.location.href = `view-record.php?id=${recordId}`;
        }
    </script>
</body>
</html>