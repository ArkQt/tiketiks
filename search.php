<?php
session_start();
require_once 'config.php';

// Get search query from URL parameter
$search_query = $_GET['q'] ?? '';
$search_results = [];

if (!empty($search_query)) {
    $conn = getDBConnection();
    
    // Search in movies (both now showing and coming soon)
    $search_query_escaped = $conn->real_escape_string($search_query);
    
    // Search in MOVIE table
    $movie_sql = "
    SELECT 
        m.movie_show_id,
        m.title,
        m.genre,
        m.duration,
        m.rating,
        m.movie_descrp,
        m.image_poster,
        MIN(ms.show_date) AS show_date,
        MIN(ms.show_hour) AS show_hour,
        CASE 
            WHEN MIN(ms.show_date) >= CURDATE() THEN 'now-showing'
            ELSE 'coming-soon'
        END AS movie_status
    FROM MOVIE m
    LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
    WHERE m.title LIKE '%$search_query_escaped%' 
       OR m.genre LIKE '%$search_query_escaped%' 
       OR m.movie_descrp LIKE '%$search_query_escaped%'
    GROUP BY 
        m.movie_show_id, 
        m.title, 
        m.genre, 
        m.duration, 
        m.rating, 
        m.movie_descrp, 
        m.image_poster
    ORDER BY m.title;
";

    
    $movie_result = $conn->query($movie_sql);
    
    if ($movie_result && $movie_result->num_rows > 0) {
        while ($row = $movie_result->fetch_assoc()) {
            $search_results[] = [
                'type' => 'movie',
                'title' => $row['title'],
                'genre' => $row['genre'],
                'duration' => $row['duration'],
                'rating' => $row['rating'],
                'description' => $row['movie_descrp'],
                'image' => $row['image_poster'],
                'status' => $row['movie_status'],
                'show_date' => $row['show_date'],
                'show_hour' => $row['show_hour']
            ];
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Ticketix</title>
    <link rel="icon" type="image/png" href="brand x.png">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/ticketix-main.css">
    <style>
        .search-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .search-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .search-form {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .search-input {
            padding: 15px 20px;
            font-size: 16px;
            border: 2px solid #00BFFF;
            border-radius: 25px 0 0 25px;
            width: 400px;
            outline: none;
        }
        
        .search-btn {
            padding: 15px 25px;
            background: linear-gradient(to right, #00BFFF, #3C50B2);
            color: white;
            border: none;
            border-radius: 0 25px 25px 0;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .search-btn:hover {
            background: linear-gradient(to right, #3C50B2, #00BFFF);
        }
        
        .search-results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .search-result-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .search-result-card:hover {
            transform: translateY(-5px);
        }
        
        .search-result-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .search-result-content {
            padding: 20px;
        }
        
        .search-result-title {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .search-result-genre {
            color: #666;
            margin-bottom: 8px;
        }
        
        .search-result-duration {
            color: #888;
            margin-bottom: 10px;
        }
        
        .search-result-description {
            color: #555;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        
        .search-result-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .status-now-showing {
            background: #4CAF50;
            color: white;
        }
        
        .status-coming-soon {
            background: #FF9800;
            color: white;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-results h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .back-to-home {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: linear-gradient(to right, #00BFFF, #3C50B2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: transform 0.3s ease;
        }
        
        .back-to-home:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="left-section">
            <div class="logo">
                <img src="images/brand x.png" alt="images/logo_sha">
            </div>
            <nav>
                <a href="TICKETIX NI CLAIRE.php#home">Home</a>
                <a href="TICKETIX NI CLAIRE.php#now-showing">Now Showing</a>
                <a href="TICKETIX NI CLAIRE.php#coming-soon">Coming Soon</a>
                <a href="TICKETIX NI CLAIRE.php#contact">Contact Us</a>
            </nav>
        </div>
        <div class="right-section">
            <button class="ticket-btn">Tickets</button>
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="logout.php" class="login-link"><i class="user-icon"></i> Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-link"><i class="user-icon"></i> Log In / Sign Up</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="search-container">
        <div class="search-header">
            <h1>Search Movies</h1>
            <p>Find your favorite movies and discover new ones</p>
        </div>
        
        <form class="search-form" method="GET" action="search.php">
            <input type="text" name="q" class="search-input" placeholder="Search for movies, genres, or descriptions..." value="<?php echo htmlspecialchars($search_query); ?>" required>
            <button type="submit" class="search-btn">Search</button>
        </form>
        
        <?php if (!empty($search_query)): ?>
            <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
            
            <?php if (!empty($search_results)): ?>
                <div class="search-results">
                    <?php foreach ($search_results as $result): ?>
                        <div class="search-result-card">
                            <?php if (!empty($result['image'])): ?>
                                <img src="<?php echo htmlspecialchars($result['image']); ?>" alt="<?php echo htmlspecialchars($result['title']); ?>" class="search-result-image">
                            <?php else: ?>
                                <div class="search-result-image" style="background: linear-gradient(135deg, #00BFFF, #3C50B2); display: flex; align-items: center; justify-content: center; color: white; font-size: 3em;">🎬</div>
                            <?php endif; ?>
                            
                            <div class="search-result-content">
                                <h3 class="search-result-title"><?php echo htmlspecialchars($result['title']); ?></h3>
                                <p class="search-result-genre"><?php echo htmlspecialchars($result['genre']); ?></p>
                                <p class="search-result-duration"><?php echo htmlspecialchars($result['duration']); ?> minutes</p>
                                
                                <span class="search-result-status status-<?php echo $result['status']; ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $result['status'])); ?>
                                </span>
                                
                                <?php if (!empty($result['description'])): ?>
                                    <p class="search-result-description"><?php echo htmlspecialchars(substr($result['description'], 0, 150)) . (strlen($result['description']) > 150 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($result['status'] === 'now-showing'): ?>
                                    <button class="btn ticket-btn" onclick="window.location.href='TICKETIX NI CLAIRE.php#now-showing'">Book Tickets</button>
                                <?php else: ?>
                                    <button class="btn notify-btn" onclick="alert('We will notify you when this movie becomes available!')">Notify Me</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <h3>No movies found</h3>
                    <p>Sorry, we couldn't find any movies matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    <p>Try searching with different keywords or check the spelling.</p>
                    <a href="TICKETIX NI CLAIRE.php" class="back-to-home">Back to Home</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <h3>Start Your Search</h3>
                <p>Enter a movie title, genre, or description to find what you're looking for.</p>
                <a href="TICKETIX NI CLAIRE.php" class="back-to-home">Back to Home</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
