<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in as customer
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$class_id = $_GET['id'] ?? null;
$class = null;
$booking = null;
$error = '';
$success = '';

// Fetch class details and check if booked
if ($class_id) {
    try {
        // Get class details
        $stmt = $conn->prepare("
            SELECT c.*, t.name as trainer_name 
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
            // Check if user has booked this class
            $stmt = $conn->prepare("
                SELECT a.* 
                FROM appointments a
                WHERE a.user_id = ? AND a.class_id = ? AND a.status = 'booked'
            ");
            $stmt->bind_param("ii", $user_id, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
        }
    } catch (Exception $e) {
        error_log("Class details error: " . $e->getMessage());
        $error = "Failed to load class details.";
    }
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_class'])) {
    $class_id = $_POST['class_id'];
    $date = date('Y-m-d H:i:s');
    
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
            $error = "Class is full or not available for booking.";
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
                $error = "You have already booked this class.";
                $conn->rollback();
            } else {
                // Create booking
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
        $error = "Booking failed. Please try again.";
    } finally {
        $conn->autocommit(true);
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    $class_id = $_POST['class_id'];
    
    try {
        // Start transaction
        $conn->autocommit(false);
        
        // Get the appointment to cancel
        $stmt = $conn->prepare("
            SELECT id 
            FROM appointments 
            WHERE id = ? AND user_id = ? AND status = 'booked'
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        
        if (!$appointment) {
            $error = "Booking not found or already cancelled.";
            $conn->rollback();
        } else {
            // Cancel the booking
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            // Update booked count
            $stmt = $conn->prepare("
                UPDATE classes 
                SET booked = booked - 1 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['booking_success'] = "Booking cancelled successfully!";
            header("Location: view_bookings.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Cancellation error: " . $e->getMessage());
        $error = "Cancellation failed. Please try again.";
    } finally {
        $conn->autocommit(true);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($booking) ? 'Your Booking' : 'Book a Class'; ?> - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .booking-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .booked-card {
            border-left: 4px solid var(--secondary-color);
        }
        
        .badge-premium { background-color: var(--warning-color); color: #000; }
        .badge-advanced { background-color: var(--danger-color); }
        .badge-regular { background-color: var(--primary-color); }
        .badge-booked { background-color: var(--secondary-color); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card <?php echo isset($booking) ? 'booked-card' : 'booking-card'; ?>">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">
                                <?php echo isset($booking) ? 'Your Booking Details' : 'Book This Class'; ?>
                            </h3>
                            <a href="classes.php" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left me-1"></i> Back to Classes
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['booking_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['booking_success']); ?>
                        <?php endif; ?>
                        
                        <?php if ($class): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="badge badge-<?php echo strtolower($class['class_type']); ?> mb-2">
                                        <?php echo ucfirst($class['class_type']); ?>
                                    </span>
                                    <?php if (isset($booking)): ?>
                                        <span class="badge badge-booked mb-2">
                                            <i class="fas fa-check-circle me-1"></i> Booked
                                        </span>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($class['name']); ?></h4>
                                    <p class="text-muted"><?php echo htmlspecialchars($class['description']); ?></p>
                                    
                                    <div class="mb-3">
                                        <h6>Class Details:</h6>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-user-tie me-2"></i>Trainer</span>
                                                <span><?php echo htmlspecialchars($class['trainer_name'] ?? 'Not assigned'); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-calendar-alt me-2"></i>Schedule</span>
                                                <span><?php echo date('M j, Y g:i A', strtotime($class['schedule'])); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-clock me-2"></i>Duration</span>
                                                <span><?php echo $class['duration_minutes']; ?> minutes</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-users me-2"></i>Availability</span>
                                                <span><?php echo ($class['capacity'] - $class['booked']); ?> of <?php echo $class['capacity']; ?> slots left</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light h-100">
                                        <div class="card-body d-flex flex-column">
                                            <?php if (isset($booking)): ?>
                                                <h5 class="card-title text-success">
                                                    <i class="fas fa-check-circle me-1"></i> Your Booking
                                                </h5>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-calendar-check me-2"></i>
                                                    You're registered for this class on <?php echo date('M j, Y g:i A', strtotime($class['schedule'])); ?>
                                                </div>
                                                
                                                <div class="mt-3 mb-4">
                                                    <h6>Booking Details:</h6>
                                                    <ul class="list-group list-group-flush">
                                                        <li class="list-group-item">
                                                            <i class="fas fa-calendar-day me-2"></i>
                                                            Booked on: <?php echo date('M j, Y g:i A', strtotime($booking['date'])); ?>
                                                        </li>
                                                        <li class="list-group-item">
                                                            <i class="fas fa-barcode me-2"></i>
                                                            Booking ID: <?php echo $booking['id']; ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                                
                                                <form method="POST" class="mt-auto">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" name="cancel_booking" class="btn btn-danger"
                                                                onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                            <i class="fas fa-times-circle me-1"></i> Cancel Booking
                                                        </button>
                                                        <a href="view_bookings.php" class="btn btn-outline-primary">
                                                            <i class="fas fa-list me-1"></i> View All Bookings
                                                        </a>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <h5 class="card-title">Confirm Booking</h5>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    This class is scheduled for <?php echo date('M j, Y g:i A', strtotime($class['schedule'])); ?>
                                                </div>
                                                
                                                <form method="POST" class="mt-auto">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                    <div class="d-grid gap-2">
                                                        <button type="submit" name="book_class" class="btn btn-primary btn-lg">
                                                            <i class="fas fa-check-circle me-1"></i> Confirm Booking
                                                        </button>
                                                        <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-outline-secondary">
                                                            <i class="fas fa-info-circle me-1"></i> View Class Details
                                                        </a>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No class selected. Please <a href="classes.php">select a class</a> to book.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>