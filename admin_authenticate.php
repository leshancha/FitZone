<?php
// admin_authenticate.php
session_start();
require 'db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HARDCODED ADMIN CREDENTIALS (FOR TESTING - REMOVE IN PRODUCTION)
$valid_admin = [
    'email' => 'admin@fitzone.lk',
    'password' => 'admin123', // Plain text for testing
    'id' => 1,
    'name' => 'System Admin'
];

// Get form data
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validate inputs
if (empty($email) || empty($password)) {
    $_SESSION['admin_login_error'] = "Email and password are required";
    header("Location: admin_login.php");
    exit;
}

// Verify credentials
if ($email !== $valid_admin['email']) {
    $_SESSION['admin_login_error'] = "Invalid admin credentials";
    header("Location: admin_login.php");
    exit;
}

if ($password !== $valid_admin['password']) {
    $_SESSION['admin_login_error'] = "Invalid admin credentials";
    header("Location: admin_login.php");
    exit;
}

// Login successful
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = $valid_admin['id'];
$_SESSION['admin_name'] = $valid_admin['name'];
$_SESSION['admin_email'] = $valid_admin['email'];

// Handle "Remember Me"
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $expires = time() + 60 * 60 * 24 * 30; // 30 days
    
    // In production, store this in database:
    // $stmt = $conn->prepare("UPDATE admin SET remember_token=?, token_expires=? WHERE id=?");
    // $stmt->execute([$token, date('Y-m-d H:i:s', $expires), $valid_admin['id']]);
    
    setcookie('admin_remember_token', $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

header("Location: admin_dashboard.php");
exit;
?>