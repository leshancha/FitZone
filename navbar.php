<?php
// navbar.php
require_once __DIR__.'/utils/SessionManager.php';
SessionManager::startSecureSession();

// Get the current logo path
$logo_path = 'assets/logos/logo.'; // Base path without extension
$logo_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
$current_logo = null;

// Check for existing logo files
foreach ($logo_extensions as $ext) {
    if (file_exists(__DIR__ . '/../' . $logo_path . $ext)) {
        $current_logo = $logo_path . $ext;
        break;
    }
}

// Get base URL for proper path resolution
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$logo_path = 'assets/logo.png';
$absolute_logo_path = $base_url . '/' . ltrim($logo_path, '/');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <?php if ($current_logo): ?>
                <img src="/<?php echo $current_logo; ?>" alt="FitZone Logo" width="50" height="50" id="navbar-logo">
            <?php else: ?>
                <!-- Fallback icon -->
                <i class="fas fa-dumbbell me-2"></i>
            <?php endif; ?>
            FitZone Fitness
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>" href="classes.php">Classes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">Reviews</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user'])): ?>
                    <!-- For logged in users -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- For guests -->
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>