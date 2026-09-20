<?php
session_start();
require_once 'config/db.php'; // Include PDO database connection

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch current user details
$stmt = $pdo->prepare("SELECT name, email, profile_picture, password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. UPDATE PROFILE INFO & PICTURE
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $profile_picture = $user['profile_picture'];

        if (empty($name) || empty($email)) {
            $errors[] = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        // Handle Image Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Only JPG, PNG, and WEBP images are allowed.";
            } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
                $errors[] = "Image size must be under 2MB.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = 'uploads/avatars/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    // Remove old picture if it's not default
                    if (!empty($user['profile_picture']) && file_exists($upload_dir . $user['profile_picture'])) {
                        unlink($upload_dir . $user['profile_picture']);
                    }
                    $profile_picture = $filename;
                } else {
                    $errors[] = "Failed to upload avatar.";
                }
            }
        }

        if (empty($errors)) {
            $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_picture = ? WHERE id = ?");
            $update_stmt->execute([$name, $email, $profile_picture, $user_id]);
            
            $_SESSION['full_name'] = $name; // Update session
            $success = "Profile updated successfully!";
            $user['name'] = $name;
            $user['email'] = $email;
            $user['profile_picture'] = $profile_picture;
        }
    }

    // 2. CHANGE PASSWORD
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = "All password fields are required.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = "Incorrect current password.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $pass_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $pass_stmt->execute([$hashed_password, $user_id]);
            $success = "Password updated successfully!";
        }
    }
}

// Fallback image generator using initials if picture missing or unavailable
$default_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=4f46e5&color=ffffff&size=150';
$avatar_path = !empty($user['profile_picture']) && file_exists('uploads/avatars/' . $user['profile_picture']) 
    ? 'uploads/avatars/' . htmlspecialchars($user['profile_picture']) 
    : $default_avatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <!-- Google Font & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="profile-container">
    
    <!-- Header Controls: Title & Navigation Buttons -->
    <div class="profile-header-nav">
        <div>
            <h1 class="profile-title">Account Settings</h1>
            <p class="profile-subtitle">Manage your personal details and account security settings.</p>
        </div>
        <div class="nav-actions">
            <button onclick="history.back()" class="btn-nav btn-nav-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
            </button>
            <a href="index.php" class="btn-nav btn-nav-primary">
                <i class="fa-solid fa-house"></i> Home
            </a>
        </div>
    </div>

    <!-- System Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <p style="margin: 0;"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Profile Details Form -->
    <div class="card">
        <div class="card-header">
            <h3>Edit Profile</h3>
            <p>Update your display photo, name, and contact email address.</p>
        </div>
        <form method="POST" action="profile.php" enctype="multipart/form-data">
            <div class="form-group">
                <label>Profile Picture</label>
                <div class="avatar-section">
                    <img src="<?= $avatar_path ?>" alt="User Avatar" class="avatar-preview">
                    <div class="avatar-controls">
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/png, image/jpeg, image/webp" hidden>
                        <label for="profile_picture" class="avatar-upload-label">
                            <i class="fa-solid fa-camera"></i> Choose New Photo
                        </label>
                        <span class="file-hint">Allowed formats: JPG, PNG, WEBP (Max 2MB)</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="Enter your email address">
            </div>

            <div class="form-footer">
                <button type="submit" name="update_profile" class="btn-primary-custom">
                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Password Change Form -->
    <div class="card">
        <div class="card-header">
            <h3>Change Password</h3>
            <p>Ensure your account is using a long, random password to stay secure.</p>
        </div>
        <form method="POST" action="profile.php">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="At least 8 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Repeat new password">
            </div>

            <div class="form-footer">
                <button type="submit" name="change_password" class="btn-primary-custom">
                    <i class="fa-solid fa-key"></i> Update Password
                </button>
            </div>
        </form>
    </div>

</div>

</body>
</html>