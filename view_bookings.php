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
$bookings = [];
$error = '';

try {
    // Fetch all booked classes for the user
    $stmt = $conn->prepare("
        SELECT a.id as appointment_id, a.date as booking_date, a.status as booking_status,
               c.id as class_id, c.name as class_name, c.description, c.schedule, 
               c.duration_minutes, c.price, c.class_type,
               t.name as trainer_name
        FROM appointments a
        JOIN classes c ON a.class_id = c.id
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE a.user_id = ? AND a.status = 'booked'
        ORDER BY c.schedule ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $error = "Failed to load your bookings. Please try again.";
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $appointment_id = $_POST['appointment_id'];
    $class_id = $_POST['class_id'];
    
    try {
        // Start transaction
        $conn->autocommit(false);
        
        // Verify the booking belongs to the user
        $stmt = $conn->prepare("
            SELECT id FROM appointments 
            WHERE id = ? AND user_id = ? AND status = 'booked'
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $appointment_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Booking not found or already cancelled.";
            $conn->rollback();
        } else {
            // Cancel the booking
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            
            // Update class booked count
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
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Cancellation error: " . $e->getMessage());
        $error = "Failed to cancel booking. Please try again.";
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
    <title>My Bookings - FitZone</title>
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
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .badge-premium { background-color: var(--warning-color); color: #000; }
        .badge-advanced { background-color: var(--danger-color); }
        .badge-regular { background-color: var(--primary-color); }
        
        .empty-state {
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>My Bookings</h1>
                <p class="text-muted">View and manage your booked classes</p>
            </div>
            <a href="classes.php" class="btn btn-outline-primary">
                <i class="fas fa-plus me-1"></i> Book Another Class
            </a>
        </div>
        
        <?php if (isset($_SESSION['booking_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['booking_success']); ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (count($bookings) > 0): ?>
            <div class="row g-4">
                <?php foreach ($bookings as $booking): ?>
                <div class="col-md-6">
                    <div class="card booking-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge badge-<?php echo strtolower($booking['class_type']); ?>">
                                    <?php echo ucfirst($booking['class_type']); ?>
                                </span>
                                <small class="text-muted">
                                    Booked on <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?>
                                </small>
                            </div>
                            
                            <h4 class="card-title"><?php echo htmlspecialchars($booking['class_name']); ?></h4>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars(substr($booking['description'], 0, 100)); ?>
                                <?php if (strlen($booking['description']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mb-3">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user-tie me-2"></i>Trainer</span>
                                        <span><?php echo htmlspecialchars($booking['trainer_name'] ?? 'Not assigned'); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-calendar-alt me-2"></i>Schedule</span>
                                        <span><?php echo date('M j, Y g:i A', strtotime($booking['schedule'])); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-clock me-2"></i>Duration</span>
                                        <span><?php echo $booking['duration_minutes']; ?> minutes</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-tag me-2"></i>Price</span>
                                        <span>$<?php echo number_format($booking['price'], 2); ?></span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <form method="POST">
                                    <input type="hidden" name="appointment_id" value="<?php echo $booking['appointment_id']; ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $booking['class_id']; ?>">
                                    <button type="submit" name="cancel_booking" class="btn btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to cancel this booking?')">
                                        <i class="fas fa-times-circle me-1"></i> Cancel Booking
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <div class="card-body">
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h3>No Bookings Found</h3>
                        <p class="text-muted">You haven't booked any classes yet.</p>
                        <a href="classes.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-1"></i> Browse Classes
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>