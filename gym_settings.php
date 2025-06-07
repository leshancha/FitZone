<?php
// gym_settings.php
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
$success = '';
$error = '';

// Fetch current settings
$settings_query = $conn->query("SELECT * FROM gym_info LIMIT 1");
$settings = $settings_query->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->autocommit(false);
        
        $gym_name = trim($_POST['gym_name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $zip_code = trim($_POST['zip_code']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $opening_hours = trim($_POST['opening_hours']);
        $facebook_url = trim($_POST['facebook_url']);
        $twitter_url = trim($_POST['twitter_url']);
        $instagram_url = trim($_POST['instagram_url']);
        $youtube_url = trim($_POST['youtube_url']);
        
        // Basic validation
        if (empty($gym_name) || empty($email) || empty($phone)) {
            throw new Exception("Required fields (Gym Name, Email, Phone) cannot be empty");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        // Handle file upload (logo)
        $logo_url = $settings['logo_url'] ?? '';
        // In the file upload section of gym_settings.php
        if (!empty($_FILES['logo']['name'])) {
            $upload_dir = __DIR__.'/../assets/logos/'; // Changed to assets/logos
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['logo']['tmp_name']);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Only JPG, PNG, GIF, and SVG files are allowed for logo");
            }
            
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) { // 2MB max
                throw new Exception("Logo file size must be less than 2MB");
            }
            
            $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $file_name = 'logo.' . $file_ext; // Simple filename
            $file_path = $upload_dir . $file_name;
            
            // Delete old logo if it exists
            $old_files = glob($upload_dir . 'logo.*');
            foreach ($old_files as $old_file) {
                if (is_file($old_file)) {
                    unlink($old_file);
                }
            }
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $file_path)) {
                $logo_url = 'assets/logos/' . $file_name;
            } else {
                throw new Exception("Failed to upload logo file");
            }
        }
        
            // Update or insert settings
            if ($settings) {
                $stmt = $conn->prepare("
                    UPDATE gym_info SET 
                        gym_name = ?, 
                        address = ?, 
                        city = ?, 
                        state = ?, 
                        zip_code = ?, 
                        phone = ?, 
                        email = ?, 
                        opening_hours = ?, 
                        facebook_url = ?, 
                        twitter_url = ?, 
                        instagram_url = ?, 
                        youtube_url = ?,
                        logo_url = ?
                    WHERE id = ?
                ");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $bind_result = $stmt->bind_param(
                    "sssssssssssssi", 
                    $gym_name, $address, $city, $state, $zip_code, 
                    $phone, $email, $opening_hours, 
                    $facebook_url, $twitter_url, $instagram_url, $youtube_url,
                    $logo_url,
                    $settings['id']
                );
                
                if (!$bind_result) {
                    throw new Exception("Bind failed: " . $stmt->error);
                }
            } else {
                // INSERT statement remains the same (no ID parameter)
                $stmt = $conn->prepare("
                    INSERT INTO gym_info (
                        gym_name, address, city, state, zip_code, 
                        phone, email, opening_hours, 
                        facebook_url, twitter_url, instagram_url, youtube_url,
                        logo_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssss", 
                    $gym_name, $address, $city, $state, $zip_code, 
                    $phone, $email, $opening_hours, 
                    $facebook_url, $twitter_url, $instagram_url, $youtube_url,
                    $logo_url
                );
            }
        
        $stmt->execute();
        $conn->commit();
        
        // Refresh settings
        $settings_query = $conn->query("SELECT * FROM gym_info LIMIT 1");
        $settings = $settings_query->fetch_assoc();
        
        $success = "Gym settings updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    } finally {
        $conn->autocommit(true);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Settings - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .logo-preview {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .social-icon {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .form-section {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-section h5 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gym Settings</h1>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label for="gym_name" class="form-label">Gym Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="gym_name" name="gym_name" 
                                           value="<?= htmlspecialchars($settings['gym_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?= htmlspecialchars($settings['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="state" class="form-label">State/Province</label>
                                        <input type="text" class="form-control" id="state" name="state" 
                                               value="<?= htmlspecialchars($settings['state'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="zip_code" class="form-label">ZIP/Postal Code</label>
                                    <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                           value="<?= htmlspecialchars($settings['zip_code'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Contact Information Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-phone-alt me-2"></i>Contact Information</h5>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($settings['phone'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($settings['email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="opening_hours" class="form-label">Opening Hours</label>
                                    <textarea class="form-control" id="opening_hours" name="opening_hours" rows="3"><?= htmlspecialchars($settings['opening_hours'] ?? '') ?></textarea>
                                    <small class="text-muted">Use new lines for different days (e.g., Monday-Friday: 6AM-10PM)</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Logo Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-image me-2"></i>Gym Logo</h5>
                                
                                <?php if (!empty($settings['logo_url'])): ?>
                                    <div>
                                        <img src="../<?= htmlspecialchars($settings['logo_url']) ?>" class="logo-preview" id="logoPreview">
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        No logo uploaded yet
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Upload New Logo</label>
                                    <input class="form-control" type="file" id="logo" name="logo" accept="image/*">
                                    <small class="text-muted">Recommended size: 300x300px, max 2MB</small>
                                </div>
                            </div>
                            
                            <!-- Social Media Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-share-alt me-2"></i>Social Media</h5>
                                
                                <div class="mb-3">
                                    <label for="facebook_url" class="form-label">
                                        <span class="social-icon bg-primary text-white"><i class="fab fa-facebook-f"></i></span>
                                        Facebook URL
                                    </label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                           value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="twitter_url" class="form-label">
                                        <span class="social-icon bg-info text-white"><i class="fab fa-twitter"></i></span>
                                        Twitter URL
                                    </label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                           value="<?= htmlspecialchars($settings['twitter_url'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instagram_url" class="form-label">
                                        <span class="social-icon bg-gradient-instagram text-white"><i class="fab fa-instagram"></i></span>
                                        Instagram URL
                                    </label>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                           value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="youtube_url" class="form-label">
                                        <span class="social-icon bg-danger text-white"><i class="fab fa-youtube"></i></span>
                                        YouTube URL
                                    </label>
                                    <input type="url" class="form-control" id="youtube_url" name="youtube_url" 
                                           value="<?= htmlspecialchars($settings['youtube_url'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mb-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview logo before upload
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('logoPreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'logoPreview';
                        preview.className = 'logo-preview';
                        document.querySelector('.form-section h5').insertAdjacentElement('afterend', preview);
                    }
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Instagram gradient background
        document.addEventListener('DOMContentLoaded', function() {
            const instagramIcons = document.querySelectorAll('.bg-gradient-instagram');
            instagramIcons.forEach(icon => {
                icon.style.background = 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%)';
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn = null;
?>