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

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_appointment'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        
        try {
            $conn->autocommit(false);
            
            // Delete appointment
            $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "Appointment deleted successfully!";
            header("Location: manage_appointment.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Delete error: " . $e->getMessage());
            $error = "Failed to delete appointment: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    } elseif (isset($_POST['update_status'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $appointment_id);
            $stmt->execute();
            
            $_SESSION['dashboard_success'] = "Appointment status updated successfully!";
            header("Location: manage_appointment.php");
            exit;
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = "Failed to update appointment status: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$query = "
    SELECT a.*, 
           u.name AS user_name, 
           u.email AS user_email,
           c.name AS class_name,
           t.name AS trainer_name
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    JOIN classes c ON a.class_id = c.id
    LEFT JOIN trainers t ON c.trainer_id = t.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($status)) {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($class_id)) {
    $query .= " AND a.class_id = ?";
    $params[] = $class_id;
    $types .= 'i';
}

if (!empty($user_id)) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

if (!empty($date_from)) {
    $query .= " AND a.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND a.date <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

$query .= " ORDER BY a.date DESC";

// Get classes for filter dropdown
$classes = $conn->query("SELECT id, name FROM classes ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get users for filter dropdown
$users = $conn->query("SELECT id, name FROM users WHERE role = 'customer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Execute main query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .status-booked { background-color: #198754; }
        .status-cancelled { background-color: #dc3545; }
        .status-attended { background-color: #0d6efd; }
        .form-container { max-width: 1800px; margin: 0 auto; }
        .filter-card { margin-bottom: 20px; }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Appointments</h1>
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
        <div class="card filter-card shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Member or class" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="booked" <?= $status === 'booked' ? 'selected' : '' ?>>Booked</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="attended" <?= $status === 'attended' ? 'selected' : '' ?>>Attended</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Class</label>
                        <select class="form-select" name="class_id">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Member</label>
                        <select class="form-select" name="user_id">
                            <option value="">All Members</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <div class="col-md-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                        <a href="manage_appointment.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Member</th>
                                <th>Class</th>
                                <th>Trainer</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No appointments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= $appt['id'] ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($appt['user_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($appt['user_email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($appt['class_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['trainer_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($appt['date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $appt['status'] ?>">
                                            <?= ucfirst($appt['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" 
                                                        onchange="this.form.submit()" style="width: 120px;">
                                                    <option value="booked" <?= $appt['status'] === 'booked' ? 'selected' : '' ?>>Booked</option>
                                                    <option value="cancelled" <?= $appt['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    <option value="attended" <?= $appt['status'] === 'attended' ? 'selected' : '' ?>>Attended</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $appt['id'] ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Delete Confirmation Modal -->
                                <div class="modal fade" id="deleteModal<?= $appt['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this appointment?</p>
                                                <p><strong>Member:</strong> <?= htmlspecialchars($appt['user_name']) ?></p>
                                                <p><strong>Class:</strong> <?= htmlspecialchars($appt['class_name']) ?></p>
                                                <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($appt['date'])) ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST">
                                                    <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                                                    <button type="submit" name="delete_appointment" class="btn btn-danger">
                                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
            </div>
        </div>
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