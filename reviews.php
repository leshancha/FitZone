<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/utils/SessionManager.php';

SessionManager::startSecureSession();

$db = new Database();
$conn = $db->getConnection();
$error = '';
$success = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    // Only proceed if user is logged in
    if (!isset($_SESSION['user'])) {
        $error = "Please login to submit a review";
    } else {
        $user_id = $_SESSION['user']['id'];
        $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $review = trim($_POST['review'] ?? '');

        // Validate inputs
        if ($rating < 1 || $rating > 5) {
            $error = "Please select a rating between 1 and 5 stars";
        } elseif (empty($review)) {
            $error = "Please write your review";
        } elseif (strlen($review) > 500) {
            $error = "Review must be less than 500 characters";
        } else {
            try {
                // Verify class exists if provided
                if ($class_id) {
                    $stmt = $conn->prepare("SELECT id, trainer_id FROM classes WHERE id = ?");
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if (!$result->num_rows) {
                        $error = "Selected class not found";
                    } else {
                        $class = $result->fetch_assoc();
                        // Ensure trainer_id matches if provided
                        if ($trainer_id && $class['trainer_id'] != $trainer_id) {
                            $error = "Trainer does not match selected class";
                        }
                    }
                }

                if (!$error) {
                    // Insert review (set is_approved to TRUE to show immediately)
                    $stmt = $conn->prepare("
                        INSERT INTO reviews 
                        (user_id, class_id, trainer_id, rating, review, is_approved) 
                        VALUES (?, ?, ?, ?, ?, TRUE)
                    ");
                    $stmt->bind_param("iiiis", $user_id, $class_id, $trainer_id, $rating, $review);
                    
                    if ($stmt->execute()) {
                        $success = "Thank you for your review!";
                        // Clear form values
                        $_POST = [];
                        // Refresh reviews to include the new one
                        header("Location: reviews.php");
                        exit();
                    } else {
                        throw new Exception("Database insert failed");
                    }
                }
            } catch (Exception $e) {
                error_log("Review submission error: " . $e->getMessage());
                $error = "Failed to submit review. Please try again.";
            }
        }
    }
}


// Fetch approved reviews and the current user's pending reviews
$reviews = [];
$average_rating = 0;
try {
    $query = "
        SELECT r.*, u.name as user_name, u.profile_image as user_image,
               c.name as class_name, t.name as trainer_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN classes c ON r.class_id = c.id
        LEFT JOIN trainers t ON r.trainer_id = t.id
        WHERE r.is_approved = TRUE";
    
    // If logged in, also get their own pending reviews
    if (isset($_SESSION['user'])) {
        $query .= " OR (r.user_id = {$_SESSION['user']['id']} AND r.is_approved = FALSE)";
    }
    
    $query .= " ORDER BY r.created_at DESC LIMIT 50";
    
    $result = $conn->query($query);
    
    if ($result) {
        $reviews = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate average rating from approved reviews only
        $approved_reviews = array_filter($reviews, function($r) { return $r['is_approved']; });
        if (count($approved_reviews) > 0) {
            $total = 0;
            foreach ($approved_reviews as $review) {
                $total += $review['rating'];
            }
            $average_rating = round($total / count($approved_reviews), 1);
        }
    }
} catch (Exception $e) {
    error_log("Reviews fetch error: " . $e->getMessage());
    $error = "Failed to load reviews. Please try again later.";
}

