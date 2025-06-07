<?php
// admin_dashboard.php
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

// Handle query resolution (from staff dashboard)
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
            $stmt->bind_param("sii", $response, $admin_id, $query_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['dashboard_success'] = "Query resolved successfully!";
            header("Location: admin_dashboard.php");
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

// Fetch all statistics in a single query (admin version)
$stats_query = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'staff') as staff_count,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_count,
        (SELECT COUNT(*) FROM classes) as total_classes,
        (SELECT COUNT(*) FROM appointments WHERE status = 'booked') as booked_appointments,
        (SELECT COUNT(*) FROM queries WHERE status = 'pending') as pending_queries,
        (SELECT COUNT(*) FROM trainers) as total_trainers,
        (SELECT COUNT(*) FROM reviews) as total_reviews,
        (SELECT COUNT(*) FROM classes WHERE schedule > NOW()) as upcoming_classes,
        (SELECT COUNT(DISTINCT user_id) FROM appointments) as unique_members
");

$stats = $stats_query->fetch_assoc();

// Fetch recent data (admin version with more details)
$recent_appointments = $conn->query("
    SELECT a.*, u.name as user_name, u.email as user_email, 
           c.name as class_name, t.name as trainer_name
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    JOIN classes c ON a.class_id = c.id
    LEFT JOIN trainers t ON c.trainer_id = t.id
    ORDER BY a.date DESC LIMIT 8
");

$pending_queries = $conn->query("
    SELECT q.*, u.name as user_name, u.email as user_email
    FROM queries q
    LEFT JOIN users u ON q.user_id = u.id
    WHERE q.status = 'pending'
    ORDER BY 
        CASE WHEN q.priority = 'high' THEN 1 
             WHEN q.priority = 'medium' THEN 2
             ELSE 3 END,
        q.created_at DESC
    LIMIT 5
");

$upcoming_classes = $conn->query("
    SELECT c.*, t.name as trainer_name, u.name as created_by_name,
           (SELECT COUNT(*) FROM appointments WHERE class_id = c.id AND status = 'booked') as booked_count
    FROM classes c
    LEFT JOIN trainers t ON c.trainer_id = t.id
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.status = 'scheduled' AND c.schedule > NOW()
    ORDER BY c.schedule ASC LIMIT 5
");

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color:rgb(166, 177, 212);
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --dark-color: #5a5c69;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%,rgb(136, 114, 114) 100%);
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.users { border-left-color: var(--primary-color); }
        .stat-card.staff { border-left-color: var(--info-color); }
        .stat-card.trainers { border-left-color: var(--secondary-color); }
        .stat-card.classes { border-left-color: var(--warning-color); }
        .stat-card.appointments { border-left-color: var(--danger-color); }
        .stat-card.reviews { border-left-color: #6f42c1; }
        
        .badge-high { background-color: var(--danger-color); }
        .badge-medium { background-color: var(--warning-color); color: #000; }
        .badge-low { background-color: var(--secondary-color); }
        
        .upcoming-class {
            background-color: rgba(25, 135, 84, 0.1);
        }
        
        .quick-actions .btn {
            transition: all 0.2s;
        }
        
        .quick-actions .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">
                            <b>Fitzone Admin Dashboard</b>
                        </h4>
                        <hr class="bg-white opacity-25">
                    </div>
                    
                    <div class="px-3 mb-4">
                        <div class="text-white fw-bold">Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                        <small class="text-white-50"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_dashboard.php">
                                <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        
                        <li class="sidebar-heading text-white-50">Management</li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="manage_user.php">
                                <i class="fas fa-fw fa-users"></i> Users
                                <span class="badge bg-primary rounded-pill float-end"><?= $stats['total_users'] ?? 0 ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_trainers.php">
                                <i class="fas fa-fw fa-user-tie"></i> Trainers
                                <span class="badge bg-success rounded-pill float-end"><?= $stats['total_trainers'] ?? 0 ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="classes.php">
                                <i class="fas fa-fw fa-calendar-alt"></i> Classes
                                <span class="badge bg-warning rounded-pill float-end"><?= $stats['total_classes'] ?? 0 ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_appointment.php">
                                <i class="fas fa-fw fa-clipboard-list"></i> Appointments
                                <span class="badge bg-danger rounded-pill float-end"><?= $stats['booked_appointments'] ?? 0 ?></span>
                            </a>
                        </li>
                        
                        <li class="sidebar-heading text-white-50">Support</li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="manage_queries.php">
                                <i class="fas fa-fw fa-question-circle"></i> Customer Queries
                                <?php if ($stats['pending_queries'] > 0): ?>
                                <span class="badge bg-danger rounded-pill float-end"><?= $stats['pending_queries'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                        <li class="sidebar-heading text-white-50">System</li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="gym_settings.php">
                                <i class="fas fa-fw fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system_logs.php">
                                <i class="fas fa-fw fa-clipboard-check"></i> System Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Top Navigation -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Overview</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?logout=true" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
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

                <!-- Quick Actions -->
                <div class="row mb-4 quick-actions">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body py-2">
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <!-- In the Quick Actions section (around line 230) -->
                                    <a href="manage_classes.php?action=create" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i> Add Class
                                    </a>
                                    <a href="create_staff.php?action=create" class="btn btn-success btn-sm">
                                        <i class="fas fa-user-plus me-1"></i> Add Trainer
                                    </a>
                                    <a href="manage_user.php?action=create" class="btn btn-info btn-sm">
                                        <i class="fas fa-user-plus me-1"></i> Manage Members
                                    </a>
                                    <a href="system_backup.php" class="btn btn-dark btn-sm">
                                        <i class="fas fa-database me-1"></i> Create Backup
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4 g-4">
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card users h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Members</h6>
                                        <h3 class="mb-0"><?= $stats['total_users'] ?? 0 ?></h3>
                                        <small class="text-muted"><?= $stats['unique_members'] ?? 0 ?> active</small>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card staff h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Staff Members</h6>
                                        <h3 class="mb-0"><?= $stats['staff_count'] ?? 0 ?></h3>
                                        <small class="text-muted"><?= $stats['admin_count'] ?? 0 ?> admins</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-shield fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card trainers h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Trainers</h6>
                                        <h3 class="mb-0"><?= $stats['total_trainers'] ?? 0 ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-tie fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card classes h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Classes</h6>
                                        <h3 class="mb-0"><?= $stats['total_classes'] ?? 0 ?></h3>
                                        <small class="text-muted"><?= $stats['upcoming_classes'] ?? 0 ?> upcoming</small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card appointments h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Booked Appointments</h6>
                                        <h3 class="mb-0"><?= $stats['booked_appointments'] ?? 0 ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-clipboard-list fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4">
                        <div class="card stat-card h-100" style="border-left-color: #6f42c1;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Customer Reviews</h6>
                                        <h3 class="mb-0"><?= $stats['total_reviews'] ?? 0 ?></h3>
                                    </div>
                                    <div class="bg-purple bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-star fa-2x text-purple"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Row -->
                <div class="row">
                    <!-- Recent Appointments -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Appointments</h6>
                                <div>
                                    <a href="manage_appointment.php" class="btn btn-sm btn-primary">View All</a>
                                    <a href="manage_appointment.php?action=create" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> New
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Class</th>
                                                <th>Trainer</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div><?= htmlspecialchars($appointment['user_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['user_email']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($appointment['class_name']) ?></td>
                                                <td><?= htmlspecialchars($appointment['trainer_name'] ?? 'N/A') ?></td>
                                                <td><?= date('M j, g:i a', strtotime($appointment['date'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        match($appointment['status']) {
                                                            'booked' => 'success',
                                                            'cancelled' => 'danger',
                                                            'attended' => 'primary',
                                                            default => 'secondary'
                                                        }
                                                    ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Queries -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Pending Customer Queries</h6>
                                <a href="manage_queries.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if ($pending_queries->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
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
                                                <?php while ($query = $pending_queries->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div><?= htmlspecialchars($query['user_name'] ?? 'Guest') ?></div>
                                                        <?php if ($query['user_email']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($query['user_email']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($query['subject']) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= strtolower($query['priority']) ?>">
                                                            <?= ucfirst($query['priority']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($query['created_at'])) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                                data-bs-target="#queryModal<?= $query['id'] ?>">
                                                            <i class="fas fa-reply"></i> Respond
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
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
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <!-- In the Upcoming Classes section (around line 520) -->
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Upcoming Classes</h6>
                                <div>
                                    <a href="classes.php" class="btn btn-sm btn-primary">View All</a>
                                    <a href="manage_classes.php?action=create" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> New
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Trainer</th>
                                                <th>Schedule</th>
                                                <th>Type</th>
                                                <th>Booked</th>
                                                <th>Created By</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($class = $upcoming_classes->fetch_assoc()): ?>
                                            <tr class="upcoming-class">
                                                <td><?= htmlspecialchars($class['name']) ?></td>
                                                <td><?= $class['trainer_name'] ? htmlspecialchars($class['trainer_name']) : 'N/A' ?></td>
                                                <td><?= date('M j, g:i a', strtotime($class['schedule'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $class['class_type'] === 'premium' ? 'warning text-dark' : 
                                                        ($class['class_type'] === 'advanced' ? 'danger' : 'primary')
                                                    ?>">
                                                        <?= ucfirst($class['class_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $class['booked_count'] ?>/<?= $class['capacity'] ?></td>
                                                <td><?= htmlspecialchars($class['created_by_name'] ?? 'System') ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= ucfirst($class['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <!-- In the classes table action buttons (around line 570) -->
                                                    <a href="manage_classes.php?action=edit&id=<?= $class['id'] ?>" 
                                                    class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Query Response Modals -->
    <?php 
    $pending_queries->data_seek(0); // Reset pointer to beginning
    while ($query = $pending_queries->fetch_assoc()): ?>
    <div class="modal fade" id="queryModal<?= $query['id'] ?>" tabindex="-1" aria-hidden="true">
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
                            <p><?= htmlspecialchars($query['user_name'] ?? 'Guest') ?></p>
                            <?php if ($query['user_email']): ?>
                            <p><small class="text-muted"><?= htmlspecialchars($query['user_email']) ?></small></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Subject:</h6>
                            <p><?= htmlspecialchars($query['subject']) ?></p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Priority:</h6>
                                <span class="badge badge-<?= strtolower($query['priority']) ?>">
                                    <?= ucfirst($query['priority']) ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <h6>Submitted:</h6>
                                <p><?= date('M j, Y g:i A', strtotime($query['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Message:</h6>
                            <div class="bg-light p-3 rounded">
                                <?= nl2br(htmlspecialchars($query['message'])) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Your Response:</h6>
                            <textarea name="response" class="form-control" rows="5" required></textarea>
                        </div>
                        
                        <input type="hidden" name="query_id" value="<?= $query['id'] ?>">
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
    <?php endwhile; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    </script>

<?php include 'footer.php'; ?>

</body>
</html>
<?php
$conn = null;
?>