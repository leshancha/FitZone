<?php
// auth.php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';
require_once __DIR__.'/controllers/LoginController.php';

SessionManager::startSecureSession();

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $loginController = new LoginController();
    $loginController->handleLogin();
} else {
    $_SESSION['login_error'] = "Invalid request method";
    header("Location: login.php");
    exit;
}