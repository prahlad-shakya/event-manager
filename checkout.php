<?php
// checkout.php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$reg_id = isset($_GET['reg_id']) ? intval($_GET['reg_id']) : 0;

$stmt = $pdo->prepare("SELECT r.*, e.title, e.event_date, e.location 
                       FROM registrations r 
                       JOIN events e ON r.event_id = e.event_id 
                       WHERE r.registration_id = :rid AND r.user_id = :uid");
$stmt->execute(['rid' => $reg_id, 'uid' => $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking receipt not found. <a href='index.php'>Return Home</a>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Confirmation</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Event Management Portal</h1>
        <nav><a href="index.php">Home</a></nav>
    </header>

    <div class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto; text-align: center;">
            <h2 style="color:green;">Booking Confirmed!</h2>
            <p>Thank you for your purchase, <?= htmlspecialchars($_SESSION['full_name']); ?>.</p>
            
            <div style="background:#f8f9fa; padding:1.5rem; border-radius:6px; margin:1.5rem 0; text-align:left;">
                <p><strong>Booking Ref ID:</strong> #<?= $booking['registration_id']; ?></p>
                <p><strong>Event Title:</strong> <?= htmlspecialchars($booking['title']); ?></p>
                <p><strong>Event Date:</strong> <?= date('d M Y, h:i A', strtotime($booking['event_date'])); ?></p>
                <p><strong>Venue:</strong> <?= htmlspecialchars($booking['location']); ?></p>
                <p><strong>Tickets Issued:</strong> <?= $booking['tickets_purchased']; ?></p>
                <p><strong>Total Amount Paid:</strong> $<?= number_format($booking['total_paid'], 2); ?></p>
                <p><strong>Status:</strong> <span style="color:green; font-weight:bold;"><?= $booking['attendance_status']; ?></span></p>
            </div>

            <a href="index.php" class="btn">Explore More Events</a>
            <button onclick="window.print()" class="btn" style="background:#7f8c8d;">Print Receipt</button>
        </div>
    </div>
</body>
</html>