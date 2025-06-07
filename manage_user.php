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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        try {
            $conn->autocommit(false);
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("User not found");
            }
            
            // First delete dependent records (appointments, etc.)
            $stmt = $conn->prepare("DELETE FROM appointments WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Then delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "User deleted successfully!";
            header("Location: manage_user.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Delete error: " . $e->getMessage());
            $error = "Failed to delete user: " . $e->getMessage();
        } finally {
            $conn->autocommit(true);
        }
    } elseif (isset($_POST['update_status'])) {
        $user_id = (int)$_POST['user_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $user_id);
            $stmt->execute();
            
            $_SESSION['dashboard_success'] = "User status updated successfully!";
            header("Location: manage_user.php");
            exit;
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = "Failed to update user status: " . $e->getMessage();
        }
    }
}

// Get all users with optional filters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($role)) {
    $query .= " AND role = ?";
    $params[] = $role;
    $types .= 's';
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - FitZone Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        .badge-role {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .badge-customer { background-color: #6c757d; }
        .badge-staff { background-color: #0d6efd; }
        .badge-trainer { background-color: #198754; }
        .badge-admin { background-color: #dc3545; }
        .status-active { color: #198754; }
        .status-inactive { color: #6c757d; }
        .status-locked { color: #dc3545; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Members</h1>
            <a href="create_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-1"></i> Add New Member
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
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Name or email" 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="trainer" <?= $role === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked</option>
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

        <!-- Users Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No members found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                                <?php if ($user['email_verified_at']): ?>
                                                    <small class="text-success d-block">
                                                        <i class="fas fa-check-circle"></i> Verified
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge badge-role badge-<?= $user['role'] ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?= $user['status'] ?>">
                                            <i class="fas fa-circle me-1"></i>
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" 
                                                        onchange="this.form.submit()" style="width: 120px;">
                                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    <option value="locked" <?= $user['status'] === 'locked' ? 'selected' : '' ?>>Locked</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $user['id'] ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Delete Confirmation Modal -->
                                <div class="modal fade" id="deleteModal<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this member? This action cannot be undone.</p>
                                                <p><strong>Member:</strong> <?= htmlspecialchars($user['name']) ?></p>
                                                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-danger">
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
                
                <!-- Pagination would go here -->
                <nav aria-label="Page navigation">
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