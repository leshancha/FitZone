<?php
require 'config/db.php';
require 'utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not staff
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: ../login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$staff_id = $_SESSION['user']['id'];
$error = '';
$success = '';

// Get class ID from URL
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify staff owns this class
$class = null;
try {
    $stmt = $conn->prepare("
        SELECT c.*, t.name as trainer_name 
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE c.id = ? AND c.created_by = ? AND c.schedule > NOW()
    ");
    $stmt->bind_param("ii", $class_id, $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $class = $result->fetch_assoc();
    
    if (!$class) {
        $error = "Class not found or you don't have permission";
    }
} catch (Exception $e) {
    error_log("Class fetch error: " . $e->getMessage());
    $error = "Failed to load class details";
}

// Get attendees for this class
$attendees = [];
if ($class) {
    try {
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.email, a.status
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            WHERE a.class_id = ? AND a.status = 'booked'
            ORDER BY u.name
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Attendees fetch error: " . $e->getMessage());
        $error = "Failed to load attendees";
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $class) {
    try {
        $conn->autocommit(false);
        
        foreach ($_POST['attendance'] as $user_id => $status) {
            $user_id = (int)$user_id;
            $status = in_array($status, ['attended', 'absent']) ? $status : 'absent';
            
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = ?, updated_at = NOW() 
                WHERE user_id = ? AND class_id = ?
            ");
            $stmt->bind_param("sii", $status, $user_id, $class_id);
            $stmt->execute();
        }
        
        // Update class status if marked completed
        if (isset($_POST['mark_completed'])) {
            $stmt = $conn->prepare("
                UPDATE classes 
                SET status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
        }
        
        $conn->commit();
        $success = "Attendance saved successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Attendance save error: " . $e->getMessage());
        $error = "Failed to save attendance";
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
    <title>Class Attendance - FitZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .attendance-card {
            max-width: 800px;
            margin: 0 auto;
        }
        .attendance-badge {
            font-size: 0.9rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Class Attendance</h1>
            <a href="staff_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($class): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($class['name']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Trainer:</strong> <?= htmlspecialchars($class['trainer_name'] ?? 'Not assigned') ?></p>
                            <p><strong>Schedule:</strong> <?= date('M j, Y g:i A', strtotime($class['schedule'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Duration:</strong> <?= $class['duration_minutes'] ?> minutes</p>
                            <p><strong>Class Type:</strong> <?= ucfirst($class['class_type']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm attendance-card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Attendance List</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($attendees) > 0): ?>
                                        <?php foreach ($attendees as $attendee): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($attendee['name']) ?></td>
                                                <td><?= htmlspecialchars($attendee['email']) ?></td>
                                                <td>
                                                    <select name="attendance[<?= $attendee['id'] ?>]" class="form-select form-select-sm">
                                                        <option value="attended" <?= $attendee['status'] === 'attended' ? 'selected' : '' ?>>Attended</option>
                                                        <option value="absent" <?= $attendee['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No attendees found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($attendees) > 0): ?>
                            <div class="mt-4 d-flex justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="mark_completed" id="mark_completed">
                                    <label class="form-check-label" for="mark_completed">
                                        Mark class as completed
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Attendance
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>