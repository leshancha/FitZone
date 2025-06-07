<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

$db = new Database();
$conn = $db->getConnection();

// Fetch all upcoming classes with pricing
$classes = [];
try {
    $stmt = $conn->prepare("
        SELECT c.*, t.name as trainer_name 
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE c.schedule > NOW() AND c.status = 'scheduled'
        ORDER BY c.schedule ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Fetch gym info for social links
$gym_info = [];
try {
    $stmt = $conn->prepare("SELECT * FROM gym_info LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $gym_info = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Gym info error: " . $e->getMessage());
}

// Handle direct booking if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_class'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
        $_SESSION['login_redirect'] = 'classes.php';
        header("Location: login.php");
        exit;
    }

    $class_id = $_POST['class_id'];
    $user_id = $_SESSION['user']['id'];
    
    try {
        // Start transaction
        $conn->autocommit(false);
        
        // Verify class exists and has capacity
        $stmt = $conn->prepare("
            SELECT id, capacity, booked 
            FROM classes 
            WHERE id = ? AND capacity > booked AND status = 'scheduled'
            FOR UPDATE
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $class = $result->fetch_assoc();
        
        if (!$class) {
            $_SESSION['booking_error'] = "Class is full or not available for booking.";
            $conn->rollback();
        } else {
            // Check if already booked
            $stmt = $conn->prepare("
                SELECT id 
                FROM appointments 
                WHERE user_id = ? AND class_id = ? AND status = 'booked'
            ");
            $stmt->bind_param("ii", $user_id, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['booking_error'] = "You have already booked this class.";
                $conn->rollback();
            } else {
                // Create booking
                $date = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("
                    INSERT INTO appointments 
                    (user_id, class_id, date, status) 
                    VALUES (?, ?, ?, 'booked')
                ");
                $stmt->bind_param("iis", $user_id, $class_id, $date);
                $stmt->execute();
                
                // Update booked count
                $stmt = $conn->prepare("
                    UPDATE classes 
                    SET booked = booked + 1 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['booking_success'] = "Class booked successfully!";
                header("Location: view_bookings.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking error: " . $e->getMessage());
        $_SESSION['booking_error'] = "Booking failed. Please try again.";
    } finally {
        $conn->autocommit(true);
    }
    
    header("Location: classes.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .price-tag {
            background-color: #198754;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .class-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-premium {
            background-color: #6f42c1;
        }
        .badge-advanced {
            background-color: #fd7e14;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1>Our Classes</h1>
            <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] == 'admin' || $_SESSION['user']['role'] == 'staff')): ?>
                <a href="manage_class.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Class
                </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['booking_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['booking_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['booking_error']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>Pricing Tiers:</strong>
                    <span class="badge bg-secondary me-2">Regular: $15</span>
                    <span class="badge badge-advanced me-2">Advanced: $20</span>
                    <span class="badge badge-premium">Premium: $25</span>
                </div>
            </div>
        </div>

        <?php if (count($classes) > 0): ?>
            <div class="row g-4">
                <?php foreach ($classes as $class): 
                    $class_type = 'regular';
                    $class_badge = 'bg-secondary';
                    if ($class['price'] >= 25) {
                        $class_type = 'premium';
                        $class_badge = 'badge-premium';
                    } elseif ($class['price'] >= 20) {
                        $class_type = 'advanced';
                        $class_badge = 'badge-advanced';
                    }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow class-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?php echo $class_badge; ?>"><?php echo ucfirst($class_type); ?></span>
                                <span class="price-tag">$<?php echo number_format($class['price'], 2); ?></span>
                            </div>
                            
                            <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
                            
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item">
                                    <strong><i class="fas fa-calendar-alt text-primary me-2"></i> When:</strong>
                                    <?php echo date('l, F j, Y g:i A', strtotime($class['schedule'])); ?>
                                </li>
                                <li class="list-group-item">
                                    <strong><i class="fas fa-user-tie text-primary me-2"></i> Trainer:</strong>
                                    <?php echo htmlspecialchars($class['trainer_name'] ?? 'Not assigned'); ?>
                                </li>
                                <li class="list-group-item">
                                    <strong><i class="fas fa-users text-primary me-2"></i> Availability:</strong>
                                    <?php echo ($class['capacity'] - $class['booked']) . ' of ' . $class['capacity']; ?> slots left
                                </li>
                                <li class="list-group-item">
                                    <strong><i class="fas fa-clock text-primary me-2"></i> Duration:</strong> <?php echo $class['duration_minutes']; ?> minutes
                                </li>
                            </ul>

                            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] == 'customer'): ?>
                                <form method="POST">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <button type="submit" name="book_class" class="btn btn-primary w-100">
                                        <i class="fas fa-calendar-check"></i> Book Now
                                    </button>
                                </form>
                            <?php elseif (!isset($_SESSION['user'])): ?>
                                <a href="login.php" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt"></i> Login to Book
                                </a>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] == 'admin' || $_SESSION['user']['role'] == 'staff')): ?>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="manage_class.php?action=edit&id=<?php echo $class['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="manage_class.php?action=delete&id=<?php echo $class['id']; ?>"
                                       class="btn btn-sm btn-outline-danger flex-grow-1"
                                       onclick="return confirm('Are you sure you want to delete this class?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <h4>No upcoming classes scheduled</h4>
                <p class="mb-0">Please check back later or contact us for more information</p>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>