<?php
require 'db.php';

// Fetch featured classes
try {
    $featured_classes = $conn->query("
        SELECT c.*, t.name as trainer_name 
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        WHERE c.schedule > NOW()
        ORDER BY c.schedule ASC
        LIMIT 3
    ")->fetchAll();
} catch (PDOException $e) {
    $featured_classes = [];
}

// Fetch gym info
try {
    $gym_info = $conn->query("SELECT * FROM gym_info LIMIT 1")->fetch();
} catch (PDOException $e) {
    $gym_info = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitZone Fitness Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Simple Hero with Inline Background -->
<section style="
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://res.cloudinary.com/dvtnhrp6q/image/upload/v1742484377/DALL_E_2025-03-19_10.30.37_-_A_high-energy_hero_section_image_for_FitZone_Fitness_Center_._The_design_features_a_modern_gym_environment_with_people_actively_working_out_lifting_w_ua0fqy.jpg') center/cover;
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    padding: 2rem;
">
    <div>
        <h1 style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1.5rem;">
            Welcome to FitZone Fitness
        </h1>
        <p style="font-size: 1.5rem; margin-bottom: 2rem;">
            Your journey to a healthier lifestyle starts here
        </p>
        <div>
            <a href="register.php" class="btn btn-primary btn-lg">Join Now</a>
            <a href="classes.php" class="btn btn-outline-light btn-lg">Browse Classes</a>
        </div>
    </div>
</section>

    <!-- Featured Classes -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Featured Classes</h2>
            <div class="row g-4">
                <?php foreach ($featured_classes as $class): ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars(substr($class['description'], 0, 100)); ?>...</p>
                            <p><strong>Trainer:</strong> <?php echo htmlspecialchars($class['trainer_name'] ?? 'Not assigned'); ?></p>
                            <p><strong>When:</strong> <?php echo date('M j, Y g:i A', strtotime($class['schedule'])); ?></p>
                            <p><strong>Slots:</strong> <?php echo ($class['capacity'] - $class['booked']) . '/' . $class['capacity']; ?></p>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer'): ?>
                                <a href="book_class.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary w-100">Book Now</a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="login.php" class="btn btn-primary w-100">Login to Book</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="classes.php" class="btn btn-outline-primary">View All Classes</a>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">What Our Members Say</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow">
                        <div class="card-body text-center">
                            <div class="text-warning mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p class="card-text">"FitZone has completely transformed my fitness routine. The trainers are knowledgeable and supportive!"</p>
                            <p class="text-muted">- Sarah J.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow">
                        <div class="card-body text-center">
                            <div class="text-warning mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p class="card-text">"The variety of classes keeps me motivated. I've never felt better!"</p>
                            <p class="text-muted">- Michael T.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow">
                        <div class="card-body text-center">
                            <div class="text-warning mb-3">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <p class="card-text">"Great facilities and friendly staff. Highly recommend to anyone looking to improve their health."</p>
                            <p class="text-muted">- Priya K.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="reviews.php" class="btn btn-outline-primary">Read More Reviews</a>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>