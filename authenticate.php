<?php
session_start();

// Hardcoded admin credentials
$valid_username = 'admin@fitzone.lk';
// Hashed password for 'admin123'
$valid_password_hash = password_hash('admin123', PASSWORD_DEFAULT);

// Get submitted form data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if ($username === $valid_username && password_verify($password, $valid_password_hash)) {
    $_SESSION['admin@fitzone.lk'] = $username;
    header("Location: admin_dashboard.php");
    exit();
} else {
    // Optional: set an error message in session
    $_SESSION['error'] = 'Invalid username or password.';
    header("Location: admin_login.php?error=1");
    exit();
}
?>
