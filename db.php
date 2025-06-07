<?php
// db.php - Single, consistent PDO connection
$host = 'localhost';
$dbname = 'fitzone';
$username = 'fitzone_admin';
$password = 'SuperSecureAdminPassword456!';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System error. Please try again later.");
}

try {
    $conn = new PDO("mysql:host=localhost;dbname=fitzone", "fitzone_admin", "SuperSecureAdminPassword456!");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connected successfully");
} catch(PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection failed");
}
?>  