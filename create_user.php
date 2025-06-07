<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not admin
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$admin_id = $_SESSION['admin_id'];
$success = '';
$error = '';

// Initialize user data with default values
$user = [
    'name' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'phone' => '',
    'address' => '',
    'date_of_birth' => '',
    'gender' => '',
    'role' => 'customer',
    'status' => 'active'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $user['name'] = trim($_POST['name'] ?? '');
    $user['email'] = trim($_POST['email'] ?? '');
    $user['password'] = $_POST['password'] ?? '';
    $user['confirm_password'] = $_POST['confirm_password'] ?? '';
    $user['phone'] = trim($_POST['phone'] ?? '');
    $user['address'] = trim($_POST['address'] ?? '');
    $user['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    $user['gender'] = $_POST['gender'] ?? '';
    $user['role'] = $_POST['role'] ?? 'customer';
    $user['status'] = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($user['name'])) {
        $error = "Name is required";
    } elseif (empty($user['email'])) {
        $error = "Email is required";
    } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (empty($user['password'])) {
        $error = "Password is required";
    } elseif (strlen($user['password']) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif ($user['password'] !== $user['confirm_password']) {
        $error = "Passwords do not match";
    } else {
        try {
            $conn->autocommit(false); // Start transaction
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $user['email']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Email already exists");
            }
            
            // Hash password
            $password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users 
                (name, email, password, phone, address, date_of_birth, gender, role, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param(
                "sssssssss", 
                $user['name'], 
                $user['email'], 
                $password_hash,
                $user['phone'],
                $user['address'],
                $user['date_of_birth'],
                $user['gender'],
                $user['role'],
                $user['status']
            );
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            
            // If creating a trainer, add to trainers table
            if ($user['role'] === 'trainer') {
                $stmt = $conn->prepare("
                    INSERT INTO trainers 
                    (user_id, name, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $is_active = $user['status'] === 'active' ? 1 : 0;
                $stmt->bind_param("isi", $user_id, $user['name'], $is_active);
                $stmt->execute();
            }
            
            $conn->commit();
            
            $_SESSION['dashboard_success'] = "User created successfully!";
            header("Location: manage_user.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("User creation error: " . $e->getMessage());
            $error = "Failed to create user. Error: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New User - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; }
        .password-toggle { cursor: pointer; }
        .form-section { 
            background-color: #f8f9fa; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 20px;
        }
        .form-section h5 {
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Create New User</h1>
            <a href="manage_user.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Users
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm form-container">
            <div class="card-body">
                <form method="POST">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <h5><i class="fas fa-user me-2"></i>Basic Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" 
                                           value="<?= htmlspecialchars($user['password']) ?>" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" 
                                           value="<?= htmlspecialchars($user['confirm_password']) ?>" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirmPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <h5><i class="fas fa-info-circle me-2"></i>Additional Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?= htmlspecialchars($user['phone']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" 
                                       value="<?= htmlspecialchars($user['date_of_birth']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="1"><?= htmlspecialchars($user['address']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Settings Section -->
                    <div class="form-section">
                        <h5><i class="fas fa-cog me-2"></i>Account Settings</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="trainer" <?= $user['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="locked" <?= $user['status'] === 'locked' ? 'selected' : '' ?>>Locked</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create User
                        </button>
                        <a href="manage_user.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Set maximum date for date of birth (18 years ago)
        document.addEventListener('DOMContentLoaded', function() {
            const dobInput = document.querySelector('input[name="date_of_birth"]');
            if (dobInput) {
                const today = new Date();
                const maxDate = new Date();
                maxDate.setFullYear(today.getFullYear() - 18);
                dobInput.max = maxDate.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>