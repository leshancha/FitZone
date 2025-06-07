<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Check if user is logged in as customer
if (!isset($_SESSION['user'])) {
    $_SESSION['login_error'] = "Please login to cancel bookings";
    header("Location: login.php");
    exit;
}

// Check if user is a customer
if ($_SESSION['user']['role'] !== 'customer') {
    header("Location: ".$_SESSION['user']['role']."_dashboard.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$booking_id = $_GET['id'] ?? null;

if ($booking_id) {
    try {
        // Start transaction
        $conn->autocommit(false);
        
        // Get booking details with FOR UPDATE lock
        $stmt = $conn->prepare("
            SELECT a.id, a.class_id, c.booked, c.schedule
            FROM appointments a
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND a.user_id = ? AND a.status = 'booked'
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            $_SESSION['booking_error'] = "Booking not found or already cancelled";
            $conn->rollback();
        } else {
            // Check if class is in the future
            if (strtotime($booking['schedule']) < time()) {
                $_SESSION['booking_error'] = "Cannot cancel past classes";
                $conn->rollback();
            } else {
                // Update booking status to cancelled
                $stmt = $conn->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $booking_id);
                $stmt->execute();
                
                // Decrement booked count in classes table
                $stmt = $conn->prepare("
                    UPDATE classes 
                    SET booked = booked - 1 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $booking['class_id']);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['booking_success'] = "Booking cancelled successfully";
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking cancellation error: " . $e->getMessage());
        $_SESSION['booking_error'] = "Error cancelling booking";
    } finally {
        $conn->autocommit(true);
    }
} else {
    $_SESSION['booking_error'] = "No booking specified";
}

header("Location: view_bookings.php");
exit;
?>