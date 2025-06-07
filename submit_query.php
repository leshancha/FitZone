<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

// Redirect if not logged in or not customer
if (!isset($_SESSION['user'])) {
    $_SESSION['login_error'] = "Please login to access this page";
    header("Location: login.php");
    exit;
}

if ($_SESSION['user']['role'] !== 'customer') {
    header("Location: ".$_SESSION['user']['role']."_dashboard.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$error = '';
$success = '';

// Handle query submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO queries 
                (user_id, subject, message, priority) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $user_id, $subject, $message, $priority);
            
            if ($stmt->execute()) {
                $_SESSION['query_success'] = "Query submitted successfully! Our staff will respond soon.";
                header("Location: dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            error_log("Query submission error: " . $e->getMessage());
            $error = "Failed to submit query. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Query - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .query-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        .query-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card query-card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Submit a Support Query</h3>
                            <a href="dashboard.php" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
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
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Your Question or Concern</label>
                                <textarea name="message" class="form-control" rows="5" required></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Query
                                </button>
                                <a href="my_queries.php" class="btn btn-outline-secondary py-2">
                                    <i class="fas fa-list me-1"></i> View My Queries
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>