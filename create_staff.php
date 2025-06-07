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

// Initialize trainer data with default values
$trainer = [
    'name' => '',
    'email' => '',
    'role' => 'staff', // Default role
    'specialty' => '',
    'certification' => '',
    'bio' => '',
    'hourly_rate' => 25.00,
    'is_active' => true,
    'create_user_account' => true,
    'password' => '',
    'confirm_password' => '',
    'profile_image' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $trainer['name'] = trim($_POST['name'] ?? '');
    $trainer['email'] = trim($_POST['email'] ?? '');
    $trainer['role'] = $_POST['role'] ?? 'staff';
    $trainer['specialty'] = trim($_POST['specialty'] ?? '');
    $trainer['certification'] = trim($_POST['certification'] ?? '');
    $trainer['bio'] = trim($_POST['bio'] ?? '');
    $trainer['hourly_rate'] = (float)($_POST['hourly_rate'] ?? 25.00);
    $trainer['is_active'] = isset($_POST['is_active']) ? true : false;
    $trainer['create_user_account'] = isset($_POST['create_user_account']) ? true : false;
    $trainer['password'] = $_POST['password'] ?? '';
    $trainer['confirm_password'] = $_POST['confirm_password'] ?? '';
    $trainer['profile_image'] = '';

    // In the file upload section, add error reporting:
if (!empty($_FILES['profile_image']['name'])) {
    $upload_dir = __DIR__.'/../uploads/profiles/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create directory: $upload_dir");
            $error = "Failed to create upload directory";
        }
    }
}

    // Handle file upload
    if (!empty($_FILES['profile_image']['name'])) {
        $upload_dir = __DIR__.'/../uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['profile_image']['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            $error = "Only JPG, PNG, and GIF files are allowed for profile images";
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) { // 2MB max
            $error = "Profile image size must be less than 2MB";
        } else {
            $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'trainer_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                $trainer['profile_image'] = 'uploads/profiles/' . $file_name;
            } else {
                $error = "Failed to upload profile image";
            }
        }
    }

    // Validation
    if (empty($error)) {
        if (empty($trainer['name'])) {
            $error = "Trainer name is required";
        } elseif (empty($trainer['email'])) {
            $error = "Email is required";
        } elseif (!filter_var($trainer['email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (empty($trainer['specialty'])) {
            $error = "Specialty is required";
        } elseif ($trainer['hourly_rate'] < 0) {
            $error = "Hourly rate cannot be negative";
        } elseif ($trainer['create_user_account'] && empty($trainer['password'])) {
            $error = "Password is required when creating user account";
        } elseif ($trainer['create_user_account'] && ($trainer['password'] !== $trainer['confirm_password'])) {
            $error = "Passwords do not match";
        }
    }

    if (empty($error)) {
        try {
            $conn->autocommit(false); // Start transaction
            
            $user_id = null;
            
            // Create user account if requested
            if ($trainer['create_user_account']) {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $trainer['email']);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email already exists in user database");
                }
                
                // Hash password
                $password_hash = password_hash($trainer['password'], PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (name, email, password, role, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $stmt->bind_param(
                    "ssss", 
                    $trainer['name'], 
                    $trainer['email'], 
                    $password_hash,
                    $trainer['role']
                );
                $stmt->execute();
                
                $user_id = $conn->insert_id;
            }
            
            // Insert trainer (whether or not user account was created)
            $stmt = $conn->prepare("
                INSERT INTO trainers 
                (user_id, name, specialty, certification, bio, hourly_rate, is_active, profile_image, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param(
                "issssdis", 
                $user_id, 
                $trainer['name'], 
                $trainer['specialty'], 
                $trainer['certification'], 
                $trainer['bio'], 
                $trainer['hourly_rate'], 
                $trainer['is_active'],
                $trainer['profile_image']
            );
            $stmt->execute();
            
            $trainer_id = $conn->insert_id;
            
            $conn->commit();
            
            $_SESSION['dashboard_success'] = "Trainer added successfully!";
            header("Location: manage_trainers.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Trainer creation error: " . $e->getMessage());
            $error = "Failed to add trainer. Error: " . $e->getMessage();
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
    <title>Add New Trainer - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; }
        .rate-input { max-width: 150px; }
        .password-toggle { cursor: pointer; }
        .profile-image-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 3px solid #dee2e6;
            position: relative;
        }
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-image-placeholder {
            width: 100%;
            height: 100%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
        }
        .profile-image-upload {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.5);
            color: white;
            text-align: center;
            padding: 5px;
            cursor: pointer;
        }
        #profileImageInput {
            display: none;
        }
        .image-preview-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Add New Trainer</h1>
            <a href="manage_trainers.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Trainers
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm form-container">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Profile Image Upload -->
                    <div class="image-preview-container">
                        <div class="profile-image-container">
                            <div class="profile-image-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-image-upload" onclick="document.getElementById('profileImageInput').click()">
                                <i class="fas fa-camera me-1"></i> Choose Image
                            </div>
                        </div>
                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" onchange="previewProfileImage(this)">
                        <small class="text-muted">Max size: 2MB (JPG, PNG, GIF)</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?= htmlspecialchars($trainer['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= htmlspecialchars($trainer['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Specialty *</label>
                            <input type="text" class="form-control" name="specialty" 
                                   value="<?= htmlspecialchars($trainer['specialty']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Certification</label>
                            <input type="text" class="form-control" name="certification" 
                                   value="<?= htmlspecialchars($trainer['certification']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea class="form-control" name="bio" rows="3"><?= htmlspecialchars($trainer['bio']) ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Hourly Rate ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control rate-input" name="hourly_rate" 
                                       min="0" step="0.01" value="<?= number_format($trainer['hourly_rate'], 2) ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" 
                                       <?= $trainer['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Active Trainer</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="create_user_account" id="createAccount" 
                                       <?= $trainer['create_user_account'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="createAccount">Create User Account</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="userAccountFields" class="<?= !$trainer['create_user_account'] ? 'd-none' : '' ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role">
                                    <option value="staff" <?= $trainer['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="trainer" <?= $trainer['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                                    <option value="admin" <?= $trainer['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="password" 
                                           value="<?= htmlspecialchars($trainer['password']) ?>">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 offset-md-6">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" 
                                           value="<?= htmlspecialchars($trainer['confirm_password']) ?>">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirmPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Add Trainer
                        </button>
                        <a href="manage_trainers.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include 'footer.php'; ?>

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
        
        // Show/hide user account fields based on checkbox
        document.getElementById('createAccount').addEventListener('change', function() {
            const accountFields = document.getElementById('userAccountFields');
            if (this.checked) {
                accountFields.classList.remove('d-none');
            } else {
                accountFields.classList.add('d-none');
            }
        });
        
        // Format hourly rate to 2 decimal places
        document.querySelector('input[name="hourly_rate"]').addEventListener('blur', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
        
        // Preview profile image before upload
        function previewProfileImage(input) {
            const container = document.querySelector('.profile-image-container');
            const placeholder = container.querySelector('.profile-image-placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Remove placeholder if it exists
                    if (placeholder) {
                        placeholder.remove();
                    }
                    
                    // Check if image element already exists
                    let img = container.querySelector('.profile-image');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'profile-image';
                        container.insertBefore(img, container.firstChild);
                    }
                    
                    img.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>