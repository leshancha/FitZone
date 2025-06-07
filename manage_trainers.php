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

// Handle trainer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_trainer'])) {
        $trainer_id = (int)$_POST['trainer_id'];
        
        try {
            $conn->autocommit(false);
            
            // Check if trainer has upcoming classes
            $stmt = $conn->prepare("
                SELECT COUNT(*) as class_count 
                FROM classes 
                WHERE trainer_id = ? AND schedule > NOW()
            ");
            $stmt->bind_param("i", $trainer_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['class_count'] > 0) {
                throw new Exception("Cannot delete trainer with upcoming classes");
            }
            
            // Get user_id before deletion
            $stmt = $conn->prepare("SELECT user_id FROM trainers WHERE id = ?");
            $stmt->bind_param("i", $trainer_id);
            $stmt->execute();
            $trainer = $stmt->get_result()->fetch_assoc();
            $user_id = $trainer['user_id'];
            
            // Delete trainer
            $stmt = $conn->prepare("DELETE FROM trainers WHERE id = ?");
            $stmt->bind_param("i", $trainer_id);
            $stmt->execute();
            
            // Delete associated user account if exists
            if ($user_id) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "Trainer deleted successfully!";
            header("Location: manage_trainers.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Delete error: " . $e->getMessage());
            $error = "Failed to delete trainer: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    } elseif (isset($_POST['update_status'])) {
        $trainer_id = (int)$_POST['trainer_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("UPDATE trainers SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $is_active, $trainer_id);
            $stmt->execute();
            
            // Also update user status if linked
            $stmt = $conn->prepare("
                UPDATE users u
                JOIN trainers t ON u.id = t.user_id
                SET u.status = ?
                WHERE t.id = ?
            ");
            $status = $is_active ? 'active' : 'inactive';
            $stmt->bind_param("si", $status, $trainer_id);
            $stmt->execute();
            
            $_SESSION['dashboard_success'] = "Trainer status updated successfully!";
            header("Location: manage_trainers.php");
            exit;
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = "Failed to update trainer status: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$specialty = $_GET['specialty'] ?? '';

// Build query with filters
$query = "
    SELECT t.*, 
           u.email as user_email,
           u.status as user_status,
           (SELECT COUNT(*) FROM classes WHERE trainer_id = t.id AND schedule > NOW()) as upcoming_classes
    FROM trainers t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (t.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($status)) {
    if ($status === 'active') {
        $query .= " AND t.is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND t.is_active = 0";
    }
}

if (!empty($specialty)) {
    $query .= " AND t.specialty LIKE ?";
    $params[] = "%$specialty%";
    $types .= 's';
}

$query .= " ORDER BY t.name ASC";

// Execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$trainers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique specialties for filter dropdown
$specialties = $conn->query("
    SELECT DISTINCT specialty 
    FROM trainers 
    WHERE specialty IS NOT NULL AND specialty != ''
    ORDER BY specialty
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .trainer-card {
            transition: all 0.3s;
        }
        .trainer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .status-active { background-color: #198754; }
        .status-inactive { background-color: #6c757d; }
        .specialty-badge {
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
        }
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Trainers</h1>
            <a href="create_staff.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-1"></i> Add New Trainer
            </a>
        </div>

        <?php if (isset($_SESSION['dashboard_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['dashboard_success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['dashboard_success']); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Name or email" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Specialty</label>
                        <select class="form-select" name="specialty">
                            <option value="">All Specialties</option>
                            <?php foreach ($specialties as $spec): ?>
                                <option value="<?= htmlspecialchars($spec['specialty']) ?>" 
                                    <?= $specialty === $spec['specialty'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec['specialty']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Trainers List -->
        <div class="row">
            <?php if (empty($trainers)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No trainers found matching your criteria.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($trainers as $trainer): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card trainer-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($trainer['name']) ?></h5>
                            <span class="status-badge status-<?= $trainer['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $trainer['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?= $trainer['profile_image'] ? htmlspecialchars($trainer['profile_image']) : 'https://www.creativefabrica.com/wp-content/uploads/2021/07/18/Mentor-trainer-icon-Graphics-14881824-2-580x386.jpg' ?>" 
                                     class="avatar me-3" alt="<?= htmlspecialchars($trainer['name']) ?>">
                                <div>
                                    <?php if ($trainer['user_email']): ?>
                                        <div class="text-muted"><?= htmlspecialchars($trainer['user_email']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($trainer['specialty']): ?>
                                        <div class="mt-1">
                                            <span class="badge specialty-badge">
                                                <?= htmlspecialchars($trainer['specialty']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <?php if ($trainer['certification']): ?>
                                    <div><strong>Certification:</strong> <?= htmlspecialchars($trainer['certification']) ?></div>
                                <?php endif; ?>
                                <?php if ($trainer['hourly_rate']): ?>
                                    <div><strong>Rate:</strong> $<?= number_format($trainer['hourly_rate'], 2) ?>/hr</div>
                                <?php endif; ?>
                                <div><strong>Upcoming Classes:</strong> <?= $trainer['upcoming_classes'] ?></div>
                            </div>
                            
                            <?php if ($trainer['bio']): ?>
                                <div class="mb-3">
                                    <p class="text-muted"><?= htmlspecialchars($trainer['bio']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <a href="edit_user.php?id=<?= $trainer['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="trainer_id" value="<?= $trainer['id'] ?>">
                                    <div class="form-check form-switch d-inline-flex align-items-center">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive<?= $trainer['id'] ?>" 
                                               <?= $trainer['is_active'] ? 'checked' : '' ?> 
                                               onchange="this.form.submit()">
                                        <input type="hidden" name="update_status" value="1">
                                        <label class="form-check-label ms-2" for="isActive<?= $trainer['id'] ?>">
                                            <?= $trainer['is_active'] ? 'Active' : 'Inactive' ?>
                                        </label>
                                    </div>
                                </form>
                                
                                <button class="btn btn-sm btn-outline-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal<?= $trainer['id'] ?>">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal<?= $trainer['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Deletion</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this trainer?</p>
                                <p><strong>Trainer:</strong> <?= htmlspecialchars($trainer['name']) ?></p>
                                <?php if ($trainer['upcoming_classes'] > 0): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        This trainer has <?= $trainer['upcoming_classes'] ?> upcoming classes.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="POST">
                                    <input type="hidden" name="trainer_id" value="<?= $trainer['id'] ?>">
                                    <button type="submit" name="delete_trainer" class="btn btn-danger">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>