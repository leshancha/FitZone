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
    'id' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'date_of_birth' => '',
    'gender' => '',
    'role' => 'customer',
    'status' => 'active',
    'profile_image' => '',
    'is_trainer' => false,
    'trainer_id' => null
];

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header("Location: manage_user.php");
    exit;
}

// Load user data
try {
    $stmt = $conn->prepare("
        SELECT u.*, t.id as trainer_id
        FROM users u
        LEFT JOIN trainers t ON u.id = t.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User not found");
    }
    
    $user = $result->fetch_assoc();
    $user['is_trainer'] = !is_null($user['trainer_id']);
    
    // Convert date format for input field
    if (!empty($user['date_of_birth'])) {
        $user['date_of_birth'] = date('Y-m-d', strtotime($user['date_of_birth']));
    }
} catch (Exception $e) {
    error_log("User load error: " . $e->getMessage());
    $error = "Failed to load user data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $user['name'] = trim($_POST['name'] ?? '');
    $user['email'] = trim($_POST['email'] ?? '');
    $user['phone'] = trim($_POST['phone'] ?? '');
    $user['address'] = trim($_POST['address'] ?? '');
    $user['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    $user['gender'] = $_POST['gender'] ?? '';
    $user['role'] = $_POST['role'] ?? 'customer';
    $user['status'] = $_POST['status'] ?? 'active';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $make_trainer = isset($_POST['make_trainer']) ? true : false;
    $specialty = trim($_POST['specialty'] ?? '');
    $certification = trim($_POST['certification'] ?? '');
    $hourly_rate = isset($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : 0;
    
    // Validation
    if (empty($user['name'])) {
        $error = "Name is required";
    } elseif (empty($user['email'])) {
        $error = "Email is required";
    } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $conn->autocommit(false); // Start transaction
            
            // Check if email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $user['email'], $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Email already exists");
            }
            
            // Handle profile image upload
            $profile_image = $user['profile_image'] ?? ''; // Keep existing image by default or set to empty string
            
            if (!empty($_FILES['profile_image']['name'])) {
                $upload_dir = __DIR__.'/../uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($file_info, $_FILES['profile_image']['tmp_name']);
                
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception("Only JPG, PNG, and GIF files are allowed for profile images");
                }
                
                if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) { // 2MB max
                    throw new Exception("Profile image size must be less than 2MB");
                }
                
                $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                // Delete old profile image if it exists
                if (!empty($profile_image) && file_exists(__DIR__.'/../'.$profile_image)) {
                    unlink(__DIR__.'/../'.$profile_image);
                }
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                    $profile_image = 'uploads/profiles/' . $file_name;
                } else {
                    throw new Exception("Failed to upload profile image");
                }
            }
            
            // Update user
            if (!empty($new_password)) {
                // Update with password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users SET
                    name = ?,
                    email = ?,
                    password = ?,
                    phone = ?,
                    address = ?,
                    date_of_birth = ?,
                    gender = ?,
                    role = ?,
                    status = ?,
                    profile_image = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "ssssssssssi",
                    $user['name'],
                    $user['email'],
                    $password_hash,
                    $user['phone'],
                    $user['address'],
                    $user['date_of_birth'],
                    $user['gender'],
                    $user['role'],
                    $user['status'],
                    $profile_image,
                    $user_id
                );
            } else {
                // Update without password
                $stmt = $conn->prepare("
                    UPDATE users SET
                    name = ?,
                    email = ?,
                    phone = ?,
                    address = ?,
                    date_of_birth = ?,
                    gender = ?,
                    role = ?,
                    status = ?,
                    profile_image = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssssssssi",
                    $user['name'],
                    $user['email'],
                    $user['phone'],
                    $user['address'],
                    $user['date_of_birth'],
                    $user['gender'],
                    $user['role'],
                    $user['status'],
                    $profile_image,
                    $user_id
                );
            }
            $stmt->execute();
            
            // Handle trainer status
            if ($make_trainer && !$user['is_trainer']) {
                // Create trainer record
                $stmt = $conn->prepare("
                    INSERT INTO trainers 
                    (user_id, name, specialty, certification, hourly_rate, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $is_active = $user['status'] === 'active' ? 1 : 0;
                $stmt->bind_param(
                    "isssdi",
                    $user_id,
                    $user['name'],
                    $specialty,
                    $certification,
                    $hourly_rate,
                    $is_active
                );
                $stmt->execute();
            } elseif (!$make_trainer && $user['is_trainer']) {
                // Delete trainer record
                $stmt = $conn->prepare("DELETE FROM trainers WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            } elseif ($make_trainer && $user['is_trainer']) {
                // Update trainer record
                $stmt = $conn->prepare("
                    UPDATE trainers SET
                    name = ?,
                    specialty = ?,
                    certification = ?,
                    hourly_rate = ?,
                    is_active = ?,
                    updated_at = NOW()
                    WHERE user_id = ?
                ");
                $is_active = $user['status'] === 'active' ? 1 : 0;
                $stmt->bind_param(
                    "sssdii",
                    $user['name'],
                    $specialty,
                    $certification,
                    $hourly_rate,
                    $is_active,
                    $user_id
                );
                $stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "User updated successfully!";
            header("Location: manage_user.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Update error: " . $e->getMessage());
            $error = "Failed to update user: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    }
}

// Load trainer details if user is a trainer
if ($user['is_trainer']) {
    try {
        $stmt = $conn->prepare("
            SELECT specialty, certification, hourly_rate, bio
            FROM trainers
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $trainer_details = $result->fetch_assoc();
            $user['specialty'] = $trainer_details['specialty'];
            $user['certification'] = $trainer_details['certification'];
            $user['hourly_rate'] = $trainer_details['hourly_rate'];
            $user['bio'] = $trainer_details['bio'];
        }
    } catch (Exception $e) {
        error_log("Trainer details load error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - FitZone Admin</title>
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
        .trainer-fields {
            transition: all 0.3s;
            overflow: hidden;
        }
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
            <h1>Edit User</h1>
            <a href="manage_user.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Users
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm form-container">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Profile Image Section -->
                    <div class="image-preview-container">
                        <div class="profile-image-container">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../<?= htmlspecialchars($user['profile_image']) ?>" class="profile-image" id="profileImagePreview">
                            <?php else: ?>
                                <div class="profile-image-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="profile-image-upload" onclick="document.getElementById('profileImageInput').click()">
                                <i class="fas fa-camera me-1"></i> Change
                            </div>
                        </div>
                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" onchange="previewProfileImage(this)">
                        <small class="text-muted">Max size: 2MB (JPG, PNG, GIF)</small>
                    </div>
                    
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
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="make_trainer" id="makeTrainer" 
                                   <?= $user['is_trainer'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="makeTrainer">This user is a trainer</label>
                        </div>
                        
                        <div id="trainerFields" class="trainer-fields <?= !$user['is_trainer'] ? 'd-none' : '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Specialty</label>
                                    <input type="text" class="form-control" name="specialty" 
                                           value="<?= htmlspecialchars($user['specialty'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Certification</label>
                                    <input type="text" class="form-control" name="certification" 
                                           value="<?= htmlspecialchars($user['certification'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hourly Rate ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="hourly_rate" 
                                               min="0" step="0.01" value="<?= number_format($user['hourly_rate'] ?? 0, 2) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Section -->
                    <div class="form-section">
                        <h5><i class="fas fa-lock me-2"></i>Password</h5>
                        <p class="text-muted">Leave blank to keep current password</p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" id="newPassword">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('newPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword">
                                    <span class="input-group-text password-toggle" onclick="togglePassword('confirmPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update User
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
        
        // Show/hide trainer fields based on checkbox
        document.getElementById('makeTrainer').addEventListener('change', function() {
            const trainerFields = document.getElementById('trainerFields');
            if (this.checked) {
                trainerFields.classList.remove('d-none');
            } else {
                trainerFields.classList.add('d-none');
            }
        });
        
        // Preview profile image before upload
        function previewProfileImage(input) {
            const preview = document.getElementById('profileImagePreview');
            const placeholder = document.querySelector('.profile-image-placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (!preview) {
                        // Create new image element if it doesn't exist
                        const img = document.createElement('img');
                        img.id = 'profileImagePreview';
                        img.className = 'profile-image';
                        img.src = e.target.result;
                        
                        if (placeholder) {
                            placeholder.replaceWith(img);
                        } else {
                            const container = document.querySelector('.profile-image-container');
                            container.insertBefore(img, container.firstChild);
                        }
                    } else {
                        preview.src = e.target.result;
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
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
            
            // Format hourly rate to 2 decimal places
            document.querySelector('input[name="hourly_rate"]')?.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>