<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::requireRole('patient');
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$query = "SELECT a.*, u.full_name as doctor_name, d.specialization 
          FROM appointments a
          JOIN doctors doc ON a.doctor_id = doc.id
          JOIN users u ON doc.user_id = u.id
          WHERE a.patient_id = :user_id";
          
if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-200);
            background: var(--white);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-btn.active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
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
        <h2>My Appointments</h2>
        
        <div class="filter-buttons">
            <button class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>" onclick="filterAppointments('all')">All</button>
            <button class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" onclick="filterAppointments('pending')">Pending</button>
            <button class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>" onclick="filterAppointments('confirmed')">Confirmed</button>
            <button class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>" onclick="filterAppointments('completed')">Completed</button>
            <button class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>" onclick="filterAppointments('cancelled')">Cancelled</button>
        </div>
        
        <?php if (count($appointments) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Symptoms</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(substr($appointment['symptoms'], 0, 50)) . (strlen($appointment['symptoms']) > 50 ? '...' : ''); ?></td>
                            <td class="action-buttons">
                                <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                    <button onclick="cancelAppointment(<?php echo $appointment['id']; ?>)" class="btn btn-danger btn-sm">Cancel</button>
                                <?php endif; ?>
                                <?php if ($appointment['status'] == 'completed'): ?>
                                    <button onclick="viewMedicalRecord(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm">View Record</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No appointments found. <a href="book-appointment.php">Book an appointment now!</a></div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function filterAppointments(status) {
        window.location.href = `my-appointments.php?status=${status}`;
    }
    
    function cancelAppointment(id) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            fetch('../api/cancel-appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Appointment cancelled successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed to cancel appointment', 'error');
                }
            });
        }
    }
    
    function viewMedicalRecord(appointmentId) {
        window.location.href = `medical-records.php?appointment_id=${appointmentId}`;
    }
    </script>
</body>
</html>