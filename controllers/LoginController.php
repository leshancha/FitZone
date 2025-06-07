<?php
// controllers/LoginController.php
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/SessionManager.php';

class LoginController {
    private $authService;
    
    public function __construct() {
        $this->authService = new AuthService();
    }
    
    public function handleLogin() {
        // Debug: Check if form is submitted
        error_log("Login form submitted");
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['login_error'] = "Invalid request method";
            header("Location: login.php");
            exit;
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $userType = $_POST['user_type'] ?? 'Member';
        $rememberMe = isset($_POST['remember']);

        // Debug: Log received values
        error_log("Email: $email, UserType: $userType");

        if (!$email || !$password) {
            $_SESSION['login_error'] = "Email and password are required";
            header("Location: login.php");
            exit;
        }

        $role = $this->mapUserTypeToRole($userType);
        $result = $this->authService->authenticate($email, $password, $role);

        if (!$result['success']) {
            $_SESSION['login_error'] = $result['error'];
            header("Location: login.php");
            exit;
        }

        $user = $result['user'];
        SessionManager::setUserSession($user);

        if ($rememberMe) {
            $token = $this->authService->createRememberToken($user['id']);
            if ($token) {
                setcookie('remember_token', $token, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
        }

        $this->redirectToDashboard($user['role']);
    }

    public function handleAutoLogin() {
        if (!empty($_COOKIE['remember_token'])) {
            $user = $this->authService->validateRememberToken($_COOKIE['remember_token']);
            if ($user) {
                SessionManager::setUserSession($user);
                $this->redirectToDashboard($user['role']);
            } else {
                // Invalid token - clear cookie
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/'
                ]);
            }
        }
    }

    private function mapUserTypeToRole($userType) {
        switch ($userType) {
            case 'Admin': return 'admin';
            case 'Staff': return 'staff';
            default: return 'customer';
        }
    }

    public function redirectToDashboard($role) {
        switch ($role) {
            case 'admin': $location = 'admin_dashboard.php'; break;
            case 'staff': $location = 'staff_dashboard.php'; break;
            default: $location = 'dashboard.php'; // Changed to dashboard.php
        }
        header("Location: $location");
        exit;
    }
}