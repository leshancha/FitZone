<?php
// dashboard.php (Member Dashboard)
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['login_error'] = "Please login to access the dashboard";
    header("Location: login.php");
    exit;
}

// Redirect if not a customer
if ($_SESSION['user']['role'] !== 'customer') {
    header("Location: ".$_SESSION['user']['role']."_dashboard.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];

// Fetch user's booked classes
$bookings = [];
$upcoming = [];
$user_stats = ['total_bookings' => 0, 'upcoming_bookings' => 0, 'classes_attended' => 0];

try {
    // Get user statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN a.status = 'booked' AND c.schedule > NOW() THEN 1 ELSE 0 END) as upcoming_bookings,
            SUM(CASE WHEN a.status = 'attended' THEN 1 ELSE 0 END) as classes_attended
        FROM appointments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_stats = $result->fetch_assoc();
    
    // Get booked classes
    $stmt = $conn->prepare("
        SELECT a.id, c.name, c.description, c.schedule, a.date, t.name as trainer_name,
               c.capacity, c.booked, c.class_type, c.duration_minutes, a.status
        FROM appointments a
        JOIN classes c ON a.class_id = c.id
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE a.user_id = ? AND (a.status = 'booked' OR a.status = 'attended')
        ORDER BY c.schedule DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);

    // Get upcoming classes (not booked by user)
    $stmt = $conn->prepare("
        SELECT c.*, t.name as trainer_name 
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE c.schedule > NOW() 
        AND c.id NOT IN (
            SELECT class_id FROM appointments 
            WHERE user_id = ? AND status = 'booked'
        )
        AND c.status = 'scheduled'
        ORDER BY c.schedule ASC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $upcoming = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $_SESSION['dashboard_error'] = "Error loading dashboard data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - FitZone</title>
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
        .stat-card.bookings { border-left-color: var(--primary-color); }
        .stat-card.upcoming { border-left-color: var(--secondary-color); }
        .stat-card.attended { border-left-color: var(--warning-color); }
        
        .class-card {
            transition: all 0.3s ease;
        }
        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .badge-premium { background-color: var(--warning-color); color: #000; }
        .badge-advanced { background-color: var(--danger-color); }
        .badge-regular { background-color: var(--primary-color); }
        
        .profile-dropdown .dropdown-toggle::after {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <main class="container py-4">
        <!-- Welcome Section -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h1 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h1>
                <p class="text-muted">Track your fitness journey and manage your bookings</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="dropdown profile-dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i> My Account
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['dashboard_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($_SESSION['dashboard_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['dashboard_error']); endif; ?>
        
        <!-- User Statistics -->
        <div class="row mb-4 g-4">
            <div class="col-md-4">
                <div class="card stat-card bookings h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Bookings</h6>
                                <h3 class="mb-0"><?php echo $user_stats['total_bookings'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-calendar-check fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card upcoming h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Upcoming Classes</h6>
                                <h3 class="mb-0"><?php echo $user_stats['upcoming_bookings'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-clock fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card attended h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Classes Attended</h6>
                                <h3 class="mb-0"><?php echo $user_stats['classes_attended'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-check-circle fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4 g-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                            <i class="fas fa-dumbbell fa-2x text-primary"></i>
                        </div>
                        <h5 class="card-title">Book a Class</h5>
                        <p class="card-text text-muted">Reserve your spot in our fitness classes</p>
                        <a href="classes.php" class="btn btn-primary stretched-link">
                            <i class="fas fa-plus me-1"></i>Book Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                            <i class="fas fa-calendar-check fa-2x text-success"></i>
                        </div>
                        <h5 class="card-title">My Bookings</h5>
                        <p class="card-text text-muted">View and manage your booked classes</p>
                        <a href="view_bookings.php" class="btn btn-success stretched-link">
                            <i class="fas fa-list me-1"></i>View Bookings
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                            <i class="fas fa-question-circle fa-2x text-primary"></i>
                        </div>
                        <h5 class="card-title">Support Queries</h5>
                        <p class="card-text text-muted">Contact our support team with your questions</p>
                        <div class="d-grid gap-2">
                            <a href="submit_query.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> New Query
                            </a>
                            <a href="view_queries.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i> My Queries
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        
        <!-- Your Bookings Section -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Recent Bookings</h5>
                    <a href="view_bookings.php" class="btn btn-sm btn-light">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($bookings) > 0): ?>
                    <div class="row g-4">
                        <?php foreach ($bookings as $booking): ?>
                        <div class="col-md-4">
                            <div class="card class-card h-100">
                                <div class="card-body">
                                    <span class="badge badge-<?php echo strtolower($booking['class_type']); ?> mb-2">
                                        <?php echo ucfirst($booking['class_type']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $booking['status'] === 'attended' ? 'success' : 'info'; ?> float-end">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($booking['name']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?php echo htmlspecialchars($booking['trainer_name'] ?? 'Not assigned'); ?>
                                    </p>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('D, M j, g:i A', strtotime($booking['schedule'])); ?>
                                        (<?php echo $booking['duration_minutes']; ?> mins)
                                    </p>
                                    
                                    <?php if ($booking['status'] === 'booked'): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted">
                                            <?php echo $booking['booked']; ?>/<?php echo $booking['capacity']; ?> booked
                                        </span>
                                        <div class="w-50">
                                            <div class="progress">
                                                <div class="progress-bar" 
                                                     style="width: <?php echo ($booking['booked']/$booking['capacity'])*100; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="class_details.php?id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-info-circle me-1"></i>Details
                                        </a>
                                        <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to cancel this booking?')">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-success mt-3 mb-0">
                                        <i class="fas fa-check-circle me-1"></i>
                                        You attended this class on <?php echo date('M j, Y', strtotime($booking['date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        You haven't booked any classes yet. 
                        <a href="classes.php" class="alert-link">Browse our classes</a> to get started!
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recommended Classes Section -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recommended Classes</h5>
                    <a href="classes.php" class="btn btn-sm btn-light">
                        Browse All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($upcoming) > 0): ?>
                    <div class="row g-4">
                        <?php foreach ($upcoming as $class): ?>
                        <div class="col-md-4">
                            <div class="card class-card h-100">
                                <div class="card-body">
                                    <span class="badge badge-<?php echo strtolower($class['class_type']); ?> mb-2">
                                        <?php echo ucfirst($class['class_type']); ?>
                                    </span>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr($class['description'], 0, 100)); ?>
                                        <?php if (strlen($class['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?php echo htmlspecialchars($class['trainer_name'] ?? 'Not assigned'); ?>
                                    </p>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('D, M j, g:i A', strtotime($class['schedule'])); ?>
                                        (<?php echo $class['duration_minutes']; ?> mins)
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted">
                                            <?php echo $class['booked']; ?>/<?php echo $class['capacity']; ?> booked
                                        </span>
                                        <div class="w-50">
                                            <div class="progress">
                                                <div class="progress-bar" 
                                                     style="width: <?php echo ($class['booked']/$class['capacity'])*100; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="book_class.php?id=<?php echo $class['id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i>Book Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        No upcoming classes available at the moment. Please check back later.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
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
</body>
</html>