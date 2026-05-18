<?php
// logout.php - Handles user logout with logging

// Use SessionManager.php instead of session.php
require_once 'includes/SessionManager.php';

// Start session if needed
SessionManager::startSession();

// Log the logout action if user is logged in
if (SessionManager::isLoggedIn()) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                  VALUES (:user_id, 'logout', 'User logged out', :ip_address)";
        $stmt = $db->prepare($query);
        
        $user_id = SessionManager::getUserId();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->execute();
    } catch(PDOException $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Destroy session completely
SessionManager::destroySession();

// Redirect to login page
header("Location: login.php");
exit();
?>