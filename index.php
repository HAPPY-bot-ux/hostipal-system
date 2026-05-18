<?php
require_once 'includes/SessionManager.php';

SessionManager::startSession();

if (SessionManager::isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    
    switch ($role) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'doctor':
            header("Location: doctor/dashboard.php");
            break;
        case 'patient':
            header("Location: patient/dashboard.php");
            break;
        default:
            header("Location: login.php");
    }
} else {
    header("Location: login.php");
}
exit();
?>