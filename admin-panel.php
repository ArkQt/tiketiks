<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// --- Fetch Stats ---
$totalUsersQuery = $conn->query("SELECT COUNT(*) AS total FROM USER_ACCOUNT WHERE role = 'user'");
$totalUsers = $totalUsersQuery ? $totalUsersQuery->fetch_assoc()['total'] ?? 0 : 0;

$totalBookingsQuery = $conn->query("SELECT COUNT(*) AS total FROM RESERVE");
$totalBookings = $totalBookingsQuery ? $totalBookingsQuery->fetch_assoc()['total'] ?? 0 : 0;

$totalRevenueQuery = $conn->query("SELECT IFNULL(SUM(amount_paid), 0) AS total FROM PAYMENT WHERE payment_status = 'paid'");
$totalRevenue = $totalRevenueQuery ? $totalRevenueQuery->fetch_assoc()['total'] ?? 0 : 0;

// --- Fetch Movies ---
$today = date('Y-m-d');

// Now Showing (latest shows <= today)
$nowShowingResult = $conn->query("
    SELECT m.*
    FROM MOVIE m
    INNER JOIN (
        SELECT movie_show_id, MAX(show_date) AS last_show
        FROM MOVIE_SCHEDULE
        WHERE show_date <= '$today'
        GROUP BY movie_show_id
    ) ms ON m.movie_show_id = ms.movie_show_id
    ORDER BY ms.last_show DESC
    LIMIT 5
");

$movies = [];
if ($nowShowingResult) {
    while ($row = $nowShowingResult->fetch_assoc()) {
        $movies[] = $row;
    }
}
$activeMovies = count($movies); // safe even if $movies is empty

// Coming Soon (next shows > today)
$comingSoonResult = $conn->query("
    SELECT m.*
    FROM MOVIE m
    INNER JOIN (
        SELECT movie_show_id, MIN(show_date) AS next_show
        FROM MOVIE_SCHEDULE
        WHERE show_date > '$today'
        GROUP BY movie_show_id
    ) ms ON m.movie_show_id = ms.movie_show_id
    ORDER BY ms.next_show ASC
    LIMIT 5
");

$comingSoon = [];
if ($comingSoonResult) {
    while ($row = $comingSoonResult->fetch_assoc()) {
        $comingSoon[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
</head>
<body>
    <aside class="sidebar">
        <div class="profile-section">
            <img src="images/brand x.png" alt="Profile Picture" class="profile-pic" />
            <h2>Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="admin-panel.php" class="active">Dashboard</a>
            <a href="add-show.php">Add Shows</a>
            <a href="view-shows.php">List Shows</a>
            <a href="view-bookings.php">List Bookings</a>
        </nav>
    </aside>
    <main class="main-content">
        <header>
            <h1>Admin <span class="highlight">Dashboard</span></h1>
        </header>

        <section class="stats-cards">
            <div class="card">
                <div class="card-info">
                    <label>Total Bookings</label>
                    <h3><?= $totalBookings ?></h3>
                </div>
                <div class="card-icon">📈</div>
            </div>
            <div class="card">
                <div class="card-info">
                    <label>Total Revenue</label>
                    <h3>₱<?= number_format($totalRevenue, 2) ?></h3>
                </div>
                <div class="card-icon">💰</div>
            </div>
            <div class="card">
                <div class="card-info">
                    <label>Active Movies</label>
                    <h3><?= $activeMovies ?></h3>
                </div>
                <div class="card-icon">🎬</div>
            </div>
            <div class="card">
                <div class="card-info">
                    <label>Total Users</label>
                    <h3><?= $totalUsers ?></h3>
                </div>
                <div class="card-icon">👥</div>
            </div>
        </section>

        <!-- Now Showing Movies -->
        <section class="active-movies">
            <h2>Now Showing</h2>
            <div class="movies-list">
                <?php if (count($nowShowing) > 0): ?>
                    <?php foreach ($nowShowing as $movie): ?>
                        <div class="movie-card">
                            <img src="<?= htmlspecialchars($movie['image_poster'] ?: 'images/default.png') ?>" 
                                 alt="<?= htmlspecialchars($movie['title']) ?>" />
                            <div class="movie-info">
                                <h3><?= htmlspecialchars($movie['title']) ?></h3>
                                <div class="price-rating">
                                    <span class="price"><?= htmlspecialchars($movie['genre']) ?></span>
                                    <span class="rating">⭐ <?= htmlspecialchars($movie['rating'] ?: 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No movies currently showing.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Coming Soon Movies -->
        <section class="coming-soon">
            <h2>Coming Soon</h2>
            <div class="movies-list">
                <?php if (count($comingSoon) > 0): ?>
                    <?php foreach ($comingSoon as $movie): ?>
                        <div class="movie-card">
                            <img src="<?= htmlspecialchars($movie['image_poster'] ?: 'images/default.png') ?>" 
                                 alt="<?= htmlspecialchars($movie['title']) ?>" />
                            <div class="movie-info">
                                <h3><?= htmlspecialchars($movie['title']) ?></h3>
                                <div class="price-rating">
                                    <span class="price"><?= htmlspecialchars($movie['genre']) ?></span>
                                    <span class="rating">⭐ <?= htmlspecialchars($movie['rating'] ?: 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No upcoming movies.</p>
                <?php endif; ?>
            </div>
        </section>

    </main>
</body>
</html>
