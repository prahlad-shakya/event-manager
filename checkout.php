<?php
// checkout.php
session_start();
require_once 'config/db.php';

$event_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['event_id']) ? intval($_POST['event_id']) : 0);
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : (isset($_POST['quantity']) ? intval($_POST['quantity']) : 1);
$error = '';

// Check if user is logged in. If not, send to login with a redirect back here.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("checkout.php?id=" . $event_id . "&quantity=" . $quantity));
    exit();
}

// Fetch Event Details & Organizer Info
$stmt = $pdo->prepare("SELECT e.*, u.name as organizer_name 
                      FROM events e 
                      JOIN users u ON e.organizer_id = u.id 
                      WHERE e.event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: index.php");
    exit();
}

// Validate quantity
if ($quantity < 1) {
    $quantity = 1;
} elseif ($quantity > $event['available_tickets']) {
    $quantity = max(1, $event['available_tickets']);
}

$total_price = $quantity * $event['ticket_price'];

// Handle Final Booking Confirmation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $user_id = $_SESSION['user_id'];

    if ($quantity > $event['available_tickets']) {
        $error = "Not enough tickets available anymore.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert booking
            $booking_sql = "INSERT INTO bookings (event_id, user_id, quantity, total_price, booking_date) 
                            VALUES (:event_id, :user_id, :quantity, :total_price, NOW())";
            $booking_stmt = $pdo->prepare($booking_sql);
            $booking_stmt->execute([
                ':event_id'    => $event_id,
                ':user_id'     => $user_id,
                ':quantity'    => $quantity,
                ':total_price' => $total_price
            ]);

            // 2. Update tickets count
            $update_sql = "UPDATE events 
                           SET available_tickets = available_tickets - :qty 
                           WHERE event_id = :event_id AND available_tickets >= :req_qty";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':qty'      => $quantity,
                ':req_qty'  => $quantity,
                ':event_id' => $event_id
            ]);

            $pdo->commit();

            // Redirect back to event with success flag
            header("Location: event-details.php?id=" . $event_id . "&success=1");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred while processing your transaction: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | Event Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="index.php">
                <i class="fa-solid fa-bolt"></i> Event Portal Checkout
            </a>
            <div class="ms-auto text-muted small">
                <i class="fa-solid fa-lock text-success me-1"></i> 256-Bit SSL Secure Checkout
            </div>
        </div>
    </nav>

    <!-- Main Checkout Container -->
    <div class="container my-5" style="max-width: 1000px;">
        
        <div class="mb-4">
            <a href="event-details.php?id=<?= $event_id; ?>" class="text-decoration-none text-muted fw-semibold small">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Event Details
            </a>
        </div>

        <h2 class="fw-bold mb-4">Complete Your Order</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger mb-4 rounded-3 shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Billing & Payment Information Form -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h5 class="fw-bold mb-3"><i class="fa-regular fa-user text-primary me-2"></i> Billing Details</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-muted fw-semibold">Full Name</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Guest User'); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0"><i class="fa-regular fa-credit-card text-primary me-2"></i> Payment Details</h5>
                        <div class="text-muted fs-5">
                            <i class="fa-brands fa-cc-visa text-primary me-1"></i>
                            <i class="fa-brands fa-cc-mastercard text-danger me-1"></i>
                            <i class="fa-brands fa-cc-amex text-success"></i>
                        </div>
                    </div>
                    
                    <p class="text-muted small mb-4">All transactions are secure and encrypted. (Sandbox/Demo Mode)</p>

                    <form action="checkout.php" method="POST">
                        <input type="hidden" name="event_id" value="<?= $event_id; ?>">
                        <input type="hidden" name="quantity" value="<?= $quantity; ?>">
                        <input type="hidden" name="confirm_booking" value="1">

                        <div class="mb-3">
                            <label class="form-label small text-muted fw-semibold">Card Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-credit-card"></i></span>
                                <input type="text" class="form-control" placeholder="4242 •••• •••• 4242" value="4242 4242 4242 4242" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted fw-semibold">Expiration Date</label>
                                <input type="text" class="form-control" placeholder="MM/YY" value="12/28" readonly>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted fw-semibold">Security Code (CVV)</label>
                                <input type="text" class="form-control" placeholder="123" value="888" readonly>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">
                            <i class="fa-solid fa-lock me-2"></i> Pay $<?= number_format($total_price, 2); ?> & Confirm Booking
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Event Order Summary Card -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 100px;">
                    <h5 class="fw-bold mb-3">Order Summary</h5>
                    
                    <div class="d-flex gap-3 align-items-center mb-3 pb-3 border-bottom">
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($event['title']); ?></h6>
                            <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y, h:i A', strtotime($event['event_date'])); ?></small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between py-2 text-secondary small">
                        <span>Ticket Price</span>
                        <span>$<?= number_format($event['ticket_price'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 text-secondary small">
                        <span>Quantity</span>
                        <span><?= $quantity; ?> <?= $quantity === 1 ? 'Ticket' : 'Tickets'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 text-secondary small">
                        <span>Processing Fee</span>
                        <span class="text-success fw-semibold">Free</span>
                    </div>

                    <hr class="text-muted">

                    <div class="d-flex justify-content-between py-2 align-items-center mb-3">
                        <span class="fw-bold text-dark">Total Due</span>
                        <span class="fw-bold fs-4 text-primary">$<?= number_format($total_price, 2); ?></span>
                    </div>

                    <div class="bg-light p-3 rounded-3 text-muted small">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Instant access to your digital ticket upon completion.
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>