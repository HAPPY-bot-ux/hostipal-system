<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::startSession();

if (!SessionManager::isLoggedIn() || $_SESSION['role'] != 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = $data['id'] ?? 0;
$status = $data['status'] ?? '';

$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "UPDATE appointments SET status = :status, updated_at = NOW() 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $appointment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>