<?php
// login.php
session_start();
require_once 'config/db.php';

// Handle post-login redirects (e.g., coming from booking on event-details.php)
$redirect_url = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (!empty($redirect_url)) {
        header("Location: " . $redirect_url);
    } elseif ($_SESSION['role'] === 'organizer') {
        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Fetch user record by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 1. Password Verification using 'password_hash' column
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 2. Check if account is verified
            if ((int)$user['is_verified'] === 0) {
                $error = "Please verify your email address before logging in. Check your inbox for the link.";
            } else {
                // 3. Set Session Variables
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role']; // 'organizer' or 'attendee'

                // 4. Route according to redirect parameter or role
                if (!empty($redirect_url)) {
                    header("Location: " . $redirect_url);
                } elseif ($user['role'] === 'organizer') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Event Management Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="text-center mb-4">
                    <a href="index.php" class="text-decoration-none text-dark fs-4 fw-bold">
                        <i class="fa-solid fa-bolt text-primary me-2"></i>Event Portal
                    </a>
                    <h4 class="fw-bold mt-3 mb-1">Sign In</h4>
                    <p class="text-muted small">Access your Event Portal account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 mb-3 small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <!-- Preserve redirect URL through form POST -->
                    <?php if (!empty($redirect_url)): ?>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_url); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Password Field with Forgot Password Link -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Password</label>
                            <a href="reset-password.php" class="small text-decoration-none text-primary fw-semibold">Forgot Password?</a>
                        </div>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold rounded-3">Sign In</button>
                </form>

                <div class="text-center mt-4 border-top pt-3">
                    <small class="text-muted">Don't have an account? <a href="register.php" class="text-decoration-none fw-semibold">Register</a></small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>