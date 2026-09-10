<?php
// dashboard.php
session_start();
require_once 'config/db.php';

// Authorization Guard: Only allow logged-in users with 'organizer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Event Creation POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title             = trim($_POST['title']);
    $category          = trim($_POST['category']);
    $description       = trim($_POST['description']);
    $event_date        = trim($_POST['event_date']);
    $location          = trim($_POST['location']);
    $ticket_price      = floatval($_POST['ticket_price']);
    $available_tickets = intval($_POST['available_tickets']);
    $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

    $image_filename = null;

    // Handle File Upload
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['event_image']['tmp_name'];
        $file_name  = $_FILES['event_image']['name'];
        $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed    = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed)) {
            $image_filename = uniqid('event_', true) . '.' . $file_ext;
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            move_uploaded_file($file_tmp, 'uploads/' . $image_filename);
        } else {
            $error = "Invalid image format. Only JPG, JPEG, PNG, and WEBP allowed.";
        }
    }

    if (empty($error)) {
        if (empty($title) || empty($category) || empty($event_date) || empty($location) || $available_tickets < 1) {
            $error = "Please complete all required fields.";
        } else {
            $sql = "INSERT INTO events (organizer_id, title, category, description, event_date, location, ticket_price, total_tickets, available_tickets, image_url, is_featured, created_at) 
                    VALUES (:organizer_id, :title, :category, :description, :event_date, :location, :ticket_price, :total_tickets, :available_tickets, :image_url, :is_featured, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $created = $stmt->execute([
                'organizer_id'      => $organizer_id,
                'title'             => $title,
                'category'          => $category,
                'description'       => $description,
                'event_date'        => $event_date,
                'location'          => $location,
                'ticket_price'      => $ticket_price,
                'total_tickets'     => $available_tickets,
                'available_tickets' => $available_tickets,
                'image_url'         => $image_filename,
                'is_featured'       => $is_featured
            ]);

            if ($created) {
                $success = "Event successfully published!";
            } else {
                $error = "An error occurred while creating the event.";
            }
        }
    }
}

// Fetch Organizer's Listed Events
$events_stmt = $pdo->prepare("SELECT * FROM events WHERE organizer_id = ? ORDER BY event_id DESC");
$events_stmt->execute([$organizer_id]);
$my_events = $events_stmt->fetchAll();

// Fetch Attendee Registrations / Bookings
$bookings_sql = "SELECT b.*, e.title as event_title, u.full_name as attendee_name, u.email as attendee_email
                FROM bookings b
                JOIN events e ON b.event_id = e.event_id
                JOIN users u ON b.user_id = u.user_id
                WHERE e.organizer_id = ?
                ORDER BY b.booking_date DESC";
$bookings_stmt = $pdo->prepare($bookings_sql);
$bookings_stmt->execute([$organizer_id]);
$registrations = $bookings_stmt->fetchAll();

// Calculate Analytics Metrics
$total_events_count  = count($my_events);
$total_tickets_sold  = 0;
$total_revenue_earned = 0.00;

