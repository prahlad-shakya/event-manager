<?php
// event-details.php
session_start();
require_once 'config/db.php';

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// Fetch Event Details
$stmt = $pdo->prepare("SELECT e.*, u.full_name as organizer_name 
                      FROM events e 
                      JOIN users u ON e.organizer_id = u.user_id 
                      WHERE e.event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: index.php");
    exit();
}

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ticket'])) {
    if (!isset($_SESSION['user_id'])) {
        // Redirect to login if user isn't signed in
        header("Location: login.php?redirect=" . urlencode("event-details.php?id=" . $event_id));
        exit();
    }

    $user_id  = $_SESSION['user_id'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if ($quantity < 1) {
        $error = "Please select at least 1 ticket.";
    } elseif ($quantity > $event['available_tickets']) {
        $error = "Not enough tickets available. Only " . $event['available_tickets'] . " left.";
    } else {
        $total_price = $quantity * $event['ticket_price'];

        try {
            // Begin Database Transaction
            $pdo->beginTransaction();

            // 1. Insert Record into Bookings Table
            $booking_sql = "INSERT INTO bookings (event_id, user_id, quantity, total_price, booking_date) 
                            VALUES (:event_id, :user_id, :quantity, :total_price, NOW())";
            $booking_stmt = $pdo->prepare($booking_sql);
            $booking_stmt->execute([
                ':event_id'    => $event_id,
                ':user_id'     => $user_id,
                ':quantity'    => $quantity,
                ':total_price' => $total_price
            ]);

            // 2. Reduce Available Tickets Count in Events Table
            $update_sql = "UPDATE events 
                           SET available_tickets = available_tickets - :qty 
                           WHERE event_id = :event_id AND available_tickets >= :req_qty";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':qty'      => $quantity,
                ':req_qty'  => $quantity,
                ':event_id' => $event_id
            ]);

            // Commit Transaction
            $pdo->commit();

            $success = "Congratulations! Your booking for " . $quantity . " ticket(s) is confirmed.";

            // Refresh event data to update available ticket count on page
            $stmt->execute([$event_id]);
            $event = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred while processing your booking: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']); ?> | Event Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">

    <!-- Navbar Navigation -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="index.php">
                <i class="fa-solid fa-bolt"></i> Event Management Portal
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Explore Events</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="btn btn-light btn-sm rounded-pill px-3 pe-none fw-semibold">
                        <i class="fa-solid fa-user text-primary me-1"></i> <?= htmlspecialchars($_SESSION['full_name']); ?>
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-primary-custom rounded-pill px-3">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php?category=<?= urlencode($event['category']); ?>"><?= htmlspecialchars($event['category']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['title']); ?></li>
            </ol>
        </nav>

        <!-- Feedback Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Event Media & Details -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <?php
                    $category_placeholders = [
                        'Technology' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1000&auto=format&fit=crop',
                        'Music' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=1000&auto=format&fit=crop',
                        'Business' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=1000&auto=format&fit=crop',
                        'Sports' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=1000&auto=format&fit=crop'
                    ];

                    $default_img = $category_placeholders[$event['category']] ?? 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1000&auto=format&fit=crop';
                    $image_src = (!empty($event['image_url']) && file_exists('uploads/' . $event['image_url'])) ? 'uploads/' . $event['image_url'] : $default_img;
                    ?>
                    <img src="<?= htmlspecialchars($image_src); ?>" class="img-fluid w-100" style="max-height: 400px; object-fit: cover;" alt="<?= htmlspecialchars($event['title']); ?>">
                </div>

                <h1 class="fw-bold mb-3"><?= htmlspecialchars($event['title']); ?></h1>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded-3 d-flex align-items-center gap-3">
                            <i class="fa-regular fa-calendar-check fs-3 text-primary"></i>
                            <div>
                                <small class="text-muted d-block text-uppercase fw-semibold">Date & Time</small>
                                <span class="fw-semibold small"><?= date('d M Y, h:i A', strtotime($event['event_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded-3 d-flex align-items-center gap-3">
                            <i class="fa-solid fa-location-dot fs-3 text-primary"></i>
                            <div>
                                <small class="text-muted d-block text-uppercase fw-semibold">Location</small>
                                <span class="fw-semibold small text-truncate d-block"><?= htmlspecialchars($event['location']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded-3 d-flex align-items-center gap-3">
                            <i class="fa-solid fa-ticket fs-3 text-primary"></i>
                            <div>
                                <small class="text-muted d-block text-uppercase fw-semibold">Availability</small>
                                <span class="fw-semibold small"><?= $event['available_tickets']; ?> Seats Left</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold mb-3">About This Event</h5>
                    <p class="text-secondary leading-relaxed m-0">
                        <?= !empty($event['description']) ? nl2br(htmlspecialchars($event['description'])) : 'No additional description provided for this event.'; ?>
                    </p>
                </div>
            </div>

            <!-- Right Column: Booking Widget -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 100px;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-muted fw-semibold">Ticket Price</span>
                        <h3 class="fw-bold text-primary m-0">
                            <?= $event['ticket_price'] > 0 ? '$' . number_format($event['ticket_price'], 2) : 'Free'; ?>
                        </h3>
                    </div>

                    <?php if ($event['available_tickets'] > 0): ?>
                        <form action="event-details.php?id=<?= $event['event_id']; ?>" method="POST">
                            <input type="hidden" name="book_ticket" value="1">

                            <div class="mb-3">
                                <label for="quantity" class="form-label small fw-semibold text-uppercase text-muted">Select Tickets</label>
                                <select class="form-select py-2" id="quantity" name="quantity" onchange="updateTotal(this.value, <?= $event['ticket_price']; ?>)">
                                    <?php for ($i = 1; $i <= min(5, $event['available_tickets']); $i++): ?>
                                        <option value="<?= $i; ?>"><?= $i; ?> <?= $i === 1 ? 'Ticket' : 'Tickets'; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3 mb-4">
                                <span class="fw-semibold">Total Amount:</span>
                                <span class="fw-bold fs-5 text-dark" id="total_display">
                                    $<?= number_format($event['ticket_price'], 2); ?>
                                </span>
                            </div>

                            <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold rounded-3 shadow-sm">
                                <i class="fa-solid fa-ticket me-2"></i> Confirm & Book Ticket
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 py-3 fw-bold rounded-3" disabled>
                            Sold Out
                        </button>
                    <?php endif; ?>

                    <div class="text-center mt-3 pt-3 border-top text-muted small">
                        <i class="fa-solid fa-shield-halved me-1 text-success"></i> Instant E-Ticket delivered to your profile.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTotal(quantity, price) {
            const total = (quantity * price).toFixed(2);
            document.getElementById('total_display').innerText = '$' + total;
        }
    </script>
</body>

</html>