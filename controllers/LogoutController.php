<?php
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/SessionManager.php';

class LogoutController {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    public function handleLogout() {
        // Delete the remember token if exists
        if (!empty($_COOKIE['remember_token'])) {
            $this->authService->deleteRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        // Destroy session
        SessionManager::destroySession();
        
        // Redirect to login with success message
        $_SESSION['logout_message'] = "You have been successfully logged out";
        header("Location: login.php");
        exit;
    }
}

// Handle direct access
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $controller = new LogoutController();
    $controller->handleLogout();
}