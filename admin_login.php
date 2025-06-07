<?php
// admin_login.php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_dashboard.php");
    exit;
}

// Check for remember token
if (!empty($_COOKIE['admin_remember_token'])) {
    require 'db.php';
    try {
        $stmt = $conn->prepare("SELECT id, name, email FROM admin WHERE remember_token = ? AND token_expires > NOW()");
        $stmt->execute([$_COOKIE['admin_remember_token']]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            
            header("Location: admin_dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Remember token error: " . $e->getMessage());
    }
}

// Clear previous error if not from POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['admin_login_error'])) {
    unset($_SESSION['admin_login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitZone - Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            text-align: center;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-card">
                    <div class="card-header">
                        <i class="fas fa-dumbbell fa-3x mb-3"></i>
                        <h2>FitZone Admin</h2>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($_SESSION['admin_login_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($_SESSION['admin_login_error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['admin_login_error']); endif; ?>
                        
                        <form action="admin_authenticate.php" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
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
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>