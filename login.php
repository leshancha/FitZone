<?php
// Start session and load dependencies FIRST
require_once __DIR__.'/utils/SessionManager.php';
require_once __DIR__ . '/controllers/LoginController.php';

// Initialize session manager and login controller
SessionManager::startSecureSession();
$loginController = new LoginController();

// Check for auto-login FIRST
if (!SessionManager::isLoggedIn() && !empty($_COOKIE['remember_token'])) {
    $loginController->handleAutoLogin();
}

// Handle logout message display
$logout_message = '';
// Display logout message if exists
if (isset($_SESSION['logout_message'])) {
    $logout_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
} else {
    $logout_message = '';
}

// Display error message if exists
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Redirect if already logged in (AFTER handling messages)
if (SessionManager::isLoggedIn()) {
    $loginController->redirectToDashboard(SessionManager::getUserRole());
    exit; // Ensure no further execution after redirect
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitZone - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            background-color: #fff;
        }
        .role-selector .nav-link {
            font-weight: 600;
            color: #6c757d;
            border: none;
            padding: 0.75rem 1rem;
        }
        .role-selector .nav-link.active {
            color: #4e73df;
            border-bottom: 3px solid #4e73df;
            background-color: transparent;
        }
        .tab-content {
            padding: 1.5rem 0;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
    <div class="container">
    <div class="container">
        <?php if ($logout_message): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3">
            <?= htmlspecialchars($logout_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="login-container">
            <div class="text-center mb-4">
                <i class="fas fa-dumbbell fa-3x text-primary mb-3"></i>
                <h2>FitZone</h2>
                <p class="text-muted">Your fitness journey starts here</p>
            </div>
            
            <?php if ($logout_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($logout_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs role-selector mb-3" id="roleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="member-tab" data-bs-toggle="tab" data-bs-target="#member" type="button" role="tab">
                        <i class="fas fa-user me-1"></i> Member
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                        <i class="fas fa-user-tie me-1"></i> Staff
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">
                        <i class="fas fa-user-shield me-1"></i> Admin
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Member Login -->
                <div class="tab-pane fade show active" id="member" role="tabpanel">
                    <form action="auth.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="user_type" value="Member">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Keep me logged in</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </button>
                    </form>
                </div>
                
                <!-- Staff Login -->
                <div class="tab-pane fade" id="staff" role="tabpanel">
                    <form action="auth.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="user_type" value="Staff">
                        <div class="mb-3">
                            <label for="staff-email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="staff-email" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="staff-password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="staff-password" name="password" required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember-staff" name="remember">
                            <label class="form-check-label" for="remember-staff">Keep me logged in</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </button>
                    </form>
                </div>
                
                <!-- Admin Login -->
                <div class="tab-pane fade" id="admin" role="tabpanel">
                    <form action="auth.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="user_type" value="Admin">
                        <div class="mb-3">
                            <label for="admin-email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="admin-email" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="admin-password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="admin-password" name="password" required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember-admin" name="remember">
                            <label class="form-check-label" for="remember-admin">Keep me logged in</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="register.php" class="text-decoration-none me-2">
                    <i class="fas fa-user-plus me-1"></i> Create Account
                </a>
                <a href="reset_passwords.php" class="text-decoration-none">
                    <i class="fas fa-key me-1"></i> Forgot Password?
                </a>
            </div>
        </div>
    </div>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Focus on first input field in active tab
        document.querySelector('.tab-pane.active input').focus();
    </script>
</body>
</html>