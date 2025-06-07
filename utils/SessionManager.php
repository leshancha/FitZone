<?php
// utils/SessionManager.php
if (!class_exists('SessionManager')) {
    class SessionManager {
        public static function startSecureSession() {
            if (session_status() == PHP_SESSION_NONE) {
                // Secure session settings
                ini_set('session.cookie_secure', 1);
                ini_set('session.cookie_httponly', 1);
                ini_set('session.use_strict_mode', 1);
                ini_set('session.cookie_samesite', 'Lax');
                
                session_start();
                
                // Regenerate ID to prevent session fixation
                if (empty($_SESSION['initiated'])) {
                    session_regenerate_id();
                    $_SESSION['initiated'] = true;
                }
            }
        }
        
        // Removed duplicate isLoggedIn method
    public static function setUserSession($userData) {
            self::startSecureSession();
            $_SESSION['user'] = [
                'id' => $userData['id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'role' => $userData['role'],
                'logged_in' => true
            ];
        }
        
        public static function destroySession() {
            self::startSecureSession();
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
    
        public static function isLoggedIn() {
            self::startSecureSession();
            return !empty($_SESSION['user']['logged_in']);
        }
        
        public static function getUserRole() {
            self::startSecureSession();
            return $_SESSION['user']['role'] ?? null;
        }
    }
}
?>