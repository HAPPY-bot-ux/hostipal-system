<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::startSession();

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = $data['id'] ?? 0;

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    if ($role == 'patient') {
        $query = "UPDATE appointments SET status = 'cancelled' 
                  WHERE id = :id AND patient_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    } else if ($role == 'admin') {
        $query = "UPDATE appointments SET status = 'cancelled' WHERE id = :id";
        $stmt = $db->prepare($query);
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $stmt->bindParam(':id', $appointment_id);
    
    if ($stmt->execute()) {
        // Log the action
        $logQuery = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                     VALUES (:user_id, 'cancel_appointment', :details, :ip)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindParam(':user_id', $user_id);
        $details = "Cancelled appointment ID: $appointment_id";
        $logStmt->bindParam(':details', $details);
        $logStmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>