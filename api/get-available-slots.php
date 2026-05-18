<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$doctor_id = $_GET['doctor_id'] ?? 0;
$date = $_GET['date'] ?? '';

if (!$doctor_id || !$date) {
    echo json_encode([]);
    exit();
}

// Get doctor's available time slots
$doctorQuery = "SELECT available_time_start, available_time_end FROM doctors WHERE id = :id";
$stmt = $db->prepare($doctorQuery);
$stmt->bindParam(':id', $doctor_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    echo json_encode([]);
    exit();
}

// Generate time slots (1-hour intervals)
$start = new DateTime($doctor['available_time_start']);
$end = new DateTime($doctor['available_time_end']);
$interval = new DateInterval('PT1H');
$slots = [];

// Get booked slots for the selected date
$bookedQuery = "SELECT appointment_time FROM appointments 
                WHERE doctor_id = :doctor_id AND appointment_date = :date 
                AND status NOT IN ('cancelled')";
$bookedStmt = $db->prepare($bookedQuery);
$bookedStmt->bindParam(':doctor_id', $doctor_id);
$bookedStmt->bindParam(':date', $date);
$bookedStmt->execute();
$bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

$current = clone $start;
while ($current < $end) {
    $time = $current->format('H:i:s');
    $is_booked = in_array($time, $bookedSlots);
    
    $slots[] = [
        'time' => $current->format('H:i'),
        'status' => $is_booked ? 'Booked' : 'Available'
    ];
    
    $current->add($interval);
}

echo json_encode($slots);
?>