<?php
// index.php
session_start();
require_once 'config/db.php';

// Handle Filter and Search Inputs
$category  = isset($_GET['category']) ? trim($_GET['category']) : '';
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$location  = isset($_GET['location']) ? trim($_GET['location']) : '';
$timeframe = isset($_GET['timeframe']) ? trim($_GET['timeframe']) : '';

// Base Query
$sql = "SELECT e.*, u.full_name as organizer_name 
        FROM events e 
        JOIN users u ON e.organizer_id = u.user_id 
        WHERE 1=1";
$params = [];

if (!empty($category)) {
    $sql .= " AND e.category = :category";
    $params['category'] = $category;
}

if (!empty($search)) {
    $sql .= " AND (e.title LIKE :search1 OR e.description LIKE :search2 OR e.location LIKE :search3)";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

if (!empty($location)) {
    $sql .= " AND e.location LIKE :location";
    $params['location'] = "%$location%";
}

if (!empty($timeframe)) {
    if ($timeframe === 'today') {
        $sql .= " AND DATE(e.event_date) = CURDATE()";
    } elseif ($timeframe === 'this_week') {
        $sql .= " AND YEARWEEK(e.event_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($timeframe === 'this_month') {
        $sql .= " AND MONTH(e.event_date) = MONTH(CURDATE()) AND YEAR(e.event_date) = YEAR(CURDATE())";
    }
}

$sql .= " ORDER BY e.is_featured DESC, e.event_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Dynamic Counters
$total_events_count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_tickets_sold = $pdo->query("SELECT IFNULL(SUM(total_tickets - available_tickets), 0) FROM events")->fetchColumn();
$total_organizers   = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM users WHERE role = 'organizer'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management Portal | Discover & Book Events</title>

    <!-- Bootstrap 5 CSS & FontAwesome Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- External Custom CSS File -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-bolt"></i> Event Management Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-2 mt-3 mt-lg-0">
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="index.php">Explore Events</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#how-it-works">How It Works</a></li>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'organizer'): ?>
                            <li class="nav-item">
                                <a class="nav-link fw-semibold text-primary" href="dashboard.php">
                                    <i class="fa-solid fa-chart-line me-1"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Logged In: User Display Badge -->
                        <li class="nav-item ms-lg-2">
                            <span class="btn btn-light px-3 rounded-pill fw-semibold d-inline-flex align-items-center pe-none">
                                <i class="fa-solid fa-circle-user me-1 text-primary"></i>
                                <span><?= htmlspecialchars($_SESSION['full_name']); ?></span>
                            </span>
                        </li>

                        <!-- Logged In: Direct Logout Button -->
                        <li class="nav-item">
                            <a class="btn btn-outline-danger rounded-pill px-3 fw-semibold d-inline-flex align-items-center" href="logout.php">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Logged Out Navigation -->
                        <li class="nav-item"><a class="nav-link fw-semibold" href="login.php">Sign In</a></li>
                        <li class="nav-item">
                            <a class="btn btn-primary-custom rounded-pill px-4" href="register.php">Get Started</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Banner with Stats Bar -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="hero-title mb-3">Discover Extraordinary<br>Events Near You</h1>
            <p class="lead opacity-75 max-w-2xl mx-auto fw-normal mb-5">
                Book tickets for top tech summits, live concerts, business conferences, and sports events in seconds.
            </p>

            <!-- Live Statistics Counter Bar -->
            <div class="row g-3 justify-content-center max-w-4xl mx-auto">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($total_events_count); ?>+</div>
                        <div class="small opacity-75 text-uppercase fw-semibold">Active Events</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($total_tickets_sold); ?>+</div>
                        <div class="small opacity-75 text-uppercase fw-semibold">Tickets Booked</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format(max($total_organizers, 12)); ?>+</div>
                        <div class="small opacity-75 text-uppercase fw-semibold">Event Hosts</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number">99.8%</div>
                        <div class="small opacity-75 text-uppercase fw-semibold">Happy Guests</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Bar with City & Time Filters -->
    <div class="container mb-5">
        <div class="search-card">
            <form action="index.php" method="GET" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 py-2" placeholder="Search keywords..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-layer-group"></i></span>
                        <select name="category" class="form-select border-start-0 py-2">
                            <option value="">All Categories</option>
                            <option value="Technology" <?= $category === 'Technology' ? 'selected' : ''; ?>>Technology</option>
                            <option value="Music" <?= $category === 'Music' ? 'selected' : ''; ?>>Music</option>
                            <option value="Business" <?= $category === 'Business' ? 'selected' : ''; ?>>Business</option>
                            <option value="Sports" <?= $category === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-location-dot"></i></span>
                        <input type="text" name="location" class="form-control border-start-0 py-2" placeholder="City or venue..." value="<?= htmlspecialchars($location); ?>">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-calendar-day"></i></span>
                        <select name="timeframe" class="form-select border-start-0 py-2">
                            <option value="">Any Time</option>
                            <option value="today" <?= $timeframe === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?= $timeframe === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?= $timeframe === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-custom w-100 py-2">Find</button>
                    <?php if (!empty($search) || !empty($category) || !empty($location) || !empty($timeframe)): ?>
                        <a href="index.php" class="btn btn-outline-secondary py-2" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex gap-2 flex-wrap mt-3 pt-3 border-top align-items-center">
                <span class="text-muted fw-bold small me-2">Trending:</span>
                <a href="index.php" class="category-pill <?= empty($category) ? 'active' : ''; ?>">All Events</a>
                <a href="index.php?category=Technology" class="category-pill <?= $category === 'Technology' ? 'active' : ''; ?>"><i class="fa-solid fa-laptop-code me-1"></i> Tech</a>
                <a href="index.php?category=Music" class="category-pill <?= $category === 'Music' ? 'active' : ''; ?>"><i class="fa-solid fa-music me-1"></i> Music</a>
                <a href="index.php?category=Business" class="category-pill <?= $category === 'Business' ? 'active' : ''; ?>"><i class="fa-solid fa-briefcase me-1"></i> Business</a>
                <a href="index.php?category=Sports" class="category-pill <?= $category === 'Sports' ? 'active' : ''; ?>"><i class="fa-solid fa-basketball me-1"></i> Sports</a>
            </div>
        </div>
    </div>

    <!-- Main Events Directory -->
    <main class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0 text-slate-900">
                <?= !empty($category) ? htmlspecialchars($category) . ' Events' : 'Upcoming Featured Events'; ?>
            </h2>
            <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill">
                <?= count($events); ?> <?= count($events) === 1 ? 'Event' : 'Events'; ?> Available
            </span>
        </div>

        <?php if (count($events) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($events as $event): ?>
                    <div class="col">
                        <div class="card event-card h-100">
                            <!-- Clickable Image Wrapper -->
                            <a href="event-details.php?id=<?= $event['event_id']; ?>" class="card-img-wrapper d-block text-decoration-none">
                                <?php if ($event['is_featured']): ?>
                                    <span class="badge-featured"><i class="fa-solid fa-star me-1"></i> FEATURED</span>
                                <?php endif; ?>

                                <span class="badge-category"><?= htmlspecialchars($event['category']); ?></span>

                                <?php
                                $category_placeholders = [
                                    'Technology' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&auto=format&fit=crop',
                                    'Music' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=600&auto=format&fit=crop',
                                    'Business' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=600&auto=format&fit=crop',
                                    'Sports' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&auto=format&fit=crop'
                                ];

                                $default_img = isset($category_placeholders[$event['category']])
                                    ? $category_placeholders[$event['category']]
                                    : 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&auto=format&fit=crop';

                                $image_src = (!empty($event['image_url']) && file_exists('uploads/' . $event['image_url']))
                                    ? 'uploads/' . $event['image_url']
                                    : $default_img;
                                ?>
                                <img src="<?= htmlspecialchars($image_src); ?>" class="card-img-top" alt="<?= htmlspecialchars($event['title']); ?>">
                            </a>

                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <h5 class="event-title mb-3">
                                        <a href="event-details.php?id=<?= $event['event_id']; ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($event['title']); ?>
                                        </a>
                                    </h5>

                                    <div class="meta-item">
                                        <i class="fa-regular fa-calendar-check"></i>
                                        <span><?= date('d M Y • h:i A', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span class="text-truncate"><?= htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="meta-item mb-3">
                                        <i class="fa-regular fa-user"></i>
                                        <span>Hosted by <strong><?= htmlspecialchars($event['organizer_name']); ?></strong></span>
                                    </div>
                                </div>

                                <div class="pt-3 border-top">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="price-badge">
                                            <?= $event['ticket_price'] > 0 ? '$' . number_format($event['ticket_price'], 2) : 'Free'; ?>
                                        </div>
                                        <div class="small fw-semibold <?= ($event['available_tickets'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                            <?= ($event['available_tickets'] > 0) ? $event['available_tickets'] . ' left' : 'Sold Out'; ?>
                                        </div>
                                    </div>

                                    <!-- Two Action Buttons: Details & Book Now -->
                                    <div class="d-flex gap-2">
                                        <a href="event-details.php?id=<?= $event['event_id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill fw-semibold py-2">
                                            <i class="fa-solid fa-circle-info me-1"></i> Details
                                        </a>
                                        
                                        <?php if ($event['available_tickets'] > 0): ?>
                                            <a href="event-details.php?id=<?= $event['event_id']; ?>" class="btn btn-primary-custom btn-sm flex-fill fw-semibold py-2">
                                                Book Now
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm flex-fill fw-semibold py-2" disabled>
                                                Sold Out
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-box my-4">
                <div class="mb-3">
                    <i class="fa-solid fa-calendar-xmark text-muted display-4"></i>
                </div>
                <h4 class="fw-bold text-dark">No Events Found</h4>
                <p class="text-muted max-w-md mx-auto mb-4">We couldn't find any events matching your criteria. Try resetting your search query or choosing another category.</p>
                <a href="index.php" class="btn btn-outline-secondary px-4 py-2">Clear All Filters</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- RESTORED: How It Works Section -->
    <section id="how-it-works" class="py-5 bg-light border-top border-bottom">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-dark">How It Works</h2>
                <p class="text-muted">Simple steps to attend or organize your next event</p>
            </div>
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                        <div class="mb-3 text-primary display-5"><i class="fa-solid fa-magnifying-glass-location"></i></div>
                        <h4 class="fw-bold mb-2">1. Find Events</h4>
                        <p class="text-muted small mb-0">Browse through categories or search by location and dates to find events matching your interest.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                        <div class="mb-3 text-primary display-5"><i class="fa-solid fa-ticket"></i></div>
                        <h4 class="fw-bold mb-2">2. Reserve Tickets</h4>
                        <p class="text-muted small mb-0">Select your tickets instantly with zero hidden fees and instant email confirmation.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white rounded-4 shadow-sm h-100">
                        <div class="mb-3 text-primary display-5"><i class="fa-solid fa-qrcode"></i></div>
                        <h4 class="fw-bold mb-2">3. Enjoy the Experience</h4>
                        <p class="text-muted small mb-0">Show your ticket badge at the entry gate and enjoy an unforgettable experience!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- RESTORED: Organizer CTA Section -->
    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="row align-items-center bg-primary text-white p-4 p-md-5 rounded-4 shadow-sm">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <h3 class="fw-bold mb-2">Are you an Event Organizer?</h3>
                    <p class="mb-0 opacity-75">Publish your events, manage ticket inventory live, and track real-time bookings from your personal dashboard.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-light btn-lg rounded-pill fw-bold text-primary px-4 py-2">Host an Event</a>
                </div>
            </div>
        </div>
    </section>

    <!-- RESTORED: Complete Footer -->
    <footer class="bg-white border-top pt-5 pb-4">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-5">
                    <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-bolt text-primary"></i> Event Management Portal
                    </h5>
                    <p class="text-muted small pe-md-4">
                        Discover, create, and manage tickets for premier conferences, tech summits, live concerts, and sporting events worldwide.
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-bold text-dark mb-3">Quick Links</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><a href="index.php" class="text-decoration-none text-muted">Explore Events</a></li>
                        <li class="mb-2"><a href="#how-it-works" class="text-decoration-none text-muted">How It Works</a></li>
                        <li class="mb-2"><a href="register.php" class="text-decoration-none text-muted">Register Account</a></li>
                        <li class="mb-2"><a href="login.php" class="text-decoration-none text-muted">Sign In</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold text-dark mb-3">Event Categories</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="index.php?category=Technology" class="btn btn-sm btn-light text-muted">Technology</a>
                        <a href="index.php?category=Music" class="btn btn-sm btn-light text-muted">Music</a>
                        <a href="index.php?category=Business" class="btn btn-sm btn-light text-muted">Business</a>
                        <a href="index.php?category=Sports" class="btn btn-sm btn-light text-muted">Sports</a>
                    </div>
                </div>
            </div>
            <div class="border-top pt-3 text-center text-muted small">
                <p class="mb-0">&copy; <?= date('Y'); ?> Event Management Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>

</body>

</html>