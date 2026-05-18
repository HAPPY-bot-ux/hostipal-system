<?php
// Auth.php - Handles authentication operations
require_once 'SessionManager.php';

class Auth {
    private $conn;
    private $table_name = "users";
    
    public function __construct($db) {
        $this->conn = $db;
        // Ensure session is started for auth operations
        SessionManager::startSession();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Check if username or email exists
        $query = "SELECT id, username, email, password, role, full_name, phone, address, is_active 
                  FROM " . $this->table_name . " 
                  WHERE username = :username OR email = :username 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if account is active
            if (isset($user['is_active']) && !$user['is_active']) {
                return ['success' => false, 'message' => 'Your account is deactivated. Please contact administrator.'];
            }
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Use SessionManager to set user data
                $userData = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? ''
                ];
                
                SessionManager::setUser($userData);
                
                // Update last login time
                $updateQuery = "UPDATE " . $this->table_name . " 
                               SET last_login = NOW() 
                               WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                
                return ['success' => true, 'role' => $user['role'], 'message' => 'Login successful!'];
            } else {
                return ['success' => false, 'message' => 'Invalid password.'];
            }
        } else {
            return ['success' => false, 'message' => 'Username or email not found.'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        SessionManager::destroySession();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return SessionManager::isLoggedIn();
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        return SessionManager::getAllUserData();
    }
    
    /**
     * Get current user role
     */
    public function getCurrentRole() {
        return SessionManager::getRole();
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return SessionManager::getUserId();
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return SessionManager::hasRole($role);
    }
    
    /**
     * Register new user (Updated to include phone and address)
     */
    public function register($userData) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password, full_name, phone, address, role, is_active, created_at) 
                  VALUES (:username, :email, :password, :full_name, :phone, :address, :role, 1, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Set default values for optional fields
        $phone = $userData['phone'] ?? null;
        $address = $userData['address'] ?? null;
        
        $stmt->bindParam(':username', $userData['username']);
        $stmt->bindParam(':email', $userData['email']);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $userData['full_name']);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':role', $userData['role']);
        
        if ($stmt->execute()) {
            $userId = $this->conn->lastInsertId();
            return [
                'success' => true, 
                'message' => 'Registration successful!',
                'user_id' => $userId
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }
    
    /**
     * Check if username or email already exists
     */
    public function isUsernameOrEmailExists($username, $email) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE username = :username OR email = :email 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at, last_login 
                  FROM " . $this->table_name . " 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Get user by username or email
     */
    public function getUserByUsernameOrEmail($username) {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at, last_login 
                  FROM " . $this->table_name . " 
                  WHERE username = :username OR email = :username";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET full_name = :full_name, phone = :phone, address = :address 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            // Update session data
            if (SessionManager::getUserId() == $userId) {
                $_SESSION['full_name'] = $data['full_name'];
            }
            return ['success' => true, 'message' => 'Profile updated successfully!'];
        }
        
        return ['success' => false, 'message' => 'Failed to update profile.'];
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        // Verify old password
        $query = "SELECT password FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($oldPassword, $user['password'])) {
                // Validate new password strength
                if (strlen($newPassword) < 8) {
                    return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
                }
                
                // Update to new password
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':password', $newHashedPassword);
                $updateStmt->bindParam(':id', $userId);
                
                if ($updateStmt->execute()) {
                    return ['success' => true, 'message' => 'Password changed successfully!'];
                } else {
                    return ['success' => false, 'message' => 'Failed to update password.'];
                }
            } else {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
        }
        
        return ['success' => false, 'message' => 'User not found.'];
    }
    
    /**
     * Reset password (for forgot password feature)
     */
    public function resetPassword($email, $newPassword) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $updateQuery = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':password', $hashedPassword);
            $updateStmt->bindParam(':id', $user['id']);
            
            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Password reset successfully!'];
            }
        }
        
        return ['success' => false, 'message' => 'Email not found.'];
    }
    
    /**
     * Deactivate user account
     */
    public function deactivateAccount($userId) {
        $query = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Account deactivated successfully.'];
        }
        
        return ['success' => false, 'message' => 'Failed to deactivate account.'];
    }
    
    /**
     * Activate user account (admin only)
     */
    public function activateAccount($userId) {
        $query = "UPDATE " . $this->table_name . " SET is_active = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Account activated successfully.'];
        }
        
        return ['success' => false, 'message' => 'Failed to activate account.'];
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers($limit = 100, $offset = 0) {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at, last_login 
                  FROM " . $this->table_name . " 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $query = "SELECT id, username, email, full_name, phone, address, role, is_active, created_at 
                  FROM " . $this->table_name . " 
                  WHERE role = :role 
                  ORDER BY full_name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total users
     */
    public function countUsers($role = null) {
        if ($role) {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE role = :role";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':role', $role);
        } else {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>