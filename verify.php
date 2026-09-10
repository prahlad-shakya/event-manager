<?php
// verify.php
session_start();
require_once 'config/db.php';

$message = '';
$status_type = 'danger'; // danger or success

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Search for user with this verification token
    $stmt = $pdo->prepare("SELECT user_id, is_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ((int)$user['is_verified'] === 1) {
            $message = "Your email address is already verified. You can sign in below.";
            $status_type = "info";
        } else {
            // Update user status to verified and clear token
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
            if ($update->execute([$user['user_id']])) {
                $message = "Email successfully verified! You can now sign in to your account.";
                $status_type = "success";
            } else {
                $message = "Failed to verify email. Please try again later.";
            }
        }
    } else {
        $message = "Invalid or expired verification token.";
    }
} else {
    $message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | Event Management Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 rounded-4 p-4 text-center">
                <h3 class="fw-bold mb-3">Account Verification</h3>
                
                <div class="alert alert-<?= $status_type; ?> py-3 mb-4">
                    <?= htmlspecialchars($message); ?>
                </div>

                <a href="login.php" class="btn btn-primary w-100 py-2 fw-bold">Go to Sign In</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>