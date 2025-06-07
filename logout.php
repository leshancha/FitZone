<?php
// logout.php
require_once __DIR__ . '/utils/SessionManager.php';  // Added forward slash
require_once __DIR__ . '/services/AuthService.php'; // Added forward slash

// Start secure session
SessionManager::startSecureSession();

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me token if exists
if (isset($_COOKIE['remember_token'])) {
    $authService = new AuthService();
    $authService->deleteRememberToken($_COOKIE['remember_token']);
    
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Redirect to login page with success message
$_SESSION['logout_message'] = "You have been successfully logged out";
header("Location: login.php");
exit;
?>