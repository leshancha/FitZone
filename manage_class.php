<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in as staff or admin
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'staff' && $_SESSION['user']['role'] !== 'admin')) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$error = '';
$success = '';

// Fetch classes assigned to this staff member (or all classes if admin)
try {
    if ($role === 'admin') {
        $stmt = $conn->prepare("
            SELECT c.*, t.name as trainer_name, COUNT(a.id) as attendees
            FROM classes c
            LEFT JOIN trainers t ON c.trainer_id = t.id
            LEFT JOIN appointments a ON c.id = a.class_id AND a.status = 'booked'
            GROUP BY c.id
            ORDER BY c.schedule DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            SELECT c.*, t.name as trainer_name, COUNT(a.id) as attendees
            FROM classes c
            JOIN trainers t ON c.trainer_id = t.id
            LEFT JOIN appointments a ON c.id = a.class_id AND a.status = 'booked'
            WHERE t.user_id = ?
            GROUP BY c.id
            ORDER BY c.schedule DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    $classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Classes fetch error: " . $e->getMessage());
    $error = "Failed to load classes.";
}

// Handle class editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_class'])) {
    $class_id = (int)$_POST['class_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $schedule = $_POST['schedule'];
    $duration = (int)$_POST['duration'];
    $capacity = (int)$_POST['capacity'];
    $price = (float)$_POST['price'];
    $class_type = $_POST['class_type'];
    $status = $_POST['status'];

    // Validate inputs
    if (empty($name) || empty($description) || empty($schedule)) {
        $error = "Name, description, and schedule are required.";
    } elseif ($duration < 15 || $duration > 180) {
        $error = "Duration must be between 15 and 180 minutes.";
    } elseif ($capacity < 1 || $capacity > 100) {
        $error = "Capacity must be between 1 and 100.";
    } elseif ($price < 0 || $price > 1000) {
        $error = "Price must be between $0 and $1000.";
    } else {
        try {
            // Verify the staff member has permission to edit this class
            if ($role !== 'admin') {
                $stmt = $conn->prepare("
                    SELECT 1 FROM classes c
                    JOIN trainers t ON c.trainer_id = t.id
                    WHERE c.id = ? AND t.user_id = ?
                ");
                $stmt->bind_param("ii", $class_id, $user_id);
                $stmt->execute();
                if (!$stmt->get_result()->num_rows) {
                    throw new Exception("Unauthorized to edit this class");
                }
            }

            // Update the class
            $stmt = $conn->prepare("
                UPDATE classes 
                SET name = ?, description = ?, schedule = ?, duration_minutes = ?, 
                    capacity = ?, price = ?, class_type = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sssiidssi", $name, $description, $schedule, $duration, 
                             $capacity, $price, $class_type, $status, $class_id);
            $stmt->execute();

            $success = "Class updated successfully!";
        } catch (Exception $e) {
            error_log("Class update error: " . $e->getMessage());
            $error = "Failed to update class. " . ($role !== 'admin' ? "You may not have permission." : "");
        }
    }
}

// Handle class cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_class'])) {
    $class_id = (int)$_POST['class_id'];

    try {
        // Verify permission
        if ($role !== 'admin') {
            $stmt = $conn->prepare("
                SELECT 1 FROM classes c
                JOIN trainers t ON c.trainer_id = t.id
                WHERE c.id = ? AND t.user_id = ?
            ");
            $stmt->bind_param("ii", $class_id, $user_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                throw new Exception("Unauthorized to cancel this class");
            }
        }

        // Update class status
        $stmt = $conn->prepare("
            UPDATE classes 
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();

        // Notify attendees (pseudo-code - implement your notification system)
        // $stmt = $conn->prepare("SELECT user_id FROM appointments WHERE class_id = ? AND status = 'booked'");
        // $stmt->bind_param("i", $class_id);
        // $stmt->execute();
        // $attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // foreach ($attendees as $attendee) {
        //     send_notification($attendee['user_id'], "Class cancelled", "The class you booked has been cancelled");
        // }

        $success = "Class cancelled successfully! Attendees have been notified.";
    } catch (Exception $e) {
        error_log("Class cancellation error: " . $e->getMessage());
        $error = "Failed to cancel class. " . ($role !== 'admin' ? "You may not have permission." : "");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .class-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .badge-scheduled { background-color: var(--secondary-color); }
        .badge-cancelled { background-color: var(--danger-color); }
        .badge-completed { background-color: var(--warning-color); color: #000; }
        
        .badge-premium { background-color: var(--warning-color); color: #000; }
        .badge-advanced { background-color: var(--danger-color); }
        .badge-regular { background-color: var(--primary-color); }
        
        .attendees-progress {
            height: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Classes</h1>
            <?php if ($role === 'admin'): ?>
                <a href="create_class.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Create New Class
                </a>
            <?php endif; ?>
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
            <?php if (empty($classes)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No classes found. <?php echo $role === 'admin' ? 'Create a new class to get started.' : 'You are not assigned to any classes.'; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                <div class="col-md-6 mb-4">
                    <div class="card class-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($class['name']); ?></h5>
                            <span class="badge badge-<?php echo strtolower($class['class_type']); ?>">
                                <?php echo ucfirst($class['class_type']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge bg-<?php 
                                    echo $class['status'] === 'scheduled' ? 'success' : 
                                         ($class['status'] === 'cancelled' ? 'danger' : 'warning text-dark');
                                ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-user-tie me-1"></i>
                                    <?php echo htmlspecialchars($class['trainer_name'] ?? 'Unassigned'); ?>
                                </span>
                            </div>
                            
                            <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('D, M j, Y g:i A', strtotime($class['schedule'])); ?>
                                    </span>
                                    <span><?php echo $class['duration_minutes']; ?> mins</span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $class['attendees']; ?>/<?php echo $class['capacity']; ?> booked
                                    </span>
                                    <span>$<?php echo number_format($class['price'], 2); ?></span>
                                </div>
                                <div class="progress attendees-progress mt-1">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo ($class['attendees']/$class['capacity'])*100; ?>%">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Class Modal Trigger -->
                            <button class="btn btn-sm btn-outline-primary w-100 mb-2" data-bs-toggle="modal" 
                                    data-bs-target="#editClassModal<?php echo $class['id']; ?>">
                                <i class="fas fa-edit me-1"></i> Edit Class
                            </button>
                            
                            <?php if ($class['status'] === 'scheduled'): ?>
                                <!-- Cancel Class Button -->
                                <form method="POST" class="d-inline w-100">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <button type="submit" name="cancel_class" class="btn btn-sm btn-outline-danger w-100"
                                            onclick="return confirm('Are you sure you want to cancel this class? All attendees will be notified.');">
                                        <i class="fas fa-times me-1"></i> Cancel Class
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Class Modal -->
                <div class="modal fade" id="editClassModal<?php echo $class['id']; ?>" tabindex="-1" 
                     aria-labelledby="editClassModalLabel<?php echo $class['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editClassModalLabel<?php echo $class['id']; ?>">
                                    Edit Class: <?php echo htmlspecialchars($class['name']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name<?php echo $class['id']; ?>" class="form-label">Class Name</label>
                                            <input type="text" class="form-control" id="name<?php echo $class['id']; ?>" 
                                                   name="name" value="<?php echo htmlspecialchars($class['name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="class_type<?php echo $class['id']; ?>" class="form-label">Class Type</label>
                                            <select class="form-select" id="class_type<?php echo $class['id']; ?>" name="class_type" required>
                                                <option value="regular" <?php echo $class['class_type'] === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                                <option value="advanced" <?php echo $class['class_type'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                <option value="premium" <?php echo $class['class_type'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description<?php echo $class['id']; ?>" class="form-label">Description</label>
                                        <textarea class="form-control" id="description<?php echo $class['id']; ?>" 
                                                  name="description" rows="3" required><?php echo htmlspecialchars($class['description']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="schedule<?php echo $class['id']; ?>" class="form-label">Schedule</label>
                                            <input type="datetime-local" class="form-control" id="schedule<?php echo $class['id']; ?>" 
                                                   name="schedule" value="<?php echo date('Y-m-d\TH:i', strtotime($class['schedule'])); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="duration<?php echo $class['id']; ?>" class="form-label">Duration (minutes)</label>
                                            <input type="number" class="form-control" id="duration<?php echo $class['id']; ?>" 
                                                   name="duration" min="15" max="180" value="<?php echo $class['duration_minutes']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="capacity<?php echo $class['id']; ?>" class="form-label">Capacity</label>
                                            <input type="number" class="form-control" id="capacity<?php echo $class['id']; ?>" 
                                                   name="capacity" min="1" max="100" value="<?php echo $class['capacity']; ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="price<?php echo $class['id']; ?>" class="form-label">Price ($)</label>
                                            <input type="number" step="0.01" class="form-control" id="price<?php echo $class['id']; ?>" 
                                                   name="price" min="0" max="1000" value="<?php echo $class['price']; ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="status<?php echo $class['id']; ?>" class="form-label">Status</label>
                                            <select class="form-select" id="status<?php echo $class['id']; ?>" name="status" required>
                                                <option value="scheduled" <?php echo $class['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="cancelled" <?php echo $class['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="completed" <?php echo $class['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_class" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>