<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not staff
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$staff_id = $_SESSION['user']['id'];
$success = '';
$error = '';

// Handle query resolution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resolve_query'])) {
    $query_id = $_POST['query_id'];
    $response = trim($_POST['response']);
    
    if (empty($response)) {
        $error = "Response cannot be empty";
    } else {
        try {
            $conn->autocommit(false);
            
            $stmt = $conn->prepare("
                UPDATE queries 
                SET status = 'resolved', 
                    response = ?, 
                    resolved_by = ?, 
                    resolved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $response, $staff_id, $query_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "Query resolved successfully!";
            header("Location: staff_dashboard.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Query resolution error: " . $e->getMessage());
            $error = "Failed to resolve query. Please try again.";
        } finally {
            $conn->autocommit(true);
        }
    }
}

// Fetch pending queries
$pending_queries = [];
try {
    $stmt = $conn->prepare("
        SELECT q.*, u.name as user_name 
        FROM queries q
        JOIN users u ON q.user_id = u.id
        WHERE q.status = 'pending'
        ORDER BY 
            CASE WHEN q.priority = 'high' THEN 1 
                 WHEN q.priority = 'medium' THEN 2
                 ELSE 3 END,
            q.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_queries = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Attempting database connection...");
$db = new Database();
$conn = $db->getConnection();
error_log("Connection status: " . ($conn->connect_error ? "Failed: ".$conn->connect_error : "Success"));
}

// Fetch classes created by this staff
$staff_classes = [];
try {
    $stmt = $conn->prepare("
        SELECT c.*, t.name as trainer_name,
               (SELECT COUNT(*) FROM appointments WHERE class_id = c.id AND status = 'booked') as booked_count
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE c.created_by = ?
        ORDER BY 
            CASE WHEN c.schedule > NOW() THEN 0 ELSE 1 END,
            c.schedule DESC
    ");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff_classes = $result->fetch_all(MYSQLI_ASSOC);
    
    // Debug output
    error_log("Classes fetched: " . count($staff_classes));
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $error = "Failed to load your classes.";
}

// Fetch stats
$stats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM classes WHERE created_by = ?) as total_classes,
            (SELECT COUNT(*) FROM classes WHERE created_by = ? AND schedule > NOW()) as upcoming_classes,
            (SELECT COUNT(*) FROM queries WHERE status = 'pending') as pending_queries,
            (SELECT COUNT(DISTINCT a.user_id) 
             FROM appointments a
             JOIN classes c ON a.class_id = c.id
             WHERE c.created_by = ?) as unique_students
    ");
    $stmt->bind_param("iii", $staff_id, $staff_id, $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    $error = "Failed to load dashboard statistics.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card.classes { border-left-color: var(--primary-color); }
        .stat-card.students { border-left-color: var(--secondary-color); }
        .stat-card.queries { border-left-color: var(--warning-color); }
        
        .badge-high { background-color: var(--danger-color); }
        .badge-medium { background-color: var(--warning-color); color: #000; }
        .badge-low { background-color: var(--secondary-color); }
        
        .upcoming-class {
            background-color: rgba(25, 135, 84, 0.1);
        }
        .past-class {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Staff Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="quickActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bolt me-1"></i> Quick Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="quickActions">
                    <li><a class="dropdown-item" href="manage.php?action=create"><i class="fas fa-plus me-2"></i>Add Class</a></li>
                    <li><a class="dropdown-item" href="classes.php"><i class="fas fa-calendar me-2"></i>View All Classes</a></li>
                </ul>
            </div>
        </div>
        
        <?php if (isset($_SESSION['dashboard_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['dashboard_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['dashboard_success']); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4 g-4">
            <div class="col-md-3">
                <div class="card stat-card classes h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Classes</h6>
                                <h3 class="mb-0"><?php echo $stats['total_classes'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-dumbbell fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card classes h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Upcoming Classes</h6>
                                <h3 class="mb-0"><?php echo $stats['upcoming_classes'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-calendar-check fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card students h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Unique Students</h6>
                                <h3 class="mb-0"><?php echo $stats['unique_students'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-users fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card queries h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Queries</h6>
                                <h3 class="mb-0"><?php echo $stats['pending_queries'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-question-circle fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Classes Section -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Classes</h5>
                    <a href="manage.php?action=create" class="btn btn-sm btn-light">
                        <i class="fas fa-plus me-1"></i> Add Class
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($staff_classes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Type</th>
                                    <th>Trainer</th>
                                    <th>Schedule</th>
                                    <th>Booked</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_classes as $class): 
                                    $is_upcoming = strtotime($class['schedule']) > time();
                                ?>
                                <tr class="<?php echo $is_upcoming ? 'upcoming-class' : 'past-class'; ?>">
                                    <td><?php echo htmlspecialchars($class['name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $class['class_type'] === 'premium' ? 'warning text-dark' : 
                                                 ($class['class_type'] === 'advanced' ? 'danger' : 'primary');
                                        ?>">
                                            <?php echo ucfirst($class['class_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($class['trainer_name'] ?? 'Not assigned'); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($class['schedule'])); ?></td>
                                    <td><?php echo $class['booked_count']; ?>/<?php echo $class['capacity']; ?></td>
                                    <td>
                                        <?php if ($is_upcoming): ?>
                                            <span class="badge bg-success">Upcoming</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="manage_class.php?action=edit&id=<?= $class['id'] ?>" 
                                        class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($is_upcoming): ?>
                                        <a href="class_attendance.php?id=<?= $class['id'] ?>" 
                                        class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-clipboard-list"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="manage.php" style="display:inline;" 
                                            onsubmit="return confirm('Are you sure you want to delete this class?');">
                                            <input type="hidden" name="delete_class" value="1">
                                            <input type="hidden" name="id" value="<?= $class['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        You haven't created any classes yet. 
                        <a href="manage_class.php?action=create" class="alert-link">Create your first class</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Queries Section -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0">Pending Customer Queries</h5>
            </div>
            <div class="card-body">
                <?php if (count($pending_queries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_queries as $query): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($query['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($query['subject']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($query['priority']); ?>">
                                            <?php echo ucfirst($query['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($query['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#queryModal<?php echo $query['id']; ?>">
                                            <i class="fas fa-reply"></i> Respond
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        No pending queries. Great job!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Query Response Modals -->
        <?php foreach ($pending_queries as $query): ?>
        <div class="modal fade" id="queryModal<?php echo $query['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Respond to Query</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <h6>Customer:</h6>
                                <p><?php echo htmlspecialchars($query['user_name']); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Subject:</h6>
                                <p><?php echo htmlspecialchars($query['subject']); ?></p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6>Priority:</h6>
                                    <span class="badge badge-<?php echo strtolower($query['priority']); ?>">
                                        <?php echo ucfirst($query['priority']); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <h6>Submitted:</h6>
                                    <p><?php echo date('M j, Y g:i A', strtotime($query['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Message:</h6>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($query['message'])); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Your Response:</h6>
                                <textarea name="response" class="form-control" rows="5" required></textarea>
                            </div>
                            
                            <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="resolve_query" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Submit Response
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize all Bootstrap components
        $(document).ready(function() {
            // Enable dropdowns
            $('.dropdown-toggle').dropdown();
            
            // Enable tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Enable modals
            $('.modal').modal();
            
            // Enable popovers
            $('[data-bs-toggle="popover"]').popover();
        });
    </script>
</body>
</html>