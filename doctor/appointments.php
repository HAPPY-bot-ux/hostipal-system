<?php
require_once '../config/database.php';
require_once '../includes/SessionManager.php';

SessionManager::requireRole('doctor');
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get doctor id
$doctorQuery = "SELECT id FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

$query = "SELECT a.*, u.full_name as patient_name, u.phone, u.email, u.address 
          FROM appointments a
          JOIN users u ON a.patient_id = u.id
          WHERE a.doctor_id = :doctor_id";

if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
}
if ($date_filter) {
    $query .= " AND a.appointment_date = :date";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor['id']);
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
if ($date_filter) {
    $stmt->bindParam(':date', $date_filter);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Hospital System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-form input, .filter-form select {
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
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
        <h2>Manage Appointments</h2>
        
        <form method="GET" class="filter-form">
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="appointments.php" class="btn btn-secondary">Reset</a>
        </form>
        
        <?php if (count($appointments) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
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
                            <td>
                                <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($appointment['phone']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                            <td>
                                <select onchange="updateStatus(<?php echo $appointment['id']; ?>, this.value)" class="status-select">
                                    <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </td>
                            <td><?php echo htmlspecialchars(substr($appointment['symptoms'], 0, 50)); ?></td>
                            <td>
                                <button onclick="viewDetails(<?php echo $appointment['id']; ?>)" class="btn btn-primary btn-sm">View</button>
                                <?php if ($appointment['status'] != 'completed'): ?>
                                    <button onclick="startConsultation(<?php echo $appointment['id']; ?>)" class="btn btn-success btn-sm">Consult</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No appointments found.</div>
        <?php endif; ?>
    </div>
    
    <script>
    function updateStatus(appointmentId, status) {
        fetch('../api/update-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: appointmentId, status: status})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Status updated successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to update status', 'error');
            }
        });
    }
    
    function viewDetails(id) {
        // Implement modal or redirect to details page
        alert('View details functionality - implement as needed');
    }
    
    function startConsultation(id) {
        window.location.href = `update-record.php?appointment_id=${id}`;
    }
    </script>
</body>
</html>