// Fetch user's attended classes for review form
$user_classes = [];
if (isset($_SESSION['user'])) {
    try {
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.schedule, t.id as trainer_id, t.name as trainer_name
            FROM appointments a
            JOIN classes c ON a.class_id = c.id
            LEFT JOIN trainers t ON c.trainer_id = t.id
            WHERE a.user_id = ? AND a.status = 'attended'
            AND c.id NOT IN (
                SELECT class_id FROM reviews 
                WHERE user_id = ? AND class_id IS NOT NULL
            )
            ORDER BY c.schedule DESC
        ");
        $stmt->bind_param("ii", $_SESSION['user']['id'], $_SESSION['user']['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_classes = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("User classes fetch error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating-input input {
            display: none;
        }
        .rating-input label {
            color: #ddd;
            font-size: 1.5rem;
            padding: 0 0.1rem;
            cursor: pointer;
        }
        .rating-input input:checked ~ label,
        .rating-input input:hover ~ label {
            color: var(--warning-color);
        }
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: var(--warning-color);
        }
        
        .review-card {
            border-left: 3px solid var(--secondary-color);
            transition: transform 0.3s ease;
        }
        .review-card:hover {
            transform: translateY(-3px);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
        
        .average-rating {
            font-size: 3rem;
            font-weight: bold;
            color: var(--warning-color);
        }

        .pending-review {
            opacity: 0.7;
            border-left: 3px solid var(--warning-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Page Header -->
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold mb-3">Member Reviews</h1>
                    
                    <div class="d-flex justify-content-center align-items-center gap-4">
                        <div class="average-rating">
                            <?php echo $average_rating; ?>
                        </div>
                        <div>
                            <div class="text-warning mb-2" style="font-size: 1.5rem;">
                                <?php 
                                $full_stars = floor($average_rating);
                                $half_star = ($average_rating - $full_stars) >= 0.5;
                                
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '<i class="fas fa-star"></i>';
                                }
                                
                                if ($half_star) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                }
                                
                                for ($i = $full_stars; $i < 5; $i++) {
                                    if (!$half_star || $i > $full_stars) {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="mb-0 text-muted">Based on <?php echo count($reviews); ?> reviews</p>
                        </div>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Update the form in your HTML to show validation errors and preserve input -->
                <?php if (isset($_SESSION['user'])): ?>
                <div class="card shadow mb-5">
                    <div class="card-body p-4">
                        <h3 class="card-title mb-4">Write a Review</h3>
                        <form method="POST">
                            <!-- Your existing form code -->
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select a class to review (optional)</label>
                                <select class="form-select" name="class_id">
                                    <option value="">General Review</option>
                                    <?php foreach ($user_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"
                                        <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>
                                        data-trainer-id="<?php echo $class['trainer_id'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($class['name']); ?> 
                                        (<?php echo date('M j, Y', strtotime($class['schedule'])); ?>)
                                        <?php if ($class['trainer_name']): ?>
                                        - Trainer: <?php echo htmlspecialchars($class['trainer_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="trainer_id" value="<?php echo $_POST['trainer_id'] ?? ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Rating</label>
                                <div class="rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                                            <?php echo (isset($_POST['rating']) && $_POST['rating'] == $i) ? 'checked' : ''; ?>>
                                        <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                                <?php if (isset($errors['rating'])): ?>
                                    <div class="text-danger small mt-1"><?php echo $errors['rating']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="review" class="form-label">Your Review</label>
                                <textarea class="form-control" id="review" name="review" rows="4" 
                                        maxlength="500" required><?php echo htmlspecialchars($_POST['review'] ?? ''); ?></textarea>
                                <div class="form-text">Maximum 500 characters</div>
                            </div>
                            
                            <button type="submit" name="submit_review" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Submit Review
                            </button>
                        </form>
                    </div>
                </div><br>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Member Experiences</h3>
                    <small class="text-muted">Showing <?php echo count($reviews); ?> reviews</small>
                </div>
                
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="card mb-4 <?php echo !$review['is_approved'] ? 'pending-review' : 'review-card'; ?>">
                        <div class="card-body">
                            <div class="d-flex gap-3 mb-3">
                                <img src="<?php echo htmlspecialchars($review['user_image'] ?? 'assets/images/default-profile.png'); ?>" 
                                     class="rounded-circle user-avatar" alt="User avatar">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($review['user_name']); ?></h5>
                                        <div class="text-warning">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i >= $review['rating'] ? '-empty' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <small class="text-muted">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                        <?php if ($review['class_name']): ?>
                                            • <?php echo htmlspecialchars($review['class_name']); ?>
                                        <?php endif; ?>
                                        <?php if ($review['trainer_name']): ?>
                                            • Trainer: <?php echo htmlspecialchars($review['trainer_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-comment-slash fa-4x text-muted mb-4"></i>
                            <h4>No reviews yet</h4>
                            <p class="text-muted">Be the first to share your experience</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rating input enhancement
        document.querySelectorAll('.rating-input input').forEach(input => {
            input.addEventListener('change', function() {
                const labels = this.parentElement.querySelectorAll('label');
                labels.forEach((label, index) => {
                    if (index < this.value) {
                        label.classList.add('fas');
                        label.classList.remove('far');
                    } else {
                        label.classList.add('far');
                        label.classList.remove('fas');
                    }
                });
            });
        });

        // Update trainer_id when class selection changes
        document.querySelector('select[name="class_id"]')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const trainerId = selectedOption.dataset.trainerId || '';
            document.querySelector('input[name="trainer_id"]').value = trainerId;
        });
    </script>
</body>
</html>