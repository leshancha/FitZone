<?php
require 'db.php';

$error = '';
$success = '';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$upload_dir = 'uploads/profiles/';

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $profile_image = null;

    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $file_type = $_FILES['profile_image']['type'];
        $file_size = $_FILES['profile_image']['size'];
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, PNG, and GIF images are allowed.";
        }
        // Validate file size (max 2MB)
        elseif ($file_size > 2097152) {
            $error = "Image size must be less than 2MB";
        } else {
            // Generate unique filename
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                $profile_image = $target_path;
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    }

    if (empty($error)) {
        try {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Email already exists.";
            } else {
                // Set default image if none uploaded
                if (!$profile_image) {
                    $profile_image = 'uploads/profiles/default.png';
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO users (name, email, password, profile_image) 
                    VALUES (?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$name, $email, $password, $profile_image])) {
                    $success = "Registration successful! You can now login.";
                    // Clear form on success
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            // Delete uploaded file if database insert fails
            if ($profile_image && $profile_image != 'uploads/profiles/default.png') {
                @unlink($profile_image);
            }
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-preview {
            width: 120px;
            height: 120px;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4">Create Account</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <img id="profilePreview" src="assets/images/default-profile.png" class="profile-preview" alt="Profile preview">
                                <label class="upload-btn btn btn-outline-primary mt-2">
                                    <i class="fas fa-camera me-2"></i> Choose Image
                                    <input type="file" id="profileImage" name="profile_image" accept="image/*" class="d-none">
                                </label>
                                <small class="d-block text-muted mt-1">Max 2MB (JPG, PNG, GIF)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
                        </form>

                        <p class="mt-3 text-center">Already have an account? <a href="login.php">Login here</a></p>
                    </div>
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
    </script>
</body>
</html>