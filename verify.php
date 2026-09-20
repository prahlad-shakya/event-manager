<?php
// verify.php
session_start();
require_once 'config/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (!empty($token)) {
    // Check if user exists with this token
    $stmt = $pdo->prepare("SELECT user_id, is_verified FROM users WHERE reset_token = :token");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        if ((int)$user['is_verified'] === 1) {
            $success = "Your account is already verified! You can sign in below.";
        } else {
            // Activate account and clear the verification token
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, reset_token = NULL WHERE user_id = :id");
            $update->execute(['id' => $user['user_id']]);
            $success = "Your email has been verified successfully! You can now log in.";
        }
    } else {
        $error = "Invalid or expired verification token.";
    }
} else {
    $error = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification | Event Management Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 p-4 text-center">
                <h3 class="fw-bold mb-3">Account Verification</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-4 small">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2 mb-4 small">
                        <?= htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <a href="login.php" class="btn btn-primary w-100 py-2 fw-bold rounded-3">Go to Sign In</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>