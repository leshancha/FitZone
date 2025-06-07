<?php
// services/AuthService.php
// services/AuthService.php
require 'db.php';  // Fixed path to db.php
require 'utils/SessionManager.php';  // Fixed path
require 'config/db.php';  // Fixed path
SessionManager::startSecureSession();

class AuthService {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function authenticate($email, $password, $role) {
        try {
            error_log("Attempting to authenticate: $email with role: $role");
            
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare(
                "SELECT id, name, email, password, role, status FROM users 
                 WHERE email = ? AND role = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows !== 1) {
                error_log("User not found or multiple users found");
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            $user = $result->fetch_assoc();
            
            if (!password_verify($password, $user['password'])) {
                error_log("Password verification failed");
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            if ($user['status'] !== 'active') {
                error_log("Account not active: " . $user['status']);
                return ['success' => false, 'error' => 'Account is ' . $user['status']];
            }
            
            // Remove password before returning user data
            unset($user['password']);
            
            error_log("Authentication successful for user: " . $user['email']);
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication failed'];
        }
    }
    
    public function createRememberToken($userId) {
        try {
            // Generate a secure random token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Store token in database
            $stmt = $this->db->prepare(
                "INSERT INTO remember_tokens (user_id, token, expires_at) 
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iss", $userId, $token, $expiry);
            $stmt->execute();
            
            return $token;
        } catch (Exception $e) {
            error_log("Token creation error: " . $e->getMessage());
            return false;
        }
    }

    public function validateRememberToken($token) {
        try {
            // Get valid token with user data
            $stmt = $this->db->prepare(
                "SELECT u.id, u.name, u.email, u.role 
                 FROM remember_tokens rt
                 JOIN users u ON rt.user_id = u.id
                 WHERE rt.token = ? 
                 AND rt.expires_at > NOW()
                 AND u.status = 'active'"
            );
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                return $result->fetch_assoc();
            }
            return false;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }

    // Add this method to your AuthService class
public function deleteRememberToken($token) {
    try {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "DELETE FROM remember_tokens WHERE token = ?"
        );
        $stmt->bind_param("s", $token);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Token deletion error: " . $e->getMessage());
        return false;
    }
}
}
?>