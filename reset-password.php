<?php
// reset-password.php
session_start();
require_once 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$message = '';
$error = '';

// Clear session if opening page fresh via GET request without restart flag
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['restart'])) {
    unset($_SESSION['reset_email'], $_SESSION['reset_step']);
}

$step = $_SESSION['reset_step'] ?? 1; // Step 1: Email, Step 2: Verify OTP & New Password

// STEP 1: Send 6-Digit OTP Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = random_int(100000, 999999); // Generate 6-digit OTP
            $expires = (string)(time() + 900); // 15 minutes in seconds

            $update = $pdo->prepare("UPDATE users SET reset_token = :otp, reset_expires_at = :expires WHERE user_id = :id");
            $update->execute([
                'otp'     => (string)$otp,
                'expires' => $expires,
                'id'      => $user['user_id']
            ]);

            // Send Email via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'prahladshakya9872@gmail.com';
                $mail->Password   = 'bdjcxpfmpdnodnsm'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('prahladshakya9872@gmail.com', 'Event Portal');
                $mail->addAddress($email, $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Your Password Reset OTP - Event Portal';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                        <h2>Password Reset Request</h2>
                        <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                        <p>Your 6-digit OTP code to reset your password is:</p>
                        <h1 style='background: #f4f4f4; padding: 10px 20px; display: inline-block; letter-spacing: 4px; color: #4F46E5;'>{$otp}</h1>
                        <p>This code will expire in 15 minutes.</p>
                    </div>";

                $mail->send();
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_step'] = 2;
                $step = 2;
                $message = "OTP sent to your email address!";
            } catch (Exception $e) {
                $error = "Email failure: " . $mail->ErrorInfo;
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
}

// STEP 2: Verify OTP and Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $email            = $_SESSION['reset_email'] ?? '';
    $entered_otp      = trim($_POST['otp'] ?? '');
    $new_password     = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($entered_otp) || empty($new_password)) {
        $error = "Please fill in all required fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Fetch the user's stored token and expiration timestamp directly
        $stmt = $pdo->prepare("SELECT user_id, reset_token, reset_expires_at FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && !empty($user['reset_token'])) {
            $db_otp     = (string)$user['reset_token'];
            $expires_at = (int)$user['reset_expires_at'];

            // Validate match and verify timestamp in PHP
            if ($db_otp === $entered_otp && $expires_at > time()) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                
                // Update password and clear reset data
                $update = $pdo->prepare("UPDATE users SET password_hash = :pass, reset_token = NULL, reset_expires_at = NULL WHERE user_id = :id");
                $update->execute([
                    'pass' => $hashed_password,
                    'id'   => $user['user_id']
                ]);

                unset($_SESSION['reset_email'], $_SESSION['reset_step']);
                $step = 3; // Success state
            } elseif ($expires_at <= time()) {
                $error = "OTP has expired. Please click 'Resend OTP' below.";
            } else {
                $error = "Incorrect OTP code. Please check your email.";
            }
        } else {
            $error = "Session invalid. Please start over.";
        }
    }
}

// Reset flow step if user clicks start over
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_email'], $_SESSION['reset_step']);
    header("Location: reset-password.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Event Management Portal</title>
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
                    <h4 class="fw-bold mt-3 mb-1">Reset Password</h4>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-3 small"><i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success py-2 mb-3 small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <!-- FORM STEP 1: Enter Email to Get OTP -->
                <?php if ($step === 1): ?>
                    <form action="reset-password.php" method="POST">
                        <input type="hidden" name="action" value="send_otp">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold rounded-3">Send OTP</button>
                    </form>
                <?php endif; ?>

                <!-- FORM STEP 2: Enter OTP & New Password -->
                <?php if ($step === 2): ?>
                    <form action="reset-password.php" method="POST">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Enter 6-Digit OTP</label>
                            <input type="text" name="otp" class="form-control text-center fw-bold fs-5" placeholder="Enter your OTP" maxlength="6" autocomplete="off" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">New Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold rounded-3">Update Password</button>
                    </form>
                    <div class="text-center mt-2">
                        <a href="reset-password.php?restart=1" class="small text-muted text-decoration-none">Resend OTP / Start Over</a>
                    </div>
                <?php endif; ?>

                <!-- STEP 3: Success Screen -->
                <?php if ($step === 3): ?>
                    <div class="alert alert-success py-3 text-center small mb-3">
                        <i class="fa-solid fa-circle-check fa-2x mb-2 text-success"></i><br>
                        Password updated successfully!
                    </div>
                    <a href="login.php" class="btn btn-primary-custom w-100 py-2 fw-bold rounded-3">Sign In Now</a>
                <?php endif; ?>

                <?php if ($step !== 3): ?>
                    <div class="text-center mt-4 border-top pt-3">
                        <a href="login.php" class="text-decoration-none small text-muted">Back to Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>