foreach ($my_events as $ev) {
    $sold = $ev['total_tickets'] - $ev['available_tickets'];
    $total_tickets_sold += $sold;
    $total_revenue_earned += ($sold * $ev['ticket_price']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organiser Dashboard | Event Portal</title>
    
    <!-- Bootstrap 5 CSS & FontAwesome Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light min-vh-100">

    <!-- Top Navigation Navbar -->
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="index.php">
                <i class="fa-solid fa-bolt fs-4"></i>
                <span>Event Portal</span>
            </a>
            
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-globe me-1"></i> Public Portal
                </a>
                <div class="vr my-2"></div>
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <?= strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <span class="fw-semibold small d-none d-sm-inline"><?= htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Dashboard Container -->
    <main class="container my-5">
        
        <!-- Welcome Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold m-0 text-slate-900">Organiser Dashboard</h2>
                <p class="text-muted small m-0">Manage your published events, view ticket sales, and post new experiences.</p>
            </div>
            <a href="#create-event-card" class="btn btn-primary-custom rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Post New Event
            </a>
        </div>

        <!-- Metric Analytics Cards -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary-subtle text-primary rounded-4 fs-3">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Hosted Events</span>
                            <h3 class="fw-bold m-0"><?= number_format($total_events_count); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-success-subtle text-success rounded-4 fs-3">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Tickets Sold</span>
                            <h3 class="fw-bold m-0"><?= number_format($total_tickets_sold); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-warning-subtle text-warning rounded-4 fs-3">
                            <i class="fa-solid fa-sack-dollar"></i>
                        </div>
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Estimated Revenue</span>
                            <h3 class="fw-bold m-0">$<?= number_format($total_revenue_earned, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Form Card: Create New Event -->
        <div class="card border-0 shadow-sm rounded-4 mb-5" id="create-event-card">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold m-0"><i class="fa-solid fa-circle-plus text-primary me-2"></i>Create New Event</h5>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="create_event" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Event Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Annual Tech Leadership Summit" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="Technology">Technology</option>
                                <option value="Music">Music</option>
                                <option value="Business">Business</option>
                                <option value="Sports">Sports</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Event Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide a brief overview of topics, speakers, or schedule..."></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Date & Time *</label>
                            <input type="datetime-local" name="event_date" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Location / Venue *</label>
                            <input type="text" name="location" class="form-control" placeholder="City or Hall Name" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Price ($) *</label>
                            <input type="number" step="0.01" min="0" name="ticket_price" class="form-control" value="0.00" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Total Tickets *</label>
                            <input type="number" min="1" name="available_tickets" class="form-control" value="50" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Event Banner Image</label>
                            <input type="file" name="event_image" class="form-control" accept="image/*">
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1">
                                <label class="form-check-label fw-semibold small" for="is_featured">Promote as Featured Event</label>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary-custom px-5 py-2 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i>Publish Event
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Table Card: Listed Events -->
        <div class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Your Listed Events</h5>
                <span class="badge bg-light text-dark border fw-semibold px-3 py-2"><?= count($my_events); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($my_events) > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th class="ps-4">Event Details</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Tickets Sold</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_events as $ev): ?>
                                    <?php 
                                        $sold = $ev['total_tickets'] - $ev['available_tickets']; 
                                        $percentage = round(($sold / $ev['total_tickets']) * 100);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold">
                                            <div class="d-flex align-items-center gap-3">
                                                <div>
                                                    <div class="text-dark"><?= htmlspecialchars($ev['title']); ?></div>
                                                    <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($ev['location']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3"><?= htmlspecialchars($ev['category']); ?></span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d M Y, h:i A', strtotime($ev['event_date'])); ?>
                                        </td>
                                        <td class="fw-bold">
                                            <?= $ev['ticket_price'] > 0 ? '$' . number_format($ev['ticket_price'], 2) : 'Free'; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold mb-1"><?= $sold; ?> / <?= $ev['total_tickets']; ?> (<?= $percentage; ?>%)</div>
                                            <div class="progress" style="height: 6px; width: 120px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percentage; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="event-details.php?id=<?= $ev['event_id']; ?>" class="btn btn-sm btn-light border me-1" title="View Public Page">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-calendar-xmark fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted m-0">No events created yet. Use the form above to add your first event.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Table Card: Attendee Registrations -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fa-solid fa-users text-primary me-2"></i>Attendee Registrations & Tracking</h5>
                <span class="badge bg-light text-dark border fw-semibold px-3 py-2"><?= count($registrations); ?> Bookings</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($registrations) > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover">
                            <thead class="table-light">
                                <tr class="text-muted small text-uppercase">
                                    <th class="ps-4">Attendee</th>
                                    <th>Event Name</th>
                                    <th>Quantity</th>
                                    <th>Booking Date</th>
                                    <th class="text-end pe-4">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $reg): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($reg['attendee_name']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($reg['attendee_email']); ?></div>
                                        </td>
                                        <td class="fw-semibold text-primary">
                                            <?= htmlspecialchars($reg['event_title']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info fw-bold px-3 py-2 rounded-pill">
                                                <?= $reg['quantity']; ?> Ticket(s)
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d M Y, h:i A', strtotime($reg['booking_date'])); ?>
                                        </td>
                                        <td class="text-end pe-4 fw-bold">
                                            $<?= number_format($reg['total_price'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-ticket-simple fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted m-0">No ticket registrations recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>

</html>