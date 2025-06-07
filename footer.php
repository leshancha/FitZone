<?php
require 'db.php';

// Fetch gym info
try {
    $gym_info = $conn->query("SELECT * FROM gym_info LIMIT 1")->fetch();
} catch (PDOException $e) {
    $gym_info = [];
}
?>

<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5>About FitZone</h5>
                <p>Your premier fitness destination offering top-notch facilities and expert trainers to help you achieve your fitness goals.</p>
            </div>
            
            <div class="col-md-4 mb-4">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php" class="text-white">Home</a></li>
                    <li><a href="about.php" class="text-white">About Us</a></li>
                    <li><a href="classes.php" class="text-white">Classes</a></li>
                    <li><a href="reviews.php" class="text-white">Reviews</a></li>
                </ul>
            </div>
            
            <div class="col-md-4 mb-4">
                <h5>Contact Us</h5>
                <p>
                    <?php echo htmlspecialchars($gym_info['address'] ?? '123 Fitness St, Kurunegala'); ?><br>
                    Phone: <?php echo htmlspecialchars($gym_info['phone'] ?? '(123) 456-7890'); ?><br>
                    Email: <?php echo htmlspecialchars($gym_info['email'] ?? 'info@fitzone.com'); ?>
                </p>
                
                <div class="social-icons">
                    <?php if (!empty($gym_info['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($gym_info['facebook_url']); ?>" target="_blank" class="text-white me-2">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($gym_info['twitter_url'])): ?>
                        <a href="<?php echo htmlspecialchars($gym_info['twitter_url']); ?>" target="_blank" class="text-white me-2">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($gym_info['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($gym_info['instagram_url']); ?>" target="_blank" class="text-white me-2">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($gym_info['youtube_url'])): ?>
                        <a href="<?php echo htmlspecialchars($gym_info['youtube_url']); ?>" target="_blank" class="text-white">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <hr class="my-4 bg-light">
        
        <div class="text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FitZone Fitness Center. All rights reserved.</p>
        </div>
    </div>
</footer>