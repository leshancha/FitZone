<?php
require 'db_connection.php';

// Reset all passwords to known values (for development only)
$passwords = [
    'admin@fitzone.lk' => 'admin123',
    'john@fitzone.com' => 'staff123',
    'mary@fitzone.com' => 'staff123'
];

foreach ($passwords as $email => $new_password) {
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ?, status = 'active', login_attempts = 0 WHERE email = ?");
    $stmt->execute([$hashed_password, $email]);
}

echo "Passwords reset successfully:<br>";
foreach ($passwords as $email => $password) {
    echo "$email : $password<br>";
}