<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$error = '';
$success = '';

// File upload settings
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$upload_dir = 'uploads/profiles/';
$max_file_size = 2 * 1024 * 1024; // 2MB

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch user data
$user = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("User data error: " . $e->getMessage());
    $error = "Failed to load user data.";
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $profile_image = $user['profile_image'] ?? null;
    
    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $file_type = $_FILES['profile_image']['type'];
        $file_size = $_FILES['profile_image']['size'];
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, PNG, and GIF images are allowed.";
        }
        // Validate file size
        elseif ($file_size > $max_file_size) {
            $error = "Image size must be less than 2MB";
        } else {
            // Generate unique filename
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                // Delete old profile image if it's not the default
                if ($profile_image && $profile_image != 'uploads/profiles/default.png' && file_exists($profile_image)) {
                    unlink($profile_image);
                }
                $profile_image = $target_path;
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    }

    if (empty($error)) {
        try {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_image = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $profile_image, $user_id);
            $stmt->execute();
            
            // Update session data
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['profile_image'] = $profile_image;
            
            $success = "Profile updated successfully!";
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = "Failed to update profile. Email may already be in use.";
        }
    }
}

// Handle password changes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        try {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if (!password_verify($current_password, $user_data['password'])) {
                $error = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                $stmt->execute();
                
                $success = "Password changed successfully!";
            }
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = "Failed to change password.";
        }
    }
}

// Handle notification preferences (for members)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notifications']) && $role === 'customer') {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ? WHERE id = ?");
        $stmt->bind_param("iii", $email_notifications, $sms_notifications, $user_id);
        $stmt->execute();
        
        $success = "Notification preferences updated!";
    } catch (Exception $e) {
        error_log("Notification update error: " . $e->getMessage());
        $error = "Failed to update notification preferences.";
    }
}

// Handle staff-specific settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff_settings']) && ($role === 'staff' || $role === 'admin')) {
    $availability = isset($_POST['availability']) ? 1 : 0;
    $max_classes = $_POST['max_classes'] ?? 5;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET is_available = ?, max_classes = ? WHERE id = ?");
        $stmt->bind_param("iii", $availability, $max_classes, $user_id);
        $stmt->execute();
        
        $success = "Staff settings updated!";
    } catch (Exception $e) {
        error_log("Staff settings error: " . $e->getMessage());
        $error = "Failed to update staff settings.";
    }
}

// Handle admin-specific settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin_settings']) && $role === 'admin') {
    $system_maintenance = isset($_POST['system_maintenance']) ? 1 : 0;
    $registration_enabled = isset($_POST['registration_enabled']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE system_settings SET maintenance_mode = ?, registration_enabled = ?");
        $stmt->bind_param("ii", $system_maintenance, $registration_enabled);
        $stmt->execute();
        
        $success = "Admin settings updated!";
    } catch (Exception $e) {
        error_log("Admin settings error: " . $e->getMessage());
        $error = "Failed to update admin settings.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .settings-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .profile-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            display: block;
            margin: 0 auto 15px;
        }
        .upload-btn {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .upload-btn input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .image-upload-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Settings</h1>
            <span class="badge bg-<?php 
                echo $role === 'admin' ? 'danger' : 
                     ($role === 'staff' ? 'warning text-dark' : 'success');
            ?>">
                <?php echo ucfirst($role); ?>
            </span>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <nav class="nav flex-column nav-pills">
                            <a class="nav-link active" data-bs-toggle="pill" href="#profile">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#password">
                                <i class="fas fa-lock me-2"></i> Password
                            </a>
                            <?php if ($role === 'customer'): ?>
                                <a class="nav-link" data-bs-toggle="pill" href="#notifications">
                                    <i class="fas fa-bell me-2"></i> Notifications
                                </a>
                            <?php endif; ?>
                            <?php if ($role === 'staff' || $role === 'admin'): ?>
                                <a class="nav-link" data-bs-toggle="pill" href="#staff-settings">
                                    <i class="fas fa-user-tie me-2"></i> Staff Settings
                                </a>
                            <?php endif; ?>
                            <?php if ($role === 'admin'): ?>
                                <a class="nav-link" data-bs-toggle="pill" href="#admin-settings">
                                    <i class="fas fa-cog me-2"></i> Admin Settings
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="image-upload-container">
                                        <img id="profilePreview" src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/images/default-profile.png'); ?>" 
                                             class="profile-preview" alt="Profile preview">
                                        <label class="upload-btn btn btn-outline-primary mt-3">
                                            <i class="fas fa-camera me-2"></i> Change Profile Image
                                            <input type="file" id="profileImage" name="profile_image" accept="image/*" class="d-none">
                                        </label>
                                        <small class="d-block text-muted mt-1">Max 2MB (JPG, PNG, GIF)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Tab -->
                    <div class="tab-pane fade" id="password">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key me-1"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifications Tab (Members only) -->
                    <?php if ($role === 'customer'): ?>
                    <div class="tab-pane fade" id="notifications">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                   <?php echo ($user['email_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications"
                                                   <?php echo ($user['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Staff Settings Tab -->
                    <?php if ($role === 'staff' || $role === 'admin'): ?>
                    <div class="tab-pane fade" id="staff-settings">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Staff Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="availability" name="availability"
                                                   <?php echo ($user['is_available'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="availability">Available for Classes</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="max_classes" class="form-label">Maximum Weekly Classes</label>
                                        <input type="number" class="form-control" id="max_classes" name="max_classes" 
                                               min="1" max="20" value="<?php echo $user['max_classes'] ?? 5; ?>">
                                    </div>
                                    <button type="submit" name="update_staff_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Admin Settings Tab -->
                    <?php if ($role === 'admin'): ?>
                    <div class="tab-pane fade" id="admin-settings">
                        <div class="card shadow-sm settings-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Admin Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="system_maintenance" name="system_maintenance"
                                                   <?php echo ($system_settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="system_maintenance">Maintenance Mode</label>
                                        </div>
                                        <small class="text-muted">When enabled, only admins can access the system</small>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled"
                                                   <?php echo ($system_settings['registration_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="registration_enabled">New Registrations</label>
                                        </div>
                                        <small class="text-muted">Allow new users to register accounts</small>
                                    </div>
                                    <button type="submit" name="update_admin_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview selected profile image
        document.getElementById('profileImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profilePreview').src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Initialize tab functionality
        var triggerTabList = [].slice.call(document.querySelectorAll('a[data-bs-toggle="pill"]'));
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl);
            
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });
    </script>
</body>
</html>