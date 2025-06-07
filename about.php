<?php
require 'db.php';
require_once 'utils/SessionManager.php';
SessionManager::startSecureSession();


// Fetch gym info
try {
    $gym_info = $conn->query("SELECT * FROM gym_info LIMIT 1")->fetch();
} catch (PDOException $e) {
    $gym_info = [];
}

// Fetch trainers
try {
    $trainers = $conn->query("SELECT * FROM trainers ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $trainers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - FitZone Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <h2 class="mb-4">About FitZone Fitness</h2>
                <p class="lead">Your premier fitness destination in <?php echo htmlspecialchars($gym_info['address'] ?? 'Kurunegala'); ?></p>
                <p>Founded in 2023, FitZone Fitness Center has been helping people achieve their fitness goals with state-of-the-art equipment and expert trainers. Our mission is to provide a welcoming environment where everyone can improve their health and wellness through personalized fitness programs.</p>
                
                <h3 class="mt-5 mb-3">Our Facilities</h3>
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item bg-transparent"><i class="fas fa-check text-primary me-2"></i> Modern cardio and strength equipment</li>
                    <li class="list-group-item bg-transparent"><i class="fas fa-check text-primary me-2"></i> Spacious group exercise studio</li>
                    <li class="list-group-item bg-transparent"><i class="fas fa-check text-primary me-2"></i> Clean and well-maintained locker rooms</li>
                    <li class="list-group-item bg-transparent"><i class="fas fa-check text-primary me-2"></i> Comfortable lounge area</li>
                </ul>
            </div>
            
            <div class="col-lg-6 mb-4">
                <img src="https://denglischdocs.com/storage/media/138/S4cVRJKPzJpxT7F8HmCR3toeMsLhNz-metaZ3ltLW1pbi5wbmc=-.png" alt="Our Gym" class="img-fluid rounded shadow">
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="text-center mb-4">Meet Our Trainers</h3>
            </div>
            
            <?php foreach ($trainers as $trainer): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow">
                    <img src="https://cdn-icons-png.flaticon.com/256/3597/3597951.png" class="card-img-top" alt="<?php echo htmlspecialchars($trainer['name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($trainer['name']); ?></h5>
                        <p class="text-primary"><?php echo htmlspecialchars($trainer['specialty']); ?></p>
                        <p class="card-text"><?php echo htmlspecialchars($trainer['bio']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Our Mission</h3>
                        <p class="card-text">To empower individuals to achieve their fitness goals through personalized training, state-of-the-art facilities, and a supportive community environment.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Our Vision</h3>
                        <p class="card-text">To be the leading fitness center in the region by providing exceptional service, innovative programs, and a commitment to member success.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>