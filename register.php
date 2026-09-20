<?php
// register.php
session_start();
require_once 'config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust path if PHPMailer is placed in a custom folder

define('ADMIN_PASSCODE', 'ADMIN');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $selected_role = $_POST['role'] ?? 'attendee';
    $admin_code    = trim($_POST['admin_code'] ?? '');

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            $error = "An account with this email address already exists.";
        } else {
            $final_role = 'attendee';

            if ($selected_role === 'organizer') {
                if ($admin_code === ADMIN_PASSCODE) {
                    $final_role = 'organizer';
                } else {
                    $error = "Invalid Admin Passcode. You cannot register as an Organizer without authorization.";
                }
            }

            if (empty($error)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $verification_token = bin2hex(random_bytes(32));

                // Insert user with is_verified = 0 and save verification_token
                $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, is_verified, reset_token, created_at) VALUES (:full_name, :email, :password_hash, :role, 0, :token, NOW())");
                
                $registered = $insert_stmt->execute([
                    'full_name'     => $full_name,
                    'email'         => $email,
                    'password_hash' => $hashed_password,
                    'role'          => $final_role,
                    'token'         => $verification_token
                ]);

                if ($registered) {
                    // Send Email Verification via PHPMailer
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $verify_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/event_portal/verify.php?token=" . $verification_token;

                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'prahladshakya9872@gmail.com'; // Your Gmail address
                        $mail->Password   = 'bdjcxpfmpdnodnsm';   // Paste your 16-character Google App Password here
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('prahladshakya9872@gmail.com', 'Event Portal');
                        $mail->addAddress($email, $full_name);

                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your Account - Event Portal';
                        $mail->Body    = "
                            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                                <h2>Welcome to Event Portal!</h2>
                                <p>Hello " . htmlspecialchars($full_name) . ",</p>
                                <p>Thank you for registering. Please verify your email address to activate your account:</p>
                                <p style='margin: 25px 0;'>
                                    <a href='{$verify_link}' style='background-color: #4F46E5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Verify Email Address</a>
                                </p>
                            </div>
                        ";

                        $mail->send();
                        $success = "Account created! Check your inbox to verify your email before logging in.";
                    } catch (Exception $e) {
                        $error = "Account created, but verification email failed to send: " . $mail->ErrorInfo;
                    }
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account | Event Management Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-5">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="text-center mb-4">
                        <a href="index.php" class="text-decoration-none text-dark fs-4 fw-bold">
                            <i class="fa-solid fa-bolt text-primary me-2"></i>Event Portal
                        </a>
                        <h4 class="fw-bold mt-3 mb-1">Create an Account</h4>
                        <p class="text-muted small">Join us to discover or host incredible events</p>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success); ?>
                            <a href="login.php" class="d-block mt-2 fw-bold text-decoration-none">Proceed to Login &rarr;</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="register.php" method="POST">
                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="John Doe">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Create a strong password">
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label fw-semibold">Account Type *</label>
                            <select class="form-select" id="role" name="role" onchange="toggleAdminCodeField(this.value)">
                                <option value="attendee" selected>Attendee (Book & Explore Events)</option>
                                <option value="organizer">Event Organizer / Admin (Create Events)</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="admin_code_wrapper">
                            <label for="admin_code" class="form-label fw-semibold text-danger">
                                <i class="fa-solid fa-key me-1"></i> Admin Security Passcode *
                            </label>
                            <input type="password" class="form-control border-danger" id="admin_code" name="admin_code" placeholder="Enter authorization passcode">
                            <small class="text-muted">Required only if registering as an Organizer/Admin.</small>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold fs-5 mt-3">
                            Register Account
                        </button>
                    </form>

                    <div class="text-center mt-4 border-top pt-3">
                        <span class="text-muted small">Already have an account?</span>
                        <a href="login.php" class="fw-bold text-decoration-none ms-1">Sign In</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAdminCodeField(role) {
            const wrapper = document.getElementById('admin_code_wrapper');
            const adminInput = document.getElementById('admin_code');

            if (role === 'organizer') {
                wrapper.classList.remove('d-none');
                adminInput.setAttribute('required', 'required');
            } else {
                wrapper.classList.add('d-none');
                adminInput.removeAttribute('required');
                adminInput.value = '';
            }
        }
    </script>
</body>
</html>