<?php
$host = "localhost";
$dbname = "FitZone";
$username = "fitzone_admin";
$password = "secure_password_123";

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>