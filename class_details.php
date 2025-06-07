<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

$db = new Database();
$conn = $db->getConnection();
$class_id = $_GET['id'] ?? null;
$class = null;
$trainer = null;
$reviews = [];
$error = '';

// Fetch class details
if ($class_id) {
    try {
        // Get class details
        $stmt = $conn->prepare("
            SELECT c.*, t.name as trainer_name, t.bio as trainer_bio, 
                   t.specialty as trainer_specialty, t.profile_image as trainer_image
            FROM classes c
            LEFT JOIN trainers t ON c.trainer_id = t.id
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $class = $result->fetch_assoc();
        
        if (!$class) {
            $error = "Class not found.";
        } else {
            // Get trainer details if available
            if ($class['trainer_id']) {
                $stmt = $conn->prepare("
                    SELECT name, specialty, certification, bio, profile_image, hourly_rate
                    FROM trainers
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $class['trainer_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $trainer = $result->fetch_assoc();
            }
            
            // Get approved reviews for this class
            $stmt = $conn->prepare("
                SELECT r.*, u.name as user_name, u.profile_image as user_image
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.class_id = ? AND r.is_approved = TRUE
                ORDER BY r.created_at DESC
                LIMIT 5
            ");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $reviews = $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Class details error: " . $e->getMessage());
        $error = "Failed to load class details.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $class ? htmlspecialchars($class['name']) : 'Class Details'; ?> - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .class-header {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('assets/images/class-bg.jpg') center/cover no-repeat;
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }
        
        .badge-premium { background-color: var(--warning-color); color: #000; }
        .badge-advanced { background-color: var(--danger-color); }
        .badge-regular { background-color: var(--primary-color); }
        
        .trainer-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .review-card {
            border-left: 3px solid var(--secondary-color);
            transition: transform 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
        }
        
        .rating {
            color: #f8d64e;
        }
        
        .class-image-placeholder {
            height: 300px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="class-header text-center">
        <div class="container">
            <h1 class="display-4 fw-bold"><?php echo $class ? htmlspecialchars($class['name']) : 'Class Details'; ?></h1>
            <?php if ($class): ?>
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <span class="badge rounded-pill bg-<?php echo strtolower($class['class_type']); ?> fs-6">
                        <?php echo ucfirst($class['class_type']); ?> Class
                    </span>
                    <span class="badge rounded-pill bg-light text-dark fs-6">
                        <i class="fas fa-clock me-1"></i> <?php echo $class['duration_minutes']; ?> mins
                    </span>
                    <span class="badge rounded-pill bg-light text-dark fs-6">
                        <i class="fas fa-users me-1"></i> 
                        <?php echo ($class['capacity'] - $class['booked']); ?> spots left
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <main class="container mb-5">
        <?php if ($error): ?>
            <div class="alert alert-danger text-center">
                <?php echo htmlspecialchars($error); ?>
                <a href="classes.php" class="alert-link">Browse all classes</a>
            </div>
        <?php elseif ($class): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="class-image-placeholder mb-4 rounded">
                                <i class="fas fa-dumbbell fa-5x"></i>
                            </div>
                            
                            <h3 class="card-title">About This Class</h3>
                            <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-calendar-alt fa-2x text-primary me-3"></i>
                                        <div>
                                            <h5 class="mb-0">Schedule</h5>
                                            <p class="mb-0"><?php echo date('l, F j, Y', strtotime($class['schedule'])); ?></p>
                                            <p class="mb-0"><?php echo date('g:i A', strtotime($class['schedule'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-tag fa-2x text-primary me-3"></i>
                                        <div>
                                            <h5 class="mb-0">Price</h5>
                                            <p class="mb-0">$<?php echo number_format($class['price'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer'): ?>
                                <div class="d-grid gap-2 mt-4">
                                    <a href="book_class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-lg">
                                        <i class="fas fa-calendar-plus me-2"></i> Book This Class
                                    </a>
                                </div>
                            <?php elseif (!isset($_SESSION['user'])): ?>
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Please <a href="login.php" class="alert-link">login</a> to book this class
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="card-title mb-4">Class Reviews</h3>
                            
                            <?php if (!empty($reviews)): ?>
                                <div class="row">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card review-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <img src="<?php echo htmlspecialchars($review['user_image'] ?? 'assets/images/default-profile.png'); ?>" 
                                                             class="rounded-circle me-3" width="50" height="50" alt="User">
                                                        <div>
                                                            <h5 class="mb-0"><?php echo htmlspecialchars($review['user_name']); ?></h5>
                                                            <div class="rating">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star<?php echo $i > $review['rating'] ? '-empty' : ''; ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="card-text"><?php echo htmlspecialchars($review['review']); ?></p>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No reviews yet. Be the first to review after taking this class!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <?php if ($trainer): ?>
                        <div class="card trainer-card mb-4">
                            <div class="card-body">
                                <h3 class="card-title">About The Trainer</h3>
                                <div class="text-center mb-4">
                                    <img src="<?php echo htmlspecialchars($trainer['profile_image'] ?? 'assets/images/default-trainer.png'); ?>" 
                                         class="rounded-circle" width="150" height="150" alt="Trainer">
                                </div>
                                <h4 class="text-center"><?php echo htmlspecialchars($trainer['name']); ?></h4>
                                <p class="text-center text-muted mb-3">
                                    <i class="fas fa-star text-warning"></i> <?php echo htmlspecialchars($trainer['specialty']); ?>
                                </p>
                                
                                <div class="mb-3">
                                    <h5>Certification</h5>
                                    <p><?php echo htmlspecialchars($trainer['certification']); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h5>Hourly Rate</h5>
                                    <p>$<?php echo number_format($trainer['hourly_rate'], 2); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h5>Bio</h5>
                                    <p><?php echo htmlspecialchars($trainer['bio']); ?></p>
                                </div>
                                
                                <a href="#" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-envelope me-2"></i> Contact Trainer
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="card-title">Class Location</h3>
                            <div class="class-image-placeholder mb-3 rounded">
                                <i class="fas fa-map-marker-alt fa-5x text-danger"></i>
                            </div>
                            <p class="card-text">
                                <i class="fas fa-building me-2"></i> FitZone Main Studio<br>
                                <i class="fas fa-map-pin me-2"></i> 123 Fitness Street, City
                            </p>
                            <a href="#" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-directions me-2"></i> Get Directions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No class selected. Please <a href="classes.php" class="alert-link">select a class</a> to view details.
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>