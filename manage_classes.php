<?php
require 'config/db.php';
require 'utils/SessionManager.php';

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

// Initialize class data with default values
$class = [
    'id' => '',
    'name' => '',
    'description' => '',
    'class_type' => 'regular',
    'trainer_id' => null,
    'schedule' => '',
    'duration_minutes' => 60,
    'capacity' => 20,
    'price' => 15.00,
    'status' => 'scheduled'
];

// Fetch active trainers for dropdown
$trainers = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM trainers WHERE is_active = TRUE ORDER BY name");
    $stmt->execute();
    $trainers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Trainers fetch error: " . $e->getMessage());
    $error = "Failed to load trainers list.";
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_class'])) {
    $class_id = (int)$_POST['id'];
    
    try {
        $conn->autocommit(false);
        
        // First delete appointments
        $stmt = $conn->prepare("DELETE FROM appointments WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        
        // Then delete the class
        $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['dashboard_success'] = "Class deleted successfully!";
        header("Location: admin_dashboard.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete error: " . $e->getMessage());
        $error = "Failed to delete class: " . $e->getMessage();
    } finally {
        $conn->autocommit(true);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class_type = $_POST['class_type'] ?? 'regular';
    $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;
    $schedule = $_POST['schedule'] ?? '';
    $duration = (int)($_POST['duration'] ?? 60);
    $capacity = (int)($_POST['capacity'] ?? 20);
    $price = (float)($_POST['price'] ?? 15.00);

    // Validation
    if (empty($name)) {
        $error = "Class name is required";
    } elseif (empty($schedule)) {
        $error = "Schedule date/time is required";
    } elseif (strtotime($schedule) < time()) {
        $error = "Schedule must be in the future";
    } elseif ($duration < 15 || $duration > 180) {
        $error = "Duration must be between 15 and 180 minutes";
    } elseif ($capacity < 1 || $capacity > 100) {
        $error = "Capacity must be between 1 and 100";
    } elseif ($price < 0) {
        $error = "Price cannot be negative";
    } else {
        try {
            $conn->autocommit(false);

            if ($action === 'create') {
                // Create new class
                $stmt = $conn->prepare("
                    INSERT INTO classes 
                    (name, description, class_type, trainer_id, schedule, 
                    duration_minutes, capacity, price, status, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW(), NOW())
                ");
                
                $stmt->bind_param(
                    "sssssiidi",
                    $name,
                    $description,
                    $class_type,
                    $trainer_id,
                    $schedule,
                    $duration,
                    $capacity,
                    $price,
                    $admin_id
                );
                
                $stmt->execute();
                $class_id = $conn->insert_id;
                
                $_SESSION['dashboard_success'] = "Class created successfully!";
                $conn->commit();
                header("Location: admin_dashboard.php");
                exit;
                
            } elseif ($action === 'update' && isset($_POST['id'])) {
                // Update existing class
                $class_id = (int)$_POST['id'];
                $stmt = $conn->prepare("
                    UPDATE classes SET
                    name = ?,
                    description = ?,
                    class_type = ?,
                    trainer_id = ?,
                    schedule = ?,
                    duration_minutes = ?,
                    capacity = ?,
                    price = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param(
                    "sssssiidi",
                    $name,
                    $description,
                    $class_type,
                    $trainer_id,
                    $schedule,
                    $duration,
                    $capacity,
                    $price,
                    $class_id
                );
                
                $stmt->execute();
                
                $_SESSION['dashboard_success'] = "Class updated successfully!";
                $conn->commit();
                header("Location: admin_dashboard.php");
                exit;
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Database error: " . $e->getMessage());
            $error = "Failed to save class. Error: " . $e->getMessage();
            
            // Keep form values on error
            $class = [
                'id' => $_POST['id'] ?? '',
                'name' => $name,
                'description' => $description,
                'class_type' => $class_type,
                'trainer_id' => $trainer_id,
                'schedule' => $schedule,
                'duration_minutes' => $duration,
                'capacity' => $capacity,
                'price' => $price,
                'status' => 'scheduled'
            ];
        } finally {
            $conn->autocommit(true);
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    // Load class for editing
    try {
        $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $class = $result->fetch_assoc();
            // Convert schedule to datetime-local format
            $class['schedule'] = date('Y-m-d\TH:i', strtotime($class['schedule']));
        } else {
            $error = "Class not found";
        }
    } catch (Exception $e) {
        error_log("Class load error: " . $e->getMessage());
        $error = "Failed to load class details.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($_GET['action']) && $_GET['action'] === 'edit' ? 'Edit' : 'Add' ?> Class - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; }
        .duration-input { max-width: 100px; }
        .capacity-input { max-width: 100px; }
        .price-input { max-width: 150px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= isset($_GET['action']) && $_GET['action'] === 'edit' ? 'Edit' : 'Add New' ?> Class</h1>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm form-container">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= isset($_GET['action']) ? $_GET['action'] : 'create' ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($class['id']) ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Class Name *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?= htmlspecialchars($class['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class Type *</label>
                            <select class="form-select" name="class_type" required>
                                <option value="regular" <?= $class['class_type'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                                <option value="advanced" <?= $class['class_type'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                                <option value="premium" <?= $class['class_type'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($class['description']) ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Trainer</label>
                            <select class="form-select" name="trainer_id">
                                <option value="">-- Select Trainer --</option>
                                <?php foreach ($trainers as $trainer): ?>
                                    <option value="<?= $trainer['id'] ?>" 
                                        <?= $class['trainer_id'] == $trainer['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($trainer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Schedule Date & Time *</label>
                            <input type="datetime-local" class="form-control" name="schedule" 
                                   value="<?= !empty($class['schedule']) ? htmlspecialchars($class['schedule']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Duration (minutes) *</label>
                            <input type="number" class="form-control duration-input" name="duration" 
                                   min="15" max="180" step="15" value="<?= $class['duration_minutes'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Capacity *</label>
                            <input type="number" class="form-control capacity-input" name="capacity" 
                                   min="1" max="100" value="<?= $class['capacity'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control price-input" name="price" 
                                       min="0" step="0.01" value="<?= number_format($class['price'], 2) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> 
                            <?= isset($_GET['action']) && $_GET['action'] === 'edit' ? 'Update' : 'Create' ?> Class
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        
                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit'): ?>
                        <button type="button" class="btn btn-danger ms-auto" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash-alt me-1"></i> Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'edit'): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this class? This action cannot be undone and will also delete all associated appointments.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $class['id'] ?>">
                        <input type="hidden" name="delete_class" value="1">
                        <button type="submit" class="btn btn-danger">Delete Class</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum datetime for schedule (current time)
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.querySelector('input[name="schedule"]').min = now.toISOString().slice(0, 16);
            
            // Format price to 2 decimal places
            document.querySelector('input[name="price"]').addEventListener('blur', function() {
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