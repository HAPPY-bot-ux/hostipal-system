<?php
// api/get-available-slots.php - Get available time slots for a doctor
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$doctor_id || !$date) {
    echo json_encode(['slots' => [], 'error' => 'Missing doctor_id or date']);
    exit;
}

try {
    // Get doctor's schedule
    $doctorQuery = "SELECT available_time_start, available_time_end, available_days, consultation_fee 
                    FROM doctors WHERE id = :id";
    $stmt = $db->prepare($doctorQuery);
    $stmt->bindParam(':id', $doctor_id);
    $stmt->execute();
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        echo json_encode(['slots' => [], 'error' => 'Doctor not found']);
        exit;
    }

    // Check if doctor works on this day
    $dayOfWeek = date('l', strtotime($date));
    $availableDays = explode(',', $doctor['available_days']);
    
    if (!in_array($dayOfWeek, $availableDays)) {
        echo json_encode(['slots' => [], 'message' => 'Doctor not available on ' . $dayOfWeek]);
        exit;
    }

    // Generate time slots (30-minute intervals for better flexibility)
    $start = new DateTime($doctor['available_time_start']);
    $end = new DateTime($doctor['available_time_end']);
    $interval = new DateInterval('PT30M');
    
    $slots = [];
    $current = clone $start;
    while ($current < $end) {
        $slots[] = $current->format('H:i:s');
        $current->add($interval);
    }

    // Get booked slots for the selected date
    $bookedQuery = "SELECT appointment_time FROM appointments 
                    WHERE doctor_id = :doctor_id AND appointment_date = :date 
                    AND status NOT IN ('cancelled')";
    $bookedStmt = $db->prepare($bookedQuery);
    $bookedStmt->bindParam(':doctor_id', $doctor_id);
    $bookedStmt->bindParam(':date', $date);
    $bookedStmt->execute();
    $bookedSlots = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Prepare response in the format expected by the booking form
    $result = [];
    foreach ($slots as $slot) {
        $timeFormatted = date('h:i A', strtotime($slot));
        $result[] = [
            'time' => $timeFormatted,
            'time_value' => $slot,
            'available' => !in_array($slot, $bookedSlots)
        ];
    }

    echo json_encode([
        'slots' => $result,
        'doctor_info' => [
            'fee' => $doctor['consultation_fee'],
            'available_days' => $doctor['available_days'],
            'start_time' => date('h:i A', strtotime($doctor['available_time_start'])),
            'end_time' => date('h:i A', strtotime($doctor['available_time_end']))
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['slots' => [], 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['slots' => [], 'error' => 'Error: ' . $e->getMessage()]);
}
?>