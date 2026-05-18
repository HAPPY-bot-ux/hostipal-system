<?php
// includes/SessionManager.php
class SessionManager {
    
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function setUser($userData) {
        self::startSession();
        
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['full_name'] = $userData['full_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function hasRole($role) {
        self::startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    public static function getRole() {
        self::startSession();
        return $_SESSION['role'] ?? null;
    }

    public static function getUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getUsername() {
        self::startSession();
        return $_SESSION['username'] ?? null;
    }

    public static function getFullName() {
        self::startSession();
        return $_SESSION['full_name'] ?? null;
    }

    public static function checkSessionTimeout($timeout = 1800) {
        self::startSession();
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::destroySession();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function destroySession() {
        self::startSession();
        
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        
        session_destroy();
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
        self::checkSessionTimeout();
    }

    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header("Location: unauthorized.php");
            exit();
        }
    }

    public static function getAllUserData() {
        self::startSession();
        
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'logged_in' => $_SESSION['logged_in'] ?? false
        ];
    }

    public static function setFlashMessage($type, $message) {
        self::startSession();
        $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    }

    public static function getFlashMessage() {
        self::startSession();
        
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        return $message;
    }
}
?>